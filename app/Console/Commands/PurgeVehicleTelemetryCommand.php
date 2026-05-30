<?php

namespace App\Console\Commands;

use App\Models\VehicleTelemetry;
use Illuminate\Console\Command;

class PurgeVehicleTelemetryCommand extends Command
{
    protected $signature = 'telemetry:purge-old';

    protected $description = 'Purge vehicle telemetry records older than 12 hours.';

    public function handle(): int
    {
        $deleted = VehicleTelemetry::query()
            ->where('created_at', '<', now()->subHours(12))
            ->delete();

        $this->info("Purged {$deleted} telemetry records.");

        return self::SUCCESS;
    }
}

