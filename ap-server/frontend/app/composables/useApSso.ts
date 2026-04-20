const PKCE_STORAGE_KEY = 'ap-sso-pkce'
const LOGOUT_RETURN_NEXT_STORAGE_KEY = 'ap-sso-logout-return-next'
const SSO_DEBUG_FLAG_QUERY_KEY = 'sso_debug'
const SSO_DEBUG_ENABLED_STORAGE_KEY = 'ap-sso-debug-enabled'
const SSO_DEBUG_TRACE_STORAGE_KEY = 'ap-sso-debug-trace'

interface SsoDebugTraceEntry {
  at: string
  stage: string
  detail?: Record<string, string | number | boolean | null>
}

interface SsoDebugTraceSummary {
  count: number
  latestStage: string | null
  latestAt: string | null
}

interface PkceSessionState {
  state: string
  nonce: string
  codeVerifier: string
  next: string
  debug?: boolean
}

interface StartBridgeSessionOptions {
  next?: string
  prompt?: 'none' | 'login'
}

interface CompleteBridgeSessionResult {
  accessToken: string
  next: string
}

function normalizeStoredNextPath(next: string | null | undefined): string | null {
  if (!next || !next.startsWith('/')) {
    return null
  }

  return normalizeNextPath(next, '/')
}

function normalizeNextPath(next: string | undefined, fallback = '/'): string {
  if (!next || !next.startsWith('/')) {
    return fallback
  }

  return next
}

function toBase64Url(bytes: Uint8Array): string {
  return btoa(String.fromCharCode(...bytes))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/g, '')
}

function randomString(byteLength = 32): string {
  const bytes = crypto.getRandomValues(new Uint8Array(byteLength))

  return toBase64Url(bytes)
}

async function sha256(value: string): Promise<Uint8Array> {
  const encoded = new TextEncoder().encode(value)
  const hash = await crypto.subtle.digest('SHA-256', encoded)

  return new Uint8Array(hash)
}

export function useApSso() {
  const config = useRuntimeConfig()
  const route = useRoute()
  const logoutReturnNextCookie = useCookie<string | null>(
    LOGOUT_RETURN_NEXT_STORAGE_KEY,
    {
      sameSite: 'lax',
      default: () => null
    }
  )
  const storedLogoutReturnNext = useState<string | null>(
    'ap-sso-logout-return-next',
    () => normalizeStoredNextPath(logoutReturnNextCookie.value)
  )
  const ssoDebugEnabled = useState<boolean>('ap-sso-debug-enabled', () => false)

  const frontendBaseUrl = computed(() => config.public.apFrontendBaseUrl)
  const globalLoginBaseUrl = computed(() => config.public.apGlobalLoginUrl)
  const keycloakIssuer = computed(() => config.public.apKeycloakIssuer)
  const keycloakClientId = computed(() => config.public.apKeycloakClientId)

  function readSsoDebugEnabledFromStorage() {
    if (!import.meta.client) {
      return false
    }

    return sessionStorage.getItem(SSO_DEBUG_ENABLED_STORAGE_KEY) === '1'
  }

  function writeSsoDebugEnabledToStorage(enabled: boolean) {
    if (!import.meta.client) {
      return
    }

    if (enabled) {
      sessionStorage.setItem(SSO_DEBUG_ENABLED_STORAGE_KEY, '1')
      return
    }

    sessionStorage.removeItem(SSO_DEBUG_ENABLED_STORAGE_KEY)
  }

  function syncSsoDebugEnabled() {
    if (!import.meta.client) {
      return ssoDebugEnabled.value
    }

    if (route.query[SSO_DEBUG_FLAG_QUERY_KEY] === '1') {
      ssoDebugEnabled.value = true
      writeSsoDebugEnabledToStorage(true)
      return true
    }

    if (route.query[SSO_DEBUG_FLAG_QUERY_KEY] === '0') {
      ssoDebugEnabled.value = false
      writeSsoDebugEnabledToStorage(false)
      clearSsoDebugTrace()
      return false
    }

    ssoDebugEnabled.value = readSsoDebugEnabledFromStorage()
    return ssoDebugEnabled.value
  }

  function isSsoDebugEnabled() {
    if (!import.meta.client) {
      return false
    }

    return ssoDebugEnabled.value || syncSsoDebugEnabled()
  }

  function readSsoDebugTrace(): SsoDebugTraceEntry[] {
    if (!import.meta.client) {
      return []
    }

    const raw = sessionStorage.getItem(SSO_DEBUG_TRACE_STORAGE_KEY)

    if (!raw) {
      return []
    }

    try {
      return JSON.parse(raw) as SsoDebugTraceEntry[]
    } catch {
      sessionStorage.removeItem(SSO_DEBUG_TRACE_STORAGE_KEY)
      return []
    }
  }

  function writeSsoDebugTrace(trace: SsoDebugTraceEntry[]) {
    if (!import.meta.client) {
      return
    }

    sessionStorage.setItem(SSO_DEBUG_TRACE_STORAGE_KEY, JSON.stringify(trace))
  }

  function appendSsoDebugTrace(
    stage: string,
    detail?: Record<string, string | number | boolean | null>
  ) {
    if (!import.meta.client) {
      return
    }

    const trace = readSsoDebugTrace()
    trace.push({
      at: new Date().toISOString(),
      stage,
      detail
    })
    writeSsoDebugTrace(trace)
  }

  function clearSsoDebugTrace() {
    if (!import.meta.client) {
      return
    }

    sessionStorage.removeItem(SSO_DEBUG_TRACE_STORAGE_KEY)
  }

  function summarizeSsoDebugTrace(): SsoDebugTraceSummary {
    const trace = readSsoDebugTrace()
    const latestEntry = trace.at(-1) ?? null

    return {
      count: trace.length,
      latestStage: latestEntry?.stage ?? null,
      latestAt: latestEntry?.at ?? null
    }
  }

  function currentNextPath() {
    return normalizeNextPath(route.fullPath, '/')
  }

  function syncStoredLogoutReturnNext() {
    if (!import.meta.client) {
      storedLogoutReturnNext.value = normalizeStoredNextPath(
        logoutReturnNextCookie.value
      )
      return storedLogoutReturnNext.value
    }

    const localValue = normalizeStoredNextPath(
      localStorage.getItem(LOGOUT_RETURN_NEXT_STORAGE_KEY)
    )
    const cookieValue = normalizeStoredNextPath(logoutReturnNextCookie.value)
    storedLogoutReturnNext.value = localValue ?? cookieValue
    logoutReturnNextCookie.value = storedLogoutReturnNext.value

    return storedLogoutReturnNext.value
  }

  function readStoredLogoutReturnNext(): string | null {
    if (!import.meta.client) {
      return storedLogoutReturnNext.value
    }

    return storedLogoutReturnNext.value ?? syncStoredLogoutReturnNext()
  }

  function storeLogoutReturnNext(next = currentNextPath()) {
    if (!import.meta.client) {
      return
    }

    localStorage.setItem(
      LOGOUT_RETURN_NEXT_STORAGE_KEY,
      normalizeNextPath(next, '/')
    )
    storedLogoutReturnNext.value = normalizeNextPath(next, '/')
    logoutReturnNextCookie.value = storedLogoutReturnNext.value
  }

  function clearStoredLogoutReturnNext() {
    storedLogoutReturnNext.value = null
    logoutReturnNextCookie.value = null

    if (!import.meta.client) {
      return
    }

    localStorage.removeItem(LOGOUT_RETURN_NEXT_STORAGE_KEY)
  }

  function loginReturnPath() {
    if (route.query.logged_out === '1') {
      return readStoredLogoutReturnNext() ?? '/'
    }

    return currentNextPath()
  }

  function bridgeUrl(next = loginReturnPath()) {
    const url = new URL('/auth/bridge', frontendBaseUrl.value)
    url.searchParams.set('next', normalizeNextPath(next))
    if (isSsoDebugEnabled()) {
      url.searchParams.set(SSO_DEBUG_FLAG_QUERY_KEY, '1')
    }

    return url.toString()
  }

  function callbackUrl() {
    return new URL('/auth/callback', frontendBaseUrl.value).toString()
  }

  function logoutReturnUrl() {
    const url = new URL('/', frontendBaseUrl.value)
    url.searchParams.set('logged_out', '1')
    url.hash = 'auth-entry'

    return url.toString()
  }

  function globalLoginUrl(next = loginReturnPath()) {
    const url = new URL(globalLoginBaseUrl.value)
    url.searchParams.set('return_to', bridgeUrl(next))

    return url.toString()
  }

  function globalLogoutUrl(returnTo = logoutReturnUrl()) {
    const loginUrl = new URL(globalLoginBaseUrl.value)
    const logoutUrl = new URL('/logout', loginUrl.origin)
    logoutUrl.searchParams.set('return_to', returnTo)

    return logoutUrl.toString()
  }

  if (import.meta.client) {
    syncSsoDebugEnabled()
    syncStoredLogoutReturnNext()
  }

  function readStoredState(): PkceSessionState | null {
    if (!import.meta.client) {
      return null
    }

    const raw = sessionStorage.getItem(PKCE_STORAGE_KEY)

    if (!raw) {
      return null
    }

    try {
      return JSON.parse(raw) as PkceSessionState
    } catch {
      sessionStorage.removeItem(PKCE_STORAGE_KEY)
      return null
    }
  }

  function clearStoredState() {
    if (!import.meta.client) {
      return
    }

    sessionStorage.removeItem(PKCE_STORAGE_KEY)
  }

  async function startBridgeSession(options: StartBridgeSessionOptions = {}) {
    if (!import.meta.client) {
      return
    }

    const next = normalizeNextPath(options.next, currentNextPath())
    const state = randomString(24)
    const nonce = randomString(24)
    const codeVerifier = randomString(48)
    const codeChallenge = toBase64Url(await sha256(codeVerifier))

    const storedState: PkceSessionState = {
      state,
      nonce,
      codeVerifier,
      next,
      debug: isSsoDebugEnabled()
    }

    sessionStorage.setItem(PKCE_STORAGE_KEY, JSON.stringify(storedState))

    if (storedState.debug) {
      clearSsoDebugTrace()
      appendSsoDebugTrace('bridge:start', {
        next,
        prompt: options.prompt ?? null
      })
    }

    const url = new URL(`${keycloakIssuer.value}/protocol/openid-connect/auth`)
    url.searchParams.set('client_id', keycloakClientId.value)
    url.searchParams.set('redirect_uri', callbackUrl())
    url.searchParams.set('response_type', 'code')
    url.searchParams.set('scope', 'openid profile email')
    url.searchParams.set('state', state)
    url.searchParams.set('nonce', nonce)
    url.searchParams.set('code_challenge', codeChallenge)
    url.searchParams.set('code_challenge_method', 'S256')

    if (options.prompt) {
      url.searchParams.set('prompt', options.prompt)
    }

    window.location.assign(url.toString())
  }

  async function completeBridgeSession(): Promise<CompleteBridgeSessionResult> {
    if (!import.meta.client) {
      throw new Error('SSO callback can only run in the browser.')
    }

    const query = route.query
    const code = typeof query.code === 'string' ? query.code : null
    const state = typeof query.state === 'string' ? query.state : null
    const oidcError = typeof query.error === 'string' ? query.error : null
    const storedState = readStoredState()
    const debugEnabled = storedState?.debug === true || isSsoDebugEnabled()

    if (debugEnabled) {
      appendSsoDebugTrace('callback:received', {
        hasCode: code !== null,
        hasState: state !== null,
        oidcError,
        storedStatePresent: storedState !== null
      })
    }

    if (oidcError) {
      clearStoredState()
      if (debugEnabled) {
        appendSsoDebugTrace('callback:oidc-error', { oidcError })
      }
      throw new Error(`Keycloak callback returned "${oidcError}".`)
    }

    if (!code || !state || !storedState) {
      if (debugEnabled) {
        appendSsoDebugTrace('callback:incomplete-parameters', {
          hasCode: code !== null,
          hasState: state !== null,
          storedStatePresent: storedState !== null
        })
      }
      throw new Error('SSO callback parameters are incomplete.')
    }

    if (storedState.state !== state) {
      clearStoredState()
      if (debugEnabled) {
        appendSsoDebugTrace('callback:state-mismatch', {
          expectedStatePresent: storedState.state.length > 0,
          receivedStatePresent: state.length > 0
        })
      }
      throw new Error('SSO callback state did not match the stored PKCE session.')
    }

    const tokenEndpoint = `${keycloakIssuer.value}/protocol/openid-connect/token`
    if (debugEnabled) {
      appendSsoDebugTrace('callback:token-exchange:start', {
        tokenEndpoint
      })
    }

    let response: {
      access_token?: string
    }

    try {
      response = await $fetch<{
        access_token?: string
      }>(tokenEndpoint, {
        method: 'POST',
        body: new URLSearchParams({
          grant_type: 'authorization_code',
          client_id: keycloakClientId.value,
          code,
          redirect_uri: callbackUrl(),
          code_verifier: storedState.codeVerifier
        }),
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        }
      })
    } catch (error) {
      if (debugEnabled) {
        appendSsoDebugTrace('callback:token-exchange:error', {
          message: error instanceof Error ? error.message : String(error)
        })
      }
      throw error
    }

    if (debugEnabled) {
      appendSsoDebugTrace('callback:token-exchange:done', {
        hasAccessToken: Boolean(response.access_token)
      })
    }

    clearStoredState()
    clearStoredLogoutReturnNext()

    if (!response.access_token) {
      if (debugEnabled) {
        appendSsoDebugTrace('callback:missing-access-token')
      }
      throw new Error('Keycloak token response did not include an access token.')
    }

    if (debugEnabled) {
      appendSsoDebugTrace('callback:session-complete', {
        next: storedState.next
      })
    }

    return {
      accessToken: response.access_token,
      next: storedState.next
    }
  }

  return {
    frontendBaseUrl,
    globalLoginBaseUrl,
    keycloakIssuer,
    keycloakClientId,
    bridgeUrl,
    callbackUrl,
    logoutReturnUrl,
    globalLoginUrl,
    globalLogoutUrl,
    storeLogoutReturnNext,
    readStoredLogoutReturnNext,
    clearStoredLogoutReturnNext,
    startBridgeSession,
    completeBridgeSession,
    clearStoredState,
    readSsoDebugTrace,
    summarizeSsoDebugTrace,
    appendSsoDebugTrace,
    clearSsoDebugTrace
  }
}
