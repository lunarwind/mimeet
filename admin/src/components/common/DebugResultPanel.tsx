import { useState } from 'react'

interface DebugResult {
  success: boolean
  elapsed_ms?: number | null
  debug_log?: string[]
  debug_text?: string
  error_detail?: Record<string, unknown> | null
  data?: Record<string, unknown>
  [key: string]: unknown
}

export default function DebugResultPanel({ result, isLoading }: { result: DebugResult | null; isLoading: boolean }) {
  const [showRaw, setShowRaw] = useState(false)

  if (isLoading) {
    return (
      <div style={{ marginTop: 12, padding: '12px 16px', background: '#1e1e2e', borderRadius: 8, border: '1px solid #3b3b52', color: '#89b4fa', fontFamily: 'monospace', fontSize: 13 }}>
        ⏳ 發送中，請稍候...
      </div>
    )
  }

  if (!result) return null

  const ok = result.success
  const borderColor = ok ? '#2d5a2d' : '#5a2d2d'
  const bgColor = ok ? '#1a2e1a' : '#2e1a1a'
  const headerBg = ok ? '#1e3a1e' : '#3a1e1e'

  return (
    <div style={{ marginTop: 12, borderRadius: 8, border: `1px solid ${borderColor}`, background: bgColor, overflow: 'hidden', fontFamily: 'monospace', fontSize: 13 }}>
      {/* Header */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '8px 14px', background: headerBg, borderBottom: `1px solid ${borderColor}` }}>
        <span style={{ color: ok ? '#a6e3a1' : '#f38ba8', fontWeight: 600 }}>
          {ok ? '✅ 發送成功' : '❌ 發送��敗'}
          {result.elapsed_ms != null && <span style={{ color: '#a6adc8', marginLeft: 10, fontWeight: 400 }}>({result.elapsed_ms}ms)</span>}
        </span>
        <button onClick={() => setShowRaw(!showRaw)} style={{ background: 'transparent', border: '1px solid #585b70', color: '#cdd6f4', borderRadius: 4, padding: '2px 8px', cursor: 'pointer', fontSize: 11 }}>
          {showRaw ? '隱藏 JSON' : '原始 JSON'}
        </button>
      </div>

      {/* Debug Log */}
      {result.debug_log && result.debug_log.length > 0 && (
        <div style={{ padding: '10px 14px' }}>
          <div style={{ color: '#6c7086', fontSize: 11, marginBottom: 6 }}>DEBUG LOG</div>
          {result.debug_log.map((line, i) => {
            let color = '#cdd6f4'
            if (line.includes('✅')) color = '#a6e3a1'
            else if (line.includes('❌')) color = '#f38ba8'
            else if (line.includes('診斷建議')) color = '#fab387'
            else if (line.startsWith('  ')) color = '#89dceb'
            else if (line.startsWith('[')) color = '#89b4fa'
            return <div key={i} style={{ color, lineHeight: 1.7, whiteSpace: 'pre', fontSize: line.startsWith('  ') ? 12 : 13 }}>{line}</div>
          })}
        </div>
      )}

      {/* Error Detail */}
      {!ok && result.error_detail && (
        <div style={{ padding: '8px 14px', borderTop: '1px solid #3b3b52', background: '#1e1e2e' }}>
          <div style={{ color: '#6c7086', fontSize: 11, marginBottom: 6 }}>ERROR DETAIL</div>
          <pre style={{ color: '#f38ba8', fontSize: 12, margin: 0, whiteSpace: 'pre-wrap', wordBreak: 'break-all' }}>
            {JSON.stringify(result.error_detail, null, 2)}
          </pre>
        </div>
      )}

      {/* Raw JSON */}
      {showRaw && (
        <div style={{ padding: '8px 14px', borderTop: '1px solid #3b3b52', background: '#11111b' }}>
          <div style={{ color: '#6c7086', fontSize: 11, marginBottom: 6 }}>RAW RESPONSE</div>
          <pre style={{ color: '#cdd6f4', fontSize: 11, margin: 0, whiteSpace: 'pre-wrap', wordBreak: 'break-all', maxHeight: 300, overflowY: 'auto' }}>
            {JSON.stringify(result, null, 2)}
          </pre>
        </div>
      )}

      {/* Copy buttons */}
      <div style={{ padding: '6px 14px', borderTop: '1px solid #3b3b52', background: '#181825', display: 'flex', gap: 8 }}>
        <button onClick={() => navigator.clipboard.writeText(result.debug_text || JSON.stringify(result, null, 2))} style={{ background: 'transparent', border: '1px solid #585b70', color: '#a6adc8', borderRadius: 4, padding: '3px 10px', cursor: 'pointer', fontSize: 11 }}>
          📋 複製 Log
        </button>
        <button onClick={() => navigator.clipboard.writeText(JSON.stringify(result, null, 2))} style={{ background: 'transparent', border: '1px solid #585b70', color: '#a6adc8', borderRadius: 4, padding: '3px 10px', cursor: 'pointer', fontSize: 11 }}>
          📋 複��� JSON
        </button>
      </div>
    </div>
  )
}
