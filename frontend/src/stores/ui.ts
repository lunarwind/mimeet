import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUiStore = defineStore('ui', () => {
  const isLoading = ref(false)
  const toast = ref<{
    message: string
    type: 'success' | 'error' | 'warning' | 'info'
    visible: boolean
  }>({
    message: '',
    type: 'info',
    visible: false,
  })

  function showLoading() {
    isLoading.value = true
  }

  function hideLoading() {
    isLoading.value = false
  }

  function showToast(
    message: string,
    type: 'success' | 'error' | 'warning' | 'info' = 'info',
    duration = 3000,
  ) {
    toast.value = { message, type, visible: true }
    setTimeout(() => {
      toast.value.visible = false
    }, duration)
  }

  return { isLoading, toast, showLoading, hideLoading, showToast }
})
