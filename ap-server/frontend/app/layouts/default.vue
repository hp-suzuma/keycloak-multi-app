<template>
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

    <main class="mx-auto flex max-w-6xl flex-col gap-6 px-6 py-6">
      <slot />
    </main>
  </div>
</template>
