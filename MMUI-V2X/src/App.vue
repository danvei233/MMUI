<template>
  <a-config-provider :theme="themeConfig">
    <div class="mmui-theme-root" :data-mmui-theme="mode" :style="cssVars">
      <HomePage />
      <div
        class="mmui-floating-scrollbar"
        :class="{ 'is-visible': scrollState.scrollable && scrollState.active }"
        aria-hidden="true"
      >
        <i :style="scrollThumbStyle"></i>
      </div>
    </div>
  </a-config-provider>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import HomePage from '@/pages/HomePage.vue';
import { useThemeMode } from '@/composables/useThemeMode';
import { mmuiThemeModes } from '@/theme/antdTheme';
import { getMmuiCssVars } from '@/theme/themeCssVars';

const themeMode = useThemeMode();
const scrollState = ref({
  scrollable: false,
  active: false,
  top: 8,
  height: 42,
});
let scrollFrame = 0;
let scrollIdleTimer = 0;

onMounted(() => {
  themeMode.initThemeMode();
  syncFloatingScrollbar();
  window.addEventListener('scroll', requestFloatingScrollbarSync, { passive: true });
  window.addEventListener('resize', requestFloatingScrollbarSync);
});

onBeforeUnmount(() => {
  window.removeEventListener('scroll', requestFloatingScrollbarSync);
  window.removeEventListener('resize', requestFloatingScrollbarSync);

  if (scrollFrame) {
    window.cancelAnimationFrame(scrollFrame);
  }

  if (scrollIdleTimer) {
    window.clearTimeout(scrollIdleTimer);
  }
});

const mode = computed(() => themeMode.mode.value);
const themeEntry = computed(() => mmuiThemeModes[mode.value] || mmuiThemeModes.light);
const themeConfig = computed(() => themeEntry.value.antdTheme);
const cssVars = computed(() => getMmuiCssVars(mode.value));
const scrollThumbStyle = computed(() => ({
  height: `${scrollState.value.height}px`,
  transform: `translate3d(0, ${scrollState.value.top}px, 0)`,
}));

function requestFloatingScrollbarSync() {
  if (scrollState.value.scrollable) {
    scrollState.value = {
      ...scrollState.value,
      active: true,
    };

    window.clearTimeout(scrollIdleTimer);
    scrollIdleTimer = window.setTimeout(() => {
      scrollState.value = {
        ...scrollState.value,
        active: false,
      };
      scrollIdleTimer = 0;
    }, 760);
  }

  if (scrollFrame) {
    return;
  }

  scrollFrame = window.requestAnimationFrame(syncFloatingScrollbar);
}

function syncFloatingScrollbar() {
  scrollFrame = 0;

  const documentElement = document.documentElement;
  const scrollHeight = Math.max(documentElement.scrollHeight, document.body.scrollHeight);
  const viewportHeight = window.innerHeight;
  const maxScroll = Math.max(0, scrollHeight - viewportHeight);
  const railInset = 10;
  const railHeight = Math.max(0, viewportHeight - railInset * 2);
  const thumbHeight = maxScroll > 0
    ? Math.max(34, Math.min(railHeight, (viewportHeight / scrollHeight) * railHeight))
    : railHeight;
  const top = maxScroll > 0
    ? railInset + (window.scrollY / maxScroll) * Math.max(0, railHeight - thumbHeight)
    : railInset;

  scrollState.value = {
    scrollable: maxScroll > 2,
    active: scrollState.value.active && maxScroll > 2,
    top,
    height: thumbHeight,
  };
}
</script>
