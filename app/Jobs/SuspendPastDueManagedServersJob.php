<?php

namespace App\Jobs;

use App\Actions\Provisioning\SuspendPastDueManagedServers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SuspendPastDueManagedServersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function handle(): void
    {
        $result = SuspendPastDueManagedServers::run();
        if ($result['examined'] > 0) {
            Log::info('SuspendPastDueManagedServersJob complete', $result);
        }
    }
}
