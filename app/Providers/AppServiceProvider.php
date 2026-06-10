<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AttendanceRecord;
use App\Observers\AttendanceRecordObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ATT-4..6: Auto-deduct ledger when an absence is recorded
        AttendanceRecord::observe(AttendanceRecordObserver::class);
    }
}

