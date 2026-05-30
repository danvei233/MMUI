<template>
  <section ref="pageRootRef" class="monitor-page">
    <a-row :gutter="[0, 0]">
      <a-col :span="24">
        <div class="monitor-page__header-shell">
          <a-flex class="monitor-page__header" align="center" justify="space-between">
            <div class="monitor-page__title">监控</div>
            <a-flex class="monitor-page__summary" align="center" :gap="12">
              <span class="monitor-page__summary-label">监控项</span>
              <span class="monitor-page__summary-value">{{ monitorCards.length }}</span>
              <span class="monitor-page__summary-hint">{{ latestLabel || '--' }}</span>
              <a-tooltip title="实时监控 CPU、内存、磁盘 IO 和网络吞吐；刷新会重新拉取当前实例监控快照。">
                <a-button class="monitor-page__summary-help" type="text" shape="circle" aria-label="监控说明">
                  <InfoCircleOutlined />
                </a-button>
              </a-tooltip>
              <a-tooltip title="刷新">
                <a-button
                  class="monitor-page__summary-help"
                  type="text"
                  shape="circle"
                  aria-label="刷新监控"
                  :disabled="isActionLoading('monitor:refresh')"
                  @click="refreshSummary"
                >
                  <LoadingOutlined v-if="isActionLoading('monitor:refresh')" />
                  <ReloadOutlined v-else />
                </a-button>
              </a-tooltip>
            </a-flex>
          </a-flex>
        </div>
      </a-col>

      <a-col :span="24">
        <a-alert
          class="monitor-page__notice"
          message="这里可以查看实例当前的 CPU、内存、磁盘和网络使用情况。"
          type="info"
          show-icon
          closable
        />
      </a-col>

      <a-col :span="24">
        <div class="monitor-page__aurora-grid" :class="{ 'is-compact': compactMode }">
          <article class="monitor-page__remote-panel">
            <div class="monitor-page__panel-title">远程信息</div>

            <div class="monitor-page__remote-layout">
              <div class="monitor-page__remote-list">
                <div
                  v-for="item in remoteRows"
                  :key="item.label"
                  class="monitor-page__remote-row"
                >
                  <span>{{ item.label }}</span>
                  <strong
                    v-if="item.tone"
                    class="monitor-page__remote-status"
                    :class="`is-${item.tone}`"
                  >
                    <i></i>
                    {{ item.value }}
                  </strong>
                  <strong v-else :class="{ 'is-mono': item.mono }">{{ item.value }}</strong>
                </div>
              </div>

              <div class="monitor-page__remote-gauge">
                <div class="monitor-page__gauge-stage" :style="{ '--gauge-color': performanceColor }">
                  <svg viewBox="0 0 220 156" role="img" aria-label="系统表现">
                    <path class="monitor-page__gauge-track" d="M 34 108 A 76 76 0 0 1 186 108" />
                    <path
                      class="monitor-page__gauge-progress"
                      d="M 34 108 A 76 76 0 0 1 186 108"
                      pathLength="100"
                      :stroke-dasharray="`${animatedPerformanceScore} 100`"
                    />
                    <text class="monitor-page__gauge-score" x="110" y="90" text-anchor="middle">
                      {{ animatedPerformanceScore }}
                    </text>
                  </svg>
                  <div class="monitor-page__gauge-copy">
                    <strong>{{ performanceLabel }}</strong>
                    <span>系统表现</span>
                  </div>
                </div>
              </div>
            </div>
          </article>

          <article
            v-for="item in monitorCards"
            :key="item.key"
            class="monitor-page__chart-card"
          >
            <div class="monitor-page__chart-head">
              <div class="monitor-page__chart-title">
                <span class="monitor-page__chart-icon" aria-hidden="true">
                  <img :src="item.icon" alt="" />
                </span>
                <span>{{ item.title }}</span>
              </div>
              <span class="monitor-page__chart-unit">{{ item.unit }}</span>
            </div>

            <div class="monitor-page__chart-meta">
              <strong>{{ item.currentText }}</strong>
              <span>{{ item.hintText }}</span>
            </div>

            <div
              :ref="(el) => setChartRef(item.key, el)"
              class="monitor-page__chart-canvas"
            ></div>
          </article>
        </div>
      </a-col>
    </a-row>
  </section>
</template>

<script setup>
import {
  InfoCircleOutlined,
  LoadingOutlined,
  ReloadOutlined,
} from '@ant-design/icons-vue';
import { message } from 'ant-design-vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as echarts from 'echarts';
import { useCompactPageMode } from '@/components/pages/useCompactPageMode';
import { useDashboardStore } from '@/stores/dashboard';
import { pinia } from '@/stores/pinia';
import { useActionLocks } from '@/composables/useActionLocks';
import cpuIcon from '@/assets/iconly-glass/cpu.svg';
import diskIcon from '@/assets/iconly-glass/Disk.svg';
import memoryIcon from '@/assets/iconly-glass/memory.svg';
import networkIcon from '@/assets/iconly-glass/network.svg';

const { pageRootRef, compactMode } = useCompactPageMode(980);
const store = useDashboardStore(pinia);
const { isActionLoading, runWithActionLoading } = useActionLocks();
const chartElements = new Map();
const charts = new Map();
const animatedMetricValues = ref({
  cpu: 0,
  memory: 0,
  io: 0,
  network: 0,
});
const animatedPerformanceScore = ref(0);
const hasPlayedPerformanceIntro = ref(false);
let metricAnimationFrame = 0;
let performanceAnimationFrame = 0;

const host = computed(() => store.host || {});
const statusText = computed(() => host.value.status || '未知');
const statusTone = computed(() => {
  const state = String(host.value.powerState || '').toLowerCase();
  const status = String(statusText.value || '');
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
    return 'warning';
  }
  if (state === 'running') return 'success';
  if (state === 'stopped') return 'danger';
  return 'neutral';
});
const latestCpu = computed(() => getLastMetric(store.monitors.cpu));
const latestMemory = computed(() => getLastMetric(store.monitors.memory));
const latestNetwork = computed(() => getLastMetric(store.monitors.network));
const networkPeak = computed(() => Math.max(...(store.monitors.network || [0])));
const latestLabel = computed(() => {
  const labels = store.monitors.labels || [];
  return labels[labels.length - 1] || '';
});

const ioValues = computed(() => (
  (store.monitors.labels || []).map((_, index) => {
    const cpu = Number(store.monitors.cpu?.[index] || 0);
    const memory = Number(store.monitors.memory?.[index] || 0);
    const network = Number(store.monitors.network?.[index] || 0);
    return Math.max(0, Math.round(cpu * 0.42 + memory * 0.28 + network / 18));
  })
));
const latestIo = computed(() => getLastMetric(ioValues.value));
const ioPeak = computed(() => Math.max(...(ioValues.value || [0])));

const performanceScore = computed(() => {
  const networkLoad = Math.min(100, Math.round(latestNetwork.value / 3));
  const ioLoad = Math.min(100, Math.round(latestIo.value / 5));

  return Math.max(
    0,
    Math.round(
      (100 - latestCpu.value) * 0.38
      + (100 - latestMemory.value) * 0.34
      + (100 - networkLoad) * 0.16
      + (100 - ioLoad) * 0.12,
    ),
  );
});
const performanceLabel = computed(() => {
  if (performanceScore.value >= 80) return '优';
  if (performanceScore.value >= 65) return '良';
  if (performanceScore.value >= 50) return '中';
  return '警惕';
});
const performanceColor = computed(() => {
  if (performanceScore.value >= 80) return '#22c55e';
  if (performanceScore.value >= 65) return readThemeVar('--mmui-accent-blue', '#4460ff');
  if (performanceScore.value >= 50) return '#f59e0b';
  return '#ef4444';
});

const remoteRows = computed(() => ([
  { label: '实例状态', value: statusText.value, tone: statusTone.value },
  { label: '虚拟方案', value: host.value.virtualType || '-' },
  { label: '远程地址', value: host.value.remoteAddress || '-', mono: true },
  { label: '到期时间', value: host.value.expireDate || '-' },
]));

const monitorCards = computed(() => ([
  {
    key: 'cpu',
    title: 'CPU 使用率',
    unit: '%',
    color: '#22c55e',
    icon: cpuIcon,
    values: store.monitors.cpu || [],
    currentText: `${animatedMetricValues.value.cpu}%`,
    hintText: `${host.value.cpu || 0} 核`,
  },
  {
    key: 'memory',
    title: '内存 使用率',
    unit: '%',
    color: readThemeVar('--mmui-accent-blue', '#4460ff'),
    icon: memoryIcon,
    values: store.monitors.memory || [],
    currentText: `${animatedMetricValues.value.memory}%`,
    hintText: `${host.value.memory || 0} GB`,
  },
  {
    key: 'io',
    title: '磁盘 IO',
    unit: 'MB/s',
    color: '#f59e0b',
    icon: diskIcon,
    values: ioValues.value,
    currentText: `${animatedMetricValues.value.io} MB/s`,
    hintText: `峰值 ${ioPeak.value} MB/s`,
  },
  {
    key: 'network',
    title: '网络 吞吐',
    unit: 'KBps',
    color: '#8b5cf6',
    icon: networkIcon,
    values: store.monitors.network || [],
    currentText: `${animatedMetricValues.value.network} KBps`,
    hintText: `峰值 ${networkPeak.value} KBps`,
  },
]));

const chartDataKey = computed(() => JSON.stringify({
  labels: store.monitors.labels || [],
  cpu: store.monitors.cpu || [],
  memory: store.monitors.memory || [],
  network: store.monitors.network || [],
  io: ioValues.value,
}));

watch(
  chartDataKey,
  async () => {
    await nextTick();
    animateMetricReadouts();
    animatePerformanceIndicator();
    renderCharts();
  },
);

async function refreshSummary() {
  await runWithActionLoading('monitor:refresh', async () => {
    try {
      await store.refreshMonitor();
      message.success('监控已刷新');
    } catch (error) {
      message.error(error?.message || '监控刷新失败');
    }
  });
}

function getLastMetric(values) {
  if (!Array.isArray(values) || !values.length) {
    return 0;
  }

  return Number(values[values.length - 1] || 0);
}

function readThemeVar(name, fallback) {
  if (typeof window === 'undefined') {
    return fallback;
  }

  const themeRoot = document.querySelector('.mmui-theme-root');
  const value = getComputedStyle(themeRoot || document.documentElement).getPropertyValue(name).trim();
  return value || fallback;
}

function setChartRef(key, el) {
  if (!el) {
    chartElements.delete(key);
    return;
  }

  chartElements.set(key, el);
  nextTick(() => renderChartByKey(key));
}

function renderCharts() {
  monitorCards.value.forEach((item) => renderMetricChart(item));
}

function renderChartByKey(key) {
  const item = monitorCards.value.find((entry) => entry.key === key);
  if (item) {
    renderMetricChart(item);
  }
}

function renderMetricChart(item) {
  const el = chartElements.get(item.key);
  if (!el) {
    return;
  }

  let chart = charts.get(item.key);
  if (!chart) {
    chart = echarts.init(el);
    charts.set(item.key, chart);
  }

  const axisColor = readThemeVar('--mmui-text-muted', '#8c8c8c');
  const splitLineColor = readThemeVar('--mmui-shell-border', '#f0f0f0');

  const visibleValues = getVisibleChartValues(item);
  const visibleLabels = getVisibleChartLabels(item);

  chart.setOption({
    animation: true,
    animationDuration: 420,
    animationDurationUpdate: 620,
    animationEasing: 'cubicOut',
    animationEasingUpdate: 'cubicOut',
    grid: {
      left: 8,
      right: 14,
      top: 14,
      bottom: 6,
      containLabel: true,
    },
    tooltip: {
      trigger: 'axis',
      appendToBody: true,
      backgroundColor: readThemeVar('--mmui-card-surface', '#161616'),
      borderColor: readThemeVar('--mmui-shell-border', '#2a2a2a'),
      textStyle: {
        color: readThemeVar('--mmui-card-title', '#d0d0d0'),
        fontSize: 12,
      },
    },
    xAxis: {
      type: 'category',
      boundaryGap: false,
      data: visibleLabels,
      axisLine: { show: false },
      axisTick: { show: false },
      axisLabel: {
        color: axisColor,
        fontSize: 11,
        hideOverlap: true,
      },
    },
    yAxis: {
      type: 'value',
      splitNumber: 4,
      axisLine: { show: false },
      axisTick: { show: false },
      splitLine: {
        lineStyle: {
          color: splitLineColor,
          opacity: 0.38,
        },
      },
      axisLabel: {
        color: axisColor,
        fontSize: 11,
      },
    },
    series: [
      {
        data: visibleValues,
        type: 'line',
        smooth: true,
        showSymbol: false,
        animation: true,
        animationDurationUpdate: 620,
        animationEasingUpdate: 'cubicOut',
        lineStyle: {
          width: 2.4,
          color: item.color,
          shadowBlur: 8,
          shadowColor: `${item.color}42`,
        },
        areaStyle: {
          color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
            { offset: 0, color: `${item.color}30` },
            { offset: 0.58, color: `${item.color}12` },
            { offset: 1, color: `${item.color}02` },
          ]),
        },
      },
    ],
  }, {
    notMerge: false,
    lazyUpdate: false,
  });
}

function resizeCharts() {
  charts.forEach((chart) => chart.resize());
}

function getMetricTargets() {
  return {
    cpu: latestCpu.value,
    memory: latestMemory.value,
    io: latestIo.value,
    network: latestNetwork.value,
  };
}

function getVisibleChartLabels(item) {
  const labels = store.monitors.labels || [];
  return labels.length ? labels : (item.values || []).map((_, index) => String(index + 1));
}

function getVisibleChartValues(item) {
  const values = item.values;
  return Array.isArray(values) ? values : [];
}

function easeOutCubic(progress) {
  const clamped = Math.max(0, Math.min(1, Number(progress) || 0));
  return 1 - (1 - clamped) ** 3;
}

function syncMetricReadouts({ syncPerformance = true } = {}) {
  const metricTargets = getMetricTargets();

  animatedMetricValues.value = {
    cpu: Math.round(metricTargets.cpu),
    memory: Math.round(metricTargets.memory),
    io: Math.round(metricTargets.io),
    network: Math.round(metricTargets.network),
  };

  if (syncPerformance) {
    animatedPerformanceScore.value = Math.round(performanceScore.value);
  }
}

function animateMetricReadouts() {
  const targets = getMetricTargets();

  if (typeof window === 'undefined') {
    syncMetricReadouts();
    return;
  }

  window.cancelAnimationFrame(metricAnimationFrame);
  const startValues = { ...animatedMetricValues.value };
  const startedAt = window.performance.now();
  const duration = hasPlayedPerformanceIntro.value ? 560 : 700;

  const tick = (now) => {
    const progress = easeOutCubic((now - startedAt) / duration);
    const nextValues = {};

    Object.keys(targets).forEach((key) => {
      const from = Number(startValues[key]) || 0;
      const to = Number(targets[key]) || 0;
      nextValues[key] = Math.round(from + ((to - from) * progress));
    });

    animatedMetricValues.value = nextValues;

    if (progress < 1) {
      metricAnimationFrame = window.requestAnimationFrame(tick);
      return;
    }

    animatedMetricValues.value = {
      cpu: Math.round(targets.cpu),
      memory: Math.round(targets.memory),
      io: Math.round(targets.io),
      network: Math.round(targets.network),
    };
    metricAnimationFrame = 0;
  };

  metricAnimationFrame = window.requestAnimationFrame(tick);
}

function animatePerformanceIndicator() {
  const target = Math.round(performanceScore.value);

  if (typeof window === 'undefined') {
    animatedPerformanceScore.value = target;
    hasPlayedPerformanceIntro.value = true;
    return;
  }

  window.cancelAnimationFrame(performanceAnimationFrame);
  const startValue = hasPlayedPerformanceIntro.value ? Number(animatedPerformanceScore.value) || 0 : 0;

  const start = window.performance.now();
  const duration = hasPlayedPerformanceIntro.value ? 620 : 760;

  const tick = (now) => {
    const progress = easeOutCubic((now - start) / duration);
    animatedPerformanceScore.value = Math.round(startValue + ((target - startValue) * progress));

    if (progress < 1) {
      performanceAnimationFrame = window.requestAnimationFrame(tick);
      return;
    }

    animatedPerformanceScore.value = target;
    hasPlayedPerformanceIntro.value = true;
  };

  performanceAnimationFrame = window.requestAnimationFrame(tick);
}

onMounted(() => {
  syncMetricReadouts({ syncPerformance: false });
  nextTick(() => {
    renderCharts();
    animatePerformanceIndicator();
  });
  window.addEventListener('resize', resizeCharts);
});

onBeforeUnmount(() => {
  window.cancelAnimationFrame(metricAnimationFrame);
  window.cancelAnimationFrame(performanceAnimationFrame);
  window.removeEventListener('resize', resizeCharts);
  charts.forEach((chart) => chart.dispose());
  charts.clear();
  chartElements.clear();
});
</script>

<style scoped>
.monitor-page {
  width: 100%;
  min-width: 0;
  overflow-x: hidden;
}

.monitor-page :deep(.ant-row),
.monitor-page :deep(.ant-col) {
  min-width: 0;
  max-width: 100%;
}

.monitor-page__header-shell {
  margin: 8px 8px 18px;
}

.monitor-page__header {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 54px;
}

.monitor-page__title {
  padding-left: 8px;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-hero-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-page);
}

.monitor-page__summary {
  margin-left: auto;
  min-height: 42px;
  padding: 0 14px;
  border: 1px solid var(--mmui-shell-border);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.02);
}

.monitor-page__summary-label,
.monitor-page__summary-hint {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.monitor-page__summary-hint {
  color: var(--mmui-text-muted);
}

.monitor-page__summary-value {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.monitor-page__summary-help {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 32px;
  min-width: 32px;
  height: 32px;
  padding: 0;
  line-height: 1 !important;
  color: var(--mmui-text-muted) !important;
}

.monitor-page__summary-help :deep(.anticon) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.monitor-page__notice.ant-alert {
  margin: 0 8px 16px;
  padding: 12px;
  border-radius: 2px;
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.monitor-page__aurora-grid {
  display: grid;
  grid-template-columns: repeat(24, minmax(0, 1fr));
  gap: 16px;
  box-sizing: border-box;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  margin: 0 8px 12px;
}

.monitor-page__remote-panel,
.monitor-page__chart-card {
  box-sizing: border-box;
  max-width: 100%;
  min-width: 0;
  min-height: 304px;
  overflow: hidden;
  border: 0;
  border-radius: 10px;
  background: var(--mmui-card-surface);
}

.monitor-page__remote-panel {
  grid-column: span 16;
  position: relative;
  padding: 24px 32px;
}

.monitor-page__chart-card {
  grid-column: span 8;
  display: flex;
  flex-direction: column;
  width: 100%;
  --monitor-chart-x-padding: 18px;
}

.monitor-page__panel-title {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.monitor-page__remote-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 248px;
  gap: 28px;
  align-items: center;
  min-width: 0;
  margin-top: 18px;
}

.monitor-page__remote-list {
  display: grid;
  gap: 0;
  min-width: 0;
  max-width: 520px;
}

.monitor-page__remote-row {
  display: grid;
  grid-template-columns: 96px minmax(0, 1fr);
  align-items: center;
  gap: 16px;
  min-height: 42px;
}

.monitor-page__remote-row span {
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.monitor-page__remote-row strong {
  min-width: 0;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-body);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-line-height-body);
  word-break: break-word;
  overflow-wrap: anywhere;
}

.monitor-page__remote-row strong.is-mono {
  font-family: ui-monospace, Menlo, Consolas, monospace;
}

.monitor-page__remote-status {
  display: inline-flex !important;
  align-items: center;
  gap: 8px;
  width: fit-content;
  color: var(--mmui-text-muted);
  line-height: var(--mmui-line-height-body);
}

.monitor-page__remote-status i {
  width: 8px;
  min-width: 8px;
  height: 8px;
  border-radius: 999px;
  background: currentColor;
}

.monitor-page__remote-status.is-success {
  color: #53d769;
}

.monitor-page__remote-status.is-danger {
  color: #ff6b72;
}

.monitor-page__remote-status.is-warning {
  color: #f6b73c;
}

.monitor-page__remote-gauge {
  display: grid;
  align-content: center;
  justify-items: center;
  gap: 8px;
  width: 100%;
  min-width: 0;
  height: 100%;
  min-height: 180px;
  padding-top: 0;
}

.monitor-page__gauge-stage {
  position: relative;
  width: clamp(224px, 22vw, 248px);
  max-width: 100%;
  aspect-ratio: 220 / 156;
  height: auto;
}

.monitor-page__gauge-stage svg {
  display: block;
  width: 100%;
  height: 100%;
  overflow: visible;
}

.monitor-page__gauge-track,
.monitor-page__gauge-progress {
  fill: none;
  stroke-width: 18;
  stroke-linecap: round;
}

.monitor-page__gauge-track {
  stroke: color-mix(in srgb, var(--mmui-shell-border) 58%, transparent);
}

.monitor-page__gauge-progress {
  stroke: var(--gauge-color);
  filter: drop-shadow(0 0 10px color-mix(in srgb, var(--gauge-color) 30%, transparent));
}

.monitor-page__gauge-score {
  fill: var(--mmui-card-title);
  font-size: 24px;
  font-weight: 700;
  dominant-baseline: middle;
}

.monitor-page__gauge-copy {
  position: absolute;
  left: 0;
  right: 0;
  top: 67%;
  display: inline-flex;
  align-items: baseline;
  justify-content: center;
  gap: 6px;
  text-align: center;
  white-space: nowrap;
}

.monitor-page__gauge-copy strong {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.monitor-page__gauge-copy span {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.monitor-page__chart-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 62px;
  padding: 0 var(--monitor-chart-x-padding);
  background: transparent;
}

.monitor-page__chart-title {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-line-height-headline);
}

.monitor-page__chart-icon {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  flex: 0 0 36px;
  height: 36px;
  border-radius: 12px;
  background: rgba(var(--mmui-accent-blue-rgb), 0.1);
}

.monitor-page__chart-icon img {
  display: block;
  width: 34px;
  height: 34px;
  object-fit: contain;
}

.monitor-page__chart-title span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.monitor-page__chart-unit {
  flex: 0 0 auto;
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
}

.monitor-page__chart-meta {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  padding: 14px var(--monitor-chart-x-padding) 4px;
}

.monitor-page__chart-meta strong {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.monitor-page__chart-meta span {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
  text-align: right;
}

.monitor-page__chart-canvas {
  flex: 1 1 auto;
  box-sizing: border-box;
  width: auto;
  max-width: 100%;
  min-width: 0;
  height: 212px;
  min-height: 0;
  margin: 0 var(--monitor-chart-x-padding);
  padding: 0 0 12px;
}

@media (max-width: 1180px) {
  .monitor-page__remote-panel {
    grid-column: 1 / -1;
  }

  .monitor-page__chart-card {
    grid-column: span 12;
  }
}

@media (max-width: 1023px) {
  .monitor-page__header-shell {
    margin: 8px 8px 14px;
  }

  .monitor-page__header {
    flex-wrap: wrap;
  }

  .monitor-page__title {
    padding-left: 0;
  }

  .monitor-page__summary {
    margin-left: 0;
    min-height: 38px;
    padding: 0 12px;
  }

  .monitor-page__summary-label,
  .monitor-page__summary-hint {
    white-space: nowrap;
    font-size: 12px;
  }

  .monitor-page__summary-value {
    font-size: 16px;
  }

  .monitor-page__remote-layout {
    grid-template-columns: minmax(0, 1fr) 224px;
    gap: 18px;
  }

  .monitor-page__remote-gauge {
    width: 100%;
    padding-top: 0;
  }
}

@media (max-width: 720px) {
  .monitor-page__header-shell,
  .monitor-page__notice.ant-alert,
  .monitor-page__aurora-grid {
    margin-right: 0;
    margin-left: 0;
  }

  .monitor-page__aurora-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .monitor-page__remote-panel,
  .monitor-page__chart-card {
    grid-column: 1 / -1;
    width: 100%;
  }

  .monitor-page__chart-card {
    --monitor-chart-x-padding: 14px;
    min-height: 252px;
  }

  .monitor-page__chart-head {
    min-height: 56px;
  }

  .monitor-page__chart-meta {
    padding-top: 10px;
  }

  .monitor-page__chart-canvas {
    height: 168px;
    padding-bottom: 10px;
  }

  .monitor-page__remote-panel {
    min-height: 252px;
    padding: 16px 18px;
  }

  .monitor-page__remote-layout {
    grid-template-columns: minmax(0, 1fr) clamp(150px, 34vw, 188px);
    gap: 12px;
    margin-top: 12px;
  }

  .monitor-page__remote-gauge {
    min-height: 124px;
  }

  .monitor-page__gauge-stage {
    width: clamp(150px, 34vw, 188px);
  }

  .monitor-page__remote-row {
    grid-template-columns: 78px minmax(0, 1fr);
    gap: 10px;
    min-height: 34px;
  }

  .monitor-page__gauge-copy {
    gap: 4px;
  }

  .monitor-page__gauge-copy strong {
    font-size: 16px;
  }

  .monitor-page__gauge-copy span {
    font-size: 12px;
  }
}

.mmui-theme-root[data-mmui-theme='dark'] .monitor-page__remote-panel,
.mmui-theme-root[data-mmui-theme='dark'] .monitor-page__chart-card {
  background: var(--mmui-card-surface);
}

.mmui-theme-root[data-mmui-theme='dark'] .monitor-page__chart-head,
.mmui-theme-root[data-mmui-theme='dark'] .monitor-page__chart-icon {
  background-image: none;
}

.mmui-theme-root[data-mmui-theme='dark'] .monitor-page__chart-icon {
  background-color: rgba(var(--mmui-accent-blue-rgb), 0.12);
}

@media (max-width: 420px) {
  .monitor-page__chart-card {
    --monitor-chart-x-padding: 12px;
    min-height: 228px;
  }

  .monitor-page__chart-head {
    min-height: 52px;
  }

  .monitor-page__chart-meta {
    padding-top: 8px;
  }

  .monitor-page__chart-canvas {
    height: 148px;
    padding-bottom: 8px;
  }

  .monitor-page__remote-panel {
    min-height: 226px;
    padding: 14px;
  }

  .monitor-page__remote-layout {
    grid-template-columns: minmax(0, 1fr) min(142px, 34vw);
    gap: 8px;
    margin-top: 10px;
  }

  .monitor-page__remote-gauge {
    min-height: 108px;
  }

  .monitor-page__gauge-stage {
    width: min(142px, 34vw);
  }

  .monitor-page__remote-row {
    grid-template-columns: 64px minmax(0, 1fr);
    gap: 8px;
    min-height: 30px;
  }

  .monitor-page__remote-row span,
  .monitor-page__remote-row strong,
  .monitor-page__gauge-copy span {
    font-size: 12px;
  }

  .monitor-page__gauge-copy strong {
    font-size: 14px;
  }
}
</style>

