<template>
  <img
    v-if="src"
    class="mmui-brand-logo-image"
    :src="src"
    alt=""
    aria-hidden="true"
  />
  <svg
    v-else
    viewBox="0 0 24 24"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
  >
    <defs>
      <linearGradient id="mmui-brand-top" x1="5" y1="4" x2="19" y2="12" gradientUnits="userSpaceOnUse">
        <stop stop-color="#B8C8FF" />
        <stop offset="0.52" stop-color="#7CA1FF" />
        <stop offset="1" stop-color="#4E6BFF" />
      </linearGradient>
      <linearGradient id="mmui-brand-left" x1="4" y1="8" x2="11" y2="19" gradientUnits="userSpaceOnUse">
        <stop stop-color="#87A8FF" />
        <stop offset="1" stop-color="#4E6BFF" />
      </linearGradient>
      <linearGradient id="mmui-brand-right" x1="13" y1="8" x2="20" y2="19" gradientUnits="userSpaceOnUse">
        <stop stop-color="#5E7BFF" />
        <stop offset="1" stop-color="#6E4BFF" />
      </linearGradient>
      <filter id="mmui-brand-glow" x="-20%" y="-20%" width="140%" height="140%">
        <feDropShadow dx="0" dy="0" stdDeviation="1.4" flood-color="#5C74FF" flood-opacity="0.35" />
      </filter>
    </defs>

    <g filter="url(#mmui-brand-glow)">
      <path
        d="M12 3.4 20 7.55 12 11.7 4 7.55 12 3.4Z"
        fill="url(#mmui-brand-top)"
      />
      <path
        d="M4.9 8.65 11.1 11.9V20L4.9 16.8V8.65Z"
        fill="url(#mmui-brand-left)"
      />
      <path
        d="M19.1 8.65 12.9 11.9V20l6.2-3.2V8.65Z"
        fill="url(#mmui-brand-right)"
      />
      <path
        d="M12 6.1 16.4 8.35 12 10.65 7.6 8.35 12 6.1Z"
        fill="rgba(255,255,255,0.52)"
      />
    </g>
  </svg>
</template>

<script setup>
defineProps({
  src: {
    type: String,
    default: '',
  },
});
</script>

<style scoped>
.mmui-brand-logo-image {
  display: block;
  width: min(100%, 32px);
  height: min(100%, 32px);
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  object-position: center;
  flex: 0 0 auto;
  overflow: hidden;
}
</style>
