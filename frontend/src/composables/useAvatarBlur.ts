import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

export function useAvatarBlur(targetUserId?: number) {
  const auth = useAuthStore()

  const shouldBlur = computed(() => {
    if (targetUserId && auth.user?.id === targetUserId) return false
    const level = auth.user?.membership_level ?? 0
    return level < 1.5
  })

  const blurStyle = computed(() =>
    shouldBlur.value ? { filter: 'blur(8px)' } : {},
  )

  return { shouldBlur, blurStyle }
}
