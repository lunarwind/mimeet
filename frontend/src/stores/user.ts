import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface User {
  id: number
  uuid: string
  nickname: string
  gender: 'male' | 'female'
  membershipLevel: number
  isPaid: boolean
  creditScore: number
  status: 'active' | 'suspended' | 'deleted'
  avatarUrl: string | null
}

export const useUserStore = defineStore('user', () => {
  const currentUser = ref<User | null>(null)
  const memberLevel = ref<number>(parseInt(localStorage.getItem('member_level') ?? '0'))
  const isSuspended = ref<boolean>(localStorage.getItem('is_suspended') === 'true')

  function setUser(user: User) {
    currentUser.value = user
    memberLevel.value = user.membershipLevel
    isSuspended.value = user.status === 'suspended'
    localStorage.setItem('member_level', String(user.membershipLevel))
    localStorage.setItem('is_suspended', String(user.status === 'suspended'))
  }

  function clearUser() {
    currentUser.value = null
    memberLevel.value = 0
    isSuspended.value = false
  }

  return { currentUser, memberLevel, isSuspended, setUser, clearUser }
})
