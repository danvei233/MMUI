import { getBridgeConfig, readEmbeddedPayload, refreshEmbeddedPayload } from '@/config/phpBridge';

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const cloneData = (value) => JSON.parse(JSON.stringify(value));
const mockPortQuotaTotal = 8;
const mockSnapshotQuotaTotal = 3;
const mockBackupQuotaTotal = 5;
const mockFirewallQuotaTotal = 20;
const mockSiteQuotaTotal = 2;
const MOCK_RESOURCE_SETTLE_DELAY = 2600;
const MOCK_STATE_CREATING = 1;
const MOCK_STATE_READY = 2;
const MOCK_STATE_FAILED = 3;
const MOCK_STATE_RESTORING = 4;
const MOCK_STATE_DELETING = 5;
const mockPortSeedRows = [
  {
    id: 1,
    name: 'RDP',
    sport: 53389,
    publicIp: '149.88.18.20',
    dport: 3389,
    protocol: 'TCP',
    kind: '系统创建',
    editable: false,
  },
  {
    id: 2,
    name: 'Minecraft',
    sport: 25565,
    publicIp: '149.88.18.20',
    dport: 25565,
    protocol: 'TCP',
    kind: '自定义',
    editable: true,
  },
  {
    id: 3,
    name: 'WebSSH',
    sport: 20022,
    publicIp: '149.88.18.20',
    dport: 22,
    protocol: 'TCP',
    kind: '自定义',
    editable: true,
  },
];
const mockPortRuntime = {
  rows: null,
  nextId: 10,
};
const mockCollectionSeeds = {
  snapshot: [
    {
      id: 1,
      name: 'daily-2026-04-12',
      create_time: '2026-04-12 22:15',
      state: MOCK_STATE_READY,
    },
  ],
  backup: [
    {
      id: 1,
      name: 'weekly-2026-04-07',
      create_time: '2026-04-07 04:10',
      state: MOCK_STATE_READY,
    },
    {
      id: 2,
      name: 'weekly-2026-04-14',
      create_time: '2026-04-14 04:10',
      state: MOCK_STATE_CREATING,
    },
  ],
  strategy: [
    {
      id: 1,
      protocol: 'TCP',
      direction: 'in',
      method: 'accept',
      start_port: '22',
      start_ip: '0.0.0.0/0',
      priority: '100',
    },
    {
      id: 2,
      protocol: 'TCP',
      direction: 'in',
      method: 'accept',
      start_port: '3389',
      start_ip: '0.0.0.0/0',
      priority: '110',
    },
    {
      id: 3,
      protocol: 'UDP',
      direction: 'out',
      method: 'drop',
      start_port: '-1',
      start_ip: '203.0.113.8',
      priority: '300',
    },
    {
      id: 4,
      protocol: 'ANY',
      direction: 'in',
      method: 'accept',
      start_port: '-1',
      start_ip: '10.0.0.0/24',
      priority: '500',
    },
  ],
  site: [
    {
      id: 1,
      domain: 'panel.example.com',
    },
  ],
};
const mockCollectionRuntime = {
  snapshot: { rows: null, nextId: 10 },
  backup: { rows: null, nextId: 10 },
  strategy: { rows: null, nextId: 10 },
  site: { rows: null, nextId: 10 },
};
const mockCollectionTimers = new Map();

function ensureMockPortRuntime() {
  if (!mockPortRuntime.rows) {
    mockPortRuntime.rows = cloneData(mockPortSeedRows);
    mockPortRuntime.nextId = Math.max(...mockPortSeedRows.map((row) => row.id)) + 1;
  }

  return mockPortRuntime;
}

function ensureMockCollectionRuntime(resourceKey) {
  const runtime = mockCollectionRuntime[resourceKey];
  const seedRows = mockCollectionSeeds[resourceKey] || [];

  if (!runtime.rows) {
    runtime.rows = cloneData(seedRows);
    runtime.nextId = Math.max(0, ...seedRows.map((row) => Number(row.id) || 0)) + 1;
    runtime.rows.forEach((row) => {
      primeCollectionRowState(resourceKey, row.id, row.state);
    });
  }

  return runtime;
}

function normalizeNumericId(value) {
  const nextValue = Number(value);
  return Number.isFinite(nextValue) ? nextValue : value;
}

function buildCollectionTimerKey(resourceKey, rowId) {
  return `${resourceKey}:${rowId}`;
}

function clearCollectionTimer(resourceKey, rowId) {
  const timerKey = buildCollectionTimerKey(resourceKey, rowId);
  const timer = mockCollectionTimers.get(timerKey);

  if (timer) {
    window.clearTimeout(timer);
    mockCollectionTimers.delete(timerKey);
  }
}

function scheduleCollectionTimer(resourceKey, rowId, callback, delay = MOCK_RESOURCE_SETTLE_DELAY) {
  const timerKey = buildCollectionTimerKey(resourceKey, rowId);
  clearCollectionTimer(resourceKey, rowId);

  const timer = window.setTimeout(() => {
    mockCollectionTimers.delete(timerKey);
    callback();
  }, delay);

  mockCollectionTimers.set(timerKey, timer);
}

function findCollectionRow(resourceKey, rowId) {
  const runtime = ensureMockCollectionRuntime(resourceKey);
  const targetId = normalizeNumericId(rowId);
  return runtime.rows.find((row) => normalizeNumericId(row.id) === targetId) || null;
}

function formatMockTimestamp(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hour = String(date.getHours()).padStart(2, '0');
  const minute = String(date.getMinutes()).padStart(2, '0');
  return `${year}-${month}-${day} ${hour}:${minute}`;
}

function primeCollectionRowState(resourceKey, rowId, state) {
  const currentState = Number(state);
  if (![MOCK_STATE_CREATING, MOCK_STATE_RESTORING, MOCK_STATE_DELETING].includes(currentState)) {
    return;
  }

  scheduleCollectionTimer(resourceKey, rowId, () => {
    const runtime = ensureMockCollectionRuntime(resourceKey);
    const target = findCollectionRow(resourceKey, rowId);

    if (!target) {
      return;
    }

    if (currentState === MOCK_STATE_DELETING) {
      runtime.rows = runtime.rows.filter((item) => normalizeNumericId(item.id) !== normalizeNumericId(rowId));
      return;
    }

    target.state = MOCK_STATE_READY;
  });
}

function buildPortQuotaLabel(used, total) {
  return `创建端口数：${used}/${total}`;
}

function buildPortQuickSubtitle(used, total) {
  return `数目：${used}/${total}`;
}

function syncPortRuntimeToPayload(payload) {
  const runtime = ensureMockPortRuntime();
  const rows = cloneData(runtime.rows);
  const total = payload?.pages?.port?.total || payload?.quotas?.portMapping?.total || mockPortQuotaTotal;
  const used = rows.length;

  if (payload?.pages?.port) {
    payload.pages.port.rows = rows;
    payload.pages.port.total = total;
    payload.pages.port.quotaLabel = buildPortQuotaLabel(used, total);
  }

  if (payload?.quotas?.portMapping) {
    payload.quotas.portMapping.used = used;
    payload.quotas.portMapping.total = total;
  }

  if (Array.isArray(payload?.quickLinks)) {
    payload.quickLinks = payload.quickLinks.map((item) => (
      item.key === 'port'
        ? { ...item, subtitle: buildPortQuickSubtitle(used, total) }
        : item
    ));
  }
}

function syncSnapshotRuntimeToPayload(payload) {
  const runtime = ensureMockCollectionRuntime('snapshot');
  const rows = cloneData(runtime.rows);
  const total = payload?.pages?.snapshot?.total || payload?.quotas?.snapshot?.total || mockSnapshotQuotaTotal;
  const used = rows.length;

  if (payload?.pages?.snapshot) {
    payload.pages.snapshot.rows = rows;
    payload.pages.snapshot.total = total;
    payload.pages.snapshot.quotaLabel = `创建快照数：${used}/${total}`;
  }

  if (payload?.quotas?.snapshot) {
    payload.quotas.snapshot.used = used;
    payload.quotas.snapshot.total = total;
  }

  if (Array.isArray(payload?.quickLinks)) {
    payload.quickLinks = payload.quickLinks.map((item) => (
      item.key === 'snapshot'
        ? { ...item, subtitle: `数目：${used}/${total}` }
        : item
    ));
  }
}

function syncBackupRuntimeToPayload(payload) {
  const runtime = ensureMockCollectionRuntime('backup');
  const rows = cloneData(runtime.rows);
  const total = payload?.pages?.backup?.total || payload?.quotas?.backup?.total || mockBackupQuotaTotal;
  const used = rows.length;

  if (payload?.pages?.backup) {
    payload.pages.backup.rows = rows;
    payload.pages.backup.total = total;
    payload.pages.backup.quotaLabel = `创建备份数：${used}/${total}`;
  }

  if (payload?.quotas?.backup) {
    payload.quotas.backup.used = used;
    payload.quotas.backup.total = total;
  }

  if (Array.isArray(payload?.quickLinks)) {
    payload.quickLinks = payload.quickLinks.map((item) => (
      item.key === 'backup'
        ? { ...item, subtitle: `数目：${used}/${total}` }
        : item
    ));
  }
}

function syncStrategyRuntimeToPayload(payload) {
  const runtime = ensureMockCollectionRuntime('strategy');
  const rows = cloneData(runtime.rows);
  const used = rows.length;

  if (payload?.pages?.strategy) {
    payload.pages.strategy.rows = rows;
    payload.pages.strategy.count = used;
  }

  if (payload?.quotas?.firewall) {
    payload.quotas.firewall.used = used;
    payload.quotas.firewall.total = payload.quotas.firewall.total || mockFirewallQuotaTotal;
  }

  if (Array.isArray(payload?.quickLinks)) {
    payload.quickLinks = payload.quickLinks.map((item) => (
      item.key === 'firewall'
        ? { ...item, subtitle: `数目：${used}` }
        : item
    ));
  }
}

function syncSiteRuntimeToPayload(payload) {
  const runtime = ensureMockCollectionRuntime('site');
  const rows = cloneData(runtime.rows);

  if (payload?.pages?.site) {
    payload.pages.site.rows = rows;
    payload.pages.site.quota = payload.pages.site.quota || mockSiteQuotaTotal;
    payload.pages.site.used = rows.length;
  }
}

function syncMockRuntimeToPayload(payload) {
  syncPortRuntimeToPayload(payload);
  syncSnapshotRuntimeToPayload(payload);
  syncBackupRuntimeToPayload(payload);
  syncStrategyRuntimeToPayload(payload);
  syncSiteRuntimeToPayload(payload);
}

function validatePortMappingInput(input) {
  const name = String(input?.name || '').trim();
  const sport = Number(input?.sport);
  const dport = Number(input?.dport);

  if (!name) {
    throw new Error('服务名称不能为空');
  }

  if (!Number.isInteger(sport) || sport < 1 || sport > 65535) {
    throw new Error('外网端口必须是 1-65535 的整数');
  }

  if (!Number.isInteger(dport) || dport < 1 || dport > 65535) {
    throw new Error('内网端口必须是 1-65535 的整数');
  }

  return { name, sport, dport };
}

const mockHomePayload = {
  host: {
    id: 201,
    name: 'web',
    status: '运行中',
    powerState: 'running',
    areaName: '洛杉矶 DC2',
    lineName: 'CUVIP 优化',
    osName: 'Windows Server 2022 Datacenter',
    osType: 'Windows',
    virtualType: 'KVM',
    cpu: 8,
    memory: 16,
    bandwidth: 50,
    bandwidthIn: 100,
    traffic: '20 GB',
    disk: 320,
    buyDate: '2026-03-15',
    expireDate: '2027-03-15',
    version: 'lb2.0.6',
    panelPassword: 'QZ-panel-7G!m',
    systemPassword: 'QZ-sys-8R!n',
    remoteAddress: '149.88.18.20:3389',
    image: '',
    isNat: true,
  },
  quotas: {
    portMapping: { used: 3, total: 8 },
    snapshot: { used: 1, total: 3 },
    backup: { used: 2, total: 5 },
    firewall: { used: 4, total: 20 },
  },
  monitors: {
    cpu: [12, 15, 18, 25, 32, 28, 35, 42, 39, 30, 34, 29],
    network: [128, 156, 142, 188, 205, 198, 260, 288, 224, 176, 163, 149],
    memory: [38, 39, 40, 42, 41, 43, 46, 45, 47, 46, 44, 43],
    labels: ['11:00', '11:05', '11:10', '11:15', '11:20', '11:25', '11:30', '11:35', '11:40', '11:45', '11:50', '11:55'],
  },
  quickLinks: [
    { key: 'port', title: '端口映射', subtitle: '数目：3/8', icon: 'BranchesOutlined', action: '查看详情' },
    { key: 'snapshot', title: '快照', subtitle: '数目：1/3', icon: 'CameraOutlined', action: '查看详情' },
    { key: 'backup', title: '备份', subtitle: '数目：2/5', icon: 'CopyOutlined', action: '查看详情' },
    { key: 'firewall', title: '安全策略', subtitle: '数目：4', icon: 'SafetyCertificateOutlined', action: '查看详情' },
  ],
  portableActions: [
    { key: 'vnc', label: 'VNC', icon: 'DesktopOutlined' },
    { key: 'rescue', label: '快速救援', icon: 'ToolOutlined' },
    { key: 'download', label: '一键远程', icon: 'ArrowRightOutlined' },
    { key: 'share', label: '一键分享', icon: 'ShareAltOutlined' },
  ],
  pages: {
    power: {
      cards: [
        {
          title: '开机',
          glyph: '▶',
          description: '这可能需要两到三分钟才能完成。',
          buttons: [{ label: '连接终端', primary: true }],
        },
        {
          title: '关机',
          glyph: '⏻',
          description: '强制关机，操作前请保存数据。',
          buttons: [
            { label: '正常关机', primary: true },
            { label: '强制关机', primary: false },
          ],
        },
        {
          title: '重启',
          glyph: '↻',
          description: '重启系统前请确认业务已保存。',
          buttons: [{ label: '连接终端', primary: true }],
        },
      ],
    },
    reinstall: {
      quota: '1/3',
      cards: [
        {
          title: 'Windows',
          glyph: '⌘',
          subtitle: 'Microsoft',
          description: '兼具易用性与强大生态，广泛应用于个人与企业计算环境。',
          options: ['Windows Server 2022 Datacenter', 'Windows Server 2019 Datacenter'],
        },
        {
          title: 'CentOS',
          glyph: '⬢',
          subtitle: 'redhat',
          description: '基于 Red Hat 的稳定系统，适用于服务器与企业级应用。',
          options: ['CentOS Stream 9', 'CentOS 7.9'],
        },
        {
          title: 'Ubuntu',
          glyph: '◐',
          subtitle: 'linux',
          description: '用户友好、社区活跃，适合开发者与 Linux 新手。',
          options: ['Ubuntu 24.04 LTS', 'Ubuntu 22.04 LTS'],
        },
        {
          title: 'Debian',
          glyph: '◌',
          subtitle: 'linux',
          description: '稳定、安全、高度可定制，是服务器与开发环境的理想选择。',
          options: ['Debian 12 Bookworm', 'Debian 11 Bullseye'],
        },
      ],
    },
    iso: {
      isoOptions: ['WinPE_2025_x64.iso', 'ubuntu-live-server.iso', 'debian-rescue.iso'],
      bootOptions: ['IDE', 'CD'],
    },
    network: {
      trafficLimit: '20 GB',
      tables: [
        {
          title: '分配 IP 列表',
          columns: ['primary', 'secondary', 'mask', 'gateway'],
          rows: [
            {
              primary: '外部 IP：149.88.18.20',
              secondary: 'NAT IP：10.0.2.15 (主 IP)',
              mask: '子网掩码：255.255.255.0',
              gateway: '网关：10.0.2.1',
            },
            {
              primary: '外部 IP：149.88.18.21',
              secondary: 'NAT IP：10.0.2.16',
              mask: '子网掩码：255.255.255.0',
              gateway: '网关：10.0.2.1',
            },
          ],
        },
        {
          title: '线路信息',
          columns: ['primary', 'secondary'],
          rows: [
            { primary: '区域：洛杉矶 DC2', secondary: '线路：CUVIP 优化' },
            { primary: '带宽：50 Mbps', secondary: '峰值上限：100 Mbps' },
          ],
        },
        {
          title: '流量使用情况',
          columns: ['primary', 'secondary'],
          rows: [
            { primary: '上行流量：', secondary: '月使用：1820 MB' },
            { primary: '下行流量：', secondary: '月使用：9520 MB' },
          ],
        },
      ],
    },
    vnc: {
      infoNote: '登录信息与远程方式会根据系统和网络环境有所不同，建议优先尝试网页登录或本地 RDP。',
      links: {
        web: 'https://vnc.netmc.icu/console/web',
        rdp: 'https://vnc.netmc.icu/console/rdp',
        rdp_file: 'https://vnc.netmc.icu/download/server-201.rdp',
        vnc: 'https://vnc.netmc.icu/console/vnc',
      },
      primaryMethods: [
        {
          key: 'web',
          name: '网页登录',
          icon: 'web',
          description: '通过网页直连远程控制台，无需本地客户端，适合快速登录和临时处理。可直接从浏览器进入实例桌面。',
          actionLabel: '登录',
          primary: true,
          enabled: true,
          stateText: '推荐',
        },
        {
          key: 'rdp',
          name: '本地 RDP',
          icon: 'rdp',
          description: '下载一键登录文件，双击后自动写入远程桌面凭据并启动本地远程桌面。',
          actionLabel: '下载文件',
          secondaryActionLabel: '下载文件',
          primary: false,
          enabled: true,
          stateText: '常用',
        },
        {
          key: 'vnc',
          name: 'Web VNC',
          icon: 'vnc',
          description: '使用 Web VNC 登录服务器，当系统无法正常登录时可用于图形救援与故障排查。',
          actionLabel: '登录',
          primary: false,
          enabled: true,
          stateText: '救援',
        },
      ],
      otherMethods: [
        {
          key: 'sunlogin',
          name: '向日葵远程',
          icon: 'sunlogin',
          enabled: true,
          id: '302 123 4461',
          code: '872663',
          description: '通过向日葵远程桌面连接服务器，方便快捷高效。可用于临时协助和跨网络桌面接入。',
          downloadUrl: 'https://sunlogin.oray.com/download',
        },
        {
          key: 'todesk',
          name: 'ToDesk 远程',
          icon: 'todesk',
          enabled: true,
          id: '542 031 332',
          code: '615204',
          description: '通过 ToDesk 远程桌面连接服务器，适合国内线路访问和日常维护操作。',
          downloadUrl: 'https://www.todesk.com/download.html',
        },
        {
          key: 'myrtille',
          name: 'Myrtille 远程',
          icon: 'myrtille',
          enabled: true,
          id: '149 880 1820',
          code: '338912',
          description: '通过 Myrtille Web 远程网关连接桌面或应用，适合浏览器访问与兼容模式接入。',
          downloadUrl: 'https://www.myrtille.io/',
        },
        {
          key: 'uu',
          name: 'UU 远程',
          icon: 'uu',
          enabled: true,
          description: '通过网易 UU 远程访问设备，适合游戏与桌面场景的跨端远程控制。',
          downloadUrl: 'https://uuyc.163.com/',
        },
      ],
    },
    snapshot: {
      title: '快照',
      quotaLabel: '创建快照数：1/3',
      actionText: '添加快照',
      total: mockSnapshotQuotaTotal,
      rows: [
        { id: 1, name: 'daily-2026-04-12', create_time: '2026-04-12 22:15', state: MOCK_STATE_READY },
      ],
    },
    backup: {
      title: '备份',
      quotaLabel: '创建备份数：2/5',
      actionText: '添加备份',
      total: mockBackupQuotaTotal,
      rows: [
        { id: 1, name: 'weekly-2026-04-07', create_time: '2026-04-07 04:10', state: MOCK_STATE_READY },
        { id: 2, name: 'weekly-2026-04-14', create_time: '2026-04-14 04:10', state: MOCK_STATE_CREATING },
      ],
    },
    strategy: {
      count: 4,
      rows: [
        { id: 1, protocol: 'TCP', direction: 'in', method: 'accept', start_port: '22', start_ip: '0.0.0.0/0', priority: '100' },
        { id: 2, protocol: 'TCP', direction: 'in', method: 'accept', start_port: '3389', start_ip: '0.0.0.0/0', priority: '110' },
      ],
    },
    port: {
      title: '映射',
      quotaLabel: '创建端口数：3/8',
      actionText: '添加端口映射',
      total: mockPortQuotaTotal,
      columns: [
        { key: 'name', title: '服务名称' },
        { key: 'sport', title: '外网端口' },
        { key: 'publicIp', title: '外网 IP' },
        { key: 'dport', title: '内网端口' },
        { key: 'kind', title: '类型' },
        { key: 'action', title: '操作' },
      ],
      rows: cloneData(mockPortSeedRows),
    },
    site: {
      quota: mockSiteQuotaTotal,
      rows: [
        { id: 1, domain: 'panel.example.com' },
      ],
    },
  },
};

function cloneMockHomePayload() {
  const payload = cloneData(mockHomePayload);
  syncMockRuntimeToPayload(payload);
  return payload;
}

async function refreshPortMappings() {
  const runtime = ensureMockPortRuntime();
  await wait(100);
  return cloneData(runtime.rows);
}

async function refreshSnapshots() {
  const runtime = ensureMockCollectionRuntime('snapshot');
  await wait(100);
  return cloneData(runtime.rows);
}

async function refreshBackups() {
  const runtime = ensureMockCollectionRuntime('backup');
  await wait(100);
  return cloneData(runtime.rows);
}

async function refreshFirewallRules() {
  const runtime = ensureMockCollectionRuntime('strategy');
  await wait(100);
  return cloneData(runtime.rows);
}

async function refreshDomainWhitelist() {
  const runtime = ensureMockCollectionRuntime('site');
  await wait(100);
  return cloneData(runtime.rows);
}

async function createPortMapping(input) {
  const runtime = ensureMockPortRuntime();
  const { name, sport, dport } = validatePortMappingInput(input);

  if (runtime.rows.length >= mockPortQuotaTotal) {
    throw new Error('端口映射数量已达上限');
  }

  if (runtime.rows.some((row) => Number(row.sport) === sport)) {
    throw new Error('外网端口已存在，请更换后重试');
  }

  const row = {
    id: runtime.nextId,
    name,
    sport,
    publicIp: '149.88.18.20',
    dport,
    protocol: 'TCP',
    kind: '自定义',
    editable: true,
  };

  runtime.nextId += 1;
  runtime.rows.unshift(row);

  await wait(120);
  return cloneData(row);
}

async function deletePortMappings(ids) {
  const runtime = ensureMockPortRuntime();
  const idSet = new Set(ids.map((id) => Number(id)).filter(Number.isFinite));

  runtime.rows = runtime.rows.filter((row) => {
    if (row.kind === '系统创建') {
      return true;
    }

    return !idSet.has(Number(row.id));
  });

  await wait(100);
  return cloneData(runtime.rows);
}

async function findPortMappingCandidates(keywords = '') {
  const runtime = ensureMockPortRuntime();
  const usedPorts = new Set(
    runtime.rows
      .map((row) => Number(row.sport))
      .filter((port) => Number.isInteger(port)),
  );
  const prefix = String(keywords || '').replace(/\D/g, '');
  const candidates = [];

  if (prefix) {
    let cursor = Math.min(65535, Math.max(1, Number(prefix)));
    while (candidates.length < 8 && cursor <= 65535) {
      if (!usedPorts.has(cursor)) {
        candidates.push(cursor);
      }
      cursor += 1;
    }
  }

  while (candidates.length < 8) {
    const candidate = 10000 + Math.floor(Math.random() * 50000);
    if (!usedPorts.has(candidate) && !candidates.includes(candidate)) {
      candidates.push(candidate);
    }
  }

  await wait(60);
  return candidates;
}

async function generatePortMappingCandidate(keywords = '') {
  const candidates = await findPortMappingCandidates(keywords);
  return candidates[0];
}

async function createSnapshot() {
  const runtime = ensureMockCollectionRuntime('snapshot');

  if (runtime.rows.length >= mockSnapshotQuotaTotal) {
    throw new Error('快照数量已达上限');
  }

  const row = {
    id: runtime.nextId,
    name: `snapshot-${formatMockTimestamp(new Date()).replace(/[- :]/g, '').slice(2, 12)}`,
    create_time: formatMockTimestamp(),
    state: MOCK_STATE_CREATING,
  };

  runtime.nextId += 1;
  runtime.rows.unshift(row);

  scheduleCollectionTimer('snapshot', row.id, () => {
    const target = findCollectionRow('snapshot', row.id);
    if (target && target.state === MOCK_STATE_CREATING) {
      target.state = MOCK_STATE_READY;
    }
  });

  await wait(120);
  return cloneData(row);
}

async function restoreSnapshot(id) {
  const row = findCollectionRow('snapshot', id);
  if (!row) {
    throw new Error('快照不存在');
  }

  row.state = MOCK_STATE_RESTORING;
  scheduleCollectionTimer('snapshot', row.id, () => {
    const target = findCollectionRow('snapshot', row.id);
    if (target && target.state === MOCK_STATE_RESTORING) {
      target.state = MOCK_STATE_READY;
    }
  });

  await wait(100);
  return cloneData(row);
}

async function deleteSnapshot(id) {
  const runtime = ensureMockCollectionRuntime('snapshot');
  const row = findCollectionRow('snapshot', id);
  if (!row) {
    throw new Error('快照不存在');
  }

  row.state = MOCK_STATE_DELETING;
  scheduleCollectionTimer('snapshot', row.id, () => {
    runtime.rows = runtime.rows.filter((item) => normalizeNumericId(item.id) !== normalizeNumericId(id));
    clearCollectionTimer('snapshot', id);
  });

  await wait(100);
  return cloneData(runtime.rows);
}

async function deleteSnapshots(ids) {
  let rows = [];
  for (const id of ids) {
    rows = await deleteSnapshot(id);
  }

  return rows;
}

async function createBackup() {
  const runtime = ensureMockCollectionRuntime('backup');

  if (runtime.rows.length >= mockBackupQuotaTotal) {
    throw new Error('备份数量已达上限');
  }

  const row = {
    id: runtime.nextId,
    name: `backup-${formatMockTimestamp(new Date()).replace(/[- :]/g, '').slice(2, 12)}`,
    create_time: formatMockTimestamp(),
    state: MOCK_STATE_CREATING,
  };

  runtime.nextId += 1;
  runtime.rows.unshift(row);

  scheduleCollectionTimer('backup', row.id, () => {
    const target = findCollectionRow('backup', row.id);
    if (target && target.state === MOCK_STATE_CREATING) {
      target.state = MOCK_STATE_READY;
    }
  });

  await wait(120);
  return cloneData(row);
}

async function restoreBackup(id) {
  const row = findCollectionRow('backup', id);
  if (!row) {
    throw new Error('备份不存在');
  }

  row.state = MOCK_STATE_RESTORING;
  scheduleCollectionTimer('backup', row.id, () => {
    const target = findCollectionRow('backup', row.id);
    if (target && target.state === MOCK_STATE_RESTORING) {
      target.state = MOCK_STATE_READY;
    }
  });

  await wait(100);
  return cloneData(row);
}

async function deleteBackup(id) {
  const runtime = ensureMockCollectionRuntime('backup');
  const row = findCollectionRow('backup', id);
  if (!row) {
    throw new Error('备份不存在');
  }

  row.state = MOCK_STATE_DELETING;
  scheduleCollectionTimer('backup', row.id, () => {
    runtime.rows = runtime.rows.filter((item) => normalizeNumericId(item.id) !== normalizeNumericId(id));
    clearCollectionTimer('backup', id);
  });

  await wait(100);
  return cloneData(runtime.rows);
}

async function deleteBackups(ids) {
  let rows = [];
  for (const id of ids) {
    rows = await deleteBackup(id);
  }

  return rows;
}

function normalizeFirewallSelectValue(value, fallback = '') {
  const nextValue = String(value || fallback).trim().toUpperCase();
  return nextValue;
}

function normalizeFirewallDirection(value) {
  const nextValue = String(value || '').trim().toLowerCase();
  if (nextValue === '入') {
    return 'in';
  }
  if (nextValue === '出') {
    return 'out';
  }
  return nextValue;
}

function normalizeFirewallMethod(value) {
  const nextValue = String(value || '').trim().toLowerCase();
  if (nextValue === '允许') {
    return 'accept';
  }
  if (nextValue === '拒绝') {
    return 'drop';
  }
  return nextValue;
}

function nextFirewallPriority(rows) {
  const priorities = rows
    .map((row) => Number(row.priority))
    .filter((value) => Number.isInteger(value));

  return String((Math.max(90, ...priorities) + 10));
}

async function createFirewallRule(input) {
  const runtime = ensureMockCollectionRuntime('strategy');
  const direction = normalizeFirewallDirection(input?.direction);
  const method = normalizeFirewallMethod(input?.method);
  const protocol = normalizeFirewallSelectValue(input?.protocol);
  const port = String(input?.port || '').trim();
  const ip = String(input?.ip || '').trim();
  const priorityInput = String(input?.priority ?? '').trim();

  if (!['in', 'out'].includes(direction)) {
    throw new Error('请选择规则方向');
  }

  if (!['accept', 'drop'].includes(method)) {
    throw new Error('请选择授权策略');
  }

  if (!['ANY', 'TCP', 'UDP', 'ICMP'].includes(protocol)) {
    throw new Error('请选择协议类型');
  }

  if (!port) {
    throw new Error('端口不能为空');
  }

  if (!ip) {
    throw new Error('IP 不能为空');
  }

  const priority = priorityInput || nextFirewallPriority(runtime.rows);
  if (!/^\d+$/.test(priority)) {
    throw new Error('优先级必须是数字');
  }

  const row = {
    id: runtime.nextId,
    protocol,
    direction,
    method,
    start_port: port,
    start_ip: ip,
    priority,
  };

  runtime.nextId += 1;
  runtime.rows.unshift(row);

  await wait(120);
  return cloneData(row);
}

async function deleteFirewallRule(id) {
  const runtime = ensureMockCollectionRuntime('strategy');
  runtime.rows = runtime.rows.filter((row) => normalizeNumericId(row.id) !== normalizeNumericId(id));
  await wait(100);
  return cloneData(runtime.rows);
}

async function deleteFirewallRules(ids) {
  let rows = [];
  for (const id of ids) {
    rows = await deleteFirewallRule(id);
  }

  return rows;
}

function validateDomainName(value) {
  const nextValue = String(value || '').trim().toLowerCase();
  const domainRegex = /^(?!:\/\/)([a-z0-9][a-z0-9-]{0,61}[a-z0-9]\.)+[a-z]{2,}$/;

  if (!domainRegex.test(nextValue)) {
    throw new Error('请输入完整有效的域名');
  }

  return nextValue;
}

async function createDomainWhitelist(input) {
  const runtime = ensureMockCollectionRuntime('site');
  const domain = validateDomainName(input?.domain);

  if (runtime.rows.length >= mockSiteQuotaTotal) {
    throw new Error('域名白名单数量已达上限');
  }

  if (runtime.rows.some((row) => String(row.domain || '').toLowerCase() === domain)) {
    throw new Error('该域名已经存在');
  }

  const row = {
    id: runtime.nextId,
    domain,
  };

  runtime.nextId += 1;
  runtime.rows.unshift(row);

  await wait(120);
  return cloneData(row);
}

async function deleteDomainWhitelist(id) {
  const runtime = ensureMockCollectionRuntime('site');
  runtime.rows = runtime.rows.filter((row) => normalizeNumericId(row.id) !== normalizeNumericId(id));
  await wait(100);
  return cloneData(runtime.rows);
}

async function deleteDomainWhitelists(ids) {
  let rows = [];
  for (const id of ids) {
    rows = await deleteDomainWhitelist(id);
  }

  return rows;
}

async function getHomePayload() {
  const bridgeConfig = getBridgeConfig();
  const embeddedPayload = readEmbeddedPayload();

  if (embeddedPayload && Object.keys(embeddedPayload).length > 0) {
    return embeddedPayload;
  }

  if (!bridgeConfig.useMock) {
    const refreshedPayload = await refreshEmbeddedPayload();
    if (refreshedPayload) {
      return refreshedPayload;
    }
  }

  await wait(180);
  return cloneMockHomePayload();
}

async function refreshState() {
  await wait(80);
  return {
    state: 2,
    status: '运行中',
    powerState: 'running',
  };
}

async function refreshMonitor() {
  await wait(80);
  const nowLabel = new Date().toLocaleTimeString('zh-CN', { hour12: false });

  return {
    cpu: [10 + Math.round(Math.random() * 45)],
    memory: [35 + Math.round(Math.random() * 25)],
    network: [120 + Math.round(Math.random() * 180)],
    labels: [nowLabel],
  };
}

async function powerAction(action) {
  await wait(180);
  const payload = cloneMockHomePayload();

  if (['shutdown', 'close', 'poweroff', 'force'].includes(action)) {
    payload.host.status = '已关机';
    payload.host.powerState = 'stopped';
    payload.host.state = 3;
  } else {
    payload.host.status = '运行中';
    payload.host.powerState = 'running';
    payload.host.state = 2;
  }

  return payload;
}

async function reinstallSystem(input = {}) {
  await wait(260);
  const payload = cloneMockHomePayload();
  payload.host.osName = input.image || input.osName || payload.host.osName;
  payload.host.systemPassword = input.password || payload.host.systemPassword;
  return payload;
}

async function setBootMode(input = {}) {
  await wait(140);
  const payload = cloneMockHomePayload();
  payload.host.bios = input.bootType || input.bios || payload.host.bios || 'IDE';
  payload.host.nowIso = input.isoPath || input.iso_path || '';
  return payload;
}

async function updateSystemPassword(password) {
  await wait(120);
  const payload = cloneMockHomePayload();
  payload.host.systemPassword = password || payload.host.systemPassword;
  return payload;
}

async function updatePanelPassword(panelPassword) {
  await wait(120);
  const payload = cloneMockHomePayload();
  payload.host.panelPassword = panelPassword || payload.host.panelPassword;
  return payload;
}

async function syncTime() {
  await wait(100);
  return cloneMockHomePayload();
}

async function openVnc() {
  await wait(80);
  return mockHomePayload.pages.vnc.links.vnc;
}

export const mockAdapter = {
  bridge: {
    getConfig: getBridgeConfig,
  },
  dashboard: {
    getHomePayload,
    refreshEmbeddedPayload,
    refreshState,
    refreshMonitor,
    powerAction,
    reinstallSystem,
    setBootMode,
    updateSystemPassword,
    updatePanelPassword,
    syncTime,
    openVnc,
    refreshPortMappings,
    createPortMapping,
    deletePortMappings,
    generatePortMappingCandidate,
    findPortMappingCandidates,
    refreshSnapshots,
    createSnapshot,
    restoreSnapshot,
    deleteSnapshot,
    deleteSnapshots,
    refreshBackups,
    createBackup,
    restoreBackup,
    deleteBackup,
    deleteBackups,
    refreshFirewallRules,
    createFirewallRule,
    deleteFirewallRule,
    deleteFirewallRules,
    refreshDomainWhitelist,
    createDomainWhitelist,
    deleteDomainWhitelist,
    deleteDomainWhitelists,
  },
};
