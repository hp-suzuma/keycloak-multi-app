<script setup lang="ts">
const {
  mode,
  apiBase,
  bearerToken,
  globalLoginUrl,
  globalLogoutUrl,
  currentUser,
  authorization,
  status,
  errorMessage,
  hasBearerToken,
  isLiveReady,
  liveSessionLooksExpired,
  authRecoveryKind,
  setMode,
  setBearerToken,
  clearClientAuth,
  refreshCurrentUser
} = useApAuth()
const { storeLogoutReturnNext } = useApSso()

const RECOMMENDED_AP_API_BASE = 'https://ap-backend-fpm.example.com/api'
const route = useRoute()
const tokenDraft = ref('')
const isSubmitting = ref(false)
const isRecommendedApiBase = computed(() => apiBase.value === RECOMMENDED_AP_API_BASE)
const hasUserManagePermission = computed(() => authorization.value?.permissions.includes('user.manage') ?? false)
const isLoggedOutRedirect = computed(() => route.query.logged_out === '1')
const authRecoveryTitle = computed(() => {
  if (authRecoveryKind.value === 'setup') {
    return 'Session Setup'
  }

  if (authRecoveryKind.value === 'refresh') {
    return 'Re-auth Required'
  }

  if (authRecoveryKind.value === 'retry') {
    return 'API Retry'
  }

  return 'Auth Status'
})
const authRecoveryBody = computed(() => {
  if (authRecoveryKind.value === 'setup') {
    return '実運用の session recovery は `global.example.com/login` へ戻す方針です。Auth Entry の Bearer token 入力は live debug 用として残しています。'
  }

  if (authRecoveryKind.value === 'refresh') {
    return '`GET /api/me` と `/api/me/authorization` は成功しましたが、どちらも `current_user: null` でした。実運用では SSO Login へ戻し、debug 時だけ fresh token へ入れ替えて再確認します。'
  }

  if (authRecoveryKind.value === 'retry') {
    return errorMessage.value ?? 'live API への再取得が必要です。実運用では SSO Login へ戻し、debug 時だけ API Base と token を確認して再試行してください。'
  }

  return 'Auth Entry で live session を確認できます。'
})
const authRecoverySteps = computed(() => {
  if (authRecoveryKind.value === 'setup') {
    return [
      '1. 実運用では `SSO Login` から global login へ戻る',
      '2. live debug が必要な時だけ Bearer token を貼って `Apply & Refresh` を押す',
      '3. `Current User` と `permissions` が埋まることを確認する'
    ]
  }

  if (authRecoveryKind.value === 'refresh' || authRecoveryKind.value === 'retry') {
    return [
      '1. 実運用では `SSO Login` から global login へ戻る',
      '2. live debug が必要な時だけ fresh token を取り直して `Apply & Refresh` を押す',
      '3. `Current User` が復帰したあと users 画面へ戻る'
    ]
  }

  return []
})
const scopeLabelById = computed(() => {
  const labels = new Map<number, string>()

  for (const assignment of authorization.value?.assignments ?? []) {
    labels.set(assignment.scope.id, `${assignment.scope.name} (${assignment.scope.layer})`)
  }

  return labels
})
const permissionScopeEntries = computed(() =>
  Object.entries(authorization.value?.permission_scopes ?? {}).map(([permission, scopeAccess]) => ({
    permission,
    grantedScopeIds: scopeAccess.granted_scope_ids,
    accessibleScopeIds: scopeAccess.accessible_scope_ids,
    grantedScopeLabels: scopeAccess.granted_scope_ids.map(scopeId => scopeLabelById.value.get(scopeId) ?? `scope#${scopeId}`),
    accessibleScopeLabels: scopeAccess.accessible_scope_ids.map(scopeId => scopeLabelById.value.get(scopeId) ?? `scope#${scopeId}`)
  }))
)

watchEffect(() => {
  tokenDraft.value = bearerToken.value
})

async function changeMode(nextMode: 'mock' | 'live') {
  setMode(nextMode)
  await refreshCurrentUser()
}

async function applySettings() {
  isSubmitting.value = true
  setBearerToken(tokenDraft.value)
  await refreshCurrentUser()
  isSubmitting.value = false
}

async function refreshOnly() {
  isSubmitting.value = true
  await refreshCurrentUser()
  isSubmitting.value = false
}

async function signOutFromSso() {
  storeLogoutReturnNext()
  clearClientAuth()
  await navigateTo(globalLogoutUrl.value, { external: true })
}

onMounted(async () => {
  if (status.value === 'idle') {
    await refreshCurrentUser()
  }
})
</script>

<template>
  <UCard
    id="auth-entry"
    class="border-primary/20 bg-white/80 shadow-lg shadow-cyan-950/5 backdrop-blur dark:bg-stone-900/70"
  >
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-sm font-semibold text-highlighted">
            Auth Entry
          </p>
          <p class="text-xs text-muted">
            `GET /api/me` を CurrentUser の共通入口として使います
          </p>
        </div>

        <div class="flex items-center gap-2">
          <UButton
            color="neutral"
            :variant="mode === 'mock' ? 'solid' : 'soft'"
            size="xs"
            @click="changeMode('mock')"
          >
            Mock
          </UButton>
          <UButton
            color="primary"
            :variant="mode === 'live' ? 'solid' : 'soft'"
            size="xs"
            @click="changeMode('live')"
          >
            Live
          </UButton>
        </div>
      </div>
    </template>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(320px,1fr)]">
      <div class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-2">
          <div class="rounded-2xl border border-default bg-stone-50/70 p-4 dark:bg-stone-950/40">
            <p class="text-xs uppercase tracking-[0.18em] text-muted">
              Mode
            </p>
            <p class="mt-2 text-sm font-semibold text-highlighted">
              {{ mode.toUpperCase() }}
            </p>
          </div>

          <div class="rounded-2xl border border-default bg-stone-50/70 p-4 dark:bg-stone-950/40">
            <p class="text-xs uppercase tracking-[0.18em] text-muted">
              Current User
            </p>
            <p class="mt-2 text-sm font-semibold text-highlighted">
              {{ currentUser?.name || 'Not resolved yet' }}
            </p>
            <p class="text-xs text-muted">
              {{ currentUser?.email || 'CurrentUser 未取得' }}
            </p>
          </div>
        </div>

        <div class="rounded-2xl border border-default bg-cyan-50/70 p-4 dark:bg-cyan-950/20">
          <p class="text-xs uppercase tracking-[0.18em] text-muted">
            Live Readiness
          </p>
          <p class="mt-2 text-sm text-toned">
            API Base: {{ apiBase || 'not set' }}
          </p>
          <p class="text-sm text-toned">
            Bearer Token: {{ hasBearerToken ? 'configured' : 'missing' }}
          </p>
          <p class="mt-2 text-xs text-muted">
            {{ isLiveReady ? 'live mode で CurrentUser を取得できます。' : 'live mode では API Base と Bearer token の両方が必要です。' }}
          </p>
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 dark:border-amber-500/20 dark:bg-amber-950/20">
          <p class="text-xs uppercase tracking-[0.18em] text-muted">
            Live Mode Tips
          </p>
          <p class="mt-2 text-sm text-toned">
            推奨 API Base: {{ RECOMMENDED_AP_API_BASE }}
          </p>
          <p class="text-xs leading-5 text-muted">
            {{ isRecommendedApiBase ? '現在の API Base は推奨値です。' : 'current_user が null / Forbidden に見える時は、まず fresh token へ入れ替えてから再試行してください。' }}
          </p>
          <p class="mt-2 text-xs leading-5 text-muted">
            live 検証で使っている Keycloak token は 5 分程度で期限切れになります。users 一覧 / 詳細 / assignment 操作を続けて確認する時は、先に token を更新しておくと切り分けがぶれません。
          </p>
          <div
            v-if="mode === 'live'"
            class="mt-4 flex flex-wrap gap-2"
          >
            <UButton
              :to="globalLoginUrl"
              color="primary"
              variant="soft"
              trailing-icon="i-lucide-log-in"
            >
              SSO Login
            </UButton>
            <UButton
              color="neutral"
              variant="soft"
              trailing-icon="i-lucide-log-out"
              @click="signOutFromSso"
            >
              SSO Logout
            </UButton>
          </div>
        </div>

        <div
          v-if="mode === 'live' && isLoggedOutRedirect"
          class="rounded-2xl border border-success/30 bg-success/10 p-4 dark:border-success/20"
        >
          <p class="text-xs uppercase tracking-[0.18em] text-muted">
            Logout Complete
          </p>
          <p class="mt-2 text-sm text-toned">
            global SSO logout が完了し、AP Frontend 側の local token もクリアしました。次に続ける時は `SSO Login` か debug 用 token 再設定を使います。
          </p>
        </div>

        <div class="rounded-2xl border border-default bg-stone-50/70 p-4 dark:bg-stone-950/40">
          <p class="text-xs uppercase tracking-[0.18em] text-muted">
            Authorization
          </p>
          <p class="mt-2 text-sm text-toned">
            assignments: {{ authorization?.assignments.length ?? 0 }}
          </p>
          <p class="text-sm text-toned">
            permissions: {{ authorization?.permissions.length ?? 0 }}
          </p>
          <p class="mt-2 text-xs text-muted">
            {{ hasUserManagePermission ? '`user.manage` を確認済みです。users 管理 UI の live 検証に進めます。' : '`user.manage` が見えない場合は、token を取り直してから再取得してください。' }}
          </p>
        </div>

        <div
          v-if="mode === 'live' && authRecoveryKind !== 'none'"
          class="rounded-2xl border border-warning/30 bg-warning/10 p-4 dark:border-warning/20"
        >
          <p class="text-xs uppercase tracking-[0.18em] text-muted">
            {{ authRecoveryTitle }}
          </p>
          <p class="mt-2 text-sm text-toned">
            {{ authRecoveryBody }}
          </p>
          <p
            v-if="liveSessionLooksExpired"
            class="mt-2 text-xs text-muted"
          >
            live mode では、期限切れまたは無効な Bearer token でも Auth Entry 上は `403` ではなく `null` として見えることがあります。
          </p>
          <ul
            v-if="authRecoverySteps.length"
            class="mt-3 space-y-1 text-xs text-muted"
          >
            <li
              v-for="step in authRecoverySteps"
              :key="step"
            >
              {{ step }}
            </li>
          </ul>
        </div>

        <div class="rounded-2xl border border-default bg-stone-50/70 p-4 dark:bg-stone-950/40">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <p class="text-xs uppercase tracking-[0.18em] text-muted">
                Permission Scope Debug
              </p>
              <p class="mt-2 text-xs text-muted">
                menu 切り替えは従来どおり `permissions` を使い、ここでは direct grant と descendant access の根拠だけを補助表示します。
              </p>
            </div>
            <UBadge
              color="neutral"
              variant="soft"
            >
              {{ permissionScopeEntries.length }} permissions
            </UBadge>
          </div>

          <div
            v-if="permissionScopeEntries.length"
            class="mt-4 space-y-3"
          >
            <div
              v-for="entry in permissionScopeEntries"
              :key="entry.permission"
              class="rounded-2xl border border-default bg-white/80 p-3 dark:bg-stone-900/70"
            >
              <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-semibold text-highlighted">
                  {{ entry.permission }}
                </p>
                <div class="flex flex-wrap gap-2">
                  <UBadge
                    color="neutral"
                    variant="soft"
                  >
                    direct {{ entry.grantedScopeIds.length }}
                  </UBadge>
                  <UBadge
                    color="primary"
                    variant="soft"
                  >
                    accessible {{ entry.accessibleScopeIds.length }}
                  </UBadge>
                </div>
              </div>

              <p class="mt-3 text-xs uppercase tracking-[0.14em] text-muted">
                Direct Grant
              </p>
              <p class="mt-1 text-sm text-toned">
                {{ entry.grantedScopeLabels.join(', ') || 'none' }}
              </p>

              <p class="mt-3 text-xs uppercase tracking-[0.14em] text-muted">
                Accessible Scope
              </p>
              <p class="mt-1 text-sm text-toned">
                {{ entry.accessibleScopeLabels.join(', ') || 'none' }}
              </p>
            </div>
          </div>

          <p
            v-else
            class="mt-3 text-sm text-muted"
          >
            `/api/me/authorization` で permission_scopes が返ると、ここに direct grant と accessible scope の差分を表示します。
          </p>
        </div>

        <div class="rounded-2xl border border-default bg-stone-50/70 p-4 dark:bg-stone-950/40">
          <p class="text-xs uppercase tracking-[0.18em] text-muted">
            Live Verification
          </p>
          <ul class="mt-2 space-y-1 text-sm text-toned">
            <li>1. `Live` に切り替える</li>
            <li>2. `alice` の fresh token を貼って `Apply & Refresh`</li>
            <li>3. `Current User` が `Alice A` になることを確認する</li>
            <li>4. `user.manage` を確認してから `/users` へ進む</li>
          </ul>
        </div>

        <p
          v-if="errorMessage"
          class="text-sm text-error"
        >
          {{ errorMessage }}
        </p>
      </div>

      <form
        class="space-y-3 rounded-[1.5rem] border border-default bg-stone-50/70 p-4 dark:bg-stone-950/40"
        @submit.prevent="applySettings"
      >
        <label class="block space-y-2">
          <span class="text-sm font-medium text-highlighted">Bearer Token Override</span>
          <textarea
            v-model="tokenDraft"
            rows="5"
            placeholder="live mode で使う Bearer token を入力"
            class="w-full rounded-2xl border border-default bg-white px-3 py-2 text-sm outline-none transition focus:border-primary dark:bg-stone-950"
          />
        </label>

        <div class="flex flex-wrap items-center gap-2">
          <UButton
            type="submit"
            color="primary"
            :loading="isSubmitting || status === 'loading'"
          >
            Apply & Refresh
          </UButton>
          <UButton
            type="button"
            color="neutral"
            variant="soft"
            :loading="isSubmitting || status === 'loading'"
            @click="refreshOnly"
          >
            Refresh Only
          </UButton>
          <UButton
            type="button"
            color="neutral"
            variant="soft"
            @click="tokenDraft = ''; setBearerToken('')"
          >
            Clear Token
          </UButton>
          <UButton
            type="button"
            color="neutral"
            variant="soft"
            @click="tokenDraft = ''; clearClientAuth()"
          >
            Reset Session
          </UButton>
        </div>

        <p class="text-xs leading-5 text-muted">
          runtime config の token がある場合も、ここで上書きした token を優先します。設定はブラウザの localStorage に保持します。
        </p>
        <p class="text-xs leading-5 text-muted">
          users 管理の live 検証は `alice` の fresh token を入れてから `Apply & Refresh` し、その後に `/users` へ進む運用を前提にしています。
        </p>
      </form>
    </div>
  </UCard>
</template>
