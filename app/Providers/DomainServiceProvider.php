<?php

namespace App\Providers;

use App\Domains\Audit\AuditLog\Repositories\AuditLogRepository;
use App\Domains\Audit\AuditLog\Repositories\AuditLogRepositoryInterface;
use App\Domains\Facilities\Facility\Repositories\FacilityRepository;
use App\Domains\Facilities\Facility\Repositories\FacilityRepositoryInterface;
use App\Domains\Media\Media\Repositories\MediaRepository;
use App\Domains\Media\Media\Repositories\MediaRepositoryInterface;
use App\Domains\Users\User\Repositories\UserRepository;
use App\Domains\Users\User\Repositories\UserRepositoryInterface;
use App\Models\Facilities\Facility;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        MediaRepositoryInterface::class => MediaRepository::class,
        AuditLogRepositoryInterface::class => AuditLogRepository::class,
    ];

    /** @var list<string> */
    private const ROUTE_FILES = [
        'routes/auth/auth.php',
        'routes/facilities/facilities.php',
        'routes/media/media.php',
        'routes/audit/audit.php',
    ];

    public function boot(): void
    {
        // Short, stable names in media.mediable_type (and the upload API) instead of class names.
        // New owner model = one entry here + its alias in config/media.php.
        Relation::morphMap([
            'facility' => Facility::class,
        ]);

        Route::middleware('api')->prefix('api')->group(function () {
            foreach (self::ROUTE_FILES as $file) {
                require base_path($file);
            }
        });
    }
}
