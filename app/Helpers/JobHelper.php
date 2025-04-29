<?php

if (! function_exists('runBackgroundJob')) {

    function runBackgroundJob(string $class, string $method, array $params= [], int $delay=0, int $priority=0) {
        $php    = PHP_BINARY;
        $script = base_path('run-job.php');
        $jobId  = uniqid('jq_', true);

        $queueFile = storage_path('logs/job_queue.json');
        if (! file_exists(dirname($queueFile))) {
            mkdir(dirname($queueFile), 0777, true);
        }
        $queue = file_exists($queueFile)
              ? json_decode(file_get_contents($queueFile), true)
              : [];

        $queue[] = [
            'job_id'       => $jobId,
            'class'        => $class,
            'method'       => $method,
            'params'       => $params,
            'scheduled_at' => time() + $delay,
            'priority'     => $priority,
        ];

        file_put_contents($queueFile, json_encode($queue, JSON_PRETTY_PRINT));

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            pclose(popen("start /B cmd /c \"$php $script process-queue\"", 'r'));
        } else {
            // Unix / Mac
            exec("$php $script process-queue > /dev/null 2>&1 &");
        }
    }
}
