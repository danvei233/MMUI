export class MmuiApiError extends Error {
  constructor(message, detail = {}) {
    super(message);
    this.name = 'MmuiApiError';
    this.detail = detail;
  }
}

function buildFormBody(data = {}) {
  const body = new URLSearchParams();

  Object.entries(data).forEach(([key, value]) => {
    if (value === undefined || value === null) {
      return;
    }

    body.set(key, String(value));
  });

  return body;
}

async function parseJsonResponse(response) {
  const contentType = response.headers.get('content-type') || '';

  if (!contentType.includes('application/json')) {
    const text = await response.text();
    throw new MmuiApiError('接口未返回JSON', {
      status: response.status,
      body: text.slice(0, 300),
    });
  }

  const text = await response.text();
  const normalizedText = text.replace(/^\uFEFF/, '').trim();

  try {
    return JSON.parse(normalizedText);
  } catch (error) {
    throw new MmuiApiError('接口返回的JSON无法解析', {
      status: response.status,
      body: text.slice(0, 300),
      cause: error,
    });
  }
}

export async function requestJson(url, options = {}) {
  const method = options.method || (options.data ? 'POST' : 'GET');
  const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(options.headers || {}),
  };
  const requestOptions = {
    method,
    headers,
    credentials: 'same-origin',
  };

  if (options.data) {
    headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
    requestOptions.body = buildFormBody(options.data);
  }

  const response = await fetch(url, requestOptions);
  const payload = await parseJsonResponse(response);

  if (!response.ok) {
    throw new MmuiApiError(`接口请求失败：${response.status}`, { status: response.status, payload });
  }

  return payload;
}

export function assertThinkSuccess(payload) {
  if (Number(payload?.code) !== 1 && Number(payload?.code) !== 200) {
    throw new MmuiApiError(payload?.msg || '操作失败', { payload });
  }

  return payload;
}

export function assertQzSuccess(payload) {
  if (Number(payload?.code) === 0 && payload?.type === 'success') {
    return payload;
  }

  return assertThinkSuccess(payload);
}

export function extractLayuiRows(payload) {
  if (Array.isArray(payload?.data)) {
    return payload.data;
  }

  if (Array.isArray(payload?.content)) {
    return payload.content;
  }

  return [];
}
