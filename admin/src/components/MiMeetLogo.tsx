interface MiMeetLogoProps {
  variant?: 'light' | 'dark'
  size?: 'sm' | 'md' | 'lg'
  style?: React.CSSProperties
}

const sizeMap = { sm: 18, md: 22, lg: 32 }

export default function MiMeetLogo({ variant = 'light', size = 'md', style }: MiMeetLogoProps) {
  return (
    <span style={{
      fontFamily: "'Noto Serif TC', serif",
      fontWeight: 600,
      fontSize: sizeMap[size],
      letterSpacing: '-0.5px',
      lineHeight: 1,
      display: 'inline-flex',
      alignItems: 'baseline',
      ...style,
    }}>
      <span style={{ color: '#F0294E' }}>Mi</span>
      <span style={{ color: variant === 'dark' ? '#FFFFFF' : '#111827' }}>Meet</span>
    </span>
  )
}
