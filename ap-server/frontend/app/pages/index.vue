<script setup lang="ts">
import { dashboardHomeTitle, dashboardNavGroups, dashboardSidebarMeta } from '../utils/dashboard'

definePageMeta({
  layout: 'dashboard'
})

const {
  currentUser,
  authorization,
  effectivePermissions,
  refreshCurrentUser,
  status
} = useApAuth()

const navGroups = computed(() => dashboardNavGroups(currentUser.value, authorization.value))
const summary = computed(() => dashboardSidebarMeta(currentUser.value, authorization.value))
const pageTitle = computed(() => dashboardHomeTitle(authorization.value))

onMounted(async () => {
  if (status.value === 'idle') {
    await refreshCurrentUser()
  }
})
</script>

<template>
  <div class="space-y-6">
    <DashboardPageHeader
      badge="DASHBOARD HOME"
      :title="pageTitle"
      description="ログイン後ホームとして使う暫定ダッシュボードです。ヘッダー・サイドバー・フッターを共通化し、メニューは CurrentUser と authorization に応じて切り替わります。"
    />

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(320px,420px)]">
      <section class="space-y-6">
        <div class="grid gap-4 lg:grid-cols-3">
          <UCard class="rounded-[1.75rem] border-white/70 bg-white/80 dark:border-white/10 dark:bg-stone-900/70">
            <template #header>
              <p class="text-sm font-semibold text-highlighted">
                Signed In As
              </p>
            </template>
            <p class="text-lg font-semibold text-highlighted">
              {{ currentUser?.name || 'CurrentUser 未取得' }}
            </p>
            <p class="text-sm text-toned">
              {{ currentUser?.email || 'Auth Entry から mode / token を設定してください' }}
            </p>
          </UCard>

          <UCard class="rounded-[1.75rem] border-white/70 bg-white/80 dark:border-white/10 dark:bg-stone-900/70">
            <template #header>
              <p class="text-sm font-semibold text-highlighted">
                Primary Context
              </p>
            </template>
            <p class="text-lg font-semibold text-highlighted">
              {{ summary.line1 }}
            </p>
            <p class="text-sm text-toned">
              {{ summary.line2 }}
            </p>
          </UCard>

          <UCard class="rounded-[1.75rem] border-white/70 bg-white/80 dark:border-white/10 dark:bg-stone-900/70">
            <template #header>
              <p class="text-sm font-semibold text-highlighted">
                Effective Permissions
              </p>
            </template>
            <p class="text-3xl font-semibold text-highlighted">
              {{ effectivePermissions.length }}
            </p>
            <p class="text-sm text-toned">
              表示中メニューもこの権限セットに合わせて切り替えます。
            </p>
          </UCard>
        </div>

        <AppAuthPanel />

        <div class="grid gap-4 lg:grid-cols-2">
          <DashboardPlaceholderCard
            title="Dashboard Layout"
            description="参照元の `vue_example/frontend` と同じく、共通 shell は header / sidebar / footer に分割しました。モバイルでは drawer、デスクトップでは折りたたみ sidebar で動きます。"
            note="今後は breadcrumb、通知、ページ単位 toolbar をこの shell 上に追加できます。"
          />

          <DashboardPlaceholderCard
            title="Temporary Page Layout"
            description="objects / playbooks / policies / checklists / security には暫定ページを用意し、メニュー導線と page header の共通化を先に確認できるようにしています。"
            note="本実装時はこのカードを各 resource の table / form / detail に置き換えていく前提です。"
          />
        </div>
      </section>

      <aside class="space-y-6">
        <UCard class="overflow-hidden rounded-[1.75rem] border-primary/20 bg-white/80 shadow-lg shadow-cyan-950/5 dark:bg-stone-900/70">
          <template #header>
            <div class="flex items-center justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-highlighted">
                  Visible Navigation
                </p>
                <p class="text-sm text-muted">
                  現在のログインユーザーで見えるメニュー
                </p>
              </div>

              <UIcon
                name="i-lucide-panel-left"
                class="text-2xl text-primary"
              />
            </div>
          </template>

          <div class="space-y-5">
            <section
              v-for="group in navGroups"
              :key="group.title"
              class="space-y-2"
            >
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-muted">
                {{ group.title }}
              </p>

              <NuxtLink
                v-for="item in group.items"
                :key="item.to"
                :to="item.to"
                class="flex items-start gap-3 rounded-2xl border border-default px-4 py-3 transition hover:border-primary/40 hover:bg-cyan-50/60 dark:hover:bg-cyan-950/20"
              >
                <UIcon
                  :name="item.icon"
                  class="mt-0.5 size-5 text-primary"
                />
                <div>
                  <p class="text-sm font-medium text-highlighted">
                    {{ item.label }}
                  </p>
                  <p class="text-xs text-muted">
                    {{ item.description }}
                  </p>
                </div>
              </NuxtLink>
            </section>
          </div>
        </UCard>
      </aside>
    </div>
  </div>
</template>
