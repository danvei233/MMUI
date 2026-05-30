const PLACEHOLDER_PREFIX = '__MMUI_VUE_';
const EMBED_ID = 'mmui-vue-page-props';

function isPlaceholder(value) {
  return typeof value === 'string' && value.trim().startsWith(PLACEHOLDER_PREFIX);
}

function safeParse(raw) {
  if (!raw || isPlaceholder(raw)) {
    return null;
  }

  try {
    return JSON.parse(raw);
  } catch (error) {
    console.warn('Failed to parse embedded MMUI payload.', error);
    return null;
  }
}

export function getBridgeConfig() {
  const config = window.__MMUI_VUE_CONFIG__ || {};

  return {
    embeddedJsonUrl: isPlaceholder(config.embeddedJsonUrl) ? '' : config.embeddedJsonUrl || '',
    requestKey: isPlaceholder(config.requestKey) ? 'mmui_vue_json' : config.requestKey || 'mmui_vue_json',
    useMock: config.useMock !== false,
    adapter: isPlaceholder(config.adapter) ? '' : config.adapter || '',
  };
}

export function readEmbeddedPayload() {
  const tag = document.getElementById(EMBED_ID);
  return safeParse(tag?.textContent?.trim());
}

export async function refreshEmbeddedPayload(options = {}) {
  const { embeddedJsonUrl } = getBridgeConfig();

  if (!embeddedJsonUrl) {
    return null;
  }

  const targetUrl = new URL(embeddedJsonUrl, window.location.href);
  targetUrl.searchParams.set('_mmui_t', String(Date.now()));
  if (options.scope) {
    targetUrl.searchParams.set('mmui_vue_scope', options.scope);
  }
  if (options.fields) {
    const fields = Array.isArray(options.fields) ? options.fields.join(',') : options.fields;
    targetUrl.searchParams.set('mmui_vue_fields', fields);
  }

  const response = await fetch(targetUrl.toString(), {
    headers: {
      Accept: 'application/json',
    },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch embedded payload: ${response.status}`);
  }

  return response.json();
}
