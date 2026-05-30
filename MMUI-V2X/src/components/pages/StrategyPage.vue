<template>
  <section ref="pageRootRef" class="strategy-page">
    <ResourcePageHero
      :title="page.title || '策略'"
      subtitle="按方向、协议、端口和来源范围管理主机安全规则，快速建立清晰稳定的访问边界。"
      summary-label="创建策略数"
      :primary-value="totalCount"
      :primary-total="quotaTotal"
      :progress-percent="strategyPercent"
      :stat-cards="heroStatCards"
      :compact-summary-items="heroCompactSummaryItems"
      :hero-image="strategyHeroImage"
      help-text="安全策略用于控制云主机入站 / 出站访问；可按方向、授权策略和协议筛选，端口 -1 表示不限制。"
      help-aria-label="安全策略说明"
      hide-subtitle-on-compact
    />

    <a-card class="strategy-page__panel-shell resource-page__table-panel" :class="{ 'is-compact': compactMode }">
      <div class="strategy-page__filters-shell">
        <div class="strategy-page__filters-main">
          <div class="strategy-page__filters-grid">
            <a-select
              v-model:value="draftFilters.direction"
              class="strategy-page__filter-field"
              :placeholder="compactMode ? '方向' : '规则方向'"
              allow-clear
              @change="handleFilterChange"
            >
              <a-select-option value="out">出</a-select-option>
              <a-select-option value="in">入</a-select-option>
            </a-select>
            <a-select
              v-model:value="draftFilters.method"
              class="strategy-page__filter-field"
              :placeholder="compactMode ? '策略' : '授权策略'"
              allow-clear
              @change="handleFilterChange"
            >
              <a-select-option value="accept">允许</a-select-option>
              <a-select-option value="drop">拒绝</a-select-option>
            </a-select>
            <a-select
              v-model:value="draftFilters.protocol"
              class="strategy-page__filter-field"
              :placeholder="compactMode ? '协议' : '协议类型'"
              allow-clear
              @change="handleFilterChange"
            >
              <a-select-option value="TCP">TCP</a-select-option>
              <a-select-option value="UDP">UDP</a-select-option>
              <a-select-option value="ICMP">ICMP</a-select-option>
            </a-select>
          </div>
          <div class="strategy-page__toolbar">
            <a-button
              class="strategy-page__toolbar-btn strategy-page__toolbar-btn--icon strategy-page__toolbar-btn--desktop-refresh"
              aria-label="刷新"
              :disabled="isActionLoading('strategy:reload')"
              @click="reload"
            >
              <LoadingOutlined v-if="isActionLoading('strategy:reload')" />
              <ReloadOutlined v-else />
            </a-button>
          </div>
        </div>
        <a-space class="strategy-page__toolbar-actions" :size="8">
          <a-button class="strategy-page__toolbar-btn" type="primary" @click="addRow">添加安全策略</a-button>
          <a-popconfirm
            :disabled="!selectedDeletableIds.length"
            :title="batchDeleteConfirmText"
            ok-text="确定"
            cancel-text="取消"
            @confirm="deleteSelected"
          >
            <a-button
              class="strategy-page__toolbar-btn"
              danger
              :disabled="!selectedDeletableIds.length || isActionLoading('strategy:delete:selected')"
            >
              <LoadingOutlined v-if="isActionLoading('strategy:delete:selected')" />
              <DeleteOutlined v-else />
              删除
            </a-button>
          </a-popconfirm>
          <a-button
            class="strategy-page__toolbar-btn strategy-page__toolbar-btn--icon strategy-page__toolbar-btn--mobile-refresh"
            aria-label="刷新"
            :disabled="isActionLoading('strategy:reload')"
            @click="reload"
          >
            <LoadingOutlined v-if="isActionLoading('strategy:reload')" />
            <ReloadOutlined v-else />
          </a-button>
        </a-space>
      </div>

      <div class="strategy-page__table-shell" :class="{ 'is-compact': compactMode }">
          <template v-if="compactMode">
            <template v-if="filteredRows.length">
              <transition-group
                name="resource-list"
                tag="div"
                class="strategy-page__mobile-list"
                appear
              >
                <article
                  v-for="record in compactRows"
                  :key="record.id"
                  class="strategy-page__mobile-card"
                  :class="{
                    'is-editing': activeEditRowId === record.id,
                    'is-closing': closingEditId === record.id,
                    'is-new': !!newRowMap[record.id],
                    'is-fresh': freshRowId === record.id,
                  }"
                >
                  <a-flex class="strategy-page__mobile-head" align="center" justify="space-between" :gap="12">
                    <a-flex class="strategy-page__mobile-head-main" align="center" :gap="10">
                      <a-checkbox
                        :checked="selectedRowKeys.includes(record.id)"
                        :disabled="!!newRowMap[record.id]"
                        @change="toggleRowSelection(record.id, $event.target.checked)"
                      />
                      <div class="strategy-page__mobile-tags">
                        <span class="strategy-page__pill">{{ protocolLabel(record.protocol) }}</span>
                        <span class="strategy-page__pill">{{ directionLabel(record.direction) }}</span>
                        <span
                          class="strategy-page__pill"
                          :class="record.method === 'drop' ? 'is-danger' : 'is-accept'"
                        >
                          {{ methodLabel(record.method) }}
                        </span>
                      </div>
                    </a-flex>
                  </a-flex>

                  <div class="strategy-page__mobile-content">
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
                        class="strategy-page__mobile-panel strategy-page__mobile-form"
                      >
                        <div class="strategy-page__mobile-field">
                          <div class="strategy-page__mobile-field-head">
                            <div class="strategy-page__mobile-label">规则方向</div>
                            <a-popover trigger="click" placement="topLeft" overlayClassName="strategy-page__field-tip-popover">
                              <template #title>{{ tips.direction.title }}</template>
                              <template #content>
                                <div class="strategy-page__field-tip-copy">{{ tips.direction.text }}</div>
                              </template>
                              <a-button class="strategy-page__field-help" type="text" shape="circle" size="small" aria-label="规则方向说明">
                                <InfoCircleOutlined />
                              </a-button>
                            </a-popover>
                          </div>
                          <a-select v-model:value="record.direction" @focus="focusKey = 'direction'">
                            <a-select-option value="out">出</a-select-option>
                            <a-select-option value="in">入</a-select-option>
                          </a-select>
                        </div>

                        <div class="strategy-page__mobile-field">
                          <div class="strategy-page__mobile-field-head">
                            <div class="strategy-page__mobile-label">授权策略</div>
                            <a-popover trigger="click" placement="topLeft" overlayClassName="strategy-page__field-tip-popover">
                              <template #title>{{ tips.method.title }}</template>
                              <template #content>
                                <div class="strategy-page__field-tip-copy">{{ tips.method.text }}</div>
                              </template>
                              <a-button class="strategy-page__field-help" type="text" shape="circle" size="small" aria-label="授权策略说明">
                                <InfoCircleOutlined />
                              </a-button>
                            </a-popover>
                          </div>
                          <a-select v-model:value="record.method" @focus="focusKey = 'method'">
                            <a-select-option value="accept">允许</a-select-option>
                            <a-select-option value="drop">拒绝</a-select-option>
                          </a-select>
                        </div>

                        <div class="strategy-page__mobile-field">
                          <div class="strategy-page__mobile-field-head">
                            <div class="strategy-page__mobile-label">协议类型</div>
                            <a-popover trigger="click" placement="topLeft" overlayClassName="strategy-page__field-tip-popover">
                              <template #title>{{ tips.protocol.title }}</template>
                              <template #content>
                                <div class="strategy-page__field-tip-copy">{{ tips.protocol.text }}</div>
                              </template>
                              <a-button class="strategy-page__field-help" type="text" shape="circle" size="small" aria-label="协议类型说明">
                                <InfoCircleOutlined />
                              </a-button>
                            </a-popover>
                          </div>
                          <a-select v-model:value="record.protocol" @focus="focusKey = 'protocol'">
                            <a-select-option value="TCP">TCP</a-select-option>
                            <a-select-option value="UDP">UDP</a-select-option>
                            <a-select-option value="ICMP">ICMP</a-select-option>
                          </a-select>
                        </div>

                        <div class="strategy-page__mobile-field">
                          <div class="strategy-page__mobile-field-head">
                            <div class="strategy-page__mobile-label">端口</div>
                            <a-popover trigger="click" placement="topLeft" overlayClassName="strategy-page__field-tip-popover">
                              <template #title>{{ tips.port.title }}</template>
                              <template #content>
                                <div class="strategy-page__field-tip-copy">{{ tips.port.text }}</div>
                              </template>
                              <a-button class="strategy-page__field-help" type="text" shape="circle" size="small" aria-label="端口说明">
                                <InfoCircleOutlined />
                              </a-button>
                            </a-popover>
                          </div>
                          <a-input v-model:value="record.start_port" placeholder="端口，-1 表示不限制" @focus="focusKey = 'port'" />
                        </div>

                        <div class="strategy-page__mobile-field">
                          <div class="strategy-page__mobile-field-head">
                            <div class="strategy-page__mobile-label">IP</div>
                            <a-popover trigger="click" placement="topLeft" overlayClassName="strategy-page__field-tip-popover">
                              <template #title>{{ tips.ip.title }}</template>
                              <template #content>
                                <div class="strategy-page__field-tip-copy">{{ tips.ip.text }}</div>
                              </template>
                              <a-button class="strategy-page__field-help" type="text" shape="circle" size="small" aria-label="IP 说明">
                                <InfoCircleOutlined />
                              </a-button>
                            </a-popover>
                          </div>
                          <a-input v-model:value="record.start_ip" placeholder="IP，0.0.0.0 表示不限制" @focus="focusKey = 'ip'" />
                        </div>

                        <div v-if="showPriorityField" class="strategy-page__mobile-field">
                          <div class="strategy-page__mobile-field-head">
                            <div class="strategy-page__mobile-label">优先级</div>
                            <a-popover trigger="click" placement="topLeft" overlayClassName="strategy-page__field-tip-popover">
                              <template #title>{{ tips.priority.title }}</template>
                              <template #content>
                                <div class="strategy-page__field-tip-copy">{{ tips.priority.text }}</div>
                              </template>
                              <a-button class="strategy-page__field-help" type="text" shape="circle" size="small" aria-label="优先级说明">
                                <InfoCircleOutlined />
                              </a-button>
                            </a-popover>
                          </div>
                          <a-input v-model:value="record.priority" placeholder="优先级 1-1000，数字越小越优先" @focus="focusKey = 'priority'" />
                        </div>

                        <a-space class="strategy-page__mobile-actions" :size="8">
                          <a-button
                            type="primary"
                            size="small"
                            :disabled="isActionLoading(`strategy:save:${record.id}`)"
                            :loading="isActionLoading(`strategy:save:${record.id}`)"
                            @click="save(record)"
                          >保存</a-button>
                          <a-button size="small" @click="cancel(record)">取消</a-button>
                        </a-space>
                      </div>

                      <div v-else :key="`mobile-view-${record.id}`" class="strategy-page__mobile-panel strategy-page__mobile-stage">
                        <div class="strategy-page__mobile-meta">
                          <div class="strategy-page__mobile-meta-item">
                            <span class="strategy-page__mobile-label">端口</span>
                            <span class="strategy-page__mobile-value">{{ record.start_port }}</span>
                          </div>
                          <div class="strategy-page__mobile-meta-item">
                            <span class="strategy-page__mobile-label">IP</span>
                            <span class="strategy-page__mobile-value">{{ record.start_ip }}</span>
                          </div>
                          <div class="strategy-page__mobile-meta-item">
                            <span class="strategy-page__mobile-label">优先级</span>
                            <span class="strategy-page__mobile-value">{{ priorityText(record) }}</span>
                          </div>
                        </div>

                        <a-space class="strategy-page__mobile-actions strategy-page__mobile-actions--tail" :size="8">
                          <a-button class="strategy-page__mobile-action-btn" type="link" size="small" @click="edit(record)">
                            编辑
                          </a-button>
                          <a-popconfirm :title="deleteConfirmText" @confirm="deleteItem(record)">
                            <a-button
                              class="strategy-page__mobile-action-btn"
                              type="link"
                              danger
                              size="small"
                              :disabled="isActionLoading(`strategy:delete:${record.id}`)"
                              :loading="isActionLoading(`strategy:delete:${record.id}`)"
                            >
                              删除
                            </a-button>
                          </a-popconfirm>
                        </a-space>
                      </div>
                    </transition>
                  </div>
                </article>
              </transition-group>

              <div v-if="showCompactPagination" class="strategy-page__mobile-pagination">
                <a-pagination
                  :current="currentPage"
                  :page-size="pageSize"
                  :page-size-options="pageSizeOptions"
                  :total="filteredRows.length"
                  show-size-changer
                  :show-total="(total) => `共 ${total} 条`"
                  size="small"
                  @change="handlePageChange"
                  @showSizeChange="handlePageChange"
                />
              </div>
            </template>
            <a-empty v-else class="strategy-page__empty" description="暂无安全策略数据" />
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
              :scroll="{ x: 960 }"
              rowKey="id"
              size="middle"
              tableLayout="fixed"
              class="strategy-page__table"
            >
              <template #expandedRowRender="{ record }">
                <transition name="strategy-note-panel" appear>
                  <div v-if="record.id === noteVisibleId" class="strategy-page__note-stage">
                    <transition name="strategy-note-swap">
                      <a-flex :key="focusKey" class="strategy-page__note" align="center" :gap="18">
                        <div class="strategy-page__note-icon" aria-hidden="true">
                          <component :is="currentTip.icon" />
                        </div>
                        <div class="strategy-page__note-copy">
                          <div class="strategy-page__note-title">{{ currentTip.title }}</div>
                          <div class="strategy-page__note-text">{{ currentTip.text }}</div>
                        </div>
                      </a-flex>
                    </transition>
                  </div>
                </transition>
              </template>

              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'protocol'">
                  <div class="strategy-cell-stack">
                    <transition :name="editTransitionName">
                      <a-select
                        v-if="shouldRenderRowEditor(record)"
                        :key="`edit-protocol-${record.id}`"
                        v-model:value="record.protocol"
                        class="strategy-cell-view strategy-cell-control"
                        @focus="focusKey = 'protocol'"
                      >
                        <a-select-option value="TCP">TCP</a-select-option>
                        <a-select-option value="UDP">UDP</a-select-option>
                        <a-select-option value="ICMP">ICMP</a-select-option>
                      </a-select>
                      <span v-else :key="`view-protocol-${record.id}`" class="strategy-cell-view strategy-cell-text">
                        <span class="strategy-page__pill">{{ protocolLabel(record.protocol) }}</span>
                      </span>
                    </transition>
                  </div>
                </template>

                <template v-else-if="column.key === 'direction'">
                  <div class="strategy-cell-stack">
                    <transition :name="editTransitionName">
                      <a-select
                        v-if="shouldRenderRowEditor(record)"
                        :key="`edit-direction-${record.id}`"
                        v-model:value="record.direction"
                        class="strategy-cell-view strategy-cell-control"
                        @focus="focusKey = 'direction'"
                      >
                        <a-select-option value="out">出</a-select-option>
                        <a-select-option value="in">入</a-select-option>
                      </a-select>
                      <span v-else :key="`view-direction-${record.id}`" class="strategy-cell-view strategy-cell-text">
                        <span class="strategy-page__pill">{{ directionLabel(record.direction) }}</span>
                      </span>
                    </transition>
                  </div>
                </template>

                <template v-else-if="column.key === 'method'">
                  <div class="strategy-cell-stack">
                    <transition :name="editTransitionName">
                      <a-select
                        v-if="shouldRenderRowEditor(record)"
                        :key="`edit-method-${record.id}`"
                        v-model:value="record.method"
                        class="strategy-cell-view strategy-cell-control"
                        @focus="focusKey = 'method'"
                      >
                        <a-select-option value="accept">允许</a-select-option>
                        <a-select-option value="drop">拒绝</a-select-option>
                      </a-select>
                      <span v-else :key="`view-method-${record.id}`" class="strategy-cell-view strategy-cell-text">
                        <span
                          class="strategy-page__pill"
                          :class="record.method === 'drop' ? 'is-danger' : 'is-accept'"
                        >
                          {{ methodLabel(record.method) }}
                        </span>
                      </span>
                    </transition>
                  </div>
                </template>

                <template v-else-if="column.key === 'start_port'">
                  <div class="strategy-cell-stack">
                    <transition :name="editTransitionName">
                      <a-input
                        v-if="shouldRenderRowEditor(record)"
                        :key="`edit-port-${record.id}`"
                        v-model:value="record.start_port"
                        class="strategy-cell-view strategy-cell-control"
                        placeholder="-1"
                        @focus="focusKey = 'port'"
                      />
                      <span v-else :key="`view-port-${record.id}`" class="strategy-cell-view strategy-cell-text">
                        {{ record.start_port }}
                      </span>
                    </transition>
                  </div>
                </template>

                <template v-else-if="column.key === 'start_ip'">
                  <div class="strategy-cell-stack">
                    <transition :name="editTransitionName">
                      <a-input
                        v-if="shouldRenderRowEditor(record)"
                        :key="`edit-ip-${record.id}`"
                        v-model:value="record.start_ip"
                        class="strategy-cell-view strategy-cell-control"
                        placeholder="0.0.0.0"
                        @focus="focusKey = 'ip'"
                      />
                      <span v-else :key="`view-ip-${record.id}`" class="strategy-cell-view strategy-cell-text strategy-page__ip-text">
                        {{ record.start_ip }}
                      </span>
                    </transition>
                  </div>
                </template>

                <template v-else-if="column.key === 'priority'">
                  <div class="strategy-cell-stack">
                    <transition :name="editTransitionName">
                      <a-input
                        v-if="shouldRenderPriorityEditor(record)"
                        :key="`edit-priority-${record.id}`"
                        v-model:value="record.priority"
                        class="strategy-cell-view strategy-cell-control"
                        placeholder="自动"
                        @focus="focusKey = 'priority'"
                      />
                      <span v-else :key="`view-priority-${record.id}`" class="strategy-cell-view strategy-cell-text">
                        {{ priorityText(record) }}
                      </span>
                    </transition>
                  </div>
                </template>

                <template v-else-if="column.key === 'actions'">
                  <div class="strategy-cell-stack">
                    <transition :name="editTransitionName">
                      <a-space
                        v-if="shouldRenderRowEditor(record)"
                        :key="`edit-actions-${record.id}`"
                        class="strategy-cell-view strategy-cell-control strategy-page__inline-actions"
                        :size="8"
                      >
                        <a-button
                          type="primary"
                          size="small"
                          :disabled="isActionLoading(`strategy:save:${record.id}`)"
                          :loading="isActionLoading(`strategy:save:${record.id}`)"
                          @click="save(record)"
                        >保存</a-button>
                        <a-button size="small" @click="cancel(record)">取消</a-button>
                      </a-space>
                      <a-space
                        v-else
                        :key="`view-actions-${record.id}`"
                        class="strategy-cell-view strategy-cell-text strategy-page__inline-actions"
                        :size="4"
                      >
                        <a-button type="link" size="small" @click="edit(record)">编辑</a-button>
                        <a-popconfirm :title="deleteConfirmText" @confirm="deleteItem(record)">
                          <a-button
                            type="link"
                            danger
                            size="small"
                            :disabled="isActionLoading(`strategy:delete:${record.id}`)"
                            :loading="isActionLoading(`strategy:delete:${record.id}`)"
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
  ApiOutlined,
  DeleteOutlined,
  FieldNumberOutlined,
  GlobalOutlined,
  InfoCircleOutlined,
  LoadingOutlined,
  ReloadOutlined,
  SafetyCertificateOutlined,
  SwapOutlined,
  TagsOutlined,
} from '@ant-design/icons-vue';
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { message } from 'ant-design-vue';
import { useDashboardStore } from '@/stores/dashboard';
import ResourcePageHero from '@/components/pages/ResourcePageHero.vue';
import { useCompactPageMode } from '@/components/pages/useCompactPageMode';
import { useActionLocks } from '@/composables/useActionLocks';
import strategyHeroImage from '@/assets/page-hero/strategy-hero.png';

const props = defineProps({
  page: {
    type: Object,
    default: () => ({}),
  },
});

const EDIT_CLOSE_DELAY = 220;
const NOTE_EXIT_LEAD = 120;

const store = useDashboardStore();
const { pageRootRef, compactMode } = useCompactPageMode();
const { isActionLoading, runWithActionLoading } = useActionLocks();
const rows = ref([]);
const freshRowId = ref(null);
const editingId = ref(null);
const closingEditId = ref(null);
const noteVisibleId = ref(null);
const selectedRowKeys = ref([]);
const newRowMap = ref({});
const editSnapshotMap = ref({});
const editMotionDirection = ref('open');
const focusKey = ref('direction');
const currentPage = ref(1);
const pageSize = ref(10);
const draftFilters = reactive({
  direction: undefined,
  method: undefined,
  protocol: undefined,
});
const appliedFilters = ref({
  direction: undefined,
  method: undefined,
  protocol: undefined,
});

let freshRowTimer = null;
let deferExternalRowsSync = false;
let pendingExternalRowsSync = false;

const totalCount = computed(() => rows.value.length);
const quotaTotal = computed(() => '∞');
const strategyPercent = computed(() => 0);
const allowCount = computed(() => rows.value.filter((row) => row.method === 'accept').length);
const dropCount = computed(() => rows.value.filter((row) => row.method === 'drop').length);
const heroStatCards = computed(() => ([
  { label: '允许', value: allowCount.value },
  { label: '拒绝', value: dropCount.value },
]));
const heroCompactSummaryItems = computed(() => ([
  { label: '创建策略数', value: `${totalCount.value} / ${quotaTotal.value}` },
]));
const showPriorityField = computed(() => String(store.host?.virtualType || '').trim().toLowerCase() !== 'kvm');
const deleteConfirmText = '确定删除该防火墙吗？';
const batchDeleteConfirmText = computed(() => `确定删除选中的 ${selectedDeletableIds.value.length} 条防火墙吗？`);
const activeEditRowId = computed(() => editingId.value || closingEditId.value || null);
const editTransitionName = computed(() => (editMotionDirection.value === 'close' ? 'strategy-edit-close' : 'strategy-edit-open'));
const expandedRowKeys = computed(() => (noteVisibleId.value ? [noteVisibleId.value] : []));
const tips = {
  direction: {
    title: '规则方向',
    text: '入站控制外部访问云主机，出站控制云主机访问外部；新增策略先确认方向，再看端口和 IP 范围。',
    icon: SwapOutlined,
  },
  method: {
    title: '授权策略',
    text: '允许会放行命中流量，拒绝会拦截命中流量；拒绝规则建议配合更高优先级使用，避免误伤业务访问。',
    icon: SafetyCertificateOutlined,
  },
  protocol: {
    title: '协议类型',
    text: 'TCP/UDP 适合指定业务端口，ICMP 用于连通性探测；宽泛协议规则适合兜底，但需要更谨慎。',
    icon: ApiOutlined,
  },
  port: {
    title: '端口',
    text: '填写单个端口或 -1；-1 表示不限制端口，适合配合明确 IP 段或兜底策略使用。',
    icon: FieldNumberOutlined,
  },
  ip: {
    title: 'IP',
    text: '0.0.0.0 表示不限制来源或目标，CIDR 网段可用于一组地址；公网开放规则建议尽量收窄。',
    icon: GlobalOutlined,
  },
  priority: {
    title: '优先级',
    text: '数字越小越先匹配；新增时会自动给一个靠后的值，需要覆盖已有规则时再手动调整。',
    icon: TagsOutlined,
  },
};
const currentTip = computed(() => tips[focusKey.value] || tips.direction);
const pageSizeOptions = ['10', '20', '50'];
const tablePagination = computed(() => ({
  size: 'small',
  current: currentPage.value,
  pageSize: pageSize.value,
  total: filteredRows.value.length,
  showSizeChanger: true,
  pageSizeOptions,
  showTotal: (total) => `共 ${total} 条`,
  onChange: handlePageChange,
  onShowSizeChange: handlePageChange,
}));
const columns = [
  { title: '协议类型', dataIndex: 'protocol', key: 'protocol', width: 132 },
  { title: '方向', dataIndex: 'direction', key: 'direction', width: 104 },
  { title: '策略', dataIndex: 'method', key: 'method', width: 120 },
  { title: '端口', dataIndex: 'start_port', key: 'start_port', width: 124 },
  { title: 'IP', dataIndex: 'start_ip', key: 'start_ip', width: 248 },
  { title: '优先级', dataIndex: 'priority', key: 'priority', width: 110 },
  { title: '操作', key: 'actions', width: 140 },
];
const filteredRows = computed(() => rows.value.filter((row) => {
  if (appliedFilters.value.direction && String(row.direction) !== appliedFilters.value.direction) {
    return false;
  }

  if (appliedFilters.value.method && String(row.method) !== appliedFilters.value.method) {
    return false;
  }

  if (appliedFilters.value.protocol && String(row.protocol) !== appliedFilters.value.protocol) {
    return false;
  }

  return true;
}));
const compactRows = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value;
  return filteredRows.value.slice(start, start + pageSize.value);
});
const showCompactPagination = computed(() => filteredRows.value.length > 0);
const selectedDeletableIds = computed(() => {
  const currentIds = new Set(rows.value.filter((row) => !newRowMap.value[row.id]).map((row) => row.id));
  return selectedRowKeys.value.filter((id) => currentIds.has(id));
});
const rowSelection = computed(() => ({
  selectedRowKeys: selectedRowKeys.value,
  onChange: (keys) => {
    selectedRowKeys.value = keys;
  },
  getCheckboxProps: (record) => ({
    disabled: !!newRowMap.value[record.id],
  }),
}));

function normalizeProtocol(value) {
  const nextValue = String(value || '').trim().toUpperCase();
  return ['ANY', 'TCP', 'UDP', 'ICMP'].includes(nextValue) ? nextValue : 'TCP';
}

function normalizeDirection(value) {
  const nextValue = String(value || '').trim().toLowerCase();
  if (nextValue === '出') {
    return 'out';
  }
  if (nextValue === '入') {
    return 'in';
  }
  return ['in', 'out'].includes(nextValue) ? nextValue : 'in';
}

function normalizeMethod(value) {
  const nextValue = String(value || '').trim().toLowerCase();
  if (nextValue === '拒绝') {
    return 'drop';
  }
  if (nextValue === '允许') {
    return 'accept';
  }
  return ['accept', 'drop'].includes(nextValue) ? nextValue : 'accept';
}

function toViewRow(item) {
  return {
    id: item?.id,
    protocol: normalizeProtocol(item?.protocol),
    direction: normalizeDirection(item?.direction),
    method: normalizeMethod(item?.method),
    start_port: String(item?.start_port ?? item?.port ?? '-1'),
    start_ip: String(item?.start_ip ?? item?.ip ?? '0.0.0.0'),
    priority: String(item?.priority ?? ''),
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

function protocolLabel(value) {
  return value === 'ANY' ? '全部' : String(value || '');
}

function directionLabel(value) {
  return String(value) === 'out' ? '出' : '入';
}

function methodLabel(value) {
  return String(value) === 'drop' ? '拒绝' : '允许';
}

function priorityText(record) {
  return String(record?.priority ?? '').trim() || '自动';
}

function rowClassName(record) {
  return [
    record.id === activeEditRowId.value ? 'is-editing' : '',
    newRowMap.value[record.id] ? 'strategy-row--new' : '',
    record.id === freshRowId.value ? 'strategy-row--fresh' : '',
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

function shouldRenderPriorityEditor(record) {
  return showPriorityField.value && shouldRenderRowEditor(record);
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
  return container?.classList.contains('strategy-page__mobile-content') ? container : null;
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

  const { afterClose, closeDelay = compactMode.value ? EDIT_CLOSE_DELAY : 180 } = options;
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
  focusKey.value = 'direction';
  newRowMap.value = {};
  editSnapshotMap.value = {};
  editMotionDirection.value = 'open';
  deferExternalRowsSync = false;
  pendingExternalRowsSync = false;
}

function restoreDraftFilters() {
  draftFilters.direction = appliedFilters.value.direction;
  draftFilters.method = appliedFilters.value.method;
  draftFilters.protocol = appliedFilters.value.protocol;
}

function handleFilterChange() {
  if (activeEditRowId.value) {
    message.info('请先完成当前编辑中的条目');
    restoreDraftFilters();
    return;
  }

  appliedFilters.value = {
    direction: draftFilters.direction,
    method: draftFilters.method,
    protocol: draftFilters.protocol,
  };
  currentPage.value = 1;
  selectedRowKeys.value = [];
}

function nextPriority() {
  const priorities = rows.value
    .map((row) => Number(row.priority))
    .filter((value) => Number.isInteger(value));

  return String(Math.max(90, ...priorities) + 10);
}

function createDraftRow() {
  return {
    id: `new_${Date.now()}`,
    protocol: appliedFilters.value.protocol || 'TCP',
    direction: appliedFilters.value.direction || 'in',
    method: appliedFilters.value.method || 'accept',
    start_port: '-1',
    start_ip: '0.0.0.0',
    priority: showPriorityField.value ? nextPriority() : '',
  };
}

function addRow() {
  if (activeEditRowId.value) {
    message.info('请先完成当前编辑中的条目');
    return;
  }

  const row = createDraftRow();
  rows.value.unshift(row);
  newRowMap.value[row.id] = true;
  editMotionDirection.value = 'open';
  editingId.value = row.id;
  focusKey.value = 'direction';
  currentPage.value = 1;
  selectedRowKeys.value = [];
  scheduleNoteOpen(row.id);
}

function handlePageChange(page, size) {
  if (activeEditRowId.value) {
    message.info('请先完成当前编辑中的条目');
    return;
  }

  currentPage.value = page;
  pageSize.value = Number(size) || 10;
  clampCurrentPage();
}

function edit(record) {
  if (activeEditRowId.value) {
    message.info('请先完成当前编辑中的条目');
    return;
  }

  rememberEditSnapshot(record);
  editMotionDirection.value = 'open';
  editingId.value = record.id;
  focusKey.value = 'direction';
  scheduleNoteOpen(record.id);
}

async function cancel(record) {
  if (record && newRowMap.value[record.id]) {
    await closeEditing(record.id, {
      closeDelay: compactMode.value ? 180 : 180,
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

function buildRuleInput(record) {
  const direction = normalizeDirection(record?.direction);
  const method = normalizeMethod(record?.method);
  const protocol = normalizeProtocol(record?.protocol);
  const port = String(record?.start_port ?? '').trim();
  const ip = String(record?.start_ip ?? '').trim();
  const priority = String(record?.priority ?? '').trim();

  if (!port) {
    message.warning('端口不能为空');
    return null;
  }

  if (!ip) {
    message.warning('IP 不能为空');
    return null;
  }

  if (showPriorityField.value && priority && !/^\d+$/.test(priority)) {
    message.warning('优先级必须是数字');
    return null;
  }

  const input = {
    direction,
    method,
    protocol,
    port,
    ip,
  };

  if (showPriorityField.value) {
    input.priority = priority;
  }

  return input;
}

async function save(record) {
  const input = buildRuleInput(record);
  if (!input) {
    return;
  }

  await runWithActionLoading(`strategy:save:${record.id}`, async () => {
    try {
      if (newRowMap.value[record.id]) {
        deferExternalRowsSync = true;
        const rowsAfterCreate = await store.createFirewallRule(input);
        delete newRowMap.value[record.id];
        await closeEditing(record.id, {
          afterClose: () => {
            flushDeferredRowSync(true);
            markFreshRow(rowsAfterCreate?.[0]?.id);
          },
        });
        message.success('安全策略已添加');
        return;
      }

      const originalId = record.id;
      const knownIds = new Set(rows.value.map((row) => String(row.id)));
      deferExternalRowsSync = true;
      const rowsAfterCreate = await store.createFirewallRule(input);
      const createdRow = (rowsAfterCreate || []).find((row) => !knownIds.has(String(row?.id)));
      await store.deleteFirewallRule(originalId);
      await closeEditing(originalId, {
        afterClose: () => {
          flushDeferredRowSync(true);
          markFreshRow(createdRow?.id);
        },
      });
      message.success('安全策略已修改');
    } catch (error) {
      flushDeferredRowSync();
      message.error(error instanceof Error ? error.message : '保存失败');
    }
  });
}

async function reload() {
  await runWithActionLoading('strategy:reload', async () => {
    try {
      resetEditingState();
      await store.refreshFirewallRules();
      syncRows(true);
      pruneSelectedRows();
    } catch (error) {
      message.error(error instanceof Error ? error.message : '刷新失败');
    }
  });
}

async function deleteItem(record) {
  if (activeEditRowId.value && activeEditRowId.value !== record.id) {
    message.info('请先完成当前编辑中的条目');
    return;
  }

  await runWithActionLoading(`strategy:delete:${record.id}`, async () => {
    try {
      await store.deleteFirewallRule(record.id);
      selectedRowKeys.value = selectedRowKeys.value.filter((id) => id !== record.id);
      syncRows(true);
      message.success('安全策略已删除');
    } catch (error) {
      message.error(error instanceof Error ? error.message : '删除失败');
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

  await runWithActionLoading('strategy:delete:selected', async () => {
    try {
      await store.deleteFirewallRules(ids);
      selectedRowKeys.value = [];
      syncRows(true);
      message.success(`已删除 ${ids.length} 条安全策略`);
    } catch (error) {
      message.error(error instanceof Error ? error.message : '批量删除失败');
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
  },
);

watch(
  () => [filteredRows.value.length, pageSize.value],
  () => {
    clampCurrentPage();
  },
);

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
.strategy-page {
  width: 100%;
}

.strategy-page__panel-shell {
  position: relative;
  z-index: 2;
  margin: 0 8px 12px;
  overflow: hidden;
}

.strategy-page__panel-shell :deep(.ant-card-body) {
  padding: 18px;
}

.strategy-page__header-shell {
  margin: 8px 8px 24px;
}

.strategy-page__header {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 54px;
}

.strategy-page__title {
  padding-left: 8px;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-hero-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-page);
}

.strategy-page__summary {
  margin-left: auto;
  min-height: 42px;
  padding: 0 14px;
  border: 1px solid var(--mmui-shell-border);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.02);
}

.strategy-page__summary-label {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.strategy-page__summary-value {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.strategy-page__summary-help {
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

.strategy-page__summary-help :deep(.anticon) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.strategy-page__filters-shell {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin: 0 0 16px;
}

.strategy-page__filters-main {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
  flex: 1 1 auto;
}

.strategy-page__filters-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 160px));
  gap: 12px;
  flex: 0 1 auto;
}

.strategy-page__toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.strategy-page__toolbar-actions {
  display: inline-flex;
  align-items: center;
  margin-left: auto;
  flex-shrink: 0;
}

.strategy-page__toolbar-btn {
  border-radius: 10px;
}

.strategy-page__toolbar-btn--icon {
  width: 40px;
  padding-inline: 0;
}

.strategy-page__toolbar-btn--mobile-refresh {
  display: none;
}

.strategy-page__table-shell {
  margin: 0;
  overflow: hidden;
  border-top: 1px solid var(--mmui-shell-border);
}

.strategy-page__mobile-list {
  position: relative;
  display: block;
  padding: 0;
  border: 0;
  background: transparent;
}

.strategy-page__mobile-card {
  position: relative;
  max-height: 620px;
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

.strategy-page__mobile-card + .strategy-page__mobile-card {
  border-top: 1px solid var(--mmui-shell-border);
}

.strategy-page__mobile-card.is-editing,
.strategy-page__mobile-card.is-fresh {
  background: var(--mmui-sidebar-hover);
}

.strategy-page__mobile-card.is-editing {
  animation: strategy-edit-activate 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}

.strategy-page__mobile-card.is-closing {
  pointer-events: none;
}

.strategy-page__mobile-card.is-new {
  background: var(--mmui-sidebar-hover);
  animation: strategy-row-reveal 0.56s cubic-bezier(0.2, 0.84, 0.24, 1);
}

.strategy-page__mobile-card.is-new::before {
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
  animation: strategy-card-sheen 0.88s ease-out 0.1s both;
}

.strategy-page__mobile-head {
  display: flex !important;
  align-items: center;
  justify-content: flex-start;
  gap: 12px;
  margin-bottom: 12px;
  flex-wrap: nowrap;
}

.strategy-page__mobile-head-main {
  display: flex !important;
  align-items: center;
  gap: 10px;
  min-width: 0;
  flex: 1 1 auto;
}

.strategy-page__mobile-tags {
  display: flex;
  flex-wrap: nowrap;
  gap: 8px;
  min-width: 0;
  overflow: hidden;
}

.strategy-page__pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 28px;
  padding: 0 10px;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-caption);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-caption);
  white-space: nowrap;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.06);
}

.strategy-page__pill.is-accept {
  color: var(--mmui-accent-blue);
  background: rgba(var(--mmui-accent-blue-rgb), 0.12);
}

.strategy-page__pill.is-danger {
  color: #ff6b72;
  background: rgba(255, 107, 114, 0.12);
}

.strategy-page__mobile-content {
  position: relative;
  display: grid;
  align-items: start;
  overflow: hidden;
  transition: height 0.34s cubic-bezier(0.22, 1, 0.36, 1);
  will-change: height;
}

.strategy-page__mobile-panel {
  grid-area: 1 / 1;
  min-width: 0;
}

.strategy-page__mobile-form {
  display: grid;
  gap: 12px;
}

.strategy-page__mobile-stage {
  display: grid;
  gap: 0;
}

.strategy-page__mobile-field {
  display: grid;
  gap: 6px;
}

.strategy-page__mobile-field-head {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  width: fit-content;
}

.strategy-page__field-help {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  min-width: 22px;
  height: 22px;
  padding: 0;
  color: var(--mmui-text-muted) !important;
}

.strategy-page__field-help:hover {
  color: var(--mmui-accent-blue) !important;
}

:deep(.strategy-page__field-tip-popover .ant-popover-inner) {
  max-width: 260px;
  border-radius: 12px;
}

.strategy-page__field-tip-copy {
  max-width: 220px;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.strategy-page__mobile-meta {
  display: flex;
  align-items: center;
  min-width: 0;
  gap: 0;
}

.strategy-page__mobile-meta-item {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  min-width: 0;
  gap: 6px;
  padding-right: 12px;
}

.strategy-page__mobile-meta-item:first-child {
  flex: 0.72 1 0;
  min-width: min-content;
}

.strategy-page__mobile-meta-item:nth-child(2) {
  flex: 1.8 1 0;
  min-width: 0;
}

.strategy-page__mobile-meta-item:nth-child(3) {
  flex: 0.9 0 0;
  min-width: max-content;
  justify-content: space-between;
  padding-right: 0;
}

.strategy-page__mobile-meta-item:nth-child(3) .strategy-page__mobile-value {
  margin-left: auto;
  justify-content: flex-end;
  text-align: right;
}

.strategy-page__mobile-meta-item + .strategy-page__mobile-meta-item {
  padding-left: 12px;
}

.strategy-page__mobile-meta-item + .strategy-page__mobile-meta-item::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  width: 1px;
  height: 20px;
  background: var(--mmui-shell-border);
  transform: translateY(-50%);
}

.strategy-page__mobile-label {
  flex: 0 0 auto;
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
  white-space: nowrap;
}

.strategy-page__mobile-value {
  display: inline-flex;
  align-items: center;
  justify-content: flex-start;
  flex: 0 0 auto;
  min-width: 0;
  color: var(--mmui-text);
  overflow: hidden;
  font-size: var(--mmui-font-size-body);
  line-height: var(--mmui-line-height-body);
  text-align: left;
  text-overflow: ellipsis;
  white-space: nowrap;
  word-break: normal;
}

.strategy-page__mobile-actions {
  display: flex !important;
  width: min-content;
  max-width: 100%;
  margin-top: 16px;
  margin-left: auto;
  justify-content: flex-end;
}

.strategy-page__mobile-actions--tail {
  margin-top: 10px;
  white-space: nowrap;
}

.strategy-page__mobile-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding-inline: 0 !important;
  gap: 2px;
}

.strategy-page__mobile-action-btn.ant-btn-sm {
  height: 32px;
}

.strategy-page__mobile-pagination {
  display: flex;
  justify-content: center;
  margin: 20px 0 0;
}

.strategy-page__mobile-pagination :deep(.ant-pagination) {
  margin: 0;
}

.strategy-page__empty {
  padding: 26px 0;
}

.strategy-page__table {
  border: 0;
}

.strategy-page__table :deep(.ant-table-pagination.ant-pagination) {
  margin: 20px 0 0;
}

.strategy-page__table :deep(.ant-pagination-total-text),
.strategy-page__mobile-pagination :deep(.ant-pagination-total-text) {
  margin-right: auto;
}

.strategy-page__table :deep(.ant-table),
.strategy-page__table :deep(.ant-table-container),
.strategy-page__table :deep(.ant-table-content),
.strategy-page__table :deep(.ant-table-header),
.strategy-page__table :deep(.ant-table-body) {
  border-radius: 10px;
}

.strategy-page__table :deep(.ant-table) {
  background: transparent;
  box-shadow: none;
}

.strategy-page__table :deep(.ant-table-container::before),
.strategy-page__table :deep(.ant-table-container::after) {
  display: none;
}

.strategy-page__table :deep(.ant-table-content table),
.strategy-page__table :deep(.ant-table-header table),
.strategy-page__table :deep(.ant-table-body table) {
  width: max(100%, 960px) !important;
  min-width: 960px;
}

.strategy-page__table :deep(.ant-table-thead > tr > th) {
  height: 66px;
  padding: 0 12px;
  color: var(--mmui-card-title);
  white-space: nowrap;
  background: rgba(255, 255, 255, 0.04);
  border-bottom: 1px solid var(--mmui-shell-border);
}

.strategy-page__table :deep(.ant-table-tbody > tr > td) {
  padding: 16px 12px;
  border-bottom: 1px solid var(--mmui-shell-border);
}

.strategy-page__table :deep(.ant-table-cell-row-hover) {
  background: transparent !important;
}

.strategy-page__table :deep(.ant-table-tbody > tr:hover > td) {
  background: color-mix(in srgb, var(--mmui-card-surface) 94%, #ffffff 6%) !important;
}

.strategy-page__table :deep(.ant-table-expanded-row > td) {
  padding: 0 !important;
  background: transparent !important;
}

.strategy-page__table :deep(.is-editing > td) {
  background: var(--mmui-sidebar-hover) !important;
  animation: strategy-edit-activate 0.28s cubic-bezier(0.22, 1, 0.36, 1);
  transition: background-color 0.24s ease;
}

.strategy-page__table :deep(.strategy-row--new > td) {
  background: var(--mmui-sidebar-hover) !important;
  animation: strategy-row-reveal 0.56s cubic-bezier(0.2, 0.84, 0.24, 1);
  box-shadow: none;
  will-change: transform, opacity;
}

.strategy-page__table :deep(.strategy-row--new > td:first-child) {
  box-shadow: none;
}

.strategy-page__table :deep(.strategy-row--new > td:last-child) {
  box-shadow: none;
}

.strategy-page__table :deep(.strategy-row--fresh > td) {
  background: var(--mmui-sidebar-hover) !important;
  animation: strategy-desktop-row-settle 360ms cubic-bezier(0.2, 0.84, 0.24, 1);
}

.strategy-page__note-stage {
  position: relative;
  display: grid;
  overflow: hidden;
  padding: 0 8px 8px;
  transform-origin: top center;
}

.strategy-page__note {
  display: flex;
  align-items: center;
  gap: 18px;
  padding: 26px 18px 18px 12px;
}

.strategy-page__note-copy {
  min-width: 0;
  flex: 1 1 auto;
}

.strategy-page__note-icon {
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

.strategy-page__note-icon :deep(.anticon),
.strategy-page__note-icon :deep(svg) {
  font-size: 22px;
}

.strategy-page__note-title {
  margin-bottom: 6px;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.strategy-page__note-text {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-body);
  line-height: var(--mmui-line-height-body);
}

.strategy-page__note-stage > .strategy-page__note {
  grid-area: 1 / 1;
}

.strategy-page__table :deep(.ant-input),
.strategy-page__table :deep(.ant-select-selector) {
  border-radius: 10px;
  box-shadow: none;
}

.strategy-page__table :deep(.is-editing .ant-input),
.strategy-page__table :deep(.is-editing .ant-select-selector) {
  background: var(--mmui-card-action-bg) !important;
}

.strategy-page__table :deep(.ant-btn-sm) {
  height: 32px;
  min-width: 56px;
  padding: 0 12px;
  border-radius: 10px;
}

.strategy-cell-stack {
  display: grid;
  align-items: center;
  width: 100%;
  min-width: 0;
  min-height: 32px;
  overflow: hidden;
}

.strategy-cell-stack > * {
  grid-area: 1 / 1;
  min-width: 0;
}

.strategy-cell-view {
  width: 100%;
  min-width: 0;
}

.strategy-cell-text {
  display: inline-flex;
  align-items: center;
  min-height: 32px;
  padding-inline: 12px;
  box-sizing: border-box;
  opacity: 1;
}

.strategy-cell-control {
  min-width: 0;
}

.strategy-page__ip-text {
  word-break: break-all;
}

.strategy-page__inline-actions {
  align-items: center;
  width: auto;
  max-width: 100%;
  padding-inline: 0;
  justify-self: start;
}

.strategy-note-panel-enter-active {
  transition:
    max-height 0.34s cubic-bezier(0.22, 1, 0.36, 1),
    padding-bottom 0.34s cubic-bezier(0.22, 1, 0.36, 1),
    opacity 0.2s ease-out,
    transform 0.24s ease-out;
  will-change: max-height, padding-bottom, opacity, transform;
}

.strategy-note-panel-leave-active {
  transition:
    max-height 0.28s cubic-bezier(0.22, 1, 0.36, 1),
    padding-bottom 0.28s cubic-bezier(0.22, 1, 0.36, 1),
    opacity 0.18s ease-out,
    transform 0.2s ease-out;
  will-change: max-height, padding-bottom, opacity, transform;
}

.strategy-note-panel-enter-from,
.strategy-note-panel-leave-to {
  max-height: 0;
  padding-bottom: 0;
  opacity: 0;
  transform: translateY(-6px);
}

.strategy-note-panel-enter-to,
.strategy-note-panel-leave-from {
  max-height: 180px;
  padding-bottom: 8px;
  opacity: 1;
  transform: translateY(0);
}

.strategy-note-swap-enter-active,
.strategy-note-swap-leave-active {
  transition: opacity 0.2s ease-out, transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), filter 0.2s ease-out;
  transform-origin: left center;
}

.strategy-note-swap-enter-from {
  opacity: 0;
  filter: blur(3px);
  transform: translateX(14px);
}

.strategy-note-swap-leave-to {
  opacity: 0;
  filter: blur(2px);
  transform: translateX(-10px);
}

.strategy-edit-open-enter-active,
.strategy-edit-open-leave-active,
.strategy-edit-close-enter-active,
.strategy-edit-close-leave-active {
  transition:
    transform 220ms cubic-bezier(0.22, 1, 0.36, 1),
    opacity 180ms ease-out,
    filter 180ms ease-out;
  will-change: transform, opacity, filter;
}

.strategy-edit-open-enter-from.strategy-page__mobile-panel,
.strategy-edit-close-leave-to.strategy-page__mobile-panel {
  opacity: 0;
  filter: blur(2px);
  transform: translateY(10px) scale(0.992);
}

.strategy-edit-open-enter-to.strategy-page__mobile-panel,
.strategy-edit-close-leave-from.strategy-page__mobile-panel,
.strategy-edit-open-leave-from.strategy-page__mobile-panel,
.strategy-edit-close-enter-to.strategy-page__mobile-panel {
  opacity: 1;
  filter: blur(0);
  transform: translateY(0) scale(1);
}

.strategy-edit-open-leave-to.strategy-page__mobile-panel,
.strategy-edit-close-enter-from.strategy-page__mobile-panel {
  opacity: 0;
  filter: blur(1.5px);
  transform: translateY(-6px) scale(0.996);
}

.strategy-edit-open-enter-active.strategy-cell-control,
.strategy-edit-close-leave-active.strategy-cell-control {
  z-index: 2;
}

.strategy-edit-open-leave-active.strategy-cell-text,
.strategy-edit-close-enter-active.strategy-cell-text {
  z-index: 1;
}

.strategy-edit-open-enter-from.strategy-cell-control {
  opacity: 0;
  filter: blur(1.5px);
  transform: translateX(4px) scale(0.998);
}

.strategy-edit-open-enter-to.strategy-cell-control,
.strategy-edit-close-leave-from.strategy-cell-control {
  opacity: 1;
  filter: blur(0);
  transform: translateX(0) scale(1);
}

.strategy-edit-open-leave-from.strategy-cell-text,
.strategy-edit-close-enter-to.strategy-cell-text {
  opacity: 1;
  filter: none;
  transform: translateX(0);
}

.strategy-edit-open-leave-to.strategy-cell-text,
.strategy-edit-close-enter-from.strategy-cell-text {
  opacity: 0;
  filter: none;
  transform: translateX(-4px);
}

.strategy-edit-close-leave-to.strategy-cell-control {
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

@keyframes strategy-card-appear {
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

@keyframes strategy-card-sheen {
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

@keyframes strategy-row-reveal {
  0% {
    opacity: 0;
    transform: translateY(-12px);
  }

  100% {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes strategy-edit-activate {
  0% {
    transform: scale(0.997);
  }

  100% {
    transform: scale(1);
  }
}

@keyframes strategy-desktop-row-settle {
  0% {
    background: rgba(var(--mmui-accent-blue-rgb), 0.16);
  }

  100% {
    background: var(--mmui-sidebar-hover);
  }
}

@media (max-width: 1023px) {
  .strategy-page__header-shell {
    margin: 8px 8px 10px;
  }

  .strategy-page__header {
    flex-wrap: wrap;
    min-height: 46px;
  }

  .strategy-page__title {
    padding-left: 0;
  }

  .strategy-page__summary {
    margin-left: 0;
  }

  .strategy-page__filters-shell {
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
  }

  .strategy-page__filters-main {
    width: 100%;
  }

  .strategy-page__filters-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    width: 100%;
  }

  .strategy-page__toolbar {
    width: 100%;
    margin-left: 0;
  }

  .strategy-page__filter-field {
    min-width: 0;
  }

  .strategy-page__filter-field :deep(.ant-select-selector) {
    min-height: 34px !important;
    height: 34px !important;
    padding: 0 22px 0 6px !important;
    border: 0 !important;
    border-bottom: 1px solid var(--mmui-shell-border) !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
  }

  .strategy-page__filter-field :deep(.ant-select-selection-item),
  .strategy-page__filter-field :deep(.ant-select-selection-placeholder) {
    line-height: 32px !important;
    font-size: 13px;
  }

  .strategy-page__filter-field :deep(.ant-select-selection-item) {
    color: var(--mmui-card-title) !important;
  }

  .strategy-page__filter-field :deep(.ant-select-selection-placeholder) {
    color: color-mix(in srgb, var(--mmui-card-title) 82%, transparent) !important;
  }

  .strategy-page__filter-field :deep(.ant-select-focused .ant-select-selector),
  .strategy-page__filter-field :deep(.ant-select:hover .ant-select-selector) {
    border-bottom-color: var(--mmui-accent-blue) !important;
  }

  .strategy-page__filter-field :deep(.ant-select-arrow),
  .strategy-page__filter-field :deep(.ant-select-clear) {
    right: 4px;
    font-size: 12px;
    color: var(--mmui-card-title) !important;
  }
}

@media (max-width: 640px) {
  .strategy-page :deep(.resource-hero) {
    margin-bottom: 18px;
  }

  .strategy-page__panel-shell.is-compact {
    margin: 0 0 12px;
    border-color: transparent !important;
    background: transparent !important;
    box-shadow: none !important;
  }

  .strategy-page__panel-shell.is-compact :deep(.ant-card-body) {
    padding: 0;
  }

  .strategy-page__filters-shell {
    gap: 10px;
  }

  .strategy-page__filters-main {
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
  }

  .strategy-page__toolbar {
    display: none;
  }

  .strategy-page__toolbar-actions {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 44px;
    gap: 10px !important;
    width: 100%;
    margin-left: 0;
  }

  .strategy-page__toolbar-actions :deep(.ant-space-item) {
    width: 100%;
    min-width: 0;
  }

  .strategy-page__toolbar-actions :deep(.ant-space-item > *) {
    width: 100%;
  }

  .strategy-page__toolbar-btn {
    width: 100%;
    min-width: 0;
    padding-inline: 10px;
    white-space: nowrap;
  }

  .strategy-page__toolbar-btn--icon {
    width: 44px;
    min-width: 44px;
    padding-inline: 0;
  }

  .strategy-page__toolbar-btn--mobile-refresh {
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .strategy-page__table-shell.is-compact {
    border-top: 0;
  }

  .strategy-page__mobile-list {
    overflow: hidden;
    border: 1px solid var(--mmui-shell-border);
    border-radius: 10px;
    background: var(--mmui-card-surface);
  }

  .strategy-page__mobile-pagination {
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    text-align: center;
  }

  .strategy-page__mobile-pagination :deep(.ant-pagination-total-text) {
    width: 100%;
    margin-right: 0;
    text-align: center;
  }
}
</style>

