import { Routes, Route, Navigate } from 'react-router-dom'
import AdminLayout from './layouts/AdminLayout'
import LoginPage from './pages/auth/LoginPage'
import MembersPage from './pages/members/MembersPage'
import MemberDetailPage from './pages/members/MemberDetailPage'
import TicketsPage from './pages/tickets/TicketsPage'
import PaymentsPage from './pages/payments/PaymentsPage'
import SystemSettingsPage from './pages/settings/SystemSettingsPage'
import { useAuthStore } from './stores/authStore'

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const isLoggedIn = useAuthStore((s) => s.isLoggedIn)
  if (!isLoggedIn) return <Navigate to="/admin/login" replace />
  return <>{children}</>
}

export default function App() {
  return (
    <Routes>
      <Route path="/admin/login" element={<LoginPage />} />
      <Route
        path="/admin"
        element={
          <ProtectedRoute>
            <AdminLayout />
          </ProtectedRoute>
        }
      >
        <Route index element={<Navigate to="/admin/members" replace />} />
        <Route path="members" element={<MembersPage />} />
        <Route path="members/:id" element={<MemberDetailPage />} />
        <Route path="tickets" element={<TicketsPage />} />
        <Route path="payments" element={<PaymentsPage />} />
        <Route path="settings/system" element={<SystemSettingsPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/admin/login" replace />} />
    </Routes>
  )
}
