<template>
  <a-tour
    v-model:current="activeIndex"
    root-class-name="mmui-tutorial-tour"
    :open="open"
    :steps="tourSteps"
    :gap="{ offset: 10, radius: 14 }"
    :mask="tourMask"
    :animated="{ placeholder: true }"
    :scroll-into-view-options="scrollIntoViewOptions"
    @close="finish"
    @finish="finish"
  />
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
  auto: {
    type: Boolean,
    default: false,
  },
  storageKey: {
    type: String,
    default: 'mmui_tutorial_seen_v2x_2_0_0',
  },
});

const emit = defineEmits(['navigate']);

const open = ref(false);
const activeIndex = ref(0);
const tourMask = {
  color: 'rgba(6, 8, 15, 0.46)',
  style: {
    backdropFilter: 'blur(1.5px)',
  },
};
const scrollIntoViewOptions = {
  block: 'center',
  inline: 'nearest',
  behavior: 'smooth',
};

const steps = [
  {
    selector: '.dashboard-page__topbar',
    page: 'panel',
    title: '顶部状态',
    description: '这里显示实例名、运行状态、地区、远程地址和常用操作。',
  },
  {
    selector: '.dashboard-page__status-card',
    page: 'panel',
    title: '实时状态',
    description: 'CPU、内存和网络会周期更新。这里的刷新只刷新监控接口。',
  },
  {
    selector: '.dashboard-page__account-card',
    page: 'panel',
    title: '账号信息',
    description: '系统密码和面板密码默认隐藏，可查看、复制或修改。',
  },
  {
    selector: '.dashboard-page__remote-card',
    page: 'panel',
    title: '远程登录',
    description: '远程方式会根据系统和设备类型自动选择下载文件或唤起远程。',
  },
  {
    selector: '.dashboard-page__topbar-actions',
    page: 'panel',
    title: '更多操作',
    description: '重启、分享、刷新和更多入口会根据宽度自动收纳。',
  },
  {
    selector: '.mmui-sidebar-nav',
    page: 'panel',
    title: '功能菜单',
    description: '监控、系统、VNC、网络、快照、备份、映射、策略和建站都从这里进入。',
  },
  {
    selector: '.dashboard-page__network-card',
    page: 'panel',
    title: '网络信息',
    description: '公网、NAT、私网地址和流量使用率在这里快速查看。',
  },
];

const tourSteps = computed(() => steps.map((step) => ({
  title: step.title,
  description: step.description,
  target: () => document.querySelector(step.selector),
})));

watch(() => props.auto, (enabled) => {
  if (!enabled || hasSeen()) {
    return;
  }

  window.setTimeout(() => {
    start();
  }, 700);
}, { immediate: true });

watch(activeIndex, async (index) => {
  const step = steps[index];
  if (!step) {
    return;
  }

  emit('navigate', step.page || 'panel');
  await nextTick();
});

async function start(options = {}) {
  activeIndex.value = 0;
  emit('navigate', 'panel');
  await nextTick();
  open.value = true;
  if (!options.manual) {
    markSeen();
  }
}

function finish() {
  open.value = false;
  markSeen();
}

function reset() {
  try {
    window.localStorage.removeItem(props.storageKey);
  } catch (error) {
    // Ignore storage failures.
  }
}

function hasSeen() {
  try {
    return window.localStorage.getItem(props.storageKey) === '1';
  } catch (error) {
    return true;
  }
}

function markSeen() {
  try {
    window.localStorage.setItem(props.storageKey, '1');
  } catch (error) {
    // Ignore storage failures.
  }
}

defineExpose({
  start,
  reset,
});
</script>

<style>
.mmui-tutorial-tour.ant-tour {
  transition:
    left 360ms cubic-bezier(0.16, 1, 0.3, 1),
    top 360ms cubic-bezier(0.16, 1, 0.3, 1),
    opacity 220ms ease-out,
    transform 360ms cubic-bezier(0.16, 1, 0.3, 1);
}

.mmui-tutorial-tour .ant-tour-inner {
  border-radius: 14px;
  box-shadow: 0 18px 54px rgba(0, 0, 0, 0.22);
}

.mmui-tutorial-tour .ant-tour-title {
  font-weight: 800;
}

.mmui-tutorial-tour .ant-tour-description {
  line-height: 1.7;
}

.mmui-tutorial-tour.ant-tour-placement-left .ant-tour-inner,
.mmui-tutorial-tour.ant-tour-placement-leftTop .ant-tour-inner,
.mmui-tutorial-tour.ant-tour-placement-leftBottom .ant-tour-inner,
.mmui-tutorial-tour.ant-tour-placement-right .ant-tour-inner,
.mmui-tutorial-tour.ant-tour-placement-rightTop .ant-tour-inner,
.mmui-tutorial-tour.ant-tour-placement-rightBottom .ant-tour-inner {
  border-radius: 14px;
}

.ant-tour-mask .ant-tour-placeholder-animated {
  transition:
    x 380ms cubic-bezier(0.16, 1, 0.3, 1),
    y 380ms cubic-bezier(0.16, 1, 0.3, 1),
    width 380ms cubic-bezier(0.16, 1, 0.3, 1),
    height 380ms cubic-bezier(0.16, 1, 0.3, 1),
    rx 380ms cubic-bezier(0.16, 1, 0.3, 1);
}
</style>
