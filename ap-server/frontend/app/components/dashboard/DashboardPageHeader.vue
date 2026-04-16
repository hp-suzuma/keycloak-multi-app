<script setup lang="ts">
defineProps<{
  badge?: string
  title: string
  description: string
}>()
</script>

<template>
  <div class="space-y-4">
    <UBadge v-if="badge" color="primary" variant="soft" class="rounded-full px-4 py-1 text-xs font-semibold tracking-[0.24em]">
      {{ badge }}
    </UBadge>

    <div class="space-y-2">
      <h1 class="text-3xl font-semibold tracking-tight text-highlighted sm:text-4xl">
        {{ title }}
      </h1>
      <p class="max-w-3xl text-sm leading-7 text-toned sm:text-base">
        {{ description }}
      </p>
    </div>
  </div>
</template>
