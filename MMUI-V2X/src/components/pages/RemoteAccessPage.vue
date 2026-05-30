<template>
  <section ref="pageRootRef" class="remote-page">
    <a-row :gutter="[0, 0]">
      <a-col :span="24">
        <div class="remote-page__header-shell">
          <a-flex class="remote-page__header" align="center" justify="space-between">
            <div class="remote-page__title-wrap">
              <div class="remote-page__title">辅助远程</div>
            </div>
            <a-flex class="remote-page__summary" align="center" :gap="12">
              <span class="remote-page__summary-label">可用方式</span>
              <span class="remote-page__summary-value">{{ availableMethodCount }} / {{ totalMethodCount }}</span>
              <a-tooltip title="远程方式来自当前适配器配置，轻舟默认提供 RDP 与 Web VNC。">
                <a-button class="remote-page__summary-help" type="text" shape="circle" aria-label="远程说明">
                  <InfoCircleOutlined />
                </a-button>
              </a-tooltip>
            </a-flex>
          </a-flex>
        </div>
      </a-col>

      <a-col :span="24">
        <a-alert
          class="remote-page__notice"
          message="远程登录方式由当前主控适配器提供；轻舟默认支持本地 RDP 与 Web VNC。"
          type="info"
          show-icon
          closable
        />
      </a-col>

      <a-col :span="24">
        <div class="remote-page__aurora-grid" :class="{ 'is-compact': compactMode }">
          <section class="home-card remote-page__panel remote-page__panel--info">
            <div class="remote-page__panel-head">
              <div class="remote-page__panel-title-wrap">
                <div class="remote-page__panel-title">远程信息</div>
              </div>
              <span class="remote-page__panel-status" :class="`is-${remoteStatusTone}`">
                <i></i>
                {{ remoteStatusText }}
              </span>
            </div>

            <div class="remote-page__info-body">
              <div class="remote-page__os-block">
                <div class="remote-page__os-logo" aria-hidden="true">
                  <svg v-if="isWindowsHost" viewBox="0 0 48 48">
                    <path d="M2 7l19-3v19H2V7zm25-4l19-3v22H27V3zM2 25h19v19L2 41V25zm25 0h19v22l-19-3V25z" fill="currentColor" />
                  </svg>
                  <CloudServerOutlined v-else />
                </div>
                <div class="remote-page__os-copy">
                  <span>系统类型</span>
                  <strong class="remote-page__os-name">{{ host.osName || '-' }}</strong>
                  <small>{{ host.osType || '系统' }}</small>
                </div>
              </div>

              <div class="remote-page__address-card">
                <div class="remote-page__address-copy">
                  <span>远程地址</span>
                  <strong class="remote-page__info-mono">{{ remoteAddress }}</strong>
                </div>
                <div class="remote-page__address-actions">
                  <a-tooltip title="公网地址:端口">
                    <a-button class="remote-page__copy-btn" type="text" size="small">
                      <InfoCircleOutlined />
                    </a-button>
                  </a-tooltip>
                  <a-button class="remote-page__copy-btn" type="text" size="small" @click="copyText(remoteAddress, '远程地址')">
                    <CopyOutlined />
                  </a-button>
                </div>
              </div>

              <div class="remote-page__credential-grid">
                <div class="remote-page__credential-item">
                  <span>系统用户</span>
                  <div class="remote-page__info-value">
                    <strong class="remote-page__info-mono">{{ remoteUser }}</strong>
                    <a-button class="remote-page__copy-btn" type="text" size="small" @click="copyText(remoteUser, '系统用户')">
                      <CopyOutlined />
                    </a-button>
                  </div>
                </div>
                <div class="remote-page__credential-item">
                  <span>系统密码</span>
                  <div class="remote-page__info-value">
                    <strong class="remote-page__info-mono">{{ showPassword ? systemPassword : maskedPassword }}</strong>
                    <a-button class="remote-page__copy-btn" type="text" size="small" @click="showPassword = !showPassword">
                      <component :is="showPassword ? EyeInvisibleOutlined : EyeOutlined" />
                    </a-button>
                    <a-button class="remote-page__copy-btn" type="text" size="small" @click="copyText(systemPassword, '系统密码')">
                      <CopyOutlined />
                    </a-button>
                  </div>
                </div>
              </div>

              <div class="remote-page__access-gauge" :style="{ '--access-color': accessGaugeColor }">
                <div class="remote-page__access-gauge-stage">
                  <svg viewBox="0 0 220 156" aria-label="远程可用性">
                    <path class="remote-page__access-gauge-track" d="M 34 108 A 76 76 0 0 1 186 108" />
                    <path
                      class="remote-page__access-gauge-progress"
                      d="M 34 108 A 76 76 0 0 1 186 108"
                      pathLength="100"
                      :stroke-dasharray="`${accessPercent} 100`"
                    />
                    <text class="remote-page__access-gauge-score" x="110" y="90" text-anchor="middle">
                      {{ availableMethodCount }}/{{ totalMethodCount || 0 }}
                    </text>
                  </svg>
                  <div class="remote-page__access-gauge-copy">
                    <strong>{{ accessLabel }}</strong>
                    <span>远程可用性</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="remote-page__info-actions">
              <a-button type="primary" size="large" @click="copyText(remoteAddress, '远程地址')">
                <CopyOutlined />
                复制远程地址
              </a-button>
              <a-button size="large" @click="copyText(remoteUser, '系统用户')">
                <CopyOutlined />
                复制用户名
              </a-button>
            </div>
          </section>

          <section class="home-card remote-page__panel remote-page__panel--primary">
            <div class="remote-page__panel-head">
              <div class="remote-page__panel-title-wrap">
                <div class="remote-page__panel-title">远程方式</div>
              </div>
              <span class="remote-page__panel-chip">{{ availableMethodCount }} / {{ totalMethodCount }}</span>
            </div>

            <div class="remote-page__other-shell">
              <div class="remote-page__other-select">
                <a-tabs
                  v-model:active-key="selectedOtherMethodKey"
                  class="remote-page__method-tabs"
                  size="large"
                >
                  <a-tab-pane
                    v-for="item in allRemoteMethods"
                    :key="item.key"
                    :disabled="item.enabled === false"
                  >
                    <template #tab>
                      <span class="remote-page__method-tab-label">
                        <span class="remote-page__option-icon" :class="`is-${item.key}`">
                          <img :src="resolveMethodGraphic(item)" alt="" aria-hidden="true" />
                        </span>
                        <span class="remote-page__method-tab-copy">
                          <span class="remote-page__other-option-name">{{ item.name }}</span>
                          <span v-if="item.primary" class="remote-page__other-option-desc">推荐入口</span>
                          <span v-else class="remote-page__other-option-desc">{{ item.groupLabel }}</span>
                        </span>
                      </span>
                    </template>
                  </a-tab-pane>
                </a-tabs>
              </div>

              <div v-if="selectedOtherMethod" class="remote-page__other-detail">
                  <div class="remote-page__tool-head">
                  <div class="remote-page__tool-title-wrap">
                    <span class="remote-page__detail-icon" :class="`is-${selectedOtherMethod.key}`">
                      <img :src="resolveMethodGraphic(selectedOtherMethod)" alt="" aria-hidden="true" />
                    </span>
                    <div>
                      <div class="remote-page__tool-title">{{ selectedOtherMethod.name }}</div>
                      <div class="remote-page__tool-subtitle">
                        {{ isPrimaryMethod(selectedOtherMethod) ? '常用入口' : '第三方远程' }}
                      </div>
                    </div>
                  </div>
                  <span
                    class="remote-page__tool-state"
                    :class="selectedOtherMethod.enabled === false ? 'is-off' : 'is-on'"
                  >
                    {{ selectedOtherMethod.enabled === false ? '不可用' : '可用' }}
                  </span>
                </div>

                <div class="remote-page__tool-desc">{{ selectedOtherMethod.description }}</div>

                <div v-if="selectedOtherMethod.id || selectedOtherMethod.code" class="remote-page__tool-credentials">
                  <div class="remote-page__tool-kv" v-if="selectedOtherMethod.id">
                    <span>识别码</span>
                    <div class="remote-page__tool-value">
                      <strong>{{ selectedOtherMethod.id }}</strong>
                      <a-button class="remote-page__copy-btn" type="text" size="small" @click="copyText(selectedOtherMethod.id, '识别码')">
                        <CopyOutlined />
                      </a-button>
                    </div>
                  </div>

                  <div class="remote-page__tool-kv" v-if="selectedOtherMethod.code">
                    <span>验证码</span>
                    <div class="remote-page__tool-value">
                      <strong>{{ visibleCodes[selectedOtherMethod.key] ? selectedOtherMethod.code : maskCode(selectedOtherMethod.code) }}</strong>
                      <a-button class="remote-page__copy-btn" type="text" size="small" @click="toggleCode(selectedOtherMethod.key)">
                        <component :is="visibleCodes[selectedOtherMethod.key] ? EyeInvisibleOutlined : EyeOutlined" />
                      </a-button>
                      <a-button class="remote-page__copy-btn" type="text" size="small" @click="copyText(selectedOtherMethod.code, '验证码')">
                        <CopyOutlined />
                      </a-button>
                    </div>
                  </div>
                </div>

                <a-space class="remote-page__tool-actions" :size="10">
                  <a-button
                    class="remote-page__action-btn"
                    :type="selectedOtherMethod.primary ? 'primary' : 'default'"
                    size="large"
                    :disabled="selectedOtherMethod.enabled === false || isActionLoading(`remote:primary:${selectedOtherMethod.key}`)"
                    :loading="isActionLoading(`remote:primary:${selectedOtherMethod.key}`)"
                    @click="triggerSelectedMethodPrimary(selectedOtherMethod)"
                  >
                    {{ getMethodActionLabel(selectedOtherMethod) }}
                  </a-button>
                  <a-button
                    v-if="shouldShowSecondaryAction(selectedOtherMethod)"
                    class="remote-page__action-btn"
                    size="large"
                    :disabled="selectedOtherMethod.enabled === false || isActionLoading(`remote:secondary:${selectedOtherMethod.key}`)"
                    :loading="isActionLoading(`remote:secondary:${selectedOtherMethod.key}`)"
                    @click="triggerSelectedMethodSecondary(selectedOtherMethod)"
                  >
                    {{ selectedOtherMethod.secondaryActionLabel || '下载客户端' }}
                  </a-button>
                </a-space>
              </div>
            </div>
          </section>
        </div>
      </a-col>
    </a-row>
  </section>
</template>

<script setup>
import {
  BulbOutlined,
  CloudServerOutlined,
  CopyOutlined,
  DesktopOutlined,
  EyeInvisibleOutlined,
  EyeOutlined,
  GlobalOutlined,
  InfoCircleOutlined,
  LinkOutlined,
  ToolOutlined,
} from '@ant-design/icons-vue';
import { message } from 'ant-design-vue';
import { computed, reactive, ref, watch } from 'vue';
import { useCompactPageMode } from '@/components/pages/useCompactPageMode';
import { useDashboardStore } from '@/stores/dashboard';
import { useActionLocks } from '@/composables/useActionLocks';
import {
  getRemoteActionLabel,
  getRemoteUser,
  isWindowsHost as checkWindowsHost,
  normalizeRemoteLoginMethod,
  triggerHostRemoteAccess,
} from '@/utils/remoteAccess';
import remoteConsoleGraphic from '@/assets/iconly-glass/console.svg';
import remoteFileGraphic from '@/assets/iconly-glass/file.svg';
import remoteNetworkGraphic from '@/assets/iconly-glass/network.svg';
import sunloginLogo from '@/assets/remote-logos/sunlogin.ico';
import todeskLogo from '@/assets/remote-logos/todesk.ico';
import myrtilleLogo from '@/assets/remote-logos/myrtille.ico';
import uuLogo from '@/assets/remote-logos/uu.ico';

const props = defineProps({
  page: {
    type: Object,
    default: () => ({}),
  },
});

const { pageRootRef, compactMode } = useCompactPageMode(980);
const store = useDashboardStore();
const { isActionLoading, runWithActionLoading } = useActionLocks();
const showPassword = ref(false);
const visibleCodes = reactive({});
const selectedOtherMethodKey = ref('');

const iconMap = {
  web: GlobalOutlined,
  rdp: DesktopOutlined,
  ssh: ToolOutlined,
  vnc: CloudServerOutlined,
  sunlogin: BulbOutlined,
  todesk: DesktopOutlined,
  myrtille: ToolOutlined,
  mytrille: ToolOutlined,
  uu: LinkOutlined,
};
const methodGraphicMap = {
  web: remoteNetworkGraphic,
  rdp: remoteFileGraphic,
  ssh: remoteNetworkGraphic,
  vnc: remoteConsoleGraphic,
  sunlogin: sunloginLogo,
  todesk: todeskLogo,
  myrtille: myrtilleLogo,
  mytrille: myrtilleLogo,
  uu: uuLogo,
};

const host = computed(() => store.host || {});
const isWindowsHost = computed(() => checkWindowsHost(host.value));
const remoteAddress = computed(() => host.value.remoteAddress || '-');
const remoteUser = computed(() => getRemoteUser(host.value));
const systemPassword = computed(() => host.value.systemPassword || '-');
const maskedPassword = computed(() => maskCode(systemPassword.value));
const remoteLinks = computed(() => props.page?.links || {});
const primaryMethods = computed(() => (
  Array.isArray(props.page?.primaryMethods)
    ? props.page.primaryMethods.map((item) => normalizeRemoteMethod(item))
    : []
));
const otherMethods = computed(() => (
  Array.isArray(props.page?.otherMethods) ? props.page.otherMethods : []
));
const allRemoteMethods = computed(() => ([
  ...primaryMethods.value.map((item) => ({ ...item, groupLabel: '常用' })),
  ...otherMethods.value.map((item) => ({ ...item, groupLabel: '第三方' })),
]));
const totalMethodCount = computed(() => primaryMethods.value.length + otherMethods.value.length);
const availableMethodCount = computed(() => (
  allRemoteMethods.value.filter((item) => item.enabled !== false).length
));
const accessPercent = computed(() => {
  if (!totalMethodCount.value) return 0;
  return Math.round((availableMethodCount.value / totalMethodCount.value) * 100);
});
const accessLabel = computed(() => {
  if (accessPercent.value >= 86) return '良好';
  if (accessPercent.value >= 60) return '可用';
  if (accessPercent.value > 0) return '受限';
  return '不可用';
});
const accessGaugeColor = computed(() => {
  if (accessPercent.value >= 86) return '#22c55e';
  if (accessPercent.value >= 60) return 'var(--mmui-accent-blue)';
  if (accessPercent.value > 0) return '#f59e0b';
  return '#ef4444';
});

function normalizeRemoteMethod(item) {
  return normalizeRemoteLoginMethod(item, host.value);
}

watch(
  allRemoteMethods,
  (items) => {
    items.forEach((item) => {
      if (!(item.key in visibleCodes)) {
        visibleCodes[item.key] = false;
      }
    });

    if (!items.length) {
      selectedOtherMethodKey.value = '';
      return;
    }

    if (!items.some((item) => item.key === selectedOtherMethodKey.value)) {
      const firstEnabled = items.find((item) => item.enabled !== false);
      selectedOtherMethodKey.value = firstEnabled?.key || items[0].key;
    }
  },
  { immediate: true },
);

const selectedOtherMethod = computed(() => (
  allRemoteMethods.value.find((item) => item.key === selectedOtherMethodKey.value) || allRemoteMethods.value[0] || null
));
const remoteStatusText = computed(() => {
  const status = String(host.value.status || '').trim();
  return status || '运行中';
});
const remoteStatusTone = computed(() => {
  const status = remoteStatusText.value;
  if (
    status.includes('开机')
    || status.includes('启动')
    || status.includes('关机中')
    || status.includes('重启')
    || status.includes('重装')
    || status.includes('处理中')
    || status.includes('等待')
  ) {
    return 'warning';
  }
  if (status.includes('运行')) return 'success';
  if (status.includes('关闭') || status.includes('关机')) return 'danger';
  return 'neutral';
});

function maskCode(value) {
  const text = String(value || '');
  return text ? '•'.repeat(Math.max(8, text.length)) : '-';
}

function toggleCode(key) {
  visibleCodes[key] = !visibleCodes[key];
}

function selectOtherMethod(key) {
  selectedOtherMethodKey.value = key;
}

function isPrimaryMethod(item) {
  return primaryMethods.value.some((entry) => entry.key === item?.key);
}

function shouldShowSecondaryAction(item) {
  if (!item || item.key === 'rdp' || item.key === 'ssh') {
    return false;
  }

  return Boolean(item.secondaryActionLabel || item.downloadUrl);
}

function getMethodActionLabel(item) {
  if (item?.key === 'rdp' || item?.key === 'ssh') {
    return getRemoteActionLabel(host.value);
  }

  return item?.actionLabel || '进入远程';
}

function resolveMethodGraphic(item) {
  return methodGraphicMap[item?.key] || methodGraphicMap[item?.icon] || remoteConsoleGraphic;
}

function openUrl(url, emptyMessage) {
  const target = String(url || '').trim();
  if (!target) {
    message.info(emptyMessage);
    return;
  }

  window.open(target, '_blank');
}

async function copyText(value, label) {
  const text = String(value || '').trim();
  if (!text || text === '-') {
    message.warning(`暂无可复制的${label}`);
    return;
  }

  try {
    await navigator.clipboard.writeText(text);
    message.success(`已复制${label}`);
  } catch {
    const input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    message.success(`已复制${label}`);
  }
}

async function openPrimaryMethod(item) {
  if (item.key === 'vnc') {
    try {
      const url = await store.openVnc();
      openUrl(url, 'VNC 控制台暂未返回可打开链接');
    } catch (error) {
      message.error(error?.message || 'VNC 控制台打开失败');
    }
    return;
  }

  if (item.key === 'rdp' || item.key === 'ssh') {
    await triggerHostRemoteAccess(host.value);
    return;
  }

  openUrl(
    remoteLinks.value?.[item.key],
    `${item.name} 暂未配置远程链接`,
  );
}

async function runSecondaryAction(item) {
  if (item.key === 'rdp' || item.key === 'ssh') {
    await triggerHostRemoteAccess(host.value);
    return;
  }

  openUrl(
    remoteLinks.value?.[`${item.key}_secondary`],
    `${item.name} 暂未配置附加入口`,
  );
}

function triggerOtherTool(item, action) {
  if (action === '下载客户端') {
    openUrl(item.downloadUrl, `${item.name} 暂未配置客户端下载地址`);
    return;
  }

  message.info(`${item.name} 暂未配置直接登录入口，可先复制识别码和验证码使用`);
}

async function triggerSelectedMethodPrimary(item) {
  await runWithActionLoading(`remote:primary:${item.key}`, async () => {
    if (primaryMethods.value.some((entry) => entry.key === item.key)) {
      await openPrimaryMethod(item);
      return;
    }

    triggerOtherTool(item, '进入远程');
  });
}

async function triggerSelectedMethodSecondary(item) {
  await runWithActionLoading(`remote:secondary:${item.key}`, async () => {
    if (primaryMethods.value.some((entry) => entry.key === item.key)) {
      await runSecondaryAction(item);
      return;
    }

    triggerOtherTool(item, '下载客户端');
  });
}
</script>

<style scoped>
@keyframes remote-pulse {
  0%,
  100% { opacity: 1; box-shadow: 0 0 0 0 currentColor; }
  50% { opacity: .7; box-shadow: 0 0 0 4px transparent; }
}

@keyframes remote-gauge-fill {
  from { stroke-dasharray: 0 100; }
}

@keyframes remote-fade-in {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}

.remote-page {
  --remote-gap: var(--mmui-space-2);
  --remote-card-padding: var(--mmui-card-padding);
  --remote-card-padding-sm: var(--mmui-card-padding-sm);
  --remote-head-height: var(--mmui-card-head-height);
  --remote-row-height: var(--mmui-row-height);
  width: 100%;
  display: grid;
  gap: var(--remote-gap);
  color: var(--mmui-text);
  font-size: var(--mmui-font-size-body);
  line-height: var(--mmui-line-height-body);
}

.remote-page__header-shell {
  margin: 8px 8px 18px;
}

.remote-page__header {
  display: flex !important;
  align-items: center;
  gap: 12px;
  min-height: 54px;
  padding: 0;
}

.remote-page__title-wrap {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.remote-page__title {
  padding-left: 8px;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-hero-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-page);
}

.remote-page__title-chip {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
}

.remote-page__summary {
  margin-left: auto;
  min-height: 42px;
  padding: 0 14px;
  border: 1px solid var(--mmui-shell-border);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.02);
}

.remote-page__summary-label,
.remote-page__summary-help {
  color: var(--mmui-text-muted) !important;
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.remote-page__summary-value {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.remote-page__summary-help {
  width: 32px !important;
  min-width: 32px !important;
  height: 32px !important;
  padding: 0 !important;
  border-radius: 999px;
}

.remote-page__notice.ant-alert {
  margin: 0 8px 16px;
  padding: 12px 16px !important;
  border-radius: 10px !important;
  border: 1px solid rgba(var(--mmui-accent-blue-rgb), 0.12) !important;
  background: linear-gradient(135deg, rgba(var(--mmui-accent-blue-rgb), 0.04), rgba(var(--mmui-accent-blue-rgb), 0.01)) !important;
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.remote-page__notice :deep(.anticon) {
  color: var(--mmui-accent-blue);
}

.remote-page__aurora-grid {
  display: grid;
  grid-template-columns: repeat(24, minmax(0, 1fr));
  gap: 16px;
  margin: 0 8px 12px;
}

.remote-page__panel {
  position: relative;
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 304px;
  overflow: hidden;
  border: 0;
  border-radius: 10px;
  background: var(--mmui-card-surface);
  box-shadow: none;
}

.remote-page__panel--primary {
  grid-column: span 15;
  order: 1;
}

.remote-page__panel--info {
  grid-column: span 9;
  order: 2;
}

.remote-page__panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 62px;
  padding: 0 24px;
  background:
    linear-gradient(180deg, rgba(var(--mmui-accent-blue-rgb), 0.03), rgba(255, 255, 255, 0));
}

.remote-page__panel-title-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.remote-page__panel-title {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.remote-page__panel-chip {
  flex: 0 0 auto;
  min-height: 26px;
  padding: 0 10px;
  border-radius: 999px;
  color: var(--mmui-accent-blue);
  font-size: var(--mmui-font-size-caption);
  font-weight: var(--mmui-font-weight-semibold);
  line-height: 26px;
  background: rgba(var(--mmui-accent-blue-rgb), 0.12);
}

.remote-page__panel-status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-caption);
  font-weight: var(--mmui-font-weight-semibold);
  line-height: var(--mmui-line-height-body);
  white-space: nowrap;
}

.remote-page__panel-status i {
  width: 8px;
  min-width: 8px;
  height: 8px;
  border-radius: 999px;
  background: currentColor;
  animation: remote-pulse 2.4s ease-in-out infinite;
}

.remote-page__panel-status.is-success {
  color: #53d769;
}

.remote-page__panel-status.is-danger {
  color: #ff6b72;
}

.remote-page__panel-status.is-warning {
  color: #f6b73c;
}

.remote-page__panel-note {
  padding: 0 24px 16px;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.remote-page__info-body {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: 0;
  padding: 8px 24px 16px;
}

.remote-page__os-block {
  display: flex;
  align-items: center;
  gap: var(--mmui-space-2);
  min-width: 0;
  min-height: 74px;
  padding: 0;
  border-bottom: 1px solid var(--mmui-shell-border);
  background: transparent;
}

.remote-page__os-logo {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 54px;
  min-width: 54px;
  height: 54px;
  color: var(--mmui-accent-blue);
  border-radius: 14px;
  background: radial-gradient(circle at 50% 50%, rgba(var(--mmui-accent-blue-rgb), 0.14), transparent 68%);
}

.remote-page__os-logo svg,
.remote-page__os-logo :deep(.anticon) {
  width: 36px;
  height: 36px;
  font-size: 32px;
}

.remote-page__os-copy {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.remote-page__os-copy span,
.remote-page__address-copy span,
.remote-page__credential-item > span {
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-footnote);
  font-weight: var(--mmui-font-weight-regular);
  line-height: var(--mmui-line-height-footnote);
}

.remote-page__os-copy small {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-body);
}

.remote-page__os-name {
  text-align: left;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-body);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-line-height-body);
}

.remote-page__address-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--mmui-space-2);
  min-height: 48px;
  padding: 0;
  border-bottom: 1px solid var(--mmui-shell-border);
  background: transparent;
  transition: background 0.15s ease;
}

.remote-page__address-card:hover {
  background: rgba(var(--mmui-accent-blue-rgb), 0.02);
}

.remote-page__address-copy {
  display: grid;
  grid-template-columns: 96px minmax(0, 1fr);
  align-items: center;
  gap: var(--mmui-space-2);
  width: 100%;
  min-width: 0;
}

.remote-page__address-actions {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  flex: 0 0 auto;
}

.remote-page__credential-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 0;
}

.remote-page__credential-item {
  display: grid;
  grid-template-columns: 96px minmax(0, 1fr);
  align-items: center;
  gap: var(--mmui-space-2);
  min-width: 0;
  min-height: 48px;
  padding: 0;
  border-bottom: 1px solid var(--mmui-shell-border);
  background: transparent;
  transition: background 0.15s ease;
}

.remote-page__credential-item:hover {
  background: rgba(var(--mmui-accent-blue-rgb), 0.02);
}

.remote-page__info-value {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--mmui-space-1);
  width: 100%;
  min-width: 0;
}

.remote-page__info-mono {
  min-width: 0;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-body);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-line-height-body);
  font-family: ui-monospace, Menlo, Consolas, monospace;
  text-align: right;
  word-break: break-word;
  overflow-wrap: anywhere;
}

.remote-page__copy-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  min-width: 24px;
  height: 24px;
  padding: 0;
  color: var(--mmui-text-muted) !important;
  border-radius: 8px;
  transition:
    color 0.15s ease,
    background 0.15s ease,
    transform 0.12s ease;
}

.remote-page__copy-btn:hover {
  color: var(--mmui-accent-blue) !important;
  background: rgba(var(--mmui-accent-blue-rgb), 0.06);
}

.remote-page__copy-btn:active {
  transform: scale(0.88);
}

.remote-page__access-gauge {
  display: grid;
  justify-items: center;
  padding: 20px 0 4px;
}

.remote-page__access-gauge-stage {
  position: relative;
  width: 220px;
  height: 156px;
}

.remote-page__access-gauge-stage svg {
  display: block;
  width: 220px;
  height: 156px;
  overflow: visible;
}

.remote-page__access-gauge-track,
.remote-page__access-gauge-progress {
  fill: none;
  stroke-width: 16;
  stroke-linecap: round;
}

.remote-page__access-gauge-track {
  stroke: color-mix(in srgb, var(--mmui-shell-border) 58%, transparent);
}

.remote-page__access-gauge-progress {
  stroke: var(--access-color);
  filter: drop-shadow(0 0 10px color-mix(in srgb, var(--access-color) 30%, transparent));
  animation: remote-gauge-fill 1s ease-out forwards;
}

.remote-page__access-gauge-score {
  fill: var(--mmui-card-title);
  font-size: 22px;
  font-weight: 700;
  dominant-baseline: middle;
}

.remote-page__access-gauge-copy {
  position: absolute;
  left: 0;
  right: 0;
  top: 104px;
  display: grid;
  justify-items: center;
  gap: 2px;
  text-align: center;
}

.remote-page__access-gauge-copy strong {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.remote-page__access-gauge-copy span {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.remote-page__info-actions {
  display: flex;
  gap: 10px;
  width: 100%;
  margin-top: auto;
  padding: 0 24px 24px;
}

.remote-page__info-actions :deep(.ant-btn),
.remote-page__tool-actions :deep(.ant-btn) {
  min-width: 0;
}

.remote-page__info-actions :deep(.ant-space-item),
.remote-page__info-actions :deep(.ant-btn) {
  flex: 1 1 0;
  width: 100%;
}

.remote-page__other-shell {
  display: grid;
  grid-template-columns: minmax(264px, 308px) minmax(0, 1fr);
  gap: 28px;
  flex: 1 1 auto;
  padding: 0 24px 24px;
  min-height: 0;
}

.remote-page__other-select {
  display: grid;
  align-content: start;
  gap: 0;
  min-height: 0;
  max-height: none;
  padding-right: 20px;
  border-right: 1px solid var(--mmui-shell-border);
  overflow-y: auto;
}

.remote-page__method-tabs {
  width: 100%;
  min-width: 0;
}

.remote-page__method-tabs :deep(.ant-tabs-nav) {
  margin: 0;
}

.remote-page__method-tabs :deep(.ant-tabs-nav::before) {
  display: none;
}

.remote-page__method-tabs :deep(.ant-tabs-nav-wrap),
.remote-page__method-tabs :deep(.ant-tabs-nav-list) {
  width: 100%;
  min-width: 0;
}

.remote-page__method-tabs :deep(.ant-tabs-nav-list) {
  display: grid;
  gap: 0;
  transform: none !important;
}

.remote-page__method-tabs :deep(.ant-tabs-tab) {
  min-width: 0;
  min-height: 58px;
  margin: 0;
  padding: 0 12px;
  border-bottom: 1px solid var(--mmui-shell-border);
  color: inherit;
  transition:
    background-color 0.18s ease,
    color 0.18s ease;
}

.remote-page__method-tabs :deep(.ant-tabs-tab:hover) {
  background: color-mix(in srgb, var(--mmui-accent-blue) 6%, transparent);
}

.remote-page__method-tabs :deep(.ant-tabs-tab-active) {
  color: var(--mmui-accent-blue);
  background:
    linear-gradient(90deg, rgba(var(--mmui-accent-blue-rgb), 0.14), rgba(var(--mmui-accent-blue-rgb), 0.03));
  box-shadow: inset 3px 0 0 var(--mmui-accent-blue);
}

.remote-page__method-tabs :deep(.ant-tabs-tab-btn) {
  width: 100%;
  min-width: 0;
  color: inherit !important;
}

.remote-page__method-tabs :deep(.ant-tabs-ink-bar),
.remote-page__method-tabs :deep(.ant-tabs-content-holder),
.remote-page__method-tabs :deep(.ant-tabs-nav-operations) {
  display: none !important;
}

.remote-page__method-tab-label {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  min-width: 0;
}

.remote-page__method-tab-copy {
  display: grid;
  gap: 2px;
  min-width: 0;
  flex: 1 1 auto;
  text-align: left;
}

.remote-page__other-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  min-height: 58px;
  padding: 0 12px;
  color: inherit;
  text-align: left;
  border: 0;
  border-bottom: 1px solid var(--mmui-shell-border);
  border-radius: 0;
  background: transparent;
  cursor: pointer;
  transition:
    background-color 0.18s ease,
    color 0.18s ease;
}

.remote-page__other-option:hover {
  background: color-mix(in srgb, var(--mmui-accent-blue) 6%, transparent);
}

.remote-page__other-option.is-active {
  color: var(--mmui-accent-blue);
  background:
    linear-gradient(90deg, rgba(var(--mmui-accent-blue-rgb), 0.14), rgba(var(--mmui-accent-blue-rgb), 0.03));
  box-shadow: inset 3px 0 0 var(--mmui-accent-blue);
}

.remote-page__other-option.is-disabled {
  opacity: 0.55;
}

.remote-page__other-option-main {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.remote-page__option-icon,
.remote-page__detail-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  color: var(--mmui-accent-blue);
  line-height: 1;
  background: radial-gradient(circle at 50% 50%, rgba(var(--mmui-accent-blue-rgb), 0.14), transparent 68%);
}

.remote-page__option-icon {
  width: 36px;
  min-width: 36px;
  height: 36px;
  border-radius: 12px;
}

.remote-page__detail-icon {
  width: 58px;
  min-width: 58px;
  height: 58px;
  border-radius: 16px;
}

.remote-page__option-icon img,
.remote-page__detail-icon img {
  display: block;
  width: 34px;
  height: 34px;
  object-fit: contain;
  filter: drop-shadow(0 8px 14px rgba(var(--mmui-accent-blue-rgb), 0.16));
}

.remote-page__detail-icon img {
  width: 54px;
  height: 54px;
}

.remote-page__option-icon.is-web,
.remote-page__detail-icon.is-web {
  color: var(--mmui-accent-blue);
  background: radial-gradient(circle at 50% 50%, rgba(var(--mmui-accent-blue-rgb), 0.14), transparent 68%);
}

.remote-page__option-icon.is-rdp,
.remote-page__detail-icon.is-rdp,
.remote-page__option-icon.is-ssh,
.remote-page__detail-icon.is-ssh,
.remote-page__option-icon.is-todesk,
.remote-page__detail-icon.is-todesk {
  color: #25b985;
  background: radial-gradient(circle at 50% 50%, rgba(37, 211, 145, 0.14), transparent 68%);
}

.remote-page__option-icon.is-vnc,
.remote-page__detail-icon.is-vnc,
.remote-page__option-icon.is-myrtille,
.remote-page__detail-icon.is-myrtille,
.remote-page__option-icon.is-mytrille,
.remote-page__detail-icon.is-mytrille {
  color: #8f63ff;
  background: radial-gradient(circle at 50% 50%, rgba(143, 99, 255, 0.14), transparent 68%);
}

.remote-page__option-icon.is-sunlogin,
.remote-page__detail-icon.is-sunlogin {
  color: #ff9c3f;
  background: radial-gradient(circle at 50% 50%, rgba(255, 156, 63, 0.14), transparent 68%);
}

.remote-page__option-icon.is-uu,
.remote-page__detail-icon.is-uu {
  color: #151515;
  background: radial-gradient(circle at 50% 50%, rgba(9, 9, 10, 0.12), transparent 68%);
}

.remote-page__other-option-copy {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.remote-page__other-option-name {
  min-width: 0;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-subheadline);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-subheadline);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.remote-page__other-option-desc {
  min-width: 0;
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.remote-page__other-option-side {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
}

.remote-page__other-detail {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-height: 0;
  padding: 0;
  border: 0;
  border-radius: 0;
  background: transparent;
  animation: remote-fade-in 0.22s ease-out;
}

.remote-page__tool-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
  min-width: 0;
  min-height: 76px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--mmui-shell-border);
}

.remote-page__tool-title-wrap {
  display: inline-flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
}

.remote-page__tool-title {
  min-width: 0;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.remote-page__tool-subtitle {
  margin-top: 0;
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.remote-page__tool-state {
  flex: 0 0 auto;
  min-height: 26px;
  padding: 0 10px;
  border-radius: 999px;
  font-size: var(--mmui-font-size-caption);
  font-weight: var(--mmui-font-weight-semibold);
  line-height: 26px;
}

.remote-page__tool-state.is-on {
  color: #53d769;
  background: rgba(83, 215, 105, 0.12);
}

.remote-page__tool-state.is-off {
  color: #ff6b72;
  background: rgba(255, 107, 114, 0.12);
}

.remote-page__tool-desc {
  min-height: 78px;
  padding: 0 0 16px;
  border: 0;
  border-bottom: 1px solid var(--mmui-shell-border);
  border-radius: 0;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
  background: transparent;
}

.remote-page__tool-credentials {
  display: grid;
  gap: 0;
}

.remote-page__tool-kv {
  display: grid;
  grid-template-columns: 72px minmax(0, 1fr);
  gap: var(--mmui-space-2);
  align-items: center;
  min-height: 42px;
  padding: 0;
  border: 0;
  border-bottom: 1px solid var(--mmui-shell-border);
  border-radius: 0;
  background: transparent;
}

.remote-page__tool-kv span {
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-footnote);
  font-weight: var(--mmui-font-weight-regular);
  line-height: var(--mmui-line-height-footnote);
  white-space: nowrap;
}

.remote-page__tool-kv strong {
  min-width: 0;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-body);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-line-height-body);
  word-break: break-word;
  overflow-wrap: anywhere;
}

.remote-page__tool-value {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--mmui-space-1);
  min-width: 0;
}

.remote-page__tool-actions {
  display: flex !important;
  flex-wrap: nowrap;
  gap: 10px;
  width: 100%;
  margin-top: auto;
}

.remote-page__tool-actions :deep(.ant-space-item) {
  flex: 1 1 0;
  min-width: 0;
}

.remote-page__tool-actions :deep(.ant-btn) {
  width: 100%;
  min-width: 0;
}

@media (max-width: 1180px) {
  .remote-page__panel--primary,
  .remote-page__panel--info {
    grid-column: 1 / -1;
  }
}

@media (max-width: 1023px) {
  .remote-page__header-shell {
    margin: 8px 8px 14px;
  }

  .remote-page__header {
    flex-wrap: wrap;
  }

  .remote-page__title {
    padding-left: 0;
  }

  .remote-page__summary {
    margin-left: 0;
    min-height: 38px;
    padding: 0 12px;
    border-left: 1px solid var(--mmui-shell-border);
  }

  .remote-page__other-shell {
    grid-template-columns: minmax(250px, 318px) minmax(0, 1fr);
  }
}

@media (max-width: 720px) {
  .remote-page,
  .remote-page__aurora-grid {
    min-width: 0;
    max-width: 100%;
    overflow-x: hidden;
  }

  .remote-page__header-shell,
  .remote-page__notice.ant-alert,
  .remote-page__aurora-grid {
    margin-right: 0;
    margin-left: 0;
  }

  .remote-page__aurora-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .remote-page__notice.ant-alert {
    margin-bottom: 12px;
  }

  .remote-page__panel--primary,
  .remote-page__panel--info {
    grid-column: 1 / -1;
    justify-self: stretch;
    width: 100%;
    min-width: 0;
    max-width: 100%;
  }

  .remote-page__panel-head {
    min-height: 58px;
    padding: 0 18px;
  }

  .remote-page__panel-note {
    padding: 0 18px 14px;
  }

  .remote-page__other-shell {
    grid-template-columns: minmax(0, 1fr);
    gap: 18px;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    padding: 4px 18px 20px;
  }

  .remote-page__other-select {
    display: block;
    width: auto;
    min-width: 0;
    max-width: none;
    margin: 0 -18px;
    max-height: none;
    padding: 0 18px 14px;
    border-right: 0;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
  }

  .remote-page__other-select::-webkit-scrollbar {
    display: none;
  }

  .remote-page__method-tabs {
    width: 100%;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
  }

  .remote-page__method-tabs :deep(.ant-tabs-nav) {
    width: 100%;
    min-width: 0;
    margin: 0;
  }

  .remote-page__method-tabs :deep(.ant-tabs-nav::before) {
    border-bottom-color: var(--mmui-shell-border);
  }

  .remote-page__method-tabs :deep(.ant-tabs-nav-wrap) {
    flex: 1 1 auto;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    overflow: auto hidden;
    scrollbar-width: none;
  }

  .remote-page__method-tabs :deep(.ant-tabs-nav-wrap::-webkit-scrollbar) {
    display: none;
  }

  .remote-page__method-tabs :deep(.ant-tabs-nav-list) {
    display: flex;
    width: max-content;
    min-width: 0;
    max-width: none;
    gap: 6px;
    transform: translate(0) !important;
  }

  .remote-page__method-tabs :deep(.ant-tabs-tab) {
    flex: 0 0 auto;
    margin: 0;
    padding: 8px 10px 10px;
    border-radius: 8px 8px 0 0;
  }

  .remote-page__method-tabs :deep(.ant-tabs-nav-operations) {
    display: none !important;
  }

  .remote-page__method-tabs :deep(.ant-tabs-tab-active) {
    background: rgba(var(--mmui-accent-blue-rgb), 0.1);
  }

  .remote-page__method-tabs :deep(.ant-tabs-content-holder) {
    display: none;
  }

  .remote-page__method-tab-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: clamp(112px, 30vw, 148px);
    min-width: 0;
    max-width: 148px;
  }

  .remote-page__method-tab-copy {
    display: grid;
    gap: 2px;
    min-width: 0;
    flex: 1 1 auto;
  }

  .remote-page__other-option-name {
    color: var(--mmui-card-title);
    font-size: 14px;
    font-weight: var(--mmui-font-weight-semibold);
    line-height: var(--mmui-line-height-tight);
  }

  .remote-page__other-option-desc {
    color: var(--mmui-text-soft);
    font-size: 12px;
    line-height: var(--mmui-line-height-tight);
  }

  .remote-page__option-icon {
    align-self: center;
    width: 30px;
    min-width: 30px;
    height: 30px;
    border-radius: 8px;
  }

  .remote-page__option-icon img {
    width: 28px;
    height: 28px;
  }

  .remote-page__other-detail {
    padding-top: 18px;
    border-top: 1px solid var(--mmui-shell-border);
  }

  .remote-page__info-body,
  .remote-page__info-actions {
    padding-left: 18px;
    padding-right: 18px;
  }

  .remote-page__info-body {
    gap: 0;
    padding-top: 4px;
    padding-bottom: 12px;
  }

  .remote-page__os-block {
    min-height: 62px;
    gap: 12px;
  }

  .remote-page__os-logo {
    width: 42px;
    min-width: 42px;
    height: 42px;
  }

  .remote-page__os-logo svg,
  .remote-page__os-logo :deep(.anticon) {
    width: 30px;
    height: 30px;
    font-size: 28px;
  }

  .remote-page__address-card {
    gap: 8px;
    align-items: center;
    min-height: 48px;
  }

  .remote-page__address-copy,
  .remote-page__credential-item {
    grid-template-columns: 82px minmax(0, 1fr);
    gap: 8px;
    align-items: center;
    min-height: 48px;
    padding: 0;
  }

  .remote-page__address-copy span,
  .remote-page__credential-item > span {
    white-space: nowrap;
  }

  .remote-page__address-actions {
    gap: 2px;
  }

  .remote-page__info-mono {
    font-size: 13px;
    white-space: nowrap;
    word-break: normal;
    overflow-wrap: normal;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .remote-page__access-gauge {
    padding: 12px 0 0;
  }

  .remote-page__access-gauge-stage,
  .remote-page__access-gauge-stage svg {
    width: 164px;
    height: 116px;
  }

  .remote-page__access-gauge-track,
  .remote-page__access-gauge-progress {
    stroke-width: 14;
  }

  .remote-page__access-gauge-score {
    font-size: 18px;
  }

  .remote-page__access-gauge-copy {
    top: 76px;
  }

  .remote-page__access-gauge-copy strong {
    font-size: 16px;
  }

  .remote-page__access-gauge-copy span {
    font-size: 12px;
  }

  .remote-page__info-actions {
    gap: 8px;
    padding-top: 0;
    padding-bottom: 18px;
  }

  .remote-page__info-actions,
  .remote-page__tool-actions {
    flex-direction: column;
  }

  .remote-page__info-actions :deep(.ant-btn) {
    height: 40px;
  }

  .remote-page__tool-desc {
    min-height: auto;
  }

  .remote-page__tool-head {
    align-items: flex-start;
  }

  .remote-page__tool-kv {
    grid-template-columns: 64px minmax(0, 1fr);
  }
}

@media (max-width: 420px) {
  .remote-page__method-tabs :deep(.ant-tabs-tab) {
    padding-inline: 8px;
  }

  .remote-page__option-icon img {
    width: 26px;
    height: 26px;
  }
}
</style>
