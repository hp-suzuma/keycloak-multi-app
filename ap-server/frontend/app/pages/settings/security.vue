<script setup lang="ts">
definePageMeta({
  layout: 'dashboard'
})
</script>

<template>
  <div class="space-y-6">
    <DashboardPageHeader
      badge="SECURITY"
      title="Security Settings"
      description="認証・権限まわりの設定確認用の暫定ページです。dashboard shell の settings 配下導線を先に整えています。"
    />

    <DashboardPlaceholderCard
      title="Security Placeholder"
      description="ここには今後 CurrentUser、Bearer token、assignment、監査ログなどの設定 UI を置く想定です。"
      note="現段階では auth entry と menu の接点を確認するためのページに留めています。"
    />
  </div>
</template>
