<script setup lang="ts">
import { describeApApiError } from '~/utils/apApiError'
import { buildAuthRecoveryCopy } from '~/utils/authRecoveryCopy'
import { resolvePermissionAccessStatus } from '~/utils/permissionScopes'

definePageMeta({
  layout: 'dashboard'
})

const route = useRoute()
const { mode, currentUser, authorization, needsAuthRecovery, authRecoveryKind, globalLoginUrl } = useApAuth()
const { listScopes, listObjects } = useApUserManagement()

const serviceScopeId = computed(() => {
  const value = route.query.service_scope_id

  return typeof value === 'string' ? Number(value) : undefined
})

const tenantScopeId = computed(() => {
  const value = route.query.tenant_scope_id

  return typeof value === 'string' ? Number(value) : undefined
})

const activeScopeId = computed(() => tenantScopeId.value ?? serviceScopeId.value)
const code = computed(() => typeof route.query.code === 'string' ? route.query.code : '')
const name = computed(() => typeof route.query.name === 'string' ? route.query.name : '')
const sort = computed(() => typeof route.query.sort === 'string' ? route.query.sort : 'code')
const codeDraft = ref(code.value)
const nameDraft = ref(name.value)

watch(code, (value) => {
  codeDraft.value = value
})

watch(name, (value) => {
  nameDraft.value = value
})

const { data: scopesData } = await useAsyncData(
  'objects-scopes',
  () => listScopes({ sort: 'name' })
)

const { data: objectsData, status: objectsStatus, error: objectsError } = await useAsyncData(
  'objects-index',
  () => listObjects({
    scope_id: activeScopeId.value,
    code: code.value || undefined,
    name: name.value || undefined,
    sort: sort.value
  }),
  {
    watch: [activeScopeId, code, name, sort]
  }
)

const scopes = computed(() => scopesData.value?.data ?? [])
const services = computed(() => scopes.value.filter(scope => scope.layer === 'service'))
const tenants = computed(() => scopes.value.filter(
  scope => scope.layer === 'tenant' && scope.parent_scope_id === serviceScopeId.value
))
const objects = computed(() => objectsData.value?.data ?? [])
const objectsMeta = computed(() => objectsData.value?.meta)
const objectsErrorMessage = computed(() => {
  if (!objectsError.value) {
    return null
  }

  return describeApApiError(objectsError.value, 'objects 一覧の取得に失敗しました。')
})

const selectedService = computed(() => services.value.find(scope => scope.id === serviceScopeId.value) ?? null)
const selectedTenant = computed(() => tenants.value.find(scope => scope.id === tenantScopeId.value) ?? null)
const activeScope = computed(() => selectedTenant.value ?? selectedService.value)
const scopeMap = computed(() => new Map(scopes.value.map(scope => [scope.id, scope])))
const objectReadAccess = computed(() =>
  resolvePermissionAccessStatus(authorization.value, 'object.read', activeScope.value?.id)
)
const authRecoveryCopy = computed(() => buildAuthRecoveryCopy(authRecoveryKind.value, 'operations-index'))

const sortOptions = [
  { label: 'コード A-Z', value: 'code' },
  { label: 'コード Z-A', value: '-code' },
  { label: '名称 A-Z', value: 'name' },
  { label: '名称 Z-A', value: '-name' }
] as const

async function updateQuery(next: Record<string, string | undefined>) {
  await navigateTo({
    path: '/objects',
    query: Object.fromEntries(
      Object.entries(next).filter(([, value]) => value)
    )
  }, { replace: true })
}

async function applyFilters() {
  await updateQuery({
    service_scope_id: serviceScopeId.value ? String(serviceScopeId.value) : undefined,
    tenant_scope_id: tenantScopeId.value ? String(tenantScopeId.value) : undefined,
    code: codeDraft.value.trim() || undefined,
    name: nameDraft.value.trim() || undefined,
    sort: sort.value
  })
}

async function resetFilters() {
  codeDraft.value = ''
  nameDraft.value = ''

  await updateQuery({
    service_scope_id: serviceScopeId.value ? String(serviceScopeId.value) : undefined,
    tenant_scope_id: tenantScopeId.value ? String(tenantScopeId.value) : undefined,
    sort: 'code'
  })
}

async function selectService(scopeId?: number) {
  await updateQuery({
    service_scope_id: scopeId ? String(scopeId) : undefined,
    code: code.value || undefined,
    name: name.value || undefined,
    sort: sort.value
  })
}

async function selectTenant(scopeId?: number) {
  await updateQuery({
    service_scope_id: serviceScopeId.value ? String(serviceScopeId.value) : undefined,
    tenant_scope_id: scopeId ? String(scopeId) : undefined,
    code: code.value || undefined,
    name: name.value || undefined,
    sort: sort.value
  })
}

async function changeSort(event: Event) {
  const nextSort = (event.target as HTMLSelectElement).value

  await updateQuery({
    service_scope_id: serviceScopeId.value ? String(serviceScopeId.value) : undefined,
    tenant_scope_id: tenantScopeId.value ? String(tenantScopeId.value) : undefined,
    code: code.value || undefined,
    name: name.value || undefined,
    sort: nextSort
  })
}
</script>

<template>
  <div class="space-y-6">
    <DashboardPageHeader
      badge="OBJECTS"
      title="Objects"
      description="`GET /api/objects` の最小契約として、scope filter・code/name 検索・sort・一覧表示を先に通した画面です。"
    />

    <div class="grid gap-4 xl:grid-cols-3">
      <UCard class="rounded-[1.75rem] border-primary/20 bg-cyan-50/70 dark:bg-cyan-950/20">
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
          objects 一覧も users と同じ auth 入口を使い、mock / live の切り替えだけを共通で持ちます。
        </p>
      </UCard>

      <UCard class="rounded-[1.75rem] border-white/70 bg-white/80 dark:border-white/10 dark:bg-stone-900/70">
        <template #header>
          <p class="text-sm font-semibold text-highlighted">
            Active Scope
          </p>
        </template>
        <p class="text-lg font-semibold text-highlighted">
          {{ activeScope?.name || 'All Accessible Scopes' }}
        </p>
        <p class="text-sm text-toned">
          {{ activeScope ? `${activeScope.layer} / ${activeScope.code}` : 'scope 未指定なら閲覧可能範囲全体を対象にします。' }}
        </p>
        <p class="mt-3 text-xs text-muted">
          `object.read`: {{ objectReadAccess }}
        </p>
      </UCard>

      <UCard class="rounded-[1.75rem] border-white/70 bg-white/80 dark:border-white/10 dark:bg-stone-900/70">
        <template #header>
          <p class="text-sm font-semibold text-highlighted">
            Result Summary
          </p>
        </template>
        <p class="text-3xl font-semibold text-highlighted">
          {{ objectsMeta?.total ?? objects.length }}
        </p>
        <p class="text-sm text-toned">
          現在の filter で見えている object 件数です。
        </p>
        <p class="mt-3 text-xs text-muted">
          sort: {{ objectsMeta?.filters.sort || sort }}
        </p>
      </UCard>
    </div>

    <UCard
      v-if="mode === 'live'"
      class="rounded-[1.75rem] border-amber-200 bg-amber-50/80 dark:border-amber-500/20 dark:bg-amber-950/20"
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
        live mode の推奨 API Base は `https://ap-backend-fpm.example.com/api` です。objects 一覧で `401/403` や `CurrentUser 未取得` に見える時は、まず `SSO Login` で session を張り直し、live debug を続ける時だけ Auth Entry で token と API Base を確認してください。
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

    <UCard class="rounded-[1.75rem] border-white/70 bg-white/80 dark:border-white/10 dark:bg-stone-900/70">
      <template #header>
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-sm font-semibold text-highlighted">
              Filter & Sort
            </p>
            <p class="text-xs text-muted">
              service / tenant scope と code / name の最小入力
            </p>
          </div>
          <UBadge
            v-if="currentUser"
            color="primary"
            variant="soft"
          >
            {{ currentUser.name }}
          </UBadge>
        </div>
      </template>

      <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-5">
        <label class="space-y-2">
          <span class="text-sm font-medium text-highlighted">Service</span>
          <select
            class="w-full rounded-2xl border border-default bg-default px-4 py-3 text-sm"
            :value="serviceScopeId ?? ''"
            @change="selectService(($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : undefined)"
          >
            <option value="">
              All Services
            </option>
            <option
              v-for="service in services"
              :key="service.id"
              :value="service.id"
            >
              {{ service.name }}
            </option>
          </select>
        </label>

        <label class="space-y-2">
          <span class="text-sm font-medium text-highlighted">Tenant</span>
          <select
            class="w-full rounded-2xl border border-default bg-default px-4 py-3 text-sm"
            :value="tenantScopeId ?? ''"
            :disabled="!serviceScopeId"
            @change="selectTenant(($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : undefined)"
          >
            <option value="">
              {{ serviceScopeId ? 'All Tenants In Service' : 'Select Service First' }}
            </option>
            <option
              v-for="tenant in tenants"
              :key="tenant.id"
              :value="tenant.id"
            >
              {{ tenant.name }}
            </option>
          </select>
        </label>

        <label class="space-y-2">
          <span class="text-sm font-medium text-highlighted">Code</span>
          <input
            v-model="codeDraft"
            type="text"
            placeholder="tenant-object"
            class="w-full rounded-2xl border border-default bg-default px-4 py-3 text-sm"
          >
        </label>

        <label class="space-y-2">
          <span class="text-sm font-medium text-highlighted">Name</span>
          <input
            v-model="nameDraft"
            type="text"
            placeholder="Tenant Object"
            class="w-full rounded-2xl border border-default bg-default px-4 py-3 text-sm"
          >
        </label>

        <label class="space-y-2">
          <span class="text-sm font-medium text-highlighted">Sort</span>
          <select
            class="w-full rounded-2xl border border-default bg-default px-4 py-3 text-sm"
            :value="sort"
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

      <div class="mt-4 flex flex-wrap gap-3">
        <UButton
          color="primary"
          @click="applyFilters"
        >
          Apply Filters
        </UButton>
        <UButton
          color="neutral"
          variant="soft"
          @click="resetFilters"
        >
          Reset
        </UButton>
      </div>
    </UCard>

    <UCard class="rounded-[1.75rem] border-white/70 bg-white/80 dark:border-white/10 dark:bg-stone-900/70">
      <template #header>
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-sm font-semibold text-highlighted">
              Objects List
            </p>
            <p class="text-xs text-muted">
              現在は一覧取得までを先行し、detail / update 導線は次段で足します。
            </p>
          </div>
          <UBadge
            :color="objectsStatus === 'success' ? 'primary' : 'neutral'"
            variant="soft"
          >
            {{ objectsStatus }}
          </UBadge>
        </div>
      </template>

      <div
        v-if="objectsErrorMessage"
        class="rounded-2xl border border-red-200 bg-red-50/80 px-4 py-4 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-950/20 dark:text-red-200"
      >
        {{ objectsErrorMessage }}
      </div>

      <div
        v-else-if="objects.length === 0"
        class="rounded-2xl border border-dashed border-default px-4 py-8 text-sm text-muted"
      >
        条件に一致する object はありません。scope または code / name filter を見直してください。
      </div>

      <div
        v-else
        class="overflow-x-auto"
      >
        <table class="min-w-full divide-y divide-default text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-[0.2em] text-muted">
              <th class="px-4 py-3 font-semibold">
                Code
              </th>
              <th class="px-4 py-3 font-semibold">
                Name
              </th>
              <th class="px-4 py-3 font-semibold">
                Scope
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-default">
            <tr
              v-for="object in objects"
              :key="object.id"
              class="transition hover:bg-cyan-50/50 dark:hover:bg-cyan-950/10"
            >
              <td class="px-4 py-4 font-mono text-xs text-highlighted sm:text-sm">
                {{ object.code }}
              </td>
              <td class="px-4 py-4 text-highlighted">
                {{ object.name }}
              </td>
              <td class="px-4 py-4">
                <div class="space-y-1">
                  <p class="font-medium text-highlighted">
                    {{ scopeMap.get(object.scope_id)?.name || `Scope #${object.scope_id}` }}
                  </p>
                  <p class="text-xs text-muted">
                    {{ scopeMap.get(object.scope_id)?.layer || 'unknown' }} / {{ scopeMap.get(object.scope_id)?.code || object.scope_id }}
                  </p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex flex-wrap gap-2 text-xs text-muted">
        <span v-if="objectsMeta?.filters.scope_id">scope_id: {{ objectsMeta.filters.scope_id }}</span>
        <span v-if="objectsMeta?.filters.code">code: {{ objectsMeta.filters.code }}</span>
        <span v-if="objectsMeta?.filters.name">name: {{ objectsMeta.filters.name }}</span>
        <span>sort: {{ objectsMeta?.filters.sort || sort }}</span>
      </div>
    </UCard>
  </div>
</template>
