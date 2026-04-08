import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  Tabs, Descriptions, Avatar, Tag, Card, Table, Button, Modal, InputNumber, Input,
  Space, Typography, Statistic, Row, Col, message, Image, Result,
} from 'antd'
import { ArrowLeftOutlined, CheckCircleOutlined, CloseCircleOutlined } from '@ant-design/icons'
import { getMemberDetail, getMemberScoreRecords, getMemberSubscriptions } from '../../mocks/members'
import { getCreditLevel, CreditLevelLabel, CreditLevelColor, CreditLevelBg } from '../../types/admin'
import dayjs from 'dayjs'

const { Title, Text } = Typography

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

  if (!member) {
    return <Result status="404" title="找不到此會員" extra={<Button onClick={() => navigate('/admin/members')}>返回列表</Button>} />
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
      <Button type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate('/admin/members')} style={{ padding: 0, marginBottom: 16 }}>
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
                      <Space style={{ marginTop: 16, width: '100%', justifyContent: 'center' }}>
                        <Button onClick={() => setAdjustModalOpen(true)}>調整分數</Button>
                        <Button danger={member.status !== 'suspended'} onClick={handleSuspend}>
                          {member.status === 'suspended' ? '解除停權' : '停權'}
                        </Button>
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
    </div>
  )
}
