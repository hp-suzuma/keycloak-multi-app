<script setup lang="ts">
import { buildAuthRecoveryCopy } from '~/utils/authRecoveryCopy'

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
const shouldShowSsoLogin = computed(() => mode.value === 'live' || isLoggedOutRedirect.value)
const shouldShowSsoLogout = computed(() => mode.value === 'live' && !isLoggedOutRedirect.value)
const authRecoveryCopy = computed(() => buildAuthRecoveryCopy(authRecoveryKind.value, 'auth-entry', {
  errorMessage: errorMessage.value
}))
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
  if (isLoggedOutRedirect.value && mode.value !== 'live') {
    setMode('live')
  }

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
            {{ isRecommendedApiBase ? '現在の API Base は推奨値です。' : 'API Base が推奨値から外れている時は、まず `ap-backend-fpm.example.com/api` にそろえてから切り分けてください。' }}
          </p>
          <p class="mt-2 text-xs leading-5 text-muted">
            `CurrentUser 未取得` や `401/403` は、まず `SSO Login` で session を張り直し、live debug を続ける時だけ Bearer token と API Base を確認します。`Failed to fetch` は session recovery ではなく、hosts と証明書許可を先に確認してください。
          </p>
          <div
            v-if="shouldShowSsoLogin || shouldShowSsoLogout"
            class="mt-4 flex flex-wrap gap-2"
          >
            <UButton
              v-if="shouldShowSsoLogin"
              :to="globalLoginUrl"
              color="primary"
              variant="soft"
              trailing-icon="i-lucide-log-in"
            >
              SSO Login
            </UButton>
            <UButton
              v-if="shouldShowSsoLogout"
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
          v-if="isLoggedOutRedirect"
          class="rounded-2xl border border-success/30 bg-success/10 p-4 dark:border-success/20"
        >
          <p class="text-xs uppercase tracking-[0.18em] text-muted">
            Logout Complete
          </p>
          <p class="mt-2 text-sm text-toned">
            global SSO logout が完了し、AP Frontend 側の local token もクリアしました。通常の再開は `SSO Login` を使い、live debug を続ける時だけ token を再設定します。
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
            {{ hasUserManagePermission ? '`user.manage` を確認済みです。users 管理 UI の live 検証に進めます。' : '`user.manage` が見えない時は、まず `SSO Login` で session を張り直し、debug を続ける時だけ token と API Base を確認してから再取得してください。' }}
          </p>
        </div>

        <div
          v-if="mode === 'live' && authRecoveryKind !== 'none'"
          class="rounded-2xl border border-warning/30 bg-warning/10 p-4 dark:border-warning/20"
        >
          <p class="text-xs uppercase tracking-[0.18em] text-muted">
            {{ authRecoveryCopy.title }}
          </p>
          <p class="mt-2 text-sm text-toned">
            {{ authRecoveryCopy.body }}
          </p>
          <p
            v-if="liveSessionLooksExpired"
            class="mt-2 text-xs text-muted"
          >
            live mode では、期限切れまたは無効な Bearer token でも Auth Entry 上は `403` ではなく `null` として見えることがあります。
          </p>
          <ul
            v-if="authRecoveryCopy.steps.length"
            class="mt-3 space-y-1 text-xs text-muted"
          >
            <li
              v-for="step in authRecoveryCopy.steps"
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
          <p class="mt-2 text-xs leading-5 text-muted">
            実運用の主導線は `SSO Login` で、下の token 入力は live debug 専用です。network failure が見えている時は、ここを触る前に `ap-backend-fpm.example.com` の hosts と証明書許可を確認します。
          </p>
          <ul class="mt-2 space-y-1 text-sm text-toned">
            <li>1. 実運用確認ではまず `SSO Login` で session を張り直す</li>
            <li>2. live debug が必要な時だけ `alice` の fresh token を貼って `Apply & Refresh`</li>
            <li>3. `Current User` が `Alice A` になり、`permissions` が埋まることを確認する</li>
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
          users 管理の live debug は `alice` の fresh token を入れてから `Apply & Refresh` し、その後に `/users` へ進みます。通常の session recovery はこのフォームではなく `SSO Login` を使います。
        </p>
      </form>
    </div>
  </UCard>
</template>
