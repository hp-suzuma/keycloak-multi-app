<script setup lang="ts">
import { dashboardNavGroups, dashboardSidebarMeta } from '../../utils/dashboard'

const props = defineProps<{
  collapsed?: boolean
}>()

const route = useRoute()
const { currentUser, authorization, effectivePermissions } = useApAuth()

const navGroups = computed(() => dashboardNavGroups(currentUser.value, authorization.value))
const meta = computed(() => dashboardSidebarMeta(currentUser.value, authorization.value))
const assignmentCount = computed(() => authorization.value?.assignments.length ?? 0)
</script>

<template>
  <aside class="flex h-full min-h-0 flex-col">
    <div class="shrink-0 border-b border-default/80 px-4 py-5">
      <div class="flex items-center gap-3" :class="props.collapsed ? 'justify-center' : ''">
        <div class="flex size-11 items-center justify-center rounded-2xl bg-stone-900 text-white">
          <UIcon name="i-lucide-orbit" class="size-6" />
        </div>
        <div v-if="!props.collapsed" class="min-w-0">
          <p class="truncate text-sm font-semibold text-highlighted">
            {{ meta.line1 }}
          </p>
          <p class="truncate text-xs text-muted">
            {{ meta.line2 }}
          </p>
        </div>
      </div>
    </div>

    <div class="shrink-0 border-b border-default/80 px-4 py-4" v-if="!props.collapsed">
      <div class="flex flex-wrap gap-2">
        <UBadge color="neutral" variant="soft">
          {{ assignmentCount }} assignments
        </UBadge>
        <UBadge color="primary" variant="soft">
          {{ effectivePermissions.length }} permissions
        </UBadge>
      </div>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto px-3 py-4">
      <div class="space-y-6">
        <section v-for="group in navGroups" :key="group.title" class="space-y-2">
          <p v-if="!props.collapsed" class="px-3 text-xs font-semibold uppercase tracking-[0.2em] text-muted">
            {{ group.title }}
          </p>

          <div class="space-y-1">
            <NuxtLink
              v-for="item in group.items"
              :key="item.to"
              :to="item.to"
              class="flex items-center gap-3 rounded-2xl px-3 py-3 transition"
              :class="route.path === item.to
                ? 'bg-cyan-50 text-cyan-900 shadow-sm dark:bg-cyan-950/30 dark:text-cyan-100'
                : 'text-toned hover:bg-stone-100/80 dark:hover:bg-stone-800/60'"
            >
              <UIcon :name="item.icon" class="size-5 shrink-0" />
              <div v-if="!props.collapsed" class="min-w-0">
                <p class="truncate text-sm font-medium">
                  {{ item.label }}
                </p>
                <p class="truncate text-xs text-muted">
                  {{ item.description }}
                </p>
              </div>
            </NuxtLink>
          </div>
        </section>
      </div>
    </div>
  </aside>
</template>
