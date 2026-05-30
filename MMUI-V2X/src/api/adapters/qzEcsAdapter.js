import {
  getBridgeConfig,
  readEmbeddedPayload,
  refreshEmbeddedPayload as fetchEmbeddedPayload,
} from '@/config/phpBridge';
import { assertQzSuccess, assertThinkSuccess, extractLayuiRows, requestJson } from '@/api/core/client';

const cloneData = (value) => JSON.parse(JSON.stringify(value));

let activePayload = null;

const COLLECTION_TASK_BUSY_STATES = new Set([4, 5]);
const COLLECTION_TASK_POLL_INTERVAL = 800;
const COLLECTION_TASK_POLL_TIMEOUT = 120000;
const COLLECTION_TASK_RETRY_TIMEOUT = 120000;

function isPlainObject(value) {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function hasMonitorSamples(monitors) {
  return ['cpu', 'memory', 'network', 'labels'].some((key) => (
    Array.isArray(monitors?.[key]) && monitors[key].length > 0
  ));
}

function mergePayload(base, patch) {
  if (!isPlainObject(base)) {
    return cloneData(patch);
  }

  if (!isPlainObject(patch)) {
    return cloneData(base);
  }

  const result = cloneData(base);
  Object.entries(patch).forEach(([key, value]) => {
    if (key === 'scope') {
      return;
    }

    if (key === 'monitors' && !hasMonitorSamples(value)) {
      return;
    }

    if (isPlainObject(value) && isPlainObject(result[key])) {
      result[key] = mergePayload(result[key], value);
      return;
    }

    result[key] = cloneData(value);
  });

  return result;
}

function getActions() {
  return activePayload?.actions || {};
}

function getHostId() {
  return activePayload?.host?.id;
}

function normalizeIds(ids) {
  return Array.isArray(ids) ? ids : [ids];
}

function wait(ms) {
  return new Promise((resolve) => {
    window.setTimeout(resolve, ms);
  });
}

function resolveActionUrl(actionUrl) {
  if (!actionUrl) {
    throw new Error('当前接口未配置');
  }

  return new URL(actionUrl, window.location.href).toString();
}

function findReinstallTemplateId(imageName) {
  const cards = activePayload?.pages?.reinstall?.cards || [];

  for (const card of cards) {
    if (card?.optionIds && Object.prototype.hasOwnProperty.call(card.optionIds, imageName)) {
      return card.optionIds[imageName];
    }

    const match = (card.options || []).find((option) => {
      if (typeof option === 'string') {
        return option === imageName;
      }

      return option?.name === imageName || option?.os_name === imageName || String(option?.id) === String(imageName);
    });

    if (match && typeof match === 'object') {
      return match.id || match.config_id || match.template_id || match.os_id || match.name || match.os_name;
    }
  }

  return imageName;
}

function findPageRow(pageKey, rowId) {
  const rows = activePayload?.pages?.[pageKey]?.rows || [];
  const targetId = String(rowId);
  return rows.find((row) => String(row?.id) === targetId) || null;
}

function syncPageRows(pageKey, rows) {
  if (activePayload?.pages?.[pageKey]) {
    activePayload.pages[pageKey].rows = cloneData(rows);
  }

  return rows;
}

function hasBusyCollectionTask(rows) {
  return rows.some((row) => COLLECTION_TASK_BUSY_STATES.has(Number(row?.state)));
}

function hasCollectionRow(rows, rowId) {
  const targetId = String(rowId);
  return rows.some((row) => String(row?.id) === targetId);
}

async function waitForCollectionTaskIdle(refreshRows, timeoutMs = COLLECTION_TASK_POLL_TIMEOUT) {
  const startedAt = Date.now();

  while (Date.now() - startedAt < timeoutMs) {
    await wait(COLLECTION_TASK_POLL_INTERVAL);
    const rows = await refreshRows();

    if (!hasBusyCollectionTask(rows)) {
      return rows;
    }
  }

  throw new Error('轻舟仍在处理上一个任务，请稍后刷新后继续操作');
}

async function waitForCollectionRowRemoved(rowId, refreshRows, timeoutMs = COLLECTION_TASK_POLL_TIMEOUT) {
  const startedAt = Date.now();

  while (Date.now() - startedAt < timeoutMs) {
    await wait(COLLECTION_TASK_POLL_INTERVAL);
    const rows = await refreshRows();

    if (!hasCollectionRow(rows, rowId)) {
      return rows;
    }
  }

  throw new Error('轻舟删除任务仍未完成，请稍后刷新后继续操作');
}

function isCollectionTaskBusyError(error) {
  const message = String(error?.message || error?.detail?.payload?.msg || '');
  return message.includes('请等待上一个') || message.includes('上一个操作');
}

async function postCollectionActionWithRetry(actionUrl, data, refreshRows) {
  const startedAt = Date.now();

  while (Date.now() - startedAt < COLLECTION_TASK_RETRY_TIMEOUT) {
    let rows = await refreshRows();

    if (hasBusyCollectionTask(rows)) {
      await waitForCollectionTaskIdle(refreshRows);
    }

    try {
      return await postAction(actionUrl, data);
    } catch (error) {
      if (!isCollectionTaskBusyError(error)) {
        throw error;
      }

      await wait(COLLECTION_TASK_POLL_INTERVAL);
      rows = await refreshRows();

      if (hasBusyCollectionTask(rows)) {
        await waitForCollectionTaskIdle(refreshRows);
      }
    }
  }

  throw new Error('轻舟仍在处理上一个任务，请稍后刷新后继续操作');
}

async function deleteCollectionRowInQueue(actionUrl, id, refreshRows) {
  const startedAt = Date.now();

  while (Date.now() - startedAt < COLLECTION_TASK_RETRY_TIMEOUT) {
    try {
      await postCollectionActionWithRetry(actionUrl, { id }, refreshRows);
      return waitForCollectionRowRemoved(id, refreshRows);
    } catch (error) {
      if (!isCollectionTaskBusyError(error)) {
        throw error;
      }

      await wait(COLLECTION_TASK_POLL_INTERVAL);
    }
  }

  throw new Error('轻舟仍在处理上一个任务，请稍后刷新后继续操作');
}

function normalizeMonitorPayload(payload) {
  const data = payload?.data || {};
  const nowLabel = new Date().toLocaleTimeString('zh-CN', { hour12: false });
  const cpuStats = data?.CpuStats;
  const memoryStats = data?.MemoryStats;
  const cpu = Number(
    typeof cpuStats === 'number' || typeof cpuStats === 'string'
      ? cpuStats
      : cpuStats?.Usage ?? cpuStats?.cpu ?? data?.cpu ?? 0,
  ) || 0;
  const memory = Number(
    typeof memoryStats === 'number' || typeof memoryStats === 'string'
      ? memoryStats
      : memoryStats?.Usage ?? memoryStats?.memory ?? data?.mem ?? data?.memory ?? 0,
  ) || 0;
  const networkStats = data?.NetworkStats;
  let network = Number(data?.network ?? 0) || 0;

  if (Array.isArray(networkStats) && networkStats.length) {
    const last = networkStats[networkStats.length - 1];
    network = Number(last?.[1] || 0);
  } else if (networkStats && typeof networkStats === 'object') {
    network = Number(networkStats.BytesSentPersec || 0);
  }

  return {
    cpu: [Math.round(cpu)],
    memory: [Math.round(memory)],
    network: [Math.round(network)],
    labels: [nowLabel],
    raw: data,
  };
}

function syncHostStatusFromState(payload) {
  const state = Array.isArray(payload?.data) ? payload.data : [];
  const stateCode = Number(state[0]);
  const status = state[1] || activePayload?.host?.status || '';

  if (activePayload?.host) {
    activePayload.host.status = status;
    if (stateCode === 2) {
      activePayload.host.powerState = 'running';
    } else if (stateCode === 3) {
      activePayload.host.powerState = 'stopped';
    }
    activePayload.host.state = Number.isFinite(stateCode) ? stateCode : activePayload.host.state;
  }

  return { state: stateCode, status, powerState: activePayload?.host?.powerState };
}

async function getHomePayload() {
  const bridgeConfig = getBridgeConfig();
  const embeddedPayload = readEmbeddedPayload();

  if (embeddedPayload && Object.keys(embeddedPayload).length > 0) {
    activePayload = embeddedPayload;
    return cloneData(activePayload);
  }

  if (bridgeConfig.embeddedJsonUrl) {
    activePayload = await fetchEmbeddedPayload();
    return cloneData(activePayload);
  }

  return {};
}

async function refreshFromAction(actionUrl) {
  const payload = await requestJson(resolveActionUrl(actionUrl));
  return extractLayuiRows(payload);
}

async function postAction(actionUrl, data = {}) {
  const payload = await requestJson(resolveActionUrl(actionUrl), {
    method: 'POST',
    data: {
      hostid: getHostId(),
      ...data,
    },
  });

  return assertThinkSuccess(payload);
}

async function refreshPortMappings() {
  return syncPageRows('port', await refreshFromAction(getActions().port?.list));
}

async function createPortMapping(input) {
  await postAction(getActions().port?.add, input);
  return refreshPortMappings();
}

async function deletePortMappings(ids) {
  for (const id of normalizeIds(ids)) {
    const row = findPageRow('port', id);
    await postAction(getActions().port?.remove, { ...(row || {}), id });
  }

  return refreshPortMappings();
}

async function generatePortMappingCandidate(keywords = '') {
  const candidates = await findPortMappingCandidates(keywords);
  return candidates[0];
}

async function findPortMappingCandidates(keywords = '') {
  const actions = getActions();
  const url = new URL(resolveActionUrl(actions.port?.find));

  if (keywords) {
    url.searchParams.set('keywords', keywords);
  }

  const payload = await requestJson(url.toString());
  const content = Array.isArray(payload?.content) ? payload.content : [payload?.content];
  return content
    .map((item) => Number(item))
    .filter((port) => Number.isInteger(port) && port > 0);
}

async function refreshState() {
  const payload = await requestJson(resolveActionUrl(getActions().state), {
    method: 'POST',
    data: { hostid: getHostId() },
  });
  assertThinkSuccess(payload);
  return syncHostStatusFromState(payload);
}

async function refreshMonitor() {
  const payload = await requestJson(resolveActionUrl(getActions().monitor), {
    method: 'POST',
    data: {
      hostid: getHostId(),
      vmid: getHostId(),
    },
  });
  assertThinkSuccess(payload);
  return normalizeMonitorPayload(payload);
}

async function powerAction(action) {
  const actionMap = {
    boot: getActions().power?.start,
    start: getActions().power?.start,
    shutdown: getActions().power?.close,
    close: getActions().power?.close,
    poweroff: getActions().power?.power,
    force: getActions().power?.power,
    reboot: getActions().power?.restart,
    restart: getActions().power?.restart,
  };

  await postAction(actionMap[action] || actionMap.restart);
  await refreshState();
  return cloneData(activePayload);
}

async function reinstallSystem(input = {}) {
  const templateId = findReinstallTemplateId(input.templateId || input.template_id || input.image || input.osName);
  await postAction(getActions().reinstall, {
    template_id: templateId,
    password: input.password,
  });
  return refreshEmbeddedPayload({ scope: 'system' });
}

async function setBootMode(input = {}) {
  const bootType = input.bootType || input.bios || 'IDE';

  if (String(bootType).toUpperCase() === 'IDE') {
    await postAction(getActions().iso?.unmount);
  } else if (input.isoPath || input.iso_path) {
    await postAction(getActions().iso?.mount, { iso_path: input.isoPath || input.iso_path });
  }

  await postAction(getActions().iso?.bios, { bios: bootType });
  return refreshEmbeddedPayload({ scope: 'system' });
}

async function refreshIsoOptions() {
  const payload = await requestJson(resolveActionUrl(getActions().iso?.list));
  assertThinkSuccess(payload);
  const options = Array.isArray(payload?.data) ? payload.data : [];

  if (activePayload?.pages?.iso) {
    activePayload.pages.iso.isoOptions = cloneData(options);
  }

  return options;
}

async function updateSystemPassword(password) {
  await postAction(getActions().password?.system, { password });
  return refreshEmbeddedPayload({ scope: 'system' });
}

async function updatePanelPassword(panelPassword) {
  await postAction(getActions().password?.panel, { panel_password: panelPassword });
  return refreshEmbeddedPayload({ scope: 'system' });
}

async function syncTime(enabled) {
  await postAction(getActions().power?.syncTime, { sync_time: enabled ? 1 : 2 });
  return refreshEmbeddedPayload({ scope: 'system' });
}

async function openVnc() {
  const payload = await requestJson(resolveActionUrl(getActions().vnc), {
    method: 'POST',
    data: { hostid: getHostId() },
  });
  assertQzSuccess(payload);
  return payload?.url || payload?.data?.url || '';
}

async function refreshSnapshots() {
  return syncPageRows('snapshot', await refreshFromAction(getActions().snapshot?.list));
}

async function createSnapshot() {
  await postAction(getActions().snapshot?.create);
  return refreshSnapshots();
}

async function restoreSnapshot(id) {
  await postAction(getActions().snapshot?.restore, { id });
  return refreshSnapshots();
}

async function deleteSnapshot(id) {
  return deleteCollectionRowInQueue(getActions().snapshot?.remove, id, refreshSnapshots);
}

async function deleteSnapshots(ids) {
  const normalizedIds = normalizeIds(ids);
  let rows = [];

  for (let index = 0; index < normalizedIds.length; index += 1) {
    rows = await deleteCollectionRowInQueue(getActions().snapshot?.remove, normalizedIds[index], refreshSnapshots);
  }

  return rows.length ? rows : refreshSnapshots();
}

async function refreshBackups() {
  return syncPageRows('backup', await refreshFromAction(getActions().backup?.list));
}

async function createBackup() {
  await postAction(getActions().backup?.create);
  return refreshBackups();
}

async function restoreBackup(id) {
  await postAction(getActions().backup?.restore, { id });
  return refreshBackups();
}

async function deleteBackup(id) {
  return deleteCollectionRowInQueue(getActions().backup?.remove, id, refreshBackups);
}

async function deleteBackups(ids) {
  const normalizedIds = normalizeIds(ids);
  let rows = [];

  for (let index = 0; index < normalizedIds.length; index += 1) {
    rows = await deleteCollectionRowInQueue(getActions().backup?.remove, normalizedIds[index], refreshBackups);
  }

  return rows.length ? rows : refreshBackups();
}

async function refreshFirewallRules() {
  return syncPageRows('strategy', await refreshFromAction(getActions().firewall?.list));
}

async function createFirewallRule(input) {
  await postAction(getActions().firewall?.add, input);
  return refreshFirewallRules();
}

async function deleteFirewallRule(id) {
  const row = findPageRow('strategy', id);
  await postAction(getActions().firewall?.remove, { ...(row || {}), id });
  return refreshFirewallRules();
}

async function deleteFirewallRules(ids) {
  for (const id of normalizeIds(ids)) {
    await deleteFirewallRule(id);
  }

  return refreshFirewallRules();
}

async function refreshDomainWhitelist() {
  return syncPageRows('site', await refreshFromAction(getActions().domain?.list));
}

async function createDomainWhitelist(input) {
  await postAction(getActions().domain?.add, input);
  return refreshDomainWhitelist();
}

async function deleteDomainWhitelist(id) {
  const row = findPageRow('site', id);
  await postAction(getActions().domain?.remove, { ...(row || {}), id });
  return refreshDomainWhitelist();
}

async function deleteDomainWhitelists(ids) {
  for (const id of normalizeIds(ids)) {
    await deleteDomainWhitelist(id);
  }

  return refreshDomainWhitelist();
}

async function refreshEmbeddedPayload(options = {}) {
  const payload = await fetchEmbeddedPayload(options);
  if (payload && !hasMonitorSamples(payload.monitors)) {
    delete payload.monitors;
  }
  activePayload = options.scope || options.fields ? mergePayload(activePayload || {}, payload || {}) : payload;
  return cloneData(activePayload);
}

export const qzEcsAdapter = {
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
    refreshIsoOptions,
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
