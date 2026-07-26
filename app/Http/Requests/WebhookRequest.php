<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authentication is handled by JwtMiddleware before this runs.
        return true;
    }

    /**
     * Validate the envelope AND the nested reservation.* object.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'in:reservation.created,reservation.updated,reservation.cancelled'],

            'reservation' => ['required', 'array'],
            'reservation.ota_reservation_id' => ['required', 'string', 'max:255'],
            'reservation.property_id' => ['required', 'string', 'max:255'],
            'reservation.guest_name' => ['required', 'string', 'max:255'],
            'reservation.guest_email' => ['required', 'email', 'max:255'],
            'reservation.check_in' => ['required', 'date_format:Y-m-d'],
            'reservation.check_out' => ['required', 'date_format:Y-m-d', 'after:reservation.check_in'],
            'reservation.room_type' => ['required', 'string', 'max:255'],
            'reservation.total_amount' => ['required', 'numeric', 'min:0'],
            'reservation.currency' => ['required', 'string', 'size:3'],
            'reservation.status' => ['required', 'string', 'max:50'],
        ];
    }
}
