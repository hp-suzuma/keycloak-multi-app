<script setup lang="ts">
definePageMeta({
  layout: 'dashboard'
})
</script>

<template>
  <div class="space-y-6">
    <DashboardPageHeader
      badge="OBJECTS"
      title="Objects"
      description="業務 object 一覧の暫定ページです。object.read を持つユーザー向けメニューから遷移するホーム枠として先に配置しています。"
    />

    <DashboardPlaceholderCard
      title="Objects List Placeholder"
      description="ここには今後 `GET /api/objects` に繋ぐ table、scope filter、検索 UI を載せます。"
      note="dashboard shell 上で page header / toolbar / table をどう並べるかをこのページで固めていく想定です。"
    />
  </div>
</template>
