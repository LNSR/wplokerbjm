import { ref } from 'vue'

export function useAsyncState() {
  const loading = ref(false)
  const error = ref<string | null>(null)

  function setLoading(value: boolean) {
    loading.value = value
  }

  function setError(message: string | null) {
    error.value = message
  }

  function resetError() {
    error.value = null
  }

  return {
    loading,
    error,
    setLoading,
    setError,
    resetError,
  }
}