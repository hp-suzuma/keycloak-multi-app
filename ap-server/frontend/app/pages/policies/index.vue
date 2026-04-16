<script setup lang="ts">
definePageMeta({
  layout: 'dashboard'
})
</script>

<template>
  <div class="space-y-6">
    <DashboardPageHeader
      badge="POLICIES"
      title="Policies"
      description="policies 一覧の暫定ページです。権限に応じて sidebar の表示が変わることを確認しやすいように先に導線だけ用意しています。"
    />

    <DashboardPlaceholderCard
      title="Policy List Placeholder"
      description="ここには今後 `GET /api/policies` の一覧と編集導線を載せます。"
      note="本実装時は scope 固定更新ルールをページ UI にどう見せるかもこの画面で検討します。"
    />
  </div>
</template>
