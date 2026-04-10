import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  Tabs, Descriptions, Avatar, Tag, Card, Table, Button, Modal, InputNumber, Input,
  Space, Typography, Statistic, Row, Col, message, Image, Result, Select, Drawer, Form,
} from 'antd'
import { ArrowLeftOutlined, CheckCircleOutlined, CloseCircleOutlined, LockOutlined, EditOutlined } from '@ant-design/icons'
import { getMemberDetail, getMemberScoreRecords, getMemberSubscriptions } from '../../mocks/members'
import { getCreditLevel, CreditLevelLabel, CreditLevelColor, CreditLevelBg } from '../../types/admin'
import { useAuthStore } from '../../stores/authStore'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title, Text } = Typography

interface ChatLogEntry {
  conversation_id: number
  counterpart: { id: number; nickname: string; avatar_url?: string } | null
  last_message: { content: string; sent_at: string } | null
  total_messages: number
}

export default function MemberDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const uid = Number(id)
  const member = getMemberDetail(uid)
  const scoreRecords = getMemberScoreRecords(uid)
  const subscriptions = getMemberSubscriptions(uid)

  const [adjustModalOpen, setAdjustModalOpen] = useState(false)
  const [adjustValue, setAdjustValue] = useState<number>(0)
  const [adjustReason, setAdjustReason] = useState('')

  // S12-09 Permission Modal state
  const [permModalOpen, setPermModalOpen] = useState(false)
  const [permLevel, setPermLevel] = useState<number>(0)
  const [permScore, setPermScore] = useState<number>(0)
  const [permStatus, setPermStatus] = useState<string>('active')
  const [permSaving, setPermSaving] = useState(false)

  // S12-10 Edit Drawer state
  const [editDrawerOpen, setEditDrawerOpen] = useState(false)
  const [editForm] = Form.useForm()
  const [editSaving, setEditSaving] = useState(false)

  const authUser = useAuthStore((s) => s.user)
  const isSuperAdmin = authUser?.role === 'super_admin'

  if (!member) {
    return <Result status="404" title="找不到此會員" extra={<Button onClick={() => navigate('/members')}>返回列表</Button>} />
  }

  const creditLevel = getCreditLevel(member.credit_score)

  const handleAdjustScore = () => {
    message.success(`誠信分數已調整 ${adjustValue > 0 ? '+' : ''}${adjustValue}`)
    setAdjustModalOpen(false)
    setAdjustValue(0)
    setAdjustReason('')
  }

  const handleSuspend = () => {
    Modal.confirm({
      title: member.status === 'suspended' ? '確定解除停權？' : '確定停權此會員？',
      content: member.status === 'suspended' ? `將解除 ${member.nickname} 的停權狀態` : `將停權 ${member.nickname}`,
      okText: '確定',
      cancelText: '取消',
      okButtonProps: { danger: member.status !== 'suspended' },
      onOk: () => message.success(member.status === 'suspended' ? '已解除停權' : '已停權'),
    })
  }

  // S12-09 Permission Modal handlers
  const openPermModal = () => {
    setPermLevel(member.membership_level ?? member.level)
    setPermScore(member.credit_score)
    setPermStatus(member.status)
    setPermModalOpen(true)
  }

  const handlePermSave = async () => {
    setPermSaving(true)
    try {
      await apiClient.patch(`/admin/members/${uid}/permissions`, {
        membership_level: permLevel,
        credit_score: permScore,
        status: permStatus,
      })
      message.success('會員權限已更新')
      setPermModalOpen(false)
    } catch (err: any) {
      message.error(err.response?.data?.message || '更新失敗')
    } finally {
      setPermSaving(false)
    }
  }

  // S12-10 Edit Drawer handlers
  const openEditDrawer = () => {
    editForm.setFieldsValue({
      nickname: member.nickname,
      introduction: member.introduction,
      location: member.location,
      job: member.job,
      education: member.education,
      height: member.height,
      weight: member.weight,
    })
    setEditDrawerOpen(true)
  }

  const handleEditSave = async () => {
    try {
      const values = await editForm.validateFields()
      setEditSaving(true)
      await apiClient.patch(`/admin/members/${uid}/profile`, values)
      message.success('會員資料已更新')
      setEditDrawerOpen(false)
    } catch (err: any) {
      if (err.errorFields) return // Form validation error
      message.error(err.response?.data?.message || '更新失敗')
    } finally {
      setEditSaving(false)
    }
  }

  const scoreColumns = [
    { title: '時間', dataIndex: 'created_at', key: 'created_at', render: (d: string) => dayjs(d).format('YYYY/MM/DD HH:mm') },
    {
      title: '分數變化', dataIndex: 'delta', key: 'delta',
      render: (d: number) => <Text style={{ color: d > 0 ? '#10B981' : '#EF4444', fontWeight: 700 }}>{d > 0 ? `+${d}` : d}</Text>,
    },
    { title: '原因', dataIndex: 'reason', key: 'reason' },
    { title: '操作者', dataIndex: 'operator', key: 'operator' },
  ]

  const subColumns = [
    { title: '方案', dataIndex: 'plan', key: 'plan' },
    { title: '金額', dataIndex: 'amount', key: 'amount', render: (a: number) => `NT$${a}` },
    { title: '狀態', dataIndex: 'status', key: 'status', render: (s: string) => <Tag color={s === 'active' ? 'green' : 'default'}>{s}</Tag> },
    { title: '開始日', dataIndex: 'started_at', key: 'started_at', render: (d: string) => dayjs(d).format('YYYY/MM/DD') },
    { title: '到期日', dataIndex: 'expires_at', key: 'expires_at', render: (d: string) => dayjs(d).format('YYYY/MM/DD') },
  ]

  return (
    <div>
      <Button type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate('/members')} style={{ padding: 0, marginBottom: 16 }}>
        返回會員列表
      </Button>

      <Tabs
        defaultActiveKey="profile"
        items={[
          {
            key: 'profile',
            label: '基本資料',
            children: (
              <div>
                <Row gutter={24}>
                  <Col xs={24} md={8}>
                    <Card>
                      <div style={{ textAlign: 'center' }}>
                        <Avatar src={member.avatar} size={96} />
                        <Title level={4} style={{ marginTop: 12, marginBottom: 4 }}>{member.nickname}</Title>
                        <Text type="secondary">UID: {member.uid}</Text>
                        <div style={{ marginTop: 12 }}>
                          <Tag color={member.status === 'active' ? 'green' : 'red'}>
                            {member.status === 'active' ? '正常' : '停權'}
                          </Tag>
                          <Tag color={member.level >= 3 ? 'gold' : member.level >= 2 ? 'blue' : 'default'}>Lv{member.level}</Tag>
                        </div>
                      </div>
                      <div style={{ marginTop: 24, textAlign: 'center' }}>
                        <Statistic
                          title="誠信分數"
                          value={member.credit_score}
                          suffix={<Tag style={{ background: CreditLevelBg[creditLevel], color: CreditLevelColor[creditLevel], border: 'none', marginLeft: 8 }}>{CreditLevelLabel[creditLevel]}</Tag>}
                          valueStyle={{ color: CreditLevelColor[creditLevel], fontSize: 36, fontWeight: 800 }}
                        />
                      </div>
                      <Space direction="vertical" style={{ marginTop: 16, width: '100%' }}>
                        <Space style={{ width: '100%', justifyContent: 'center' }}>
                          <Button onClick={() => setAdjustModalOpen(true)}>調整分數</Button>
                          <Button danger={member.status !== 'suspended'} onClick={handleSuspend}>
                            {member.status === 'suspended' ? '解除停權' : '停權'}
                          </Button>
                        </Space>
                        <Space style={{ width: '100%', justifyContent: 'center' }}>
                          <Button icon={<LockOutlined />} onClick={openPermModal}>
                            權限調整
                          </Button>
                          {isSuperAdmin && (
                            <Button icon={<EditOutlined />} onClick={openEditDrawer}>
                              編輯資料
                            </Button>
                          )}
                        </Space>
                      </Space>
                    </Card>
                  </Col>
                  <Col xs={24} md={16}>
                    <Card title="個人資料">
                      <Descriptions column={2} bordered size="small">
                        <Descriptions.Item label="性別">{member.gender === 'male' ? '男' : '女'}</Descriptions.Item>
                        <Descriptions.Item label="年齡">{member.age}</Descriptions.Item>
                        <Descriptions.Item label="生日">{member.birth_date}</Descriptions.Item>
                        <Descriptions.Item label="地區">{member.location}</Descriptions.Item>
                        <Descriptions.Item label="身高">{member.height} cm</Descriptions.Item>
                        <Descriptions.Item label="體重">{member.weight} kg</Descriptions.Item>
                        <Descriptions.Item label="職業">{member.job}</Descriptions.Item>
                        <Descriptions.Item label="學歷">{member.education}</Descriptions.Item>
                        <Descriptions.Item label="Email" span={2}>{member.email}</Descriptions.Item>
                        <Descriptions.Item label="簡介" span={2}>{member.introduction}</Descriptions.Item>
                      </Descriptions>
                    </Card>
                    <Card title="驗證狀態" style={{ marginTop: 16 }}>
                      <Space size={16}>
                        <Tag icon={member.email_verified ? <CheckCircleOutlined /> : <CloseCircleOutlined />} color={member.email_verified ? 'blue' : 'default'}>
                          Email {member.email_verified ? '已驗證' : '未驗證'}
                        </Tag>
                        <Tag icon={member.phone_verified ? <CheckCircleOutlined /> : <CloseCircleOutlined />} color={member.phone_verified ? 'green' : 'default'}>
                          手機 {member.phone_verified ? '已驗證' : '未驗證'}
                        </Tag>
                        <Tag icon={member.advanced_verified ? <CheckCircleOutlined /> : <CloseCircleOutlined />} color={member.advanced_verified ? 'orange' : 'default'}>
                          進階 {member.advanced_verified ? '已驗證' : '未驗證'}
                        </Tag>
                      </Space>
                    </Card>
                    {member.photos.length > 0 && (
                      <Card title="照片" style={{ marginTop: 16 }}>
                        <Image.PreviewGroup>
                          <Space>
                            {member.photos.map((p) => (
                              <Image key={p.id} src={p.url} width={120} height={120} style={{ objectFit: 'cover', borderRadius: 8 }} />
                            ))}
                          </Space>
                        </Image.PreviewGroup>
                      </Card>
                    )}
                  </Col>
                </Row>
              </div>
            ),
          },
          {
            key: 'scores',
            label: '分數紀錄',
            children: <Table dataSource={scoreRecords} columns={scoreColumns} rowKey="id" pagination={{ pageSize: 20 }} size="small" />,
          },
          {
            key: 'subscription',
            label: '訂閱記錄',
            children: subscriptions.length > 0
              ? <Table dataSource={subscriptions} columns={subColumns} rowKey="id" pagination={false} size="small" />
              : <Text type="secondary">無訂閱記錄</Text>,
          },
          // Chat logs tab — only for admin/super_admin with chat.view
          ...(useAuthStore.getState().user?.role !== 'cs' ? [{
            key: 'chat-logs',
            label: '聊天記錄',
            children: <MemberChatLogsTab userId={uid} />,
          }] : []),
          {
            key: 'operations',
            label: '操作紀錄',
            children: (
              <Table
                dataSource={[
                  { id: 1, operator: 'admin@mimeet.tw', action: '查看會員資料', created_at: new Date().toISOString() },
                ]}
                columns={[
                  { title: '時間', dataIndex: 'created_at', key: 'created_at', render: (d: string) => dayjs(d).format('YYYY/MM/DD HH:mm') },
                  { title: '操作者', dataIndex: 'operator', key: 'operator' },
                  { title: '操作內容', dataIndex: 'action', key: 'action' },
                ]}
                rowKey="id"
                size="small"
              />
            ),
          },
        ]}
      />

      {/* Adjust Score Modal (existing) */}
      <Modal
        title="調整誠信分數"
        open={adjustModalOpen}
        onOk={handleAdjustScore}
        onCancel={() => setAdjustModalOpen(false)}
        okText="確認調整"
      >
        <div style={{ marginBottom: 16 }}>
          <Text>目前分數：<Text strong>{member.credit_score}</Text></Text>
        </div>
        <div style={{ marginBottom: 12 }}>
          <Text>調整值（正數加分，負數扣分）：</Text>
          <InputNumber value={adjustValue} onChange={(v) => setAdjustValue(v || 0)} style={{ width: '100%', marginTop: 4 }} />
        </div>
        <div>
          <Text>原因：</Text>
          <Input.TextArea value={adjustReason} onChange={(e) => setAdjustReason(e.target.value)} rows={3} style={{ marginTop: 4 }} placeholder="請填寫調整原因" />
        </div>
      </Modal>

      {/* S12-09 Permission Override Modal */}
      <Modal
        title="權限調整"
        open={permModalOpen}
        onOk={handlePermSave}
        onCancel={() => setPermModalOpen(false)}
        okText="確認修改"
        confirmLoading={permSaving}
      >
        <div style={{ marginBottom: 16 }}>
          <Text strong>會員等級</Text>
          <Select
            value={permLevel}
            onChange={setPermLevel}
            style={{ width: '100%', marginTop: 4 }}
          >
            <Select.Option value={0}>Lv0 - 基本（未驗證）</Select.Option>
            <Select.Option value={1}>Lv1 - 基本會員</Select.Option>
            <Select.Option value={2}>Lv2 - 進階驗證會員</Select.Option>
            <Select.Option value={3}>Lv3 - 付費會員</Select.Option>
          </Select>
        </div>
        <div style={{ marginBottom: 16 }}>
          <Text strong>誠信分數</Text>
          <InputNumber
            value={permScore}
            onChange={(v) => setPermScore(v ?? 0)}
            min={0}
            max={100}
            style={{ width: '100%', marginTop: 4 }}
          />
        </div>
        <div>
          <Text strong>帳號狀態</Text>
          <Select
            value={permStatus}
            onChange={setPermStatus}
            style={{ width: '100%', marginTop: 4 }}
          >
            <Select.Option value="active">正常</Select.Option>
            <Select.Option value="suspended">停權</Select.Option>
          </Select>
        </div>
      </Modal>

      {/* S12-10 Edit Profile Drawer (super_admin only) */}
      <Drawer
        title="編輯會員資料"
        placement="right"
        width={480}
        open={editDrawerOpen}
        onClose={() => setEditDrawerOpen(false)}
        extra={
          <Space>
            <Button onClick={() => setEditDrawerOpen(false)}>取消</Button>
            <Button type="primary" onClick={handleEditSave} loading={editSaving}>
              儲存
            </Button>
          </Space>
        }
      >
        <Form form={editForm} layout="vertical">
          <Form.Item
            name="nickname"
            label="暱稱"
            rules={[
              { required: true, message: '請輸入暱稱' },
              { max: 20, message: '暱稱最多 20 字' },
            ]}
          >
            <Input />
          </Form.Item>
          <Form.Item
            name="introduction"
            label="簡介"
            rules={[{ max: 500, message: '簡介最多 500 字' }]}
          >
            <Input.TextArea rows={3} />
          </Form.Item>
          <Form.Item name="location" label="地區">
            <Input />
          </Form.Item>
          <Form.Item name="job" label="職業">
            <Input />
          </Form.Item>
          <Form.Item name="education" label="學歷">
            <Select>
              <Select.Option value="高中">高中</Select.Option>
              <Select.Option value="專科">專科</Select.Option>
              <Select.Option value="大學">大學</Select.Option>
              <Select.Option value="碩士">碩士</Select.Option>
              <Select.Option value="博士">博士</Select.Option>
            </Select>
          </Form.Item>
          <Form.Item name="height" label="身高 (cm)">
            <InputNumber min={100} max={250} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="weight" label="體重 (kg)">
            <InputNumber min={30} max={200} style={{ width: '100%' }} />
          </Form.Item>
        </Form>
      </Drawer>
    </div>
  )
}

function MemberChatLogsTab({ userId }: { userId: number }) {
  const navigate = useNavigate()
  const [chatLogs, setChatLogs] = useState<ChatLogEntry[]>([])
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    setLoading(true)
    apiClient.get(`/admin/members/${userId}/chat-logs`)
      .then((res) => setChatLogs(res.data.data ?? []))
      .catch(() => setChatLogs([]))
      .finally(() => setLoading(false))
  }, [userId])

  const columns = [
    {
      title: '對方暱稱', key: 'counterpart', width: 160,
      render: (_: unknown, r: ChatLogEntry) => r.counterpart ? (
        <a onClick={() => navigate(`/members/${r.counterpart!.id}`)}>{r.counterpart.nickname}</a>
      ) : '-',
    },
    {
      title: '最後訊息', key: 'last_message',
      render: (_: unknown, r: ChatLogEntry) => r.last_message?.content ?? '-',
    },
    {
      title: '最後時間', key: 'last_time', width: 160,
      render: (_: unknown, r: ChatLogEntry) => r.last_message ? dayjs(r.last_message.sent_at).format('YYYY/MM/DD HH:mm') : '-',
    },
    { title: '訊息總數', dataIndex: 'total_messages', key: 'total_messages', width: 100 },
    {
      title: '操作', key: 'action', width: 100,
      render: (_: unknown, r: ChatLogEntry) => r.counterpart ? (
        <Button size="small" type="link" onClick={() => navigate(`/chat-logs?user_a=${userId}&user_b=${r.counterpart!.id}`)}>
          查看對話
        </Button>
      ) : null,
    },
  ]

  return (
    <Table
      dataSource={chatLogs}
      columns={columns}
      rowKey="conversation_id"
      loading={loading}
      pagination={{ pageSize: 20 }}
      size="small"
      locale={{ emptyText: '無聊天記錄' }}
    />
  )
}
