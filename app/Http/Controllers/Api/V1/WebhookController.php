<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\JwtMiddleware;
use App\Http\Requests\WebhookRequest;
use App\Models\Client;
use App\Services\Webhook\WebhookService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookService $webhook,
    ) {}

    /**
     * POST /api/v1/webhook  (protected by jwt middleware)
     *
     * Thin: validate -> hand the payload + authenticated client to the service
     * -> return the standard envelope. All logic lives in WebhookService.
     */
    public function handle(WebhookRequest $request): JsonResponse
    {
        /** @var Client $client */
        $client = $request->attributes->get(JwtMiddleware::CLIENT_ATTRIBUTE);

        $reservation = $this->webhook->process($request->validated(), $client);

        return ApiResponse::success([
            'reservation_id' => $reservation->id,
            'event_id' => $reservation->event_id,
        ], 'Reservation received');
    }
}
