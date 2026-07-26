<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A reservation ingested from an OTA webhook.
 *
 * `event_id` is unique — it is both the idempotency key AND a DB-level backstop
 * (unique index) against duplicate inserts.
 *
 * @property int $id
 * @property string $event_id
 * @property string $ota_reservation_id
 * @property string $property_id
 * @property string $guest_name
 * @property string $guest_email
 * @property string $check_in
 * @property string $check_out
 * @property string $room_type
 * @property string $total_amount
 * @property string $currency
 * @property string $status
 */
final class Reservation extends Model
{
    protected $fillable = [
        'event_id',
        'ota_reservation_id',
        'property_id',
        'guest_name',
        'guest_email',
        'check_in',
        'check_out',
        'room_type',
        'total_amount',
        'currency',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'check_in' => 'date:Y-m-d',
            'check_out' => 'date:Y-m-d',
            'total_amount' => 'decimal:2',
        ];
    }
}
