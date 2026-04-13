<script setup>
useHead({
  meta: [
    { name: 'viewport', content: 'width=device-width, initial-scale=1' }
  ],
  htmlAttrs: {
    lang: 'ja'
  }
})

const title = 'AP Server Frontend'
const description = '新しい AP サーバーのフロントエンド開発ベースです。'

useSeoMeta({
  title,
  description,
  ogTitle: title,
  ogDescription: description,
  twitterCard: 'summary_large_image'
})
</script>

<template>
  <UApp>
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,_#ecfeff,_#fafaf9_55%,_#f5f5f4)]">
      <header class="border-b border-default/60 bg-default/80 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.32em] text-primary">
              AP Server
            </p>
            <p class="text-sm text-muted">
              Nuxt 4 + Nuxt UI starter
            </p>
          </div>

          <UColorModeButton />
        </div>
      </header>

      <main>
        <NuxtPage />
      </main>
    </div>
  </UApp>
</template>
