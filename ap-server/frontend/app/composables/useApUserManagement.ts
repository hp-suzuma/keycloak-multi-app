type ScopeLayer = 'server' | 'service' | 'tenant'
type PermissionRole = 'admin' | 'operator' | 'viewer' | 'user_manager'

export interface ScopeItem {
  id: number
  layer: ScopeLayer
  code: string
  name: string
  parent_scope_id: number | null
}

export interface PermissionItem {
  id: number
  slug: string
  name: string
}

export interface RoleItem {
  id: number
  slug: string
  name: string
  scope_layer: ScopeLayer
  permission_role: PermissionRole
}

export interface UserAssignmentItem {
  id: number
  scope: ScopeItem
  role: RoleItem
  permissions: PermissionItem[]
}

export interface UserItem {
  keycloak_sub: string
  display_name: string | null
  email: string | null
  assignments: UserAssignmentItem[]
  permissions: string[]
}

export interface UserIndexFilters {
  scope_id?: number
  keycloak_sub?: string
  keyword?: string
  sort?: string
  page?: number
  per_page?: number
}

export interface ScopeIndexFilters {
  layer?: ScopeLayer
  parent_scope_id?: number
  code?: string
  name?: string
  sort?: string
}

export interface UserIndexResponse {
  data: UserItem[]
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
    filters: {
      scope_id: number | null
      keycloak_sub: string | null
      keyword: string | null
      sort: string | null
    }
  }
}

export interface UserShowResponse {
  data: UserItem
}

export interface ScopeIndexResponse {
  data: ScopeItem[]
}

const rootScope: ScopeItem = { id: 1, layer: 'server', code: 'ap-root', name: 'AP Root', parent_scope_id: null }
const serviceAlphaScope: ScopeItem = { id: 11, layer: 'service', code: 'svc-alpha', name: 'Service Alpha', parent_scope_id: 1 }
const serviceBetaScope: ScopeItem = { id: 12, layer: 'service', code: 'svc-beta', name: 'Service Beta', parent_scope_id: 1 }
const tenantAScope: ScopeItem = { id: 21, layer: 'tenant', code: 'tenant-a', name: 'Tenant A', parent_scope_id: 11 }
const tenantBScope: ScopeItem = { id: 22, layer: 'tenant', code: 'tenant-b', name: 'Tenant B', parent_scope_id: 11 }
const tenantCScope: ScopeItem = { id: 23, layer: 'tenant', code: 'tenant-c', name: 'Tenant C', parent_scope_id: 12 }

const mockScopes: ScopeItem[] = [
  rootScope,
  serviceAlphaScope,
  serviceBetaScope,
  tenantAScope,
  tenantBScope,
  tenantCScope
]

const serviceAdminRole: RoleItem = {
  id: 1,
  slug: 'service_admin',
  name: 'Service Admin',
  scope_layer: 'service',
  permission_role: 'admin'
}

const serviceUserManagerRole: RoleItem = {
  id: 2,
  slug: 'service_user_manager',
  name: 'Service User Manager',
  scope_layer: 'service',
  permission_role: 'user_manager'
}

const tenantViewerRole: RoleItem = {
  id: 3,
  slug: 'tenant_viewer',
  name: 'Tenant Viewer',
  scope_layer: 'tenant',
  permission_role: 'viewer'
}

const tenantOperatorRole: RoleItem = {
  id: 4,
  slug: 'tenant_operator',
  name: 'Tenant Operator',
  scope_layer: 'tenant',
  permission_role: 'operator'
}

const tenantUserManagerRole: RoleItem = {
  id: 5,
  slug: 'tenant_user_manager',
  name: 'Tenant User Manager',
  scope_layer: 'tenant',
  permission_role: 'user_manager'
}

const userManagePermission: PermissionItem = { id: 1, slug: 'user.manage', name: 'User Manage' }
const objectReadPermission: PermissionItem = { id: 2, slug: 'object.read', name: 'Object Read' }
const objectUpdatePermission: PermissionItem = { id: 3, slug: 'object.update', name: 'Object Update' }
const objectExecutePermission: PermissionItem = { id: 4, slug: 'object.execute', name: 'Object Execute' }

const mockUsers: UserItem[] = [
  {
    keycloak_sub: 'kc-admin-001',
    display_name: 'Mika Admin',
    email: 'mika.admin@example.com',
    assignments: [
      {
        id: 101,
        scope: serviceAlphaScope,
        role: serviceAdminRole,
        permissions: [userManagePermission, objectReadPermission, objectUpdatePermission, objectExecutePermission]
      }
    ],
    permissions: ['user.manage', 'object.read', 'object.update', 'object.execute']
  },
  {
    keycloak_sub: 'kc-operator-021',
    display_name: 'Ren Operator',
    email: 'ren.operator@example.com',
    assignments: [
      {
        id: 102,
        scope: tenantAScope,
        role: tenantOperatorRole,
        permissions: [objectReadPermission, objectUpdatePermission, objectExecutePermission]
      }
    ],
    permissions: ['object.read', 'object.update', 'object.execute']
  },
  {
    keycloak_sub: 'kc-viewer-022',
    display_name: 'Aoi Viewer',
    email: 'aoi.viewer@example.com',
    assignments: [
      {
        id: 103,
        scope: tenantBScope,
        role: tenantViewerRole,
        permissions: [objectReadPermission]
      }
    ],
    permissions: ['object.read']
  },
  {
    keycloak_sub: 'kc-manager-023',
    display_name: 'Sora Manager',
    email: 'sora.manager@example.com',
    assignments: [
      {
        id: 104,
        scope: serviceBetaScope,
        role: serviceUserManagerRole,
        permissions: [userManagePermission]
      },
      {
        id: 105,
        scope: tenantCScope,
        role: tenantUserManagerRole,
        permissions: [userManagePermission]
      }
    ],
    permissions: ['user.manage']
  }
]

function compareValues(left: string | number | null | undefined, right: string | number | null | undefined): number {
  const normalizedLeft = `${left ?? ''}`.toLowerCase()
  const normalizedRight = `${right ?? ''}`.toLowerCase()

  return normalizedLeft.localeCompare(normalizedRight, 'ja')
}

function sortItems<T, K extends keyof T>(items: T[], sort: string | undefined, fallbackKey: K): T[] {
  const sortKey = (sort ?? `${String(fallbackKey)}`).replace(/^-/, '') as keyof T
  const isDesc = sort?.startsWith('-') ?? false

  return [...items].sort((left, right) => {
    const result = compareValues(
      left[sortKey] as string | number | null | undefined,
      right[sortKey] as string | number | null | undefined
    )

    return isDesc ? result * -1 : result
  })
}

function filterMockScopes(filters: ScopeIndexFilters = {}): ScopeIndexResponse {
  let scopes = [...mockScopes]

  if (filters.layer) {
    scopes = scopes.filter(scope => scope.layer === filters.layer)
  }

  if (typeof filters.parent_scope_id === 'number') {
    scopes = scopes.filter(scope => scope.parent_scope_id === filters.parent_scope_id)
  }

  if (filters.code) {
    const keyword = filters.code.toLowerCase()
    scopes = scopes.filter(scope => scope.code.toLowerCase().includes(keyword))
  }

  if (filters.name) {
    const keyword = filters.name.toLowerCase()
    scopes = scopes.filter(scope => scope.name.toLowerCase().includes(keyword))
  }

  return {
    data: sortItems(scopes, filters.sort, 'name')
  }
}

function matchesScope(user: UserItem, scopeId?: number): boolean {
  if (!scopeId) {
    return true
  }

  return user.assignments.some((assignment) => {
    if (assignment.scope.id === scopeId) {
      return true
    }

    const scope = mockScopes.find(item => item.id === assignment.scope.id)

    return scope?.parent_scope_id === scopeId
  })
}

function filterMockUsers(filters: UserIndexFilters = {}): UserIndexResponse {
  const page = filters.page ?? 1
  const perPage = filters.per_page ?? 20
  const keyword = filters.keyword?.trim().toLowerCase()

  let users = mockUsers.filter(user => matchesScope(user, filters.scope_id))

  if (filters.keycloak_sub) {
    const keycloakSub = filters.keycloak_sub.toLowerCase()
    users = users.filter(user => user.keycloak_sub.toLowerCase().includes(keycloakSub))
  }

  if (keyword) {
    users = users.filter((user) => {
      const haystacks = [user.display_name, user.email]

      return haystacks.some(value => value?.toLowerCase().includes(keyword))
    })
  }

  const sortedUsers = sortItems(users, filters.sort, 'email')
  const total = sortedUsers.length
  const offset = (page - 1) * perPage

  return {
    data: sortedUsers.slice(offset, offset + perPage),
    meta: {
      current_page: page,
      per_page: perPage,
      total,
      last_page: Math.max(1, Math.ceil(total / perPage)),
      filters: {
        scope_id: filters.scope_id ?? null,
        keycloak_sub: filters.keycloak_sub ?? null,
        keyword: filters.keyword ?? null,
        sort: filters.sort ?? null
      }
    }
  }
}

function getMockUser(keycloakSub: string): UserShowResponse {
  const user = mockUsers.find(item => item.keycloak_sub === keycloakSub)

  if (!user) {
    throw createError({
      statusCode: 404,
      statusMessage: 'User not found'
    })
  }

  return { data: user }
}

function withAuthorizationHeaders(token: string) {
  if (!token) {
    return undefined
  }

  return {
    Authorization: `Bearer ${token}`
  }
}

function buildQuery(filters: Record<string, string | number | undefined>) {
  return Object.fromEntries(
    Object.entries(filters).filter(([, value]) => value !== undefined && value !== '')
  )
}

export function useApUserManagement() {
  const { mode, apiBase, bearerToken } = useApAuth()

  async function listUsers(filters: UserIndexFilters = {}): Promise<UserIndexResponse> {
    if (mode.value === 'mock' || !apiBase.value) {
      return filterMockUsers(filters)
    }

    return await $fetch<UserIndexResponse>('/users', {
      baseURL: apiBase.value,
      headers: withAuthorizationHeaders(bearerToken.value),
      query: buildQuery({
        scope_id: filters.scope_id,
        keycloak_sub: filters.keycloak_sub,
        keyword: filters.keyword,
        sort: filters.sort,
        page: filters.page,
        per_page: filters.per_page
      })
    })
  }

  async function getUser(keycloakSub: string): Promise<UserShowResponse> {
    if (mode.value === 'mock' || !apiBase.value) {
      return getMockUser(keycloakSub)
    }

    return await $fetch<UserShowResponse>(`/users/${keycloakSub}`, {
      baseURL: apiBase.value,
      headers: withAuthorizationHeaders(bearerToken.value)
    })
  }

  async function listScopes(filters: ScopeIndexFilters = {}): Promise<ScopeIndexResponse> {
    if (mode.value === 'mock' || !apiBase.value) {
      return filterMockScopes(filters)
    }

    return await $fetch<ScopeIndexResponse>('/scopes', {
      baseURL: apiBase.value,
      headers: withAuthorizationHeaders(bearerToken.value),
      query: buildQuery({
        layer: filters.layer,
        parent_scope_id: filters.parent_scope_id,
        code: filters.code,
        name: filters.name,
        sort: filters.sort
      })
    })
  }

  return {
    mode,
    listUsers,
    getUser,
    listScopes
  }
}
