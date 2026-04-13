import { useState, useEffect, useCallback } from 'react'
import { Switch, Card, Typography, message, Button, Table, Tag } from 'antd'
import { SaveOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'

const { Title, Text } = Typography

type LevelKey = '0' | '1' | '1.5' | '2' | '3'

const LEVELS: { key: LevelKey; label: string; color: string }[] = [
  { key: '0', label: 'Lv0 註冊', color: 'default' },
  { key: '1', label: 'Lv1 基礎', color: 'default' },
  { key: '1.5', label: 'Lv1.5 女性驗證', color: 'cyan' },
  { key: '2', label: 'Lv2 進階驗證', color: 'blue' },
  { key: '3', label: 'Lv3 付費', color: 'gold' },
]

const PERMISSION_KEYS = [
  { key: 'browse_explore', label: '瀏覽探索' },
  { key: 'basic_search', label: '基本搜尋' },
  { key: 'view_profiles', label: '查看個人頁' },
  { key: 'send_messages', label: '傳送訊息' },
  { key: 'send_date_invite', label: '發送約會邀請' },
  { key: 'view_visitors', label: '查看訪客' },
  { key: 'stealth_mode', label: '隱身模式' },
  { key: 'read_receipts', label: '已讀回執' },
  { key: 'daily_message_limit', label: '每日訊息上限' },
  { key: 'post_content', label: '發布動態' },
]

// Default permission matrix
const DEFAULT_MATRIX: Record<string, Record<LevelKey, boolean | number>> = {
  browse_explore: { '0': true, '1': true, '1.5': true, '2': true, '3': true },
  basic_search: { '0': true, '1': true, '1.5': true, '2': true, '3': true },
  view_profiles: { '0': true, '1': true, '1.5': true, '2': true, '3': true },
  send_messages: { '0': false, '1': false, '1.5': true, '2': true, '3': true },
  send_date_invite: { '0': false, '1': false, '1.5': false, '2': true, '3': true },
  view_visitors: { '0': false, '1': false, '1.5': false, '2': false, '3': true },
  stealth_mode: { '0': false, '1': false, '1.5': false, '2': false, '3': true },
  read_receipts: { '0': false, '1': false, '1.5': false, '2': false, '3': true },
  daily_message_limit: { '0': 5, '1': 10, '1.5': 20, '2': 30, '3': 999 },
  post_content: { '0': false, '1': false, '1.5': false, '2': true, '3': true },
}

export default function LevelPermissionsPage() {
  const [matrix, setMatrix] = useState<Record<string, Record<LevelKey, boolean | number>>>(DEFAULT_MATRIX)
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)

  const fetchData = useCallback(async () => {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/level-permissions')
      const apiData = res.data.data ?? res.data
      if (apiData && typeof apiData === 'object') {
        setMatrix(apiData)
      }
    } catch {
      // Use default matrix
      setMatrix(DEFAULT_MATRIX)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchData()
  }, [fetchData])

  const handleToggle = (permKey: string, levelKey: LevelKey, value: boolean) => {
    setMatrix((prev) => ({
      ...prev,
      [permKey]: {
        ...prev[permKey],
        [levelKey]: value,
      },
    }))
  }

  const handleSave = async () => {
    setSaving(true)
    try {
      await apiClient.patch('/admin/level-permissions', { permissions: matrix })
      message.success('權限設定已儲存')
    } catch {
      message.error('儲存權限設定失敗')
    } finally {
      setSaving(false)
    }
  }

  const columns = [
    {
      title: '權限',
      dataIndex: 'label',
      key: 'label',
      width: 160,
      fixed: 'left' as const,
      render: (label: string) => <Text strong>{label}</Text>,
    },
    ...LEVELS.map((level) => ({
      title: <Tag color={level.color}>{level.label}</Tag>,
      key: level.key,
      width: 140,
      align: 'center' as const,
      render: (_: unknown, record: { key: string; label: string }) => {
        const value = matrix[record.key]?.[level.key]
        if (record.key === 'daily_message_limit') {
          return (
            <Text strong style={{ color: typeof value === 'number' && value >= 999 ? '#F0294E' : undefined }}>
              {typeof value === 'number' ? (value >= 999 ? '無限' : value) : '-'}
            </Text>
          )
        }
        return (
          <Switch
            checked={!!value}
            onChange={(checked) => handleToggle(record.key, level.key, checked)}
            size="small"
          />
        )
      },
    })),
  ]

  const dataSource = PERMISSION_KEYS.map((pk) => ({
    key: pk.key,
    label: pk.label,
  }))

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <Title level={4} style={{ margin: 0 }}>等級權限設定</Title>
        <Button type="primary" icon={<SaveOutlined />} onClick={handleSave} loading={saving}>
          儲存變更
        </Button>
      </div>

      <Card>
        <Table
          dataSource={dataSource}
          columns={columns}
          rowKey="key"
          loading={loading}
          pagination={false}
          size="middle"
          scroll={{ x: 900 }}
          bordered
        />
      </Card>
    </div>
  )
}
