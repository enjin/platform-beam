<?php

use Enjin\Platform\Beam\Enums\BeamRoute;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform Beam Package Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for Beam. These
| routes are loaded by the BeamServiceProvider.
|
*/

Route::get(
    BeamRoute::CLAIM->value,
    function ($code) {
        $redirect = config('enjin-platform-beam.claim_redirect');
        $data = base64_encode(secure_url("claim/{$code}"));

        return redirect()->away($redirect ? "{$redirect}/{$data}" : secure_url('/'));
    }
);
