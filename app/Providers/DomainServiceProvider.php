<?php

namespace App\Providers;

use App\Domains\Facilities\Facility\Repositories\FacilityRepository;
use App\Domains\Facilities\Facility\Repositories\FacilityRepositoryInterface;
use App\Domains\Users\User\Repositories\UserRepository;
use App\Domains\Users\User\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * One place for every module: bind repository interfaces, load route files.
 * New module = one bind() below + one route file in the list.
 */
class DomainServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        UserRepositoryInterface::class => UserRepository::class,
        FacilityRepositoryInterface::class => FacilityRepository::class,
    ];

    /** @var list<string> */
    private const ROUTE_FILES = [
        'routes/auth/auth.php',
        'routes/facilities/facilities.php',
    ];

    public function boot(): void
    {
        Route::middleware('api')->prefix('api')->group(function () {
            foreach (self::ROUTE_FILES as $file) {
                require base_path($file);
            }
        });
    }
}
