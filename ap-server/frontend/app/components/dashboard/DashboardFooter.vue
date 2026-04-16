<script setup lang="ts">
const currentYear = new Date().getFullYear()
</script>

<template>
  <footer class="mt-auto border-t border-default/80 bg-white/85 backdrop-blur dark:bg-stone-950/85">
    <div class="flex flex-col gap-2 px-4 py-4 text-xs text-muted sm:flex-row sm:items-center sm:justify-between sm:px-6">
      <p>
        © {{ currentYear }} AP Server Prototype
      </p>
      <p>
        dashboard shell / temporary page layout
      </p>
    </div>
  </footer>
</template>
