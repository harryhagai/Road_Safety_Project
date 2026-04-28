<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\ReportConfig;
use App\Services\ReportRowService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reports:backfill {--config_id=} {--class_id=} {--roadofficer_year_id=}', function (ReportRowService $reportRowService) {
    $configs = ReportConfig::query()
        ->with('exams')
        ->when($this->option('config_id'), fn ($query, $configId) => $query->where('id', $configId))
        ->get();

    foreach ($configs as $config) {
        $classIds = $this->option('class_id')
            ? [(int) $this->option('class_id')]
            : \App\Models\Student::query()
                ->when($this->option('roadofficer_year_id'), fn ($query, $yearId) => $query->where('roadofficer_year_id', $yearId))
                ->distinct()
                ->pluck('class_id')
                ->filter()
                ->all();

        foreach ($classIds as $classId) {
            $roadofficerYearIds = $this->option('roadofficer_year_id')
                ? [(int) $this->option('roadofficer_year_id')]
                : \App\Models\Student::query()
                    ->where('class_id', $classId)
                    ->distinct()
                    ->pluck('roadofficer_year_id')
                    ->filter()
                    ->all();

            foreach ($roadofficerYearIds as $roadofficerYearId) {
                $reportRowService->recalculateClassRows($config, (int) $classId, (int) $roadofficerYearId);
                $this->info("Backfilled config {$config->id} for class {$classId}, year {$roadofficerYearId}");
            }
        }
    }
})->purpose('Backfill computed report rows');
