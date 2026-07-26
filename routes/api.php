<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes (prefixed with /api by bootstrap/app.php)
|--------------------------------------------------------------------------
| Rate limits are config-driven (config/ratelimit.php).
*/

Route::prefix('v1')->group(function () {

    // Public: mint a token. Tight throttle — sensitive endpoint.
    Route::post('oauth/token', [AuthController::class, 'token'])
        ->middleware('throttle:'.config('ratelimit.token'));

    // Protected: verified JWT required. Looser throttle — high volume.
    Route::middleware(['jwt', 'throttle:'.config('ratelimit.webhook')])->group(function () {
        Route::post('webhook', [WebhookController::class, 'handle']);
    });
});
