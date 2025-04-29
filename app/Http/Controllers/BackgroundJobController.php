<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class BackgroundJobController extends Controller
{
    protected $runningJobsFile;

    public function __construct()
    {
        $this->runningJobsFile = storage_path('logs/running_jobs.json');

       
        $dir = dirname($this->runningJobsFile);
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0777, true);
        }
        if (! File::exists($this->runningJobsFile)) {
            File::put($this->runningJobsFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    public function index()
    {
        $jobLog   = storage_path('logs/background_jobs.log');
        $errorLog = storage_path('logs/background_jobs_errors.log');

        $jobs   = File::exists($jobLog)   ? array_reverse(File::lines($jobLog)->toArray())   : [];
        $errors = File::exists($errorLog) ? array_reverse(File::lines($errorLog)->toArray()) : [];

        $runningRaw = json_decode(File::get($this->runningJobsFile), true) ?? [];
        $runningJobs = array_map(function($job) {
            return [
                'job_id'     => $job['job_id'] ?? ($job['pid'] ?? null),
                'pid'        => $job['pid'] ?? null,
                'class'      => $job['class'] ?? '',
                'method'     => $job['method'] ?? '',
                'params'     => $job['params'] ?? [],
                'started_at' => $job['started_at'] ?? '',
            ];
        }, $runningRaw);

        return view('jobs.index', [
            'runningJobs' => $runningJobs,
            'jobs'        => $jobs,
            'errors'      => $errors,
        ]);
    }


    public function launch(Request $request)
    {
        $data = $request->validate([
            'class'  => 'required|string',
            'method' => 'required|string',
            'params' => 'nullable|string',
        ]);

        $class  = trim($data['class']);
        $method = trim($data['method']);
        $params = $data['params']
            ? array_map('trim', explode(',', $data['params']))
            : [];

       
        $jobId = runBackgroundJob($class, $method, $params);

        return back()->with('success',
            "Launched job {$class}::{$method} (ID: {$jobId})
            (Params: {$params[0]})"
        );
    }

  
    public function cancel(Request $request)
    {
        $request->validate([
            'job_id' => 'required|string',
        ]);

        $jobId  = $request->input('job_id');
        $running = json_decode(File::get($this->runningJobsFile), true) ?? [];

        foreach ($running as $i => $job) {
            $id = $job['job_id'] ?? ($job['pid'] ?? '');
            if ((string)$id === $jobId) {
             
                if (! empty($job['pid'])) {
                    if (strtoupper(substr(PHP_OS,0,3)) === 'WIN') {
                        exec("taskkill /F /PID {$job['pid']}");
                    } else {
                        exec("kill -9 {$job['pid']}");
                    }
                }
               
                array_splice($running, $i, 1);
                File::put($this->runningJobsFile, json_encode(array_values($running), JSON_PRETTY_PRINT));
                return back()->with('success', "Cancelled job {$jobId}");
            }
        }

        return back()->with('error', "Job ID {$jobId} not found.");
    }
}
