<template>
  <section ref="pageRootRef" class="network-page">
    <a-row :gutter="[0, 0]">
      <a-col :span="24">
        <div class="network-page__header-shell">
          <a-flex class="network-page__header" align="center" justify="space-between">
            <div>
              <div class="network-page__title">网络信息</div>
            </div>
            <a-flex class="network-page__summary" align="center" :gap="12">
              <span class="network-page__summary-label">网络表组</span>
              <span class="network-page__summary-value">{{ normalizedTables.length }}</span>
              <a-tooltip title="展示云主机分配的公网 / NAT IP、子网掩码、网关、线路和流量信息，仅用于查看。">
                <a-button class="network-page__summary-help" type="text" shape="circle" aria-label="网络信息说明">
                  <InfoCircleOutlined />
                </a-button>
              </a-tooltip>
            </a-flex>
          </a-flex>
        </div>
      </a-col>

      <a-col :span="24">
        <div class="network-page__focus-shell" :class="{ 'is-compact': compactMode }">
          <div class="network-page__overview-grid">
            <section class="network-page__overview-item network-page__overview-item--ip">
              <span class="network-page__overview-icon">
                <img :src="networkIcon" alt="" aria-hidden="true" />
              </span>
              <div class="network-page__overview-copy">
                <div class="network-page__overview-label-row">
                  <span>外部 IP</span>
                  <em>{{ focusSummary.ipCountText }}</em>
                </div>
                <transition :name="ipTransitionName" mode="out-in">
                  <div :key="focusSummary.ip" class="network-page__ip-stage">
                    <div class="network-page__ip-value-row">
                      <strong>{{ focusSummary.ip }}</strong>
                      <a-tooltip title="复制 IP">
                        <a-button class="network-page__ip-copy" type="text" shape="circle" aria-label="复制外部 IP" @click="copyCurrentIp">
                          <CopyOutlined />
                        </a-button>
                      </a-tooltip>
                    </div>
                    <small>{{ focusSummary.ipHint }}</small>
                  </div>
                </transition>
              </div>
              <div v-if="publicIpItems.length > 1" class="network-page__ip-controls">
                <a-tooltip title="上一个 IP">
                  <a-button class="network-page__ip-cycle-btn" type="text" aria-label="上一个外部 IP" @click="showPreviousIp">
                    <CaretUpOutlined />
                  </a-button>
                </a-tooltip>
                <a-tooltip title="下一个 IP">
                  <a-button class="network-page__ip-cycle-btn" type="text" aria-label="下一个外部 IP" @click="showNextIp">
                    <CaretDownOutlined />
                  </a-button>
                </a-tooltip>
              </div>
            </section>

            <section
              v-for="item in overviewItems"
              :key="item.key"
              class="network-page__overview-item"
              :class="`is-${item.key}`"
            >
              <span class="network-page__overview-icon">
                <img :src="item.icon" alt="" aria-hidden="true" />
              </span>
              <div class="network-page__overview-copy">
                <span>{{ item.label }}</span>
                <strong>{{ item.value }}</strong>
                <small>{{ item.hint }}</small>
                <div v-if="item.progress" class="network-page__traffic-bar" aria-hidden="true">
                  <i :style="{ width: item.progress }"></i>
                </div>
              </div>
            </section>
          </div>
        </div>
      </a-col>

      <a-col :span="24">
        <div class="network-page__tables">
          <template v-if="normalizedTables.length">
            <article v-if="assignedIpTable" class="network-page__table-card network-page__table-card--assigned">
              <div class="network-page__table-head">
                <div class="network-page__table-title">
                  <span class="network-page__table-title-icon">
                    <img :src="menuIcon" alt="" aria-hidden="true" />
                  </span>
                  {{ assignedIpTable.title }}
                </div>
                <div class="network-page__table-head-side">
                  <span class="network-page__table-count">{{ assignedIpTable.rows.length }} 条</span>
                </div>
              </div>

              <template v-if="compactMode">
                <transition-group
                  v-if="assignedIpTable.rows.length"
                  name="network-list"
                  tag="div"
                  class="network-page__mobile-list network-page__mobile-list--ip"
                  appear
                >
                  <article
                    v-for="row in assignedIpTable.rows"
                    :key="row.key"
                    class="network-page__ip-mobile-row"
                  >
                    <div class="network-page__ip-mobile-head">
                      <div class="network-page__ip-mobile-head-main">
                        <span>{{ row.assignedIp.public.label }}</span>
                        <div class="network-page__ip-mobile-value-row">
                          <strong>{{ row.assignedIp.public.value }}</strong>
                          <a-button
                            class="network-page__mobile-copy-btn"
                            type="text"
                            size="small"
                            aria-label="复制公网 IP"
                            @click="copyText(row.assignedIp.public.value, '公网 IP')"
                          >
                            <CopyOutlined />
                          </a-button>
                        </div>
                      </div>
                    </div>

                    <div class="network-page__ip-mobile-meta">
                      <div class="network-page__ip-mobile-meta-item">
                        <span>{{ row.assignedIp.nat.label }}</span>
                        <strong>{{ row.assignedIp.nat.value }}</strong>
                      </div>
                      <div class="network-page__ip-mobile-meta-item">
                        <span>{{ row.assignedIp.mask.label }}</span>
                        <strong>{{ row.assignedIp.mask.value }}</strong>
                      </div>
                      <div class="network-page__ip-mobile-meta-item">
                        <span>{{ row.assignedIp.gateway.label }}</span>
                        <strong>{{ row.assignedIp.gateway.value }}</strong>
                      </div>
                    </div>
                  </article>
                </transition-group>

                <a-empty v-else class="network-page__empty" description="暂无网络信息" />
              </template>

              <div v-else-if="assignedIpTable.rows.length" class="network-page__table-wrap">
                <table class="network-page__table network-page__table--assigned">
                  <thead>
                    <tr>
                      <th>外部 IP</th>
                      <th>NAT IP</th>
                      <th>子网掩码</th>
                      <th>网关</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in assignedIpTable.rows" :key="row.key">
                      <td>{{ row.assignedIp.public.value }}</td>
                      <td>{{ row.assignedIp.nat.value }}</td>
                      <td>{{ row.assignedIp.mask.value }}</td>
                      <td>{{ row.assignedIp.gateway.value }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <a-empty v-else class="network-page__empty" description="暂无网络信息" />
            </article>

            <div class="network-page__detail-grid">
              <article class="network-page__table-card network-page__table-card--line">
                <div class="network-page__table-head">
                  <div class="network-page__table-title">
                    <span class="network-page__table-title-icon is-antd" aria-hidden="true">
                      <component :is="lineInfoTitleIcon" />
                    </span>
                    线路信息
                  </div>
                </div>
                <div class="network-page__info-grid">
                  <div v-for="item in lineInfoCards" :key="item.key" class="network-page__info-tile">
                    <span class="network-page__info-icon is-antd" aria-hidden="true">
                      <component :is="item.icon" />
                    </span>
                    <div>
                      <span>{{ item.label }}</span>
                      <strong>{{ item.value }}</strong>
                    </div>
                  </div>
                </div>
              </article>

              <article class="network-page__table-card">
                <div class="network-page__table-head">
                  <div class="network-page__table-title">
                    <span class="network-page__table-title-icon is-antd" aria-hidden="true">
                      <component :is="trafficUsageTitleIcon" />
                    </span>
                    流量使用情况
                  </div>
                  <div class="network-page__table-head-side">
                    <span class="network-page__table-count">{{ trafficUsageRows.length }} 条</span>
                  </div>
                </div>
                <div class="network-page__traffic-list">
                  <div v-for="item in trafficUsageRows" :key="item.key" class="network-page__traffic-item">
                    <span class="network-page__info-icon is-antd" aria-hidden="true">
                      <component :is="item.icon" />
                    </span>
                    <div class="network-page__traffic-copy">
                      <span>{{ item.label }}</span>
                      <strong v-if="item.value">{{ item.value }}</strong>
                      <small v-else>{{ item.hint }}</small>
                    </div>
                    <div class="network-page__traffic-usage">
                      <span>月使用</span>
                      <strong>{{ item.monthly }}</strong>
                    </div>
                  </div>
                </div>
              </article>
            </div>
          </template>

          <a-empty v-else class="network-page__empty" description="暂无网络信息" />
        </div>
      </a-col>
    </a-row>
  </section>
</template>

<script setup>
import {
  ApartmentOutlined,
  ArrowDownOutlined,
  ArrowUpOutlined,
  BarChartOutlined,
  CaretDownOutlined,
  CaretUpOutlined,
  CopyOutlined,
  DashboardOutlined,
  GlobalOutlined,
  InfoCircleOutlined,
  ThunderboltOutlined,
} from '@ant-design/icons-vue';
import { message } from 'ant-design-vue';
import { computed, ref, watch } from 'vue';
import { useCompactPageMode } from '@/components/pages/useCompactPageMode';
import { useDashboardStore } from '@/stores/dashboard';
import { pinia } from '@/stores/pinia';
import diskIcon from '@/assets/iconly-glass/Disk.svg';
import infoIcon from '@/assets/iconly-glass/Info.svg';
import menuIcon from '@/assets/iconly-glass/Menu.svg';
import networkIcon from '@/assets/iconly-glass/network.svg';
import settingIcon from '@/assets/iconly-glass/Setting.svg';

const props = defineProps({
  page: {
    type: Object,
    default: () => ({}),
  },
});

const { pageRootRef, compactMode } = useCompactPageMode();
const store = useDashboardStore(pinia);
const activeIpIndex = ref(0);
const ipTransitionName = ref('network-ip-next');
const expandedTableKeys = ref([]);

const columnLabelMap = {
  primary: '主信息',
  secondary: '附加信息',
  mask: '子网掩码',
  gateway: '网关',
};

const normalizedTables = computed(() => {
  const tables = Array.isArray(props.page?.tables) ? props.page.tables : [];

  return tables.map((table, tableIndex) => {
    const kind = resolveNetworkTableKind(table.title);
    const columns = normalizeColumns(table);
    const rows = (Array.isArray(table.rows) ? table.rows : []).map((row, rowIndex) => ({
      key: `${tableIndex}-${rowIndex}`,
      cells: columns.map((column) => ({
        key: column,
        ...splitCell(row?.[column], columnLabelMap[column] || column),
      })),
      assignedIp: null,
    }));

    if (kind === 'assigned-ip') {
      rows.forEach((row) => {
        row.assignedIp = summarizeAssignedIpRow(row.cells);
      });
    }

    return {
      key: `network-table-${tableIndex}`,
      kind,
      title: table.title || `网络信息 ${tableIndex + 1}`,
      rows,
    };
  });
});

const flatCells = computed(() => normalizedTables.value.flatMap((table) => (
  table.rows.flatMap((row) => row.cells)
)));

const publicIpItems = computed(() => {
  const items = [];
  const seen = new Set();

  normalizedTables.value.forEach((table) => {
    table.rows.forEach((row) => {
      const publicCell = row.cells.find(isExternalIpCell) || row.cells.find(isGenericIpCell);

      if (!publicCell || !hasValue(publicCell.value)) {
        return;
      }

      const value = normalizeValue(publicCell.value);
      if (seen.has(value)) {
        return;
      }

      seen.add(value);
      items.push({
        value,
        nat: normalizeValue(row.cells.find((cell) => matchesLabel(cell.label, ['NAT IP', '私网 IP', '内网 IP']))?.value),
        gateway: normalizeValue(row.cells.find((cell) => matchesLabel(cell.label, ['网关']))?.value),
      });
    });
  });

  return items.length ? items : [{ value: '-', nat: '', gateway: '' }];
});

const activePublicIp = computed(() => (
  publicIpItems.value[activeIpIndex.value] || publicIpItems.value[0] || { value: '-', nat: '', gateway: '' }
));

const networkCore = computed(() => {
  const publicIp = activePublicIp.value.value;
  const natIp = findCellValue(['NAT IP', '私网 IP', '内网 IP']);
  const gateway = findCellValue(['网关']);
  const region = findCellValue(['区域', '地区']) || readHostRegion();
  const line = findCellValue(['线路']) || readHostLine();
  const bandwidth = readHostBandwidth() || findCellValue(['带宽']);
  const downstreamBandwidth = readHostDownstreamBandwidth();
  const upstream = findRelatedRowValue(['上行流量']);
  const downstream = findRelatedRowValue(['下行流量']);
  const trafficLimit = findCellValue(['流量上限', '月流量上限', '月上限', '总流量上限']) || readTrafficLimit();
  const hasTrafficUsageData = hasValue(upstream) || hasValue(downstream);
  const trafficUsedMb = [upstream, downstream].reduce((sum, item) => sum + (parseDataSize(item) || 0), 0);
  const trafficLimitMb = parseTrafficLimitSize(trafficLimit);

  return {
    publicIp: displayValue(publicIp),
    natIp: displayValue(natIp),
    gateway: displayValue(gateway),
    region: displayValue(region),
    line: displayValue(line),
    bandwidth: displayValue(bandwidth),
    peakBandwidth: displayValue(bandwidth),
    downstreamBandwidth: displayValue(downstreamBandwidth),
    upstream: hasValue(upstream) ? displayValue(upstream) : '未知',
    downstream: hasValue(downstream) ? displayValue(downstream) : '未知',
    trafficUsed: hasTrafficUsageData ? formatDataSize(trafficUsedMb) : '未知',
    trafficLimit: displayTrafficLimitValue(trafficLimit),
    trafficUsageKnown: hasTrafficUsageData,
    trafficUsedMb,
    trafficLimitMb,
  };
});

const focusSummary = computed(() => {
  const trafficPercent = resolveTrafficPercent();
  const ipCount = publicIpItems.value.filter((item) => hasValue(item.value)).length;

  return {
    ip: networkCore.value.publicIp,
    ipHint: buildIpHint(),
    ipCountText: ipCount > 1 ? `${activeIpIndex.value + 1} / ${ipCount}` : `${ipCount || 0} 个`,
    region: networkCore.value.region,
    regionHint: networkCore.value.line !== '-' ? `线路 ${networkCore.value.line}` : '当前区域',
    bandwidth: networkCore.value.bandwidth,
    bandwidthHint: networkCore.value.downstreamBandwidth !== '-'
      ? `上行峰值 ${networkCore.value.peakBandwidth} · 下行 ${networkCore.value.downstreamBandwidth}`
      : `上行峰值 ${networkCore.value.peakBandwidth}`,
    trafficUsed: networkCore.value.trafficUsed,
    trafficLimit: networkCore.value.trafficLimit,
    trafficPercentText: resolveTrafficPercentText(trafficPercent),
    trafficPercentWidth: trafficPercent === null ? '0%' : `${Math.min(trafficPercent, 100)}%`,
  };
});

const lineInfoTitleIcon = ApartmentOutlined;
const trafficUsageTitleIcon = BarChartOutlined;
const assignedIpTable = computed(() => (
  normalizedTables.value.find((table) => table.kind === 'assigned-ip') || null
));
const overviewItems = computed(() => ([
  {
    key: 'region',
    label: '地区信息',
    value: focusSummary.value.region,
    hint: focusSummary.value.regionHint,
    icon: settingIcon,
  },
  {
    key: 'bandwidth',
    label: '带宽',
    value: focusSummary.value.bandwidth,
    hint: focusSummary.value.bandwidthHint,
    icon: infoIcon,
  },
  {
    key: 'traffic',
    label: '流量使用率',
    value: focusSummary.value.trafficPercentText,
    hint: `已用 ${focusSummary.value.trafficUsed} / 上限 ${focusSummary.value.trafficLimit}`,
    progress: focusSummary.value.trafficPercentText.endsWith('%') ? focusSummary.value.trafficPercentWidth : '',
    icon: diskIcon,
  },
]));
const lineInfoCards = computed(() => {
  const items = [
    {
      key: 'region',
      label: '区域',
      value: networkCore.value.region,
      icon: GlobalOutlined,
    },
    {
      key: 'line',
      label: '线路',
      value: networkCore.value.line,
      icon: ApartmentOutlined,
    },
    {
      key: 'bandwidth',
      label: '上行带宽',
      value: networkCore.value.bandwidth,
      icon: DashboardOutlined,
    },
  ];

  items.push({
    key: 'downstream-bandwidth',
    label: '下行带宽',
    value: networkCore.value.downstreamBandwidth,
    icon: ThunderboltOutlined,
  });

  return items;
});
const trafficUsageRows = computed(() => {
  const table = normalizedTables.value.find((item) => item.title.includes('流量'));

  if (table?.rows?.length) {
    return table.rows.map((row, index) => {
      const primaryCell = row.cells[0] || {};
      const usageCell = row.cells.find((cell) => matchesLabel(cell.label, ['月使用', '使用']));
      const label = primaryCell.label || `流量 ${index + 1}`;
      const value = normalizeValue(primaryCell.value);

      return {
        key: row.key,
        label,
        value,
        hint: label.includes('下') ? '入站累计' : '出站累计',
        monthly: hasValue(usageCell?.value) ? normalizeValue(usageCell.value) : '未知',
        icon: label.includes('下') ? ArrowDownOutlined : ArrowUpOutlined,
      };
    });
  }

  return [
    {
      key: 'upstream',
      label: '上行流量',
      value: '',
      hint: '出站累计',
      monthly: networkCore.value.upstream,
      icon: ArrowUpOutlined,
    },
    {
      key: 'downstream',
      label: '下行流量',
      value: '',
      hint: '入站累计',
      monthly: networkCore.value.downstream,
      icon: ArrowDownOutlined,
    },
  ];
});

watch(
  () => publicIpItems.value.length,
  (length) => {
    if (activeIpIndex.value >= length) {
      activeIpIndex.value = 0;
    }
  },
);

watch(
  () => normalizedTables.value.map((table) => table.key),
  (keys) => {
    const availableKeys = new Set(keys);
    expandedTableKeys.value = expandedTableKeys.value.filter((key) => availableKeys.has(key));
  },
  { immediate: true },
);

function normalizeColumns(table) {
  if (Array.isArray(table?.columns) && table.columns.length) {
    return table.columns;
  }

  const firstRow = Array.isArray(table?.rows) ? table.rows[0] : null;
  return firstRow ? Object.keys(firstRow) : [];
}

function resolveNetworkTableKind(title) {
  const text = String(title || '');
  if (text.includes('分配 IP')) {
    return 'assigned-ip';
  }

  return 'default';
}

function splitCell(input, fallbackLabel) {
  const text = String(input ?? '').trim();
  const match = text.match(/^([^：:]+)[：:]\s*(.*)$/);

  if (match) {
    return {
      label: match[1].trim(),
      value: displayValue(match[2]),
    };
  }

  return {
    label: fallbackLabel,
    value: displayValue(text),
  };
}

function summarizeAssignedIpRow(cells) {
  return {
    public: findNetworkCell(cells, ['外部 IP', '公网 IP'], '外部 IP'),
    nat: findNetworkCell(cells, ['NAT IP', '私网 IP', '内网 IP'], 'NAT IP'),
    mask: findNetworkCell(cells, ['子网掩码'], '子网掩码'),
    gateway: findNetworkCell(cells, ['网关'], '网关'),
  };
}

function findNetworkCell(cells, labels, fallback) {
  const cell = cells.find((item) => matchesLabel(item.label, labels));
  return {
    label: cell?.label || fallback,
    value: cell?.value || '-',
  };
}

function findCellValue(labels) {
  const cell = flatCells.value.find((item) => (
    matchesLabel(item.label, labels) && hasValue(item.value)
  ));

  return normalizeValue(cell?.value);
}

function findRelatedRowValue(labels) {
  for (const table of normalizedTables.value) {
    const row = table.rows.find((entry) => (
      entry.cells.some((cell) => matchesLabel(cell.label, labels))
    ));

    if (!row) {
      continue;
    }

    const usageCell = row.cells.find((cell) => (
      matchesLabel(cell.label, ['月使用', '使用']) && hasValue(cell.value)
    ));

    if (usageCell) {
      return normalizeValue(usageCell.value);
    }
  }

  return '';
}

function isExternalIpCell(cell) {
  return matchesLabel(cell.label, ['外部 IP', '公网 IP']);
}

function isGenericIpCell(cell) {
  const label = String(cell?.label || '').trim().toLowerCase();
  return label === 'ip' && !matchesLabel(label, ['nat']);
}

function buildIpHint() {
  const parts = [];

  if (hasValue(activePublicIp.value.nat)) {
    parts.push(`NAT ${activePublicIp.value.nat}`);
  } else if (hasValue(activePublicIp.value.gateway)) {
    parts.push(`网关 ${activePublicIp.value.gateway}`);
  }

  if (publicIpItems.value.length > 1) {
    parts.push(`${publicIpItems.value.length} 个外部 IP`);
  }

  return parts.join(' · ') || '主访问地址';
}

function showPreviousIp() {
  const count = publicIpItems.value.length;
  if (count <= 1) {
    return;
  }

  ipTransitionName.value = 'network-ip-prev';
  activeIpIndex.value = (activeIpIndex.value + count - 1) % count;
}

function showNextIp() {
  const count = publicIpItems.value.length;
  if (count <= 1) {
    return;
  }

  ipTransitionName.value = 'network-ip-next';
  activeIpIndex.value = (activeIpIndex.value + 1) % count;
}

function isTableExpanded(key) {
  return expandedTableKeys.value.includes(key);
}

function toggleTable(key) {
  if (isTableExpanded(key)) {
    expandedTableKeys.value = expandedTableKeys.value.filter((item) => item !== key);
    return;
  }

  expandedTableKeys.value = [...expandedTableKeys.value, key];
}

async function copyCurrentIp() {
  await copyText(activePublicIp.value.value, '外部 IP');
}

async function copyText(value, label = '内容') {
  const text = normalizeValue(value);
  if (!text) {
    message.warning(`暂无可复制的${label}`);
    return;
  }

  try {
    await navigator.clipboard.writeText(text);
    message.success(`已复制${label}`);
  } catch {
    const input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    message.success(`已复制${label}`);
  }
}

function readHostRegion() {
  return props.page?.region ?? props.page?.area ?? store.host?.areaName ?? store.host?.area_name ?? '';
}

function readHostLine() {
  return props.page?.line ?? props.page?.lineName ?? store.host?.lineName ?? store.host?.line_name ?? '';
}

function readHostBandwidth() {
  const bandwidth = props.page?.bandwidth ?? store.host?.bandwidth;
  return formatBandwidthValue(bandwidth);
}

function readHostDownstreamBandwidth() {
  const pageSource = findFirstPresentValue(
    props.page || {},
    ['bandwidthIn', 'bandwidth_in', 'downstreamBandwidth', 'downstream_bandwidth'],
  );
  const hostSource = findFirstPresentValue(
    store.host || {},
    ['bandwidthIn', 'bandwidth_in', 'downstreamBandwidth', 'downstream_bandwidth'],
  );
  const source = pageSource.found ? pageSource : hostSource;

  if (!source.found) {
    return '未知';
  }

  const formatted = formatBandwidthValue(source.value, { emptyLabel: '未限制', zeroLabel: '未限制' });
  return formatted || '未限制';
}

function findFirstPresentValue(source, keys) {
  for (const key of keys) {
    if (Object.prototype.hasOwnProperty.call(source, key)) {
      return { found: true, value: source[key] };
    }
  }

  return { found: false, value: undefined };
}

function formatBandwidthValue(value, options = {}) {
  const emptyLabel = options.emptyLabel || '';
  const zeroLabel = options.zeroLabel || '';
  const text = String(value ?? '').trim();
  if (!text) {
    return emptyLabel;
  }

  if (/^\d+(\.\d+)?$/.test(text)) {
    const numeric = Number(text);
    return numeric > 0 ? `${numeric} Mbps` : zeroLabel;
  }

  return text;
}

function readTrafficLimit() {
  const host = store.host || {};
  return props.page?.trafficLimit
    ?? props.page?.traffic?.limit
    ?? props.page?.flowLimit
    ?? props.page?.flow?.limit
    ?? host.trafficLimit
    ?? host.traffic
    ?? host.flowLimit
    ?? host.flow_limit
    ?? '';
}

function parseTrafficLimitSize(value) {
  const text = normalizeValue(value).replace(/,/g, '');
  if (!text) {
    return null;
  }

  if (/^\d+(\.\d+)?$/.test(text)) {
    return Number(text) * 1024;
  }

  return parseDataSize(text);
}

function displayTrafficLimitValue(value) {
  const text = normalizeValue(value);
  if (!text) {
    return '-';
  }

  if (/^\d+(\.\d+)?$/.test(text)) {
    return `${trimNumber(Number(text))} GB`;
  }

  return text;
}

function resolveTrafficPercent() {
  const used = networkCore.value.trafficUsedMb;
  const limit = networkCore.value.trafficLimitMb;

  if (!networkCore.value.trafficUsageKnown || !limit) {
    return null;
  }

  return Math.round((used / limit) * 100);
}

function resolveTrafficPercentText(percent) {
  if (percent !== null) {
    return `${percent}%`;
  }

  if (!networkCore.value.trafficUsageKnown) {
    return '未知';
  }

  return '未配置';
}

function parseDataSize(value) {
  const text = normalizeValue(value).replace(/,/g, '');
  const match = text.match(/([\d.]+)/);

  if (!match) {
    return null;
  }

  const amount = Number(match[1]);
  if (!Number.isFinite(amount)) {
    return null;
  }

  const normalized = text.toLowerCase();
  if (normalized.includes('tb') || normalized.includes('t')) {
    return amount * 1024 * 1024;
  }

  if (normalized.includes('gb') || normalized.includes('g')) {
    return amount * 1024;
  }

  if (normalized.includes('kb') || normalized.includes('k')) {
    return amount / 1024;
  }

  return amount;
}

function formatDataSize(valueMb) {
  if (valueMb >= 1024) {
    return `${trimNumber(valueMb / 1024)} GB`;
  }

  return `${trimNumber(valueMb)} MB`;
}

function trimNumber(value) {
  return Number(value.toFixed(1)).toString();
}

function matchesLabel(input, labels) {
  const text = String(input || '').toLowerCase();
  return labels.some((label) => text.includes(String(label).toLowerCase()));
}

function hasValue(value) {
  return !!normalizeValue(value);
}

function normalizeValue(value) {
  const text = String(value ?? '').trim();
  return text && text !== '-' ? text : '';
}

function displayValue(value) {
  return normalizeValue(value) || '-';
}
</script>

<style scoped>
.network-page {
  width: 100%;
}

.network-page__header-shell {
  margin: 8px 8px 18px;
}

.network-page__header {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 54px;
}

.network-page__title {
  padding-left: 8px;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-hero-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-page);
}

.network-page__summary {
  margin-left: auto;
  min-height: 42px;
  padding: 0 14px;
  border: 1px solid var(--mmui-shell-border);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.02);
}

.network-page__summary-label {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.network-page__summary-value {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.network-page__summary-help {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 32px;
  min-width: 32px;
  height: 32px;
  padding: 0;
  line-height: 1 !important;
  color: var(--mmui-text-muted) !important;
}

.network-page__summary-help :deep(.anticon) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.network-page__focus-shell {
  margin: 0 8px 12px;
  overflow: hidden;
  border: 0;
  border-radius: 6px;
  background: var(--mmui-card-surface-elevated);
  box-shadow: var(--mmui-card-shadow);
}

.network-page__overview-grid {
  display: grid;
  grid-template-columns: minmax(340px, 1.42fr) repeat(3, minmax(0, 1fr));
}

.network-page__overview-item {
  display: flex;
  align-items: center;
  gap: 16px;
  min-width: 0;
  min-height: 124px;
  padding: 20px 22px;
  border-right: 1px solid var(--mmui-shell-border);
}

.network-page__overview-item:last-child {
  border-right: 0;
}

.network-page__overview-item--ip {
  gap: 18px;
}

.network-page__overview-icon,
.network-page__table-title-icon,
.network-page__info-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}

.network-page__overview-icon {
  width: 52px;
  min-width: 52px;
  height: 52px;
}

.network-page__overview-icon img {
  width: 46px;
  height: 46px;
  object-fit: contain;
}

.network-page__overview-copy {
  min-width: 0;
  flex: 1 1 auto;
}

.network-page__overview-copy > span,
.network-page__overview-label-row span {
  display: block;
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.network-page__overview-copy strong {
  display: block;
  margin-top: 8px;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title-2);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-title-2);
  word-break: break-word;
  overflow-wrap: anywhere;
}

.network-page__overview-item--ip .network-page__overview-copy strong {
  font-size: 25px;
}

.network-page__overview-copy small {
  display: block;
  margin-top: 6px;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
  word-break: break-word;
  overflow-wrap: anywhere;
}

.network-page__overview-label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.network-page__overview-label-row em {
  flex: 0 0 auto;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-caption);
  font-style: normal;
  line-height: var(--mmui-line-height-caption);
}

.network-page__focus-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.network-page__focus-card {
  min-width: 0;
  min-height: 96px;
  padding: 15px 16px;
  border-right: 1px solid var(--mmui-shell-border);
}

.network-page__focus-card:last-child {
  border-right: 0;
}

.network-page__focus-card span {
  display: block;
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
}

.network-page__focus-card strong {
  display: block;
  margin-top: 8px;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-section);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-title);
  word-break: break-word;
  overflow-wrap: anywhere;
}

.network-page__focus-card--ip strong {
  font-size: 24px;
}

.network-page__ip-layout {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-width: 0;
  height: 100%;
}

.network-page__ip-main {
  min-width: 0;
  flex: 1 1 auto;
}

.network-page__ip-head {
  margin: 0;
}

.network-page__ip-stage {
  min-width: 0;
}

.network-page__ip-value-row {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  margin-top: 8px;
}

.network-page__ip-value-row strong {
  min-width: 0;
  margin-top: 0;
}

.network-page__ip-copy {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 24px;
  min-width: 24px;
  height: 24px;
  padding: 0;
  color: var(--mmui-text-muted) !important;
}

.network-page__ip-copy :deep(.anticon),
.network-page__ip-copy :deep(svg) {
  font-size: 14px;
}

.network-page__ip-copy:hover {
  color: var(--mmui-accent-blue) !important;
}

.network-page__ip-controls {
  display: flex;
  flex: 0 0 auto;
  flex-direction: column;
  gap: 6px;
}

.network-page__ip-cycle-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  min-width: 26px;
  height: 22px;
  padding: 0;
  border: 0;
  border-radius: 6px;
  color: var(--mmui-text-muted) !important;
  background: transparent !important;
}

.network-page__ip-cycle-btn:hover {
  color: var(--mmui-accent-blue) !important;
  background: rgba(255, 255, 255, 0.04) !important;
}

.network-page__ip-cycle-btn:active {
  transform: translateY(1px);
}

.network-ip-next-enter-active,
.network-ip-next-leave-active,
.network-ip-prev-enter-active,
.network-ip-prev-leave-active {
  transition:
    opacity 0.18s ease,
    transform 0.24s cubic-bezier(0.22, 1, 0.36, 1);
}

.network-ip-next-enter-from {
  opacity: 0;
  transform: translateY(8px);
}

.network-ip-next-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

.network-ip-prev-enter-from {
  opacity: 0;
  transform: translateY(-8px);
}

.network-ip-prev-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

.network-page__focus-card small {
  display: block;
  margin-top: 6px;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
  word-break: break-word;
  overflow-wrap: anywhere;
}

.network-page__focus-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.network-page__focus-card-head em {
  flex: 0 0 auto;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-caption);
  font-style: normal;
  line-height: var(--mmui-line-height-caption);
}

.network-page__traffic-bar {
  height: 4px;
  margin-top: 10px;
  overflow: hidden;
  border-radius: 999px;
  background: color-mix(in srgb, var(--mmui-shell-border) 72%, transparent);
}

.network-page__traffic-bar i {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--mmui-accent-blue);
  transition: width 240ms ease;
}

.network-page__tables {
  display: grid;
  gap: 14px;
  margin: 0 8px 12px;
}

.network-page__table-card {
  overflow: hidden;
  border: 0;
  border-radius: 6px;
  background: var(--mmui-card-surface-elevated);
  box-shadow: var(--mmui-card-shadow);
}

.network-page__table-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  min-height: 56px;
  padding: 0 16px;
  color: inherit;
  text-align: left;
  border: 0;
  border-bottom: 1px solid var(--mmui-shell-border);
  background: transparent;
  cursor: default;
}

.network-page__table-head-side {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  flex: 0 0 auto;
}

.network-page__table-toggle {
  color: var(--mmui-text-muted);
  font-size: 12px;
  transition: transform 0.22s ease, color 0.18s ease;
}

.network-page__table-head:hover .network-page__table-toggle {
  color: var(--mmui-card-title);
}

.network-page__table-head.is-open .network-page__table-toggle {
  transform: rotate(180deg);
}

.network-page__table-stage {
  overflow: hidden;
}

.network-page__table-title {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.network-page__table-title-icon {
  width: 30px;
  min-width: 30px;
  height: 30px;
}

.network-page__table-title-icon img {
  width: 28px;
  height: 28px;
  object-fit: contain;
}

.network-page__table-title-icon.is-antd,
.network-page__info-icon.is-antd {
  color: var(--mmui-accent-blue);
}

.network-page__table-title-icon.is-antd :deep(.anticon) {
  font-size: 16px;
}

.network-page__table-count {
  flex: 0 0 auto;
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
}

.network-page__table-wrap {
  overflow-x: auto;
}

.network-page__table {
  min-width: 720px;
  width: 100%;
  border-collapse: collapse;
  color: var(--mmui-text);
  table-layout: fixed;
}

.network-page__table th {
  padding: 12px 16px;
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-footnote);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-line-height-footnote);
  text-align: left;
  border-bottom: 1px solid var(--mmui-shell-border);
}

.network-page__table td {
  padding: 15px 16px;
  border-bottom: 1px solid var(--mmui-shell-border);
  vertical-align: top;
}

.network-page__table--assigned td {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-body);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-line-height-body);
}

.network-page__table tr:last-child td {
  border-bottom: 0;
}

.network-page__table td span,
.network-page__mobile-cell span {
  display: block;
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
}

.network-page__table td strong,
.network-page__mobile-cell strong {
  display: block;
  margin-top: 5px;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-body);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-line-height-body);
  word-break: break-word;
  overflow-wrap: anywhere;
}

.network-page__mobile-list {
  display: block;
}

.network-page__detail-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.12fr);
  gap: 14px;
}

.network-page__info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  padding: 10px 16px;
}

.network-page__info-tile {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
  min-height: 88px;
  padding: 14px 0;
}

.network-page__info-tile:nth-child(odd)::after {
  position: absolute;
  content: '';
  top: 18px;
  right: 0;
  bottom: 18px;
  width: 1px;
  background: var(--mmui-shell-border);
}

.network-page__info-tile:nth-child(n + 3)::before {
  position: absolute;
  content: '';
  top: 0;
  left: 0;
  right: 0;
  height: 1px;
  background: var(--mmui-shell-border);
}

.network-page__info-icon {
  width: 42px;
  min-width: 42px;
  height: 42px;
}

.network-page__info-icon img {
  width: 38px;
  height: 38px;
  object-fit: contain;
}

.network-page__info-icon.is-antd :deep(.anticon) {
  font-size: 18px;
}

.network-page__table-card--line .network-page__table-title {
  gap: 8px;
}

.network-page__table-card--line .network-page__table-title-icon {
  width: 24px;
  min-width: 24px;
  height: 24px;
}

.network-page__table-card--line .network-page__table-title-icon.is-antd :deep(.anticon) {
  font-size: 14px;
}

.network-page__table-card--line .network-page__info-tile {
  align-items: center;
  gap: 14px;
  min-height: 78px;
  padding: 14px 0;
}

.network-page__table-card--line .network-page__info-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  min-width: 42px;
  height: 42px;
  margin-top: 0;
}

.network-page__table-card--line .network-page__info-icon.is-antd :deep(.anticon) {
  font-size: 24px;
}

.network-page__info-tile span,
.network-page__traffic-copy span,
.network-page__traffic-usage span {
  display: block;
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
}

.network-page__info-tile strong,
.network-page__traffic-copy strong,
.network-page__traffic-usage strong {
  display: block;
  margin-top: 6px;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-subheadline);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-subheadline);
  word-break: break-word;
  overflow-wrap: anywhere;
}

.network-page__traffic-copy small {
  display: block;
  margin-top: 6px;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-footnote);
  font-weight: var(--mmui-font-weight-medium);
  line-height: var(--mmui-line-height-footnote);
}

.network-page__traffic-list {
  display: grid;
  padding: 0 16px;
}

.network-page__traffic-item {
  position: relative;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) minmax(96px, auto);
  align-items: center;
  gap: 14px;
  min-height: 92px;
  padding: 18px 0;
}

.network-page__traffic-item + .network-page__traffic-item {
  border-top: 1px solid var(--mmui-shell-border);
}

.network-page__traffic-usage {
  text-align: right;
}

.network-page__ip-mobile-row {
  position: relative;
  display: grid;
  gap: 14px;
  padding: 16px;
  overflow: hidden;
  transform: translateZ(0);
  transition:
    background-color 0.22s ease,
    transform 0.24s ease;
}

.network-page__ip-mobile-row + .network-page__ip-mobile-row {
  border-top: 1px solid var(--mmui-shell-border);
}

.network-page__ip-mobile-head {
  display: grid;
  gap: 6px;
  min-width: 0;
}

.network-page__ip-mobile-head-main {
  display: grid;
  gap: 6px;
  min-width: 0;
  flex: 1 1 auto;
}

.network-page__ip-mobile-head span,
.network-page__ip-mobile-meta-item span,
.network-page__ip-mobile-key {
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
}

.network-page__ip-mobile-head strong {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
  word-break: break-word;
  overflow-wrap: anywhere;
}

.network-page__ip-mobile-value-row {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}

.network-page__mobile-copy-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  min-width: 22px;
  height: 22px;
  padding: 0 !important;
  color: var(--mmui-text-muted) !important;
}

.network-page__mobile-copy-btn:hover {
  color: var(--mmui-accent-blue) !important;
}

.network-page__ip-mobile-meta {
  display: flex;
  align-items: stretch;
  justify-content: space-between;
  gap: 0;
}

.network-page__ip-mobile-meta-item {
  position: relative;
  display: grid;
  flex: 1 1 0;
  gap: 4px;
  min-width: 0;
  padding: 0 16px;
}

.network-page__ip-mobile-meta-item:first-child {
  padding-left: 0;
}

.network-page__ip-mobile-meta-item:last-child {
  padding-right: 0;
}

.network-page__ip-mobile-meta-item + .network-page__ip-mobile-meta-item {
  margin-left: 0;
}

.network-page__ip-mobile-meta-item + .network-page__ip-mobile-meta-item::before {
  position: absolute;
  left: 0;
  top: 50%;
  width: 1px;
  height: 22px;
  content: '';
  background: var(--mmui-shell-border);
  transform: translateY(-50%);
}

.network-page__ip-mobile-meta-item strong {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-footnote);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-footnote);
  word-break: break-word;
  overflow-wrap: anywhere;
}

.network-page__mobile-card {
  display: grid;
  gap: 10px;
  padding: 16px;
  transform: translateZ(0);
  transition:
    background-color 0.22s ease,
    transform 0.24s ease;
}

.network-page__mobile-card + .network-page__mobile-card {
  border-top: 1px solid var(--mmui-shell-border);
}

.network-page__mobile-cell {
  display: grid;
  grid-template-columns: minmax(76px, 0.32fr) minmax(0, 1fr);
  align-items: start;
  gap: 12px;
}

.network-page__mobile-cell strong {
  margin-top: 0;
  text-align: right;
}

.network-page__empty {
  padding: 28px 0;
}

.network-list-enter-active,
.network-list-leave-active {
  overflow: hidden;
  transition:
    max-height 0.32s cubic-bezier(0.22, 1, 0.36, 1),
    padding-top 0.32s cubic-bezier(0.22, 1, 0.36, 1),
    padding-bottom 0.32s cubic-bezier(0.22, 1, 0.36, 1),
    opacity 0.18s ease-out,
    transform 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}

.network-list-move {
  transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}

.network-list-enter-from,
.network-list-leave-to {
  max-height: 0;
  opacity: 0;
  padding-top: 0;
  padding-bottom: 0;
  transform: translateY(-10px) scale(0.992);
}

.network-list-enter-to,
.network-list-leave-from {
  max-height: 220px;
  opacity: 1;
  padding-top: 16px;
  padding-bottom: 16px;
  transform: translateY(0) scale(1);
}

.network-panel-enter-active,
.network-panel-leave-active {
  overflow: hidden;
  transition:
    max-height 0.28s cubic-bezier(0.22, 1, 0.36, 1),
    opacity 0.18s ease-out,
    transform 0.22s ease-out;
}

.network-panel-enter-from,
.network-panel-leave-to {
  max-height: 0;
  opacity: 0;
  transform: translateY(-6px);
}

.network-panel-enter-to,
.network-panel-leave-from {
  max-height: 1200px;
  opacity: 1;
  transform: translateY(0);
}

@media (max-width: 1180px) {
  .network-page__overview-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .network-page__overview-item:nth-child(2n) {
    border-right: 0;
  }

  .network-page__overview-item:nth-child(n + 3) {
    border-top: 1px solid var(--mmui-shell-border);
  }

  .network-page__detail-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .network-page__focus-shell:not(.is-compact) .network-page__focus-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .network-page__focus-card,
  .network-page__focus-card:last-child {
    border-right: 0;
  }

  .network-page__focus-card + .network-page__focus-card {
    border-top: 1px solid var(--mmui-shell-border);
  }
}

@media (max-width: 1023px) {
  .network-page__header-shell {
    margin: 8px 8px 14px;
  }

  .network-page__header {
    flex-wrap: nowrap;
    gap: 8px;
  }

  .network-page__title {
    padding-left: 0;
  }

  .network-page__summary {
    margin-left: auto;
    flex: 0 0 auto;
    min-height: 38px;
    padding: 0 12px;
  }

  .network-page__summary-label {
    white-space: nowrap;
    font-size: 12px;
  }

  .network-page__summary-value {
    font-size: 16px;
  }

  .network-page__focus-shell.is-compact {
    margin-bottom: 10px;
  }

  .network-page__focus-shell.is-compact .network-page__overview-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .network-page__focus-shell.is-compact .network-page__overview-item {
    min-height: 108px;
    padding: 16px;
  }

  .network-page__focus-shell.is-compact .network-page__overview-icon {
    width: 44px;
    min-width: 44px;
    height: 44px;
  }

  .network-page__focus-shell.is-compact .network-page__overview-icon img {
    width: 40px;
    height: 40px;
  }

  .network-page__focus-shell.is-compact .network-page__overview-copy strong,
  .network-page__focus-shell.is-compact .network-page__overview-item--ip .network-page__overview-copy strong {
    font-size: 19px;
  }

  .network-page__focus-shell.is-compact .network-page__focus-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1px;
    background: var(--mmui-shell-border);
  }

  .network-page__focus-shell.is-compact .network-page__focus-card {
    min-height: 88px;
    padding: 14px;
    border-right: 0;
    background: var(--mmui-card-surface);
  }

  .network-page__focus-shell.is-compact .network-page__focus-card strong {
    margin-top: 6px;
    font-size: 18px;
  }

  .network-page__focus-shell.is-compact .network-page__focus-card--ip strong {
    font-size: 21px;
  }

  .network-page__focus-shell.is-compact .network-page__focus-card small {
    margin-top: 4px;
    font-size: 11px;
    line-height: 1.45;
  }

  .network-page__focus-shell.is-compact .network-page__focus-card-head {
    gap: 8px;
  }

  .network-page__focus-shell.is-compact .network-page__ip-layout {
    align-items: flex-start;
    gap: 10px;
  }

  .network-page__focus-shell.is-compact .network-page__ip-value-row {
    gap: 6px;
    margin-top: 6px;
  }

  .network-page__focus-shell.is-compact .network-page__ip-copy {
    width: 22px;
    min-width: 22px;
    height: 22px;
  }

  .network-page__focus-shell.is-compact .network-page__ip-controls {
    gap: 4px;
    margin-top: 24px;
  }

  .network-page__focus-shell.is-compact .network-page__ip-cycle-btn {
    width: 24px;
    min-width: 24px;
    height: 20px;
  }

  .network-page__empty {
    padding: 22px 0;
  }
}

@media (max-width: 620px) {
  .network-page__header-shell,
  .network-page__focus-shell,
  .network-page__tables {
    margin-right: 0;
    margin-left: 0;
  }

  .network-page__focus-shell.is-compact .network-page__overview-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .network-page__overview-item,
  .network-page__overview-item:nth-child(2n) {
    border-right: 0;
  }

  .network-page__overview-item:nth-child(n + 2) {
    border-top: 1px solid var(--mmui-shell-border);
  }

  .network-page__info-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .network-page__info-tile:nth-child(odd)::after {
    display: none;
  }

  .network-page__info-tile:nth-child(n + 2)::before {
    position: absolute;
    content: '';
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: var(--mmui-shell-border);
  }

  .network-page__traffic-item {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .network-page__traffic-usage {
    grid-column: 2;
    text-align: left;
  }

  .network-page__focus-shell.is-compact .network-page__focus-card {
    min-height: 84px;
    padding: 12px 13px;
  }

  .network-page__focus-shell.is-compact .network-page__focus-card strong {
    font-size: 17px;
  }

  .network-page__focus-shell.is-compact .network-page__focus-card--ip strong {
    font-size: 19px;
  }

  .network-page__focus-shell.is-compact .network-page__summary {
    min-height: 40px;
  }
}

@media (max-width: 520px) {
  .network-page__ip-mobile-row {
    gap: 12px;
    padding: 16px;
  }

  .network-page__ip-mobile-head strong {
    font-size: 17px;
  }

  .network-page__mobile-cell {
    grid-template-columns: minmax(0, 1fr);
  }

  .network-page__mobile-cell strong {
    text-align: left;
  }
}
</style>
