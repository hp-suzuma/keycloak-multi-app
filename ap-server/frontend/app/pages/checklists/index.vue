<script setup lang="ts">
definePageMeta({
  layout: 'dashboard'
})
</script>

<template>
  <div class="space-y-6">
    <DashboardPageHeader
      badge="CHECKLISTS"
      title="Checklists"
      description="checklists 一覧の暫定ページです。dashboard 共通 shell から resource ページへ広げる時のたたき台として追加しています。"
    />

    <DashboardPlaceholderCard
      title="Checklist List Placeholder"
      description="ここには今後 `GET /api/checklists` の一覧、検索、詳細遷移を載せます。"
      note="resource ごとの差分が page body に収まるかを判断するための placeholder です。"
    />
  </div>
</template>
