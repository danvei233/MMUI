<template>
  <a-card class="home-card power-card" :bordered="false" :body-style="{ padding: '0' }">
    <div class="power-card__media">
      <div class="power-card__screen">
        <div class="power-card__pattern"></div>
        <img v-if="host.image" :src="host.image" :alt="host.name || '默认主机屏'" />
        <div v-else class="power-card__placeholder"></div>
        <div v-if="!host.image" class="power-card__button">
          <PoweroffOutlined />
        </div>
      </div>
      <div class="power-card__overlay">
        <div class="power-card__title">{{ host.name }}</div>
        <div class="power-card__status">
          主机状态：
          <span>{{ host.status }}</span>
        </div>
      </div>
    </div>
    <div class="power-card__actions">
      <a-button
        v-for="action in visibleActions"
        :key="action.key"
        class="power-card__action"
        type="default"
      >
        <component :is="action.icon" />
      </a-button>
    </div>
  </a-card>
</template>

<script setup>
import {
  DisconnectOutlined,
  PoweroffOutlined,
  RedoOutlined,
  PlayCircleOutlined,
} from '@ant-design/icons-vue';
import { computed } from 'vue';

const props = defineProps({
  host: {
    type: Object,
    default: () => ({}),
  },
});

const actions = [
  { key: 'shutdown', icon: PoweroffOutlined, states: ['running'] },
  { key: 'start', icon: PlayCircleOutlined, states: ['stopped'] },
  { key: 'restart', icon: RedoOutlined },
  { key: 'forceoff', icon: DisconnectOutlined },
];

const visibleActions = computed(() => {
  const state = props.host.powerState || 'running';
  return actions.filter((action) => !action.states || action.states.includes(state));
});
</script>

<style scoped>
.power-card {
  margin: 0 8px;
  margin-top: 0;
  margin-bottom: 0;
  border-radius: 6px !important;
  display: flex;
  flex-direction: column;
  min-height: 100%;
}

.power-card :deep(.ant-card-body) {
  padding: 0 !important;
  display: flex;
  flex-direction: column;
  min-height: 100%;
}

.power-card__media {
  position: relative;
  overflow: hidden;
  border-radius: 6px 6px 0 0;
  background: var(--mmui-screen-surface);
  flex: 0 0 auto;
}

.power-card__screen {
  position: relative;
  width: 100%;
  min-height: 150px;
  overflow: hidden;
  border-radius: 6px 6px 0 0;
}

.power-card__media img {
  position: absolute;
  inset: 0;
  display: block;
  width: 100%;
  height: 100%;
  object-fit: fill;
  border-radius: 6px 6px 0 0;
}

.power-card__placeholder {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}

.power-card__button {
  position: absolute;
  top: 50%;
  left: 50%;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30%;
  height: 49%;
  border: 2px solid var(--mmui-power-ring-border);
  border-radius: 50%;
  background-color: var(--mmui-power-ring-bg);
  color: var(--mmui-power-ring-color);
  transform: translate(-50%, -50%);
  transition: 0.3s;
}

.power-card__button :deep(.anticon) {
  font-size: 39px;
}

.power-card__pattern {
  position: absolute;
  inset: 0;
  z-index: 1;
  width: 200%;
  height: 200%;
  background: repeating-linear-gradient(
    45deg,
    var(--mmui-screen-line),
    var(--mmui-screen-line) 2px,
    transparent 2px,
    transparent 10px
  );
  opacity: var(--mmui-screen-line-opacity);
  animation: power-card-lines 6s linear infinite;
  transform: translate(-50%, -50%);
  transform-origin: top left;
}

.power-card__overlay {
  position: absolute;
  inset: auto 0 0;
  z-index: 3;
  min-height: 66px;
}

.power-card__overlay::before {
  content: '';
  position: absolute;
  inset: 0;
  background: var(--mmui-screen-overlay);
}

.power-card__title,
.power-card__status {
  position: relative;
  z-index: 1;
  user-select: none;
  color: var(--mmui-card-hero-text);
}

.power-card__title {
  padding: 10px 5px 0 11px;
  font-size: var(--mmui-font-hero-title);
  font-weight: var(--mmui-font-weight-regular);
  line-height: var(--mmui-line-height-page);
  letter-spacing: 0;
  text-shadow: none;
}

.power-card__status {
  padding: 7px 5px 7px 11px;
  font-size: var(--mmui-font-subtitle);
  font-weight: var(--mmui-font-weight-regular);
  line-height: var(--mmui-line-height-footnote);
  color: var(--mmui-card-hero-subtext);
  text-shadow: none;
}

.power-card__status span {
  color: inherit;
}

.power-card__actions {
  display: flex;
  padding: 16px 32px;
  margin: 10px 0;
}

.power-card__action:deep(.anticon) {
  font-size: 20px;
}

.power-card__action {
  flex: 1 1 auto;
  min-width: 1px;
  height: 42px;
  border-radius: 0 !important;
  border: 1px solid var(--mmui-card-action-border) !important;
  box-shadow: none !important;
  background: var(--mmui-card-action-bg) !important;
  color: var(--mmui-card-action-text) !important;
}

.power-card__action + .power-card__action {
  border-left: 0 !important;
}

.power-card__action:first-child {
  border-radius: 4px 0 0 4px !important;
  color: #f7c531 !important;
}

.power-card__action:nth-child(2) {
  color: #2d7fe8 !important;
}

.power-card__action:nth-child(3) {
  border-radius: 0 4px 4px 0 !important;
  color: #ff5b22 !important;
}

@media (min-width: 1600px) {
  .power-card {
    min-height: 370px;
  }

  .power-card__screen {
    min-height: 250px;
  }

  .power-card__overlay {
    min-height: 86px;
  }

  .power-card__title {
    padding: 18px 20px 0;
    font-size: var(--mmui-font-size-title-1);
  }

  .power-card__status {
    padding: 8px 20px 18px;
    font-size: var(--mmui-font-size-title);
  }

  .power-card__actions {
    padding: 20px 38px 28px;
    margin: 0;
  }

  .power-card__action {
    height: 46px;
  }
}

@keyframes power-card-lines {
  from {
    transform: translate(-50%, -50%);
  }

  to {
    transform: translate(-45%, -45%);
  }
}
</style>
