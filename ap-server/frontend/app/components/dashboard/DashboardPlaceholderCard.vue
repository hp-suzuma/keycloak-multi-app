<script setup lang="ts">
defineProps<{
  title: string
  description: string
  note: string
}>()
</script>

<template>
  <UCard class="rounded-[1.75rem] border-white/70 bg-white/80 dark:border-white/10 dark:bg-stone-900/70">
    <template #header>
      <p class="text-sm font-semibold text-highlighted">
        {{ title }}
      </p>
    </template>

    <div class="space-y-3">
      <p class="text-sm leading-7 text-toned">
        {{ description }}
      </p>
      <div class="rounded-2xl border border-dashed border-default px-4 py-4 text-xs leading-6 text-muted">
        {{ note }}
      </div>
    </div>
  </UCard>
</template>
