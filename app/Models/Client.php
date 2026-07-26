<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An OTA integration client (e.g. Booking.com) allowed to mint tokens.
 *
 * @property int $id
 * @property string $client_id
 * @property string $client_secret hashed — never store/return the raw value
 * @property string $name
 */
final class Client extends Model
{
    protected $fillable = [
        'client_id',
        'client_secret',
        'name',
    ];

    /**
     * Keep the hashed secret out of any array/JSON serialisation.
     *
     * @var list<string>
     */
    protected $hidden = [
        'client_secret',
    ];
}
