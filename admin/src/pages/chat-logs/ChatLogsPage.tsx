import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Tabs, Card, Input, Button, Table, Tag, Typography, Space, Result, Avatar, List,
} from 'antd'
import { SearchOutlined, DownloadOutlined, ReloadOutlined } from '@ant-design/icons'
import { useAuthStore } from '../../stores/authStore'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title, Text } = Typography


interface SearchResult {
  message_id: number
  conversation_id: number
  sender: { id: number; nickname: string } | null
  receiver: { id: number; nickname: string } | null
  content: string
  type: string
  sent_at: string
  is_read: boolean
}

interface ConversationMessage {
  id: number
  sender_id: number
  content: string | null
  type: string
  is_recalled: boolean
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

export default function ChatLogsPage() {
  const user = useAuthStore((s) => s.user)

  // cs role cannot access chat logs
  if (user?.role === 'cs') {
    return <Result status="403" title="權限不足" subTitle="此頁面僅限 super_admin 和 admin 查看" />
  }

  return <ChatLogsContent />
}

function ChatLogsContent() {
  const navigate = useNavigate()
  const [activeTab, setActiveTab] = useState('search')

  // Search tab state
  const [keyword, setKeyword] = useState('')
  const [searchUserId, setSearchUserId] = useState('')
  const [searchResults, setSearchResults] = useState<SearchResult[]>([])
  const [searchTotal, setSearchTotal] = useState(0)
  const [searchLoading, setSearchLoading] = useState(false)
  const [searchPage, setSearchPage] = useState(1)

  // Conversation tab state
  const [convUserA, setConvUserA] = useState('')
  const [convUserB, setConvUserB] = useState('')
  const [convData, setConvData] = useState<ConversationData | null>(null)
  const [convMessages, setConvMessages] = useState<ConversationMessage[]>([])
  const [convTotal, setConvTotal] = useState(0)
  const [convLoading, setConvLoading] = useState(false)
  const [convPage, setConvPage] = useState(1)

  const handleSearch = async (page = 1) => {
    if (keyword.length < 2) return
    setSearchLoading(true)
    try {
      const params: Record<string, string | number> = { keyword, page, per_page: 20 }
      if (searchUserId) params.user_id = searchUserId
      const res = await apiClient.get('/admin/chat-logs/search', { params })
      setSearchResults(res.data.data)
      setSearchTotal(res.data.meta?.total ?? 0)
      setSearchPage(page)
    } catch {
      setSearchResults([])
      setSearchTotal(0)
    }
    setSearchLoading(false)
  }

  const handleConversation = async (page = 1) => {
    if (!convUserA || !convUserB) return
    setConvLoading(true)
    try {
      const res = await apiClient.get('/admin/chat-logs/conversations', {
        params: { user_a: convUserA, user_b: convUserB, page, per_page: 50 },
      })
      setConvData(res.data.data)
      setConvMessages(res.data.data.messages)
      setConvTotal(res.data.meta?.total ?? 0)
      setConvPage(page)
    } catch {
      setConvData(null)
      setConvMessages([])
      setConvTotal(0)
    }
    setConvLoading(false)
  }

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
      a.download = `chat_${convUserA}_${convUserB}.csv`
      a.click()
      window.URL.revokeObjectURL(url)
    } catch {
      // ignore export errors
    }
  }

  const viewConversation = (senderId: number, receiverId: number) => {
    setConvUserA(String(senderId))
    setConvUserB(String(receiverId))
    setActiveTab('conversation')
    setTimeout(() => handleConversation(1), 100)
  }

  const highlightKeyword = (text: string) => {
    if (!keyword) return text
    const parts = text.split(new RegExp(`(${keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi'))
    return parts.map((part, i) =>
      part.toLowerCase() === keyword.toLowerCase() ? <mark key={i}>{part}</mark> : part,
    )
  }

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
      render: (c: string) => <span>{highlightKeyword(c)}</span>,
    },
    {
      title: '操作', key: 'action', width: 100,
      render: (_: unknown, r: SearchResult) => (
        <Button size="small" type="link" onClick={() => {
          if (r.sender && r.receiver) viewConversation(r.sender.id, r.receiver.id)
        }}>
          查看對話
        </Button>
      ),
    },
  ]

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>聊天記錄查詢</Title>

      <Tabs activeKey={activeTab} onChange={setActiveTab} items={[
        {
          key: 'search',
          label: '關鍵字搜尋',
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
          label: '查詢兩人對話',
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
                    pagination={{
                      current: convPage,
                      pageSize: 50,
                      total: convTotal,
                      onChange: (p) => handleConversation(p),
                    }}
                    renderItem={(msg) => {
                      const isUserA = msg.sender_id === convData.user_a?.id
                      return (
                        <List.Item style={{ justifyContent: isUserA ? 'flex-start' : 'flex-end' }}>
                          <div style={{
                            maxWidth: '70%',
                            padding: '8px 12px',
                            borderRadius: 8,
                            background: msg.is_recalled ? '#f5f5f5' : (isUserA ? '#e6f7ff' : '#fff7e6'),
                          }}>
                            <Text type="secondary" style={{ fontSize: 11 }}>
                              {isUserA ? convData.user_a?.nickname : convData.user_b?.nickname}
                              {' · '}
                              {dayjs(msg.sent_at).format('MM/DD HH:mm')}
                              {msg.is_read && <Tag color="green" style={{ marginLeft: 4, fontSize: 10 }}>已讀</Tag>}
                            </Text>
                            <div style={{ marginTop: 4 }}>
                              {msg.is_recalled ? (
                                <Text type="secondary" italic>此訊息已被收回</Text>
                              ) : (
                                <Text>{msg.content}</Text>
                              )}
                            </div>
                          </div>
                        </List.Item>
                      )
                    }}
                    footer={<Text type="secondary">共 {convTotal} 則訊息</Text>}
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
      ]} />
    </div>
  )
}
