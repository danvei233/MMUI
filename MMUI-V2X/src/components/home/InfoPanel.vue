<template>
  <a-card class="home-card info-panel" :bordered="false">
    <a-row :gutter="[0, 0]">
      <a-col v-for="section in sections" :key="section.key" :xs="24" :sm="8">
        <a-collapse
          class="info-panel__collapse"
          :bordered="false"
          expand-icon-position="end"
          :default-active-key="[section.key]"
        >
          <a-collapse-panel :key="section.key">
            <template #header>
              <div class="info-panel__header">
                <component :is="section.icon" class="info-panel__header-icon" />
                <span>{{ section.title }}</span>
              </div>
            </template>
            <div class="info-panel__list">
              <div v-for="entry in section.items" :key="entry.label" class="info-panel__row">
                <span class="info-panel__label">{{ entry.label }}</span>
                <span class="info-panel__value">{{ entry.value }}</span>
              </div>
            </div>
          </a-collapse-panel>
        </a-collapse>
      </a-col>
    </a-row>
  </a-card>
</template>

<script setup>
import { computed } from 'vue';
import { BarsOutlined } from '@ant-design/icons-vue';

const props = defineProps({
  host: {
    type: Object,
    default: () => ({}),
  },
});

const sections = computed(() => [
  {
    key: 'system',
    title: '系统',
    icon: BarsOutlined,
    items: [
      { label: '主机名称', value: props.host.name },
      { label: '所在区域', value: props.host.areaName },
      { label: '所在线路', value: props.host.lineName },
      { label: '操作系统', value: props.host.osName },
      { label: '虚拟方案', value: props.host.virtualType },
    ],
  },
  {
    key: 'config',
    title: '配置',
    icon: BarsOutlined,
    items: [
      { label: '核心数量', value: `${props.host.cpu} 核心` },
      { label: '实际内存', value: `${props.host.memory} GB` },
      { label: '实际带宽', value: `${props.host.bandwidth} MB` },
      { label: '数据硬盘', value: `${props.host.disk} GB` },
      { label: '远程地址', value: props.host.remoteAddress },
    ],
  },
  {
    key: 'meta',
    title: '信息',
    icon: BarsOutlined,
    items: [
      { label: '开通日期', value: props.host.buyDate },
      { label: '到期日期', value: props.host.expireDate },
      { label: '调试信息', value: props.host.version },
      { label: '面板密码', value: props.host.panelPassword },
      { label: '系统密码', value: props.host.systemPassword },
    ],
  },
]);
</script>

<style scoped>
.info-panel {
  margin: 0;
  border-radius: 6px !important;
  height: 100%;
  width: 100%;
}

.info-panel :deep(.ant-card-body) {
  padding: 0 !important;
  height: 100%;
}

.info-panel :deep(.ant-row) {
  margin: 0 !important;
  height: 100%;
}

.info-panel :deep(.ant-col) {
  position: relative;
  display: flex;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
  min-height: 100%;
}

@media (min-width: 768px) {
  .info-panel :deep(.ant-col:not(:last-child))::after {
    content: '';
    position: absolute;
    top: 7%;
    right: 0;
    width: 1px;
    height: 87%;
    background-color: var(--mmui-divider);
  }
}

.info-panel__collapse {
  width: 100%;
  background: transparent;
}

.info-panel__collapse :deep(.ant-collapse-item) {
  width: 100%;
  border-bottom: 0;
}

.info-panel__collapse :deep(.ant-collapse-item-active) {
  height: 100%;
}

.info-panel__collapse :deep(.ant-collapse-content) {
  height: calc(100% - 58px);
}

.info-panel__header {
  display: inline-flex;
  align-items: center;
  width: 100%;
  color: var(--mmui-card-title);
}

.info-panel__header-icon {
  margin-right: 8px;
  color: var(--mmui-accent-green);
  font-size: 24px;
}

.info-panel__collapse :deep(.ant-collapse-header) {
  min-height: 58px;
  padding: 12px 12px 10px !important;
  font-size: var(--mmui-font-title);
  font-weight: var(--mmui-font-weight-regular);
  line-height: var(--mmui-line-height-title);
  color: var(--mmui-card-title);
}

.info-panel__collapse :deep(.ant-collapse-expand-icon) {
  color: var(--mmui-text-muted);
}

@media (min-width: 768px) {
  .info-panel__collapse :deep(.ant-collapse-expand-icon) {
    display: none;
  }
}

.info-panel__collapse :deep(.ant-collapse-content-box) {
  display: flex;
  height: 100%;
  padding: 0 12px 12px !important;
}

.info-panel__list {
  position: relative;
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  justify-content: flex-start;
  width: 100%;
  height: 100%;
}

.info-panel__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  min-height: 36px;
  padding: 0;
  font-size: var(--mmui-font-body);
  line-height: var(--mmui-line-height-body);
}

.info-panel__label {
  flex-shrink: 0;
  margin-right: 8px;
  color: var(--mmui-text-soft);
}

.info-panel__value {
  margin-left: auto;
  color: var(--mmui-text);
  text-align: right;
  word-break: break-word;
}

@media (min-width: 1600px) {
  .info-panel__header-icon {
    margin-right: 10px;
    font-size: 26px;
  }

  .info-panel__collapse :deep(.ant-collapse-header) {
    min-height: 72px;
    padding: 18px 20px 14px !important;
    font-size: var(--mmui-font-size-section);
  }

  .info-panel__collapse :deep(.ant-collapse-content-box) {
    padding: 0 20px 18px !important;
  }

  .info-panel__row {
    min-height: 52px;
    font-size: var(--mmui-font-size-subheadline);
  }

  .info-panel__label {
    margin-right: 12px;
  }
}

@media (max-width: 767px) {
  .info-panel__collapse :deep(.ant-collapse-header) {
    min-height: 50px;
  }

  .info-panel__collapse :deep(.ant-collapse-content-box) {
    padding-bottom: 10px !important;
  }

  .info-panel__row {
    min-height: 38px;
  }
}

@media (max-width: 599px) {
  .info-panel {
    height: auto;
  }

  .info-panel :deep(.ant-card-body),
  .info-panel :deep(.ant-row),
  .info-panel :deep(.ant-col),
  .info-panel__collapse :deep(.ant-collapse-item-active),
  .info-panel__collapse :deep(.ant-collapse-content),
  .info-panel__collapse :deep(.ant-collapse-content-box),
  .info-panel__list {
    height: auto;
    min-height: 0;
  }
}
</style>
