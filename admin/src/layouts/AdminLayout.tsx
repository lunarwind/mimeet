import { useState } from 'react'
import { Outlet, useNavigate, useLocation } from 'react-router-dom'
import { Layout, Menu, Avatar, Button, Tag, Typography, Result } from 'antd'
import {
  TeamOutlined,
  FileTextOutlined,
  DollarOutlined,
  SettingOutlined,
  LogoutOutlined,
  MenuFoldOutlined,
  MenuUnfoldOutlined,
  DashboardOutlined,
  AuditOutlined,
  MessageOutlined,
  SafetyCertificateOutlined,
  NotificationOutlined,
  HistoryOutlined,
  LinkOutlined,
  SoundOutlined,
  GiftOutlined,
} from '@ant-design/icons'
import { useAuthStore } from '../stores/authStore'
import MiMeetLogo from '../components/MiMeetLogo'
import type { AdminRole } from '../types/admin'

const { Header, Sider, Content } = Layout
const { Text } = Typography

interface MenuItem {
  key: string
  icon: React.ReactNode
  label: string
  path: string
  roles: AdminRole[]
}

const MENU_ITEMS: MenuItem[] = [
  { key: 'dashboard', icon: <DashboardOutlined />, label: '儀表板', path: '/dashboard', roles: ['super_admin', 'admin', 'cs'] },
  { key: 'members', icon: <TeamOutlined />, label: '會員管理', path: '/members', roles: ['super_admin', 'admin'] },
  { key: 'chat-logs', icon: <MessageOutlined />, label: '聊天記錄', path: '/chat-logs', roles: ['super_admin', 'admin'] },
  { key: 'tickets', icon: <FileTextOutlined />, label: 'Ticket 回報', path: '/tickets', roles: ['super_admin', 'admin', 'cs'] },
  { key: 'payments', icon: <DollarOutlined />, label: '支付記錄', path: '/payments', roles: ['super_admin', 'admin'] },
  { key: 'verifications', icon: <SafetyCertificateOutlined />, label: '驗證審核', path: '/verifications', roles: ['super_admin', 'admin'] },
  // 信用卡驗證管理已整合至「💲 支付記錄」頁（Step 9）
  { key: 'broadcasts', icon: <NotificationOutlined />, label: '廣播訊息', path: '/broadcasts', roles: ['super_admin', 'admin'] },
  { key: 'seo', icon: <LinkOutlined />, label: 'SEO Meta 管理', path: '/seo', roles: ['super_admin', 'admin'] },
  { key: 'announcements', icon: <SoundOutlined />, label: '系統公告', path: '/announcements', roles: ['super_admin', 'admin'] },
  { key: 'plans', icon: <DollarOutlined />, label: '💰 方案設定', path: '/plans', roles: ['super_admin'] },
  { key: 'point-transactions', icon: <GiftOutlined />, label: '💎 點數交易', path: '/point-transactions', roles: ['super_admin', 'admin'] },
  { key: 'settings', icon: <SettingOutlined />, label: '系統設定', path: '/settings/system', roles: ['super_admin'] },
  { key: 'logs', icon: <AuditOutlined />, label: '操作日誌', path: '/logs', roles: ['super_admin'] },
  { key: 'user-activity', icon: <HistoryOutlined />, label: '用戶活動日誌', path: '/user-activity-logs', roles: ['super_admin'] },
]

const ROLE_COLORS: Record<AdminRole, string> = {
  super_admin: 'red',
  admin: 'blue',
  cs: 'green',
}

const ROLE_LABELS: Record<AdminRole, string> = {
  super_admin: '超級管理員',
  admin: '一般管理員',
  cs: '客服人員',
}

export default function AdminLayout() {
  const [collapsed, setCollapsed] = useState(false)
  const navigate = useNavigate()
  const location = useLocation()
  const user = useAuthStore((s) => s.user)
  const logout = useAuthStore((s) => s.logout)
  const hasPermission = useAuthStore((s) => s.hasPermission)

  const visibleItems = MENU_ITEMS.filter((item) => hasPermission(item.roles))

  const selectedKey = visibleItems.find((item) => location.pathname.startsWith(item.path))?.key || ''

  const handleLogout = () => {
    logout()
    navigate('/login')
  }

  // Check if current route is allowed
  const currentMenuItem = MENU_ITEMS.find((item) => location.pathname.startsWith(item.path))
  if (currentMenuItem && !hasPermission(currentMenuItem.roles)) {
    return (
      <Layout style={{ minHeight: '100vh' }}>
        <Content style={{ display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <Result status="403" title="無權限" subTitle="您沒有權限存取此頁面" extra={<Button onClick={() => navigate('/tickets')}>返回</Button>} />
        </Content>
      </Layout>
    )
  }

  const siderWidth = collapsed ? 80 : 220

  return (
    <div style={{ display: 'flex', height: '100vh', overflow: 'hidden' }}>
      {/* Sidebar: fixed, full height, independently scrollable */}
      <Sider
        trigger={null}
        collapsible
        collapsed={collapsed}
        width={220}
        style={{
          background: '#001529',
          position: 'fixed',
          left: 0,
          top: 0,
          height: '100vh',
          overflowY: 'auto',
          zIndex: 30,
        }}
      >
        <div style={{ height: 64, display: 'flex', alignItems: 'center', justifyContent: 'center', borderBottom: '1px solid rgba(255,255,255,0.1)' }}>
          {!collapsed ? (
            <span style={{ display: 'inline-flex', alignItems: 'baseline' }}>
              <MiMeetLogo variant="dark" size="sm" />
              <span style={{ color: '#fff', fontSize: 13, marginLeft: 4, fontWeight: 400 }}>Admin</span>
            </span>
          ) : (
            <Text strong style={{ color: '#F0294E', fontSize: 20 }}>M</Text>
          )}
        </div>
        <Menu
          theme="dark"
          mode="inline"
          selectedKeys={[selectedKey]}
          onClick={({ key }) => {
            const item = visibleItems.find((i) => i.key === key)
            if (item) navigate(item.path)
          }}
          items={visibleItems.map((item) => ({
            key: item.key,
            icon: item.icon,
            label: item.label,
          }))}
        />
      </Sider>

      {/* Right side: offset by sidebar width */}
      <div style={{
        flex: 1,
        display: 'flex',
        flexDirection: 'column',
        marginLeft: siderWidth,
        height: '100vh',
        overflow: 'hidden',
        transition: 'margin-left 0.2s',
      }}>
        {/* Header: sticky at top of content area */}
        <Header
          style={{
            background: '#fff',
            padding: '0 24px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            borderBottom: '1px solid #f0f0f0',
            height: 64,
            flexShrink: 0,
          }}
        >
          <Button
            type="text"
            icon={collapsed ? <MenuUnfoldOutlined /> : <MenuFoldOutlined />}
            onClick={() => setCollapsed(!collapsed)}
          />
          <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <Avatar style={{ background: '#F0294E' }}>{user?.name?.[0] || 'A'}</Avatar>
            <Text strong>{user?.name}</Text>
            {user?.role && <Tag color={ROLE_COLORS[user.role]}>{ROLE_LABELS[user.role]}</Tag>}
            <Button type="text" icon={<LogoutOutlined />} onClick={handleLogout}>
              登出
            </Button>
          </div>
        </Header>

        {/* Main content: only scrollable area */}
        <Content style={{
          flex: 1,
          overflowY: 'auto',
          padding: 24,
          background: '#f5f5f5',
        }}>
          <div style={{ padding: 24, background: '#fff', borderRadius: 8, minHeight: '100%' }}>
            <Outlet />
          </div>
        </Content>
      </div>
    </div>
  )
}
