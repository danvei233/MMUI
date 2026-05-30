import { message } from 'ant-design-vue';

const DEFAULT_RDP_PORT = 3389;
const DEFAULT_SSH_PORT = 22;

export function getClientOS() {
  const ua = navigator.userAgent || '';

  if (/Android/i.test(ua)) return 'Android';
  if (/iPhone|iPad|iPod/i.test(ua)) return 'iOS';
  if (/Windows/i.test(ua)) return 'Windows';
  if (/Macintosh|Mac OS X/i.test(ua)) return 'MacOS';
  if (/Linux/i.test(ua)) return 'Linux';
  return 'Unknown';
}

export function isMobileClient() {
  const ua = navigator.userAgent || '';
  return /Android|iPhone|iPad|iPod|Mobile/i.test(ua) || window.matchMedia('(max-width: 768px)').matches;
}

export function isWindowsHost(host) {
  const osText = `${host?.osName || ''} ${host?.osType || ''}`.toLowerCase();
  return osText.includes('windows') || osText.includes('win');
}

export function getRemoteUser(host) {
  return isWindowsHost(host) ? 'Administrator' : 'root';
}

export function normalizeRemoteLoginMethod(item, host) {
  if (item?.key !== 'rdp' && item?.key !== 'ssh') {
    return item;
  }

  if (isWindowsHost(host)) {
    return {
      ...item,
      key: 'rdp',
      name: 'RDP',
      icon: 'rdp',
      description: 'Windows 设备下载一键登录文件；手机端唤起 Remote App；其他设备下载 RDP 文件并复制密码。',
      actionLabel: getRemoteActionLabel(host),
      secondaryActionLabel: '',
      downloadUrl: '',
    };
  }

  return {
    ...item,
    key: 'ssh',
    name: 'SSH',
    icon: 'ssh',
    description: 'Windows 设备下载 SSH 登录脚本；手机端和其他设备尝试唤起本地 SSH 客户端。',
    actionLabel: getRemoteActionLabel(host),
    secondaryActionLabel: '',
    downloadUrl: '',
  };
}

export function getRemoteActionLabel(host) {
  const clientOS = getClientOS();

  if (clientOS === 'Windows') {
    return '下载文件';
  }

  if (isWindowsHost(host) && !isMobileClient()) {
    return '下载文件';
  }

  return '唤起远程';
}

function normalizeAddress(value) {
  const text = String(value || '').trim();
  return text && text !== '-' ? text : '';
}

function splitHostPort(address, defaultPort) {
  const ipv6Match = address.match(/^\[([^\]]+)\](?::(\d+))?$/);
  if (ipv6Match) {
    return {
      host: ipv6Match[1],
      port: ipv6Match[2] || String(defaultPort),
      displayHost: `[${ipv6Match[1]}]`,
    };
  }

  const parts = address.split(':');
  if (parts.length === 2 && /^\d+$/.test(parts[1])) {
    return { host: parts[0], port: parts[1], displayHost: parts[0] };
  }

  return { host: address, port: String(defaultPort), displayHost: address };
}

function getCredentialTarget(address) {
  return splitHostPort(address, DEFAULT_RDP_PORT).host;
}

function getConnectionTarget(address, defaultPort) {
  const { displayHost, port } = splitHostPort(address, defaultPort);
  return `${displayHost}:${port}`;
}

function sanitizeDownloadFileName(value) {
  return String(value || 'server')
    .trim()
    .replace(/[<>:"/\\|?*\x00-\x1f]/g, '_')
    .replace(/\s+/g, ' ')
    || 'server';
}

function downloadTextFile(fileName, content, mime) {
  const blob = new Blob([content], { type: mime });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = fileName;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

async function copyToClipboard(value, label) {
  const text = String(value || '').trim();
  if (!text || text === '-') {
    return false;
  }

  try {
    await navigator.clipboard.writeText(text);
  } catch {
    const input = document.createElement('input');
    input.value = text;
    input.style.position = 'fixed';
    input.style.left = '-9999px';
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
  }

  if (label) {
    message.success(`已复制${label}`);
  }
  return true;
}

function downloadRdpBat({ address, password, user, name }) {
  const credentialTarget = getCredentialTarget(address);
  const connectionTarget = getConnectionTarget(address, DEFAULT_RDP_PORT);
  const content = [
    '@echo off',
    `cmdkey /generic:TERMSRV/${credentialTarget} /user:${user} /pass:${password}`,
    `mstsc /v:${connectionTarget}`,
    '',
  ].join('\r\n');

  downloadTextFile(`${sanitizeDownloadFileName(name)}.bat`, content, 'application/x-msdos-program;charset=utf-8');
  message.success('已生成一键登录文件');
}

async function downloadRdpFile({ address, password, user, name }) {
  const connectionTarget = getConnectionTarget(address, DEFAULT_RDP_PORT);
  const content = [
    `full address:s:${connectionTarget}`,
    `username:s:${user}`,
    'prompt for credentials:i:1',
    '',
  ].join('\r\n');

  await copyToClipboard(password, '系统密码');
  downloadTextFile(`${sanitizeDownloadFileName(name)}.rdp`, content, 'application/rdp;charset=utf-8');
  message.success('已生成 RDP 文件');
}

async function downloadSshBat({ address, password, user, name }) {
  const { displayHost, port } = splitHostPort(address, DEFAULT_SSH_PORT);
  const content = [
    '@echo off',
    `ssh ${user}@${displayHost} -p ${port}`,
    `plink -ssh ${user}@${displayHost} -P ${port} -pw ${password}`,
    'pause',
    '',
  ].join('\r\n');

  await copyToClipboard(password, '系统密码');
  downloadTextFile(`${sanitizeDownloadFileName(name)}.bat`, content, 'application/x-msdos-program;charset=utf-8');
  message.success('已生成 SSH 登录文件');
}

function buildRdpLink({ address, password, user }) {
  const connectionTarget = getConnectionTarget(address, DEFAULT_RDP_PORT);
  return `rdp:full%20address=s:${encodeURIComponent(connectionTarget)}&username=s:${encodeURIComponent(user)}&password=s:${encodeURIComponent(password)}`;
}

function buildSshLink({ address, user }) {
  const { displayHost, port } = splitHostPort(address, DEFAULT_SSH_PORT);
  return `ssh://${encodeURIComponent(user)}@${displayHost}:${port}`;
}

function invokeExternalUrl(url, { fallback, failureMessage, delay = 1500 } = {}) {
  let settled = false;
  let timer = null;

  return new Promise((resolve) => {
    const cleanup = (result) => {
      if (settled) return;
      settled = true;
      window.removeEventListener('visibilitychange', handleVisibility);
      window.removeEventListener('blur', handleBlur, true);
      window.removeEventListener('pagehide', handleBlur, true);
      if (timer) {
        window.clearTimeout(timer);
      }
      resolve(result);
    };

    const handleVisibility = () => {
      if (document.hidden) {
        cleanup(true);
      }
    };
    const handleBlur = () => cleanup(true);

    window.addEventListener('visibilitychange', handleVisibility);
    window.addEventListener('blur', handleBlur, true);
    window.addEventListener('pagehide', handleBlur, true);
    window.location.href = url;

    timer = window.setTimeout(async () => {
      if (settled) return;
      if (failureMessage) {
        message.info(failureMessage);
      }
      if (typeof fallback === 'function') {
        await fallback();
      }
      cleanup(false);
    }, delay);
  });
}

export async function triggerHostRemoteAccess(host) {
  const address = normalizeAddress(host?.remoteAddress);
  const password = String(host?.systemPassword || '').trim();
  const user = getRemoteUser(host);
  const name = host?.name || 'server';

  if (!address) {
    message.warning('暂无可用的远程地址');
    return;
  }
  if (!password || password === '-') {
    message.warning('暂无可用的系统密码');
    return;
  }

  const clientOS = getClientOS();
  const mobile = isMobileClient();

  if (isWindowsHost(host)) {
    if (clientOS === 'Windows') {
      downloadRdpBat({ address, password, user, name });
      return;
    }

    if (mobile) {
      await invokeExternalUrl(buildRdpLink({ address, password, user }), {
        failureMessage: '打开 Remote App 失败，已改为下载 RDP 文件。',
        fallback: () => downloadRdpFile({ address, password, user, name }),
      });
      return;
    }

    await downloadRdpFile({ address, password, user, name });
    return;
  }

  if (clientOS === 'Windows') {
    await downloadSshBat({ address, password, user, name });
    return;
  }

  await copyToClipboard(password, '系统密码');
  await invokeExternalUrl(buildSshLink({ address, user }), {
    failureMessage: '打开 SSH 客户端失败，请确认本机已安装可处理 ssh:// 的远程客户端。',
  });
}
