import { defineStore } from 'pinia';
import { mmuiApi } from '@/api';

function buildPortQuotaLabel(used, total) {
  return `创建端口数：${used}/${total}`;
}

function buildPortQuickSubtitle(used, total) {
  return `数目：${used}/${total}`;
}

function buildSnapshotQuotaLabel(used, total) {
  return `创建快照数：${used}/${total}`;
}

function buildBackupQuotaLabel(used, total) {
  return `创建备份数：${used}/${total}`;
}

function buildQuickSubtitle(used, total) {
  return `数目：${used}/${total}`;
}

function buildCountSubtitle(count) {
  return `数目：${count}`;
}

export const useDashboardStore = defineStore('dashboard', {
  state: () => ({
    ready: false,
    loading: false,
    payload: null,
  }),
  getters: {
    host: (state) => state.payload?.host ?? {},
    quotas: (state) => state.payload?.quotas ?? {},
    monitors: (state) => state.payload?.monitors ?? {},
    brand: (state) => state.payload?.brand ?? {},
    quickLinks: (state) => state.payload?.quickLinks ?? [],
    portableActions: (state) => state.payload?.portableActions ?? [],
    pages: (state) => state.payload?.pages ?? {},
  },
  actions: {
    syncQuickLinkSubtitle(targetKey, subtitle) {
      if (!Array.isArray(this.payload?.quickLinks)) {
        return;
      }

      this.payload.quickLinks = this.payload.quickLinks.map((item) => (
        item.key === targetKey ? { ...item, subtitle } : item
      ));
    },
    syncPortMappings(rows, options = {}) {
      if (!this.payload?.pages?.port) {
        return rows;
      }

      const total = this.payload.pages.port.total || this.payload.quotas?.portMapping?.total || rows.length;
      this.payload.pages.port.rows = rows;
      if (options.silent) {
        return rows;
      }

      this.payload.pages.port.total = total;
      this.payload.pages.port.quotaLabel = buildPortQuotaLabel(rows.length, total);

      if (this.payload.quotas?.portMapping) {
        this.payload.quotas.portMapping.used = rows.length;
        this.payload.quotas.portMapping.total = total;
      }

      if (Array.isArray(this.payload.quickLinks)) {
        this.syncQuickLinkSubtitle('port', buildPortQuickSubtitle(rows.length, total));
      }

      return rows;
    },
    syncSnapshots(rows) {
      if (!this.payload?.pages?.snapshot) {
        return rows;
      }

      const total = this.payload.pages.snapshot.total || this.payload.quotas?.snapshot?.total || rows.length;
      this.payload.pages.snapshot.rows = rows;
      this.payload.pages.snapshot.total = total;
      this.payload.pages.snapshot.quotaLabel = buildSnapshotQuotaLabel(rows.length, total);

      if (this.payload.quotas?.snapshot) {
        this.payload.quotas.snapshot.used = rows.length;
        this.payload.quotas.snapshot.total = total;
      }

      this.syncQuickLinkSubtitle('snapshot', buildQuickSubtitle(rows.length, total));
      return rows;
    },
    syncBackups(rows) {
      if (!this.payload?.pages?.backup) {
        return rows;
      }

      const total = this.payload.pages.backup.total || this.payload.quotas?.backup?.total || rows.length;
      this.payload.pages.backup.rows = rows;
      this.payload.pages.backup.total = total;
      this.payload.pages.backup.quotaLabel = buildBackupQuotaLabel(rows.length, total);

      if (this.payload.quotas?.backup) {
        this.payload.quotas.backup.used = rows.length;
        this.payload.quotas.backup.total = total;
      }

      this.syncQuickLinkSubtitle('backup', buildQuickSubtitle(rows.length, total));
      return rows;
    },
    syncFirewallRules(rows) {
      if (!this.payload?.pages?.strategy) {
        return rows;
      }

      this.payload.pages.strategy.rows = rows;
      this.payload.pages.strategy.count = rows.length;

      if (this.payload.quotas?.firewall) {
        this.payload.quotas.firewall.used = rows.length;
      }

      this.syncQuickLinkSubtitle('firewall', buildCountSubtitle(rows.length));
      return rows;
    },
    syncDomainWhitelist(rows) {
      if (!this.payload?.pages?.site) {
        return rows;
      }

      this.payload.pages.site.rows = rows;
      this.payload.pages.site.used = rows.length;
      return rows;
    },
    mergePayloadPreservingMonitors(payload) {
      if (!payload) {
        return payload;
      }

      const currentMonitors = this.payload?.monitors;
      const hasIncomingMonitors = ['cpu', 'memory', 'network', 'labels'].some((key) => (
        Array.isArray(payload?.monitors?.[key]) && payload.monitors[key].length > 0
      ));

      this.payload = {
        ...payload,
        monitors: hasIncomingMonitors ? payload.monitors : currentMonitors,
      };

      return this.payload;
    },
    mergeMonitorSample(sample) {
      if (!this.payload?.monitors || !sample) {
        return sample;
      }

      ['cpu', 'memory', 'network', 'labels'].forEach((key) => {
        const current = Array.isArray(this.payload.monitors[key]) ? this.payload.monitors[key] : [];
        const incoming = Array.isArray(sample[key]) ? sample[key] : [];
        this.payload.monitors[key] = [...current, ...incoming].slice(-24);
      });

      return sample;
    },
    setHostRuntimeStatus(status) {
      if (!this.payload?.host || !status) {
        return;
      }

      this.payload.host = {
        ...this.payload.host,
        ...status,
      };
    },
    async load() {
      this.loading = true;

      try {
        this.payload = await mmuiApi.dashboard.getHomePayload();
        this.ready = true;
      } finally {
        this.loading = false;
      }
    },
    async refreshPortMappings(options = {}) {
      const rows = await mmuiApi.dashboard.refreshPortMappings();
      return this.syncPortMappings(rows, options);
    },
    async createPortMapping(input, options = {}) {
      await mmuiApi.dashboard.createPortMapping(input);
      return this.refreshPortMappings(options);
    },
    async deletePortMappings(ids, options = {}) {
      const rows = await mmuiApi.dashboard.deletePortMappings(ids);
      return this.syncPortMappings(rows, options);
    },
    async generatePortMappingCandidate(keywords = '') {
      return mmuiApi.dashboard.generatePortMappingCandidate(keywords);
    },
    async findPortMappingCandidates(keywords = '') {
      return mmuiApi.dashboard.findPortMappingCandidates(keywords);
    },
    async refreshSnapshots() {
      const rows = await mmuiApi.dashboard.refreshSnapshots();
      return this.syncSnapshots(rows);
    },
    async createSnapshot() {
      await mmuiApi.dashboard.createSnapshot();
      return this.refreshSnapshots();
    },
    async restoreSnapshot(id) {
      await mmuiApi.dashboard.restoreSnapshot(id);
      return this.refreshSnapshots();
    },
    async deleteSnapshot(id) {
      const rows = await mmuiApi.dashboard.deleteSnapshot(id);
      return this.syncSnapshots(rows);
    },
    async deleteSnapshots(ids) {
      const rows = await mmuiApi.dashboard.deleteSnapshots(ids);
      return this.syncSnapshots(rows);
    },
    async refreshBackups() {
      const rows = await mmuiApi.dashboard.refreshBackups();
      return this.syncBackups(rows);
    },
    async createBackup() {
      await mmuiApi.dashboard.createBackup();
      return this.refreshBackups();
    },
    async restoreBackup(id) {
      await mmuiApi.dashboard.restoreBackup(id);
      return this.refreshBackups();
    },
    async deleteBackup(id) {
      const rows = await mmuiApi.dashboard.deleteBackup(id);
      return this.syncBackups(rows);
    },
    async deleteBackups(ids) {
      const rows = await mmuiApi.dashboard.deleteBackups(ids);
      return this.syncBackups(rows);
    },
    async refreshFirewallRules() {
      const rows = await mmuiApi.dashboard.refreshFirewallRules();
      return this.syncFirewallRules(rows);
    },
    async createFirewallRule(input) {
      await mmuiApi.dashboard.createFirewallRule(input);
      return this.refreshFirewallRules();
    },
    async deleteFirewallRule(id) {
      const rows = await mmuiApi.dashboard.deleteFirewallRule(id);
      return this.syncFirewallRules(rows);
    },
    async deleteFirewallRules(ids) {
      const rows = await mmuiApi.dashboard.deleteFirewallRules(ids);
      return this.syncFirewallRules(rows);
    },
    async refreshDomainWhitelist() {
      const rows = await mmuiApi.dashboard.refreshDomainWhitelist();
      return this.syncDomainWhitelist(rows);
    },
    async createDomainWhitelist(input) {
      await mmuiApi.dashboard.createDomainWhitelist(input);
      return this.refreshDomainWhitelist();
    },
    async deleteDomainWhitelist(id) {
      const rows = await mmuiApi.dashboard.deleteDomainWhitelist(id);
      return this.syncDomainWhitelist(rows);
    },
    async deleteDomainWhitelists(ids) {
      const rows = await mmuiApi.dashboard.deleteDomainWhitelists(ids);
      return this.syncDomainWhitelist(rows);
    },
    async refreshFromPhp(options = {}) {
      this.loading = true;

      try {
        const payload = await mmuiApi.dashboard.refreshEmbeddedPayload(options);
        if (payload) {
          this.mergePayloadPreservingMonitors(payload);
        }
      } finally {
        this.loading = false;
      }
    },
    async refreshState() {
      const result = await mmuiApi.dashboard.refreshState();
      if (result?.status && this.payload?.host) {
        this.payload.host.status = result.status;
        this.payload.host.powerState = result.powerState || this.payload.host.powerState;
        this.payload.host.state = result.state || this.payload.host.state;
      }
      return result;
    },
    async refreshMonitor() {
      const sample = await mmuiApi.dashboard.refreshMonitor();
      return this.mergeMonitorSample(sample);
    },
    async powerAction(action) {
      const payload = await mmuiApi.dashboard.powerAction(action);
      if (payload) {
        this.mergePayloadPreservingMonitors(payload);
      }
      return payload;
    },
    async reinstallSystem(input) {
      const payload = await mmuiApi.dashboard.reinstallSystem(input);
      if (payload) {
        this.mergePayloadPreservingMonitors(payload);
      }
      return payload;
    },
    async setBootMode(input) {
      const payload = await mmuiApi.dashboard.setBootMode(input);
      if (payload) {
        this.mergePayloadPreservingMonitors(payload);
      }
      return payload;
    },
    async refreshIsoOptions() {
      const options = await mmuiApi.dashboard.refreshIsoOptions();
      if (!this.payload?.pages?.iso) {
        return options;
      }

      this.payload.pages.iso.isoOptions = Array.isArray(options) ? [...options] : [];
      return this.payload.pages.iso.isoOptions;
    },
    async updateSystemPassword(password) {
      const payload = await mmuiApi.dashboard.updateSystemPassword(password);
      if (payload) {
        this.mergePayloadPreservingMonitors(payload);
      }
      return payload;
    },
    async updatePanelPassword(panelPassword) {
      const payload = await mmuiApi.dashboard.updatePanelPassword(panelPassword);
      if (payload) {
        this.mergePayloadPreservingMonitors(payload);
      }
      return payload;
    },
    async syncTime(enabled) {
      const payload = await mmuiApi.dashboard.syncTime(enabled);
      if (payload) {
        this.mergePayloadPreservingMonitors(payload);
      }
      return payload;
    },
    async openVnc() {
      return mmuiApi.dashboard.openVnc();
    },
  },
});
