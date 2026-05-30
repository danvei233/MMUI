<template>
  <a-layout-header
    class="mmui-header"
    :class="{
      'is-desktop': isDesktop,
      'is-sidebar-collapsed': isDesktop && sidebarCollapsed,
    }"
    :style="headerStyle"
  >
    <div class="mmui-header__left">
      <a-button
        class="header-icon-button mmui-header__menu-btn"
        type="text"
        aria-label="menu"
        @click="$emit('toggle-sidebar')"
      >
        <MenuUnfoldOutlined />
      </a-button>
      <a class="mmui-header__brand" href="javascript:;">
        <BrandLogo class="mmui-header__brand-icon" :src="logo" />
        <span class="mmui-header__brand-text">{{ title || '云管理系统' }}</span>
      </a>
    </div>
    <div class="mmui-header__right">
      <a-button class="header-icon-button" type="text" aria-label="theme" @click="toggleDarkMode">
        <component :is="themeIcon" />
      </a-button>
    </div>
  </a-layout-header>
</template>

<script setup>
import {
  BulbOutlined,
  MenuUnfoldOutlined,
  BulbFilled,
} from '@ant-design/icons-vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import BrandLogo from '@/components/layout/BrandLogo.vue';
import { useThemeMode } from '@/composables/useThemeMode';

defineEmits(['toggle-sidebar']);

defineProps({
  title: {
    type: String,
    default: '云管理系统',
  },
  isDesktop: {
    type: Boolean,
    default: false,
  },
  sidebarCollapsed: {
    type: Boolean,
    default: false,
  },
  logo: {
    type: String,
    default: '',
  },
});

const themeMode = useThemeMode();
const scrollProgress = ref(0);
let scrollRafId = 0;

const themeIcon = computed(() => (themeMode.isDark.value ? BulbFilled : BulbOutlined));
const headerStyle = computed(() => ({
  '--mmui-header-scroll': scrollProgress.value.toFixed(3),
}));
const toggleDarkMode = (event) => themeMode.toggleDarkMode(event);

function syncHeaderScroll() {
  scrollRafId = 0;
  const nextProgress = Math.min(1, Math.max(0, window.scrollY / 96));
  scrollProgress.value = nextProgress;
}

function handleScroll() {
  if (scrollRafId) {
    return;
  }

  scrollRafId = window.requestAnimationFrame(syncHeaderScroll);
}

onMounted(() => {
  syncHeaderScroll();
  window.addEventListener('scroll', handleScroll, { passive: true });
});

onBeforeUnmount(() => {
  window.removeEventListener('scroll', handleScroll);
  if (scrollRafId) {
    window.cancelAnimationFrame(scrollRafId);
  }
});
</script>
