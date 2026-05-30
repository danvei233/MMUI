import { computed, nextTick, readonly, ref } from 'vue';

const DARK_MODE_KEY = 'darkMode';
const GLASS_MODE_KEY = 'glassMode';
const TRANSITION_CLASS = 'mmui-theme-transitioning';
const state = ref('light');
let themeTransitionRunning = false;

function systemPrefersDark() {
  return typeof window !== 'undefined'
    && typeof window.matchMedia === 'function'
    && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function resolveInitialMode() {
  if (typeof window === 'undefined') {
    return 'light';
  }

  const stored = window.localStorage.getItem(DARK_MODE_KEY);
  return stored === 'enabled' || systemPrefersDark() ? 'dark' : 'light';
}

function applyMode(mode) {
  if (typeof document === 'undefined') {
    return;
  }

  state.value = mode;
  document.body.dataset.mmuiTheme = mode;
  document.documentElement.dataset.mmuiTheme = mode;
}

function isReducedMotion() {
  return typeof window !== 'undefined'
    && typeof window.matchMedia === 'function'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function getTransitionOrigin(event) {
  if (typeof window === 'undefined') {
    return { x: 0, y: 0 };
  }

  const fallback = { x: window.innerWidth - 24, y: 28 };

  if (!event) {
    return fallback;
  }

  if (Number.isFinite(event.clientX) && Number.isFinite(event.clientY) && (event.clientX || event.clientY)) {
    return { x: event.clientX, y: event.clientY };
  }

  const target = event.currentTarget;
  if (target && typeof target.getBoundingClientRect === 'function') {
    const rect = target.getBoundingClientRect();
    return {
      x: rect.left + rect.width / 2,
      y: rect.top + rect.height / 2,
    };
  }

  return fallback;
}

function persistMode(mode) {
  if (typeof window === 'undefined') {
    return;
  }

  window.localStorage.setItem(DARK_MODE_KEY, mode === 'dark' ? 'enabled' : 'disabled');
  window.localStorage.setItem(GLASS_MODE_KEY, 'disabled');
}

function beginThemeTransition() {
  if (typeof document !== 'undefined') {
    document.documentElement.classList.add(TRANSITION_CLASS);
  }
}

function endThemeTransition() {
  if (typeof document === 'undefined') {
    return;
  }

  window.requestAnimationFrame(() => {
    window.requestAnimationFrame(() => {
      document.documentElement.classList.remove(TRANSITION_CLASS);
    });
  });
}

function initThemeMode() {
  applyMode(resolveInitialMode());
}

function toggleDarkMode(event) {
  const next = state.value === 'dark' ? 'light' : 'dark';
  persistMode(next);

  if (
    typeof document === 'undefined'
    || typeof document.startViewTransition !== 'function'
    || isReducedMotion()
    || themeTransitionRunning
  ) {
    beginThemeTransition();
    applyMode(next);
    endThemeTransition();
    return;
  }

  themeTransitionRunning = true;
  beginThemeTransition();
  const { x, y } = getTransitionOrigin(event);
  const endRadius = Math.hypot(
    Math.max(x, window.innerWidth - x),
    Math.max(y, window.innerHeight - y),
  );
  const clipPath = [
    `circle(0px at ${x}px ${y}px)`,
    `circle(${endRadius}px at ${x}px ${y}px)`,
  ];

  const transition = document.startViewTransition(async () => {
    applyMode(next);
    await nextTick();
  });

  transition.ready.then(() => {
    const revealAnimation = document.documentElement.animate(
      {
        clipPath,
      },
      {
        duration: 560,
        easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
        fill: 'both',
        pseudoElement: '::view-transition-new(root)',
      },
    );

    revealAnimation.finished
      .then(() => revealAnimation.cancel())
      .catch(() => {});
  }).catch(() => {});

  transition.finished
    .catch(() => {})
    .finally(() => {
      themeTransitionRunning = false;
      endThemeTransition();
    });
}

export function useThemeMode() {
  return {
    mode: readonly(state),
    isDark: computed(() => state.value === 'dark'),
    initThemeMode,
    toggleDarkMode,
  };
}
