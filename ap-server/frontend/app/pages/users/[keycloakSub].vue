<script setup lang="ts">
definePageMeta({
  layout: 'dashboard'
})

const route = useRoute()
const keycloakSub = computed(() => String(route.params.keycloakSub))
const { mode, currentUser } = useApAuth()
const { getUser } = useApUserManagement()

const backQuery = computed(() => Object.fromEntries(
  Object.entries({
    service_scope_id: typeof route.query.service_scope_id === 'string' ? route.query.service_scope_id : undefined,
    tenant_scope_id: typeof route.query.tenant_scope_id === 'string' ? route.query.tenant_scope_id : undefined,
    keyword: typeof route.query.keyword === 'string' ? route.query.keyword : undefined,
    sort: typeof route.query.sort === 'string' ? route.query.sort : undefined
  }).filter(([, value]) => value)
))

const { data, status, error } = await useAsyncData(
  () => `users-detail:${keycloakSub.value}`,
  () => getUser(keycloakSub.value),
  {
    watch: [keycloakSub]
  }
)

const user = computed(() => data.value?.data)
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
      users 詳細の取得に失敗しました。live mode の場合は Bearer token と backend URL を確認してください。
    </div>

    <template v-else>
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
                今回は表示のみ。追加・削除 UI は次段で検討します。
              </p>
            </div>
            <UIcon name="i-lucide-shield-check" class="text-2xl text-primary" />
          </div>
        </template>

        <div class="space-y-4">
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
          </section>
        </div>
      </UCard>
    </template>
  </div>
</template>
