<?php

namespace App\Http\Controllers;

use App\Auth\SsoUserProvider;
use App\Models\SsoUser;
use App\Services\OidcService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TenantAuthController extends Controller
{
    public function __construct(
        private readonly OidcService $oidc,
        private readonly SsoUserProvider $users,
    ) {
    }

    public function silentLogin(Request $request): RedirectResponse
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
            prompt: 'none',
        ));
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->away(env('GLOBAL_LOGIN_URL'));
        }

        abort_unless($request->session()->pull('oidc_state') === $request->string('state')->value(), 403);

        $tokens = $this->oidc->exchangeCode(
            clientId: config('services.keycloak.client_id'),
            clientSecret: config('services.keycloak.client_secret'),
            redirectUri: rtrim(config('app.url'), '/').'/auth/callback',
            code: $request->string('code')->value(),
        );

        $claims = $this->oidc->decodeIdToken($tokens['id_token'] ?? null);
        $user = SsoUser::fromClaims($claims);

        $this->users->storeUser($user);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->to('/');
    }

    public function home(Request $request)
    {
        /** @var SsoUser|null $user */
        $user = Auth::guard('web')->user();

        abort_unless($user, 401);

        return response()->json([
            'message' => 'SSO login completed.',
            'app_role' => env('APP_ROLE'),
            'user' => $user->toArray(),
        ]);
    }
}
