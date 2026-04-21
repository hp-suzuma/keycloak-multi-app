import { describeApApiError } from '~/utils/apApiError'
import { useApSso } from '~/composables/useApSso'

export interface ApCurrentUser {
  id: string | number
  name: string
  email: string
}

export interface ApScope {
  id: number
  layer: 'server' | 'service' | 'tenant'
  code: string
  name: string
  parent_scope_id: number | null
}

export interface ApRole {
  id: number
  slug: string
  name: string
  scope_layer: 'server' | 'service' | 'tenant'
  permission_role: 'admin' | 'operator' | 'viewer' | 'user_manager'
}

export interface ApPermission {
  id: number
  slug: string
  name: string
}

export interface ApAssignment {
  scope: ApScope
  role: ApRole
  permissions: ApPermission[]
}

export interface ApPermissionScopeAccess {
  granted_scope_ids: number[]
  accessible_scope_ids: number[]
}

export interface ApAuthorization {
  keycloak_sub: string
  assignments: ApAssignment[]
  permissions: string[]
  permission_scopes: Record<string, ApPermissionScopeAccess>
}

interface MeResponse {
  current_user: ApCurrentUser | null
}

interface MeAuthorizationResponse {
  current_user: ApCurrentUser | null
  authorization: ApAuthorization | null
}

function withAuthorizationHeaders(token: string) {
  return {
    'Authorization': `Bearer ${token}`,
    'X-Forwarded-Authorization': `Bearer ${token}`
  }
}

function withAuthorizationQuery(token: string) {
  return {
    access_token: token
  }
}

type AuthMode = 'mock' | 'live'
type AuthStatus = 'idle' | 'loading' | 'ready' | 'error'
type AuthRecoveryKind = 'none' | 'setup' | 'refresh' | 'retry'

const MOCK_CURRENT_USER: ApCurrentUser = {
  id: 'mock-admin-1',
  name: 'Mock AP Admin',
  email: 'mock-admin@example.com'
}

const MOCK_AUTHORIZATION: ApAuthorization = {
  keycloak_sub: 'mock-admin-1',
  assignments: [
    {
      scope: {
        id: 1,
        layer: 'server',
        code: 'ap-root',
        name: 'AP Root',
        parent_scope_id: null
      },
      role: {
        id: 1,
        slug: 'server_admin',
        name: 'Server Admin',
        scope_layer: 'server',
        permission_role: 'admin'
      },
      permissions: [
        { id: 1, slug: 'user.manage', name: 'User Manage' },
        { id: 2, slug: 'object.read', name: 'Object Read' },
        { id: 3, slug: 'object.create', name: 'Object Create' },
        { id: 4, slug: 'object.update', name: 'Object Update' },
        { id: 5, slug: 'object.delete', name: 'Object Delete' },
        { id: 6, slug: 'object.execute', name: 'Object Execute' }
      ]
    }
  ],
  permissions: [
    'user.manage',
    'object.read',
    'object.create',
    'object.update',
    'object.delete',
    'object.execute'
  ],
  permission_scopes: {
    'user.manage': {
      granted_scope_ids: [1],
      accessible_scope_ids: [1, 11, 12, 21, 22, 23]
    },
    'object.read': {
      granted_scope_ids: [1],
      accessible_scope_ids: [1, 11, 12, 21, 22, 23]
    },
    'object.create': {
      granted_scope_ids: [1],
      accessible_scope_ids: [1, 11, 12, 21, 22, 23]
    },
    'object.update': {
      granted_scope_ids: [1],
      accessible_scope_ids: [1, 11, 12, 21, 22, 23]
    },
    'object.delete': {
      granted_scope_ids: [1],
      accessible_scope_ids: [1, 11, 12, 21, 22, 23]
    },
    'object.execute': {
      granted_scope_ids: [1],
      accessible_scope_ids: [1, 11, 12, 21, 22, 23]
    }
  }
}

const MODE_STORAGE_KEY = 'ap-user-management-mode'
const TOKEN_STORAGE_KEY = 'ap-api-bearer-token'

export function useApAuth() {
  const config = useRuntimeConfig()
  const {
    globalLoginUrl: buildGlobalLoginUrl,
    globalLogoutUrl: buildGlobalLogoutUrl
  } = useApSso()
  const modeOverride = useState<AuthMode | null>('ap-auth-mode-override', () => null)
  const tokenOverride = useState<string>('ap-auth-token-override', () => '')
  const currentUser = useState<ApCurrentUser | null>('ap-auth-current-user', () => null)
  const authorization = useState<ApAuthorization | null>('ap-auth-authorization', () => null)
  const status = useState<AuthStatus>('ap-auth-status', () => 'idle')
  const errorMessage = useState<string | null>('ap-auth-error-message', () => null)
  const initialized = useState<boolean>('ap-auth-initialized', () => false)

  const mode = computed<AuthMode>(() => {
    if (modeOverride.value) {
      return modeOverride.value
    }

    return config.public.apUserManagementMode === 'live' ? 'live' : 'mock'
  })

  const apiBase = computed(() => config.public.apApiBase)
  const bearerToken = computed(() => tokenOverride.value || config.public.apApiBearerToken)
  const globalLoginUrl = computed(() => buildGlobalLoginUrl())
  const globalLogoutUrl = computed(() => buildGlobalLogoutUrl())
  const hasBearerToken = computed(() => bearerToken.value.length > 0)
  const isLiveReady = computed(() => mode.value === 'live' && apiBase.value.length > 0 && hasBearerToken.value)
  const effectivePermissions = computed(() => authorization.value?.permissions ?? [])
  const liveSessionLooksExpired = computed(() =>
    mode.value === 'live'
    && hasBearerToken.value
    && status.value === 'ready'
    && !currentUser.value
    && !authorization.value
  )
  const needsAuthRecovery = computed(() =>
    mode.value === 'live'
    && (
      !hasBearerToken.value
      || status.value === 'error'
      || liveSessionLooksExpired.value
    )
  )
  const authRecoveryKind = computed<AuthRecoveryKind>(() => {
    if (mode.value !== 'live') {
      return 'none'
    }

    if (!hasBearerToken.value) {
      return 'setup'
    }

    if (liveSessionLooksExpired.value) {
      return 'refresh'
    }

    if (status.value === 'error') {
      return 'retry'
    }

    return 'none'
  })

  function persistClientState() {
    if (!import.meta.client) {
      return
    }

    localStorage.setItem(MODE_STORAGE_KEY, mode.value)

    if (tokenOverride.value) {
      localStorage.setItem(TOKEN_STORAGE_KEY, tokenOverride.value)
      return
    }

    localStorage.removeItem(TOKEN_STORAGE_KEY)
  }

  function loadClientState() {
    if (!import.meta.client || initialized.value) {
      return
    }

    const storedMode = localStorage.getItem(MODE_STORAGE_KEY)
    const storedToken = localStorage.getItem(TOKEN_STORAGE_KEY)

    if (storedMode === 'mock' || storedMode === 'live') {
      modeOverride.value = storedMode
    }

    if (storedToken) {
      tokenOverride.value = storedToken
    }

    initialized.value = true
  }

  function setMode(nextMode: AuthMode) {
    modeOverride.value = nextMode
    persistClientState()
  }

  function setBearerToken(nextToken: string) {
    tokenOverride.value = nextToken.trim()
    persistClientState()
  }

  function clearClientAuth() {
    tokenOverride.value = ''
    currentUser.value = null
    authorization.value = null
    status.value = 'idle'
    errorMessage.value = null
    persistClientState()
  }

  async function refreshCurrentUser() {
    errorMessage.value = null
    status.value = 'loading'

    if (mode.value === 'mock') {
      currentUser.value = MOCK_CURRENT_USER
      authorization.value = MOCK_AUTHORIZATION
      status.value = 'ready'
      return currentUser.value
    }

    if (!apiBase.value) {
      currentUser.value = null
      authorization.value = null
      status.value = 'error'
      errorMessage.value = 'NUXT_PUBLIC_AP_API_BASE が未設定です。'
      return null
    }

    if (!hasBearerToken.value) {
      currentUser.value = null
      authorization.value = null
      status.value = 'error'
      errorMessage.value = 'Bearer token を設定すると live mode の CurrentUser を取得できます。'
      return null
    }

    try {
      const [meResponse, authorizationResponse] = await Promise.all([
        $fetch<MeResponse>('/me', {
          baseURL: apiBase.value,
          headers: withAuthorizationHeaders(bearerToken.value),
          query: withAuthorizationQuery(bearerToken.value)
        }),
        $fetch<MeAuthorizationResponse>('/me/authorization', {
          baseURL: apiBase.value,
          headers: withAuthorizationHeaders(bearerToken.value),
          query: withAuthorizationQuery(bearerToken.value)
        })
      ])

      currentUser.value = authorizationResponse.current_user ?? meResponse.current_user
      authorization.value = authorizationResponse.authorization
      status.value = 'ready'

      return currentUser.value
    } catch (error) {
      currentUser.value = null
      authorization.value = null
      status.value = 'error'
      errorMessage.value = describeApApiError(error, 'CurrentUser の取得に失敗しました。')
      return null
    }
  }

  if (import.meta.client && !initialized.value) {
    loadClientState()
  }

  return {
    mode,
    apiBase,
    bearerToken,
    globalLoginUrl,
    globalLogoutUrl,
    currentUser,
    authorization,
    effectivePermissions,
    status,
    errorMessage,
    hasBearerToken,
    isLiveReady,
    liveSessionLooksExpired,
    needsAuthRecovery,
    authRecoveryKind,
    setMode,
    setBearerToken,
    clearClientAuth,
    refreshCurrentUser
  }
}
