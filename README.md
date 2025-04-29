<h1>Background Job Executor</h1>
<h2>Overview</h2>
This is a PHP script to execute background jobs using a Symfony Process. The script allows you to run jobs in the background, handle retries, and log job status.

**Features:**

- Execute background jobs.

- Retry on failure with configurable attempts and delays.

- Log job status (running, completed, failed).

- Support for whitelisting of allowed job classes and methods.

- Chain jobs and execute them sequentially.

<h2>Setup Guide</h2>
<p>Follow the steps below to clone the project, configure your environment, and run the migrations.</p>
<h3>Step 1: Clone the Repository</h3>


```
git clone https://github.com/smangaliso/coding_challenge.git 
```

navigate into the project directory
```
cd coding_challenge
```

install dependencies
```
composer install
```
<h3>Step 2: Create a MySQL Database</h3>

1. **Log in to your MySQL server.**

```
mysql -u root -p
```

2. **Create a new database for your application**

```
CREATE DATABASE coding_challenge;
```

3. **Exit MySQL**

```
exit;
```

<h3>Step 3: Configure the .env File</h3>

1. **Copy the example environment file:**

- Manually copy the file named `.env.example` and rename it to `.env` in your project directory.

2. **Open the .env file and configure the database settings:**

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=coding_challenge
DB_USERNAME=root
DB_PASSWORD=your_password
```
Make sure to replace `root`, and `your_password` with the appropriate values.

<h3>Step 4: Run Migrations</h3>

```
php artisan migrate
```

<h3>Step 5: Configuration</h3>

**Whitelist Jobs and Methods**

All allowed job classes and their methods are configured in config/background-jobs.php.

```
return [
    'allowed_jobs' => [
        'SampleJob' => ['handle'],
        'ChainedJob' => ['handle'],
    ]
];

```

**Retry Configuration**
Retry attempts and delays are configured in the script run-job.php:
```
$maxAttempts = 3;  // Max retry attempts
$retryDelay = 2;   // Delay between retries (in seconds)

```

You can also define retries based on exception types:

```
$retryMap = [
    \RuntimeException::class => 3,
    \InvalidArgumentException::class => 2,
    \ArgumentCountError::class => 2,
    \Exception::class => 2,
];
```

To execute a job in the background, run the following command in your terminal:

```
php run-job.php SampleJob handle param1,param2

```

to test unauthorized jobs

```
php run-job.php RandomJob handle param1,param2

```

you will see an error in log files
```
[2025-04-29 13:07:37] FAILED: App\Jobs\RandomJob::handle | Exception | Unauthorized job: App\Jobs\RandomJob::handle
```

<h2>Job Logging</h2>

Job executions are logged in the following files:

- Job status logs: storage/logs/background_jobs.log

- Error logs: storage/logs/background_jobs_errors.log

**Example of Log Entries**

<b>Job Started:</b>

```
[2025-04-29 13:07:35] RUNNING: App\Jobs\RandomJob::handle attempt 1
```

<b>Job Completed:</b>

```
[2025-04-29 12:50:01] COMPLETED: App\Jobs\SampleJob::handle with params (param1, param2)
```

<b>Job Failed:</b>

```
[2025-04-29 12:56:46] FAILED: App\Jobs\SampleJob::handle | RuntimeException | Intentional failure.
```



## Application Usage: `runBackgroundJob()` Helper

A global PHP helper function `runBackgroundJob($class, $method, $params = [])` exists in the application to trigger background jobs programmatically.

Currently, this helper is not demonstrated in this README because it will be **showcased through the Dashboard Interface**

The dashboard will:
- Show job statuses (running, completed, failed).
- Show retry count per job.
- Allow cancellation of running jobs.



**To access the dashboard**

```
php artisan serve
```


