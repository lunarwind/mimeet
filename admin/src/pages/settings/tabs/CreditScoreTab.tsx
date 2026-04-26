import { useState, useEffect } from 'react'
import { Card, InputNumber, Button, Typography, Space, Divider, Alert, Tag, Tooltip, Modal, message, Spin } from 'antd'
import { SaveOutlined, UndoOutlined, InfoCircleOutlined } from '@ant-design/icons'
import apiClient from '../../../api/client'

const { Text } = Typography

interface SettingItem {
  key: string
  value: number
  spec_default: number
  description: string
}

// 每個 key 的狀態標籤
const KEY_STATUS: Record<string, { color: string; label: string; tooltip: string }> = {
  // 基準
  credit_score_initial:           { color: 'green',  label: '🟢 自動生效', tooltip: '新用戶建立帳號時套用' },
  credit_score_unblock_threshold: { color: 'green',  label: '🟢 自動生效', tooltip: 'Observer 監控每次分數異動' },
  // 加分
  credit_add_email_verify:        { color: 'green',  label: '🟢 自動生效', tooltip: '用戶完成 Email 驗證時' },
  credit_add_phone_verify:        { color: 'green',  label: '🟢 自動生效', tooltip: '用戶完成手機驗證時' },
  credit_add_adv_verify_male:     { color: 'green',  label: '🟢 自動生效', tooltip: 'ECPay 付款成功後' },
  credit_add_adv_verify_female:   { color: 'green',  label: '🟢 自動生效', tooltip: '管理員審核通過後' },
  credit_add_date_gps:            { color: 'green',  label: '🟢 自動生效', tooltip: 'QR 約會雙方掃碼後' },
  credit_add_date_no_gps:         { color: 'green',  label: '🟢 自動生效', tooltip: 'QR 約會（無 GPS）' },
  credit_add_report_refund:       { color: 'green',  label: '🟢 自動生效', tooltip: '管理員判定不成立或用戶取消' },
  // 扣分 — 自動生效
  credit_sub_report_user:         { color: 'green',  label: '🟢 自動生效', tooltip: '提交或收到一般檢舉時' },
  credit_sub_report_penalty:      { color: 'green',  label: '🟢 自動生效', tooltip: '管理員判定檢舉屬實時' },
  // 扣分 — 管理員手動參考值
  credit_sub_date_noshow:         { color: 'gold',   label: '🟡 管理員手動', tooltip: '管理員在後台認定爽約時使用' },
  credit_sub_bad_content:         { color: 'gold',   label: '🟡 管理員手動', tooltip: '管理員刪除不當內容時勾選扣分' },
  credit_sub_harassment:          { color: 'gold',   label: '🟡 管理員手動', tooltip: '管理員認定惡意騷擾時' },
  // Phase 2
  credit_sub_report_anon:         { color: 'default',label: '⚪ Phase 2 未上線', tooltip: '匿名聊天室功能上線後生效' },
  // 管理員裁量
  credit_admin_reward_min:        { color: 'green',  label: '🟢 自動生效', tooltip: '後台手動加分的最小值' },
  credit_admin_reward_max:        { color: 'green',  label: '🟢 自動生效', tooltip: '後台手動加分的最大值' },
  credit_admin_penalty_min:       { color: 'green',  label: '🟢 自動生效', tooltip: '後台手動扣分的最小值（絕對值）' },
  credit_admin_penalty_max:       { color: 'green',  label: '🟢 自動生效', tooltip: '後台手動扣分的最大值（絕對值）' },
}

const SECTIONS = [
  {
    title: '📊 基準分數',
    keys: ['credit_score_initial', 'credit_score_unblock_threshold'],
  },
  {
    title: '➕ 加分項目（事件自動觸發）',
    keys: [
      'credit_add_email_verify', 'credit_add_phone_verify',
      'credit_add_adv_verify_male', 'credit_add_adv_verify_female',
      'credit_add_date_gps', 'credit_add_date_no_gps',
      'credit_add_report_refund',
    ],
  },
  {
    title: '➖ 扣分項目（正值儲存，Service 內轉負）',
    keys: [
      'credit_sub_report_user', 'credit_sub_report_penalty',
      'credit_sub_date_noshow', 'credit_sub_bad_content',
      'credit_sub_harassment', 'credit_sub_report_anon',
    ],
  },
  {
    title: '👤 管理員裁量範圍（規格對稱 ±20）',
    keys: [
      'credit_admin_reward_min', 'credit_admin_reward_max',
      'credit_admin_penalty_min', 'credit_admin_penalty_max',
    ],
  },
]

export default function CreditScoreTab() {
  const [settings, setSettings] = useState<SettingItem[]>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [resetLoading, setResetLoading] = useState(false)
  const [values, setValues] = useState<Record<string, number>>({})

  useEffect(() => { fetchSettings() }, [])

  async function fetchSettings() {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/settings/credit-score')
      const items: SettingItem[] = res.data.data ?? []
      setSettings(items)
      const v: Record<string, number> = {}
      items.forEach(item => { v[item.key] = item.value })
      setValues(v)
    } catch { message.error('載入失敗') }
    setLoading(false)
  }

  function handleChange(key: string, val: number | null) {
    setValues(prev => ({ ...prev, [key]: val ?? 0 }))
  }

  function resetOne(key: string, specDefault: number) {
    setValues(prev => ({ ...prev, [key]: specDefault }))
  }

  async function handleSave() {
    setSaving(true)
    try {
      const payload = Object.entries(values).map(([key, value]) => ({ key, value }))
      await apiClient.put('/admin/settings/credit-score', { settings: payload })
      message.success('配分已更新，下一筆觸發即生效')
      fetchSettings()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } }
      message.error(e?.response?.data?.message ?? '儲存失敗')
    }
    setSaving(false)
  }

  async function handleResetAll() {
    setResetLoading(true)
    try {
      await apiClient.post('/admin/settings/credit-score/reset')
      message.success('所有配分已還原為規格預設值')
      fetchSettings()
    } catch { message.error('還原失敗') }
    setResetLoading(false)
  }

  function getItem(key: string): SettingItem | undefined {
    return settings.find(s => s.key === key)
  }

  if (loading) return <Spin style={{ display: 'block', margin: '40px auto' }} />

  return (
    <div style={{ maxWidth: 900 }}>
      <Alert
        type="info"
        showIcon
        icon={<InfoCircleOutlined />}
        message="⚡ 儲存後即時生效"
        description="配分修改送出後立即套用至後續所有觸發事件，無需等待快取過期。歷史紀錄不回溯重算。扣分項目請輸入正整數，系統內部自動轉為負數。"
        style={{ marginBottom: 20 }}
      />

      {SECTIONS.map(section => (
        <Card
          key={section.title}
          title={<span style={{ fontSize: 14, fontWeight: 600 }}>{section.title}</span>}
          style={{ marginBottom: 16 }}
          size="small"
        >
          <div style={{ display: 'grid', gap: 12 }}>
            {section.keys.map(key => {
              const item = getItem(key)
              if (!item) return null
              const status = KEY_STATUS[key]
              const isChanged = values[key] !== item.spec_default
              return (
                <div key={key} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '8px 0', borderBottom: '1px solid #f0f0f0' }}>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <Text strong style={{ fontSize: 13 }}>{item.description}</Text>
                    <br />
                    <Text type="secondary" style={{ fontSize: 11 }}>{key}</Text>
                    {isChanged && (
                      <Tag color="orange" style={{ marginLeft: 6, fontSize: 10 }}>已修改</Tag>
                    )}
                  </div>
                  <Tooltip title={`規格預設：${item.spec_default}`}>
                    <Tag color={status?.color ?? 'default'} style={{ fontSize: 11, flexShrink: 0 }}>
                      {status?.label ?? key}
                    </Tag>
                  </Tooltip>
                  <InputNumber
                    min={0}
                    max={100}
                    value={values[key] ?? item.value}
                    onChange={val => handleChange(key, val)}
                    style={{ width: 80 }}
                    size="small"
                  />
                  <Tooltip title={`還原為規格預設值 ${item.spec_default}`}>
                    <Button
                      size="small"
                      icon={<UndoOutlined />}
                      onClick={() => resetOne(key, item.spec_default)}
                      disabled={values[key] === item.spec_default}
                    />
                  </Tooltip>
                </div>
              )
            })}
          </div>
        </Card>
      ))}

      <Divider />

      <Space>
        <Button
          type="primary"
          icon={<SaveOutlined />}
          loading={saving}
          onClick={handleSave}
        >
          儲存所有修改
        </Button>
        <Button
          danger
          loading={resetLoading}
          onClick={() =>
            Modal.confirm({
              title: '確認還原所有配分？',
              content: '將把全部 19 個 key 還原為 DEV-008 規格預設值，此操作不可逆。',
              okText: '確認還原',
              cancelText: '取消',
              okButtonProps: { danger: true },
              onOk: handleResetAll,
            })
          }
        >
          全部還原預設值
        </Button>
      </Space>
    </div>
  )
}
