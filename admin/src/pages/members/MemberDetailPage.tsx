import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  Tabs, Descriptions, Avatar, Tag, Card, Table, Button, Modal, InputNumber, Input,
  Space, Typography, Statistic, Row, Col, message, Image, Result, Select, Divider, Switch,
  Drawer, Form, DatePicker, Checkbox,
} from 'antd'
import { ArrowLeftOutlined, CheckCircleOutlined, CloseCircleOutlined, SettingOutlined, EditOutlined } from '@ant-design/icons'
import { getCreditLevel, CreditLevelLabel, CreditLevelColor, CreditLevelBg, type MemberDetail } from '../../types/admin'
import { useAuthStore } from '../../stores/authStore'
import apiClient from '../../api/client'
import dayjs from 'dayjs'
import {
  STYLE_LABELS, DATING_BUDGET_LABELS, DATING_FREQUENCY_LABELS, DATING_TYPE_LABELS,
  RELATIONSHIP_GOAL_LABELS, SMOKING_LABELS, DRINKING_LABELS, AVAILABILITY_LABELS,
  formatLabel,
} from '../../constants/labelMaps'

const NA = <span style={{ color: '#999' }}>未填寫</span>

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

  const [member, setMember] = useState<MemberDetail | null>(null)
  const [scoreRecords, setScoreRecords] = useState<Record<string, unknown>[]>([])
  const [subscriptions, setSubscriptions] = useState<Record<string, unknown>[]>([])
  const [loading, setLoading] = useState(true)
  const [adjustModalOpen, setAdjustModalOpen] = useState(false)
  const [adjustValue, setAdjustValue] = useState<number>(0)
  const [adjustReason, setAdjustReason] = useState('')

  // Permissions modal state
  const [permModalOpen, setPermModalOpen] = useState(false)
  const [permLevel, setPermLevel] = useState<number>(0)
  const [permScore, setPermScore] = useState<number>(80)
  const [permStatus, setPermStatus] = useState<string>('active')
  const [permReason, setPermReason] = useState('')
  const [permSaving, setPermSaving] = useState(false)

  // Edit profile drawer state
  const [editDrawerOpen, setEditDrawerOpen] = useState(false)
  const [editSaving, setEditSaving] = useState(false)
  const [editForm] = Form.useForm()
  const isSuperAdmin = useAuthStore.getState().user?.role === 'super_admin'

  function reloadMember() {
    apiClient.get(`/admin/members/${uid}`).then(res => {
      setMember(res.data.data.member)
    }).catch(() => {})
  }

  useEffect(() => {
    setLoading(true)
    apiClient.get(`/admin/members/${uid}`).then(res => {
      setMember(res.data.data.member)
    }).catch(() => setMember(null)).finally(() => setLoading(false))

    // Fetch score history
    apiClient.get(`/admin/members/${uid}/credit-logs`).then(res => {
      setScoreRecords(res.data.data ?? [])
    }).catch(() => {})

    // Fetch subscription history
    apiClient.get(`/admin/members/${uid}/subscriptions`).then(res => {
      setSubscriptions(res.data.data ?? [])
    }).catch(() => {})
  }, [uid])

  if (loading) return <div style={{ padding: 40, textAlign: 'center' }}>載入中...</div>
  if (!member) {
    return <Result status="404" title="找不到此會員" extra={<Button onClick={() => navigate('/members')}>返回列表</Button>} />
  }

  const creditLevel = getCreditLevel(member.credit_score)

  const handleAdjustScore = async () => {
    try {
      await apiClient.patch(`/admin/members/${uid}/actions`, {
        action: 'adjust_score',
        score_delta: adjustValue,
        reason: adjustReason || '管理員手動調整',
      })
      message.success(`誠信分數已調整 ${adjustValue > 0 ? '+' : ''}${adjustValue}`)
      setAdjustModalOpen(false)
      setAdjustValue(0)
      setAdjustReason('')
      reloadMember()
      // Refresh score history
      apiClient.get(`/admin/members/${uid}/credit-logs`).then(res => setScoreRecords(res.data.data ?? [])).catch(() => {})
    } catch {
      message.error('調整失敗')
    }
  }

  const handleSuspend = () => {
    const isSuspended = member.status === 'suspended'
    Modal.confirm({
      title: isSuspended ? '確定解除停權？' : '確定停權此會員？',
      content: isSuspended ? `將解除 ${member.nickname} 的停權狀態` : `將停權 ${member.nickname}`,
      okText: '確定',
      cancelText: '取消',
      okButtonProps: { danger: !isSuspended },
      onOk: async () => {
        try {
          await apiClient.patch(`/admin/members/${uid}/actions`, {
            action: isSuspended ? 'unsuspend' : 'suspend',
          })
          message.success(isSuspended ? '已解除停權' : '已停權')
          reloadMember()
        } catch {
          message.error('操作失敗')
        }
      },
    })
  }

  function openPermModal() {
    if (!member) return
    setPermLevel(member.membership_level)
    setPermScore(member.credit_score)
    setPermStatus(member.status === 'suspended' ? 'suspended' : 'active')
    setPermReason('')
    setPermModalOpen(true)
  }

  async function handlePermSave() {
    setPermSaving(true)
    try {
      await apiClient.patch(`/admin/members/${uid}/permissions`, {
        membership_level: permLevel,
        credit_score: permScore,
        status: permStatus,
        reason: permReason || '管理員調整權限',
      })
      message.success('會員權限已更新')
      setPermModalOpen(false)
      reloadMember()
    } catch {
      message.error('更新失敗')
    }
    setPermSaving(false)
  }

  function openEditDrawer() {
    if (!member) return
    editForm.setFieldsValue({
      nickname: member.nickname,
      birth_date: member.birth_date ? dayjs(member.birth_date) : null,
      avatar_url: member.avatar,
      gender: member.gender,
      height: member.height,
      weight: member.weight,
      location: member.location,
      occupation: member.job,
      education: member.education,
      bio: member.introduction,
      // F27 profile fields
      style: member.style ?? undefined,
      dating_budget: member.dating_budget ?? undefined,
      dating_frequency: member.dating_frequency ?? undefined,
      dating_type: Array.isArray(member.dating_type) ? member.dating_type : [],
      relationship_goal: member.relationship_goal ?? undefined,
      smoking: member.smoking ?? undefined,
      drinking: member.drinking ?? undefined,
      car_owner: member.car_owner === true,
      availability: Array.isArray(member.availability) ? member.availability : [],
    })
    setEditDrawerOpen(true)
  }

  async function handleEditSave() {
    try {
      const values = await editForm.validateFields()
      setEditSaving(true)
      const payload: Record<string, unknown> = {}
      if (values.nickname !== undefined) payload.nickname = values.nickname
      if (values.birth_date) payload.birth_date = values.birth_date.format('YYYY-MM-DD')
      if (values.avatar_url !== undefined) payload.avatar_url = values.avatar_url || null
      if (values.gender !== undefined) payload.gender = values.gender
      if (values.height !== undefined) payload.height = values.height || null
      if (values.weight !== undefined) payload.weight = values.weight || null
      if (values.location !== undefined) payload.location = values.location || null
      if (values.occupation !== undefined) payload.occupation = values.occupation || null
      if (values.education !== undefined) payload.education = values.education || null
      if (values.bio !== undefined) payload.bio = values.bio || null
      // F27
      payload.style = values.style || null
      payload.dating_budget = values.dating_budget || null
      payload.dating_frequency = values.dating_frequency || null
      payload.dating_type = Array.isArray(values.dating_type) && values.dating_type.length > 0 ? values.dating_type : null
      payload.relationship_goal = values.relationship_goal || null
      payload.smoking = values.smoking || null
      payload.drinking = values.drinking || null
      payload.car_owner = typeof values.car_owner === 'boolean' ? values.car_owner : null
      payload.availability = Array.isArray(values.availability) && values.availability.length > 0 ? values.availability : null

      await apiClient.patch(`/admin/members/${uid}/profile`, payload)
      message.success('會員資料已更新')
      setEditDrawerOpen(false)
      reloadMember()
    } catch {
      message.error('更新失敗')
    }
    setEditSaving(false)
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
                      <Space style={{ marginTop: 16, width: '100%', justifyContent: 'center' }} wrap>
                        <Button onClick={() => setAdjustModalOpen(true)}>調整分數</Button>
                        <Button danger={member.status !== 'suspended'} onClick={handleSuspend}>
                          {member.status === 'suspended' ? '解除停權' : '停權'}
                        </Button>
                        <Button icon={<SettingOutlined />} onClick={openPermModal}>
                          權限調整
                        </Button>
                      </Space>
                    </Card>
                  </Col>
                  <Col xs={24} md={16}>
                    <Card title="個人資料" extra={isSuperAdmin && <Button icon={<EditOutlined />} size="small" onClick={openEditDrawer}>編輯資料</Button>}>
                      <Descriptions column={2} bordered size="small">
                        <Descriptions.Item label="性別">{member.gender === 'male' ? '男' : '女'}</Descriptions.Item>
                        <Descriptions.Item label="年齡">{member.age}</Descriptions.Item>
                        <Descriptions.Item label="生日">{member.birth_date}</Descriptions.Item>
                        <Descriptions.Item label="地區">{member.location || NA}</Descriptions.Item>
                        <Descriptions.Item label="身高">{member.height ? `${member.height} cm` : NA}</Descriptions.Item>
                        <Descriptions.Item label="體重">{member.weight ? `${member.weight} kg` : NA}</Descriptions.Item>
                        <Descriptions.Item label="職業">{member.job || NA}</Descriptions.Item>
                        <Descriptions.Item label="學歷">{member.education || NA}</Descriptions.Item>
                        <Descriptions.Item label="Email" span={2}>{member.email}</Descriptions.Item>
                        <Descriptions.Item label="簡介" span={2}>{member.introduction || NA}</Descriptions.Item>
                      </Descriptions>
                    </Card>

                    {/* F27: 外貌風格 */}
                    <Card title="外貌風格" style={{ marginTop: 16 }}>
                      <Descriptions column={2} bordered size="small">
                        <Descriptions.Item label="自我風格">{member.style ? formatLabel(member.style, STYLE_LABELS) : NA}</Descriptions.Item>
                        <Descriptions.Item label="身高">{member.height ? `${member.height} cm` : NA}</Descriptions.Item>
                        <Descriptions.Item label="體重" span={2}>{member.weight ? `${member.weight} kg` : NA}</Descriptions.Item>
                      </Descriptions>
                    </Card>

                    {/* F27: 約會偏好 */}
                    <Card title="約會偏好" style={{ marginTop: 16 }}>
                      <Descriptions column={2} bordered size="small">
                        <Descriptions.Item label="約會預算">{member.dating_budget ? formatLabel(member.dating_budget, DATING_BUDGET_LABELS) : NA}</Descriptions.Item>
                        <Descriptions.Item label="見面頻率">{member.dating_frequency ? formatLabel(member.dating_frequency, DATING_FREQUENCY_LABELS) : NA}</Descriptions.Item>
                        <Descriptions.Item label="約會類型" span={2}>
                          {Array.isArray(member.dating_type) && member.dating_type.length > 0
                            ? member.dating_type.map(t => <Tag key={t} color="blue">{formatLabel(t, DATING_TYPE_LABELS)}</Tag>)
                            : NA}
                        </Descriptions.Item>
                        <Descriptions.Item label="關係期望" span={2}>{member.relationship_goal ? formatLabel(member.relationship_goal, RELATIONSHIP_GOAL_LABELS) : NA}</Descriptions.Item>
                      </Descriptions>
                    </Card>

                    {/* F27: 生活資訊 */}
                    <Card title="生活資訊" style={{ marginTop: 16 }}>
                      <Descriptions column={2} bordered size="small">
                        <Descriptions.Item label="抽菸">{member.smoking ? formatLabel(member.smoking, SMOKING_LABELS) : NA}</Descriptions.Item>
                        <Descriptions.Item label="飲酒">{member.drinking ? formatLabel(member.drinking, DRINKING_LABELS) : NA}</Descriptions.Item>
                        <Descriptions.Item label="自備車">
                          {member.car_owner === true ? '有' : member.car_owner === false ? '無' : NA}
                        </Descriptions.Item>
                        <Descriptions.Item label="可約時段">
                          {Array.isArray(member.availability) && member.availability.length > 0
                            ? member.availability.map(t => <Tag key={t} color="green">{formatLabel(t, AVAILABILITY_LABELS)}</Tag>)
                            : NA}
                        </Descriptions.Item>
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
                    {(member.photos?.length ?? 0) > 0 && (
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
                  { id: 1, operator: 'chuck@lunarwind.org', action: '查看會員資料', created_at: new Date().toISOString() },
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

      {/* Permissions Modal */}
      <Modal
        title={<Space><SettingOutlined />權限 / 狀態調整 — {member.nickname}</Space>}
        open={permModalOpen}
        onOk={handlePermSave}
        onCancel={() => setPermModalOpen(false)}
        okText="確認儲存"
        confirmLoading={permSaving}
        width={520}
      >
        <div style={{ marginBottom: 20 }}>
          <Text type="secondary">直接設定該用戶的等級、分數與狀態，變更會即時生效並寫入操作日誌。</Text>
        </div>

        <div style={{ marginBottom: 16 }}>
          <Text strong>會員等級</Text>
          <Select value={permLevel} onChange={setPermLevel} style={{ width: '100%', marginTop: 4 }}>
            <Select.Option value={0}>Lv0 — 未驗證（一般會員）</Select.Option>
            <Select.Option value={1}>Lv1 — Email + 手機驗證</Select.Option>
            <Select.Option value={1.5}>Lv1.5 — 女性照片驗證</Select.Option>
            <Select.Option value={2}>Lv2 — 進階驗證</Select.Option>
            <Select.Option value={3}>Lv3 — 付費會員</Select.Option>
          </Select>
        </div>

        <div style={{ marginBottom: 16 }}>
          <Text strong>誠信分數</Text>
          <InputNumber
            value={permScore}
            onChange={(v) => setPermScore(v ?? 0)}
            min={0} max={100}
            style={{ width: '100%', marginTop: 4 }}
            addonAfter="/ 100"
          />
          <div style={{ marginTop: 4 }}>
            <Tag style={{
              background: CreditLevelBg[getCreditLevel(permScore)],
              color: CreditLevelColor[getCreditLevel(permScore)],
              border: 'none',
            }}>
              {permScore} 分 — {CreditLevelLabel[getCreditLevel(permScore)]}
            </Tag>
          </div>
        </div>

        <div style={{ marginBottom: 16 }}>
          <Text strong>帳號狀態</Text>
          <div style={{ marginTop: 4 }}>
            <Switch
              checked={permStatus === 'active'}
              onChange={(v) => setPermStatus(v ? 'active' : 'suspended')}
              checkedChildren="正常"
              unCheckedChildren="停權"
            />
            {permStatus === 'suspended' && (
              <Tag color="red" style={{ marginLeft: 8 }}>將被停權</Tag>
            )}
          </div>
        </div>

        <Divider />

        <div>
          <Text strong>變更原因</Text>
          <Input.TextArea
            value={permReason}
            onChange={(e) => setPermReason(e.target.value)}
            rows={2}
            style={{ marginTop: 4 }}
            placeholder="選填，會記錄在操作日誌中"
          />
        </div>
      </Modal>

      {/* Edit Profile Drawer (super_admin only) */}
      <Drawer
        title={`編輯會員資料 — ${member.nickname}`}
        open={editDrawerOpen}
        onClose={() => setEditDrawerOpen(false)}
        width={480}
        extra={
          <Button type="primary" icon={<EditOutlined />} onClick={handleEditSave} loading={editSaving}>
            儲存
          </Button>
        }
      >
        <Form form={editForm} layout="vertical">
          <Form.Item name="nickname" label="暱稱" rules={[{ required: true, min: 2, max: 20 }]}>
            <Input />
          </Form.Item>
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="gender" label="性別">
                <Select>
                  <Select.Option value="male">男</Select.Option>
                  <Select.Option value="female">女</Select.Option>
                </Select>
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="birth_date" label="生日">
                <DatePicker style={{ width: '100%' }} />
              </Form.Item>
            </Col>
          </Row>
          <Form.Item name="avatar_url" label="頭像 URL">
            <Input placeholder="https://cdn.mimeet.tw/avatars/..." />
          </Form.Item>
          <Divider />
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="height" label="身高 (cm)">
                <InputNumber min={100} max={250} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="weight" label="體重 (kg)">
                <InputNumber min={30} max={200} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="location" label="居住地區">
                <Input placeholder="台北市" />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="occupation" label="職業">
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="education" label="學歷">
                <Input />
              </Form.Item>
            </Col>
          </Row>
          <Form.Item name="bio" label="自我介紹">
            <Input.TextArea rows={4} maxLength={500} showCount />
          </Form.Item>

          {/* F27 profile fields */}
          <Divider>進階資料</Divider>
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="style" label="自我風格">
                <Select allowClear placeholder="不指定">
                  {Object.entries(STYLE_LABELS).map(([k, v]) => <Select.Option key={k} value={k}>{v}</Select.Option>)}
                </Select>
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="dating_budget" label="約會預算">
                <Select allowClear placeholder="不指定">
                  {Object.entries(DATING_BUDGET_LABELS).map(([k, v]) => <Select.Option key={k} value={k}>{v}</Select.Option>)}
                </Select>
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="dating_frequency" label="見面頻率">
                <Select allowClear placeholder="不指定">
                  {Object.entries(DATING_FREQUENCY_LABELS).map(([k, v]) => <Select.Option key={k} value={k}>{v}</Select.Option>)}
                </Select>
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="relationship_goal" label="關係期望">
                <Select allowClear placeholder="不指定">
                  {Object.entries(RELATIONSHIP_GOAL_LABELS).map(([k, v]) => <Select.Option key={k} value={k}>{v}</Select.Option>)}
                </Select>
              </Form.Item>
            </Col>
          </Row>
          <Form.Item name="dating_type" label="約會類型（可複選）">
            <Checkbox.Group options={Object.entries(DATING_TYPE_LABELS).map(([k, v]) => ({ label: v, value: k }))} />
          </Form.Item>
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="smoking" label="抽菸">
                <Select allowClear placeholder="不指定">
                  {Object.entries(SMOKING_LABELS).map(([k, v]) => <Select.Option key={k} value={k}>{v}</Select.Option>)}
                </Select>
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="drinking" label="飲酒">
                <Select allowClear placeholder="不指定">
                  {Object.entries(DRINKING_LABELS).map(([k, v]) => <Select.Option key={k} value={k}>{v}</Select.Option>)}
                </Select>
              </Form.Item>
            </Col>
          </Row>
          <Form.Item name="car_owner" label="自備車" valuePropName="checked">
            <Switch checkedChildren="有" unCheckedChildren="無" />
          </Form.Item>
          <Form.Item name="availability" label="可約時段（可複選）">
            <Checkbox.Group options={Object.entries(AVAILABILITY_LABELS).map(([k, v]) => ({ label: v, value: k }))} />
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
