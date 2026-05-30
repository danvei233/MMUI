import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

export function useCompactPageMode(breakpoint = 760) {
  const pageRootRef = ref(null);
  const compactMode = ref(false);
  let pageResizeObserver = null;

  function updateCompactMode(width = pageRootRef.value?.clientWidth ?? window.innerWidth) {
    compactMode.value = window.innerWidth <= 768 || width <= breakpoint;
  }

  function handleViewportChange() {
    nextTick(() => {
      updateCompactMode();
    });
  }

  onMounted(() => {
    if (pageRootRef.value && typeof ResizeObserver !== 'undefined') {
      pageResizeObserver = new ResizeObserver(([entry]) => {
        const width = entry?.contentRect?.width || pageRootRef.value?.clientWidth || window.innerWidth;
        updateCompactMode(width);
      });

      pageResizeObserver.observe(pageRootRef.value);
    } else {
      updateCompactMode();
    }

    handleViewportChange();
    window.addEventListener('resize', handleViewportChange);
  });

  onBeforeUnmount(() => {
    if (pageResizeObserver) {
      pageResizeObserver.disconnect();
      pageResizeObserver = null;
    }

    window.removeEventListener('resize', handleViewportChange);
  });

  return {
    pageRootRef,
    compactMode,
    updateCompactMode,
  };
}
