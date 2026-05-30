<template>
  <a-card class="home-card portable-actions" :bordered="false">
    <div class="portable-actions__header">
      <div class="portable-actions__title">便携操作</div>
      <div class="portable-actions__subtitle">先保留首页入口，功能后续再接真实接口。</div>
    </div>
    <div class="portable-actions__list">
      <div v-for="action in items" :key="action.key" class="portable-actions__item">
        <a-button class="portable-actions__button" type="primary">
          <component :is="getIcon(action.icon)" />
          <span>{{ action.label }}</span>
        </a-button>
      </div>
    </div>
  </a-card>
</template>

<script setup>
import {
  ArrowRightOutlined,
  DesktopOutlined,
  ShareAltOutlined,
  ToolOutlined,
} from '@ant-design/icons-vue';

defineProps({
  items: {
    type: Array,
    default: () => [],
  },
});

const iconMap = {
  ArrowRightOutlined,
  DesktopOutlined,
  ShareAltOutlined,
  ToolOutlined,
};

function getIcon(iconName) {
  return iconMap[iconName] || DesktopOutlined;
}
</script>

<style scoped>
.portable-actions {
  margin: 0 8px;
  border-radius: 6px !important;
}

.portable-actions :deep(.ant-card-body) {
  padding: 0 !important;
}

.portable-actions__title {
  font-size: var(--mmui-font-title);
  font-weight: 400;
  color: var(--mmui-card-title);
  padding: 14px 16px 0;
}

.portable-actions__subtitle {
  display: none;
}

.portable-actions__list {
  display: flex;
  flex-direction: column;
  padding: 8px;
}

.portable-actions__item {
  width: 100%;
  padding: 4px 0;
}

.portable-actions__button {
  width: 100%;
  height: 32px;
  padding: 0 16px !important;
  border: 1px solid var(--mmui-card-action-border) !important;
  border-radius: 25px !important;
  background: var(--mmui-card-action-bg) !important;
  color: var(--mmui-card-action-text) !important;
  box-shadow: none !important;
  font-size: var(--mmui-font-body) !important;
  font-weight: 400;
}

.portable-actions__button:hover {
  border-color: var(--mmui-card-action-border) !important;
  background: var(--mmui-card-action-bg) !important;
}

.portable-actions__button :deep(.anticon) {
  font-size: 15px;
}

@media (min-width: 600px) {
  .portable-actions__list {
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    padding: 8px;
  }

  .portable-actions__item {
    width: auto;
    padding: 8px 6px 0;
  }

  .portable-actions__button {
    width: auto;
    min-width: 96px;
  }
}

@media (min-width: 1600px) {
  .portable-actions {
    min-height: 148px;
    border-radius: 8px !important;
  }

  .portable-actions :deep(.ant-card-body) {
    display: flex;
    flex-direction: column;
    min-height: 148px;
  }

  .portable-actions__title {
    padding: 22px 28px 0;
    font-size: 20px;
  }

  .portable-actions__list {
    margin-top: auto;
    padding: 18px 22px 26px;
  }

  .portable-actions__item {
    padding: 0 8px 0 0;
  }

  .portable-actions__button {
    height: 40px;
    min-width: 120px;
    padding: 0 20px !important;
  }
}
</style>
