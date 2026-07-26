<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use App\Support\Idempotency\Contracts\IdempotencyStoreInterface;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

/**
 * Redis-backed idempotency store.
 *
 * The whole point of this class is the ATOMIC check-and-set: a single
 * `SET key 1 NX EX ttl` round-trip decides "have we seen this event before?".
 * Because it is atomic, two concurrent webhook retries racing on the same
 * event_id can never both win — exactly one gets `true`.
 */
final class RedisIdempotencyStore implements IdempotencyStoreInterface
{
    public function __construct(private readonly RedisFactory $redis) {}

    public function acquire(string $key, int $ttl): bool
    {
        // SET <key> "1" EX <ttl> NX
        //   EX <ttl> => auto-expire after <ttl> seconds
        //   NX       => only set if the key does not already exist
        // Laravel's Redis wrapper normalises this Predis-style signature for
        // both phpredis and predis. Returns true when the key was set (we own
        // it), false when NX failed (the key was already there => duplicate).
        $result = $this->redis->connection()->set($key, '1', 'EX', $ttl, 'NX');

        return $result === true || $result === 'OK';
    }
}
