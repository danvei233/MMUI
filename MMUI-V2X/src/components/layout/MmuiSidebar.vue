<template>
  <nav class="mmui-sidebar-nav" :class="{ 'is-collapsed': collapsed }" aria-label="主导航">
    <a class="mmui-sidebar-brand" href="javascript:;">
      <BrandLogo class="mmui-sidebar-brand__icon" :src="logo" />
      <span class="mmui-sidebar-brand__text">{{ title || '云管理系统' }}</span>
    </a>

    <ul class="mmui-sidebar-list">
      <li
        v-for="entry in sidebarEntries"
        :key="entry.key"
        class="mmui-sidebar-list__entry"
      >
        <button
          type="button"
          class="mmui-sidebar-item"
          :class="{ 'mmui-sidebar-item--active': isEntryActive(entry) }"
          :title="collapsed ? entry.label : ''"
          @click="handleEntryClick(entry)"
        >
          <span class="mmui-sidebar-item__icon">
            <component :is="entry.icon" />
          </span>
          <span class="mmui-sidebar-item__label">{{ entry.label }}</span>
          <span
            v-if="isEntryActive(entry)"
            class="mmui-sidebar-item__meta"
          >
            <RightOutlined />
          </span>
        </button>
      </li>
    </ul>

    <div class="mmui-sidebar-footer">
      <div class="mmui-sidebar-footer__divider"></div>
      <div class="mmui-sidebar-footer__actions">
        <button type="button" class="mmui-sidebar-item mmui-sidebar-item--exit" :title="collapsed ? '退出' : ''" @click="handleExit">
          <span class="mmui-sidebar-item__icon">
            <LogoutOutlined />
          </span>
          <span class="mmui-sidebar-item__label">退出</span>
        </button>

        <button
          type="button"
          class="mmui-sidebar-collapse"
          :aria-label="collapsed ? '展开侧边栏' : '收起侧边栏'"
          @click="emit('toggle-sidebar')"
        >
          <component :is="collapsed ? DoubleRightOutlined : DoubleLeftOutlined" />
        </button>
      </div>
    </div>
  </nav>
</template>

<script setup>
import {
  DoubleLeftOutlined,
  DoubleRightOutlined,
  LogoutOutlined,
  RightOutlined,
} from '@ant-design/icons-vue';
import BrandLogo from '@/components/layout/BrandLogo.vue';
import { mmuiSidebarEntries } from '@/config/navigation';

const props = defineProps({
  activeKey: {
    type: String,
    default: 'panel',
  },
  collapsed: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '云管理系统',
  },
  logo: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['select', 'toggle-sidebar']);

const sidebarEntries = mmuiSidebarEntries;

function resolveEntryTarget(entry) {
  return entry.key;
}

function isEntryActive(entry) {
  return props.activeKey === entry.key;
}

function handleEntryClick(entry) {
  const targetKey = resolveEntryTarget(entry);
  if (targetKey) {
    emit('select', targetKey);
  }
}

function handleExit() {
  window.history.back();
}
</script>
