import { create } from 'zustand'
import type { AdminUser, AdminRole } from '../types/admin'

interface AuthState {
  user: AdminUser | null
  isLoggedIn: boolean
  login: (user: AdminUser, token?: string) => void
  logout: () => void
  hasPermission: (requiredRoles: AdminRole[]) => boolean
}

function loadUser(): AdminUser | null {
  try {
    const raw = sessionStorage.getItem('admin_user')
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

const savedUser = loadUser()

export const useAuthStore = create<AuthState>((set, get) => ({
  user: savedUser,
  isLoggedIn: !!savedUser,

  login: (user: AdminUser, token?: string) => {
    sessionStorage.setItem('admin_user', JSON.stringify(user))
    sessionStorage.setItem('admin_token', token || '')
    // Migration: clear old localStorage keys
    localStorage.removeItem('admin_user')
    localStorage.removeItem('admin_token')
    set({ user, isLoggedIn: true })
  },

  logout: () => {
    sessionStorage.removeItem('admin_user')
    sessionStorage.removeItem('admin_token')
    localStorage.removeItem('admin_user')
    localStorage.removeItem('admin_token')
    set({ user: null, isLoggedIn: false })
  },

  hasPermission: (requiredRoles: AdminRole[]) => {
    const { user } = get()
    if (!user) return false
    return requiredRoles.includes(user.role)
  },
}))
