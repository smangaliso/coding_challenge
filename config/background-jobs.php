<?php

return [
    'allowed_jobs' => [
        App\Jobs\SampleJob::class => ['handle'],
        App\Jobs\ChainedJob::class => ['handle'],
    ],
];
