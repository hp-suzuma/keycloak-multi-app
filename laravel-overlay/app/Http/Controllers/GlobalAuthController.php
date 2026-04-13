<?php

namespace App\Http\Controllers;

use App\Services\OidcService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GlobalAuthController extends Controller
{
    public function __construct(
        private readonly OidcService $oidc,
    ) {
    }

    public function login(Request $request): RedirectResponse
    {
        $state = Str::uuid()->toString();
        $nonce = Str::uuid()->toString();

        $request->session()->put('oidc_state', $state);
        $request->session()->put('oidc_nonce', $nonce);

        return redirect()->away($this->oidc->authorizationUrl(
            clientId: config('services.keycloak.client_id'),
            redirectUri: rtrim(config('app.url'), '/').'/auth/callback',
            state: $state,
            nonce: $nonce,
        ));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($request->session()->pull('oidc_state') === $request->string('state')->value(), 403);

        $tokens = $this->oidc->exchangeCode(
            clientId: config('services.keycloak.client_id'),
            clientSecret: config('services.keycloak.client_secret'),
            redirectUri: rtrim(config('app.url'), '/').'/auth/callback',
            code: $request->string('code')->value(),
        );

        $claims = $this->oidc->decodeIdToken($tokens['id_token'] ?? null);
        $sub = $claims['sub'] ?? abort(500, 'sub claim is missing.');
        $request->session()->put('oidc_id_token', $tokens['id_token'] ?? null);

        $response = Http::acceptJson()->get(
            rtrim(env('BACKEND_URL'), '/').'/internal/users/'.$sub.'/server'
        )->throw()->json();

        $target = rtrim($response['server_url'], '/').'/auth/silent-login';

        return redirect()->away($target);
    }

    public function logout(Request $request): RedirectResponse
    {
        $idTokenHint = $request->session()->get('oidc_id_token');

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away($this->oidc->logoutUrl(
            postLogoutRedirectUri: rtrim(config('app.url'), '/').'/',
            idTokenHint: $idTokenHint,
            clientId: config('services.keycloak.client_id'),
        ));
    }
}
