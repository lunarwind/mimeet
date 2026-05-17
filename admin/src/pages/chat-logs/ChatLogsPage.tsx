import { useState, useEffect, useCallback } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import {
  Tabs, Card, Input, Button, Table, Typography, Space, Result, Avatar, List, message, Alert, Tag,
} from 'antd'
import { SearchOutlined, DownloadOutlined, ReloadOutlined, UserOutlined } from '@ant-design/icons'
import { useAuthStore } from '../../stores/authStore'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title, Text } = Typography


interface SearchResult {
  message_id: number
  conversation_id: number
  sender: { id: number; nickname: string } | null
  receiver: { id: number; nickname: string } | null
  content: string | null
  type: string
  sent_at: string
  is_read: boolean
  is_recalled?: boolean
  recalled_at?: string | null
  is_content_visible?: boolean
}

interface ConversationMessage {
  id: number
  sender_id: number
  content: string | null
  type: string
  is_recalled: boolean
  recalled_at?: string | null
  is_content_visible?: boolean
  sent_at: string
  is_read: boolean
  read_at?: string | null
}

interface ConversationData {
  conversation_id: number
  user_a: { id: number; nickname: string; avatar_url?: string } | null
  user_b: { id: number; nickname: string; avatar_url?: string } | null
  messages: ConversationMessage[]
}

interface MemberChatEntry {
  conversation_id: number
  counterpart: { id: number; nickname: string; avatar_url?: string } | null
  total_messages: number
  last_message: {
    content: string | null
    sent_at: string
    is_recalled?: boolean
    is_content_visible?: boolean
  } | null
}

export default function ChatLogsPage() {
  const user = useAuthStore((s) => s.user)

  if (user?.role === 'cs') {
    return <Result status="403" title="權限不足" subTitle="此頁面僅限 super_admin 和 admin 查看" />
  }

  return <ChatLogsContent />
}

function ChatLogsContent() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const currentUser = useAuthStore((s) => s.user)
  const isSuperAdmin = currentUser?.role === 'super_admin'

  const urlTab = searchParams.get('tab')
  const urlUserA = searchParams.get('user_a') || ''
  const urlUserB = searchParams.get('user_b') || ''
  const urlUserId = searchParams.get('user_id') || ''

  const initialTab = urlTab === 'member' ? 'member' : (urlUserA && urlUserB) ? 'conversation' : 'search'
  const [activeTab, setActiveTab] = useState(initialTab)

  // ─── Tab 1: Search state ───
  const [keyword, setKeyword] = useState('')
  const [searchUserId, setSearchUserId] = useState('')
  const [searchResults, setSearchResults] = useState<SearchResult[]>([])
  const [searchTotal, setSearchTotal] = useState(0)
  const [searchLoading, setSearchLoading] = useState(false)
  const [searchPage, setSearchPage] = useState(1)

  // ─── Tab 2: Conversation state ───
  const [convUserA, setConvUserA] = useState(urlUserA)
  const [convUserB, setConvUserB] = useState(urlUserB)
  const [convData, setConvData] = useState<ConversationData | null>(null)
  const [convMessages, setConvMessages] = useState<ConversationMessage[]>([])
  const [convTotal, setConvTotal] = useState(0)
  const [convLoading, setConvLoading] = useState(false)
  const [convPage, setConvPage] = useState(1)

  // ─── Tab 3: Member state ───
  const [memberUserId, setMemberUserId] = useState(urlUserId)
  const [memberCounterpartId, setMemberCounterpartId] = useState('')
  const [memberKeyword, setMemberKeyword] = useState('')
  const [memberResults, setMemberResults] = useState<MemberChatEntry[]>([])
  const [memberTotal, setMemberTotal] = useState(0)
  const [memberLoading, setMemberLoading] = useState(false)
  const [memberPage, setMemberPage] = useState(1)
  const [memberExporting, setMemberExporting] = useState(false)

  // ─── Tab 1: Search ───
  const handleSearch = async (page = 1) => {
    if (keyword.length < 2) {
      message.warning('關鍵字至少需要 2 個字')
      return
    }
    setSearchLoading(true)
    try {
      const params: Record<string, string | number> = { keyword, page, per_page: 20 }
      if (searchUserId) params.user_id = searchUserId
      const res = await apiClient.get('/admin/chat-logs/search', { params })
      setSearchResults(res.data.data ?? [])
      setSearchTotal(res.data.meta?.total ?? 0)
      setSearchPage(page)
    } catch {
      setSearchResults([])
      setSearchTotal(0)
    }
    setSearchLoading(false)
  }

  // ─── Tab 2: Conversation ───
  const handleConversation = useCallback(async (page = 1) => {
    if (!convUserA || !convUserB) return
    setConvLoading(true)
    try {
      const res = await apiClient.get('/admin/chat-logs/conversations', {
        params: { user_a: convUserA, user_b: convUserB, page, per_page: 50 },
      })
      setConvData(res.data.data)
      setConvMessages(res.data.data?.messages ?? [])
      setConvTotal(res.data.meta?.total ?? 0)
      setConvPage(page)
    } catch {
      setConvData(null)
      setConvMessages([])
      setConvTotal(0)
    }
    setConvLoading(false)
  }, [convUserA, convUserB])

  const handleExport = async () => {
    if (!convUserA || !convUserB) return
    try {
      const res = await apiClient.get('/admin/chat-logs/export', {
        params: { user_a: convUserA, user_b: convUserB },
        responseType: 'blob',
      })
      const url = window.URL.createObjectURL(new Blob([res.data]))
      const a = document.createElement('a')
      a.href = url
      a.download = `chat_${convUserA}_${convUserB}_${dayjs().format('YYYYMMDD')}.csv`
      a.click()
      window.URL.revokeObjectURL(url)
      message.success('匯出完成')
    } catch {
      message.error('匯出失敗')
    }
  }

  // ─── Tab 3: Member chat ───
  const handleMemberSearch = useCallback(async (page = 1) => {
    if (!memberUserId) return
    setMemberLoading(true)
    try {
      const params: Record<string, string | number> = { page, per_page: 20 }
      if (memberCounterpartId) params.counterpart_id = memberCounterpartId
      if (memberKeyword) params.keyword = memberKeyword
      const res = await apiClient.get(`/admin/members/${memberUserId}/chat-logs`, { params })
      setMemberResults(res.data.data ?? [])
      setMemberTotal(res.data.meta?.total ?? res.data.data?.length ?? 0)
      setMemberPage(page)
    } catch {
      setMemberResults([])
      setMemberTotal(0)
    }
    setMemberLoading(false)
  }, [memberUserId, memberCounterpartId, memberKeyword])

  const handleMemberExport = async (counterpartId: string) => {
    if (!memberUserId || !counterpartId) return
    setMemberExporting(true)
    try {
      const res = await apiClient.get(`/admin/members/${memberUserId}/chat-logs/export`, {
        params: { counterpart_id: counterpartId, format: 'csv' },
        responseType: 'blob',
      })
      const url = window.URL.createObjectURL(new Blob([res.data]))
      const a = document.createElement('a')
      a.href = url
      a.download = `member_${memberUserId}_chat_${dayjs().format('YYYYMMDD')}.csv`
      a.click()
      window.URL.revokeObjectURL(url)
      message.success('匯出完成')
    } catch {
      message.error('匯出失敗')
    }
    setMemberExporting(false)
  }

  const viewConversation = (senderId: number, receiverId: number) => {
    setConvUserA(String(senderId))
    setConvUserB(String(receiverId))
    setActiveTab('conversation')
    setTimeout(() => handleConversation(1), 100)
  }

  // Auto-load from URL params
  useEffect(() => {
    if (urlUserA && urlUserB && activeTab === 'conversation') {
      handleConversation(1)
    }
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (urlUserId && activeTab === 'member') {
      handleMemberSearch(1)
    }
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const highlightKeyword = (text: string) => {
    if (!keyword) return text
    const parts = text.split(new RegExp(`(${keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi'))
    return parts.map((part, i) =>
      part.toLowerCase() === keyword.toLowerCase() ? <mark key={i}>{part}</mark> : part,
    )
  }

  // ─── Tab 1 columns ───
  const searchColumns = [
    {
      title: '時間', dataIndex: 'sent_at', key: 'sent_at', width: 160,
      render: (d: string) => dayjs(d).format('YYYY/MM/DD HH:mm'),
    },
    {
      title: '發送者', key: 'sender', width: 140,
      render: (_: unknown, r: SearchResult) => r.sender ? (
        <a onClick={() => navigate(`/members/${r.sender!.id}`)}>{r.sender.nickname}</a>
      ) : '-',
    },
    {
      title: '接收者', key: 'receiver', width: 140,
      render: (_: unknown, r: SearchResult) => r.receiver ? (
        <a onClick={() => navigate(`/members/${r.receiver!.id}`)}>{r.receiver.nickname}</a>
      ) : '-',
    },
    {
      title: '內容', dataIndex: 'content', key: 'content',
      render: (_: string | null, r: SearchResult) => {
        if (r.is_recalled && r.is_content_visible === false) {
          return <Text type="secondary" italic>[已收回]</Text>
        }
        if (r.is_recalled && r.is_content_visible) {
          return (
            <Space size={4} wrap>
              <span>{highlightKeyword(r.content ?? '')}</span>
              <Tag color="orange">已被使用者收回於 {r.recalled_at ? dayjs(r.recalled_at).format('MM/DD HH:mm') : ''}</Tag>
            </Space>
          )
        }
        return <span>{highlightKeyword(r.content ?? '')}</span>
      },
    },
    {
      title: '操作', key: 'action', width: 120,
      render: (_: unknown, r: SearchResult) => (
        <Button size="small" type="link" onClick={() => {
          if (r.sender && r.receiver) viewConversation(r.sender.id, r.receiver.id)
        }}>
          查看完整對話
        </Button>
      ),
    },
  ]

  const searchRecalledVisibleCount = searchResults.filter(r => r.is_recalled && r.is_content_visible).length
  const convRecalledVisibleCount = convMessages.filter(m => m.is_recalled && m.is_content_visible).length

  // ─── Tab 3 columns ───
  const memberColumns = [
    {
      title: '對方暱稱', key: 'counterpart', width: 160,
      render: (_: unknown, r: MemberChatEntry) => r.counterpart ? (
        <Space>
          <Avatar size="small">{r.counterpart.nickname?.[0]}</Avatar>
          <a onClick={() => navigate(`/members/${r.counterpart!.id}`)}>{r.counterpart.nickname}</a>
        </Space>
      ) : '-',
    },
    {
      title: '最後訊息', key: 'last_message',
      render: (_: unknown, r: MemberChatEntry) => {
        const lm = r.last_message
        if (!lm) return '-'
        const recalledHidden = lm.is_recalled && lm.is_content_visible === false
        return (
          <Text type={recalledHidden ? 'secondary' : undefined} italic={recalledHidden}>
            {lm.content ?? '-'}
            {lm.is_recalled && lm.is_content_visible && (
              <Tag color="orange" style={{ marginLeft: 8, fontSize: 10 }}>已收回（原文可見）</Tag>
            )}
          </Text>
        )
      },
    },
    {
      title: '最後時間', key: 'last_time', width: 160,
      render: (_: unknown, r: MemberChatEntry) => r.last_message ? dayjs(r.last_message.sent_at).format('YYYY/MM/DD HH:mm') : '-',
    },
    { title: '訊息數', dataIndex: 'total_messages', key: 'total_messages', width: 80 },
    {
      title: '操作', key: 'action', width: 200,
      render: (_: unknown, r: MemberChatEntry) => r.counterpart ? (
        <Space>
          <Button size="small" type="link" onClick={() => viewConversation(Number(memberUserId), r.counterpart!.id)}>
            查看對話
          </Button>
          <Button size="small" type="link" loading={memberExporting}
            onClick={() => handleMemberExport(String(r.counterpart!.id))}>
            匯出
          </Button>
        </Space>
      ) : null,
    },
  ]

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>聊天記錄查詢</Title>

      <Tabs activeKey={activeTab} onChange={setActiveTab} items={[
        {
          key: 'search',
          label: '🔍 關鍵字搜尋',
          children: (
            <div>
              <Card style={{ marginBottom: 16 }}>
                <Space wrap>
                  <Input
                    prefix={<SearchOutlined />}
                    placeholder="搜尋訊息內容（最少 2 字）"
                    value={keyword}
                    onChange={(e) => setKeyword(e.target.value)}
                    onPressEnter={() => handleSearch()}
                    style={{ width: 240 }}
                    allowClear
                  />
                  <Input
                    placeholder="限定用戶 ID（選填）"
                    value={searchUserId}
                    onChange={(e) => setSearchUserId(e.target.value)}
                    style={{ width: 160 }}
                    allowClear
                  />
                  <Button type="primary" onClick={() => handleSearch()} disabled={keyword.length < 2}>搜尋</Button>
                  <Button icon={<ReloadOutlined />} onClick={() => {
                    setKeyword('')
                    setSearchUserId('')
                    setSearchResults([])
                    setSearchTotal(0)
                  }}>清除</Button>
                </Space>
              </Card>

              {isSuperAdmin && searchRecalledVisibleCount > 0 && (
                <Alert
                  type="warning"
                  showIcon
                  style={{ marginBottom: 12 }}
                  message="您正在檢視已收回訊息原文"
                  description={`本次搜尋結果含 ${searchRecalledVisibleCount} 筆已收回訊息。此操作會記錄至 admin_operation_logs，僅供稽核 / 法遵調閱使用。`}
                />
              )}

              {searchTotal > 0 && (
                <Text type="secondary" style={{ display: 'block', marginBottom: 8 }}>共 {searchTotal} 筆結果</Text>
              )}

              <Table
                dataSource={searchResults}
                columns={searchColumns}
                rowKey="message_id"
                loading={searchLoading}
                pagination={{
                  current: searchPage,
                  pageSize: 20,
                  total: searchTotal,
                  showTotal: (t) => `共 ${t} 筆`,
                  onChange: (p) => handleSearch(p),
                }}
                size="middle"
                locale={{ emptyText: keyword ? '無搜尋結果' : '請輸入關鍵字搜尋' }}
              />
            </div>
          ),
        },
        {
          key: 'conversation',
          label: '💬 兩人對話',
          children: (
            <div>
              <Card style={{ marginBottom: 16 }}>
                <Space wrap>
                  <Input
                    placeholder="用戶 A ID"
                    value={convUserA}
                    onChange={(e) => setConvUserA(e.target.value)}
                    style={{ width: 180 }}
                  />
                  <Input
                    placeholder="用戶 B ID"
                    value={convUserB}
                    onChange={(e) => setConvUserB(e.target.value)}
                    style={{ width: 180 }}
                  />
                  <Button type="primary" onClick={() => handleConversation()} disabled={!convUserA || !convUserB}>查詢</Button>
                  <Button icon={<DownloadOutlined />} onClick={handleExport} disabled={!convData}>匯出 CSV</Button>
                </Space>
              </Card>

              {convData ? (
                <div>
                  {isSuperAdmin && convRecalledVisibleCount > 0 && (
                    <Alert
                      type="warning"
                      showIcon
                      style={{ marginBottom: 12 }}
                      message="您正在檢視已收回訊息原文"
                      description={`本對話含 ${convRecalledVisibleCount} 筆已收回訊息。此操作會記錄至 admin_operation_logs，僅供稽核 / 法遵調閱使用。`}
                    />
                  )}
                  <Card style={{ marginBottom: 16 }}>
                    <Space size={32}>
                      {convData.user_a && (
                        <Space>
                          <Avatar>{convData.user_a.nickname?.[0]}</Avatar>
                          <div>
                            <a onClick={() => navigate(`/members/${convData.user_a!.id}`)}>{convData.user_a.nickname}</a>
                            <br /><Text type="secondary">ID: {convData.user_a.id}</Text>
                          </div>
                        </Space>
                      )}
                      <Text type="secondary">↔</Text>
                      {convData.user_b && (
                        <Space>
                          <Avatar>{convData.user_b.nickname?.[0]}</Avatar>
                          <div>
                            <a onClick={() => navigate(`/members/${convData.user_b!.id}`)}>{convData.user_b.nickname}</a>
                            <br /><Text type="secondary">ID: {convData.user_b.id}</Text>
                          </div>
                        </Space>
                      )}
                    </Space>
                  </Card>

                  <List
                    loading={convLoading}
                    dataSource={convMessages}
                    pagination={convTotal > 50 ? {
                      current: convPage,
                      pageSize: 50,
                      total: convTotal,
                      onChange: (p) => handleConversation(p),
                    } : false}
                    renderItem={(msg) => {
                      const isUserA = msg.sender_id === convData.user_a?.id
                      return (
                        <List.Item style={{ justifyContent: isUserA ? 'flex-start' : 'flex-end', border: 'none', padding: '4px 0' }}>
                          <div style={{
                            maxWidth: '70%',
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: isUserA ? 'flex-start' : 'flex-end',
                          }}>
                            <Text type="secondary" style={{ fontSize: 11, marginBottom: 2 }}>
                              {isUserA ? convData.user_a?.nickname : convData.user_b?.nickname}
                              {' · '}
                              {dayjs(msg.sent_at).format('MM/DD HH:mm')}
                            </Text>
                            <div style={{
                              padding: '8px 12px',
                              borderRadius: isUserA ? '4px 12px 12px 12px' : '12px 4px 12px 12px',
                              background: msg.is_recalled ? '#fff7e6' : (isUserA ? '#e6f7ff' : '#fff7e6'),
                              border: msg.is_recalled && msg.is_content_visible ? '1px dashed #fa8c16' : undefined,
                            }}>
                              {msg.is_recalled && !msg.is_content_visible && (
                                <Text type="secondary" italic>[已收回]</Text>
                              )}
                              {msg.is_recalled && msg.is_content_visible && (
                                <Space size={6} direction="vertical" style={{ alignItems: 'flex-start' }}>
                                  <Text>{msg.content}</Text>
                                  <Tag color="orange" style={{ fontSize: 10 }}>
                                    已被使用者收回{msg.recalled_at ? `於 ${dayjs(msg.recalled_at).format('MM/DD HH:mm')}` : ''}
                                  </Tag>
                                </Space>
                              )}
                              {!msg.is_recalled && <Text>{msg.content}</Text>}
                            </div>
                            {!isUserA && msg.is_read && !msg.is_recalled && (
                              <Text type="secondary" style={{ fontSize: 10, marginTop: 2 }}>已讀</Text>
                            )}
                          </div>
                        </List.Item>
                      )
                    }}
                    footer={<Text type="secondary">共 {convTotal} 則訊息（由舊到新排列）</Text>}
                  />
                </div>
              ) : (
                <Card>
                  <Text type="secondary">請輸入兩位用戶 ID 查詢對話</Text>
                </Card>
              )}
            </div>
          ),
        },
        {
          key: 'member',
          label: '👤 會員對話查詢',
          children: (
            <div>
              <Card style={{ marginBottom: 16 }}>
                <Space wrap>
                  <Input
                    prefix={<UserOutlined />}
                    placeholder="會員 ID（必填）"
                    value={memberUserId}
                    onChange={(e) => setMemberUserId(e.target.value)}
                    style={{ width: 160 }}
                  />
                  <Input
                    placeholder="對方 ID（選填）"
                    value={memberCounterpartId}
                    onChange={(e) => setMemberCounterpartId(e.target.value)}
                    style={{ width: 160 }}
                    allowClear
                  />
                  <Input
                    prefix={<SearchOutlined />}
                    placeholder="關鍵字（選填）"
                    value={memberKeyword}
                    onChange={(e) => setMemberKeyword(e.target.value)}
                    style={{ width: 180 }}
                    allowClear
                  />
                  <Button type="primary" onClick={() => handleMemberSearch()} disabled={!memberUserId}>查詢</Button>
                  <Button icon={<ReloadOutlined />} onClick={() => {
                    setMemberUserId('')
                    setMemberCounterpartId('')
                    setMemberKeyword('')
                    setMemberResults([])
                    setMemberTotal(0)
                  }}>清除</Button>
                </Space>
              </Card>

              <Table
                dataSource={memberResults}
                columns={memberColumns}
                rowKey="conversation_id"
                loading={memberLoading}
                pagination={{
                  current: memberPage,
                  pageSize: 20,
                  total: memberTotal,
                  showTotal: (t) => `共 ${t} 筆`,
                  onChange: (p) => handleMemberSearch(p),
                }}
                size="middle"
                locale={{ emptyText: memberUserId ? '此會員無聊天記錄' : '請輸入會員 ID 查詢' }}
              />
            </div>
          ),
        },
      ]} />
    </div>
  )
}
