<script setup lang="ts">
definePageMeta({
  layout: 'dashboard'
})
</script>

<template>
  <div class="space-y-6">
    <DashboardPageHeader
      badge="PLAYBOOKS"
      title="Playbooks"
      description="playbooks 一覧の暫定ページです。dashboard shell と menu 遷移の確認を先に揃えるために置いています。"
    />

    <DashboardPlaceholderCard
      title="Playbook List Placeholder"
      description="ここには今後 `GET /api/playbooks` の一覧、詳細遷移、作成導線を載せます。"
      note="objects / policies / checklists と同じ page layout を使い回せるかを見るための土台です。"
    />
  </div>
</template>
