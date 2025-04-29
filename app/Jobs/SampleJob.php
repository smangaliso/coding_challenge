<?php
namespace App\Jobs;
use RuntimeException;
class SampleJob {
    public function handle($param1, $param2) {
        echo "Running SampleJob with $param1 and $param2\n";

        if ($param1 === 'fail') {
            throw new RuntimeException("Intentional failure.");
        }

        for ($i = 1; $i <=5; $i++){
            echo "Processing step $i...\n";
            sleep(3); // this is just to simulate the cancellation of running jobs in the dashboard

        }

        return [
            'chain' => [
                ['class' => 'ChainedJob', 'method' => 'handle', 'params' => ['next1', 'next2']]
            ]
        ];
    }
}
