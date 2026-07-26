<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\ClientRepository;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\ReservationRepository;
use App\Support\Idempotency\Contracts\IdempotencyStoreInterface;
use App\Support\Idempotency\RedisIdempotencyStore;
use App\Support\Jwt\Contracts\JwtCodecInterface;
use App\Support\Jwt\HmacJwtCodec;
use Illuminate\Support\ServiceProvider;

/**
 * Binds every cross-layer abstraction to its concrete implementation.
 *
 * Because callers type-hint the INTERFACES, you can swap any implementation
 * (e.g. a different idempotency backend) by changing one line here — no
 * controller, service or job is touched.
 */
final class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ClientRepositoryInterface::class, ClientRepository::class);
        $this->app->bind(ReservationRepositoryInterface::class, ReservationRepository::class);
        $this->app->bind(IdempotencyStoreInterface::class, RedisIdempotencyStore::class);

        // JWT codec: HS256 with config-driven secret / algorithm / clock leeway.
        $this->app->bind(JwtCodecInterface::class, static function (): HmacJwtCodec {
            return new HmacJwtCodec(
                secret: (string) config('jwt.secret'),
                algo: (string) config('jwt.algo'),
                leeway: (int) config('jwt.leeway'),
            );
        });
    }
}
