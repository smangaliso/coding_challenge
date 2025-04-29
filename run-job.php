<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel to use config()
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Symfony\Component\Process\Process;
use Illuminate\Support\Str;

date_default_timezone_set('UTC');

// === Helper Logging Functions ===

function logJob($message) {
    $logFile = storage_path('logs/background_jobs.log');
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
}

function logError($message) {
    $logFile = storage_path('logs/background_jobs_errors.log');
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
}



function isAllowedJob($class, $method) {
    $allowed = config('background-jobs.allowed_jobs');
    return isset($allowed[$class]) && in_array($method, $allowed[$class]);
}



function runInBackground($class, $method, $params = []) {
    $cmd = array_merge(['php', base_path('run-job.php'), $class, $method], $params);
    $process = new Process($cmd);
    $process->start();
}


function executeJob($class, $method, $params = [], $retryMap = [], $attempt = 1) {
    try {
        if (!class_exists($class)) {
            throw new Exception("Class '$class' does not exist.");
        }

        $instance = new $class();

        if (!method_exists($instance, $method)) {
            throw new Exception("Method '$method' not found in class '$class'.");
        }

        $reflection = new ReflectionMethod($class, $method);
        $result = $reflection->invokeArgs($instance, $params);

        logJob("SUCCESS: $class::$method with params (" . implode(',', $params) . ")");

      
        if (is_array($result) && isset($result['chain'])) {
            foreach ($result['chain'] as $job) {
                $chainClass = $job['class'];
                $chainMethod = $job['method'];
                $chainParams = $job['params'] ?? [];

                if (isAllowedJob($chainClass, $chainMethod)) {
                    runInBackground($chainClass, $chainMethod, $chainParams);
                } else {
                    logError("UNAUTHORIZED: $chainClass::$chainMethod");
                }
            }
        }

    } catch (Throwable $e) {
        $message = "FAILURE: $class::$method | " . get_class($e) . " | " . $e->getMessage();
        logJob($message);
        logError($message);

     
        $retryMap = array_merge([
            RuntimeException::class => 3,
            InvalidArgumentException::class => 2,
            ArgumentCountError::class => 3,
            TypeError::class => 2,
        ], $retryMap);

        foreach ($retryMap as $exceptionType => $maxAttempts) {
            if ($e instanceof $exceptionType && $attempt < $maxAttempts) {
                logJob("Retrying $class::$method (Attempt $attempt)");
                sleep(2); // Delay before retry
                executeJob($class, $method, $params, $retryMap, $attempt + 1);
                return;
            }
        }
    }
}



if (php_sapi_name() === 'cli' && isset($argv[1], $argv[2])) {
    $classInput = trim($argv[1]);
    $method = trim($argv[2]);
    $params = array_slice($argv, 3);

    $class = class_exists($classInput) ? $classInput : 'App\\Jobs\\' . $classInput;

    if (!isAllowedJob($class, $method)) {
        echo "Unauthorized job: $class::$method\n";
        logError("UNAUTHORIZED: $class::$method");
        exit(1);
    }

    executeJob($class, $method, $params);

} else {
    echo "Usage: php run-job.php ClassName methodName param1 param2 ...\n";
    exit(1);
}
