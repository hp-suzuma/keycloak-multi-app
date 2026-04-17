<script setup lang="ts">
const {
  mode,
  apiBase,
  bearerToken,
  currentUser,
  authorization,
  status,
  errorMessage,
  hasBearerToken,
  isLiveReady,
  setMode,
  setBearerToken,
  refreshCurrentUser
} = useApAuth()

const RECOMMENDED_AP_API_BASE = 'https://ap-backend-fpm.example.com/api'
const tokenDraft = ref('')
const isSubmitting = ref(false)
const isRecommendedApiBase = computed(() => apiBase.value === RECOMMENDED_AP_API_BASE)
const hasUserManagePermission = computed(() => authorization.value?.permissions.includes('user.manage') ?? false)

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

onMounted(async () => {
  if (status.value === 'idle') {
    await refreshCurrentUser()
  }
})
</script>

<template>
  <UCard class="border-primary/20 bg-white/80 shadow-lg shadow-cyan-950/5 backdrop-blur dark:bg-stone-900/70">
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

        <p v-if="errorMessage" class="text-sm text-error">
          {{ errorMessage }}
        </p>
      </div>

      <form class="space-y-3 rounded-[1.5rem] border border-default bg-stone-50/70 p-4 dark:bg-stone-950/40" @submit.prevent="applySettings">
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
          <UButton type="submit" color="primary" :loading="isSubmitting || status === 'loading'">
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
