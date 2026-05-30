<template>
  <section ref="pageRootRef" class="site-page">
    <ResourcePageHero
      :title="page.title || '挂机宝建站'"
      subtitle="通过域名白名单快速放行业务站点访问，适配多域名建站与主机 IP 绑定场景。"
      summary-label="可建站个数"
      :primary-value="usedCount"
      :primary-total="quotaCount || null"
      :progress-percent="sitePercent"
      :stat-cards="heroStatCards"
      :compact-summary-items="heroCompactSummaryItems"
      :hero-image="siteHeroImage"
      help-text="挂机宝建站通过域名白名单放行访问；请先解析到主机 IP，再在云主机内绑定域名。"
      help-aria-label="挂机宝建站说明"
      hide-subtitle-on-compact
    />

    <a-card class="site-page__panel-shell resource-page__table-panel" :class="{ 'is-compact': compactMode }">
      <a-flex class="site-page__filters-shell" align="center" justify="space-between" :gap="12">
          <div class="site-page__search-row">
            <a-input-search
              v-model:value="searchText"
              class="site-page__search"
              placeholder="搜索域名白名单"
              allow-clear
            />
            <a-button
              class="site-page__toolbar-btn site-page__toolbar-btn--icon"
              aria-label="刷新"
              :disabled="isActionLoading('site:reload')"
              @click="reload"
            >
              <LoadingOutlined v-if="isActionLoading('site:reload')" />
              <ReloadOutlined v-else />
            </a-button>
          </div>
          <a-space class="site-page__toolbar" :size="8">
            <a-button
              class="site-page__toolbar-btn"
              type="primary"
              :disabled="quotaReached"
              @click="addRow"
            >
              <PlusOutlined />
              添加域名白名单
            </a-button>
            <a-popconfirm
              :disabled="!selectedDeletableIds.length"
              :title="batchDeleteConfirmText"
              ok-text="确定"
              cancel-text="取消"
              @confirm="deleteSelected"
            >
              <a-button
                class="site-page__toolbar-btn"
                danger
                :disabled="!selectedDeletableIds.length || isActionLoading('site:delete:selected')"
              >
                <LoadingOutlined v-if="isActionLoading('site:delete:selected')" />
                <DeleteOutlined v-else />
                删除
              </a-button>
            </a-popconfirm>
          </a-space>
      </a-flex>

      <div class="site-page__table-shell" :class="{ 'is-compact': compactMode }">
          <template v-if="compactMode">
            <transition-group
              v-if="pagedRows.length"
              name="resource-list"
              tag="div"
              class="site-page__mobile-list"
              appear
            >
              <article
                v-for="record in pagedRows"
                :key="record.id"
                class="site-page__mobile-card"
                :class="{
                  'is-editing': activeEditRowId === record.id,
                  'is-closing': closingEditId === record.id,
                  'is-new': !!newRowMap[record.id],
                  'is-fresh': freshRowId === record.id,
                }"
              >
                <a-flex class="site-page__mobile-head" align="center" justify="space-between" :gap="12">
                  <a-flex class="site-page__mobile-head-main" align="center" :gap="10">
                    <a-checkbox
                      :checked="selectedRowKeys.includes(record.id)"
                      :disabled="!!newRowMap[record.id]"
                      @change="toggleRowSelection(record.id, $event.target.checked)"
                    />
                    <div class="site-page__mobile-domain">{{ record.domain || '新域名白名单' }}</div>
                  </a-flex>
                </a-flex>

                <div class="site-page__mobile-content">
                  <transition
                    :name="editTransitionName"
                    @before-enter="lockMobilePanelHeight"
                    @enter="animateMobilePanelHeight"
                    @before-leave="lockMobilePanelHeight"
                    @after-enter="resetMobilePanelHeight"
                    @after-leave="resetMobilePanelHeight"
                    @enter-cancelled="resetMobilePanelHeight"
                    @leave-cancelled="resetMobilePanelHeight"
                  >
                    <div
                      v-if="activeEditRowId === record.id"
                      :key="`mobile-edit-${record.id}`"
                      class="site-page__mobile-panel site-page__mobile-form"
                    >
                      <div class="site-page__mobile-field">
                        <div class="site-page__mobile-label">域名白名单</div>
                        <a-input
                          v-model:value="record.domain"
                          maxlength="40"
                          placeholder="请输入完整域名"
                          @focus="focusKey = 'domain'"
                          @pressEnter="save(record)"
                        />
                      </div>

                      <transition name="site-note-swap">
                        <div :key="focusKey" class="site-page__mobile-note">
                          <div class="site-page__mobile-note-title">{{ currentTip.title }}</div>
                          <div class="site-page__mobile-note-text">{{ currentTip.text }}</div>
                        </div>
                      </transition>

                      <a-space class="site-page__mobile-actions" :size="8">
                        <a-button
                          type="primary"
                          size="small"
                          :disabled="isActionLoading(`site:save:${record.id}`)"
                          :loading="isActionLoading(`site:save:${record.id}`)"
                          @click="save(record)"
                        >保存</a-button>
                        <a-button size="small" @click="cancel(record)">取消</a-button>
                      </a-space>
                    </div>

                    <div v-else :key="`mobile-view-${record.id}`" class="site-page__mobile-panel site-page__mobile-stage">
                      <a-space class="site-page__mobile-actions" :size="16">
                        <a-button class="site-page__mobile-action-btn" type="link" size="small" @click="edit(record)">
                          编辑
                        </a-button>
                        <a-popconfirm
                          title="确定删除该域名吗？"
                          ok-text="确定"
                          cancel-text="取消"
                          @confirm="deleteItem(record)"
                        >
                          <a-button
                            class="site-page__mobile-action-btn"
                            type="link"
                            danger
                            size="small"
                            :disabled="isActionLoading(`site:delete:${record.id}`)"
                            :loading="isActionLoading(`site:delete:${record.id}`)"
                          >删除</a-button>
                        </a-popconfirm>
                      </a-space>
                    </div>
                  </transition>
                </div>
              </article>
            </transition-group>
            <a-empty v-else class="site-page__empty" :description="emptyText" />
            <a-pagination
              v-if="filteredRows.length"
              class="site-page__mobile-pagination"
              :current="currentPage"
              :page-size="pageSize"
              :page-size-options="pageSizeOptions"
              :total="filteredRows.length"
              show-size-changer
              :show-total="(total) => `共 ${total} 条`"
              @change="handlePageChange"
              @showSizeChange="handlePageChange"
            />
          </template>

          <template v-else>
            <a-table
              :columns="columns"
              :data-source="filteredRows"
              :expandedRowKeys="expandedRowKeys"
              :expandIconColumnIndex="-1"
              :pagination="tablePagination"
              :rowClassName="rowClassName"
              :row-selection="rowSelection"
              :scroll="{ x: 620 }"
              rowKey="id"
              size="middle"
              tableLayout="fixed"
              class="site-page__table"
            >
              <template #expandedRowRender="{ record }">
                <transition name="site-note-panel" appear>
                  <div v-if="record.id === noteVisibleId" class="site-page__note-stage">
                    <transition name="site-note-swap">
                      <a-flex :key="focusKey" class="site-page__note" align="center" :gap="18">
                        <div class="site-page__note-icon" aria-hidden="true">
                          <component :is="currentTip.icon" />
                        </div>
                        <div class="site-page__note-copy">
                          <div class="site-page__note-title">{{ currentTip.title }}</div>
                          <div class="site-page__note-text">{{ currentTip.text }}</div>
                        </div>
                      </a-flex>
                    </transition>
                  </div>
                </transition>
              </template>

              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'domain'">
                  <div class="site-cell-stack">
                    <transition :name="editTransitionName">
                      <a-input
                        v-if="shouldRenderRowEditor(record)"
                        :key="`edit-domain-${record.id}`"
                        v-model:value="record.domain"
                        maxlength="40"
                        class="site-cell-view site-cell-control"
                        placeholder="请输入完整域名"
                        @focus="focusKey = 'domain'"
                        @pressEnter="save(record)"
                      />
                      <span v-else :key="`view-domain-${record.id}`" class="site-cell-view site-cell-text">
                        {{ record.domain }}
                      </span>
                    </transition>
                  </div>
                </template>

                <template v-else-if="column.key === 'actions'">
                  <div class="site-cell-stack">
                    <transition :name="editTransitionName">
                      <a-space
                        v-if="shouldRenderRowEditor(record)"
                        :key="`edit-actions-${record.id}`"
                        class="site-cell-view site-cell-control site-page__inline-actions"
                        :size="8"
                      >
                        <a-button
                          type="primary"
                          size="small"
                          :disabled="isActionLoading(`site:save:${record.id}`)"
                          :loading="isActionLoading(`site:save:${record.id}`)"
                          @click="save(record)"
                        >保存</a-button>
                        <a-button size="small" @click="cancel(record)">取消</a-button>
                      </a-space>
                      <a-space
                        v-else
                        :key="`view-actions-${record.id}`"
                        class="site-cell-view site-cell-text site-page__inline-actions"
                        :size="4"
                      >
                        <a-button type="link" size="small" @click="edit(record)">编辑</a-button>
                        <a-popconfirm
                          title="确定删除该域名吗？"
                          ok-text="确定"
                          cancel-text="取消"
                          @confirm="deleteItem(record)"
                        >
                          <a-button
                            type="link"
                            danger
                            size="small"
                            :disabled="isActionLoading(`site:delete:${record.id}`)"
                            :loading="isActionLoading(`site:delete:${record.id}`)"
                          >删除</a-button>
                        </a-popconfirm>
                      </a-space>
                    </transition>
                  </div>
                </template>
              </template>
            </a-table>
          </template>
      </div>
    </a-card>
  </section>
</template>

<script setup>
import {
  DeleteOutlined,
  GlobalOutlined,
  InfoCircleOutlined,
  LinkOutlined,
  LoadingOutlined,
  PlusOutlined,
  ReloadOutlined,
} from '@ant-design/icons-vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { message } from 'ant-design-vue';
import ResourcePageHero from '@/components/pages/ResourcePageHero.vue';
import { useDashboardStore } from '@/stores/dashboard';
import { pinia } from '@/stores/pinia';
import { useCompactPageMode } from '@/components/pages/useCompactPageMode';
import { useActionLocks } from '@/composables/useActionLocks';
import siteHeroImage from '@/assets/page-hero/site-hero.png';

const props = defineProps({
  page: {
    type: Object,
    default: () => ({}),
  },
});

const EDIT_CLOSE_DELAY = 220;
const NOTE_EXIT_LEAD = 120;

const store = useDashboardStore(pinia);
const { pageRootRef, compactMode } = useCompactPageMode();
const { isActionLoading, runWithActionLoading } = useActionLocks();

const rows = ref([]);
const currentPage = ref(1);
const pageSize = ref(10);
const searchText = ref('');
const freshRowId = ref(null);
const editingId = ref(null);
const closingEditId = ref(null);
const noteVisibleId = ref(null);
const selectedRowKeys = ref([]);
const newRowMap = ref({});
const editSnapshotMap = ref({});
const editMotionDirection = ref('open');
const focusKey = ref('domain');

let freshRowTimer = null;
let deferExternalRowsSync = false;
let pendingExternalRowsSync = false;

const tips = {
  domain: {
    title: '域名白名单',
    text: '填写完整域名，例如 www.example.com；保存前请确认没有协议头、路径或端口。',
    icon: LinkOutlined,
  },
  dns: {
    title: '解析到主机 IP',
    text: `域名需要先解析到 ${primaryIpPlaceholder()}，解析生效后再添加白名单更稳。`,
    icon: GlobalOutlined,
  },
};

const columns = [
  { title: '域名白名单', dataIndex: 'domain', key: 'domain', width: 460 },
  { title: '操作', key: 'actions', align: 'center', width: 160 },
];
const pageSizeOptions = ['10', '20', '50'];

const usedCount = computed(() => rows.value.filter((row) => !newRowMap.value[row.id]).length);
const quotaCount = computed(() => Number(props.page?.quota || props.page?.total || store.quotas?.site?.total || 0));
const availableCount = computed(() => Math.max(0, quotaCount.value - usedCount.value));
const sitePercent = computed(() => {
  if (!quotaCount.value) {
    return 0;
  }

  return Math.min(100, Math.round((usedCount.value / quotaCount.value) * 100));
});
const heroStatCards = computed(() => ([
  { label: '已建站', value: usedCount.value },
  { label: '剩余', value: availableCount.value },
]));
const heroCompactSummaryItems = computed(() => ([
  { label: '可建站个数', value: `${usedCount.value} / ${quotaCount.value || 0}` },
]));
const quotaReached = computed(() => quotaCount.value > 0 && usedCount.value >= quotaCount.value);
const activeEditRowId = computed(() => editingId.value || closingEditId.value || null);
const editTransitionName = computed(() => (editMotionDirection.value === 'close' ? 'site-edit-close' : 'site-edit-open'));
const expandedRowKeys = computed(() => (noteVisibleId.value ? [noteVisibleId.value] : []));
const selectedDeletableIds = computed(() => {
  const currentIds = new Set(rows.value.filter((row) => !newRowMap.value[row.id]).map((row) => row.id));
  return selectedRowKeys.value.filter((id) => currentIds.has(id));
});
const batchDeleteConfirmText = computed(() => `确定删除选中的 ${selectedDeletableIds.value.length} 个域名吗？`);
const emptyText = computed(() => (searchText.value.trim() ? '未找到匹配的域名白名单' : '暂无域名白名单'));
const filteredRows = computed(() => {
  const keyword = searchText.value.trim().toLowerCase();
  if (!keyword) {
    return rows.value;
  }

  return rows.value.filter((row) => String(row.domain || '').toLowerCase().includes(keyword));
});
const pagedRows = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value;
  return filteredRows.value.slice(start, start + pageSize.value);
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
  onChange: (keys) => {
    selectedRowKeys.value = keys;
  },
  getCheckboxProps: (record) => ({
    disabled: !!newRowMap.value[record.id],
  }),
}));
const primaryIp = computed(() => {
  const remoteAddress = String(store.host?.remoteAddress || '').trim();
  const remoteHost = remoteAddress.split(':')[0]?.trim();
  return props.page?.primaryIp || props.page?.ip || remoteHost || '149.88.18.20';
});
const currentTip = computed(() => {
  if (focusKey.value === 'dns') {
    return {
      ...tips.dns,
      text: `域名需要先解析到 ${primaryIp.value}，解析生效后再添加白名单更稳。`,
    };
  }

  return tips.domain;
});

function primaryIpPlaceholder() {
  return '主机 IP';
}

function normalizeDomain(value) {
  return String(value || '').trim().toLowerCase();
}

function validateDomain(value) {
  const domain = normalizeDomain(value);
  const domainRegex = /^(?!:\/\/)([a-z0-9][a-z0-9-]{0,61}[a-z0-9]\.)+[a-z]{2,}$/i;

  if (!domainRegex.test(domain)) {
    throw new Error('请输入完整有效的域名');
  }

  return domain;
}

function toViewRow(item) {
  return {
    id: item?.id,
    domain: normalizeDomain(item?.domain),
  };
}

function syncRows(force = false) {
  if (!force && deferExternalRowsSync) {
    pendingExternalRowsSync = true;
    return;
  }

  pendingExternalRowsSync = false;
  rows.value = (props.page?.rows || []).map((item) => toViewRow(item));
  pruneSelectedRows();
}

function flushDeferredRowSync(force = false) {
  deferExternalRowsSync = false;
  const shouldSync = force || pendingExternalRowsSync;
  pendingExternalRowsSync = false;

  if (shouldSync) {
    syncRows(true);
  }
}

function rowClassName(record) {
  return [
    record.id === activeEditRowId.value ? 'is-editing' : '',
    newRowMap.value[record.id] ? 'site-row--new' : '',
    record.id === freshRowId.value ? 'site-row--fresh' : '',
  ].filter(Boolean).join(' ');
}

function shouldRenderRowEditor(record) {
  const recordId = record?.id;
  if (!recordId) {
    return false;
  }

  if (editingId.value === recordId) {
    return true;
  }

  return closingEditId.value === recordId && Boolean(newRowMap.value[recordId]);
}

function rememberEditSnapshot(record) {
  if (!record?.id || newRowMap.value[record.id]) {
    return;
  }

  editSnapshotMap.value[record.id] = { ...record };
}

function restoreEditSnapshot(record) {
  const snapshot = editSnapshotMap.value[record?.id];
  if (snapshot && record) {
    Object.assign(record, snapshot);
  }
}

function clearEditSnapshot(id) {
  if (!id || !editSnapshotMap.value[id]) {
    return;
  }

  delete editSnapshotMap.value[id];
}

function clearFreshRowTimer() {
  if (freshRowTimer) {
    window.clearTimeout(freshRowTimer);
    freshRowTimer = null;
  }
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

async function refreshRowsAfterMutationFailure() {
  try {
    await store.refreshDomainWhitelist();
  } finally {
    flushDeferredRowSync(true);
    clampCurrentPage();
    pruneSelectedRows();
  }
}

function waitFor(ms) {
  return new Promise((resolve) => {
    window.setTimeout(resolve, ms);
  });
}

function normalizeHeight(value) {
  const nextValue = Number(value);
  if (!Number.isFinite(nextValue)) {
    return 0;
  }

  return Math.max(0, Math.round(nextValue * 100) / 100);
}

function readElementHeight(el) {
  return normalizeHeight(el?.getBoundingClientRect?.().height || el?.scrollHeight || 0);
}

function setInlineHeight(el, height) {
  if (!el) {
    return;
  }

  el.style.height = `${normalizeHeight(height)}px`;
}

function resolveMobilePanelContainer(el) {
  const container = el?.parentElement;
  return container?.classList.contains('site-page__mobile-content') ? container : null;
}

function lockMobilePanelHeight(el) {
  const container = resolveMobilePanelContainer(el);
  if (!container) {
    return;
  }

  const currentHeight = readElementHeight(container) || readElementHeight(el);
  setInlineHeight(container, currentHeight);
  container.style.overflow = 'hidden';
  delete container.dataset.heightResetToken;
}

function animateMobilePanelHeight(el) {
  const container = resolveMobilePanelContainer(el);
  if (!container) {
    return;
  }

  const nextHeight = readElementHeight(el);
  window.requestAnimationFrame(() => {
    setInlineHeight(container, nextHeight);
  });
}

function resetMobilePanelHeight(el) {
  const container = resolveMobilePanelContainer(el);
  if (!container) {
    return;
  }

  const finalHeight = readElementHeight(el);
  if (finalHeight > 0) {
    setInlineHeight(container, finalHeight);
  }

  const resetToken = `${Date.now()}_${Math.random()}`;
  container.dataset.heightResetToken = resetToken;

  window.requestAnimationFrame(() => {
    window.requestAnimationFrame(() => {
      if (container.dataset.heightResetToken !== resetToken) {
        return;
      }

      container.style.height = '';
      container.style.overflow = '';
      delete container.dataset.heightResetToken;
    });
  });
}

function scheduleNoteOpen(id) {
  noteVisibleId.value = id;
}

async function hideNoteBeforeClose(id) {
  if (noteVisibleId.value !== id) {
    noteVisibleId.value = null;
    return;
  }

  noteVisibleId.value = null;
  await nextTick();
  await waitFor(NOTE_EXIT_LEAD);
}

async function closeEditing(recordId, options = {}) {
  const targetId = recordId || editingId.value || closingEditId.value;
  if (!targetId) {
    return;
  }

  const { afterClose, closeDelay = EDIT_CLOSE_DELAY } = options;
  editMotionDirection.value = 'close';
  await hideNoteBeforeClose(targetId);
  closingEditId.value = targetId;
  editingId.value = null;
  await nextTick();
  await waitFor(closeDelay);

  if (typeof afterClose === 'function') {
    await afterClose();
  }

  if (closingEditId.value === targetId) {
    closingEditId.value = null;
  }

  clearEditSnapshot(targetId);
  editMotionDirection.value = 'open';
}

function resetEditingState() {
  editingId.value = null;
  closingEditId.value = null;
  noteVisibleId.value = null;
  focusKey.value = 'domain';
  newRowMap.value = {};
  editSnapshotMap.value = {};
  editMotionDirection.value = 'open';
  deferExternalRowsSync = false;
  pendingExternalRowsSync = false;
}

function addRow() {
  if (activeEditRowId.value) {
    message.info('请先完成当前编辑中的条目');
    return;
  }

  if (quotaReached.value) {
    message.warning('可建站数量已用完');
    return;
  }

  const id = `new_${Date.now()}`;
  rows.value.unshift({
    id,
    domain: '',
  });
  newRowMap.value[id] = true;
  editMotionDirection.value = 'open';
  editingId.value = id;
  focusKey.value = 'domain';
  currentPage.value = 1;
  selectedRowKeys.value = [];
  scheduleNoteOpen(id);
}

function edit(record) {
  if (activeEditRowId.value) {
    message.info('请先完成当前编辑中的条目');
    return;
  }

  rememberEditSnapshot(record);
  editMotionDirection.value = 'open';
  editingId.value = record.id;
  focusKey.value = 'domain';
  scheduleNoteOpen(record.id);
}

async function cancel(record) {
  if (record && newRowMap.value[record.id]) {
    await closeEditing(record.id, {
      closeDelay: 180,
      afterClose: () => {
        rows.value = rows.value.filter((item) => item.id !== record.id);
        delete newRowMap.value[record.id];
        pruneSelectedRows();
      },
    });
    return;
  }

  restoreEditSnapshot(record);
  await closeEditing(record?.id);
}

async function save(record) {
  let domain = '';
  try {
    domain = validateDomain(record?.domain);
  } catch (error) {
    message.warning(error.message);
    return;
  }

  await runWithActionLoading(`site:save:${record.id}`, async () => {
    try {
      if (newRowMap.value[record.id]) {
        deferExternalRowsSync = true;
        const rowsAfterCreate = await store.createDomainWhitelist({ domain });
        delete newRowMap.value[record.id];
        await closeEditing(record.id, {
          afterClose: () => {
            flushDeferredRowSync(true);
            markFreshRow(rowsAfterCreate?.[0]?.id);
          },
        });
        message.success('域名白名单已添加');
        return;
      }

      const sourceId = record.id;
      let sourceDeleted = false;
      deferExternalRowsSync = true;

      try {
        await store.deleteDomainWhitelist(sourceId);
        sourceDeleted = true;
        const rowsAfterCreate = await store.createDomainWhitelist({ domain });
        const createdRow = rowsAfterCreate?.find((item) => normalizeDomain(item?.domain) === domain);
        selectedRowKeys.value = selectedRowKeys.value.filter((id) => id !== sourceId);
        await closeEditing(sourceId, {
          afterClose: () => {
            flushDeferredRowSync(true);
            markFreshRow(createdRow?.id || rowsAfterCreate?.[0]?.id);
          },
        });
        message.success('域名白名单已修改');
      } catch (error) {
        if (sourceDeleted) {
          await closeEditing(sourceId, {
            afterClose: refreshRowsAfterMutationFailure,
          });
          message.error(error instanceof Error ? `添加失败，记录已丢失：${error.message}` : '添加失败，记录已丢失');
          return;
        }

        await refreshRowsAfterMutationFailure();
        message.error(error instanceof Error ? error.message : '修改域名白名单失败');
      }
    } catch (error) {
      flushDeferredRowSync();
      message.error(error instanceof Error ? error.message : '添加域名白名单失败');
    }
  });
}

async function deleteItem(record) {
  if (activeEditRowId.value && activeEditRowId.value !== record.id) {
    message.info('请先完成当前编辑中的条目');
    return;
  }

  await runWithActionLoading(`site:delete:${record.id}`, async () => {
    try {
      await store.deleteDomainWhitelist(record.id);
      selectedRowKeys.value = selectedRowKeys.value.filter((id) => id !== record.id);
      syncRows(true);
      message.success('域名白名单已删除');
    } catch (error) {
      message.error(error instanceof Error ? error.message : '删除域名白名单失败');
    }
  });
}

async function deleteSelected() {
  if (activeEditRowId.value) {
    message.info('请先完成当前编辑中的条目');
    return;
  }

  const ids = [...selectedDeletableIds.value];
  if (!ids.length) {
    return;
  }

  await runWithActionLoading('site:delete:selected', async () => {
    try {
      await store.deleteDomainWhitelists(ids);
      selectedRowKeys.value = [];
      syncRows(true);
      message.success(`已删除 ${ids.length} 个域名白名单`);
    } catch (error) {
      message.error(error instanceof Error ? error.message : '批量删除域名白名单失败');
    }
  });
}

async function reload() {
  await runWithActionLoading('site:reload', async () => {
    try {
      resetEditingState();
      await store.refreshDomainWhitelist();
      syncRows(true);
      clampCurrentPage();
      pruneSelectedRows();
    } catch (error) {
      message.error(error instanceof Error ? error.message : '刷新域名白名单失败');
    }
  });
}

function toggleRowSelection(id, checked) {
  if (newRowMap.value[id]) {
    return;
  }

  if (checked) {
    selectedRowKeys.value = [...new Set([...selectedRowKeys.value, id])];
    return;
  }

  selectedRowKeys.value = selectedRowKeys.value.filter((selectedId) => selectedId !== id);
}

function pruneSelectedRows() {
  const currentIds = new Set(rows.value.filter((row) => !newRowMap.value[row.id]).map((row) => row.id));
  selectedRowKeys.value = selectedRowKeys.value.filter((id) => currentIds.has(id));
}

watch(
  () => props.page?.rows,
  () => {
    syncRows();
  },
  { immediate: true, deep: true },
);

watch(
  () => rows.value.map((row) => row.id),
  () => {
    pruneSelectedRows();
    clampCurrentPage();
  },
);

watch(searchText, () => {
  currentPage.value = 1;
});

watch(pageSize, () => {
  clampCurrentPage();
});

function handlePageChange(page, size) {
  if (activeEditRowId.value) {
    message.info('请先完成当前编辑中的条目');
    return;
  }

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

onBeforeUnmount(() => {
  clearFreshRowTimer();
});
</script>

<style scoped>
.site-page {
  width: 100%;
}

.site-page__panel-shell {
  position: relative;
  z-index: 2;
  margin: 0 8px 12px;
  overflow: hidden;
}

.site-page__panel-shell :deep(.ant-card-body) {
  padding: 18px;
}

.site-page__header-shell {
  margin: 8px 8px 32px;
}

.site-page__header {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 54px;
}

.site-page__title {
  padding-left: 8px;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-hero-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-page);
}

.site-page__summary {
  margin-left: auto;
  min-height: 42px;
  padding: 0 14px;
  border: 1px solid var(--mmui-shell-border);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.02);
}

.site-page__summary-label {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.site-page__summary-value {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.site-page__summary-help {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 32px;
  min-width: 32px;
  height: 32px;
  padding: 0;
  color: var(--mmui-text-muted) !important;
  line-height: 1 !important;
}

.site-page__summary-help :deep(.anticon) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.site-page__filters-shell {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin: 0 0 16px;
}

.site-page__search {
  width: min(320px, 100%);
}

.site-page__search-row {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.site-page__toolbar {
  width: fit-content;
  margin-left: auto;
  flex-shrink: 0;
}

.site-page__toolbar-btn {
  min-width: 0;
  border-radius: 10px;
}

.site-page__toolbar-btn--icon {
  width: 40px;
  padding-inline: 0;
}

.site-page__table-shell {
  margin: 0;
  overflow: hidden;
  border-top: 1px solid var(--mmui-shell-border);
}

.site-page__table :deep(.ant-table-pagination.ant-pagination),
.site-page__mobile-pagination {
  margin: 20px 0 0;
}

.site-page__table :deep(.ant-pagination-total-text),
.site-page__mobile-pagination :deep(.ant-pagination-total-text) {
  margin-right: auto;
}

.site-page__mobile-list {
  position: relative;
  display: block;
  padding: 0;
  border: 0;
  background: transparent;
}

.site-page__mobile-card {
  position: relative;
  max-height: 520px;
  padding: 16px;
  overflow: hidden;
  border: 0;
  border-radius: 0;
  background: transparent;
  backface-visibility: hidden;
  transform: translateZ(0);
  transition: background-color 0.22s ease, transform 0.24s ease;
  will-change: max-height, opacity, transform, padding;
}

.site-page__mobile-card + .site-page__mobile-card {
  border-top: 1px solid var(--mmui-shell-border);
}

.site-page__mobile-card.is-editing,
.site-page__mobile-card.is-fresh {
  background: var(--mmui-sidebar-hover);
}

.site-page__mobile-card.is-editing {
  animation: site-edit-activate 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}

.site-page__mobile-card.is-closing {
  pointer-events: none;
}

.site-page__mobile-card.is-new {
  background: var(--mmui-sidebar-hover);
  animation: site-row-reveal 0.56s cubic-bezier(0.2, 0.84, 0.24, 1);
}

.site-page__mobile-card.is-new::before {
  content: none;
  position: absolute;
  inset: 0;
  background: linear-gradient(
    104deg,
    rgba(var(--mmui-accent-blue-rgb), 0.2) 0%,
    rgba(var(--mmui-accent-blue-rgb), 0.08) 36%,
    rgba(255, 255, 255, 0.12) 52%,
    rgba(var(--mmui-accent-blue-rgb), 0.04) 78%,
    rgba(var(--mmui-accent-blue-rgb), 0) 100%
  );
  opacity: 0;
  pointer-events: none;
  animation: site-card-sheen 0.88s ease-out 0.1s both;
}

.site-page__mobile-head {
  margin-bottom: 0;
  flex-wrap: nowrap;
}

.site-page__mobile-head-main {
  min-width: 0;
}

.site-page__mobile-domain {
  min-width: 0;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
  word-break: break-word;
}

.site-page__mobile-content {
  position: relative;
  display: grid;
  align-items: start;
  overflow: hidden;
  transition: height 0.34s cubic-bezier(0.22, 1, 0.36, 1);
  will-change: height;
}

.site-page__mobile-panel {
  grid-area: 1 / 1;
  min-width: 0;
}

.site-page__mobile-form {
  display: grid;
  gap: 12px;
  padding-top: 16px;
}

.site-page__mobile-stage {
  display: grid;
  gap: 0;
}

.site-page__mobile-field {
  display: grid;
  gap: 6px;
}

.site-page__mobile-label {
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
  white-space: nowrap;
}

.site-page__mobile-note {
  display: grid;
  gap: 4px;
  padding: 12px;
  border-radius: 10px;
  background: rgba(var(--mmui-accent-blue-rgb), 0.08);
}

.site-page__mobile-note-title {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-footnote);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-footnote);
}

.site-page__mobile-note-text {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
}

.site-page__mobile-actions {
  display: flex !important;
  width: 100%;
  margin-top: 16px;
  justify-content: flex-end;
}

.site-page__mobile-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding-inline: 0 !important;
}

.site-page__empty {
  padding: 26px 0;
}

.site-page__table {
  border: 0;
}

.site-page__table :deep(.ant-table),
.site-page__table :deep(.ant-table-container),
.site-page__table :deep(.ant-table-content),
.site-page__table :deep(.ant-table-header),
.site-page__table :deep(.ant-table-body) {
  border-radius: 10px;
}

.site-page__table :deep(.ant-table) {
  background: transparent;
  box-shadow: none;
}

.site-page__table :deep(.ant-table-container::before),
.site-page__table :deep(.ant-table-container::after) {
  display: none;
}

.site-page__table :deep(.ant-table-content table),
.site-page__table :deep(.ant-table-header table),
.site-page__table :deep(.ant-table-body table) {
  width: max(100%, 620px) !important;
  min-width: 620px;
}

.site-page__table :deep(.ant-table-thead > tr > th) {
  height: 66px;
  padding: 0 12px;
  color: var(--mmui-card-title);
  white-space: nowrap;
  background: rgba(255, 255, 255, 0.04);
  border-bottom: 1px solid var(--mmui-shell-border);
}

.site-page__table :deep(.ant-table-tbody > tr > td) {
  padding: 16px 12px;
  border-bottom: 1px solid var(--mmui-shell-border);
}

.site-page__table :deep(.ant-table-cell-row-hover) {
  background: transparent !important;
}

.site-page__table :deep(.ant-table-tbody > tr:hover > td) {
  background: color-mix(in srgb, var(--mmui-card-surface) 94%, #ffffff 6%) !important;
}

.site-page__table :deep(.ant-table-expanded-row > td) {
  padding: 0 !important;
  background: transparent !important;
}

.site-page__table :deep(.is-editing > td) {
  background: var(--mmui-sidebar-hover) !important;
  animation: site-edit-activate 0.28s cubic-bezier(0.22, 1, 0.36, 1);
  transition: background-color 0.24s ease;
}

.site-page__table :deep(.site-row--new > td) {
  background: var(--mmui-sidebar-hover) !important;
  animation: site-row-reveal 0.56s cubic-bezier(0.2, 0.84, 0.24, 1);
  box-shadow: none;
  will-change: transform, opacity;
}

.site-page__table :deep(.site-row--new > td:first-child) {
  box-shadow: none;
}

.site-page__table :deep(.site-row--new > td:last-child) {
  box-shadow: none;
}

.site-page__table :deep(.site-row--fresh > td) {
  background: var(--mmui-sidebar-hover) !important;
  animation: site-row-reveal 0.56s cubic-bezier(0.2, 0.84, 0.24, 1);
}

.site-page__table :deep(.ant-input) {
  border-radius: 10px;
  box-shadow: none;
}

.site-page__table :deep(.is-editing .ant-input) {
  background: var(--mmui-card-action-bg) !important;
}

.site-page__table :deep(.ant-btn-sm) {
  height: 32px;
  min-width: 56px;
  padding: 0 12px;
  border-radius: 10px;
}

.site-cell-stack {
  display: grid;
  align-items: center;
  width: 100%;
  min-width: 0;
  min-height: 32px;
}

.site-cell-stack > * {
  grid-area: 1 / 1;
  min-width: 0;
}

.site-cell-view {
  width: 100%;
  min-width: 0;
}

.site-cell-text {
  display: inline-flex;
  align-items: center;
  min-height: 32px;
  padding-inline: 12px;
  box-sizing: border-box;
  opacity: 1;
}

.site-cell-control {
  min-width: 0;
}

.site-page__inline-actions {
  align-items: center;
  width: auto;
  max-width: 100%;
  padding-inline: 0;
  justify-self: center;
}

.site-page__note-stage {
  position: relative;
  display: grid;
  overflow: hidden;
  padding: 0 8px 8px;
  transform-origin: top center;
}

.site-page__note {
  display: flex;
  align-items: center;
  gap: 18px;
  padding: 26px 18px 18px 12px;
}

.site-page__note-copy {
  min-width: 0;
  flex: 1 1 auto;
}

.site-page__note-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  min-width: 48px;
  height: 48px;
  color: var(--mmui-accent-blue);
  border-radius: 12px;
  background: rgba(var(--mmui-accent-blue-rgb), 0.12);
}

.site-page__note-icon :deep(.anticon),
.site-page__note-icon :deep(svg) {
  font-size: 22px;
}

.site-page__note-title {
  margin-bottom: 6px;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.site-page__note-text {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-body);
  line-height: var(--mmui-line-height-body);
}

.site-page__note-stage > .site-page__note {
  grid-area: 1 / 1;
}

.site-note-panel-enter-active {
  transition:
    max-height 0.34s cubic-bezier(0.22, 1, 0.36, 1),
    padding-bottom 0.34s cubic-bezier(0.22, 1, 0.36, 1),
    opacity 0.2s ease-out,
    transform 0.24s ease-out;
  will-change: max-height, padding-bottom, opacity, transform;
}

.site-note-panel-leave-active {
  transition:
    max-height 0.28s cubic-bezier(0.22, 1, 0.36, 1),
    padding-bottom 0.28s cubic-bezier(0.22, 1, 0.36, 1),
    opacity 0.18s ease-out,
    transform 0.2s ease-out;
  will-change: max-height, padding-bottom, opacity, transform;
}

.site-note-panel-enter-from,
.site-note-panel-leave-to {
  max-height: 0;
  padding-bottom: 0;
  opacity: 0;
  transform: translateY(-6px);
}

.site-note-panel-enter-to,
.site-note-panel-leave-from {
  max-height: 180px;
  padding-bottom: 8px;
  opacity: 1;
  transform: translateY(0);
}

.site-note-swap-enter-active,
.site-note-swap-leave-active {
  transition: opacity 0.2s ease-out, transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), filter 0.2s ease-out;
  transform-origin: left center;
}

.site-note-swap-enter-from {
  opacity: 0;
  filter: blur(3px);
  transform: translateX(14px);
}

.site-note-swap-leave-to {
  opacity: 0;
  filter: blur(2px);
  transform: translateX(-10px);
}

.site-edit-open-enter-active,
.site-edit-open-leave-active,
.site-edit-close-enter-active,
.site-edit-close-leave-active {
  transition:
    transform 0.28s cubic-bezier(0.22, 1, 0.36, 1),
    opacity 0.2s ease-out,
    filter 0.2s ease-out;
  will-change: transform, opacity, filter;
}

.site-edit-open-enter-from.site-page__mobile-panel,
.site-edit-close-leave-to.site-page__mobile-panel {
  opacity: 0;
  filter: blur(2px);
  transform: translateY(10px) scale(0.992);
}

.site-edit-open-enter-to.site-page__mobile-panel,
.site-edit-close-leave-from.site-page__mobile-panel,
.site-edit-open-leave-from.site-page__mobile-panel,
.site-edit-close-enter-to.site-page__mobile-panel {
  opacity: 1;
  filter: blur(0);
  transform: translateY(0) scale(1);
}

.site-edit-open-leave-to.site-page__mobile-panel,
.site-edit-close-enter-from.site-page__mobile-panel {
  opacity: 0;
  filter: blur(1.5px);
  transform: translateY(-6px) scale(0.996);
}

.site-edit-open-enter-active.site-cell-control,
.site-edit-close-leave-active.site-cell-control {
  z-index: 2;
}

.site-edit-open-leave-active.site-cell-text,
.site-edit-close-enter-active.site-cell-text {
  z-index: 1;
}

.site-edit-open-enter-from.site-cell-control {
  opacity: 0;
  filter: blur(1.5px);
  transform: translateX(4px) scale(0.998);
}

.site-edit-open-enter-to.site-cell-control,
.site-edit-close-leave-from.site-cell-control {
  opacity: 1;
  filter: blur(0);
  transform: translateX(0) scale(1);
}

.site-edit-open-leave-from.site-cell-text,
.site-edit-close-enter-to.site-cell-text {
  opacity: 1;
  filter: none;
  transform: translateX(0);
}

.site-edit-open-leave-to.site-cell-text,
.site-edit-close-enter-from.site-cell-text {
  opacity: 0;
  filter: none;
  transform: translateX(-4px);
}

.site-edit-close-leave-to.site-cell-control {
  opacity: 0;
  filter: blur(1.5px);
  transform: translateX(4px) scale(0.998);
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
  padding-top: 0;
  padding-bottom: 0;
  opacity: 0;
  transform: translateY(-10px) scale(0.992);
}

.resource-list-enter-to,
.resource-list-leave-from {
  max-height: 320px;
  padding-top: 16px;
  padding-bottom: 16px;
  opacity: 1;
  transform: translateY(0) scale(1);
}

@keyframes site-card-appear {
  0% {
    opacity: 0;
    transform: translateY(-18px);
  }

  48% {
    opacity: 1;
  }

  100% {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes site-card-sheen {
  0% {
    opacity: 0;
    transform: translateX(-38%);
  }

  32% {
    opacity: 1;
  }

  100% {
    opacity: 0;
    transform: translateX(38%);
  }
}

@keyframes site-row-reveal {
  0% {
    opacity: 0;
    transform: translateY(-12px);
  }

  100% {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes site-edit-activate {
  0% {
    transform: scale(0.997);
  }

  100% {
    transform: scale(1);
  }
}

@media (max-width: 1023px) {
  .site-page__header {
    flex-wrap: wrap;
  }

  .site-page__title {
    padding-left: 0;
  }

  .site-page__summary {
    margin-left: 0;
  }

  .site-page__filters-shell {
    align-items: stretch;
    flex-direction: column;
  }

  .site-page__search {
    width: 100%;
  }

  .site-page__toolbar {
    margin-left: auto;
    align-self: flex-end;
  }
}

@media (max-width: 640px) {
  .site-page :deep(.resource-hero) {
    margin-bottom: 18px;
  }

  .site-page__panel-shell.is-compact {
    margin: 0 0 12px;
    border-color: transparent !important;
    background: transparent !important;
    box-shadow: none !important;
  }

  .site-page__panel-shell.is-compact :deep(.ant-card-body) {
    padding: 0;
  }

  .site-page__filters-shell {
    display: flex !important;
    align-items: stretch !important;
    flex-direction: column;
    gap: 10px !important;
  }

  .site-page__search-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 44px;
    gap: 10px;
    width: 100%;
  }

  .site-page__search {
    width: 100%;
  }

  .site-page__toolbar {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px !important;
    width: 100%;
    margin-left: 0;
    align-self: stretch;
  }

  .site-page__toolbar :deep(.ant-space-item) {
    width: 100%;
    min-width: 0;
  }

  .site-page__toolbar :deep(.ant-space-item > *) {
    width: 100%;
  }

  .site-page__toolbar-btn {
    width: 100%;
    min-width: 0;
    padding-inline: 10px;
    white-space: nowrap;
  }

  .site-page__toolbar-btn--icon {
    width: 44px;
    min-width: 44px;
    padding-inline: 0;
  }

  .site-page__table-shell.is-compact {
    border-top: 0;
  }

  .site-page__mobile-list {
    overflow: hidden;
    border: 1px solid var(--mmui-shell-border);
    border-radius: 10px;
    background: var(--mmui-card-surface);
  }

  .site-page__mobile-pagination {
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    text-align: center;
  }

  .site-page__mobile-pagination :deep(.ant-pagination-total-text) {
    width: 100%;
    margin-right: 0;
    text-align: center;
  }
}
</style>

