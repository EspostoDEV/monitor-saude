<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\EpidemicRecord;
use App\Observers\EpidemicRecordObserver;

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
