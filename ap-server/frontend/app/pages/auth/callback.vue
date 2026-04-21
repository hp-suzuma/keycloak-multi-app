<script setup lang="ts">
definePageMeta({
  layout: 'default'
})

const route = useRoute()
const {
  setMode,
  setBearerToken,
  refreshCurrentUser
} = useApAuth()
const {
  completeBridgeSession,
  globalLoginUrl,
  clearStoredState,
  appendSsoDebugTrace,
  readSsoDebugTrace,
  summarizeSsoDebugTrace
} = useApSso()

const next = computed(() => typeof route.query.next === 'string' ? route.query.next : '/')
const errorMessage = ref<string | null>(null)
const debugTraceEnabled = ref(false)
const debugTraceSummary = ref<ReturnType<typeof summarizeSsoDebugTrace> | null>(null)
const debugHint = computed(() =>
  debugTraceEnabled.value
    ? '`sessionStorage["ap-sso-debug-trace"]` を見ると callback trace を確認できます。'
    : null
)

onMounted(async () => {
  debugTraceEnabled.value
    = route.query.sso_debug === '1'
      || readSsoDebugTrace().length > 0

  try {
    if (debugTraceEnabled.value) {
      appendSsoDebugTrace('callback-page:mounted', {
        next: next.value
      })
    }

    const result = await completeBridgeSession()
    if (debugTraceEnabled.value) {
      appendSsoDebugTrace('callback-page:token-ready', {
        next: result.next
      })
    }
    setMode('live')
    setBearerToken(result.accessToken)
    if (debugTraceEnabled.value) {
      appendSsoDebugTrace('callback-page:refresh-current-user:start')
    }
    await refreshCurrentUser()
    if (debugTraceEnabled.value) {
      appendSsoDebugTrace('callback-page:refresh-current-user:done')
      appendSsoDebugTrace('callback-page:navigate:start', {
        next: result.next
      })
    }
    await navigateTo(result.next, { replace: true })
  } catch (error) {
    clearStoredState()
    if (debugTraceEnabled.value) {
      appendSsoDebugTrace('callback-page:error', {
        message: error instanceof Error ? error.message : String(error),
        traceLength: readSsoDebugTrace().length
      })
      debugTraceSummary.value = summarizeSsoDebugTrace()
    }
    errorMessage.value = error instanceof Error
      ? error.message
      : 'AP Frontend の auth callback で token を確定できませんでした。'
  }
})
</script>

<template>
  <div class="mx-auto flex min-h-[70vh] w-full max-w-3xl items-center justify-center px-4 py-12">
    <UCard class="w-full rounded-[2rem] border-primary/20 bg-white/90 shadow-xl shadow-cyan-950/5 dark:bg-stone-900/80">
      <template #header>
        <div class="space-y-2">
          <p class="text-sm font-semibold uppercase tracking-[0.22em] text-primary">
            AP Auth Callback
          </p>
          <h1 class="text-3xl font-semibold tracking-tight text-highlighted">
            AP Session を確定しています
          </h1>
        </div>
      </template>

      <div class="space-y-4">
        <p class="text-sm leading-7 text-toned">
          Keycloak callback を受け取り、AP Frontend が使う Bearer token を保存しています。
        </p>

        <div
          v-if="errorMessage"
          class="rounded-2xl border border-error/30 bg-error/10 p-4 text-sm text-error"
        >
          <p>{{ errorMessage }}</p>
          <p
            v-if="debugHint"
            class="mt-2 text-xs text-error/80"
          >
            {{ debugHint }}
          </p>
          <div
            v-if="debugTraceSummary?.latestStage"
            class="mt-3 rounded-xl border border-error/20 bg-white/40 p-3 text-xs text-error/90"
          >
            <p>Latest Stage: {{ debugTraceSummary.latestStage }}</p>
            <p>Trace Count: {{ debugTraceSummary.count }}</p>
            <p v-if="debugTraceSummary.latestAt">
              Latest At: {{ debugTraceSummary.latestAt }}
            </p>
          </div>
          <div class="mt-4 flex flex-wrap gap-2">
            <UButton
              :to="globalLoginUrl(next)"
              color="primary"
              variant="soft"
              trailing-icon="i-lucide-log-in"
            >
              SSO Login をやり直す
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
        </div>

        <div
          v-else
          class="flex items-center gap-3 rounded-2xl border border-default bg-stone-50/70 p-4 dark:bg-stone-950/40"
        >
          <UIcon
            name="i-lucide-loader-circle"
            class="size-5 animate-spin text-primary"
          />
          <p class="text-sm text-toned">
            token を確定したあと、元の画面へ戻ります。
          </p>
        </div>
      </div>
    </UCard>
  </div>
</template>
