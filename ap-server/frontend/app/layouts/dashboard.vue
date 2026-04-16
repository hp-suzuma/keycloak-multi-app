<script setup lang="ts">
const sidebarExpanded = ref(true)
const mobileOpen = ref(false)

function toggleSidebar() {
  mobileOpen.value = !mobileOpen.value
}

function toggleDesktopSidebar() {
  sidebarExpanded.value = !sidebarExpanded.value
}
</script>

<template>
  <div class="min-h-screen bg-[linear-gradient(180deg,_rgba(236,254,255,0.7),_rgba(245,245,244,0.9)_24%,_rgba(250,250,249,1))]">
    <DashboardHeader
      @toggle-sidebar="toggleSidebar"
      @toggle-desktop-sidebar="toggleDesktopSidebar"
    />

    <div class="flex min-h-[calc(100vh-4rem)]">
      <div class="sticky top-16 hidden h-[calc(100vh-4rem)] border-r border-default/80 lg:block">
        <div
          class="h-full overflow-hidden border-r border-white/40 bg-white/80 backdrop-blur transition-[width] duration-200 dark:border-white/10 dark:bg-stone-900/80"
          :class="sidebarExpanded ? 'w-72' : 'w-24'"
        >
          <DashboardSidebar :collapsed="!sidebarExpanded" />
        </div>
      </div>

      <UDrawer
        v-model:open="mobileOpen"
        direction="left"
        :overlay="true"
        :handle="false"
        class="lg:hidden"
      >
        <template #content>
          <div class="h-full max-w-[18rem]">
            <DashboardSidebar />
          </div>
        </template>
      </UDrawer>

      <div class="flex min-h-[calc(100vh-4rem)] min-w-0 flex-1 flex-col">
        <main class="flex-1 px-4 py-6 sm:px-6">
          <div class="mx-auto flex w-full max-w-[1600px] flex-col">
            <slot />
          </div>
        </main>

        <DashboardFooter />
      </div>
    </div>
  </div>
</template>
