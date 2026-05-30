<template>
  <section ref="pageRootRef" class="resource-page">
    <ResourcePageHero
      :title="title"
      :subtitle="heroSubtitle"
      :summary-label="summaryLabel"
      :primary-value="usedCount"
      :primary-total="totalCount"
      :progress-percent="usagePercent"
      :stat-cards="heroStatCards"
      :compact-summary-items="heroCompactSummaryItems"
      :help-text="heroHelpText"
      hide-subtitle-on-compact
      :hero-image="heroVisualImage"
    />

    <a-card
      class="resource-page__table-panel"
      :class="{ 'is-compact': compactMode }"
    >
      <div class="resource-page__toolbar">
        <a-space class="resource-page__toolbar-actions" :size="10">
          <a-button
            class="resource-page__create-btn"
            type="primary"
            :loading="isActionLoading(`${actionNamespace}:create`)"
            :disabled="usedCount >= totalCount || isActionLoading(`${actionNamespace}:create`)"
            @click="createItem"
          >
            <PlusOutlined />
            {{ actionText }}
          </a-button>
          <a-popconfirm
            :disabled="!selectedDeletableIds.length"
            :title="batchDeleteConfirmText"
            ok-text="确定"
            cancel-text="取消"
            @confirm="deleteSelected"
          >
            <a-button
              class="resource-page__delete-btn"
              danger
              :loading="isActionLoading(`${actionNamespace}:delete:selected`)"
              :disabled="!selectedDeletableIds.length || isActionLoading(`${actionNamespace}:delete:selected`)"
            >
              <DeleteOutlined />
              删除
            </a-button>
          </a-popconfirm>
        </a-space>

        <div class="resource-page__toolbar-filters">
          <a-input-search
            v-model:value="searchText"
            class="resource-page__search"
            :placeholder="`搜索${title}标识`"
            allow-clear
          />
          <a-select
            v-model:value="statusFilter"
            class="resource-page__status-select"
            :options="statusFilterOptions"
          />
          <a-button class="resource-page__refresh-btn" aria-label="刷新" @click="reload">
            <ReloadOutlined />
          </a-button>
        </div>
      </div>

      <transition name="resource-alert" appear>
        <a-alert
          v-if="isBatchDeleting"
          class="resource-page__batch-warning"
          type="warning"
          show-icon
          message="正在按队列删除，请不要刷新页面"
          description="轻舟一次只能处理一个快照或备份任务，刷新会中断当前批量队列。"
        />
      </transition>

      <template v-if="compactMode">
        <transition-group
          v-if="pagedRows.length"
          name="resource-list"
          tag="div"
          class="resource-page__mobile-list"
          appear
        >
          <article
            v-for="record in pagedRows"
            :key="record.id"
            class="resource-page__mobile-card"
            :class="{ 'is-fresh': freshRowId === record.id, 'is-leaving': record.__mmuiLeaving }"
          >
            <a-flex class="resource-page__mobile-head" align="center" justify="space-between" :gap="12">
              <a-flex class="resource-page__mobile-head-main" align="center" :gap="10">
                <a-checkbox
                  :checked="selectedRowKeys.includes(record.id)"
                  :disabled="!isResourceReady(record)"
                  @change="toggleRowSelection(record.id, $event.target.checked)"
                />
                <div class="resource-page__mobile-name">{{ record.name }}</div>
              </a-flex>
              <a-tag class="resource-page__status" :color="statusMeta(record.state).tagColor">
                <component :is="statusMeta(record.state).icon" />
                <span>{{ statusMeta(record.state).label }}</span>
              </a-tag>
            </a-flex>

            <div class="resource-page__mobile-meta">
              <div class="resource-page__mobile-meta-item">
                <span class="resource-page__mobile-label">{{ timeColumnTitle }}</span>
                <span class="resource-page__mobile-value">{{ record.create_time }}</span>
              </div>
            </div>

            <a-space class="resource-page__mobile-actions" :size="16">
              <a-popconfirm :title="restoreConfirmText" @confirm="restoreItem(record)">
                <a-button
                  class="resource-page__mobile-action-btn"
                  type="link"
                  size="small"
                  :loading="isActionLoading(`${actionNamespace}:restore:${record.id}`)"
                  :disabled="!isResourceReady(record) || isActionLoading(`${actionNamespace}:restore:${record.id}`)"
                >
                  恢复
                </a-button>
              </a-popconfirm>
              <a-popconfirm :title="deleteConfirmText" @confirm="deleteItem(record)">
                <a-button
                  class="resource-page__mobile-action-btn"
                  type="link"
                  danger
                  size="small"
                  :loading="isActionLoading(`${actionNamespace}:delete:${record.id}`)"
                  :disabled="!isResourceReady(record) || isActionLoading(`${actionNamespace}:delete:${record.id}`)"
                >
                  删除
                </a-button>
              </a-popconfirm>
            </a-space>
          </article>
        </transition-group>
        <a-empty v-else class="resource-page__empty" :description="emptyText" />
      </template>

      <template v-else>
        <a-table
          :columns="columns"
          :data-source="filteredRows"
          :pagination="tablePagination"
          :rowClassName="rowClassName"
          :row-selection="rowSelection"
          :scroll="{ x: 840 }"
          rowKey="id"
          size="middle"
          tableLayout="fixed"
          class="resource-page__table"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'name'">
              <div class="resource-page__name-cell">
                <span>{{ record.name }}</span>
              </div>
            </template>

            <template v-else-if="column.key === 'state'">
              <a-tag class="resource-page__status" :color="statusMeta(record.state).tagColor">
                <component :is="statusMeta(record.state).icon" />
                <span>{{ statusMeta(record.state).label }}</span>
              </a-tag>
            </template>

            <template v-else-if="column.key === 'actions'">
              <a-space class="resource-page__inline-actions" :size="12">
                <a-popconfirm :title="restoreConfirmText" @confirm="restoreItem(record)">
                  <a-button
                    type="link"
                    size="small"
                    :loading="isActionLoading(`${actionNamespace}:restore:${record.id}`)"
                    :disabled="!isResourceReady(record) || isActionLoading(`${actionNamespace}:restore:${record.id}`)"
                  >
                    恢复
                  </a-button>
                </a-popconfirm>
                <a-popconfirm :title="deleteConfirmText" @confirm="deleteItem(record)">
                  <a-button
                    type="link"
                    danger
                    size="small"
                    :loading="isActionLoading(`${actionNamespace}:delete:${record.id}`)"
                    :disabled="!isResourceReady(record) || isActionLoading(`${actionNamespace}:delete:${record.id}`)"
                  >
                    删除
                  </a-button>
                </a-popconfirm>
              </a-space>
            </template>
          </template>
        </a-table>
      </template>

      <a-pagination
        v-if="compactMode && filteredRows.length"
        class="resource-page__mobile-pagination"
        :current="currentPage"
        :page-size="pageSize"
        :page-size-options="pageSizeOptions"
        :total="filteredRows.length"
        show-size-changer
        :show-total="(total) => `共 ${total} 条`"
        @change="handlePageChange"
        @showSizeChange="handlePageChange"
      />
    </a-card>
  </section>
</template>

<script setup>
import {
  CheckCircleFilled,
  CloseCircleFilled,
  DeleteOutlined,
  LoadingOutlined,
  PlusOutlined,
  ReloadOutlined,
} from '@ant-design/icons-vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { message } from 'ant-design-vue';
import { useDashboardStore } from '@/stores/dashboard';
import { pinia } from '@/stores/pinia';
import { useCompactPageMode } from '@/components/pages/useCompactPageMode';
import ResourcePageHero from '@/components/pages/ResourcePageHero.vue';
import { useActionLocks } from '@/composables/useActionLocks';
import backupHeroImage from '@/assets/page-hero/backup-hero.png';
import snapshotHeroLight from '@/assets/snapshot-hero-light.png';

const props = defineProps({
  pageKey: {
    type: String,
    required: true,
  },
  page: {
    type: Object,
    default: () => ({}),
  },
});

const store = useDashboardStore(pinia);
const { pageRootRef, compactMode } = useCompactPageMode();
const { isActionLoading, runWithActionLoading } = useActionLocks();
const freshRowId = ref(null);
const displayRows = ref([]);
const selectedRowKeys = ref([]);
const searchText = ref('');
const statusFilter = ref('all');
const currentPage = ref(1);
const pageSize = ref(10);

let settleTimer = null;
let freshRowTimer = null;
const leavingRowTimers = new Map();

const stateMetaMap = {
  1: { label: '创建中', tone: 'processing', tagColor: 'processing', icon: LoadingOutlined },
  2: { label: '创建成功', tone: 'success', tagColor: 'success', icon: CheckCircleFilled },
  3: { label: '创建失败', tone: 'danger', tagColor: 'error', icon: CloseCircleFilled },
  4: { label: '恢复中', tone: 'processing', tagColor: 'processing', icon: LoadingOutlined },
  5: { label: '删除中', tone: 'processing', tagColor: 'processing', icon: LoadingOutlined },
};

const title = computed(() => props.page?.title || (props.pageKey === 'snapshot' ? '快照' : '备份'));
const actionNamespace = computed(() => `resource:${props.pageKey}`);
const summaryLabel = computed(() => (props.pageKey === 'snapshot' ? '创建快照数' : '创建备份数'));
const heroSubtitle = computed(() => (
  props.pageKey === 'snapshot'
    ? '创建和管理云服务器快照，轻松备份与恢复您的数据'
    : '创建和管理云服务器备份，保留稳定可恢复的数据副本'
));
const heroHelpText = computed(() => (
  props.pageKey === 'snapshot'
    ? '快照用于保存当前实例磁盘状态，可在需要时恢复数据。'
    : '备份用于保留可恢复的数据副本，可在异常时回滚。'
));
const heroVisualImage = computed(() => {
  if (props.pageKey === 'snapshot') {
    return snapshotHeroLight;
  }

  return backupHeroImage;
});
const heroStatCards = computed(() => ([
  { label: '已用', value: usedCount.value },
  { label: '可用', value: availableCount.value },
]));
const heroCompactSummaryItems = computed(() => ([
  { label: summaryLabel.value, value: `${usedCount.value} / ${totalCount.value || 0}` },
]));
const actionText = computed(() => props.page?.actionText || (props.pageKey === 'snapshot' ? '添加快照' : '添加备份'));
const timeColumnTitle = computed(() => (props.pageKey === 'snapshot' ? '快照时间' : '备份时间'));
const restoreConfirmText = computed(() => `确定恢复该${title.value}吗？`);
const deleteConfirmText = computed(() => `确定删除该${title.value}吗？`);
const batchDeleteConfirmText = computed(() => `确定删除选中的 ${selectedDeletableIds.value.length} 个${title.value}吗？`);
const emptyText = computed(() => `暂无${title.value}数据`);
const batchDeleteLockKey = computed(() => `${actionNamespace.value}:delete:selected`);
const isBatchDeleting = computed(() => isActionLoading(batchDeleteLockKey.value));
const rows = computed(() => Array.isArray(props.page?.rows) ? props.page.rows : []);
const totalCount = computed(() => {
  if (props.pageKey === 'snapshot') {
    return Number(props.page?.total || store.quotas?.snapshot?.total || rows.value.length);
  }

  return Number(props.page?.total || store.quotas?.backup?.total || rows.value.length);
});
const usedCount = computed(() => rows.value.length);
const availableCount = computed(() => Math.max(0, totalCount.value - usedCount.value));
const usagePercent = computed(() => {
  if (!totalCount.value) {
    return 0;
  }

  return Math.min(100, Math.round((usedCount.value / totalCount.value) * 100));
});
const statusFilterOptions = [
  { label: '状态：全部', value: 'all' },
  { label: '创建成功', value: 'success' },
  { label: '进行中', value: 'processing' },
  { label: '失败', value: 'danger' },
];
const pageSizeOptions = ['10', '20', '50'];
const columns = computed(() => ([
  {
    title: props.pageKey === 'snapshot' ? '快照标识' : '备份标识',
    dataIndex: 'name',
    key: 'name',
    width: 280,
    sorter: (a, b) => String(a.name || '').localeCompare(String(b.name || '')),
  },
  {
    title: timeColumnTitle.value,
    dataIndex: 'create_time',
    key: 'create_time',
    width: 220,
    sorter: (a, b) => String(a.create_time || '').localeCompare(String(b.create_time || '')),
  },
  {
    title: '状态',
    dataIndex: 'state',
    key: 'state',
    width: 170,
  },
  {
    title: '操作',
    key: 'actions',
    width: 170,
  },
]));
const filteredRows = computed(() => {
  const keyword = String(searchText.value || '').trim().toLowerCase();

  return displayRows.value.filter((row) => {
    const matchesKeyword = !keyword || String(row.name || '').toLowerCase().includes(keyword);
    const matchesStatus = statusFilter.value === 'all' || statusMeta(row.state).tone === statusFilter.value;
    return matchesKeyword && matchesStatus;
  });
});
const pagedRows = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value;
  return filteredRows.value.slice(start, start + pageSize.value);
});
const selectedDeletableIds = computed(() => {
  const currentRows = new Map(rows.value.map((row) => [row.id, row]));
  return selectedRowKeys.value.filter((id) => {
    const row = currentRows.get(id);
    return row && isResourceReady(row);
  });
});
const tablePagination = computed(() => ({
  current: currentPage.value,
  pageSize: pageSize.value,
  total: filteredRows.value.length,
  showSizeChanger: true,
  pageSizeOptions,
  showTotal: (total) => `共 ${total} 条`,
  onChange: handlePageChange,
  onShowSizeChange: handlePageChange,
}));
const rowSelection = computed(() => ({
  selectedRowKeys: selectedRowKeys.value,
  columnWidth: 58,
  getCheckboxProps: (record) => ({
    disabled: !isResourceReady(record),
  }),
  onChange: (keys) => {
    selectedRowKeys.value = keys;
  },
}));

watch(
  rows,
  (nextRows) => {
    syncDisplayRows(nextRows);
  },
  { immediate: true },
);

watch(
  () => rows.value.map((row) => row.id),
  () => {
    pruneSelectedRows();
  },
);

watch([searchText, statusFilter], () => {
  currentPage.value = 1;
});

watch([filteredRows, pageSize], () => {
  clampCurrentPage();
});

function resolveStateCode(state) {
  const numericState = Number(state);
  if (Number.isFinite(numericState) && stateMetaMap[numericState]) {
    return numericState;
  }

  switch (String(state || '').trim()) {
    case '创建中':
      return 1;
    case '创建成功':
      return 2;
    case '创建失败':
      return 3;
    case '恢复中':
      return 4;
    case '删除中':
      return 5;
    default:
      return 2;
  }
}

function statusMeta(state) {
  return stateMetaMap[resolveStateCode(state)] || stateMetaMap[2];
}

function isResourceReady(record) {
  return !record?.__mmuiLeaving && resolveStateCode(record?.state) === 2;
}

function rowClassName(record) {
  return [
    record.id === freshRowId.value ? 'resource-row--fresh' : '',
    record.__mmuiLeaving ? 'resource-row--leaving' : '',
  ].filter(Boolean).join(' ');
}

function clearSettleTimer() {
  if (settleTimer) {
    window.clearTimeout(settleTimer);
    settleTimer = null;
  }
}

function clearFreshRowTimer() {
  if (freshRowTimer) {
    window.clearTimeout(freshRowTimer);
    freshRowTimer = null;
  }
}

function clearLeavingRowTimers() {
  leavingRowTimers.forEach((timer) => {
    window.clearTimeout(timer);
  });
  leavingRowTimers.clear();
}

function scheduleLeavingRowRemoval(id) {
  const key = String(id);
  if (leavingRowTimers.has(key)) {
    return;
  }

  const timer = window.setTimeout(() => {
    displayRows.value = displayRows.value.filter((row) => (
      String(row.id) !== key || !row.__mmuiLeaving
    ));
    leavingRowTimers.delete(key);
  }, 420);

  leavingRowTimers.set(key, timer);
}

function syncDisplayRows(nextRows) {
  const sourceRows = Array.isArray(nextRows) ? nextRows : [];
  const nextById = new Map(sourceRows.map((row) => [String(row.id), row]));

  if (!displayRows.value.length) {
    displayRows.value = sourceRows.map((row) => ({ ...row }));
    return;
  }

  const consumed = new Set();
  const nextDisplayRows = [];

  displayRows.value.forEach((currentRow) => {
    const key = String(currentRow.id);
    const nextRow = nextById.get(key);

    if (nextRow) {
      nextDisplayRows.push({ ...nextRow });
      consumed.add(key);
      return;
    }

    const leavingRow = { ...currentRow, __mmuiLeaving: true };
    nextDisplayRows.push(leavingRow);
    scheduleLeavingRowRemoval(key);
  });

  sourceRows.forEach((row) => {
    const key = String(row.id);
    if (!consumed.has(key)) {
      nextDisplayRows.push({ ...row });
    }
  });

  displayRows.value = nextDisplayRows;
}

function scheduleSettleRefresh() {
  clearSettleTimer();
  settleTimer = window.setTimeout(() => {
    settleTimer = null;
    reload();
  }, 2900);
}

function markFreshRow(id) {
  if (!id) {
    return;
  }

  freshRowId.value = id;
  clearFreshRowTimer();
  freshRowTimer = window.setTimeout(() => {
    freshRowId.value = null;
    freshRowTimer = null;
  }, 900);
}

function resolveRefreshAction() {
  return props.pageKey === 'snapshot' ? store.refreshSnapshots : store.refreshBackups;
}

function resolveCreateAction() {
  return props.pageKey === 'snapshot' ? store.createSnapshot : store.createBackup;
}

function resolveRestoreAction() {
  return props.pageKey === 'snapshot' ? store.restoreSnapshot : store.restoreBackup;
}

function resolveDeleteAction() {
  return props.pageKey === 'snapshot' ? store.deleteSnapshot : store.deleteBackup;
}

function resolveBatchDeleteAction() {
  return props.pageKey === 'snapshot' ? store.deleteSnapshots : store.deleteBackups;
}

async function reload() {
  await runWithActionLoading(`${actionNamespace.value}:reload`, async () => {
    try {
      await resolveRefreshAction().call(store);
      pruneSelectedRows();
    } catch (error) {
      message.error(error instanceof Error ? error.message : '刷新失败');
    }
  });
}

async function createItem() {
  if (usedCount.value >= totalCount.value) {
    message.warning(`${title.value}数量已达上限`);
    return;
  }

  await runWithActionLoading(`${actionNamespace.value}:create`, async () => {
    try {
      const rowsAfterCreate = await resolveCreateAction().call(store);
      markFreshRow(rowsAfterCreate?.[0]?.id);
      scheduleSettleRefresh();
      message.success(`${title.value}创建指令已提交`);
    } catch (error) {
      message.error(error instanceof Error ? error.message : '创建失败');
    }
  });
}

async function restoreItem(record) {
  await runWithActionLoading(`${actionNamespace.value}:restore:${record.id}`, async () => {
    try {
      await resolveRestoreAction().call(store, record.id);
      scheduleSettleRefresh();
      message.success(`${title.value}恢复指令已提交`);
    } catch (error) {
      message.error(error instanceof Error ? error.message : '恢复失败');
    }
  });
}

async function deleteItem(record) {
  await runWithActionLoading(`${actionNamespace.value}:delete:${record.id}`, async () => {
    try {
      await resolveDeleteAction().call(store, record.id);
      selectedRowKeys.value = selectedRowKeys.value.filter((id) => id !== record.id);
      scheduleSettleRefresh();
      message.success(`成功删除该${title.value}`);
    } catch (error) {
      message.error(error instanceof Error ? error.message : '删除失败');
    }
  });
}

async function deleteSelected() {
  const ids = [...selectedDeletableIds.value];
  if (!ids.length) {
    return;
  }

  const noticeKey = `${batchDeleteLockKey.value}:notice`;

  await runWithActionLoading(batchDeleteLockKey.value, async () => {
    message.loading({
      key: noticeKey,
      content: `正在按队列删除 ${ids.length} 个${title.value}，请不要刷新页面`,
      duration: 0,
    });

    try {
      await resolveBatchDeleteAction().call(store, ids);
      selectedRowKeys.value = [];
      scheduleSettleRefresh();
      message.success({
        key: noticeKey,
        content: `成功删除 ${ids.length} 个${title.value}`,
        duration: 3,
      });
    } catch (error) {
      message.error({
        key: noticeKey,
        content: error instanceof Error ? error.message : '批量删除失败',
        duration: 4,
      });
    }
  });
}

function handleBeforeUnload(event) {
  if (!isBatchDeleting.value) {
    return;
  }

  event.preventDefault();
  event.returnValue = '正在按队列删除，请不要刷新页面';
}

function toggleRowSelection(id, checked) {
  if (checked) {
    selectedRowKeys.value = [...new Set([...selectedRowKeys.value, id])];
    return;
  }

  selectedRowKeys.value = selectedRowKeys.value.filter((selectedId) => selectedId !== id);
}

function handlePageChange(page, size) {
  currentPage.value = page;
  pageSize.value = Number(size) || 10;
  clampCurrentPage();
}

function clampCurrentPage() {
  const maxPage = Math.max(1, Math.ceil(filteredRows.value.length / pageSize.value));
  if (currentPage.value > maxPage) {
    currentPage.value = maxPage;
  }
}

function pruneSelectedRows() {
  const currentIds = new Set(rows.value.map((row) => row.id));
  selectedRowKeys.value = selectedRowKeys.value.filter((id) => currentIds.has(id));
}

onBeforeUnmount(() => {
  clearSettleTimer();
  clearFreshRowTimer();
  clearLeavingRowTimers();
  window.removeEventListener('beforeunload', handleBeforeUnload);
});

window.addEventListener('beforeunload', handleBeforeUnload);
</script>

<style scoped>
.resource-page {
  width: 100%;
}

.resource-page__table-panel {
  position: relative;
  z-index: 2;
  margin: 0 8px 12px;
  overflow: hidden;
}

.resource-page__table-panel :deep(.ant-card-body) {
  padding: 18px;
}

.resource-page__toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 16px;
}

.resource-page__batch-warning {
  margin-bottom: 16px;
  border-color: rgba(245, 158, 11, 0.36);
  background: rgba(245, 158, 11, 0.1);
  transform-origin: 50% 0;
}

.resource-page__batch-warning :deep(.ant-alert-message) {
  color: #f6d08f;
  font-weight: 700;
}

.resource-page__batch-warning :deep(.ant-alert-description) {
  color: rgba(255, 244, 214, 0.78);
}

.resource-page__toolbar-actions {
  flex: 0 0 auto;
}

.resource-page__toolbar-filters {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  min-width: 0;
  flex: 1 1 auto;
}

.resource-page__search {
  width: min(280px, 100%);
}

.resource-page__status-select {
  width: 148px;
}

.resource-page__mobile-list {
  position: relative;
  display: block;
  padding: 0;
  border: 0;
  background: transparent;
  overflow: hidden;
}

.resource-page__mobile-card {
  position: relative;
  max-height: 620px;
  padding: 16px;
  border: 0;
  border-radius: 0;
  background: transparent;
  overflow: hidden;
  transform: translateZ(0);
  backface-visibility: hidden;
  will-change: max-height, opacity, transform, padding;
  transition: background-color 0.22s ease, transform 0.24s ease;
}

.resource-page__mobile-card + .resource-page__mobile-card {
  border-top: 1px solid var(--mmui-shell-border);
}

.resource-page__mobile-card.is-fresh {
  background: var(--mmui-sidebar-hover);
  animation: resource-row-reveal 0.56s cubic-bezier(0.2, 0.84, 0.24, 1);
}

.resource-page__mobile-card.is-leaving {
  pointer-events: none;
  animation: resource-mobile-row-leave 0.42s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

.resource-page__mobile-head {
  margin-bottom: 16px;
}

.resource-page__mobile-head-main {
  min-width: 0;
}

.resource-page__mobile-name {
  min-width: 0;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
  word-break: break-word;
}

.resource-page__mobile-meta {
  display: grid;
  gap: 10px;
}

.resource-page__mobile-meta-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.resource-page__mobile-label {
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
  white-space: nowrap;
}

.resource-page__mobile-value {
  color: var(--mmui-text);
  font-size: var(--mmui-font-size-body);
  line-height: var(--mmui-line-height-body);
  text-align: right;
}

.resource-page__mobile-actions {
  display: flex !important;
  width: 100%;
  margin-top: 16px;
  justify-content: flex-end;
}

.resource-page__mobile-action-btn {
  padding-inline: 0 !important;
}

.resource-page__empty {
  padding: 48px 0;
}

.resource-page__table {
  overflow: hidden;
}

.resource-page__table :deep(.ant-table-thead > tr > th) {
  font-size: var(--mmui-font-size-body);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-body);
}

.resource-page__table :deep(.ant-table-tbody > tr > td) {
  font-size: var(--mmui-font-size-body);
  line-height: var(--mmui-line-height-body);
}

.resource-page__table :deep(.resource-row--fresh > td) {
  background: var(--mmui-sidebar-hover) !important;
  animation: resource-row-reveal 0.56s cubic-bezier(0.2, 0.84, 0.24, 1);
}

.resource-page__table :deep(.resource-row--leaving) {
  pointer-events: none;
}

.resource-page__table :deep(.resource-row--leaving > td) {
  overflow: hidden;
  background: rgba(248, 113, 113, 0.08) !important;
  animation: resource-table-row-leave 0.42s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

.resource-page__inline-actions {
  align-items: center;
}

.resource-page__name-cell {
  display: inline-flex;
  align-items: center;
  min-width: 0;
}

.resource-page__name-cell > span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.resource-page__status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-right: 0;
  margin-inline-end: 0;
}

.resource-page__status :deep(.anticon) {
  font-size: 14px;
}

.resource-page__status :deep(.anticon-loading) {
  animation: resource-spin 1s linear infinite;
}

.resource-list-enter-active,
.resource-list-leave-active {
  overflow: hidden;
  transition:
    max-height 0.32s cubic-bezier(0.22, 1, 0.36, 1),
    padding-top 0.32s cubic-bezier(0.22, 1, 0.36, 1),
    padding-bottom 0.32s cubic-bezier(0.22, 1, 0.36, 1),
    opacity 0.18s ease-out,
    transform 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}

.resource-list-move {
  transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}

.resource-list-enter-from,
.resource-list-leave-to {
  max-height: 0;
  opacity: 0;
  padding-top: 0;
  padding-bottom: 0;
  transform: translateY(-10px) scale(0.992);
}

.resource-list-enter-to,
.resource-list-leave-from {
  max-height: 320px;
  opacity: 1;
  padding-top: 16px;
  padding-bottom: 16px;
  transform: translateY(0) scale(1);
}

.resource-alert-enter-active,
.resource-alert-leave-active {
  overflow: hidden;
  transition:
    max-height 0.28s cubic-bezier(0.22, 1, 0.36, 1),
    margin-bottom 0.28s cubic-bezier(0.22, 1, 0.36, 1),
    opacity 0.2s ease,
    transform 0.28s cubic-bezier(0.22, 1, 0.36, 1),
    filter 0.2s ease;
}

.resource-alert-enter-from,
.resource-alert-leave-to {
  max-height: 0;
  margin-bottom: 0;
  opacity: 0;
  transform: translateY(-8px) scale(0.99);
  filter: blur(4px);
}

.resource-alert-enter-to,
.resource-alert-leave-from {
  max-height: 92px;
  opacity: 1;
  transform: translateY(0) scale(1);
  filter: blur(0);
}

.resource-page__table :deep(.ant-table-pagination.ant-pagination),
.resource-page__mobile-pagination {
  margin: 20px 0 0;
}

.resource-page__table :deep(.ant-pagination-total-text),
.resource-page__mobile-pagination :deep(.ant-pagination-total-text) {
  margin-right: auto;
}

.resource-page :deep(.ant-btn-link) {
  font-weight: 600;
}

@keyframes resource-row-reveal {
  0% {
    opacity: 0;
    transform: translateY(-12px);
  }

  100% {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes resource-table-row-leave {
  0% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }

  55% {
    opacity: 0.42;
    transform: translateY(-2px) scale(0.996);
  }

  100% {
    opacity: 0;
    transform: translateY(-8px) scale(0.988);
  }
}

@keyframes resource-mobile-row-leave {
  0% {
    max-height: 620px;
    opacity: 1;
    padding-top: 16px;
    padding-bottom: 16px;
    transform: translateY(0) scale(1);
  }

  100% {
    max-height: 0;
    opacity: 0;
    padding-top: 0;
    padding-bottom: 0;
    transform: translateY(-10px) scale(0.99);
  }
}

@keyframes resource-spin {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1023px) {
  .resource-page__toolbar {
    align-items: stretch;
    flex-direction: column;
  }

  .resource-page__toolbar-filters {
    justify-content: flex-start;
  }

  .resource-page__search {
    width: 100%;
  }
}

@media (max-width: 640px) {
  .resource-page :deep(.resource-hero) {
    margin-bottom: 18px;
  }

  .resource-page__table-panel.is-compact {
    margin: 0 0 12px;
    border-color: transparent !important;
    background: transparent !important;
    box-shadow: none !important;
  }

  .resource-page__table-panel.is-compact :deep(.ant-card-body) {
    padding: 0;
  }

  .resource-page__mobile-list {
    border: 1px solid var(--mmui-shell-border);
    border-radius: 10px;
    background: var(--mmui-card-surface);
  }

  .resource-page__toolbar-actions {
    display: grid !important;
    order: 2;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px !important;
    width: 100%;
  }

  .resource-page__toolbar-actions :deep(.ant-space-item) {
    width: 100%;
    min-width: 0;
  }

  .resource-page__toolbar-actions :deep(.ant-space-item > *) {
    width: 100%;
  }

  .resource-page__toolbar-actions :deep(.ant-space-item:first-child) {
    min-width: 0;
  }

  .resource-page__toolbar-actions :deep(.ant-space-item:nth-child(2)) {
    min-width: 0;
  }

  .resource-page__toolbar-actions :deep(.ant-btn) {
    width: 100%;
    min-width: 0;
    white-space: nowrap;
  }

  .resource-page__create-btn,
  .resource-page__delete-btn {
    width: 100% !important;
    min-width: 0 !important;
  }

  .resource-page__toolbar-actions :deep(.ant-space-item:first-child .ant-btn),
  .resource-page__toolbar-actions :deep(.ant-space-item:nth-child(2) .ant-btn) {
    padding-inline: 10px;
  }

  .resource-page__toolbar-filters {
    display: grid;
    order: 1;
    grid-template-columns: minmax(0, 1fr) 44px;
    gap: 10px;
    width: 100%;
  }

  .resource-page__search {
    grid-column: 1;
    grid-row: 1;
    width: 100%;
  }

  .resource-page__status-select {
    grid-column: 1 / -1;
    grid-row: 2;
    width: 100%;
  }

  .resource-page__refresh-btn {
    grid-column: 2;
    grid-row: 1;
    width: 44px;
    min-width: 44px;
    padding-inline: 0;
  }

  .resource-page__mobile-pagination {
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    text-align: center;
  }

  .resource-page__mobile-pagination :deep(.ant-pagination-total-text) {
    width: 100%;
    margin-right: 0;
    text-align: center;
  }

}
</style>
