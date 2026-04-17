<?php

namespace App\Http\Controllers;

use App\Services\OidcService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GlobalAuthController extends Controller
{
    private const POST_LOGIN_REDIRECT_KEY = 'post_login_redirect';

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
        $request->session()->forget(self::POST_LOGIN_REDIRECT_KEY);

        if ($returnTo = $this->validatedPostLoginRedirect($request->query('return_to'))) {
            $request->session()->put(self::POST_LOGIN_REDIRECT_KEY, $returnTo);
        }

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

        if ($postLoginRedirect = $request->session()->pull(self::POST_LOGIN_REDIRECT_KEY)) {
            return redirect()->away($postLoginRedirect);
        }

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

    private function validatedPostLoginRedirect(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($value);

        if (($parts['scheme'] ?? null) !== 'https') {
            return null;
        }

        $allowedHosts = [
            'global.example.com',
            'a.example.com',
            'b.example.com',
            'ap.example.com',
        ];

        if (! in_array($parts['host'] ?? '', $allowedHosts, true)) {
            return null;
        }

        return $value;
    }
}
