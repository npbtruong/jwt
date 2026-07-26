<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TokenRequest;
use App\Services\Auth\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    /**
     * POST /api/v1/oauth/token
     *
     * Exchange client_id + client_secret for a short-lived Bearer JWT.
     */
    public function token(TokenRequest $request): JsonResponse
    {
        $client = $this->auth->authenticateClient(
            $request->string('client_id')->toString(),
            $request->string('client_secret')->toString(),
        );

        $token = $this->auth->issueToken($client);

        return ApiResponse::success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl'),
        ], 'Token issued');
    }
}
