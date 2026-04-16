import { lazy, Suspense, useCallback } from 'react'
import { Routes, Route, Navigate, useNavigate } from 'react-router-dom'
import AdminLayout from './layouts/AdminLayout'
import LoginPage from './pages/auth/LoginPage'
const DashboardPage = lazy(() => import('./pages/dashboard/DashboardPage'))
import MembersPage from './pages/members/MembersPage'
import MemberDetailPage from './pages/members/MemberDetailPage'
import TicketsPage from './pages/tickets/TicketsPage'
import PaymentsPage from './pages/payments/PaymentsPage'
import SystemSettingsPage from './pages/settings/SystemSettingsPage'
import ActivityLogsPage from './pages/logs/ActivityLogsPage'
import UserActivityLogsPage from './pages/logs/UserActivityLogsPage'
import ChatLogsPage from './pages/chat-logs/ChatLogsPage'
import VerificationsPage from './pages/verifications/VerificationsPage'
import BroadcastsPage from './pages/broadcasts/BroadcastsPage'
import SeoPage from './pages/seo/SeoPage'
import AnnouncementsPage from './pages/announcements/AnnouncementsPage'
import { useAuthStore } from './stores/authStore'
import { useIdleTimeout } from './hooks/useIdleTimeout'

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const isLoggedIn = useAuthStore((s) => s.isLoggedIn)
  const logout = useAuthStore((s) => s.logout)
  const navigate = useNavigate()

  const handleTimeout = useCallback(() => {
    logout()
    navigate('/login?reason=idle_timeout')
  }, [logout, navigate])

  useIdleTimeout(handleTimeout, isLoggedIn)

  if (!isLoggedIn) return <Navigate to="/login" replace />
  return <>{children}</>
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route
        path="/"
        element={
          <ProtectedRoute>
            <AdminLayout />
          </ProtectedRoute>
        }
      >
        <Route index element={<Navigate to="/dashboard" replace />} />
        <Route path="dashboard" element={<Suspense fallback={<div>Loading...</div>}><DashboardPage /></Suspense>} />
        <Route path="members" element={<MembersPage />} />
        <Route path="members/:id" element={<MemberDetailPage />} />
        <Route path="tickets" element={<TicketsPage />} />
        <Route path="payments" element={<PaymentsPage />} />
        <Route path="settings/system" element={<SystemSettingsPage />} />
        <Route path="chat-logs" element={<ChatLogsPage />} />
        <Route path="verifications" element={<VerificationsPage />} />
        <Route path="broadcasts" element={<BroadcastsPage />} />
        <Route path="seo" element={<SeoPage />} />
        <Route path="announcements" element={<AnnouncementsPage />} />
        <Route path="logs" element={<ActivityLogsPage />} />
        <Route path="user-activity-logs" element={<UserActivityLogsPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  )
}
