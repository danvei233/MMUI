<template>
  <section
    class="resource-hero"
    :class="{
      'is-plain': !heroImage,
      'has-compact-summary': normalizedCompactSummaryItems.length,
      'hide-subtitle-on-compact': hideSubtitleOnCompact,
    }"
  >
    <div class="resource-hero__main">
      <div class="resource-hero__title-row">
        <h1>{{ title }}</h1>
        <a-tooltip v-if="helpText" :title="helpText">
          <a-button
            class="resource-hero__help"
            type="text"
            shape="circle"
            :aria-label="helpAriaLabel || `${title}说明`"
          >
            <InfoCircleOutlined />
          </a-button>
        </a-tooltip>
        <div v-if="normalizedCompactSummaryItems.length" class="resource-hero__compact-summary">
          <template v-for="item in normalizedCompactSummaryItems" :key="item.label">
            <span class="resource-hero__compact-summary-label">{{ item.label }}</span>
            <span class="resource-hero__compact-summary-value">{{ item.value }}</span>
          </template>
          <a-tooltip v-if="helpText" :title="helpText">
            <a-button
              class="resource-hero__compact-summary-help"
              type="text"
              shape="circle"
              :aria-label="helpAriaLabel || `${title}说明`"
            >
              <InfoCircleOutlined />
            </a-button>
          </a-tooltip>
        </div>
      </div>
      <div class="resource-hero__subtitle">{{ subtitle }}</div>

      <div class="resource-hero__stats">
        <a-card class="resource-hero__stat-card resource-hero__stat-card--main">
          <div class="resource-hero__stat-copy">
            <a-statistic :title="summaryLabel" :value="primaryValue">
              <template v-if="showPrimaryTotal" #suffix>
                <span class="resource-hero__stat-total">/ {{ normalizedPrimaryTotal }}</span>
              </template>
            </a-statistic>
          </div>
          <div class="resource-hero__ring">
            <a-progress
              type="circle"
              :percent="animatedProgressPercent"
              :size="94"
              :stroke-width="10"
              stroke-linecap="round"
              stroke-color="var(--mmui-accent-blue)"
              trail-color="rgba(var(--mmui-accent-blue-rgb), 0.1)"
              :format="() => ''"
            />
          </div>
        </a-card>

        <a-card
          v-for="item in normalizedStatCards"
          :key="item.label"
          class="resource-hero__stat-card resource-hero__stat-card--mini"
        >
          <a-statistic :title="item.label" :value="item.value" />
        </a-card>
      </div>
    </div>

    <div v-if="heroImage" class="resource-hero__visual" aria-hidden="true">
      <img class="resource-hero__image" :src="heroImage" alt="" />
    </div>
  </section>
</template>

<script setup>
import { InfoCircleOutlined } from '@ant-design/icons-vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
  summaryLabel: {
    type: String,
    required: true,
  },
  primaryValue: {
    type: [Number, String],
    required: true,
  },
  primaryTotal: {
    type: [Number, String, null],
    default: null,
  },
  progressPercent: {
    type: Number,
    default: null,
  },
  statCards: {
    type: Array,
    default: () => [],
  },
  heroImage: {
    type: String,
    default: '',
  },
  helpText: {
    type: String,
    default: '',
  },
  helpAriaLabel: {
    type: String,
    default: '',
  },
  compactSummaryItems: {
    type: Array,
    default: () => [],
  },
  hideSubtitleOnCompact: {
    type: Boolean,
    default: false,
  },
});

const normalizedPrimaryTotal = computed(() => {
  if (typeof props.primaryTotal === 'string') {
    const text = props.primaryTotal.trim();
    if (!text) {
      return null;
    }

    const numericValue = Number(text);
    return Number.isFinite(numericValue) && numericValue > 0 ? numericValue : text;
  }

  const value = Number(props.primaryTotal);
  if (!Number.isFinite(value) || value <= 0) {
    return null;
  }

  return value;
});

const showPrimaryTotal = computed(() => normalizedPrimaryTotal.value !== null);

const normalizedProgressPercent = computed(() => {
  const explicitPercent = Number(props.progressPercent);
  if (Number.isFinite(explicitPercent)) {
    return Math.min(100, Math.max(0, Math.round(explicitPercent)));
  }

  const total = normalizedPrimaryTotal.value;
  const value = Number(props.primaryValue);
  if (!Number.isFinite(Number(total)) || !Number.isFinite(value)) {
    return 0;
  }

  return Math.min(100, Math.max(0, Math.round((value / Number(total)) * 100)));
});

const animatedProgressPercent = ref(0);
let progressAnimationFrame = 0;
let hasMounted = false;

function easeOutCubic(progress) {
  return 1 - (1 - progress) ** 3;
}

function animateProgressTo(targetPercent) {
  const target = Math.min(100, Math.max(0, Math.round(Number(targetPercent) || 0)));

  if (typeof window === 'undefined' || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
    animatedProgressPercent.value = target;
    return;
  }

  window.cancelAnimationFrame(progressAnimationFrame);

  const startValue = animatedProgressPercent.value;
  const delta = target - startValue;
  const startTime = window.performance.now();
  const duration = 680;

  const tick = (now) => {
    const progress = Math.min(1, (now - startTime) / duration);
    animatedProgressPercent.value = Math.round((startValue + delta * easeOutCubic(progress)) * 10) / 10;

    if (progress < 1) {
      progressAnimationFrame = window.requestAnimationFrame(tick);
      return;
    }

    animatedProgressPercent.value = target;
  };

  progressAnimationFrame = window.requestAnimationFrame(tick);
}

watch(
  normalizedProgressPercent,
  (nextPercent) => {
    if (!hasMounted) {
      return;
    }

    animateProgressTo(nextPercent);
  },
);

const normalizedStatCards = computed(() => (
  Array.isArray(props.statCards)
    ? props.statCards.filter((item) => item && item.label !== undefined).slice(0, 2)
    : []
));

const normalizedCompactSummaryItems = computed(() => (
  Array.isArray(props.compactSummaryItems)
    ? props.compactSummaryItems
      .filter((item) => item && item.label !== undefined)
      .map((item) => ({
        label: item.label,
        value: item.value ?? '--',
      }))
      .slice(0, 3)
    : []
));

onMounted(() => {
  hasMounted = true;
  animatedProgressPercent.value = 0;
  animateProgressTo(normalizedProgressPercent.value);
});

onBeforeUnmount(() => {
  window.cancelAnimationFrame(progressAnimationFrame);
});
</script>

<style scoped>
.resource-hero {
  position: relative;
  display: block;
  margin: 0 8px 28px;
  padding: 0;
  overflow: visible;
  background: transparent;
}

.resource-hero__main {
  position: relative;
  z-index: 1;
  display: grid;
  align-content: start;
  gap: 14px;
  width: min(100%, 720px);
  min-width: 0;
}

.resource-hero__title-row {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-height: 54px;
  min-width: 0;
}

.resource-hero__title-row h1 {
  margin: 0;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title-1);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-title-1);
  letter-spacing: 0;
}

.resource-hero__help {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  min-width: 34px;
  height: 34px;
  padding: 0;
  color: var(--mmui-text-muted) !important;
}

.resource-hero__subtitle {
  width: fit-content;
  max-width: 100%;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-subheadline);
  line-height: var(--mmui-line-height-subheadline);
}

.resource-hero__compact-summary {
  display: none;
  align-items: center;
  gap: 12px;
  min-height: 42px;
  margin-left: auto;
  padding: 0 14px;
  border: 1px solid var(--mmui-shell-border);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.02);
  white-space: nowrap;
}

.resource-hero__compact-summary-label {
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.resource-hero__compact-summary-value {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.resource-hero__compact-summary-help {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px !important;
  min-width: 32px !important;
  height: 32px !important;
  padding: 0 !important;
  color: var(--mmui-text-muted) !important;
  border-radius: 999px;
}

.resource-hero__stats {
  display: grid;
  grid-template-columns: minmax(240px, 1.35fr) repeat(2, minmax(104px, 0.55fr));
  gap: 10px;
  width: min(100%, 640px);
}

.resource-hero__stat-card {
  min-width: 0;
}

.resource-hero__stat-card :deep(.ant-card-body) {
  height: 100%;
  padding: 18px;
}

.resource-hero__stat-card--main :deep(.ant-card-body) {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  min-height: 124px;
}

.resource-hero__stat-copy {
  min-width: 0;
}

.resource-hero__stat-copy :deep(.ant-statistic-title),
.resource-hero__stat-card--mini :deep(.ant-statistic-title) {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-body);
  line-height: var(--mmui-line-height-body);
}

.resource-hero__stat-copy :deep(.ant-statistic-content) {
  color: var(--mmui-text-muted);
  line-height: 1;
}

.resource-hero__stat-copy :deep(.ant-statistic-content-value) {
  color: var(--mmui-accent-blue);
  font-size: 44px;
  font-weight: 800;
  line-height: 0.9;
}

.resource-hero__stat-total {
  display: inline-flex;
  margin-left: 8px;
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-page);
  font-weight: var(--mmui-text-headline-weight);
}

.resource-hero__ring {
  position: relative;
  display: grid;
  place-items: center;
  width: 94px;
  min-width: 94px;
  height: 94px;
}

.resource-hero__ring :deep(.ant-progress) {
  position: absolute;
  inset: 0;
}

.resource-hero__stat-card--mini :deep(.ant-card-body) {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 124px;
}

.resource-hero__stat-card--mini :deep(.ant-statistic-content-value) {
  color: var(--mmui-card-title);
  font-size: 29px;
  font-weight: 800;
  line-height: 1;
}

.resource-hero__visual {
  position: absolute;
  z-index: -1;
  top: 0;
  right: 0;
  width: clamp(200px, 16vw, 400px);
  height: auto;
  overflow: visible;
  pointer-events: none;
}

.resource-hero__image {
  display: block;
  width: 100%;
  height: auto;
  object-fit: contain;
  object-position: right top;
  opacity: 0.6;
  filter: drop-shadow(0 22px 36px rgba(67, 103, 190, 0.12));
  mask-image: linear-gradient(90deg, transparent 0, #000 24%, #000 100%);
  transform: scale(2);
  transform-origin: right top;
}

@media (max-width: 1023px) {
  .resource-hero__visual {
    display: none;
  }

  .resource-hero__main {
    width: 100%;
  }

  .resource-hero__stats {
    grid-template-columns: minmax(0, 1fr);
    width: 100%;
  }

  .resource-hero__stat-card--mini :deep(.ant-card-body) {
    justify-content: flex-start;
    min-height: 92px;
  }

  .resource-hero__title-row {
    display: flex;
    width: 100%;
    min-height: 48px;
  }

  .resource-hero__title-row h1 {
    font-size: var(--mmui-font-hero-title);
  }

  .resource-hero.has-compact-summary .resource-hero__stats {
    display: none;
  }

  .resource-hero.has-compact-summary .resource-hero__compact-summary {
    display: inline-flex;
  }

  .resource-hero.has-compact-summary .resource-hero__help {
    display: none;
  }

  .resource-hero.hide-subtitle-on-compact .resource-hero__subtitle {
    display: none;
  }
}

@media (max-width: 640px) {
  .resource-hero {
    margin-inline: 0;
  }
}
</style>

