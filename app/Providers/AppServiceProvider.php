<?php

namespace App\Providers;

use App\Models\EpidemicRecord;
use App\Observers\EpidemicRecordObserver;
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
        EpidemicRecord::observe(EpidemicRecordObserver::class);
    }
}
