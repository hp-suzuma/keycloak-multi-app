<template>
  <div class="mx-auto flex min-h-[calc(100vh-73px)] max-w-6xl items-center px-6 py-16">
    <div class="grid w-full gap-8 lg:grid-cols-[minmax(0,1.3fr)_minmax(320px,420px)]">
      <section class="space-y-6">
        <UBadge
          color="primary"
          variant="soft"
          class="rounded-full px-4 py-1 text-xs font-semibold tracking-[0.24em]"
        >
          FRONTEND SCAFFOLD
        </UBadge>

        <div class="space-y-4">
          <h1 class="max-w-3xl text-4xl font-semibold tracking-tight text-highlighted sm:text-5xl">
            新しい AP サーバーのフロント開発を始めるための土台
          </h1>

          <p class="max-w-2xl text-base leading-8 text-toned sm:text-lg">
            `Nuxt 4 + Nuxt UI` をベースに、画面実装と API 連携をこのディレクトリで育てていくための最小雛形です。
          </p>
        </div>

        <div class="flex flex-wrap gap-3">
          <UButton
            to="https://ui.nuxt.com"
            target="_blank"
            trailing-icon="i-lucide-arrow-up-right"
            size="lg"
          >
            Nuxt UI Docs
          </UButton>
          <UButton
            to="https://nuxt.com/docs/4.x/getting-started"
            target="_blank"
            color="neutral"
            variant="subtle"
            trailing-icon="i-lucide-arrow-up-right"
            size="lg"
          >
            Nuxt 4 Docs
          </UButton>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
          <UCard>
            <template #header>
              <div class="flex items-center gap-2 text-sm font-semibold">
                <UIcon name="i-lucide-layout-panel-top" class="text-primary" />
                UI
              </div>
            </template>

            <p class="text-sm leading-6 text-toned">
              ページ、レイアウト、コンポーネントをここから積み上げます。
            </p>
          </UCard>

          <UCard>
            <template #header>
              <div class="flex items-center gap-2 text-sm font-semibold">
                <UIcon name="i-lucide-shield-user" class="text-primary" />
                CurrentUser
              </div>
            </template>

            <p class="text-sm leading-6 text-toned">
              後続工程で簡易ログインと SSO 受け口を composable に寄せます。
            </p>
          </UCard>

          <UCard>
            <template #header>
              <div class="flex items-center gap-2 text-sm font-semibold">
                <UIcon name="i-lucide-plug-zap" class="text-primary" />
                API
              </div>
            </template>

            <p class="text-sm leading-6 text-toned">
              Laravel 13 バックエンドとの通信ルールをここに合わせていきます。
            </p>
          </UCard>
        </div>
      </section>

      <aside class="lg:pt-10">
        <UCard class="overflow-hidden border-primary/20 shadow-lg shadow-cyan-950/5">
          <template #header>
            <div class="flex items-center justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-highlighted">
                  Next Steps
                </p>
                <p class="text-sm text-muted">
                  この雛形の次に進める作業
                </p>
              </div>

              <UIcon name="i-lucide-rocket" class="text-2xl text-primary" />
            </div>
          </template>

          <ol class="space-y-3 text-sm leading-6 text-toned">
            <li>1. レイアウトとルーティングの骨組みを追加する</li>
            <li>2. API 呼び出し用 composable を切り出す</li>
            <li>3. CurrentUser の取得口を 1 箇所に寄せる</li>
            <li>4. Laravel バックエンドとの接続設定を追加する</li>
          </ol>
        </UCard>
      </aside>
    </div>
  </div>
</template>
