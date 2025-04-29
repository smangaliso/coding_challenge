<?php

require __DIR__ . '/vendor/autoload.php';

$app    = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Symfony\Component\Process\Process;

date_default_timezone_set('UTC');

define('JOB_LOG',   storage_path('logs/background_jobs.log'));
define('ERROR_LOG', storage_path('logs/background_jobs_errors.log'));
define('QUEUE_FILE', storage_path('logs/job_queue.json'));

foreach ([dirname(JOB_LOG), dirname(QUEUE_FILE)] as $dir) {
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}
if (! file_exists(JOB_LOG))   file_put_contents(JOB_LOG, '');
if (! file_exists(ERROR_LOG)) file_put_contents(ERROR_LOG, '');
if (! file_exists(QUEUE_FILE)) file_put_contents(QUEUE_FILE, json_encode([], JSON_PRETTY_PRINT));


function logJob(string $msg) {
    file_put_contents(JOB_LOG,   '['.date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND);
}
function logError(string $msg) {
    file_put_contents(ERROR_LOG, '['.date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND);
}

function resolveJobClass(string $c): string {
    return class_exists($c) ? $c : 'App\\Jobs\\' . $c;
}


function runInBackground(string $class, string $method, array $params = []): void {
    $class = resolveJobClass($class);
    $php   = PHP_BINARY;
    $script= __FILE__;

    $esc = array_map('escapeshellarg', $params);
    $cmd = array_merge([$php, $script, $class, $method], $esc);

    $process = new Process($cmd);
    $process->start();

    $pid = $process->getPid();
    if ($pid) {
        $runningFile = storage_path('logs/running_jobs.json');
        $running = json_decode(file_get_contents($runningFile), true) ?: [];
        $running[] = [
            'pid'        => $pid,
            'class'      => $class,
            'method'     => $method,
            'params'     => $params,
            'started_at' => date('Y-m-d H:i:s')
        ];
        file_put_contents($runningFile, json_encode($running, JSON_PRETTY_PRINT));
    }
}


function processQueue(): void {
    $queue = json_decode(file_get_contents(QUEUE_FILE), true);
    $now   = time();

    
    $due = array_filter($queue, fn($j) => $j['scheduled_at'] <= $now);
    if (empty($due)) {
        return;
    }

    usort($due, function($a, $b) {
        if ($a['priority'] !== $b['priority']) {
            return $b['priority'] <=> $a['priority'];
        }
        return $a['scheduled_at'] <=> $b['scheduled_at'];
    });

    // dispatch each due job in background
    foreach ($due as $job) {
        $class  = $job['class'];
        $method = $job['method'];
        $params = $job['params'] ?? [];

        logJob("DISPATCHING: $class::$method (priority {$job['priority']})");
        runInBackground($class, $method, $params);
    }

    // remove dispatched jobs from queue
    $remaining = array_filter(
        $queue,
        fn($j) => $j['scheduled_at'] > $now
    );

    file_put_contents(QUEUE_FILE, json_encode(array_values($remaining), JSON_PRETTY_PRINT));
}


if (php_sapi_name() === 'cli') {
    global $argv;

    //process queue first
    if (isset($argv[1]) && $argv[1] === 'process-queue') {
        processQueue();
        exit;
    }

    // direct job execution
    if (isset($argv[1], $argv[2])) {
        $class  = resolveJobClass($argv[1]);
        $method = $argv[2];
        $params = array_slice($argv, 3);

        logJob("RUNNING: $class::$method");

        try {
            if (! class_exists($class)) {
                throw new Exception("Class $class not found");
            }
            $inst = new $class;
            if (! method_exists($inst, $method)) {
                throw new Exception("Method $method not found on $class");
            }
            call_user_func_array([$inst, $method], $params);
            logJob("COMPLETED: $class::$method");

            // chaining 
            if ($result = $inst->$method(...$params)) {
                if (is_array($result) && isset($result['chain'])) {
                    foreach ($result['chain'] as $c) {
                        $cc = $c['class'];
                        runInBackground($cc, $c['method'], $c['params'] ?? []);
                        logJob("CHAINED: $cc::{$c['method']} queued");
                    }
                }
            }
        } catch (Throwable $e) {
            $msg = "FAILED: $class::$method | ".get_class($e)." | ".$e->getMessage();
            logJob($msg);
            logError($msg."\n".$e->getTraceAsString());
        }
        exit;
    }

    echo "Usage:\n"
       . "  php run-job.php process-queue\n"
       . "  php run-job.php ClassName methodName [param1 param2 ...]\n";
    exit(1);
}
