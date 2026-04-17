<script setup lang="ts">
import type { PermissionRole, ScopeItem, UserAssignmentItem } from '~/composables/useApUserManagement'
import { describeApApiError } from '~/utils/apApiError'
import { resolvePermissionAccessStatus } from '~/utils/permissionScopes'

definePageMeta({
  layout: 'dashboard'
})

const route = useRoute()
const keycloakSub = computed(() => String(route.params.keycloakSub))
const { mode, currentUser, authorization } = useApAuth()
const { getUser, listScopes, listRoles, addUserAssignment, deleteUserAssignment } = useApUserManagement()

const serviceScopeId = computed(() => {
  const value = route.query.service_scope_id

  return typeof value === 'string' ? Number(value) : undefined
})

const tenantScopeId = computed(() => {
  const value = route.query.tenant_scope_id

  return typeof value === 'string' ? Number(value) : undefined
})

const backQuery = computed(() => Object.fromEntries(
  Object.entries({
    service_scope_id: serviceScopeId.value ? String(serviceScopeId.value) : undefined,
    tenant_scope_id: tenantScopeId.value ? String(tenantScopeId.value) : undefined,
    keyword: typeof route.query.keyword === 'string' ? route.query.keyword : undefined,
    sort: typeof route.query.sort === 'string' ? route.query.sort : undefined
  }).filter(([, value]) => value)
))

const { data, status, error, refresh: refreshUser } = await useAsyncData(
  () => `users-detail:${keycloakSub.value}`,
  () => getUser(keycloakSub.value),
  {
    watch: [keycloakSub]
  }
)

const { data: servicesData, status: servicesStatus } = await useAsyncData(
  'users-detail-service-scopes',
  () => listScopes({ layer: 'service', sort: 'name' })
)

const { data: tenantsData, status: tenantsStatus } = await useAsyncData(
  'users-detail-tenant-scopes',
  () => {
    if (!serviceScopeId.value) {
      return Promise.resolve({ data: [] })
    }

    return listScopes({
      layer: 'tenant',
      parent_scope_id: serviceScopeId.value,
      sort: 'name'
    })
  },
  {
    watch: [serviceScopeId]
  }
)

const user = computed(() => data.value?.data)
const detailErrorMessage = computed(() => {
  if (!error.value) {
    return null
  }

  return describeApApiError(error.value, 'users 詳細の取得に失敗しました。')
})
const services = computed(() => servicesData.value?.data ?? [])
const tenants = computed(() => tenantsData.value?.data ?? [])
const selectedService = computed(() => services.value.find(scope => scope.id === serviceScopeId.value) ?? null)

const assignmentScopeOptions = computed(() => {
  const options: ScopeItem[] = []

  if (selectedService.value) {
    options.push(selectedService.value)
  }

  options.push(...tenants.value)

  if (options.length > 0) {
    return options
  }

  return services.value
})

const preferredAssignmentScopeId = computed(() => tenantScopeId.value ?? serviceScopeId.value ?? assignmentScopeOptions.value[0]?.id)
const selectedAssignmentScopeId = ref<number | undefined>(preferredAssignmentScopeId.value)
const selectedRoleId = ref<number | undefined>(undefined)
const scopeSearchKeyword = ref('')
const roleSearchKeyword = ref('')
const selectedPermissionRole = ref<PermissionRole | 'all'>('all')
const selectedRoleSort = ref<'name' | 'slug' | 'permission_role'>('name')
const feedback = ref<{ tone: 'success' | 'error'; message: string } | null>(null)
const isSubmitting = ref(false)
const deletingAssignmentId = ref<number | null>(null)
const pendingRemovalAssignment = ref<UserAssignmentItem | null>(null)
const isRemoveDialogOpen = ref(false)

const permissionRoleOptions: Array<{ value: PermissionRole | 'all', label: string }> = [
  { value: 'all', label: 'All' },
  { value: 'admin', label: 'Admin' },
  { value: 'operator', label: 'Operator' },
  { value: 'viewer', label: 'Viewer' },
  { value: 'user_manager', label: 'User Manager' }
]

const roleSortOptions = [
  { value: 'name', label: 'Role Name' },
  { value: 'slug', label: 'Slug' },
  { value: 'permission_role', label: 'Permission Role' }
] as const

watch(
  [assignmentScopeOptions, preferredAssignmentScopeId],
  ([options, preferredId]) => {
    if (!options.some(scope => scope.id === selectedAssignmentScopeId.value)) {
      selectedAssignmentScopeId.value = preferredId
    }
  },
  { immediate: true }
)

const selectedAssignmentScope = computed(
  () => assignmentScopeOptions.value.find(scope => scope.id === selectedAssignmentScopeId.value) ?? null
)
const userManageAccess = computed(() =>
  resolvePermissionAccessStatus(authorization.value, 'user.manage', selectedAssignmentScope.value?.id)
)
const selectedRoleUserManageAccess = computed(() =>
  resolvePermissionAccessStatus(authorization.value, 'user.manage', selectedAssignmentScope.value?.id)
)
const pendingRemovalUserManageAccess = computed(() =>
  resolvePermissionAccessStatus(authorization.value, 'user.manage', pendingRemovalAssignment.value?.scope.id)
)

const filteredAssignmentScopeOptions = computed(() => {
  const keyword = scopeSearchKeyword.value.trim().toLowerCase()

  if (!keyword) {
    return assignmentScopeOptions.value
  }

  return assignmentScopeOptions.value.filter((scope) => {
    const haystacks = [scope.name, scope.code, scope.layer]

    return haystacks.some(value => value.toLowerCase().includes(keyword))
  })
})

watch(
  filteredAssignmentScopeOptions,
  (options) => {
    if (options.length === 0) {
      selectedAssignmentScopeId.value = undefined
      return
    }

    if (!options.some(scope => scope.id === selectedAssignmentScopeId.value)) {
      selectedAssignmentScopeId.value = options[0]?.id
    }
  },
  { immediate: true }
)

watch(isRemoveDialogOpen, (isOpen) => {
  if (!isOpen && deletingAssignmentId.value === null) {
    pendingRemovalAssignment.value = null
  }
})

const { data: rolesData, status: rolesStatus, refresh: refreshRoles } = await useAsyncData(
  () => `users-detail-roles:${selectedAssignmentScope.value?.layer ?? 'none'}`,
  () => {
    if (!selectedAssignmentScope.value) {
      return Promise.resolve({ data: [] })
    }

    return listRoles({
      scope_layer: selectedAssignmentScope.value.layer,
      permission_role: selectedPermissionRole.value === 'all' ? undefined : selectedPermissionRole.value,
      sort: selectedRoleSort.value
    })
  },
  {
    watch: [selectedAssignmentScope, selectedPermissionRole, selectedRoleSort]
  }
)

const assignedRoleIdsForSelectedScope = computed(() => new Set(
  (user.value?.assignments ?? [])
    .filter(assignment => assignment.scope.id === selectedAssignmentScopeId.value)
    .map(assignment => assignment.role.id)
))

const availableRoles = computed(() => {
  const assignedRoleIds = assignedRoleIdsForSelectedScope.value

  return (rolesData.value?.data ?? []).filter(role => !assignedRoleIds.has(role.id))
})

const filteredAvailableRoles = computed(() => {
  const keyword = roleSearchKeyword.value.trim().toLowerCase()

  if (!keyword) {
    return availableRoles.value
  }

  return availableRoles.value.filter((role) => {
    const haystacks = [
      role.name,
      role.slug,
      role.permission_role,
      ...role.permissions.map(permission => permission.slug),
      ...role.permissions.map(permission => permission.name)
    ]

    return haystacks.some(value => value.toLowerCase().includes(keyword))
  })
})

const selectedRole = computed(
  () => filteredAvailableRoles.value.find(role => role.id === selectedRoleId.value)
    ?? availableRoles.value.find(role => role.id === selectedRoleId.value)
    ?? null
)

function formatPermissionRoleLabel(permissionRole: PermissionRole): string {
  return permissionRoleOptions.find(option => option.value === permissionRole)?.label ?? permissionRole
}

watch(
  filteredAvailableRoles,
  (roles) => {
    if (!roles.some(role => role.id === selectedRoleId.value)) {
      selectedRoleId.value = roles[0]?.id
    }
  },
  { immediate: true }
)

function extractErrorMessage(cause: unknown, fallback: string): string {
  return describeApApiError(cause, fallback)
}

async function submitAssignment() {
  if (!selectedAssignmentScopeId.value || !selectedRoleId.value) {
    return
  }

  feedback.value = null
  isSubmitting.value = true

  try {
    const response = await addUserAssignment(keycloakSub.value, {
      scope_id: selectedAssignmentScopeId.value,
      role_id: selectedRoleId.value
    })

    data.value = response
    await refreshRoles()
    feedback.value = {
      tone: 'success',
      message: 'assignment を追加しました。'
    }
  } catch (cause) {
    feedback.value = {
      tone: 'error',
      message: extractErrorMessage(cause, 'assignment の追加に失敗しました。')
    }
  } finally {
    isSubmitting.value = false
  }
}

function openRemoveDialog(assignment: UserAssignmentItem) {
  pendingRemovalAssignment.value = assignment
  isRemoveDialogOpen.value = true
}

function closeRemoveDialog() {
  if (deletingAssignmentId.value !== null) {
    return
  }

  isRemoveDialogOpen.value = false
  pendingRemovalAssignment.value = null
}

async function removeAssignment() {
  if (!pendingRemovalAssignment.value) {
    return
  }

  const assignmentId = pendingRemovalAssignment.value.id
  feedback.value = null
  deletingAssignmentId.value = assignmentId

  try {
    await deleteUserAssignment(keycloakSub.value, assignmentId)
    await refreshUser()
    await refreshRoles()
    feedback.value = {
      tone: 'success',
      message: 'assignment を削除しました。'
    }
    isRemoveDialogOpen.value = false
    pendingRemovalAssignment.value = null
  } catch (cause) {
    feedback.value = {
      tone: 'error',
      message: extractErrorMessage(cause, 'assignment の削除に失敗しました。')
    }
  } finally {
    deletingAssignmentId.value = null
  }
}
</script>

<template>
  <div class="flex flex-col gap-6 py-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-primary">
          User Detail
        </p>
        <h1 class="text-3xl font-semibold tracking-tight text-highlighted">
          {{ user?.display_name || keycloakSub }}
        </h1>
      </div>

      <div class="flex items-center gap-3">
        <UBadge v-if="currentUser" color="neutral" variant="soft">
          viewer: {{ currentUser.name }}
        </UBadge>
        <UBadge :color="mode === 'live' ? 'success' : 'warning'" variant="soft">
          {{ mode === 'live' ? 'LIVE API' : 'MOCK DATA' }}
        </UBadge>
        <UButton to="/users" :query="backQuery" color="neutral" variant="soft" leading-icon="i-lucide-arrow-left">
          一覧へ戻る
        </UButton>
      </div>
    </div>

    <div v-if="status === 'pending'" class="rounded-[2rem] border border-default bg-white/80 px-6 py-12 text-sm text-muted dark:bg-stone-900/70">
      users 詳細を読み込み中です。
    </div>

    <div v-else-if="error || !user" class="rounded-[2rem] border border-default bg-white/80 px-6 py-12 text-sm text-error dark:bg-stone-900/70">
      {{ detailErrorMessage ?? 'users 詳細の取得に失敗しました。' }}
    </div>

    <template v-else>
      <UCard v-if="mode === 'live'" class="rounded-[2rem] border-amber-200 bg-amber-50/80 dark:border-amber-500/20 dark:bg-amber-950/20">
        <template #header>
          <div class="flex items-center gap-2 text-sm font-semibold">
            <UIcon name="i-lucide-badge-alert" class="text-amber-600" />
            Live Check
          </div>
        </template>

        <p class="text-sm leading-6 text-toned">
          live mode の推奨 API Base は `https://ap-backend-fpm.example.com/api` です。assignment 追加 / 削除で失敗した時は UI より先に token expiry を疑い、Auth Entry で fresh token を入れ直してから再試行してください。
        </p>
        <p class="mt-2 text-xs leading-5 text-muted">
          正常系では viewer が `Alice A` で、assignment 追加は `201`、重複追加は `422`、削除は `204` になります。
        </p>
      </UCard>

      <section class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(320px,380px)]">
        <UCard class="rounded-[2rem] border-white/70 bg-white/80 dark:border-white/10 dark:bg-stone-900/70">
          <template #header>
            <div class="flex items-center justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-highlighted">
                  Identity
                </p>
                <p class="text-xs text-muted">
                  backend: `GET /api/users/{keycloak_sub}`
                </p>
              </div>
              <UIcon name="i-lucide-id-card" class="text-2xl text-primary" />
            </div>
          </template>

          <dl class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-default bg-stone-50/70 p-4 dark:bg-stone-950/40">
              <dt class="text-xs uppercase tracking-[0.18em] text-muted">
                Display Name
              </dt>
              <dd class="mt-2 text-sm font-medium text-highlighted">
                {{ user.display_name || 'No display name' }}
              </dd>
            </div>

            <div class="rounded-2xl border border-default bg-stone-50/70 p-4 dark:bg-stone-950/40">
              <dt class="text-xs uppercase tracking-[0.18em] text-muted">
                Email
              </dt>
              <dd class="mt-2 text-sm font-medium text-highlighted">
                {{ user.email || 'No email' }}
              </dd>
            </div>

            <div class="rounded-2xl border border-default bg-stone-50/70 p-4 dark:bg-stone-950/40 sm:col-span-2">
              <dt class="text-xs uppercase tracking-[0.18em] text-muted">
                Keycloak Subject
              </dt>
              <dd class="mt-2 break-all text-sm font-medium text-highlighted">
                {{ user.keycloak_sub }}
              </dd>
            </div>
          </dl>
        </UCard>

        <UCard class="rounded-[2rem] border-primary/20 bg-cyan-50/70 dark:bg-cyan-950/20">
          <template #header>
            <div class="flex items-center justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-highlighted">
                  Effective Permissions
                </p>
                <p class="text-xs text-muted">
                  assignment 集約結果
                </p>
              </div>
              <UIcon name="i-lucide-key-round" class="text-2xl text-primary" />
            </div>
          </template>

          <div class="flex flex-wrap gap-2">
            <UBadge
              v-for="permission in user.permissions"
              :key="permission"
              color="primary"
              variant="soft"
              class="rounded-full px-3 py-1"
            >
              {{ permission }}
            </UBadge>
          </div>
        </UCard>
      </section>

      <UCard class="rounded-[2rem] border-white/70 bg-white/80 dark:border-white/10 dark:bg-stone-900/70">
        <template #header>
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-sm font-semibold text-highlighted">
                Visible Assignments
              </p>
              <p class="text-xs text-muted">
                single-assignment API を使って追加 / 削除します。
              </p>
            </div>
            <UIcon name="i-lucide-shield-check" class="text-2xl text-primary" />
          </div>
        </template>

        <div class="space-y-6">
          <section class="grid gap-6 rounded-[1.5rem] border border-primary/20 bg-cyan-50/60 p-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] dark:bg-cyan-950/20">
            <label class="space-y-2 lg:col-span-3">
              <span class="text-xs uppercase tracking-[0.18em] text-muted">Scope Filter</span>
              <input
                v-model="scopeSearchKeyword"
                type="search"
                placeholder="scope 名 / code / layer で絞り込み"
                class="w-full rounded-2xl border border-default bg-white px-3 py-2 text-sm outline-none transition focus:border-primary dark:bg-stone-950"
              >
              <p class="text-xs text-muted">
                {{ filteredAssignmentScopeOptions.length }} / {{ assignmentScopeOptions.length }} scopes
              </p>
            </label>

            <label class="space-y-2">
              <span class="text-xs uppercase tracking-[0.18em] text-muted">Target Scope</span>
              <select
                v-model.number="selectedAssignmentScopeId"
                class="w-full rounded-2xl border border-default bg-white px-3 py-2 text-sm outline-none transition focus:border-primary disabled:cursor-not-allowed disabled:opacity-60 dark:bg-stone-950"
                :disabled="filteredAssignmentScopeOptions.length === 0 || servicesStatus === 'pending' || tenantsStatus === 'pending'"
              >
                <option v-for="scope in filteredAssignmentScopeOptions" :key="scope.id" :value="scope.id">
                  {{ scope.name }} ({{ scope.layer }})
                </option>
              </select>
              <p class="text-xs text-muted">
                {{ selectedAssignmentScope ? `${selectedAssignmentScope.code} / ${selectedAssignmentScope.layer}` : 'filter 条件に一致する scope がありません。' }}
              </p>
              <div v-if="selectedAssignmentScope" class="flex flex-wrap items-center gap-2">
                <UBadge :color="userManageAccess.tone" variant="soft">
                  user.manage: {{ userManageAccess.label }}
                </UBadge>
                <p class="text-xs text-muted">
                  {{ userManageAccess.kind === 'direct'
                    ? 'この scope へ直接付与された管理権限です。'
                    : userManageAccess.kind === 'descendant'
                      ? '上位 scope の直付与から、この scope へ継承されている管理権限です。'
                      : 'この scope に対する user.manage は確認できません。' }}
                </p>
              </div>
            </label>

            <label class="space-y-2">
              <span class="text-xs uppercase tracking-[0.18em] text-muted">Role</span>
              <div class="mb-2 flex flex-wrap items-center gap-2">
                <select
                  v-model="selectedPermissionRole"
                  class="rounded-2xl border border-default bg-white px-3 py-2 text-sm outline-none transition focus:border-primary dark:bg-stone-950"
                >
                  <option v-for="option in permissionRoleOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
                <select
                  v-model="selectedRoleSort"
                  class="rounded-2xl border border-default bg-white px-3 py-2 text-sm outline-none transition focus:border-primary dark:bg-stone-950"
                >
                  <option v-for="option in roleSortOptions" :key="option.value" :value="option.value">
                    Sort: {{ option.label }}
                  </option>
                </select>
              </div>
              <input
                v-model="roleSearchKeyword"
                type="search"
                placeholder="role 名 / slug / permission で絞り込み"
                class="mb-2 w-full rounded-2xl border border-default bg-white px-3 py-2 text-sm outline-none transition focus:border-primary dark:bg-stone-950"
              >
              <select
                v-model.number="selectedRoleId"
                class="w-full rounded-2xl border border-default bg-white px-3 py-2 text-sm outline-none transition focus:border-primary disabled:cursor-not-allowed disabled:opacity-60 dark:bg-stone-950"
                :disabled="!selectedAssignmentScope || filteredAvailableRoles.length === 0 || rolesStatus === 'pending'"
              >
                <option v-for="role in filteredAvailableRoles" :key="role.id" :value="role.id">
                  {{ role.name }} ({{ role.slug }})
                </option>
              </select>
              <p class="text-xs text-muted">
                {{ rolesStatus === 'pending' ? 'role 候補を読み込み中です。' : filteredAvailableRoles.length > 0 ? `${filteredAvailableRoles.length} / ${availableRoles.length} roles を表示中です。` : 'filter 条件に一致する role 候補がありません。' }}
              </p>
            </label>

            <div class="flex items-end">
              <UButton
                color="primary"
                class="w-full justify-center"
                :loading="isSubmitting"
                :disabled="!selectedAssignmentScopeId || !selectedRoleId || filteredAvailableRoles.length === 0"
                @click="submitAssignment"
              >
                Add Assignment
              </UButton>
            </div>

            <div class="rounded-2xl border border-default bg-white/70 p-4 lg:col-span-3 dark:bg-stone-950/40">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="text-xs uppercase tracking-[0.18em] text-muted">
                    Role Summary
                  </p>
                  <p class="mt-2 text-sm font-semibold text-highlighted">
                    {{ selectedRole ? selectedRole.name : 'role を選ぶと summary を表示します。' }}
                  </p>
                  <p v-if="selectedRole" class="text-xs text-toned">
                    {{ selectedRole.slug }} / {{ formatPermissionRoleLabel(selectedRole.permission_role) }}
                  </p>
                </div>
                <div class="flex flex-wrap gap-2">
                  <UBadge v-if="selectedRole" color="neutral" variant="soft">
                    {{ formatPermissionRoleLabel(selectedRole.permission_role) }}
                  </UBadge>
                  <UBadge v-if="selectedRole" color="neutral" variant="soft">
                    {{ selectedRole.permissions.length }} permissions
                  </UBadge>
                  <UBadge
                    v-if="selectedRole && selectedAssignmentScope"
                    :color="selectedRoleUserManageAccess.tone"
                    variant="soft"
                  >
                    user.manage: {{ selectedRoleUserManageAccess.label }}
                  </UBadge>
                </div>
              </div>

              <div v-if="selectedRole" class="mt-3 flex flex-wrap gap-2">
                <UBadge
                  v-for="permission in selectedRole.permissions"
                  :key="permission.id"
                  color="primary"
                  variant="soft"
                >
                  {{ permission.slug }}
                </UBadge>
              </div>

              <p v-if="selectedRole && selectedAssignmentScope" class="mt-3 text-xs text-muted">
                この role を
                <span class="font-semibold text-toned">{{ selectedAssignmentScope.name }}</span>
                へ付与する操作は、
                <span class="font-semibold text-toned">{{ selectedRoleUserManageAccess.label }}</span>
                の `user.manage` を根拠にしています。
              </p>
            </div>
          </section>

          <div class="flex flex-wrap gap-2 text-xs text-muted">
            <UBadge v-if="selectedService" color="neutral" variant="soft">
              service: {{ selectedService.name }}
            </UBadge>
            <UBadge v-if="tenantScopeId" color="primary" variant="soft">
              tenant drill-down active
            </UBadge>
            <span v-if="servicesStatus === 'pending' || tenantsStatus === 'pending'">
              scope 候補を読み込み中です。
            </span>
          </div>

          <div
            v-if="feedback"
            class="rounded-2xl border px-4 py-3 text-sm"
            :class="feedback.tone === 'success' ? 'border-success/30 bg-success/10 text-success' : 'border-error/30 bg-error/10 text-error'"
          >
            {{ feedback.message }}
          </div>

          <div v-if="user.assignments.length === 0" class="rounded-2xl border border-dashed border-default px-4 py-8 text-sm text-muted">
            visible assignment はまだありません。上のフォームから追加できます。
          </div>

          <section
            v-for="assignment in user.assignments"
            :key="assignment.id"
            class="grid gap-4 rounded-[1.5rem] border border-default p-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)]"
          >
            <div class="space-y-2">
              <p class="text-xs uppercase tracking-[0.18em] text-muted">
                Scope
              </p>
              <p class="font-semibold text-highlighted">
                {{ assignment.scope.name }}
              </p>
              <p class="text-sm text-toned">
                {{ assignment.scope.layer }} / {{ assignment.scope.code }}
              </p>
            </div>

            <div class="space-y-2">
              <p class="text-xs uppercase tracking-[0.18em] text-muted">
                Role
              </p>
              <p class="font-semibold text-highlighted">
                {{ assignment.role.name }}
              </p>
              <p class="text-sm text-toned">
                {{ assignment.role.slug }}
              </p>
            </div>

            <div class="space-y-2">
              <p class="text-xs uppercase tracking-[0.18em] text-muted">
                Permissions
              </p>
              <div class="flex flex-wrap gap-2">
                <UBadge
                  v-for="permission in assignment.permissions"
                  :key="permission.id"
                  color="neutral"
                  variant="soft"
                >
                  {{ permission.slug }}
                </UBadge>
              </div>
            </div>

            <div class="lg:col-span-3 lg:flex lg:justify-end">
              <UButton
                color="error"
                variant="soft"
                leading-icon="i-lucide-trash-2"
                :disabled="deletingAssignmentId !== null && deletingAssignmentId !== assignment.id"
                @click="openRemoveDialog(assignment)"
              >
                Remove
              </UButton>
            </div>
          </section>
        </div>
      </UCard>

      <UModal v-model:open="isRemoveDialogOpen">
        <template #content>
          <div class="space-y-5 rounded-[1.75rem] bg-white p-6 dark:bg-stone-900">
            <div class="space-y-2">
              <p class="text-sm font-semibold text-highlighted">
                Remove Assignment
              </p>
              <p class="text-sm text-toned">
                この assignment を削除します。backend では `DELETE /api/users/{keycloak_sub}/assignments/{assignmentId}` を呼びます。
              </p>
            </div>

            <div
              v-if="pendingRemovalAssignment"
              class="rounded-2xl border border-default bg-stone-50/70 p-4 text-sm dark:bg-stone-950/40"
            >
              <p class="font-semibold text-highlighted">
                {{ pendingRemovalAssignment.scope.name }} / {{ pendingRemovalAssignment.role.name }}
              </p>
              <p class="mt-1 text-toned">
                {{ pendingRemovalAssignment.scope.layer }} / {{ pendingRemovalAssignment.scope.code }}
              </p>
              <div class="mt-3 flex flex-wrap items-center gap-2">
                <UBadge :color="pendingRemovalUserManageAccess.tone" variant="soft">
                  user.manage: {{ pendingRemovalUserManageAccess.label }}
                </UBadge>
                <p class="text-xs text-muted">
                  この削除操作は対象 scope に対する `user.manage` を根拠にしています。
                </p>
              </div>
            </div>

            <div class="flex justify-end gap-2">
              <UButton color="neutral" variant="soft" :disabled="deletingAssignmentId !== null" @click="closeRemoveDialog">
                Cancel
              </UButton>
              <UButton color="error" :loading="deletingAssignmentId !== null" @click="removeAssignment">
                Remove
              </UButton>
            </div>
          </div>
        </template>
      </UModal>
    </template>
  </div>
</template>
