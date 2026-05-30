import {
  CameraOutlined,
  ClusterOutlined,
  CopyOutlined,
  DashboardOutlined,
  DesktopOutlined,
  FilterOutlined,
  GlobalOutlined,
  SafetyCertificateOutlined,
  SettingOutlined,
  ShareAltOutlined,
} from '@ant-design/icons-vue';

export const mmuiPageMeta = {
  panel: { key: 'panel', label: '面板', title: '面板' },
  monitor: { key: 'monitor', label: '监控', title: '监控' },
  systemControl: { key: 'system-control', label: '系统管理', title: '系统管理' },
  power: { key: 'power', label: '电源', title: '电源' },
  reinstall: { key: 'reinstall', label: '重装', title: '重装系统' },
  iso: { key: 'iso', label: '光驱', title: '启动管理' },
  vnc: { key: 'vnc', label: 'VNC', title: '辅助远程' },
  snapshot: { key: 'snapshot', label: '快照', title: '快照' },
  backup: { key: 'backup', label: '备份', title: '备份' },
  'network-detail': { key: 'network-detail', label: '网络', title: '网络信息' },
  strategy: { key: 'strategy', label: '策略', title: '策略' },
  port: { key: 'port', label: '端口映射', title: '映射' },
  site: { key: 'site', label: '挂机宝建站', title: '挂机宝建站' },
};

export const mmuiPanelEntry = {
  ...mmuiPageMeta.panel,
  icon: DashboardOutlined,
};

export const mmuiSidebarEntries = [
  {
    key: mmuiPageMeta.panel.key,
    label: mmuiPageMeta.panel.label,
    icon: DashboardOutlined,
    type: 'page',
  },
  {
    key: mmuiPageMeta.monitor.key,
    label: mmuiPageMeta.monitor.label,
    icon: SafetyCertificateOutlined,
    type: 'page',
  },
  {
    key: mmuiPageMeta.systemControl.key,
    label: '系统',
    icon: SettingOutlined,
    type: 'page',
  },
  {
    key: mmuiPageMeta.vnc.key,
    label: mmuiPageMeta.vnc.label,
    icon: DesktopOutlined,
    type: 'page',
  },
  {
    key: mmuiPageMeta['network-detail'].key,
    label: mmuiPageMeta['network-detail'].label,
    icon: ClusterOutlined,
    type: 'page',
  },
  {
    key: mmuiPageMeta.snapshot.key,
    label: mmuiPageMeta.snapshot.label,
    icon: CameraOutlined,
    type: 'page',
  },
  {
    key: mmuiPageMeta.backup.key,
    label: mmuiPageMeta.backup.label,
    icon: CopyOutlined,
    type: 'page',
  },
  {
    key: mmuiPageMeta.port.key,
    label: '映射',
    icon: ShareAltOutlined,
    type: 'page',
  },
  {
    key: mmuiPageMeta.strategy.key,
    label: mmuiPageMeta.strategy.label,
    icon: FilterOutlined,
    type: 'page',
  },
  {
    key: mmuiPageMeta.site.key,
    label: '建站',
    icon: GlobalOutlined,
    type: 'page',
  },
];
