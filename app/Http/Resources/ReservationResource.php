<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stable public shape of a reservation.
 *
 * @mixin Reservation
 */
final class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'ota_reservation_id' => $this->ota_reservation_id,
            'property_id' => $this->property_id,
            'guest_name' => $this->guest_name,
            'guest_email' => $this->guest_email,
            'check_in' => $this->check_in?->toDateString(),
            'check_out' => $this->check_out?->toDateString(),
            'room_type' => $this->room_type,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'status' => $this->status,
        ];
    }
}
