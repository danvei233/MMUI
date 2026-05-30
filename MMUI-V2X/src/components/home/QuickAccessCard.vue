<template>
  <a-card class="home-card quick-access-card" :bordered="false">
    <div class="quick-access-card__main">
      <div class="quick-access-card__content">
        <div class="quick-access-card__title">{{ title }}</div>
        <div class="quick-access-card__subtitle">{{ subtitle }}</div>
        <div class="quick-access-card__actions">
          <a-button class="quick-access-card__plus" type="primary" shape="circle">
            <PlusOutlined />
          </a-button>
          <a-button class="quick-access-card__action" type="text">{{ action }}</a-button>
        </div>
      </div>
      <component :is="iconComponent" :style="iconStyle" class="quick-access-card__icon" aria-hidden="true" />
    </div>
  </a-card>
</template>

<script setup>
import {
  ApiOutlined,
  CameraOutlined,
  CopyOutlined,
  PlusOutlined,
  StopOutlined,
} from '@ant-design/icons-vue';
import { computed } from 'vue';

const props = defineProps({
  title: String,
  subtitle: String,
  action: String,
  icon: String,
});

const iconMap = {
  ApiOutlined,
  CameraOutlined,
  CopyOutlined,
  StopOutlined,
};

const iconAliases = {
  BranchesOutlined: 'ApiOutlined',
  SafetyCertificateOutlined: 'StopOutlined',
};

const resolvedIcon = computed(() => iconAliases[props.icon] || props.icon);
const iconComponent = computed(() => iconMap[resolvedIcon.value] || ApiOutlined);

const iconStyleMap = {
  ApiOutlined: {
    right: '-66px',
    bottom: '-58px',
    fontSize: '128px',
  },
  CameraOutlined: {
    right: '-66px',
    bottom: '-58px',
    fontSize: '128px',
  },
  CopyOutlined: {
    right: '-66px',
    bottom: '-58px',
    fontSize: '128px',
  },
  StopOutlined: {
    right: '-66px',
    bottom: '-58px',
    fontSize: '128px',
  },
};

const iconStyle = computed(() => iconStyleMap[resolvedIcon.value] || iconStyleMap.ApiOutlined);
</script>

<style scoped>
.quick-access-card {
  margin: 0;
  max-height: 250px;
  border-radius: 6px !important;
  overflow: hidden;
}

.quick-access-card :deep(.ant-card-body) {
  min-height: 123px;
  padding: 8px !important;
}

.quick-access-card__main {
  position: relative;
  display: flex;
  min-height: 123px;
  max-height: 250px;
  padding: 0;
  overflow: hidden;
}

.quick-access-card__content {
  display: flex;
  position: relative;
  z-index: 2;
  flex: 1 1 100%;
  flex-direction: column;
  margin: 11px 11px 10px;
  padding: 0;
}

.quick-access-card__title {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-title);
  font-weight: var(--mmui-font-weight-regular);
  line-height: var(--mmui-line-height-title);
  user-select: none;
  letter-spacing: 0;
}

.quick-access-card__subtitle {
  margin: 10px 0;
  color: var(--mmui-card-subtitle);
  font-size: var(--mmui-font-subtitle);
  line-height: var(--mmui-line-height-footnote);
  min-height: 20px;
}

.quick-access-card__actions {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: auto;
  padding-top: 0;
}

.quick-access-card__plus {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  min-width: 32px;
  height: 32px;
  margin-top: 4px;
  border: 0 !important;
  border-radius: 6px !important;
  background: var(--mmui-accent-blue) !important;
  box-shadow: none !important;
}

.quick-access-card__plus :deep(.anticon) {
  font-size: 22px;
  color: #fff;
}

.quick-access-card__action {
  min-height: 32px;
  margin-top: 4px;
  padding: 0 8px !important;
  color: var(--mmui-card-title) !important;
  font-size: var(--mmui-font-body);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-body);
  border-radius: 25px !important;
  background: transparent !important;
}

.quick-access-card__action:hover {
  color: var(--mmui-card-title) !important;
  background: transparent !important;
}

.quick-access-card__icon {
  position: absolute;
  z-index: 1;
  color: var(--mmui-accent-blue-soft);
  line-height: 1;
  pointer-events: none;
}

.quick-access-card__icon :deep(svg) {
  display: block;
  width: 1em;
  height: 1em;
}

@media (min-width: 1600px) {
  .quick-access-card {
    min-height: 164px;
    max-height: none;
    border-radius: 8px !important;
  }

  .quick-access-card :deep(.ant-card-body) {
    min-height: 164px;
    padding: 12px !important;
  }

  .quick-access-card__main {
    min-height: 140px;
  }

  .quick-access-card__content {
    margin: 16px 18px 14px;
  }

  .quick-access-card__title {
    font-size: var(--mmui-font-size-section);
  }

  .quick-access-card__subtitle {
    margin: 14px 0;
    font-size: var(--mmui-font-size-title);
  }

  .quick-access-card__plus {
    width: 36px;
    min-width: 36px;
    height: 36px;
  }

  .quick-access-card__action {
    min-height: 34px;
    padding: 0 10px !important;
    font-size: var(--mmui-font-size-subheadline);
  }
}

</style>
