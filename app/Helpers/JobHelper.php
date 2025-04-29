<?php

use Illuminate\Container\Attributes\Log;
use Symfony\Component\Process\Process;

if (! function_exists('runBackgroundJob')) {

    function runBackgroundJob(string $class, string $method, array $params = []): string
    {
        $jobId = uniqid('job_', true);

      
        $php    = PHP_BINARY;
        $script = base_path('run-job.php');
        $args   = array_merge([$class, $method], $params);


       
        $args   = array_map('escapeshellarg', $args);
        $process = new Process(array_merge([$php, $script], $args));
        $process->start();

        $pidFile = storage_path('logs/running_jobs.json');
        $running = file_exists($pidFile)
            ? json_decode(file_get_contents($pidFile), true)
            : [];

        $running[] = [
            'job_id'     => $jobId,
            'pid'        => $process->getPid(),
            'class'      => $class,
            'method'     => $method,
            'params'     => $params,
            'script'     => $script,
            'started_at' => date('Y-m-d H:i:s'),
        ];

        file_put_contents($pidFile, json_encode($running, JSON_PRETTY_PRINT));

        return $jobId;
    }
}
