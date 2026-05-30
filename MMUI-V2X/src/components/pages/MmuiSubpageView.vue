<template>
  <section class="mmui-subpage">
    <template v-if="pageKey === 'system-control' || pageKey === 'power' || pageKey === 'reinstall' || pageKey === 'iso'">
      <SystemControlPage
        :page-key="pageKey"
        :pages="pages"
        :boot-modal-request="bootModalRequest"
        @boot-modal-request-consumed="emit('boot-modal-request-consumed')"
      />
    </template>

    <template v-else-if="pageKey === 'network-detail'">
      <NetworkInfoPage :page="pages.network || {}" />
    </template>

    <template v-else-if="pageKey === 'monitor'">
      <MonitorPage />
    </template>

    <template v-else-if="pageKey === 'vnc'">
      <RemoteAccessPage :page="pages.vnc || {}" />
    </template>

    <template v-else-if="pageKey === 'port'">
      <PortMappingPage :page="pages.port || {}" />
    </template>

    <template v-else-if="pageKey === 'snapshot' || pageKey === 'backup'">
      <SnapshotBackupPage :page-key="pageKey" :page="currentTablePage" />
    </template>

    <template v-else-if="pageKey === 'strategy'">
      <StrategyPage :page="pages.strategy || {}" />
    </template>

    <template v-else-if="pageKey === 'site'">
      <SiteWhitelistPage :page="pages.site || {}" />
    </template>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import MonitorPage from '@/components/pages/MonitorPage.vue';
import NetworkInfoPage from '@/components/pages/NetworkInfoPage.vue';
import PortMappingPage from '@/components/pages/PortMappingPage.vue';
import RemoteAccessPage from '@/components/pages/RemoteAccessPage.vue';
import SiteWhitelistPage from '@/components/pages/SiteWhitelistPage.vue';
import SnapshotBackupPage from '@/components/pages/SnapshotBackupPage.vue';
import StrategyPage from '@/components/pages/StrategyPage.vue';
import SystemControlPage from '@/components/pages/SystemControlPage.vue';

const props = defineProps({
  pageKey: {
    type: String,
    required: true,
  },
  pages: {
    type: Object,
    default: () => ({}),
  },
  bootModalRequest: {
    type: Number,
    default: 0,
  },
});

const emit = defineEmits(['boot-modal-request-consumed']);

const currentTablePage = computed(() => {
  if (props.pageKey === 'snapshot') {
    return props.pages.snapshot || {};
  }

  return props.pages.backup || {};
});
</script>

<style scoped>
.mmui-subpage {
  width: 100%;
}

.mmui-subpage-card {
  margin: 8px;
  border-radius: 6px !important;
  overflow: hidden;
}

.mmui-subpage-card :deep(.ant-card-body) {
  padding: 0 !important;
}

.mmui-subpage-card--header :deep(.ant-card-body),
.mmui-subpage-card--filters :deep(.ant-card-body) {
  padding: 10px !important;
}

.mmui-subpage-card--action :deep(.ant-card-body),
.mmui-subpage-card--site :deep(.ant-card-body) {
  padding: 8px !important;
}

.mmui-subpage-header {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 48px;
}

.mmui-subpage-header__title {
  padding-left: 8px;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-hero-title);
  line-height: var(--mmui-line-height-page);
}

.mmui-subpage-header__chip {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-subtitle);
}

.mmui-subpage-header__spacer,
.mmui-filter-bar__spacer {
  flex: 1 1 auto;
}

.mmui-subpage-card__inner {
  min-height: 100%;
}

.mmui-subpage-card__title {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-hero-title);
  line-height: var(--mmui-line-height-page);
  user-select: none;
}

.mmui-subpage-card__title-icon {
  margin-left: 10px;
  color: var(--mmui-accent-blue-soft);
  font-size: 32px;
}

.mmui-subpage-card__subtitle {
  color: var(--mmui-card-subtitle);
  font-size: var(--mmui-font-subtitle);
  line-height: var(--mmui-line-height-footnote);
}

.mmui-subpage-card__content {
  margin-top: 8px;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-body);
  line-height: var(--mmui-line-height-body);
}

.mmui-subpage-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 15px;
}

.mmui-subpage-card__actions.is-stack {
  display: block;
}

.mmui-subpage-form {
  display: flex;
  flex-direction: column;
  gap: 15px;
  margin-top: 15px;
}

.mmui-page-field {
  width: 100%;
  min-height: 38px;
  padding: 0 14px;
  color: var(--mmui-text);
  font-size: var(--mmui-font-body);
  background: var(--mmui-card-action-bg);
  border: 1px solid var(--mmui-card-action-border);
  border-radius: 6px;
  outline: 0;
}

.mmui-page-btn {
  min-width: 132px;
  min-height: 36px;
  padding: 0 18px;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-body);
  background: var(--mmui-card-action-bg);
  border: 1px solid var(--mmui-card-action-border);
  border-radius: 8px;
}

.mmui-page-btn--primary {
  color: #fff;
  background: var(--mmui-accent-blue);
  border-color: var(--mmui-accent-blue);
}

.mmui-page-btn--block {
  width: 100%;
}

.mmui-subpage-table-wrap {
  overflow-x: auto;
}

.mmui-subpage-table {
  width: 100%;
  border-collapse: collapse;
  color: var(--mmui-text);
  font-size: var(--mmui-font-body);
}

.mmui-subpage-table thead th {
  padding: 14px 16px;
  text-align: left;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-title);
  font-weight: 400;
  border-bottom: 1px solid var(--mmui-divider);
}

.mmui-subpage-table tbody td {
  padding: 13px 16px;
  border-bottom: 1px solid var(--mmui-divider);
}

.mmui-subpage-table tbody tr:last-child td {
  border-bottom: 0;
}

.mmui-filter-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
}

.mmui-filter-bar .mmui-page-field {
  width: 120px;
}

.mmui-subpage-site__media img {
  display: block;
  width: 100%;
  height: 190px;
  object-fit: cover;
}

@media (max-width: 1023px) {
  .mmui-subpage-header {
    flex-wrap: wrap;
  }

  .mmui-subpage-header__title {
    padding-left: 0;
  }

  .mmui-subpage-table thead th,
  .mmui-subpage-table tbody td {
    padding-left: 12px;
    padding-right: 12px;
  }
}
</style>
