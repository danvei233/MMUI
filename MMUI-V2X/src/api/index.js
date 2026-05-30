import { readEmbeddedPayload, getBridgeConfig } from '@/config/phpBridge';
import { mockAdapter } from '@/api/adapters/mockAdapter';
import { qzEcsAdapter } from '@/api/adapters/qzEcsAdapter';

function selectAdapter() {
  const config = getBridgeConfig();
  const embeddedPayload = readEmbeddedPayload();

  if (config.adapter === 'qz-ecs') {
    return qzEcsAdapter;
  }

  if (embeddedPayload?.actions || config.useMock === false) {
    return qzEcsAdapter;
  }

  return mockAdapter;
}

export const mmuiApi = selectAdapter();
