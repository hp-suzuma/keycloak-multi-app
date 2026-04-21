<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'
import { describeApApiError } from '~/utils/apApiError'
import { buildAuthRecoveryCopy } from '~/utils/authRecoveryCopy'
import { resolvePermissionAccessStatus } from '~/utils/permissionScopes'

definePageMeta({
  layout: 'dashboard'
})

const route = useRoute()
const { mode, currentUser, authorization, needsAuthRecovery, authRecoveryKind, globalLoginUrl } = useApAuth()
const { listScopes, listUsers } = useApUserManagement()

const serviceScopeId = computed(() => {
  const value = route.query.service_scope_id

  return typeof value === 'string' ? Number(value) : undefined
})

const tenantScopeId = computed(() => {
  const value = route.query.tenant_scope_id

  return typeof value === 'string' ? Number(value) : undefined
})

const activeScopeId = computed(() => tenantScopeId.value ?? serviceScopeId.value)
const keyword = computed(() => typeof route.query.keyword === 'string' ? route.query.keyword : '')
const sort = computed(() => typeof route.query.sort === 'string' ? route.query.sort : 'email')
const keywordDraft = ref(keyword.value)

watch(keyword, (value) => {
  keywordDraft.value = value
})

const { data: servicesData, status: servicesStatus } = await useAsyncData(
  'users-service-scopes',
  () => listScopes({ layer: 'service', sort: 'name' })
)

const { data: tenantsData, status: tenantsStatus } = await useAsyncData(
  'users-tenant-scopes',
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

const { data: usersData, status: usersStatus, error: usersError } = await useAsyncData(
  'users-index',
  () => listUsers({
    scope_id: activeScopeId.value,
    keyword: keyword.value || undefined,
    sort: sort.value
  }),
  {
    watch: [activeScopeId, keyword, sort]
  }
)

const services = computed(() => servicesData.value?.data ?? [])
const tenants = computed(() => tenantsData.value?.data ?? [])
const users = computed(() => usersData.value?.data ?? [])
const usersMeta = computed(() => usersData.value?.meta)
const usersErrorMessage = computed(() => {
  if (!usersError.value) {
    return null
  }

  return describeApApiError(usersError.value, 'users 一覧の取得に失敗しました。')
})

const selectedService = computed(() => services.value.find(scope => scope.id === serviceScopeId.value) ?? null)
const selectedTenant = computed(() => tenants.value.find(scope => scope.id === tenantScopeId.value) ?? null)
const activeScope = computed(() => selectedTenant.value ?? selectedService.value)
const userManageAccess = computed(() =>
  resolvePermissionAccessStatus(authorization.value, 'user.manage', activeScope.value?.id)
)
const authRecoveryCopy = computed(() => buildAuthRecoveryCopy(authRecoveryKind.value, 'users-index'))

const sortOptions = [
  { label: 'メールアドレス A-Z', value: 'email' },
  { label: 'メールアドレス Z-A', value: '-email' },
  { label: '表示名 A-Z', value: 'display_name' },
  { label: '表示名 Z-A', value: '-display_name' }
] as const

function withListQuery(query: Record<string, string | undefined>): RouteLocationRaw {
  return {
    path: '/users',
    query: Object.fromEntries(
      Object.entries(query).filter(([, value]) => value)
    )
  }
}

function updateQuery(next: Record<string, string | undefined>) {
  return navigateTo(withListQuery(next), { replace: true })
}

function applyKeyword() {
  return updateQuery({
    service_scope_id: serviceScopeId.value ? String(serviceScopeId.value) : undefined,
    tenant_scope_id: tenantScopeId.value ? String(tenantScopeId.value) : undefined,
    keyword: keywordDraft.value.trim() || undefined,
    sort: sort.value
  })
}

function changeSort(event: Event) {
  const nextSort = (event.target as HTMLSelectElement).value

  return updateQuery({
    service_scope_id: serviceScopeId.value ? String(serviceScopeId.value) : undefined,
    tenant_scope_id: tenantScopeId.value ? String(tenantScopeId.value) : undefined,
    keyword: keyword.value || undefined,
    sort: nextSort
  })
}

function selectService(scopeId?: number) {
  return updateQuery({
    service_scope_id: scopeId ? String(scopeId) : undefined,
    keyword: keyword.value || undefined,
    sort: sort.value
  })
}

function selectTenant(scopeId?: number) {
  return updateQuery({
    service_scope_id: serviceScopeId.value ? String(serviceScopeId.value) : undefined,
    tenant_scope_id: scopeId ? String(scopeId) : undefined,
    keyword: keyword.value || undefined,
    sort: sort.value
  })
}

function buildUserLink(keycloakSub: string): RouteLocationRaw {
  return {
    path: `/users/${keycloakSub}`,
    query: Object.fromEntries(
      Object.entries({
        service_scope_id: serviceScopeId.value ? String(serviceScopeId.value) : undefined,
        tenant_scope_id: tenantScopeId.value ? String(tenantScopeId.value) : undefined,
        keyword: keyword.value || undefined,
        sort: sort.value
      }).filter(([, value]) => value)
    )
  }
}
</script>

<template>
  <div class="flex flex-col gap-8 py-4">
    <section class="grid gap-6 rounded-[2rem] border border-white/70 bg-white/80 p-8 shadow-xl shadow-cyan-950/5 backdrop-blur lg:grid-cols-[minmax(280px,340px)_minmax(0,1fr)] dark:border-white/10 dark:bg-stone-900/70">
      <div class="space-y-5">
        <div class="space-y-3">
          <UBadge
            color="primary"
            variant="soft"
            class="rounded-full px-4 py-1 text-xs font-semibold tracking-[0.24em]"
          >
            USER MANAGEMENT
          </UBadge>

          <div class="space-y-3">
            <h1 class="text-3xl font-semibold tracking-tight text-highlighted sm:text-4xl">
              users 一覧の最小フロー
            </h1>
            <p class="text-sm leading-7 text-toned sm:text-base">
              service から tenant へ drill-down して対象 scope を決め、`keyword` と `sort` で一覧を絞る前提をそのまま画面に落としたプロトタイプです。
            </p>
          </div>
        </div>

        <UCard class="border-primary/20 bg-cyan-50/70 dark:bg-cyan-950/20">
          <template #header>
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-highlighted">
                  Data Source
                </p>
                <p class="text-xs text-muted">
                  `NUXT_PUBLIC_AP_USER_MANAGEMENT_MODE`
                </p>
              </div>
              <UBadge
                :color="mode === 'live' ? 'success' : 'warning'"
                variant="soft"
              >
                {{ mode === 'live' ? 'LIVE API' : 'MOCK DATA' }}
              </UBadge>
            </div>
          </template>

          <p class="text-sm leading-6 text-toned">
            認証受け口がまだ無い間は mock を既定にし、backend 接続を試す時だけ live へ切り替える構成です。
          </p>
        </UCard>

        <UCard
          v-if="mode === 'live'"
          class="border-amber-200 bg-amber-50/80 dark:border-amber-500/20 dark:bg-amber-950/20"
        >
          <template #header>
            <div class="flex items-center gap-2 text-sm font-semibold">
              <UIcon
                name="i-lucide-badge-alert"
                class="text-amber-600"
              />
              Live Check
            </div>
          </template>

          <p class="text-sm leading-6 text-toned">
            live mode の推奨 API Base は `https://ap-backend-fpm.example.com/api` です。一覧取得で `Forbidden` や `CurrentUser 未取得` に見える時は、まず `SSO Login` で session を張り直し、live debug を続ける時だけ Auth Entry で token と API Base を確認してください。
          </p>
          <p class="mt-2 text-xs leading-5 text-muted">
            `Failed to fetch` に見える時は session recovery ではなく、`ap-backend-fpm.example.com` の hosts と証明書許可を先に確認します。正常系では Auth Entry の `Current User` が `Alice A` になり、`user.manage` を持った状態でこの一覧が開きます。
          </p>
          <div
            v-if="needsAuthRecovery && authRecoveryCopy.body"
            class="mt-4 rounded-2xl border border-amber-300/70 bg-white/70 p-4 dark:bg-stone-950/30"
          >
            <p class="text-sm font-semibold text-highlighted">
              Re-auth Flow
            </p>
            <p class="mt-2 text-sm text-toned">
              {{ authRecoveryCopy.body }}
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
              <UButton
                :to="globalLoginUrl"
                color="primary"
                variant="soft"
                trailing-icon="i-lucide-log-in"
              >
                SSO Login
              </UButton>
              <UButton
                to="/#auth-entry"
                color="neutral"
                variant="soft"
                trailing-icon="i-lucide-arrow-up-right"
              >
                Auth Entry Debug
              </UButton>
            </div>
            <p class="mt-3 text-xs text-muted">
              {{ authRecoveryCopy.logoutHint }}
            </p>
          </div>
        </UCard>

        <UCard
          v-if="currentUser"
          class="border-default bg-stone-50/70 dark:bg-stone-950/40"
        >
          <template #header>
            <div class="flex items-center gap-2 text-sm font-semibold">
              <UIcon
                name="i-lucide-user-round-check"
                class="text-primary"
              />
              CurrentUser
            </div>
          </template>

          <p class="text-sm font-medium text-highlighted">
            {{ currentUser.name }}
          </p>
          <p class="text-sm text-toned">
            {{ currentUser.email }}
          </p>
          <p class="mt-2 text-xs text-muted">
            users API の live 呼び出しでもこの共通 auth 入口の token を使います。
          </p>
        </UCard>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
          <UCard>
            <template #header>
              <div class="flex items-center gap-2 text-sm font-semibold">
                <UIcon
                  name="i-lucide-git-branch-plus"
                  class="text-primary"
                />
                Step 1
              </div>
            </template>
            <p class="text-sm leading-6 text-toned">
              service を先に選び、tenant 一覧は `parent_scope_id` で追いかけます。
            </p>
          </UCard>

          <UCard>
            <template #header>
              <div class="flex items-center gap-2 text-sm font-semibold">
                <UIcon
                  name="i-lucide-search"
                  class="text-primary"
                />
                Step 2
              </div>
            </template>
            <p class="text-sm leading-6 text-toned">
              users 検索は `keyword` 1 入力だけを出し、`display_name` / `email` 横断検索に合わせます。
            </p>
          </UCard>
        </div>
      </div>

      <div class="space-y-6">
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
          <UCard>
            <template #header>
              <div class="flex items-center justify-between gap-3">
                <div>
                  <p class="text-sm font-semibold text-highlighted">
                    Service Scope
                  </p>
                  <p class="text-xs text-muted">
                    初回は query なしでも一覧表示
                  </p>
                </div>
                <UButton
                  color="neutral"
                  variant="ghost"
                  size="xs"
                  @click="selectService()"
                >
                  Clear
                </UButton>
              </div>
            </template>

            <div class="space-y-3">
              <button
                v-for="scope in services"
                :key="scope.id"
                class="flex w-full items-center justify-between rounded-2xl border px-4 py-3 text-left transition hover:border-primary/60 hover:bg-cyan-50/60 dark:hover:bg-cyan-950/20"
                :class="scope.id === serviceScopeId ? 'border-primary bg-cyan-50/80 dark:bg-cyan-950/30' : 'border-default'"
                @click="selectService(scope.id)"
              >
                <div>
                  <p class="font-medium text-highlighted">
                    {{ scope.name }}
                  </p>
                  <p class="text-xs text-muted">
                    {{ scope.code }}
                  </p>
                </div>
                <UBadge
                  color="neutral"
                  variant="soft"
                >
                  service
                </UBadge>
              </button>

              <p
                v-if="servicesStatus === 'pending'"
                class="text-sm text-muted"
              >
                service scopes を読み込み中です。
              </p>
            </div>
          </UCard>

          <UCard>
            <template #header>
              <div class="flex items-center justify-between gap-3">
                <div>
                  <p class="text-sm font-semibold text-highlighted">
                    Tenant Scope
                  </p>
                  <p class="text-xs text-muted">
                    service 選択後に tenant を絞り込み
                  </p>
                </div>
                <UButton
                  color="neutral"
                  variant="ghost"
                  size="xs"
                  :disabled="!tenantScopeId"
                  @click="selectTenant()"
                >
                  Clear
                </UButton>
              </div>
            </template>

            <div
              v-if="!serviceScopeId"
              class="rounded-2xl border border-dashed border-default px-4 py-8 text-sm text-muted"
            >
              先に service を選ぶと tenant 候補を表示します。
            </div>

            <div
              v-else
              class="space-y-3"
            >
              <button
                v-for="scope in tenants"
                :key="scope.id"
                class="flex w-full items-center justify-between rounded-2xl border px-4 py-3 text-left transition hover:border-primary/60 hover:bg-cyan-50/60 dark:hover:bg-cyan-950/20"
                :class="scope.id === tenantScopeId ? 'border-primary bg-cyan-50/80 dark:bg-cyan-950/30' : 'border-default'"
                @click="selectTenant(scope.id)"
              >
                <div>
                  <p class="font-medium text-highlighted">
                    {{ scope.name }}
                  </p>
                  <p class="text-xs text-muted">
                    {{ scope.code }}
                  </p>
                </div>
                <UBadge
                  color="neutral"
                  variant="soft"
                >
                  tenant
                </UBadge>
              </button>

              <p
                v-if="tenantsStatus === 'pending'"
                class="text-sm text-muted"
              >
                tenant scopes を読み込み中です。
              </p>
              <p
                v-else-if="tenants.length === 0"
                class="text-sm text-muted"
              >
                この service 配下に表示できる tenant はまだありません。
              </p>
            </div>
          </UCard>
        </div>

        <UCard class="overflow-hidden">
          <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <p class="text-sm font-semibold text-highlighted">
                  Users
                </p>
                <p class="text-xs text-muted">
                  backend query: `scope_id`, `keyword`, `sort`
                </p>
              </div>

              <div class="flex flex-col gap-3 sm:flex-row">
                <form
                  class="flex gap-2"
                  @submit.prevent="applyKeyword"
                >
                  <input
                    v-model="keywordDraft"
                    type="search"
                    placeholder="名前 / メールアドレスで検索"
                    class="w-full min-w-64 rounded-xl border border-default bg-white px-3 py-2 text-sm outline-none transition focus:border-primary dark:bg-stone-950"
                  >
                  <UButton
                    type="submit"
                    color="primary"
                    variant="soft"
                  >
                    Search
                  </UButton>
                </form>

                <label class="flex items-center gap-2 text-sm text-toned">
                  Sort
                  <select
                    :value="sort"
                    class="rounded-xl border border-default bg-white px-3 py-2 text-sm outline-none transition focus:border-primary dark:bg-stone-950"
                    @change="changeSort"
                  >
                    <option
                      v-for="option in sortOptions"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </label>
              </div>
            </div>
          </template>

          <div class="flex flex-wrap gap-2 border-b border-default/80 bg-stone-50/70 px-6 py-4 text-xs text-muted dark:bg-stone-950/40">
            <UBadge
              v-if="selectedService"
              color="neutral"
              variant="soft"
            >
              service: {{ selectedService.name }}
            </UBadge>
            <UBadge
              v-if="selectedTenant"
              color="primary"
              variant="soft"
            >
              tenant: {{ selectedTenant.name }}
            </UBadge>
            <UBadge
              v-if="activeScope"
              :color="userManageAccess.tone"
              variant="soft"
            >
              user.manage: {{ userManageAccess.label }}
            </UBadge>
            <UBadge
              v-if="keyword"
              color="warning"
              variant="soft"
            >
              keyword: {{ keyword }}
            </UBadge>
            <span v-if="!selectedService && !selectedTenant && !keyword">
              条件なしの初回表示です。
            </span>
          </div>

          <div
            v-if="activeScope"
            class="border-b border-default/80 px-6 py-4 text-sm dark:border-default/60"
          >
            <p class="font-medium text-highlighted">
              選択中 scope: {{ activeScope.name }}
            </p>
            <p class="mt-1 text-xs text-muted">
              `user.manage` はこの scope に対して
              <span class="font-semibold text-toned">{{ userManageAccess.label }}</span>
              として見えています。drill-down 先の tenant で `descendant access` と出ていれば、上位 scope の直付与から配下へ届いている状態です。
            </p>
          </div>

          <div
            v-if="usersStatus === 'pending'"
            class="px-6 py-10 text-sm text-muted"
          >
            users 一覧を読み込み中です。
          </div>

          <div
            v-else-if="usersErrorMessage"
            class="px-6 py-10 text-sm text-error"
          >
            {{ usersErrorMessage }}
          </div>

          <div
            v-else-if="users.length === 0"
            class="px-6 py-10 text-sm text-muted"
          >
            該当するユーザーがいません。
          </div>

          <div
            v-else
            class="divide-y divide-default/80"
          >
            <NuxtLink
              v-for="user in users"
              :key="user.keycloak_sub"
              :to="buildUserLink(user.keycloak_sub)"
              class="grid gap-4 px-6 py-5 transition hover:bg-cyan-50/60 dark:hover:bg-cyan-950/20 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto]"
            >
              <div class="space-y-1">
                <p class="font-semibold text-highlighted">
                  {{ user.display_name || 'No display name' }}
                </p>
                <p class="text-sm text-toned">
                  {{ user.email || 'No email' }}
                </p>
                <p class="text-xs text-muted">
                  {{ user.keycloak_sub }}
                </p>
              </div>

              <div class="flex flex-wrap gap-2">
                <UBadge
                  v-for="assignment in user.assignments"
                  :key="assignment.id"
                  color="neutral"
                  variant="soft"
                  class="rounded-full"
                >
                  {{ assignment.scope.name }} / {{ assignment.role.name }}
                </UBadge>
              </div>

              <div class="flex items-start justify-end">
                <UButton
                  color="neutral"
                  variant="ghost"
                  trailing-icon="i-lucide-arrow-right"
                >
                  詳細
                </UButton>
              </div>
            </NuxtLink>
          </div>

          <template #footer>
            <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-toned">
              <p>
                {{ usersMeta?.total ?? users.length }} users
              </p>
              <p class="text-xs text-muted">
                今回は page 復元 UI をまだ出さず、一覧導線と検索条件の最小化を優先しています。
              </p>
            </div>
          </template>
        </UCard>
      </div>
    </section>
  </div>
</template>
