<script setup lang="ts">
import { dashboardHomeTitle, dashboardRoleBadge } from '../../utils/dashboard'

const emit = defineEmits<{
  toggleSidebar: []
  toggleDesktopSidebar: []
}>()

const route = useRoute()
const {
  currentUser,
  authorization,
  status,
  mode,
  globalLoginUrl,
  refreshCurrentUser
} = useApAuth()

const roleBadge = computed(() => dashboardRoleBadge(authorization.value))
const headerTitle = computed(() => {
  if (route.path.startsWith('/users')) {
    return 'User Management'
  }

  return dashboardHomeTitle(authorization.value)
})

const menuItems = computed(() => [
  {
    label: 'Dashboard',
    icon: 'i-lucide-layout-dashboard',
    to: '/'
  },
  {
    label: 'Users',
    icon: 'i-lucide-users',
    to: '/users'
  },
  ...(mode.value === 'live'
    ? [{
        label: 'SSO Login',
        icon: 'i-lucide-log-in',
        to: globalLoginUrl.value
      }]
    : []),
  {
    type: 'separator' as const
  },
  {
    label: 'Refresh Session',
    icon: 'i-lucide-refresh-cw',
    async onSelect() {
      await refreshCurrentUser()
    }
  }
])
</script>

<template>
  <header class="sticky top-0 z-20 border-b border-default/80 bg-white/85 backdrop-blur dark:bg-stone-950/85">
    <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6">
      <div class="flex items-center gap-3">
        <UButton
          color="neutral"
          variant="ghost"
          icon="i-lucide-menu"
          class="lg:hidden"
          @click="emit('toggleSidebar')"
        />
        <UButton
          color="neutral"
          variant="ghost"
          icon="i-lucide-panel-left-close"
          class="hidden lg:inline-flex"
          @click="emit('toggleDesktopSidebar')"
        />

        <div class="flex items-center gap-3">
          <div class="flex size-10 items-center justify-center rounded-2xl bg-cyan-600 text-white shadow-lg shadow-cyan-950/10">
            <UIcon
              :name="roleBadge.icon"
              class="size-5"
            />
          </div>

          <div>
            <p class="text-sm font-semibold text-highlighted">
              {{ headerTitle }}
            </p>
            <p class="text-xs text-muted">
              {{ roleBadge.label }}
            </p>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2 sm:gap-3">
        <UBadge
          :color="mode === 'live' ? 'success' : 'warning'"
          variant="soft"
          class="hidden sm:inline-flex"
        >
          {{ mode === 'live' ? 'LIVE' : 'MOCK' }}
        </UBadge>
        <UBadge
          :color="status === 'ready' ? 'primary' : 'neutral'"
          variant="soft"
          class="hidden md:inline-flex"
        >
          {{ status }}
        </UBadge>

        <UDropdownMenu
          :items="menuItems"
          :content="{ align: 'end', sideOffset: 10 }"
        >
          <UButton
            color="neutral"
            variant="ghost"
            class="gap-2 px-2"
          >
            <UIcon
              name="i-lucide-user-round"
              class="size-6 text-muted"
            />
            <span class="hidden text-sm font-semibold text-highlighted sm:inline">
              {{ currentUser?.name || 'Guest' }}
            </span>
          </UButton>

          <template #content-top>
            <div class="flex items-center gap-3 border-b border-default/80 px-3 py-3">
              <div class="flex size-10 items-center justify-center rounded-2xl bg-stone-100 text-stone-600 dark:bg-stone-800 dark:text-stone-100">
                <UIcon
                  name="i-lucide-user-round"
                  class="size-5"
                />
              </div>
              <div>
                <p class="text-sm font-semibold text-highlighted">
                  {{ currentUser?.name || 'CurrentUser 未取得' }}
                </p>
                <p class="text-xs text-muted">
                  {{ currentUser?.email || 'mode を切り替えて取得してください' }}
                </p>
              </div>
            </div>
          </template>
        </UDropdownMenu>
      </div>
    </div>
  </header>
</template>
