<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class BackgroundJobController extends Controller
{
    protected $runningJobsFile;
    protected $queueFile;

    public function __construct()
    {
        $this->runningJobsFile = storage_path('logs/running_jobs.json');
        $this->queueFile       = storage_path('logs/job_queue.json');

        foreach ([$this->runningJobsFile, $this->queueFile] as $file) {
            $dir = dirname($file);
            if (! File::exists($dir)) {
                File::makeDirectory($dir, 0777, true);
            }
            if (! File::exists($file)) {
                File::put($file, json_encode([], JSON_PRETTY_PRINT));
            }
        }
    }

 
    public function index()
    {
        $jobsPath   = storage_path('logs/background_jobs.log');
        $errorsPath = storage_path('logs/background_jobs_errors.log');

        $jobs   = File::exists($jobsPath)   ? array_reverse(File::lines($jobsPath)->toArray())   : [];
        $errors = File::exists($errorsPath) ? array_reverse(File::lines($errorsPath)->toArray()) : [];

        // Pending queue entries already have a job_id
        $queue = json_decode(File::get($this->queueFile), true);

        // Running jobs: ensure each has job_id 
        $rawRunning = json_decode(File::get($this->runningJobsFile), true);
        $runningJobs = array_map(function($job) {
            return [
                'job_id'     => $job['job_id'] ?? ($job['pid'] ?? null),
                'pid'        => $job['pid'] ?? null,
                'class'      => $job['class'] ?? '',
                'method'     => $job['method'] ?? '',
                'params'     => $job['params'] ?? [],
                'started_at' => $job['started_at'] ?? '',
            ];
        }, $rawRunning);

        return view('jobs.index', [
            'queue'       => $queue,
            'runningJobs' => $runningJobs,
            'jobs'        => $jobs,
            'errors'      => $errors,
        ]);
    }

   
    public function launch(Request $request)
    {
        $data = $request->validate([
            'class'    => 'required|string',
            'method'   => 'required|string',
            'params'   => 'nullable|string',
            'delay'    => 'nullable|integer|min:0',
            'priority' => 'nullable|integer|min:0',
        ]);

        $class    = trim($data['class']);
        $method   = trim($data['method']);
        $params   = $data['params']
                   ? array_map('trim', explode(',', $data['params']))
                   : [];
        $delay    = $data['delay']    ?? 0;
        $priority = $data['priority'] ?? 0;

        
        $jobId = runBackgroundJob($class, $method, $params, $delay, $priority);

        return back()->with('success',
            "Enqueued {$class}::{$method} as job ID {$jobId}"
        );
    }

    public function cancel(Request $request)
    {
        $request->validate([
            'job_id' => 'required|string',
        ]);

        $jobId = $request->input('job_id');
        $running = json_decode(File::get($this->runningJobsFile), true);

        foreach ($running as $idx => $job) {
            $id = $job['job_id'] ?? ($job['pid'] ?? '');
            if ($id === $jobId) {
               
                if (! empty($job['pid'])) {
                    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                        exec("taskkill /F /PID {$job['pid']}");
                    } else {
                        exec("kill -9 {$job['pid']}");
                    }
                }
        
                array_splice($running, $idx, 1);
                File::put($this->runningJobsFile,
                    json_encode(array_values($running), JSON_PRETTY_PRINT)
                );
                return back()->with('success', "Cancelled job {$jobId}");
            }
        }

        return back()->with('error', "Job ID {$jobId} not found or already finished.");
    }
}
