<template>
  <section ref="pageRootRef" class="port-page">
    <ResourcePageHero
      :title="page.title === '映射' ? '端口映射' : (page.title || '端口映射')"
      subtitle="把公网入口稳定转发到主机服务端口，集中管理外网 IP、映射端口和自定义规则。"
      summary-label="端口池"
      :primary-value="mappedCount"
      :primary-total="totalCount"
      :progress-percent="mappedPercent"
      :stat-cards="heroStatCards"
      :compact-summary-items="heroCompactSummaryItems"
      :hero-image="portHeroImage"
      help-text="端口映射用于把公网端口转发到云主机内服务端口。可搜索服务名、端口和 IP；系统创建条目默认只读。"
      help-aria-label="端口映射说明"
      hide-subtitle-on-compact
    />

    <a-card class="port-page__panel-shell resource-page__table-panel" :class="{ 'is-compact': compactMode }">
      <a-flex class="port-page__filters-shell" align="center" justify="space-between">
          <a-flex class="port-page__search-wrap" align="center">
            <a-input
              v-model:value="keyword"
              allow-clear
              placeholder="搜索服务名 / 端口 / IP"
              class="port-page__search"
            />
            <a-button
              class="port-page__toolbar-btn port-page__toolbar-btn--icon"
              aria-label="刷新"
              :disabled="isActionLoading('port:reload')"
              @click="reload"
            >
              <LoadingOutlined v-if="isActionLoading('port:reload')" />
              <ReloadOutlined v-else />
            </a-button>
          </a-flex>
          <a-space class="port-page__toolbar" :size="8">
            <a-button class="port-page__toolbar-btn" type="primary" @click="addRow">添加端口映射</a-button>
            <a-button class="port-page__toolbar-btn" danger :loading="isActionLoading('port:delete:selected')" :disabled="!removableSelectedIds.length || isActionLoading('port:delete:selected')" @click="removeSelected">删除</a-button>
          </a-space>
      </a-flex>

      <div
        ref="tableShellRef"
        class="port-page__table-shell"
        :class="{ 'is-compact': compactMode }"
        :style="tableShellStyle"
      >
        <div ref="tableStageRef" class="port-page__table-stage">
          <template v-if="compactMode">
            <transition-group v-if="pagedRows.length" name="port-list" tag="div" class="port-page__mobile-list" appear>
              <article
                v-for="record in pagedRows"
                :key="record.id"
                class="port-page__mobile-card"
                :class="{
                  'is-editing': activeEditRowId === record.id,
                  'is-closing': closingEditId === record.id,
                  'is-new': !!newRowMap[record.id],
                }"
              >
                <a-flex class="port-page__mobile-head" align="center" justify="space-between" :gap="12">
                  <a-flex class="port-page__mobile-head-main" align="center" :gap="10">
                    <a-checkbox
                      :checked="selectedRowKeys.includes(record.id)"
                      :disabled="record.kind === '系统创建'"
                      @change="toggleRowSelection(record.id, $event.target.checked)"
                    />
                    <div class="port-page__mobile-name">{{ record.name || '未命名映射' }}</div>
                  </a-flex>
                  <span
                    class="port-page__kind-tag port-page__mobile-tag"
                    :class="record.kind === '系统创建' ? 'is-system' : 'is-custom'"
                  >
                    {{ record.kind }}
                  </span>
                </a-flex>

                <div class="port-page__mobile-content">
                  <transition
                    :name="editTransitionName"
                    @before-enter="lockMobilePanelHeight"
                    @enter="animateMobilePanelHeight"
                    @before-leave="lockMobilePanelHeight"
                    @after-enter="resetMobilePanelHeight"
                    @enter-cancelled="resetMobilePanelHeight"
                    @leave-cancelled="resetMobilePanelHeight"
                  >
                    <div
                      v-if="activeEditRowId === record.id"
                      :key="`mobile-edit-${record.id}`"
                      class="port-page__mobile-panel port-page__mobile-form"
                    >
                      <div class="port-page__mobile-field">
                        <div class="port-page__mobile-field-head">
                          <div class="port-page__mobile-label">服务名称</div>
                          <a-popover trigger="click" placement="topLeft" overlayClassName="port-page__field-tip-popover">
                            <template #title>{{ tips.name.title }}</template>
                            <template #content>
                              <div class="port-page__field-tip-copy">{{ tips.name.text }}</div>
                            </template>
                            <a-button class="port-page__field-help" type="text" shape="circle" size="small" aria-label="服务名称说明">
                              <InfoCircleOutlined />
                            </a-button>
                          </a-popover>
                        </div>
                        <a-input v-model:value="record.name" @focus="focusKey = 'name'" />
                      </div>

                      <div class="port-page__mobile-field">
                        <div class="port-page__mobile-field-head">
                          <div class="port-page__mobile-label">外网端口</div>
                          <a-popover trigger="click" placement="topLeft" overlayClassName="port-page__field-tip-popover">
                            <template #title>{{ tips.pubPort.title }}</template>
                            <template #content>
                              <div class="port-page__field-tip-copy">{{ tips.pubPort.text }}</div>
                            </template>
                            <a-button class="port-page__field-help" type="text" shape="circle" size="small" aria-label="外网端口说明">
                              <InfoCircleOutlined />
                            </a-button>
                          </a-popover>
                        </div>
                        <div class="port-page__port-editor">
                          <div class="port-page__number-with-action">
                            <a-auto-complete
                              :value="String(record.pubPort || '')"
                              :options="portCandidateOptions(record)"
                              :not-found-content="isPortCandidateLoading(record) ? '查询中...' : '暂无可用端口'"
                              class="port-page__port-autocomplete"
                              popupClassName="port-page__port-candidate-popup"
                              @search="handlePortCandidateSearch(record, $event)"
                              @select="selectPortCandidate(record, $event)"
                              @focus="openPortCandidates(record)"
                            >
                              <a-input
                                class="port-page__port-input"
                                placeholder="请输入要查询的端口"
                                @focus="focusKey = 'pubPort'"
                              />
                            </a-auto-complete>
                            <button
                              type="button"
                              class="port-page__dice-btn"
                              aria-label="随机端口"
                              @click="fillRandomPort(record)"
                            >
                              <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zm1.8 3.8a1.4 1.4 0 1 0 0 2.8 1.4 1.4 0 0 0 0-2.8zm6.4 0a1.4 1.4 0 1 0 0 2.8 1.4 1.4 0 0 0 0-2.8zm-3.2 4.4a1.4 1.4 0 1 0 0 2.8 1.4 1.4 0 0 0 0-2.8zm-3.2 4.4a1.4 1.4 0 1 0 0 2.8 1.4 1.4 0 0 0 0-2.8zm6.4 0a1.4 1.4 0 1 0 0 2.8 1.4 1.4 0 0 0 0-2.8z" />
                              </svg>
                            </button>
                          </div>
                        </div>
                      </div>

                      <div class="port-page__mobile-field">
                        <div class="port-page__mobile-field-head">
                          <div class="port-page__mobile-label">外网 IP</div>
                          <a-popover trigger="click" placement="topLeft" overlayClassName="port-page__field-tip-popover">
                            <template #title>{{ tips.pubIp.title }}</template>
                            <template #content>
                              <div class="port-page__field-tip-copy">{{ tips.pubIp.text }}</div>
                            </template>
                            <a-button class="port-page__field-help" type="text" shape="circle" size="small" aria-label="外网 IP 说明">
                              <InfoCircleOutlined />
                            </a-button>
                          </a-popover>
                        </div>
                        <a-input v-model:value="record.pubIp" disabled @focus="focusKey = 'pubIp'" />
                      </div>

                      <div class="port-page__mobile-field">
                        <div class="port-page__mobile-field-head">
                          <div class="port-page__mobile-label">内网端口</div>
                          <a-popover trigger="click" placement="topLeft" overlayClassName="port-page__field-tip-popover">
                            <template #title>{{ tips.priPort.title }}</template>
                            <template #content>
                              <div class="port-page__field-tip-copy">{{ tips.priPort.text }}</div>
                            </template>
                            <a-button class="port-page__field-help" type="text" shape="circle" size="small" aria-label="内网端口说明">
                              <InfoCircleOutlined />
                            </a-button>
                          </a-popover>
                        </div>
                        <a-input-number
                          v-model:value="record.priPort"
                          :min="1"
                          :max="65535"
                          controls
                          class="port-page__number"
                          @focus="focusKey = 'priPort'"
                        />
                      </div>

                      <a-space class="port-page__mobile-actions" :size="8">
                        <a-button type="primary" size="small" :loading="isActionLoading(`port:save:${record.id}`)" :disabled="isActionLoading(`port:save:${record.id}`)" @click="save(record)">保存</a-button>
                        <a-button size="small" @click="cancel(record)">取消</a-button>
                      </a-space>
                    </div>

                    <div v-else :key="`mobile-view-${record.id}`" class="port-page__mobile-panel port-page__mobile-stage">
                      <a-flex class="port-page__mobile-meta" align="center" :gap="0" wrap="wrap">
                        <div class="port-page__mobile-meta-item">
                          <span class="port-page__mobile-label">外网端口</span>
                          <span class="port-page__mobile-value">{{ record.pubPort }}</span>
                        </div>
                        <div class="port-page__mobile-meta-item">
                          <span class="port-page__mobile-label">内网端口</span>
                          <span class="port-page__mobile-value">{{ record.priPort }}</span>
                        </div>
                        <div class="port-page__mobile-meta-item port-page__mobile-meta-item--ip">
                          <span class="port-page__mobile-label">外网 IP</span>
                          <a-space class="port-page__mobile-value port-page__mobile-value--with-copy" :size="6">
                            <span>{{ record.pubIp }}</span>
                            <a-button class="port-page__copy-btn" type="text" size="small" @click="copyAddress(record)">
                              <CopyOutlined />
                            </a-button>
                          </a-space>
                        </div>
                      </a-flex>

                      <a-space class="port-page__mobile-actions" :size="16">
                          <a-button
                            class="port-page__mobile-action-btn"
                            type="link"
                            size="small"
                            :disabled="record.kind === '系统创建'"
                            @click="edit(record)"
                          >
                            <EditOutlined />
                            编辑
                          </a-button>
                        <a-popconfirm
                          title="确认删除该端口映射？"
                          :disabled="record.kind === '系统创建'"
                          @confirm="removeRow(record)"
                        >
                          <a-button class="port-page__mobile-action-btn" type="link" danger size="small" :disabled="record.kind === '系统创建' || isActionLoading(`port:delete:${record.id}`)">
                            <LoadingOutlined v-if="isActionLoading(`port:delete:${record.id}`)" />
                            <DeleteOutlined v-else />
                            删除
                          </a-button>
                        </a-popconfirm>
                      </a-space>
                    </div>
                  </transition>
                </div>
              </article>
            </transition-group>
            <a-empty v-else class="port-page__empty" description="暂无端口映射数据" />
            <a-pagination
              v-if="filteredRows.length"
              class="port-page__mobile-pagination"
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
            <transition name="port-scroll-hint">
              <div v-if="showScrollHint" class="port-page__scroll-hint" aria-hidden="true">
                <div class="port-page__scroll-hint-inner">
                  <span class="port-page__scroll-hint-text">滑动查看</span>
                  <SwapOutlined class="port-page__scroll-hint-icon" />
                </div>
              </div>
            </transition>
            <a-table
              :columns="columns"
              :data-source="filteredRows"
              :row-selection="rowSelection"
              :expandedRowKeys="expandedRowKeys"
              :expandIconColumnIndex="-1"
              :pagination="tablePagination"
              :rowClassName="rowClassName"
              :scroll="tableScroll"
              rowKey="id"
              size="middle"
              tableLayout="fixed"
              class="port-page__table"
            >
              <template #expandedRowRender="{ record }">
                <transition name="port-note-panel" appear>
                  <div v-if="record.id === noteVisibleId" class="port-page__note-stage">
                    <transition name="port-note-swap">
                      <a-flex :key="focusKey" class="port-page__note" align="center" :gap="18">
                        <div class="port-page__note-icon" aria-hidden="true">
                          <component :is="currentTip.icon" />
                        </div>
                        <div class="port-page__note-copy">
                          <div class="port-page__note-title">{{ currentTip.title }}</div>
                          <div class="port-page__note-text">{{ currentTip.text }}</div>
                        </div>
                      </a-flex>
                    </transition>
                  </div>
                </transition>
              </template>

              <template #headerCell="{ column }">
                <span v-if="column.key === 'pubPort'">
                  外网端口
                  <a-tooltip title="公网访问时使用的映射端口">
                    <InfoCircleOutlined class="port-page__info-icon" />
                  </a-tooltip>
                </span>
                <span v-else-if="column.key === 'kind'">
                  类型
                  <a-tooltip title="系统创建或用户新建的映射规则">
                    <InfoCircleOutlined class="port-page__info-icon" />
                  </a-tooltip>
                </span>
                <span v-else>{{ column.title }}</span>
              </template>

              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'name'">
                  <div class="port-cell-stack">
                    <transition :name="editTransitionName">
                      <a-input
                        v-if="shouldRenderRowEditor(record)"
                        :key="`edit-name-${record.id}`"
                        class="port-cell-view port-cell-control"
                        v-model:value="record.name"
                        @focus="focusKey = 'name'"
                      />
                      <span v-else :key="`view-name-${record.id}`" class="port-cell-view port-cell-text">{{ record.name }}</span>
                    </transition>
                  </div>
                </template>

                <template v-else-if="column.key === 'pubPort'">
                  <div class="port-cell-stack port-cell-stack--floating">
                    <transition :name="editTransitionName">
                      <div
                        v-if="shouldRenderRowEditor(record)"
                        :key="`edit-pub-port-${record.id}`"
                        class="port-cell-view port-cell-control port-page__port-editor"
                      >
                        <div class="port-page__number-with-action">
                          <a-auto-complete
                            :value="String(record.pubPort || '')"
                            :options="portCandidateOptions(record)"
                            :not-found-content="isPortCandidateLoading(record) ? '查询中...' : '暂无可用端口'"
                            class="port-page__port-autocomplete"
                            popupClassName="port-page__port-candidate-popup"
                            @search="handlePortCandidateSearch(record, $event)"
                            @select="selectPortCandidate(record, $event)"
                            @focus="openPortCandidates(record)"
                          >
                            <a-input
                              class="port-page__port-input"
                              placeholder="请输入要查询的端口"
                              @focus="focusKey = 'pubPort'"
                            />
                          </a-auto-complete>
                          <button
                            type="button"
                            class="port-page__dice-btn"
                            aria-label="随机端口"
                            @click="fillRandomPort(record)"
                          >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                              <path d="M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zm1.8 3.8a1.4 1.4 0 1 0 0 2.8 1.4 1.4 0 0 0 0-2.8zm6.4 0a1.4 1.4 0 1 0 0 2.8 1.4 1.4 0 0 0 0-2.8zm-3.2 4.4a1.4 1.4 0 1 0 0 2.8 1.4 1.4 0 0 0 0-2.8zm-3.2 4.4a1.4 1.4 0 1 0 0 2.8 1.4 1.4 0 0 0 0-2.8zm6.4 0a1.4 1.4 0 1 0 0 2.8 1.4 1.4 0 0 0 0-2.8z" />
                            </svg>
                          </button>
                        </div>
                      </div>
                      <span v-else :key="`view-pub-port-${record.id}`" class="port-cell-view port-cell-text">{{ record.pubPort }}</span>
                    </transition>
                  </div>
                </template>

              <template v-else-if="column.key === 'pubIp'">
                <div class="port-cell-stack">
                  <transition :name="editTransitionName">
                    <a-input
                      v-if="shouldRenderRowEditor(record)"
                      :key="`edit-pub-ip-${record.id}`"
                      class="port-cell-view port-cell-control"
                      v-model:value="record.pubIp"
                      disabled
                      @focus="focusKey = 'pubIp'"
                    />
                    <a-space v-else :key="`view-pub-ip-${record.id}`" class="port-cell-view port-cell-text port-page__desktop-ip" :size="6">
                      <span>{{ record.pubIp }}</span>
                      <a-button class="port-page__copy-btn" type="text" size="small" @click="copyAddress(record)">
                        <CopyOutlined />
                      </a-button>
                    </a-space>
                  </transition>
                </div>
              </template>

                <template v-else-if="column.key === 'priPort'">
                  <div class="port-cell-stack">
                    <transition :name="editTransitionName">
                      <a-input-number
                        v-if="shouldRenderRowEditor(record)"
                        :key="`edit-pri-port-${record.id}`"
                        v-model:value="record.priPort"
                        :min="1"
                        :max="65535"
                        controls
                        class="port-cell-view port-cell-control port-page__number"
                        @focus="focusKey = 'priPort'"
                      />
                      <span v-else :key="`view-pri-port-${record.id}`" class="port-cell-view port-cell-text">{{ record.priPort }}</span>
                    </transition>
                  </div>
                </template>

                <template v-else-if="column.key === 'kind'">
                  <span class="port-page__kind-tag" :class="record.kind === '系统创建' ? 'is-system' : 'is-custom'">
                    {{ record.kind }}
                  </span>
                </template>

                <template v-else-if="column.key === 'actions'">
                  <div class="port-cell-stack">
                    <transition :name="editTransitionName">
                      <a-space
                        v-if="shouldRenderRowEditor(record)"
                        :key="`edit-actions-${record.id}`"
                        class="port-cell-view port-cell-control port-page__inline-actions"
                        :size="8"
                      >
                        <a-button type="primary" size="small" :loading="isActionLoading(`port:save:${record.id}`)" :disabled="isActionLoading(`port:save:${record.id}`)" @click="save(record)">保存</a-button>
                        <a-button size="small" @click="cancel(record)">取消</a-button>
                      </a-space>
                      <a-space v-else :key="`view-actions-${record.id}`" class="port-cell-view port-cell-text port-page__inline-actions" :size="4">
                        <a-button
                          type="link"
                          size="small"
                          :disabled="record.kind === '系统创建'"
                          @click="edit(record)"
                        >
                          编辑
                        </a-button>
                        <a-popconfirm
                          title="确认删除该端口映射？"
                          :disabled="record.kind === '系统创建'"
                          @confirm="removeRow(record)"
                        >
                          <a-button type="link" danger size="small" :disabled="record.kind === '系统创建' || isActionLoading(`port:delete:${record.id}`)">
                            <LoadingOutlined v-if="isActionLoading(`port:delete:${record.id}`)" />
                            删除
                          </a-button>
                        </a-popconfirm>
                      </a-space>
                    </transition>
                  </div>
                </template>
              </template>
            </a-table>
          </template>
        </div>
      </div>
    </a-card>
  </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { message } from 'ant-design-vue';
import {
  ApiOutlined,
  ApartmentOutlined,
  CopyOutlined,
  DeleteOutlined,
  EditOutlined,
  GlobalOutlined,
  InfoCircleOutlined,
  LoadingOutlined,
  ReloadOutlined,
  SwapOutlined,
  TagsOutlined,
} from '@ant-design/icons-vue';
import { useDashboardStore } from '@/stores/dashboard';
import ResourcePageHero from '@/components/pages/ResourcePageHero.vue';
import portHeroImage from '@/assets/page-hero/port-hero.png';
import { useActionLocks } from '@/composables/useActionLocks';

const SCROLL_HINT_SEEN_KEY = 'mmui-port-scroll-hint-seen';
const isDevMode = import.meta.env.DEV;
const EDIT_CLOSE_DELAY = 280;
const NOTE_EXIT_LEAD = 260;

const props = defineProps({
  page: {
    type: Object,
    default: () => ({}),
  },
});

const store = useDashboardStore();
const { isActionLoading, runWithActionLoading } = useActionLocks();
const COMPACT_TABLE_BREAKPOINT = 760;

const columns = [
  { title: '服务名称', dataIndex: 'name', key: 'name', width: 136 },
  { title: '外网端口', dataIndex: 'pubPort', key: 'pubPort', width: 132 },
  { title: '外网 IP', dataIndex: 'pubIp', key: 'pubIp', width: 168 },
  { title: '内网端口', dataIndex: 'priPort', key: 'priPort', width: 96 },
  { title: '类型', dataIndex: 'kind', key: 'kind', width: 84 },
  { title: '操作', key: 'actions', width: 96 },
];
const tableScroll = computed(() => ({ x: 760 }));
const pageSizeOptions = ['10', '20', '50'];

const keyword = ref('');
const currentPage = ref(1);
const pageSize = ref(10);
const editingId = ref(null);
const closingEditId = ref(null);
const freshRowId = ref(null);
const noteVisibleId = ref(null);
const rows = ref([]);
const selectedRowKeys = ref([]);
const newRowMap = ref({});
const editSnapshotMap = ref({});
const portCandidateMap = ref({});
const portCandidateLoadingMap = ref({});
const frozenMappedCount = ref(null);
const editMotionDirection = ref('open');
const focusKey = ref('name');
const pageRootRef = ref(null);
const tableShellRef = ref(null);
const tableStageRef = ref(null);
const showScrollHint = ref(false);
const isTableVisible = ref(false);
const compactMode = ref(false);
const tableShellHeight = ref(null);

const tips = {
  name: {
    title: '服务名称',
    text: '用于标识该映射规则，建议简短可读，便于后续排查与维护。',
    icon: TagsOutlined,
  },
  pubPort: {
    title: '外网端口',
    text: '公网访问使用的映射端口。输入端口后会查询可用端口，请从下拉候选中选择。',
    icon: ApiOutlined,
  },
  priPort: {
    title: '内网端口',
    text: '云主机内服务实际监听的端口，需要与业务程序配置保持一致。',
    icon: ApartmentOutlined,
  },
  pubIp: {
    title: '外网 IP',
    text: '出口 IP 由平台分配，本次迁移阶段先只读展示，不开放手工修改。',
    icon: GlobalOutlined,
  },
  kind: {
    title: '类型',
    text: '系统创建条目默认只读，自定义条目支持删除；新建条目保存后会进入列表。',
    icon: TagsOutlined,
  },
};

const currentTip = computed(() => tips[focusKey.value] || tips.name);
const primaryIp = computed(() => {
  const remoteAddress = String(store.host?.remoteAddress || '').trim();
  const remoteHost = remoteAddress.split(':')[0]?.trim();
  return remoteHost || '149.88.18.20';
});
const mappedCount = computed(() => (
  frozenMappedCount.value === null ? (props.page?.rows || []).length : frozenMappedCount.value
));
const totalCount = computed(() => Number(props.page?.total || store.quotas?.portMapping?.total || mappedCount.value));
const remainingCount = computed(() => Math.max(0, totalCount.value - mappedCount.value));
const mappedPercent = computed(() => {
  if (!totalCount.value) {
    return 0;
  }

  return Math.min(100, Math.round((mappedCount.value / totalCount.value) * 100));
});
const heroStatCards = computed(() => ([
  { label: '已映射', value: mappedCount.value },
  { label: '剩余', value: remainingCount.value },
]));
const heroCompactSummaryItems = computed(() => ([
  { label: '端口池', value: `${mappedCount.value} / ${totalCount.value || 0}` },
]));
const activeEditRowId = computed(() => editingId.value || closingEditId.value || null);
const editTransitionName = computed(() => (editMotionDirection.value === 'close' ? 'port-edit-close' : 'port-edit-open'));
const expandedRowKeys = computed(() => (noteVisibleId.value ? [noteVisibleId.value] : []));
const removableSelectedIds = computed(() => selectedRowKeys.value.filter((id) => {
  const row = rows.value.find((item) => item.id === id);
  return row && row.kind !== '系统创建';
}));
const filteredRows = computed(() => {
  const term = keyword.value.trim().toLowerCase();
  if (!term) {
    return rows.value;
  }

  return rows.value.filter((row) => (
    [row.name, row.pubPort, row.pubIp, row.priPort, row.kind]
      .some((value) => String(value ?? '').toLowerCase().includes(term))
  ));
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
const tableShellStyle = computed(() => (
  !compactMode.value && tableShellHeight.value ? { height: `${tableShellHeight.value}px` } : null
));
const rowSelection = computed(() => ({
  selectedRowKeys: selectedRowKeys.value,
  onChange: onSelectChange,
  getCheckboxProps: (record) => ({
    disabled: record.kind === '系统创建',
  }),
}));

let scrollHintDelayTimer = null;
let scrollHintHideTimer = null;
let activeScrollContainer = null;
let tableVisibilityObserver = null;
let hasShownScrollHintThisMount = false;
let pageResizeObserver = null;
let tableHeightObserver = null;
let deferExternalRowsSync = false;
let pendingExternalRowsSync = false;
let freshRowTimer = null;
const portCandidateTimers = new Map();

function hasSeenScrollHint() {
  if (hasShownScrollHintThisMount) {
    return true;
  }

  if (isDevMode) {
    return false;
  }

  try {
    return window.sessionStorage.getItem(SCROLL_HINT_SEEN_KEY) === '1';
  } catch {
    return false;
  }
}

function markScrollHintSeen() {
  hasShownScrollHintThisMount = true;

  if (isDevMode) {
    return;
  }

  try {
    window.sessionStorage.setItem(SCROLL_HINT_SEEN_KEY, '1');
  } catch {
    // ignore
  }
}

function clearScrollHintTimers() {
  if (scrollHintDelayTimer) {
    window.clearTimeout(scrollHintDelayTimer);
    scrollHintDelayTimer = null;
  }

  if (scrollHintHideTimer) {
    window.clearTimeout(scrollHintHideTimer);
    scrollHintHideTimer = null;
  }
}

function hideScrollHint() {
  showScrollHint.value = false;
}

function resolveTableScrollContainer() {
  return tableShellRef.value?.querySelector('.ant-table-content, .ant-table-body') || null;
}

function handleTableScroll() {
  if (!activeScrollContainer) {
    return;
  }

  if (activeScrollContainer.scrollLeft > 4) {
    markScrollHintSeen();
    clearScrollHintTimers();
    hideScrollHint();
  }
}

function bindTableScrollContainer() {
  const nextContainer = resolveTableScrollContainer();
  if (activeScrollContainer === nextContainer) {
    return;
  }

  if (activeScrollContainer) {
    activeScrollContainer.removeEventListener('scroll', handleTableScroll);
  }

  activeScrollContainer = nextContainer;

  if (activeScrollContainer) {
    activeScrollContainer.addEventListener('scroll', handleTableScroll, { passive: true });
  }
}

function syncScrollHintState() {
  bindTableScrollContainer();

  if (compactMode.value || !activeScrollContainer || !isTableVisible.value) {
    clearScrollHintTimers();
    hideScrollHint();
    return;
  }

  const hasOverflow = activeScrollContainer.scrollWidth > activeScrollContainer.clientWidth + 8;
  if (!hasOverflow) {
    clearScrollHintTimers();
    hideScrollHint();
    return;
  }

  if (activeScrollContainer.scrollLeft > 4) {
    markScrollHintSeen();
    clearScrollHintTimers();
    hideScrollHint();
    return;
  }

  if (hasSeenScrollHint() || scrollHintDelayTimer || showScrollHint.value) {
    return;
  }

  scrollHintDelayTimer = window.setTimeout(() => {
    showScrollHint.value = true;
    markScrollHintSeen();
    scrollHintDelayTimer = null;
    scrollHintHideTimer = window.setTimeout(() => {
      hideScrollHint();
      scrollHintHideTimer = null;
    }, 2200);
  }, 3000);
}

function toViewRow(item) {
  const typeIsNumeric = Number.isFinite(Number(item?.type));
  const kind = item?.kind || (typeIsNumeric ? (Number(item.type) === 1 ? '系统创建' : '自定义') : '自定义');

  return {
    id: item?.id,
    name: item?.name || '',
    pubPort: Number(item?.sport ?? item?.pub_port ?? 0) || null,
    pubIp: item?.publicIp || item?.pub_ip || primaryIp.value,
    priPort: Number(item?.dport ?? item?.pri_port ?? 0) || null,
    kind,
    editable: item?.editable ?? kind !== '系统创建',
  };
}

function syncRows(force = false) {
  if (!force && deferExternalRowsSync) {
    pendingExternalRowsSync = true;
    return;
  }

  pendingExternalRowsSync = false;
  rows.value = (props.page?.rows || []).map((item) => toViewRow(item));
}

function resetEditingState() {
  editingId.value = null;
  closingEditId.value = null;
  freshRowId.value = null;
  noteVisibleId.value = null;
  frozenMappedCount.value = null;
  focusKey.value = 'name';
  newRowMap.value = {};
  editSnapshotMap.value = {};
  editMotionDirection.value = 'open';
  deferExternalRowsSync = false;
  pendingExternalRowsSync = false;
}

function onSelectChange(keys) {
  selectedRowKeys.value = keys;
}

function toggleRowSelection(id, checked) {
  const next = new Set(selectedRowKeys.value);
  if (checked) {
    next.add(id);
  } else {
    next.delete(id);
  }
  selectedRowKeys.value = Array.from(next);
}

function rowClassName(record) {
  return [
    record.id === activeEditRowId.value ? 'is-editing' : '',
    newRowMap.value[record.id] ? 'port-row--new' : '',
    record.id === freshRowId.value ? 'port-row--fresh' : '',
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

function clearPortCandidateTimers() {
  portCandidateTimers.forEach((timer) => {
    window.clearTimeout(timer);
  });
  portCandidateTimers.clear();
}

function setPortCandidateState(mapRef, id, value) {
  if (!id) {
    return;
  }

  mapRef.value = {
    ...mapRef.value,
    [id]: value,
  };
}

function portCandidateRows(record) {
  return portCandidateMap.value[record?.id] || [];
}

function portCandidateOptions(record) {
  return portCandidateRows(record).map((port) => ({
    value: String(port),
    label: String(port),
  }));
}

function isPortCandidateLoading(record) {
  return Boolean(portCandidateLoadingMap.value[record?.id]);
}

async function loadPortCandidates(record, keywords = '') {
  if (!record?.id) {
    return;
  }

  const query = String(keywords ?? '').trim();
  setPortCandidateState(portCandidateLoadingMap, record.id, true);

  try {
    const candidates = await store.findPortMappingCandidates(query);
    setPortCandidateState(portCandidateMap, record.id, candidates);
  } catch (error) {
    setPortCandidateState(portCandidateMap, record.id, []);
    message.error(error instanceof Error ? error.message : '可用端口查询失败');
  } finally {
    setPortCandidateState(portCandidateLoadingMap, record.id, false);
  }
}

function handlePortCandidateSearch(record, value) {
  record.pubPort = value;
  focusKey.value = 'pubPort';

  const key = String(record?.id || '');
  if (!key) {
    return;
  }

  if (portCandidateTimers.has(key)) {
    window.clearTimeout(portCandidateTimers.get(key));
  }

  const timer = window.setTimeout(() => {
    portCandidateTimers.delete(key);
    loadPortCandidates(record, value);
  }, 180);

  portCandidateTimers.set(key, timer);
}

function openPortCandidates(record) {
  focusKey.value = 'pubPort';
  loadPortCandidates(record, record?.pubPort || '');
}

function selectPortCandidate(record, value) {
  record.pubPort = Number(value);
  focusKey.value = 'pubPort';
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
  }, 760);
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
  return container?.classList.contains('port-page__mobile-content') ? container : null;
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

function flushDeferredRowSync(force = false) {
  deferExternalRowsSync = false;
  const shouldSync = force || pendingExternalRowsSync;
  pendingExternalRowsSync = false;

  if (shouldSync) {
    syncRows(true);
  }
}

async function refreshPortRowsAfterEditFailure() {
  deferExternalRowsSync = false;
  pendingExternalRowsSync = false;
  await store.refreshPortMappings();
  syncRows(true);
  clampCurrentPage();
}

function hasPortRecordChanged(record, snapshot) {
  if (!snapshot) {
    return false;
  }

  return (
    String(record.name || '').trim() !== String(snapshot.name || '').trim()
    || Number(record.pubPort) !== Number(snapshot.pubPort)
    || Number(record.priPort) !== Number(snapshot.priPort)
  );
}

async function recreatePortMappingDeleteFirst(record, input) {
  let oldRecordDeleted = false;

  try {
    await store.deletePortMappings([record.id], { silent: true });
    oldRecordDeleted = true;
  } catch (error) {
    await refreshPortRowsAfterEditFailure();
    throw new Error('修改失败');
  }

  try {
    const rowsAfterCreate = await store.createPortMapping(input, { silent: true });
    return rowsAfterCreate;
  } catch (error) {
    await refreshPortRowsAfterEditFailure();
    if (oldRecordDeleted) {
      throw new Error('添加失败，记录已丢失');
    }
    throw new Error('修改失败');
  }
}

async function recreatePortMappingAddFirst(record, input) {
  let rowsAfterCreate = null;

  try {
    rowsAfterCreate = await store.createPortMapping(input, { silent: true });
  } catch (error) {
    await refreshPortRowsAfterEditFailure();
    throw new Error('修改失败');
  }

  try {
    await store.deletePortMappings([record.id], { silent: true });
    return rowsAfterCreate;
  } catch (error) {
    await refreshPortRowsAfterEditFailure();
    throw new Error('修改失败');
  }
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

function updateTableShellHeight(height = tableStageRef.value?.getBoundingClientRect().height) {
  const nextHeight = normalizeHeight(height);
  tableShellHeight.value = nextHeight || null;
}

function addRow() {
  if (activeEditRowId.value) {
    message.info('请先完成当前编辑中的条目');
    return;
  }

  if (mappedCount.value >= totalCount.value) {
    message.warning('端口映射数量已达上限');
    return;
  }

  const id = `new_${Date.now()}`;
  rows.value.unshift({
    id,
    name: '',
    pubPort: null,
    pubIp: primaryIp.value,
    priPort: null,
    kind: '自定义',
    editable: true,
  });
  newRowMap.value[id] = true;
  editMotionDirection.value = 'open';
  editingId.value = id;
  focusKey.value = 'name';
  currentPage.value = 1;
  selectedRowKeys.value = [];
  scheduleNoteOpen(id);
}

function edit(record) {
  if (activeEditRowId.value) {
    message.info('请先完成当前编辑中的条目');
    return;
  }

  if (record.kind === '系统创建') {
    message.info('系统创建的条目当前不可编辑');
    return;
  }

  rememberEditSnapshot(record);
  frozenMappedCount.value = mappedCount.value;
  editMotionDirection.value = 'open';
  editingId.value = record.id;
  focusKey.value = 'name';
  scheduleNoteOpen(record.id);
}

async function cancel(record) {
  if (record && newRowMap.value[record.id]) {
    await closeEditing(record.id, {
      closeDelay: compactMode.value ? 0 : 180,
      afterClose: () => {
        rows.value = rows.value.filter((item) => item.id !== record.id);
        delete newRowMap.value[record.id];
      },
    });
  } else {
    restoreEditSnapshot(record);
    await closeEditing(record?.id);
  }
  frozenMappedCount.value = null;
}

async function save(record) {
  const name = String(record.name || '').trim();
  const sport = Number(record.pubPort);
  const dport = Number(record.priPort);

  if (!name || !sport || !dport) {
    message.warning('请填写完整信息');
    return;
  }

  if (sport < 1 || sport > 65535 || dport < 1 || dport > 65535) {
    message.warning('端口范围必须在 1-65535 之间');
    return;
  }

  await runWithActionLoading(`port:save:${record.id}`, async () => {
    try {
      if (newRowMap.value[record.id]) {
        deferExternalRowsSync = true;
        const rowsAfterCreate = await store.createPortMapping({ name, sport, dport });
        delete newRowMap.value[record.id];
        await closeEditing(record.id, {
          afterClose: () => {
            flushDeferredRowSync(true);
            markFreshRow(rowsAfterCreate?.[0]?.id);
          },
        });
        message.success('端口映射已创建');
        return;
      }

      const snapshot = editSnapshotMap.value[record.id];
      if (!hasPortRecordChanged(record, snapshot)) {
        await closeEditing(record?.id);
        message.info('端口映射未修改');
        return;
      }

      const nameChanged = String(name) !== String(snapshot?.name || '').trim();
      const sportChanged = sport !== Number(snapshot?.pubPort);
      const dportChanged = dport !== Number(snapshot?.priPort);
      const quotaReached = mappedCount.value >= totalCount.value;
      const shouldAddFirst = sportChanged && !nameChanged && !dportChanged && !quotaReached;

      deferExternalRowsSync = true;
      const input = { name, sport, dport };
      const rowsAfterUpdate = shouldAddFirst
        ? await recreatePortMappingAddFirst(record, input)
        : await recreatePortMappingDeleteFirst(record, input);

      await closeEditing(record.id, {
        afterClose: () => {
          flushDeferredRowSync(true);
          markFreshRow(rowsAfterUpdate?.[0]?.id);
        },
      });
      await store.refreshPortMappings();
      syncRows(true);
      frozenMappedCount.value = null;
      message.success('端口映射已修改');
    } catch (error) {
      flushDeferredRowSync();
      frozenMappedCount.value = null;
      message.error(error instanceof Error ? error.message : '修改失败');
    }
  });
}

async function fillRandomPort(record) {
  try {
    const randomKeyword = String(Math.floor(Math.random() * 101));
    record.pubPort = await store.generatePortMappingCandidate(randomKeyword);
    await loadPortCandidates(record, record.pubPort);
    focusKey.value = 'pubPort';
  } catch (error) {
    message.error(error instanceof Error ? error.message : '随机端口生成失败');
  }
}

async function copyAddress(record) {
  const text = `${record.pubIp}:${record.pubPort}`;

  try {
    await navigator.clipboard.writeText(text);
    message.success('已复制外网地址');
  } catch {
    const input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    message.success('已复制外网地址');
  }
}

async function removeSelected() {
  if (!removableSelectedIds.value.length) {
    return;
  }

  await runWithActionLoading('port:delete:selected', async () => {
    try {
      await store.deletePortMappings(removableSelectedIds.value);
      selectedRowKeys.value = [];
      syncRows();
      message.success('已删除所选端口映射');
    } catch (error) {
      message.error(error instanceof Error ? error.message : '删除失败');
    }
  });
}

async function removeRow(record) {
  await runWithActionLoading(`port:delete:${record.id}`, async () => {
  try {
    await store.deletePortMappings([record.id]);
    selectedRowKeys.value = selectedRowKeys.value.filter((id) => id !== record.id);
    syncRows();
    message.success('端口映射已删除');
  } catch (error) {
    message.error(error instanceof Error ? error.message : '删除失败');
  }
  });
}

async function reload() {
  await runWithActionLoading('port:reload', async () => {
  try {
    resetEditingState();
    await store.refreshPortMappings();
    syncRows();
    clampCurrentPage();
    await nextTick();
    syncScrollHintState();
  } catch (error) {
    message.error(error instanceof Error ? error.message : '刷新失败');
  }
  });
}

function handleViewportChange() {
  nextTick(() => {
    updateCompactMode();
    syncScrollHintState();
  });
}

function updateCompactMode(width = pageRootRef.value?.clientWidth ?? window.innerWidth) {
  compactMode.value = window.innerWidth <= 768 || width <= COMPACT_TABLE_BREAKPOINT;
}

function initPageResizeObserver() {
  if (!pageRootRef.value || typeof ResizeObserver === 'undefined') {
    updateCompactMode();
    return;
  }

  pageResizeObserver = new ResizeObserver(([entry]) => {
    const width = entry?.contentRect?.width || pageRootRef.value.clientWidth;
    updateCompactMode(width);
    syncScrollHintState();
  });

  pageResizeObserver.observe(pageRootRef.value);
}

function initTableVisibilityObserver() {
  if (!tableShellRef.value || typeof IntersectionObserver === 'undefined') {
    isTableVisible.value = true;
    return;
  }

  tableVisibilityObserver = new IntersectionObserver(
    ([entry]) => {
      isTableVisible.value = Boolean(entry?.isIntersecting && entry.intersectionRatio > 0.35);
      syncScrollHintState();
    },
    {
      threshold: [0, 0.35, 0.6],
    },
  );

  tableVisibilityObserver.observe(tableShellRef.value);
}

function initTableHeightObserver() {
  if (!tableStageRef.value || typeof ResizeObserver === 'undefined') {
    nextTick(() => {
      updateTableShellHeight();
    });
    return;
  }

  tableHeightObserver = new ResizeObserver(([entry]) => {
    const height = entry?.contentRect?.height || tableStageRef.value?.getBoundingClientRect().height || 0;
    updateTableShellHeight(height);
  });

  tableHeightObserver.observe(tableStageRef.value);
  nextTick(() => {
    updateTableShellHeight();
  });
}

onMounted(() => {
  initPageResizeObserver();
  initTableVisibilityObserver();
  initTableHeightObserver();
  handleViewportChange();
  window.addEventListener('resize', handleViewportChange);
});

onBeforeUnmount(() => {
  clearFreshRowTimer();
  clearPortCandidateTimers();
  clearScrollHintTimers();
  if (activeScrollContainer) {
    activeScrollContainer.removeEventListener('scroll', handleTableScroll);
    activeScrollContainer = null;
  }
  if (tableVisibilityObserver) {
    tableVisibilityObserver.disconnect();
    tableVisibilityObserver = null;
  }
  if (pageResizeObserver) {
    pageResizeObserver.disconnect();
    pageResizeObserver = null;
  }
  if (tableHeightObserver) {
    tableHeightObserver.disconnect();
    tableHeightObserver = null;
  }
  window.removeEventListener('resize', handleViewportChange);
});

watch(keyword, () => {
  currentPage.value = 1;
});

watch([filteredRows, pageSize], () => {
  clampCurrentPage();
});

watch(
  () => [props.page?.rows, primaryIp.value],
  async () => {
    syncRows();
    clampCurrentPage();
    await nextTick();
    syncScrollHintState();
    updateTableShellHeight();
  },
  { immediate: true, deep: true },
);

watch(
  () => [editingId.value, closingEditId.value, noteVisibleId.value, filteredRows.value.length],
  async () => {
    await nextTick();
    syncScrollHintState();
    updateTableShellHeight();
  },
  { deep: true },
);

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
</script>

<style scoped>
.port-page {
  width: 100%;
}

.port-page__toolbar,
.port-page__inline-actions {
  align-items: center;
}

.port-page__panel-shell {
  position: relative;
  z-index: 2;
  margin: 0 8px 12px;
  overflow: hidden;
}

.port-page__panel-shell :deep(.ant-card-body) {
  padding: 18px;
}

.port-page__toolbar {
  margin-left: auto;
}

.port-page__toolbar-btn {
  min-width: 0;
  height: 42px;
  padding: 0 18px;
  border-radius: 10px !important;
}

.port-page__toolbar-btn--icon {
  width: 42px;
  min-width: 42px;
  padding: 0;
}

.port-page__filters-shell {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin: 0 0 16px;
  padding: 0;
  background: transparent;
  border: 0;
  box-shadow: none;
}

.port-page__search-wrap {
  gap: 10px;
  flex: 0 1 342px;
  min-width: 0;
  padding: 0;
  background: transparent;
  border: 0;
}

.port-page__search {
  width: 100%;
  max-width: none;
  min-height: 42px;
  padding-inline: 14px;
  border: 1px solid var(--mmui-shell-border) !important;
  border-radius: 10px;
  background: var(--mmui-card-action-bg) !important;
  box-shadow: none;
}

.port-page__search :deep(.ant-input),
.port-page__search:deep(.ant-input) {
  background: var(--mmui-card-action-bg) !important;
  color: var(--mmui-text) !important;
}

.port-page__search :deep(.ant-input::placeholder),
.port-page__search:deep(.ant-input::placeholder) {
  color: var(--mmui-text-muted) !important;
}

.port-page__info-icon {
  margin-left: 4px;
  color: var(--mmui-text-muted);
}

.port-page__table-shell {
  position: relative;
  margin: 0;
  overflow: hidden;
  border-top: 1px solid var(--mmui-shell-border);
  transition: height 0.34s cubic-bezier(0.22, 1, 0.36, 1), border-color 0.2s ease, box-shadow 0.2s ease;
}

.port-page__table-stage {
  display: block;
  min-height: 0;
}

.port-page__table :deep(.ant-table-pagination.ant-pagination),
.port-page__mobile-pagination {
  margin: 20px 0 0;
}

.port-page__table :deep(.ant-pagination-total-text),
.port-page__mobile-pagination :deep(.ant-pagination-total-text) {
  margin-right: auto;
}

.port-page__scroll-hint {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: 132px;
  z-index: 4;
  pointer-events: none;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding-right: 14px;
  background: linear-gradient(
    90deg,
    rgba(32, 32, 32, 0) 0%,
    rgba(60, 60, 60, 0.06) 35%,
    rgba(92, 92, 92, 0.16) 68%,
    rgba(128, 128, 128, 0.32) 100%
  );
}

.port-page__scroll-hint-inner {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  animation: port-scroll-nudge 1.1s ease-in-out 2;
}

.port-page__scroll-hint-text {
  color: rgba(255, 255, 255, 0.78);
  font-size: var(--mmui-font-size-caption);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-caption);
  letter-spacing: 0.24em;
  writing-mode: vertical-rl;
  text-orientation: mixed;
  white-space: nowrap;
}

.port-page__scroll-hint-icon {
  color: rgba(255, 255, 255, 0.82);
  font-size: 44px;
  filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.16));
}

.port-page__table-shell.is-compact {
  overflow: hidden;
  border-top: 1px solid var(--mmui-shell-border);
}

.port-page__mobile-list {
  position: relative;
  display: block;
  padding: 0;
  border: 0;
  background: transparent;
}

.port-page__mobile-card {
  position: relative;
  padding: 16px;
  border: 0;
  border-radius: 0;
  background: transparent;
  overflow: hidden;
  max-height: 620px;
  transform: translateZ(0);
  backface-visibility: hidden;
  will-change: max-height, opacity, transform, padding;
  transition: background-color 0.22s ease, transform 0.24s ease;
}

.port-page__mobile-card + .port-page__mobile-card {
  border-top: 1px solid var(--mmui-shell-border);
}

.port-page__mobile-card.is-editing {
  background: var(--mmui-sidebar-hover);
  animation: port-edit-activate 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}

.port-page__mobile-card.is-closing {
  pointer-events: none;
}

.port-page__mobile-card.is-new {
  animation: port-card-appear 0.7s cubic-bezier(0.2, 0.84, 0.24, 1);
}

.port-page__mobile-card.is-new::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(
    104deg,
    rgba(var(--mmui-accent-blue-rgb), 0.22) 0%,
    rgba(var(--mmui-accent-blue-rgb), 0.08) 36%,
    rgba(255, 255, 255, 0.12) 52%,
    rgba(var(--mmui-accent-blue-rgb), 0.04) 78%,
    rgba(var(--mmui-accent-blue-rgb), 0) 100%
  );
  opacity: 0;
  pointer-events: none;
  animation: port-card-sheen 0.88s ease-out 0.1s both;
}

.port-page__mobile-head {
  margin-bottom: 16px;
  flex-wrap: nowrap;
}

.port-page__mobile-head-main {
  min-width: 0;
  flex: 1 1 auto;
}

.port-page__mobile-name {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.port-page__mobile-tag {
  flex: 0 0 auto;
  margin-left: auto;
  min-width: max-content;
  align-self: center;
}

.port-page__kind-tag {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-right: 0;
  margin-inline-end: 0;
  min-height: 28px;
  padding: 0 10px;
  border-radius: 999px;
  font-size: var(--mmui-font-size-caption);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-caption);
  white-space: nowrap;
}

.port-page__kind-tag.is-custom {
  color: var(--mmui-accent-blue);
  background: rgba(var(--mmui-accent-blue-rgb), 0.12);
}

.port-page__kind-tag.is-system {
  color: #ff6b72;
  background: rgba(255, 107, 114, 0.12);
}

.port-page__mobile-meta {
  display: grid !important;
  grid-template-columns: minmax(86px, 0.85fr) minmax(86px, 0.85fr) minmax(118px, 1.15fr);
  width: 100%;
  align-items: center;
}

.port-page__mobile-meta-item {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-width: 0;
  gap: 10px;
  padding-right: 12px;
}

.port-page__mobile-meta-item + .port-page__mobile-meta-item {
  margin-left: 12px;
  padding-left: 12px;
}

.port-page__mobile-meta-item + .port-page__mobile-meta-item::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  width: 1px;
  height: 20px;
  background: var(--mmui-shell-border);
  transform: translateY(-50%);
}

.port-page__mobile-meta-item--ip {
  min-width: 0;
  padding-right: 0;
}

.port-page__mobile-label {
  flex: 0 0 auto;
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
  white-space: nowrap;
}

.port-page__mobile-value {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  flex: 0 1 auto;
  min-width: 0;
  color: var(--mmui-text);
  font-size: var(--mmui-font-size-body);
  line-height: var(--mmui-line-height-body);
  text-align: right;
  word-break: normal;
  white-space: nowrap;
}

.port-page__mobile-value--with-copy {
  align-items: center;
  flex-wrap: nowrap;
  min-width: 0;
  max-width: 100%;
  line-height: 1.2;
  justify-content: flex-end;
  gap: 6px;
  white-space: nowrap;
}

.port-page__mobile-value--with-copy :deep(.ant-space-item:first-child) {
  min-width: 0;
  overflow: hidden;
}

.port-page__mobile-value--with-copy span {
  display: block;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.port-page__copy-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  min-width: 18px;
  height: 18px;
  line-height: 1;
  color: var(--mmui-text-muted) !important;
  vertical-align: middle;
}

.port-page__copy-btn :deep(.anticon),
.port-page__copy-btn :deep(svg) {
  font-size: 14px;
}

.port-page__copy-btn:hover {
  color: var(--mmui-accent-blue) !important;
}

.port-page__desktop-ip {
  align-items: center;
}

.port-page__mobile-content {
  position: relative;
  display: grid;
  align-items: start;
  overflow: hidden;
  transition: height 0.34s cubic-bezier(0.22, 1, 0.36, 1);
  will-change: height;
}

.port-page__mobile-panel {
  grid-area: 1 / 1;
  min-width: 0;
}

.port-cell-stack {
  display: grid;
  align-items: center;
  min-width: 0;
  width: 100%;
  min-height: 32px;
  overflow: hidden;
}

.port-cell-stack--floating {
  overflow: visible;
}

.port-cell-stack > * {
  grid-area: 1 / 1;
  min-width: 0;
}

.port-cell-view {
  min-width: 0;
  width: 100%;
}

.port-cell-text {
  display: inline-flex;
  align-items: center;
  min-height: 32px;
  padding-inline: 12px;
  box-sizing: border-box;
  opacity: 1;
}

.port-cell-control {
  min-width: 0;
}

.port-cell-text.port-page__inline-actions {
  padding-inline: 0;
}

.port-cell-view.port-page__desktop-ip,
.port-cell-view.port-page__inline-actions {
  width: auto;
  max-width: 100%;
  justify-self: start;
}

.port-page__mobile-form {
  display: grid;
  gap: 12px;
}

.port-page__mobile-stage {
  display: grid;
  gap: 0;
}

.port-page__mobile-field {
  display: grid;
  gap: 6px;
}

.port-page__mobile-field-head {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  width: fit-content;
}

.port-page__field-help {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  min-width: 22px;
  height: 22px;
  padding: 0;
  color: var(--mmui-text-muted) !important;
}

.port-page__field-help:hover {
  color: var(--mmui-accent-blue) !important;
}

:deep(.port-page__field-tip-popover .ant-popover-inner) {
  max-width: 260px;
  border-radius: 12px;
}

.port-page__field-tip-copy {
  max-width: 220px;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.port-page__mobile-actions {
  display: flex !important;
  width: 100%;
  margin-top: 16px;
  justify-content: flex-end;
}

.port-page__mobile-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding-inline: 0 !important;
  gap: 2px;
}

.port-page__mobile-actions :deep(.ant-btn-link) {
  padding-inline: 0;
}

.port-page__empty {
  padding: 24px 0;
}

.port-page__table {
  border: 0;
  border-radius: 10px;
}

.port-page__table :deep(.ant-spin-nested-loading),
.port-page__table :deep(.ant-spin-container),
.port-page__table :deep(.ant-table),
.port-page__table :deep(.ant-table-container),
.port-page__table :deep(.ant-table-content),
.port-page__table :deep(.ant-table-header),
.port-page__table :deep(.ant-table-body) {
  border-radius: 10px;
}

.port-page__table :deep(.ant-table-content table),
.port-page__table :deep(.ant-table-header table),
.port-page__table :deep(.ant-table-body table) {
  min-width: 760px;
  width: max(100%, 760px) !important;
}

.port-page__table :deep(.ant-table) {
  background: transparent;
  box-shadow: none;
}

.port-page__table :deep(.ant-table-container::before),
.port-page__table :deep(.ant-table-container::after) {
  display: none;
}

.port-page__table :deep(.ant-table-content),
.port-page__table :deep(.ant-table-body) {
  scrollbar-width: auto;
  scrollbar-color: rgba(255, 255, 255, 0.42) rgba(255, 255, 255, 0.1);
}

.port-page__table :deep(.ant-table-content::-webkit-scrollbar),
.port-page__table :deep(.ant-table-body::-webkit-scrollbar) {
  height: 12px;
}

.port-page__table :deep(.ant-table-content::-webkit-scrollbar-track),
.port-page__table :deep(.ant-table-body::-webkit-scrollbar-track) {
  background: rgba(255, 255, 255, 0.08);
  border-radius: 999px;
}

.port-page__table :deep(.ant-table-content::-webkit-scrollbar-thumb),
.port-page__table :deep(.ant-table-body::-webkit-scrollbar-thumb) {
  background: rgba(255, 255, 255, 0.36);
  border: 2px solid rgba(255, 255, 255, 0.08);
  border-radius: 999px;
}

.port-page__table :deep(.ant-table-content::-webkit-scrollbar-thumb:hover),
.port-page__table :deep(.ant-table-body::-webkit-scrollbar-thumb:hover) {
  background: rgba(255, 255, 255, 0.5);
}

.port-page__table :deep(.ant-table-thead > tr > th) {
  height: 66px;
  padding: 0 12px;
  white-space: nowrap;
  background: rgba(255, 255, 255, 0.04);
  border-bottom: 1px solid var(--mmui-shell-border);
}

.port-page__table :deep(.ant-table-thead > tr > th) {
  white-space: nowrap;
}

.port-page__table :deep(.ant-table-tbody > tr > td) {
  padding: 16px 12px;
  border-bottom: 1px solid var(--mmui-shell-border);
}

.port-page__table :deep(.ant-table-cell-row-hover) {
  background: transparent !important;
}

.port-page__table :deep(.ant-table-tbody > tr:hover > td) {
  background: color-mix(in srgb, var(--mmui-card-surface) 94%, #ffffff 6%) !important;
}

.port-page__table :deep(.ant-table-expanded-row > td) {
  background: transparent !important;
  padding: 0 !important;
}

.port-page__table :deep(.is-editing > td) {
  background: var(--mmui-sidebar-hover) !important;
  animation: port-edit-activate 0.28s cubic-bezier(0.22, 1, 0.36, 1);
  transition: background-color 0.24s ease;
}

.port-page__table :deep(.port-row--new > td) {
  background: var(--mmui-sidebar-hover) !important;
  animation: port-row-reveal 0.56s cubic-bezier(0.2, 0.84, 0.24, 1);
  box-shadow: none;
  will-change: transform, opacity;
}

.port-page__table :deep(.port-row--fresh > td) {
  background: var(--mmui-sidebar-hover) !important;
  animation: port-desktop-row-settle 360ms cubic-bezier(0.2, 0.84, 0.24, 1);
  box-shadow: none;
}

.port-page__table :deep(.port-row--new > td:first-child) {
  box-shadow: none;
}

.port-page__table :deep(.port-row--new > td:last-child) {
  box-shadow: none;
}

.port-page__note-stage {
  position: relative;
  display: grid;
  overflow: hidden;
  padding: 0 8px 8px;
  transform-origin: top center;
}

.port-page__note {
  display: flex;
  align-items: center;
  gap: 18px;
  padding: 26px 18px 18px 12px;
}

.port-page__note-copy {
  min-width: 0;
  flex: 1 1 auto;
}

.port-page__note-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  min-width: 48px;
  height: 48px;
  border-radius: 12px;
  background: rgba(var(--mmui-accent-blue-rgb), 0.12);
  color: var(--mmui-accent-blue);
}

.port-page__note-icon :deep(.anticon),
.port-page__note-icon :deep(svg) {
  font-size: 22px;
}

.port-page__note-title {
  margin-bottom: 6px;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.port-page__note-text {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-body);
  line-height: var(--mmui-line-height-body);
}

.port-page__port-editor {
  display: flex;
  align-items: center;
  width: 100%;
}

.port-page__number-with-action {
  position: relative;
  width: 100%;
}

.port-page__port-autocomplete {
  width: 100%;
}

.port-page__port-autocomplete :deep(.ant-select-selector) {
  height: 34px !important;
  border-radius: 10px !important;
  background: var(--mmui-card-action-bg) !important;
  box-shadow: none !important;
}

.port-page__port-autocomplete :deep(.ant-select-selection-search-input) {
  height: 32px !important;
}

.port-page__port-input {
  padding-right: 38px !important;
}

.port-page__table :deep(.ant-input),
.port-page__table :deep(.ant-input-number),
.port-page__table :deep(.ant-input-affix-wrapper) {
  border-radius: 10px;
  box-shadow: none;
}

.port-page__table :deep(.is-editing .ant-input),
.port-page__table :deep(.is-editing .ant-input-number),
.port-page__table :deep(.is-editing .ant-input-affix-wrapper) {
  background: var(--mmui-card-action-bg) !important;
}

.port-page__number {
  width: 100%;
}

.port-page__number-with-action :deep(.ant-input-number-input) {
  padding-right: 36px;
}

:deep(.port-page__port-candidate-popup .ant-select-item-option-content) {
  color: var(--mmui-text);
  font-size: var(--mmui-font-size-footnote);
}

.port-page__dice-btn {
  position: absolute;
  top: 50%;
  right: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  padding: 0;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--mmui-text-muted);
  transform: translateY(-50%);
  cursor: pointer;
  transition: color 0.2s ease, background-color 0.2s ease;
}

.port-page__dice-btn:hover {
  background: rgba(var(--mmui-accent-blue-rgb), 0.12);
  color: var(--mmui-accent-blue);
}

.port-page__dice-btn svg {
  width: 16px;
  height: 16px;
  fill: currentColor;
}

.port-page__table :deep(.ant-btn-sm) {
  height: 32px;
  min-width: 56px;
  padding: 0 12px;
  border-radius: 10px;
}

.port-page__table :deep(.ant-tag) {
  margin-right: 0;
  margin-inline-end: 0;
  border-radius: 999px;
}

.port-page__note-stage > .port-page__note {
  grid-area: 1 / 1;
}

.port-note-panel-enter-active {
  transition:
    max-height 0.34s cubic-bezier(0.22, 1, 0.36, 1),
    padding-bottom 0.34s cubic-bezier(0.22, 1, 0.36, 1),
    opacity 0.2s ease-out,
    transform 0.24s ease-out;
  will-change: max-height, padding-bottom, opacity, transform;
}

.port-note-panel-leave-active {
  transition:
    max-height 0.28s cubic-bezier(0.22, 1, 0.36, 1),
    padding-bottom 0.28s cubic-bezier(0.22, 1, 0.36, 1),
    opacity 0.18s ease-out,
    transform 0.2s ease-out;
  will-change: max-height, padding-bottom, opacity, transform;
}

.port-note-panel-enter-from,
.port-note-panel-leave-to {
  max-height: 0;
  padding-bottom: 0;
  opacity: 0;
  transform: translateY(-6px);
}

.port-note-panel-enter-to,
.port-note-panel-leave-from {
  max-height: 180px;
  padding-bottom: 8px;
  opacity: 1;
  transform: translateY(0);
}

.port-note-swap-enter-active,
.port-note-swap-leave-active {
  transition: opacity 0.2s ease-out, transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), filter 0.2s ease-out;
  transform-origin: left center;
}

.port-note-swap-enter-from {
  opacity: 0;
  filter: blur(3px);
  transform: translateX(14px);
}

.port-note-swap-leave-to {
  opacity: 0;
  filter: blur(2px);
  transform: translateX(-10px);
}

.port-edit-open-enter-active,
.port-edit-open-leave-active,
.port-edit-close-enter-active,
.port-edit-close-leave-active {
  transition:
    transform 220ms cubic-bezier(0.22, 1, 0.36, 1),
    opacity 180ms ease-out,
    filter 180ms ease-out;
  will-change: transform, opacity, filter;
}

.port-edit-open-enter-from.port-page__mobile-panel,
.port-edit-close-leave-to.port-page__mobile-panel {
  opacity: 0;
  filter: blur(2px);
  transform: translateY(10px) scale(0.992);
}

.port-edit-open-enter-to.port-page__mobile-panel,
.port-edit-close-leave-from.port-page__mobile-panel,
.port-edit-open-leave-from.port-page__mobile-panel,
.port-edit-close-enter-to.port-page__mobile-panel {
  opacity: 1;
  filter: blur(0);
  transform: translateY(0) scale(1);
}

.port-edit-open-leave-to.port-page__mobile-panel,
.port-edit-close-enter-from.port-page__mobile-panel {
  opacity: 0;
  filter: blur(1.5px);
  transform: translateY(-6px) scale(0.996);
}

.port-edit-open-enter-active.port-cell-control,
.port-edit-close-leave-active.port-cell-control {
  z-index: 2;
}

.port-edit-open-leave-active.port-cell-text,
.port-edit-close-enter-active.port-cell-text {
  z-index: 1;
}

.port-edit-open-enter-from.port-cell-control {
  opacity: 0;
  filter: blur(1.5px);
  transform: translateX(4px) scale(0.998);
}

.port-edit-open-enter-to.port-cell-control,
.port-edit-close-leave-from.port-cell-control {
  opacity: 1;
  filter: blur(0);
  transform: translateX(0) scale(1);
}

.port-edit-open-leave-from.port-cell-text,
.port-edit-close-enter-to.port-cell-text {
  opacity: 1;
  filter: none;
  transform: translateX(0);
}

.port-edit-open-leave-to.port-cell-text {
  opacity: 0;
  filter: none;
  transform: translateX(-4px);
}

.port-edit-close-enter-from.port-cell-text {
  opacity: 0;
  filter: none;
  transform: translateX(-4px);
}

.port-edit-close-leave-to.port-cell-control {
  opacity: 0;
  filter: blur(1.5px);
  transform: translateX(4px) scale(0.998);
}

.port-scroll-hint-enter-active,
.port-scroll-hint-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.port-scroll-hint-enter-from,
.port-scroll-hint-leave-to {
  opacity: 0;
  transform: translateX(14px);
}

@keyframes port-scroll-nudge {
  0%,
  100% {
    transform: translateX(0);
  }

  50% {
    transform: translateX(-12px);
  }
}

@keyframes port-row-reveal {
  0% {
    opacity: 0;
    transform: translateY(-12px);
  }

  100% {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes port-card-appear {
  0% {
    opacity: 0;
    transform: translateY(-20px) scale(0.982);
  }

  52% {
    opacity: 1;
  }

  100% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@keyframes port-edit-activate {
  0% {
    opacity: 0.72;
    transform: translateY(8px);
  }

  100% {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes port-desktop-row-settle {
  0% {
    background: rgba(var(--mmui-accent-blue-rgb), 0.16);
  }

  100% {
    background: var(--mmui-sidebar-hover);
  }
}

@keyframes port-card-sheen {
  0% {
    opacity: 0;
    transform: translateX(-18%);
  }

  38% {
    opacity: 1;
  }

  100% {
    opacity: 0;
    transform: translateX(18%);
  }
}

.port-list-enter-active,
.port-list-leave-active {
  overflow: hidden;
  transition:
    max-height 0.36s cubic-bezier(0.22, 1, 0.36, 1),
    padding-top 0.36s cubic-bezier(0.22, 1, 0.36, 1),
    padding-bottom 0.36s cubic-bezier(0.22, 1, 0.36, 1),
    opacity 0.18s ease-out,
    transform 0.34s cubic-bezier(0.22, 1, 0.36, 1);
}

.port-list-move {
  transition: transform 0.34s cubic-bezier(0.22, 1, 0.36, 1);
}

.port-list-enter-from,
.port-list-leave-to {
  max-height: 0;
  opacity: 0;
  padding-top: 0;
  padding-bottom: 0;
  transform: translateY(-10px) scale(0.992);
}

.port-list-enter-to,
.port-list-leave-from {
  max-height: 620px;
  opacity: 1;
  padding-top: 16px;
  padding-bottom: 16px;
  transform: translateY(0) scale(1);
}

@media (max-width: 1023px) {
  .port-page__title {
    padding-left: 0;
  }

  .port-page__toolbar {
    flex-shrink: 0;
  }
}

@media (max-width: 640px) {
  .port-page__panel-shell.is-compact {
    margin: 0 0 12px;
    border-color: transparent !important;
    background: transparent !important;
    box-shadow: none !important;
  }

  .port-page__panel-shell.is-compact :deep(.ant-card-body) {
    padding: 0;
  }

  .port-page :deep(.resource-hero) {
    margin-bottom: 18px;
  }

  .port-page__filters-shell {
    display: flex !important;
    align-items: stretch !important;
    flex-direction: column;
    gap: 10px;
  }

  .port-page__search-wrap {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) 44px;
    gap: 10px;
    width: 100%;
    flex: none;
  }

  .port-page__search {
    width: 100%;
  }

  .port-page__toolbar {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px !important;
    width: 100%;
    margin-left: 0;
  }

  .port-page__toolbar :deep(.ant-space-item) {
    width: 100%;
    min-width: 0;
  }

  .port-page__toolbar-btn {
    width: 100%;
    min-width: 0;
    padding-inline: 10px;
    white-space: nowrap;
  }

  .port-page__toolbar-btn--icon {
    width: 44px;
    min-width: 44px;
    padding-inline: 0;
  }

  .port-page__table-shell.is-compact {
    border-top: 0;
    transition: none;
  }

  .port-page__mobile-list {
    border: 1px solid var(--mmui-shell-border);
    border-radius: 10px;
    background: var(--mmui-card-surface);
    overflow: hidden;
  }

  .port-page__mobile-label {
    white-space: nowrap;
  }

  .port-page__mobile-pagination {
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    text-align: center;
  }

  .port-page__mobile-pagination :deep(.ant-pagination-total-text) {
    width: 100%;
    margin-right: 0;
    text-align: center;
  }
}

</style>

