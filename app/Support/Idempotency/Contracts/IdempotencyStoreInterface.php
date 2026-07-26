<?php

declare(strict_types=1);

namespace App\Support\Idempotency\Contracts;

/**
 * Abstraction over the idempotency backing store.
 *
 * Callers depend on this interface only — the Redis implementation can be
 * swapped (e.g. for a DynamoDB / Memcached store) without touching services.
 */
interface IdempotencyStoreInterface
{
    /**
     * Atomically reserve $key for $ttl seconds.
     *
     * Implementations MUST perform a single atomic "set if not exists"
     * operation (Redis: SET key value NX EX ttl).
     *
     * @return bool true  => key was free and is now reserved (first delivery)
     *              false => key already existed (duplicate delivery)
     */
    public function acquire(string $key, int $ttl): bool;
}
