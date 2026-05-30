<template>
  <section ref="pageRootRef" class="system-page">
    <a-row :gutter="[0, 0]">
      <a-col :span="24">
        <div class="system-page__header-shell">
          <a-flex class="system-page__header" align="center" justify="space-between">
            <div class="system-page__title-wrap">
              <div class="system-page__title">系统管理</div>
            </div>
            <a-flex class="system-page__summary" align="center" :gap="12">
              <span class="system-page__summary-label">当日重装</span>
              <span class="system-page__summary-value">{{ reinstallQuotaText }}</span>
              <a-tooltip title="电源、重装与启动项变更都可能影响业务，请确认后再执行。">
                <a-button class="system-page__summary-help" type="text" shape="circle" aria-label="系统管理说明">
                  <InfoCircleOutlined />
                </a-button>
              </a-tooltip>
            </a-flex>
          </a-flex>
        </div>
      </a-col>

      <a-col :span="24">
        <div class="system-page__notice">
          <a-alert
            message="系统操作包含电源管理、系统重装与启动项管理。涉及重启或关机的动作会中断当前业务连接。"
            type="info"
            show-icon
            closable
          />
        </div>
      </a-col>

      <a-col :span="24">
        <div class="system-page__grid">
          <a-card class="system-page__card" :class="{ 'is-current': isPowerCurrent }">
            <template #title>
              <div class="system-page__card-title-row">
                <span>电源管理</span>
                <a-tag
                  class="system-page__status-tag"
                  :class="`is-${powerStatusTone}`"
                >
                  {{ powerStatusText }}
                </a-tag>
              </div>
            </template>
            <div class="system-page__card-copy">
              <div class="system-page__overview-row">
                <span>实例名称</span>
                <strong>{{ host.name || '-' }}</strong>
              </div>
              <div class="system-page__overview-row">
                <span>当前状态</span>
                <strong class="system-page__status-indicator" :class="`is-${powerStatusTone}`">
                  <i></i>
                  {{ powerStatusText }}
                </strong>
              </div>
              <div class="system-page__overview-row">
                <span>远程地址</span>
                <strong>{{ host.remoteAddress || '-' }}</strong>
              </div>
            </div>

            <div class="system-page__action-buttons system-page__action-buttons--triple system-page__action-buttons--footer">
              <a-button
                type="primary"
                :disabled="isAnyPowerActionLoading"
                @click="handlePowerAction('boot')"
              >
                <LoadingOutlined v-if="isActionLoading('system:power:boot')" />
                <ThunderboltOutlined v-else />
                启动
              </a-button>
              <a-button
                :disabled="isAnyPowerActionLoading"
                @click="handlePowerAction('reboot')"
              >
                <LoadingOutlined v-if="isActionLoading('system:power:reboot')" />
                <ReloadOutlined v-else />
                重启
              </a-button>
              <a-button
                danger
                :disabled="isAnyPowerActionLoading"
                @click="handlePowerAction('shutdown')"
              >
                <LoadingOutlined v-if="isActionLoading('system:power:shutdown')" />
                <PoweroffOutlined v-else />
                关机
              </a-button>
            </div>
          </a-card>

          <a-card class="system-page__card" :class="{ 'is-current': isReinstallCurrent }">
            <template #title>
              <div class="system-page__card-title-row">
                <span>重装系统</span>
              </div>
            </template>
            <template #extra>
              <span class="system-page__card-extra">{{ reinstallQuotaText }}</span>
            </template>

            <div class="system-page__card-copy">
              <div class="system-page__overview-row">
                <span>当前系统</span>
                <strong>{{ host.osName || '-' }}</strong>
              </div>
              <div class="system-page__overview-row">
                <span>可选镜像</span>
                <strong>{{ reinstallOptionCount }} 个</strong>
              </div>
              <div class="system-page__overview-row">
                <span>默认密码</span>
                <div class="system-page__overview-value">
                  <strong class="system-page__mono">
                    {{ showSystemPassword ? (host.systemPassword || '-') : maskedSystemPassword }}
                  </strong>
                  <div class="system-page__overview-actions">
                    <a-tooltip :title="showSystemPassword ? '隐藏系统密码' : '查看系统密码'">
                      <a-button class="system-page__icon-btn" type="text" size="small" @click="showSystemPassword = !showSystemPassword">
                        <component :is="showSystemPassword ? EyeInvisibleOutlined : EyeOutlined" />
                      </a-button>
                    </a-tooltip>
                    <a-tooltip title="复制系统密码">
                      <a-button class="system-page__icon-btn" type="text" size="small" @click="copyValue(host.systemPassword, '系统密码')">
                        <CopyOutlined />
                      </a-button>
                    </a-tooltip>
                    <a-tooltip title="修改系统密码">
                      <a-button class="system-page__icon-btn" type="text" size="small" @click="openPasswordModal('system')">
                        <EditOutlined />
                      </a-button>
                    </a-tooltip>
                  </div>
                </div>
              </div>
            </div>

            <div class="system-page__action-buttons system-page__action-buttons--footer">
              <a-button type="primary" @click="openReinstallModal">
                <CloudSyncOutlined />
                开始重装
              </a-button>
            </div>
          </a-card>

          <a-card class="system-page__card" :class="{ 'is-current': isIsoCurrent }">
            <template #title>
              <div class="system-page__card-title-row">
                <span>启动项管理</span>
              </div>
            </template>
            <template #extra>
              <span class="system-page__card-extra">{{ currentBootType }}</span>
            </template>

            <div class="system-page__card-copy">
              <div class="system-page__overview-row">
                <span>启动方式</span>
                <strong>{{ currentBootType }}</strong>
              </div>
              <div class="system-page__overview-row">
                <span>系统镜像</span>
                <strong>{{ currentIsoName || '未挂载' }}</strong>
              </div>
              <div class="system-page__overview-row">
                <span>可选 ISO</span>
                <strong>{{ isoOptions.length }} 个</strong>
              </div>
              <p class="system-page__boot-note">
                选择 ISO 类型后可挂载镜像，保存后实例会按新的启动项进行引导。
              </p>
            </div>

            <div class="system-page__action-buttons system-page__action-buttons--pair system-page__action-buttons--footer">
              <a-button @click="openBootModal">
                <SwapOutlined />
                切换启动项
              </a-button>
              <a-button
                type="primary"
                :disabled="isActionLoading('system:boot:save')"
                @click="saveBootMode"
              >
                <LoadingOutlined v-if="isActionLoading('system:boot:save')" />
                <SaveOutlined v-else />
                保存当前设置
              </a-button>
            </div>
          </a-card>

          <a-card class="system-page__card">
            <template #title>
              <div class="system-page__card-title-row">
                <span>密码与时间</span>
              </div>
            </template>
            <template #extra>
              <span class="system-page__card-extra">维护</span>
            </template>

            <div class="system-page__card-copy">
              <div class="system-page__overview-row">
                <span>系统密码</span>
                <div class="system-page__overview-value">
                  <strong class="system-page__mono">
                    {{ showSystemPassword ? (host.systemPassword || '-') : maskedSystemPassword }}
                  </strong>
                  <div class="system-page__overview-actions">
                    <a-tooltip title="复制系统密码">
                      <a-button class="system-page__icon-btn" type="text" size="small" @click="copyValue(host.systemPassword, '系统密码')">
                        <CopyOutlined />
                      </a-button>
                    </a-tooltip>
                    <a-tooltip :title="showSystemPassword ? '隐藏系统密码' : '查看系统密码'">
                      <a-button class="system-page__icon-btn" type="text" size="small" @click="showSystemPassword = !showSystemPassword">
                        <component :is="showSystemPassword ? EyeInvisibleOutlined : EyeOutlined" />
                      </a-button>
                    </a-tooltip>
                  </div>
                </div>
              </div>
              <div class="system-page__overview-row">
                <span>面板密码</span>
                <div class="system-page__overview-value">
                  <strong class="system-page__mono">
                    {{ showPanelPassword ? (host.panelPassword || '-') : maskedPanelPassword }}
                  </strong>
                  <div class="system-page__overview-actions">
                    <a-tooltip title="复制面板密码">
                      <a-button class="system-page__icon-btn" type="text" size="small" @click="copyValue(host.panelPassword, '面板密码')">
                        <CopyOutlined />
                      </a-button>
                    </a-tooltip>
                    <a-tooltip :title="showPanelPassword ? '隐藏面板密码' : '查看面板密码'">
                      <a-button class="system-page__icon-btn" type="text" size="small" @click="showPanelPassword = !showPanelPassword">
                        <component :is="showPanelPassword ? EyeInvisibleOutlined : EyeOutlined" />
                      </a-button>
                    </a-tooltip>
                  </div>
                </div>
              </div>
              <div class="system-page__overview-row">
                <span>时间同步</span>
                <div class="system-page__overview-value">
                  <strong>{{ syncTimeSupportText }}</strong>
                  <a-button
                    class="system-page__sync-inline-btn"
                    type="primary"
                    size="small"
                    :disabled="isActionLoading('system:sync-time')"
                    @click="submitSyncTime"
                  >
                    <LoadingOutlined v-if="isActionLoading('system:sync-time')" />
                    <ClockCircleOutlined v-else />
                    同步
                  </a-button>
                </div>
              </div>
            </div>
          </a-card>
        </div>
      </a-col>
    </a-row>

    <a-modal
      v-model:open="reinstallModalOpen"
      :title="null"
      :closable="false"
      :footer="null"
      :destroyOnClose="false"
      :width="720"
      wrap-class-name="system-page__modal-wrap"
      centered
      class="system-page__modal"
    >
      <div class="system-page__modal-body system-page__reinstall-modal">
        <div class="system-page__modal-head">
          <span class="system-page__modal-icon" aria-hidden="true">
            <CloudSyncOutlined />
          </span>
          <div class="system-page__modal-title-block">
            <strong>{{ reinstallStep === 0 ? '重装系统' : '设置新密码' }}</strong>
            <small>{{ reinstallStep === 0 ? '选择系统类型和具体镜像' : '确认目标系统并设置登录密码' }}</small>
          </div>
          <a-button class="system-page__modal-close" type="text" shape="circle" aria-label="关闭重装弹窗" @click="closeReinstallModal">
            <CloseOutlined />
          </a-button>
        </div>

        <div class="system-page__step-line" aria-label="重装步骤">
          <div class="system-page__step-node" :class="{ 'is-active': reinstallStep === 0, 'is-done': reinstallStep > 0 }">
            <span>1</span>
            <strong>选择系统</strong>
          </div>
          <i aria-hidden="true"></i>
          <div class="system-page__step-node" :class="{ 'is-active': reinstallStep === 1 }">
            <span>2</span>
            <strong>设置密码</strong>
          </div>
        </div>

        <template v-if="reinstallStep === 0">
          <div class="system-page__reinstall-picker">
            <div class="system-page__family-tabs" aria-label="系统类型">
              <button
                v-for="family in reinstallFamilies"
                :key="family.title"
                type="button"
                class="system-page__family-tab"
                :class="{ 'is-active': family.title === selectedReinstallFamilyTitle }"
                :aria-pressed="family.title === selectedReinstallFamilyTitle"
                @click="selectReinstallFamily(family.title)"
              >
                <span
                  class="system-page__family-glyph"
                  :class="{ 'is-windows': resolveReinstallFamilyType(family) === 'windows' }"
                  aria-hidden="true"
                >
                  <svg v-if="resolveReinstallFamilyType(family) === 'windows'" viewBox="0 0 24 24" role="img" aria-label="Windows">
                    <rect x="3" y="4" width="8" height="7" rx="0.9" />
                    <rect x="13" y="4" width="8" height="7" rx="0.9" />
                    <rect x="3" y="13" width="8" height="7" rx="0.9" />
                    <rect x="13" y="13" width="8" height="7" rx="0.9" />
                  </svg>
                  <template v-else>{{ family.glyph || family.title.slice(0, 1) }}</template>
                </span>
                <span class="system-page__family-copy">
                  <strong>{{ family.title }}</strong>
                  <small>{{ family.subtitle || `${family.options?.length || 0} 个镜像` }}</small>
                </span>
                <CheckCircleFilled v-if="family.title === selectedReinstallFamilyTitle" class="system-page__family-check" />
              </button>
            </div>

            <div class="system-page__image-panel">
              <div class="system-page__image-panel-head">
                <span>具体系统</span>
                <strong>{{ activeReinstallFamily?.title || '-' }}</strong>
              </div>

              <div v-if="activeReinstallOptions.length" class="system-page__option-list" aria-label="具体系统镜像">
                <button
                  v-for="option in activeReinstallOptions"
                  :key="option"
                  type="button"
                  class="system-page__option-item"
                  :class="{ 'is-active': option === selectedReinstallOption }"
                  :aria-pressed="option === selectedReinstallOption"
                  @click="selectReinstallOption(option)"
                >
                  <span class="system-page__option-main">
                    <strong>{{ option }}</strong>
                    <small>{{ activeReinstallFamily?.description || '选择后将进入密码设置' }}</small>
                  </span>
                  <span class="system-page__option-check" aria-hidden="true">
                    <CheckCircleFilled v-if="option === selectedReinstallOption" />
                  </span>
                </button>
              </div>

              <a-empty v-else class="system-page__option-empty" description="暂无可选镜像" />
            </div>
          </div>
        </template>

        <template v-else>
          <div class="system-page__confirm">
            <div class="system-page__confirm-row">
              <span>目标系统</span>
              <strong>{{ selectedReinstallOption || '-' }}</strong>
            </div>
            <div class="system-page__confirm-row">
              <span>新密码</span>
              <a-input-password
                v-model:value="reinstallPassword"
                class="system-page__password-field"
                placeholder="请输入新的系统密码"
              />
            </div>
            <div class="system-page__confirm-help">
              默认已带入当前系统密码，你可以直接使用，也可以在这里改成新的密码。
            </div>
          </div>
        </template>

        <div class="system-page__modal-actions">
          <a-button :disabled="isActionLoading('system:reinstall')" @click="closeReinstallModal">取消</a-button>
          <a-button v-if="reinstallStep === 1" @click="reinstallStep = 0">上一步</a-button>
          <a-button
            v-if="reinstallStep === 0"
            type="primary"
            @click="goReinstallNextStep"
          >
            下一步
          </a-button>
          <a-button
            v-else
            type="primary"
            :loading="isActionLoading('system:reinstall')"
            :disabled="isActionLoading('system:reinstall')"
            @click="submitReinstall"
          >
            确认重装
          </a-button>
        </div>
      </div>
    </a-modal>

    <a-modal
      v-model:open="bootModalOpen"
      title="切换启动项"
      :footer="null"
      wrap-class-name="system-page__modal-wrap"
      centered
      class="system-page__modal"
    >
      <div class="system-page__modal-body">
        <div class="system-page__confirm">
          <div class="system-page__confirm-row">
            <span>启动方式</span>
            <a-select v-model:value="draftBootType" class="system-page__boot-field">
              <a-select-option v-for="option in bootTypeOptions" :key="option" :value="option">{{ option }}</a-select-option>
            </a-select>
          </div>
          <div v-if="requiresIsoSelection" class="system-page__confirm-row">
            <span>ISO 镜像</span>
            <a-select v-model:value="draftIsoName" class="system-page__boot-field" placeholder="请选择镜像">
              <a-select-option v-for="option in isoOptions" :key="option" :value="option">{{ option }}</a-select-option>
            </a-select>
          </div>
        </div>

        <div class="system-page__modal-actions">
          <a-button @click="bootModalOpen = false">取消</a-button>
          <a-button
            type="primary"
            :loading="isActionLoading('system:boot:modal')"
            :disabled="isActionLoading('system:boot:modal')"
            @click="submitBootModal"
          >
            保存
          </a-button>
        </div>
      </div>
    </a-modal>

    <a-modal
      v-model:open="passwordModalOpen"
      :title="passwordModalTitle"
      :footer="null"
      wrap-class-name="system-page__modal-wrap"
      centered
      class="system-page__modal"
    >
      <div class="system-page__modal-body">
        <div class="system-page__confirm">
          <div class="system-page__confirm-row">
            <span>{{ passwordMode === 'panel' ? '面板密码' : '系统密码' }}</span>
            <a-input-password
              v-model:value="passwordDraft"
              class="system-page__password-field"
              :maxlength="passwordMaxLength"
              :placeholder="passwordPlaceholder"
              @pressEnter="submitPasswordUpdate"
            />
          </div>
          <div class="system-page__confirm-help">
            {{ passwordRuleText }}
          </div>
        </div>

        <div class="system-page__modal-actions">
          <a-button @click="passwordModalOpen = false">取消</a-button>
          <a-button
            type="primary"
            :loading="isActionLoading(`system:password:${passwordMode}`)"
            :disabled="isActionLoading(`system:password:${passwordMode}`)"
            @click="submitPasswordUpdate"
          >
            保存
          </a-button>
        </div>
      </div>
    </a-modal>
  </section>
</template>

<script setup>
import {
  CheckCircleFilled,
  ClockCircleOutlined,
  CloudSyncOutlined,
  CloseOutlined,
  CopyOutlined,
  EditOutlined,
  EyeInvisibleOutlined,
  EyeOutlined,
  InfoCircleOutlined,
  LoadingOutlined,
  PoweroffOutlined,
  ReloadOutlined,
  SaveOutlined,
  SwapOutlined,
  ThunderboltOutlined,
} from '@ant-design/icons-vue';
import { message } from 'ant-design-vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useCompactPageMode } from '@/components/pages/useCompactPageMode';
import { useDashboardStore } from '@/stores/dashboard';
import { pinia } from '@/stores/pinia';
import { useActionLocks } from '@/composables/useActionLocks';

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

const { pageRootRef } = useCompactPageMode();
const store = useDashboardStore(pinia);
const { isActionLoading, runWithActionLoading } = useActionLocks();

const powerState = ref('');
const reinstallModalOpen = ref(false);
const reinstallStep = ref(0);
const selectedReinstallFamilyTitle = ref('');
const selectedReinstallOption = ref('');
const reinstallPassword = ref('');
const bootModalOpen = ref(false);
const currentBootType = ref('');
const currentIsoName = ref('');
const draftBootType = ref('');
const draftIsoName = ref('');
const passwordModalOpen = ref(false);
const passwordMode = ref('system');
const passwordDraft = ref('');
const showSystemPassword = ref(false);
const showPanelPassword = ref(false);
let powerPollTimer = 0;
let powerPollToken = 0;
let isoLoadStarted = false;

const host = computed(() => store.host || {});
const reinstallQuotaText = computed(() => props.pages?.reinstall?.quota || '0/0');
const reinstallFamilies = computed(() => (
  Array.isArray(props.pages?.reinstall?.cards) ? props.pages.reinstall.cards : []
));
const reinstallOptionCount = computed(() => reinstallFamilies.value.reduce((sum, family) => sum + (family.options?.length || 0), 0));
const bootTypeOptions = computed(() => (
  Array.isArray(props.pages?.iso?.bootOptions) && props.pages.iso.bootOptions.length
    ? props.pages.iso.bootOptions
    : ['IDE', 'CD']
));
const isoOptions = computed(() => (
  Array.isArray(props.pages?.iso?.isoOptions) ? props.pages.iso.isoOptions : []
));
const activeReinstallFamily = computed(() => (
  reinstallFamilies.value.find((family) => family.title === selectedReinstallFamilyTitle.value) || reinstallFamilies.value[0] || null
));
const activeReinstallOptions = computed(() => activeReinstallFamily.value?.options || []);
const requiresIsoSelection = computed(() => draftBootType.value !== 'IDE');
const powerStatusText = computed(() => {
  const status = String(powerState.value || host.value.status || '').trim();
  return status || '未知';
});
const powerStatusTone = computed(() => {
  const kind = resolveHostStatusKind({
    ...host.value,
    status: powerStatusText.value,
  });
  if (kind === 'running') return 'success';
  if (kind === 'stopped') return 'danger';
  if (kind === 'error') return 'error';
  if (kind === 'pending') return 'warning';
  return 'neutral';
});
const isPowerCurrent = computed(() => props.pageKey === 'power');
const isReinstallCurrent = computed(() => props.pageKey === 'reinstall');
const isIsoCurrent = computed(() => props.pageKey === 'iso');
const isAnyPowerActionLoading = computed(() => (
  isActionLoading('system:power:boot')
  || isActionLoading('system:power:reboot')
  || isActionLoading('system:power:shutdown')
));
const passwordModalTitle = computed(() => (passwordMode.value === 'panel' ? '修改面板密码' : '修改系统密码'));
const passwordMaxLength = computed(() => (passwordMode.value === 'panel' ? 12 : 20));
const passwordPlaceholder = computed(() => (
  passwordMode.value === 'panel'
    ? '6-12 位数字、字母、下划线或短横线'
    : '8-20 位，至少三类字符'
));
const passwordRuleText = computed(() => (
  passwordMode.value === 'panel'
    ? '轻舟控制台密码规则：6-12 个数字、字母、下划线或短横线组合。'
    : '轻舟系统密码规则：8-20 个字符，至少包含大写字母、小写字母、数字、特殊符号中的三类。'
));
const maskedSystemPassword = computed(() => maskValue(host.value.systemPassword));
const maskedPanelPassword = computed(() => maskValue(host.value.panelPassword));
const isKvmHost = computed(() => String(host.value.virtualType || '').toLowerCase().includes('kvm'));
const syncTimeSupportText = computed(() => (isKvmHost.value ? 'KVM 未提供' : '按需执行'));

function resolveReinstallFamilyType(family) {
  const text = [
    family?.type,
    family?.key,
    family?.title,
    family?.subtitle,
    family?.glyph,
  ].filter(Boolean).join(' ').toLowerCase();

  if (text.includes('windows') || text.includes('win')) return 'windows';
  return 'generic';
}

function resolveHostStatusKind(hostInfo) {
  const stateCode = Number(hostInfo?.state);
  const state = String(hostInfo?.powerState || '').toLowerCase();
  const status = String(hostInfo?.status || '').toLowerCase();

  if (
    ['error', 'failed', 'failure', 'unknown_error'].includes(state)
    || status.includes('异常')
    || status.includes('失败')
    || status.includes('错误')
  ) {
    return 'error';
  }

  if (
    ['pending', 'starting', 'stopping', 'rebooting', 'reinstalling'].includes(state)
    || status.includes('开机')
    || status.includes('启动')
    || status.includes('关机中')
    || status.includes('重启')
    || status.includes('重装')
    || status.includes('处理中')
    || status.includes('等待')
  ) {
    return 'pending';
  }

  if (stateCode === 2 || state === 'running' || status.includes('运行')) {
    return 'running';
  }

  if (
    stateCode === 3
    || ['stopped', 'shutdown', 'poweroff', 'closed'].includes(state)
    || status.includes('关机')
    || status.includes('关闭')
    || status.includes('停止')
  ) {
    return 'stopped';
  }

  return 'unknown';
}

function getPowerTransitionStatus(action) {
  const map = {
    boot: { status: '开机中', powerState: 'starting' },
    start: { status: '开机中', powerState: 'starting' },
    shutdown: { status: '关机中', powerState: 'stopping' },
    close: { status: '关机中', powerState: 'stopping' },
    reboot: { status: '重启中', powerState: 'rebooting' },
    restart: { status: '重启中', powerState: 'rebooting' },
  };

  return map[action] || null;
}

function hasReachedPowerTransitionTarget(action, samples) {
  const kind = resolveHostStatusKind(host.value);
  if (kind === 'pending' || samples < 2) {
    return false;
  }

  if (action === 'shutdown' || action === 'close') {
    return kind === 'stopped' || kind === 'error';
  }

  return kind === 'running' || kind === 'error';
}

function stopPowerStatusPolling() {
  window.clearTimeout(powerPollTimer);
  powerPollTimer = 0;
  powerPollToken += 1;
}

function startPowerStatusPolling(action) {
  const token = ++powerPollToken;
  let samples = 0;

  window.clearTimeout(powerPollTimer);

  const tick = async () => {
    if (token !== powerPollToken) {
      return;
    }

    samples += 1;
    try {
      await store.refreshState();
    } catch (error) {
      console.debug('MMUI power status polling failed.', error);
    }

    if (token !== powerPollToken) {
      return;
    }

    if (hasReachedPowerTransitionTarget(action, samples) || samples >= 36) {
      powerPollTimer = 0;
      return;
    }

    powerPollTimer = window.setTimeout(tick, 1600);
  };

  powerPollTimer = window.setTimeout(tick, 250);
}

function beginPowerTransition(action) {
  const status = getPowerTransitionStatus(action);
  if (status) {
    store.setHostRuntimeStatus(status);
  }
  startPowerStatusPolling(action);
}

watch(
  () => host.value.status,
  (value) => {
    powerState.value = String(value || '').trim();
  },
  { immediate: true },
);

watch(
  () => props.pages?.iso,
  (page) => {
    currentBootType.value = page?.currentBoot || page?.bootType || bootTypeOptions.value[0] || 'IDE';
    currentIsoName.value = page?.currentIso || page?.isoName || '';
    draftBootType.value = currentBootType.value;
    draftIsoName.value = currentIsoName.value;
  },
  { immediate: true, deep: true },
);

watch(
  () => props.bootModalRequest,
  (value, oldValue) => {
    if (!value || value === oldValue) {
      return;
    }

    openBootModal();
    emit('boot-modal-request-consumed');
  },
  { immediate: true },
);

watch(
  () => props.pageKey,
  (value) => {
    if ((value === 'iso' || value === 'system-control') && !isoLoadStarted) {
      loadIsoOptionsSilently();
    }
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  stopPowerStatusPolling();
});

async function handlePowerAction(action) {
  await runWithActionLoading(`system:power:${action}`, async () => {
    try {
      beginPowerTransition(action);
      await store.powerAction(action);
      startPowerStatusPolling(action);
      message.success(action === 'boot' ? '开机请求已发送' : action === 'reboot' ? '重启请求已发送' : '关机请求已发送');
    } catch (error) {
      stopPowerStatusPolling();
      try {
        await store.refreshState();
      } catch (refreshError) {
        console.debug('MMUI power status refresh failed.', refreshError);
      }
      message.error(error instanceof Error ? error.message : '电源操作失败');
    }
  });
}

function openReinstallModal() {
  reinstallModalOpen.value = true;
  reinstallStep.value = 0;
  selectedReinstallFamilyTitle.value = reinstallFamilies.value[0]?.title || '';
  selectedReinstallOption.value = reinstallFamilies.value[0]?.options?.[0] || '';
  reinstallPassword.value = host.value.systemPassword || '';
}

function closeReinstallModal() {
  reinstallModalOpen.value = false;
  reinstallStep.value = 0;
}

function selectReinstallFamily(title) {
  selectedReinstallFamilyTitle.value = title;
  const target = reinstallFamilies.value.find((family) => family.title === title);
  selectedReinstallOption.value = target?.options?.[0] || '';
}

function selectReinstallOption(option) {
  selectedReinstallOption.value = option;
}

function goReinstallNextStep() {
  if (!selectedReinstallOption.value) {
    message.warning('请先选择系统镜像');
    return;
  }

  reinstallStep.value = 1;
}

async function submitReinstall() {
  const errorMessage = validateSystemPassword(reinstallPassword.value);
  if (errorMessage) {
    message.warning(errorMessage);
    return;
  }

  await runWithActionLoading('system:reinstall', async () => {
    try {
      await store.reinstallSystem({
        image: selectedReinstallOption.value,
        password: reinstallPassword.value,
      });
      message.success(`已提交重装请求：${selectedReinstallOption.value}`);
      reinstallModalOpen.value = false;
      reinstallStep.value = 0;
    } catch (error) {
      message.error(error instanceof Error ? error.message : '提交重装请求失败');
    }
  });
}

function openBootModal() {
  loadIsoOptionsSilently();
  draftBootType.value = currentBootType.value;
  draftIsoName.value = currentIsoName.value;
  bootModalOpen.value = true;
}

async function loadIsoOptionsSilently() {
  if (isoLoadStarted || (Array.isArray(props.pages?.iso?.isoOptions) && props.pages.iso.isoOptions.length > 0)) {
    return;
  }

  isoLoadStarted = true;
  try {
    await store.refreshIsoOptions();
  } catch (error) {
    console.debug('MMUI ISO list preload failed.', error);
  }
}

async function submitBootModal() {
  if (requiresIsoSelection.value && !draftIsoName.value) {
    message.warning('请选择 ISO 镜像');
    return;
  }

  await runWithActionLoading('system:boot:modal', async () => {
    try {
      await store.setBootMode({
        bootType: draftBootType.value,
        isoPath: requiresIsoSelection.value ? draftIsoName.value : '',
      });
      currentBootType.value = draftBootType.value;
      currentIsoName.value = requiresIsoSelection.value ? draftIsoName.value : '';
      bootModalOpen.value = false;
      message.success('启动项已更新');
    } catch (error) {
      message.error(error instanceof Error ? error.message : '启动项更新失败');
    }
  });
}

async function saveBootMode() {
  await runWithActionLoading('system:boot:save', async () => {
    try {
      await store.setBootMode({
        bootType: currentBootType.value,
        isoPath: currentIsoName.value,
      });
      message.success(`已保存当前启动项：${currentBootType.value}`);
    } catch (error) {
      message.error(error instanceof Error ? error.message : '保存启动项失败');
    }
  });
}

function openPasswordModal(mode) {
  passwordMode.value = mode === 'panel' ? 'panel' : 'system';
  passwordDraft.value = passwordMode.value === 'panel'
    ? (host.value.panelPassword || '')
    : (host.value.systemPassword || '');
  passwordModalOpen.value = true;
}

function maskValue(value) {
  return String(value || '').trim() ? '•'.repeat(8) : '-';
}

async function copyValue(value, label) {
  const text = String(value || '').trim();
  if (!text || text === '-') {
    message.warning(`暂无可复制的${label}`);
    return;
  }

  try {
    await navigator.clipboard.writeText(text);
    message.success(`已复制${label}`);
  } catch {
    const input = document.createElement('textarea');
    input.value = text;
    input.setAttribute('readonly', 'readonly');
    input.style.position = 'fixed';
    input.style.left = '-9999px';
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    message.success(`已复制${label}`);
  }
}

async function submitPasswordUpdate() {
  const nextPassword = String(passwordDraft.value || '').trim();
  const errorMessage = validatePasswordByMode(passwordMode.value, nextPassword);
  if (errorMessage) {
    message.warning(errorMessage);
    return;
  }

  await runWithActionLoading(`system:password:${passwordMode.value}`, async () => {
    try {
      if (passwordMode.value === 'panel') {
        await store.updatePanelPassword(nextPassword);
        message.success('面板密码已更新');
      } else {
        await store.updateSystemPassword(nextPassword);
        message.success('系统密码已更新');
      }
      passwordModalOpen.value = false;
    } catch (error) {
      message.error(error instanceof Error ? error.message : '密码更新失败');
    }
  });
}

function validatePanelPassword(value) {
  const text = String(value || '').trim();
  if (!/^[A-Za-z0-9_-]{6,12}$/.test(text)) {
    return '面板密码需为 6-12 位数字、字母、下划线或短横线组合';
  }
  return '';
}

function validateSystemPassword(value) {
  const text = String(value || '').trim();
  if (text.length < 8 || text.length > 20) {
    return '系统密码需为 8-20 个字符';
  }

  const classCount = [
    /[A-Z]/,
    /[a-z]/,
    /\d/,
    /[^A-Za-z0-9]/,
  ].filter((pattern) => pattern.test(text)).length;

  if (classCount < 3) {
    return '系统密码需至少包含大写字母、小写字母、数字、特殊符号中的三类';
  }

  return '';
}

function validatePasswordByMode(mode, value) {
  return mode === 'panel' ? validatePanelPassword(value) : validateSystemPassword(value);
}

async function submitSyncTime() {
  if (isKvmHost.value) {
    message.warning('轻舟 KVM 适配器未提供同步时间接口');
    return;
  }

  await runWithActionLoading('system:sync-time', async () => {
    try {
      await store.syncTime(true);
      message.success('同步时间命令已提交');
    } catch (error) {
      message.error(error instanceof Error ? error.message : '同步时间失败');
    }
  });
}
</script>

<style scoped>
.system-page {
  width: 100%;
}

.system-page__header-shell {
  margin: 8px 8px 18px;
}

.system-page__header {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 54px;
}

.system-page__title-wrap {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.system-page__title {
  padding-left: 8px;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-hero-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-page);
}

.system-page__summary {
  margin-left: auto;
  min-height: 42px;
  padding: 0 14px;
  border: 1px solid var(--mmui-shell-border);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.02);
}

.system-page__summary-label {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.system-page__summary-value {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.system-page__summary-help {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  min-width: 32px;
  height: 32px;
  padding: 0;
  color: var(--mmui-text-muted) !important;
  line-height: 1 !important;
}

.system-page__notice {
  margin: 0 8px 16px;
}

.system-page__grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  margin: 0 8px 12px;
}

.system-page__card {
  min-width: 0;
  height: 100%;
}

.system-page__card :deep(.ant-card-body) {
  display: flex;
  flex-direction: column;
  height: calc(100% - var(--system-card-head-height, 57px));
}

.system-page__card.is-current {
  border-color: color-mix(in srgb, var(--mmui-accent-blue) 24%, var(--mmui-shell-border)) !important;
}

.system-page__card-title-row {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.system-page__status-tag.ant-tag {
  margin: 0 !important;
  padding: 0 10px !important;
  font-size: var(--mmui-font-size-caption);
  line-height: 22px;
  border-radius: 999px;
}

.system-page__status-tag.is-success {
  color: #129a4d !important;
  background: rgba(21, 185, 104, 0.12) !important;
  border-color: rgba(21, 185, 104, 0.18) !important;
}

.system-page__status-tag.is-danger {
  color: #d9363e !important;
  background: rgba(255, 77, 79, 0.12) !important;
  border-color: rgba(255, 77, 79, 0.24) !important;
}

.system-page__status-tag.is-error {
  color: #ff4d4f !important;
  background: rgba(255, 77, 79, 0.16) !important;
  border-color: rgba(255, 77, 79, 0.32) !important;
}

.system-page__status-tag.is-warning {
  color: #d48806 !important;
  background: rgba(250, 173, 20, 0.14) !important;
  border-color: rgba(250, 173, 20, 0.24) !important;
}

.system-page__status-tag.is-neutral {
  color: rgba(255, 255, 255, 0.62) !important;
  background: rgba(255, 255, 255, 0.07) !important;
  border-color: rgba(255, 255, 255, 0.13) !important;
}

.system-page__card-extra {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
}

.system-page__card-copy {
  display: grid;
  align-content: start;
  flex: 1 1 auto;
}

.system-page__overview-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  min-height: var(--mmui-row-height);
  padding: 0;
  border-bottom: 1px solid color-mix(in srgb, var(--mmui-shell-border) 74%, transparent);
}

.system-page__overview-row:last-child {
  border-bottom: 0;
}

.system-page__overview-row > span {
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.system-page__overview-row strong {
  min-width: 0;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-body);
  font-weight: var(--mmui-text-body-emphasis-weight);
  text-align: right;
  line-height: var(--mmui-line-height-body);
  word-break: break-word;
  overflow-wrap: anywhere;
}

.system-page__status-indicator {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  width: fit-content;
  margin-left: auto;
  transition: color 180ms ease;
}

.system-page__status-indicator i {
  width: 10px;
  min-width: 10px;
  height: 10px;
  border-radius: 999px;
  background: currentColor;
  box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.07);
  transition:
    background 180ms ease,
    box-shadow 180ms ease;
}

.system-page__status-indicator.is-success {
  color: #129a4d;
}

.system-page__status-indicator.is-success i {
  box-shadow: 0 0 0 3px rgba(21, 185, 104, 0.14);
}

.system-page__status-indicator.is-danger {
  color: #d9363e;
}

.system-page__status-indicator.is-danger i {
  box-shadow: 0 0 0 3px rgba(255, 77, 79, 0.16);
}

.system-page__status-indicator.is-error {
  color: #ff4d4f;
}

.system-page__status-indicator.is-error i {
  box-shadow: 0 0 0 3px rgba(255, 77, 79, 0.22);
}

.system-page__status-indicator.is-warning {
  color: #d48806;
}

.system-page__status-indicator.is-warning i {
  box-shadow: 0 0 0 3px rgba(250, 173, 20, 0.18);
}

.system-page__status-indicator.is-neutral {
  color: rgba(255, 255, 255, 0.62);
}

.system-page__overview-value {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  min-width: 0;
}

.system-page__overview-value strong {
  text-align: right;
}

.system-page__overview-actions {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--mmui-space-1);
  flex: 0 0 auto;
  min-width: 0;
}

.system-page__icon-btn.ant-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  min-width: 24px;
  height: 24px;
  padding: 0;
  color: var(--mmui-text-muted) !important;
}

.system-page__icon-btn.ant-btn:hover {
  color: var(--mmui-card-title) !important;
  background: rgba(255, 255, 255, 0.06) !important;
}

.system-page__sync-inline-btn.ant-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 28px;
  padding-inline: 10px;
  border-radius: 7px;
  font-size: var(--mmui-font-size-caption);
  font-weight: var(--mmui-font-weight-semibold);
}

.system-page__mono {
  font-family: ui-monospace, Menlo, Consolas, monospace;
}

.system-page__inline-link.ant-btn {
  padding-inline: 0;
}

.system-page__action-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.system-page__action-buttons--triple {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.system-page__action-buttons--pair {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.system-page__action-buttons--footer {
  margin-top: auto;
  padding-top: 18px;
}

.system-page__boot-copy {
  display: grid;
  align-content: start;
}

.system-page__boot-note {
  margin: 14px 0 0;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

:global(.system-page__modal-wrap .ant-modal-body),
:global(.system-page__modal .ant-modal-body) {
  padding: 0;
}

.system-page__modal-body {
  display: grid;
  gap: 16px;
  min-width: 0;
  padding: 20px;
  background: var(--mmui-card-surface-elevated);
}

.system-page__modal-head {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;
}

.system-page__modal-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  min-width: 42px;
  height: 42px;
  color: #ffffff;
  border-radius: 8px;
  background: linear-gradient(145deg, var(--mmui-accent-blue), color-mix(in srgb, var(--mmui-accent-blue) 72%, #5b7cff));
  box-shadow: 0 10px 22px rgba(var(--mmui-accent-blue-rgb), 0.22);
}

.system-page__modal-icon :deep(.anticon) {
  font-size: var(--mmui-font-size-title);
}

.system-page__modal-title-block {
  display: grid;
  gap: 3px;
  min-width: 0;
}

.system-page__modal-title-block strong {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.system-page__modal-title-block small {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
}

.system-page__modal-close.ant-btn {
  color: var(--mmui-text-muted);
}

.system-page__step-line {
  display: grid;
  grid-template-columns: auto minmax(36px, 1fr) auto;
  align-items: center;
  gap: 12px;
  padding: 2px 0 4px;
}

.system-page__step-line > i {
  display: block;
  height: 1px;
  background: var(--mmui-shell-border);
}

.system-page__step-node {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  color: var(--mmui-text-soft);
}

.system-page__step-node span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  min-width: 28px;
  height: 28px;
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-caption);
  font-weight: var(--mmui-font-weight-semibold);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.06);
}

.system-page__step-node strong {
  font-size: var(--mmui-font-size-footnote);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-line-height-footnote);
}

.system-page__step-node.is-active,
.system-page__step-node.is-done {
  color: var(--mmui-card-title);
}

.system-page__step-node.is-active span,
.system-page__step-node.is-done span {
  color: #ffffff;
  background: var(--mmui-accent-blue);
  box-shadow: 0 8px 18px rgba(var(--mmui-accent-blue-rgb), 0.2);
}

.system-page__reinstall-picker {
  display: grid;
  grid-template-columns: minmax(190px, 0.72fr) minmax(0, 1.28fr);
  gap: 12px;
  min-width: 0;
}

.system-page__family-tabs,
.system-page__option-list {
  display: grid;
  align-content: start;
  gap: 8px;
  min-width: 0;
}

.system-page__family-tab,
.system-page__option-item {
  color: inherit;
  text-align: left;
  appearance: none;
  border: 1px solid color-mix(in srgb, var(--mmui-shell-border) 86%, transparent);
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.02);
  cursor: pointer;
  transition:
    border-color 0.16s ease,
    background-color 0.16s ease,
    box-shadow 0.18s ease,
    transform 0.18s ease;
}

.system-page__family-tab:hover,
.system-page__option-item:hover {
  border-color: color-mix(in srgb, var(--mmui-accent-blue) 36%, var(--mmui-shell-border));
  background: rgba(var(--mmui-accent-blue-rgb), 0.06);
}

.system-page__family-tab:focus-visible,
.system-page__option-item:focus-visible {
  outline: none;
  border-color: var(--mmui-accent-blue);
  box-shadow: 0 0 0 2px rgba(var(--mmui-accent-blue-rgb), 0.18);
}

.system-page__family-tab {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;
  min-height: 64px;
  padding: 10px;
}

.system-page__family-tab.is-active,
.system-page__option-item.is-active {
  border-color: color-mix(in srgb, var(--mmui-accent-blue) 72%, var(--mmui-shell-border));
  background: rgba(var(--mmui-accent-blue-rgb), 0.1);
  box-shadow: inset 0 0 0 1px rgba(var(--mmui-accent-blue-rgb), 0.12);
}

.system-page__family-glyph {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  min-width: 34px;
  height: 34px;
  color: var(--mmui-accent-blue);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-font-weight-semibold);
  border-radius: 8px;
  background: rgba(var(--mmui-accent-blue-rgb), 0.1);
}

.system-page__family-glyph.is-windows {
  color: #4f6bff;
}

.system-page__family-glyph svg {
  width: 20px;
  height: 20px;
  fill: currentColor;
}

.system-page__family-copy {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.system-page__family-copy strong,
.system-page__option-main strong {
  min-width: 0;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-body);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-line-height-body);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.system-page__family-copy small,
.system-page__option-main small {
  min-width: 0;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.system-page__family-check {
  color: var(--mmui-accent-blue);
  font-size: var(--mmui-font-size-body);
}

.system-page__image-panel {
  display: grid;
  align-content: start;
  gap: 10px;
  min-width: 0;
  padding: 12px;
  border: 1px solid color-mix(in srgb, var(--mmui-shell-border) 82%, transparent);
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.018);
}

.system-page__image-panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-width: 0;
}

.system-page__image-panel-head span {
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.system-page__image-panel-head strong {
  min-width: 0;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-footnote);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-line-height-footnote);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.system-page__option-item {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;
  min-height: 64px;
  padding: 10px 12px;
}

.system-page__option-main {
  display: grid;
  gap: 3px;
  min-width: 0;
}

.system-page__option-check {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  min-width: 22px;
  height: 22px;
  color: var(--mmui-accent-blue);
  font-size: var(--mmui-font-size-body);
}

.system-page__option-empty {
  padding: 12px 0;
}

.system-page__confirm {
  display: grid;
  gap: 12px;
}

.system-page__confirm-row {
  display: grid;
  gap: 8px;
}

.system-page__confirm-row span {
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.system-page__confirm-row strong {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-body);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-line-height-body);
}

.system-page__confirm-help {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
}

.system-page__password-field,
.system-page__boot-field {
  width: 100%;
}

.system-page__modal-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

@media (max-width: 1180px) {
  .system-page__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .system-page__card:last-child {
    grid-column: 1 / -1;
  }
}

@media (max-width: 760px) {
  .system-page__header-shell,
  .system-page__notice,
  .system-page__grid {
    margin-right: 0;
    margin-left: 0;
  }

  .system-page__header {
    flex-wrap: wrap;
  }

  .system-page__title {
    padding-left: 0;
  }

  .system-page__summary {
    margin-left: 0;
  }

  .system-page__grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .system-page__overview-row {
    align-items: flex-start;
    flex-direction: column;
    min-height: auto;
    padding: 10px 0;
  }

  .system-page__overview-value {
    justify-content: flex-start;
    width: 100%;
  }

  .system-page__overview-actions {
    margin-left: auto;
  }

  .system-page__action-buttons--triple,
  .system-page__action-buttons--pair {
    grid-template-columns: minmax(0, 1fr);
  }

  :global(.system-page__modal) {
    max-width: calc(100vw - 24px);
  }

  .system-page__modal-body {
    padding: 16px;
  }

  .system-page__modal-head {
    grid-template-columns: auto minmax(0, 1fr) auto;
  }

  .system-page__step-line {
    grid-template-columns: minmax(0, 1fr);
    gap: 8px;
  }

  .system-page__step-line > i {
    display: none;
  }

  .system-page__reinstall-picker {
    grid-template-columns: minmax(0, 1fr);
  }

  .system-page__image-panel {
    padding: 10px;
  }

  .system-page__modal-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .system-page__modal-actions .ant-btn-primary:last-child {
    grid-column: auto;
  }
}
</style>

