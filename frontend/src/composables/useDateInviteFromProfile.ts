/**
 * useDateInviteFromProfile.ts
 * 從個人資料頁發起約會邀請的 composable
 */
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import client from '@/api/client'

const USE_MOCK = import.meta.env.DEV

interface DateInviteForm {
  date: string
  time: string
  locationName: string
}

export function useDateInviteFromProfile(profileUserId: () => number, profileMemberLevel: () => number) {
  const authStore = useAuthStore()
  const isLoading = ref(false)
  const showBottomSheet = ref(false)
  const form = ref<DateInviteForm>({ date: '', time: '', locationName: '' })
  const successMessage = ref('')

  const isEligibleToInvite = computed(() => {
    const myLevel = authStore.membershipLevel
    const theirLevel = profileMemberLevel()
    const isSelf = authStore.user?.id === profileUserId()
    return myLevel >= 3 && theirLevel >= 1 && !isSelf
  })

  async function handleInviteClick() {
    isLoading.value = true
    try {
      // Step 1: Create or get existing conversation
      if (USE_MOCK) {
        await new Promise(r => setTimeout(r, 300))
      } else {
        await client.post('/chats', { user_id: profileUserId() })
      }

      // Step 2: Open bottom sheet
      form.value = { date: '', time: '', locationName: '' }
      showBottomSheet.value = true
    } catch {
      // Silently open bottom sheet even if chat creation fails
      showBottomSheet.value = true
    } finally {
      isLoading.value = false
    }
  }

  async function handleSubmit(nickname: string) {
    if (!form.value.date || !form.value.time) return
    isLoading.value = true
    try {
      const dateTime = `${form.value.date}T${form.value.time}:00`

      if (USE_MOCK) {
        await new Promise(r => setTimeout(r, 500))
      } else {
        await client.post('/dates', {
          invitee_id: profileUserId(),
          date_time: dateTime,
          location_name: form.value.locationName || null,
          latitude: null,
          longitude: null,
        })
      }

      showBottomSheet.value = false
      successMessage.value = `約會邀請已送出！等待 ${nickname} 確認`

      // Auto-clear toast after 3s
      setTimeout(() => { successMessage.value = '' }, 3000)
    } catch {
      // Keep bottom sheet open on error
    } finally {
      isLoading.value = false
    }
  }

  function handleCancel() {
    showBottomSheet.value = false
  }

  return {
    isEligibleToInvite,
    isLoading,
    showBottomSheet,
    form,
    successMessage,
    handleInviteClick,
    handleSubmit,
    handleCancel,
  }
}
