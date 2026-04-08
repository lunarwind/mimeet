import { create } from 'zustand'
import type { AdminUser, AdminRole } from '../types/admin'

interface AuthState {
  user: AdminUser | null
  isLoggedIn: boolean
  login: (user: AdminUser) => void
  logout: () => void
  hasPermission: (requiredRoles: AdminRole[]) => boolean
}

function loadUser(): AdminUser | null {
  try {
    const raw = localStorage.getItem('admin_user')
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

const savedUser = loadUser()

export const useAuthStore = create<AuthState>((set, get) => ({
  user: savedUser,
  isLoggedIn: !!savedUser,

  login: (user: AdminUser) => {
    localStorage.setItem('admin_user', JSON.stringify(user))
    localStorage.setItem('admin_token', 'admin-mock-token')
    set({ user, isLoggedIn: true })
  },

  logout: () => {
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
