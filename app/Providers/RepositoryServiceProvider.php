<?php

namespace App\Providers;

use App\Repositories\Contracts\EpidemicRepositoryInterface;
use App\Repositories\Eloquent\PostgisEpidemicRepository;
use App\Repositories\Eloquent\SqliteEpidemicRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(EpidemicRepositoryInterface::class, function () {
            $driver = DB::getDriverName();

            return match ($driver) {
                'pgsql' => new PostgisEpidemicRepository,
                'sqlite' => new SqliteEpidemicRepository,
                default => throw new \RuntimeException("Database driver [{$driver}] is not supported by Monitor Saúde Spatial Repositories."),
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
