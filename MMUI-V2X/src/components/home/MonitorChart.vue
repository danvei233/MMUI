<template>
  <a-card class="home-card monitor-chart" :bordered="false">
    <div class="monitor-chart__header">
      <div class="monitor-chart__title">
        <component :is="icon" />
        <span>{{ title }}</span>
      </div>
      <div class="monitor-chart__unit">{{ unit }}</div>
    </div>
    <div ref="chartRef" class="monitor-chart__canvas"></div>
    <div v-if="currentText || hintText" class="monitor-chart__foot">
      <strong v-if="currentText">{{ currentText }}</strong>
      <small v-if="hintText">{{ hintText }}</small>
    </div>
  </a-card>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as echarts from 'echarts';

const props = defineProps({
  title: String,
  unit: String,
  color: String,
  labels: {
    type: Array,
    default: () => [],
  },
  values: {
    type: Array,
    default: () => [],
  },
  icon: {
    type: [Object, Function],
    required: true,
  },
  currentText: String,
  hintText: String,
});

const chartRef = ref(null);
let chart;

function readThemeVar(name, fallback) {
  if (typeof window === 'undefined') {
    return fallback;
  }

  const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return value || fallback;
}

function renderChart() {
  if (!chartRef.value) {
    return;
  }

  if (!chart) {
    chart = echarts.init(chartRef.value);
  }

  const axisColor = readThemeVar('--mmui-text-muted', '#8c8c8c');
  const splitLineColor = readThemeVar('--mmui-divider', '#f0f0f0');

  chart.setOption({
    animation: true,
    animationDuration: 420,
    animationDurationUpdate: 620,
    animationEasing: 'cubicOut',
    animationEasingUpdate: 'cubicOut',
    grid: {
      left: 10,
      right: 10,
      top: 20,
      bottom: 14,
      containLabel: true,
    },
    tooltip: {
      trigger: 'axis',
    },
    xAxis: {
      type: 'category',
      boundaryGap: false,
      data: props.labels,
      axisLine: { show: false },
      axisTick: { show: false },
      axisLabel: {
        color: axisColor,
        fontSize: 11,
      },
    },
    yAxis: {
      type: 'value',
      axisLine: { show: false },
      axisTick: { show: false },
      splitLine: {
        lineStyle: {
          color: splitLineColor,
        },
      },
      axisLabel: {
        color: axisColor,
        fontSize: 11,
      },
    },
    series: [
      {
        data: props.values,
        type: 'line',
        smooth: true,
        showSymbol: false,
        animation: true,
        animationDurationUpdate: 620,
        animationEasingUpdate: 'cubicOut',
        lineStyle: {
          width: 2,
          color: props.color,
        },
        areaStyle: {
          color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
            { offset: 0, color: `${props.color}80` },
            { offset: 1, color: `${props.color}10` },
          ]),
        },
      },
    ],
  }, {
    notMerge: false,
    lazyUpdate: false,
  });
}

function resizeChart() {
  chart?.resize();
}

onMounted(() => {
  renderChart();
  window.addEventListener('resize', resizeChart);
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', resizeChart);
  chart?.dispose();
});

watch(() => [props.labels, props.values], renderChart, { deep: true });
</script>

<style scoped>
.monitor-chart {
  margin: 0;
  border-radius: 6px !important;
  width: 100%;
}

.monitor-chart :deep(.ant-card-body) {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 0 !important;
}

.monitor-chart__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 0;
}

.monitor-chart__title {
  display: inline-flex;
  align-items: center;
  gap: 0;
  font-size: var(--mmui-font-title);
  font-weight: var(--mmui-text-headline-weight);
  padding: 14px 8px 8px 14px;
  line-height: var(--mmui-line-height-title);
  color: var(--mmui-card-title);
}

.monitor-chart__title :deep(.anticon) {
  margin-right: 10px;
  font-size: 18px;
}

.monitor-chart__unit {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  min-height: 48px;
  padding-right: 18px;
  color: var(--mmui-monitor-unit);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
}

.monitor-chart__canvas {
  width: 100%;
  height: 170px;
  bottom: 0;
}

.monitor-chart__foot {
  display: grid;
  gap: 6px;
  margin-top: auto;
  padding: 4px 18px 18px;
}

.monitor-chart__foot strong {
  display: block;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title-2);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-title-2);
}

.monitor-chart__foot small {
  display: block;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

@media (min-width: 1600px) {
  .monitor-chart {
    border-radius: 8px !important;
  }

  .monitor-chart__title {
    font-size: var(--mmui-font-size-section);
    padding: 18px 10px 12px 16px;
  }

  .monitor-chart__title :deep(.anticon) {
    margin-right: 12px;
    font-size: 22px;
  }

  .monitor-chart__unit {
    min-height: 58px;
    padding-right: 20px;
    font-size: var(--mmui-font-size-body);
  }

  .monitor-chart__canvas {
    height: 220px;
  }

  .monitor-chart__foot {
    padding: 0 20px 20px;
  }

  .monitor-chart__foot strong {
    font-size: var(--mmui-font-size-title-1);
  }

  .monitor-chart__foot small {
    font-size: var(--mmui-font-size-body);
  }
}
</style>
