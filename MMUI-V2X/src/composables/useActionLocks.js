import { ref } from 'vue';

export function useActionLocks() {
  const actionLocks = ref({});

  function isActionLoading(key) {
    return Boolean(actionLocks.value[key]);
  }

  async function runWithActionLoading(key, task) {
    if (isActionLoading(key)) {
      return undefined;
    }

    actionLocks.value = {
      ...actionLocks.value,
      [key]: true,
    };

    try {
      return await task();
    } finally {
      const nextLocks = { ...actionLocks.value };
      delete nextLocks[key];
      actionLocks.value = nextLocks;
    }
  }

  return {
    actionLocks,
    isActionLoading,
    runWithActionLoading,
  };
}
