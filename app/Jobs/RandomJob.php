<?php
namespace App\Jobs;

class RandomJob
{
    public function handle($param1, $param2)
    {
        echo "Running random jobs with params: $param1, $param2";
    }
}



?>