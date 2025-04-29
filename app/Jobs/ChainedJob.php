<?php

namespace App\Jobs;

class ChainedJob
{
    public function handle($param1, $param2)
    {
        echo "ChainedJob is handling: $param1 and $param2\n";
       
    }
}
