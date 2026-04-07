export function mockLogin(_data: any) {
  return {
    success: true,
    data: {
      user: {
        id: 1,
        email: 'test@mimeet.tw',
        nickname: '測試用戶',
        avatar: null,
        gender: 'male',
        status: 'active',
        credit_score: 75,
        membership_level: 1,
        verified: '1',
      },
      tokens: {
        access_token: 'mock-token-12345',
        refresh_token: 'mock-refresh-token',
        token_type: 'Bearer',
        expires_in: 3600,
      },
    },
  }
}

export function mockRegister(data: any) {
  const payload = data?.data ?? data ?? {}
  return {
    success: true,
    code: 201,
    message: '註冊成功，請查收驗證郵件',
    data: {
      user: {
        id: 2,
        email: payload.email ?? 'new@mimeet.tw',
        nickname: payload.nickname ?? '新用戶',
        gender: payload.gender ?? 'male',
        group: payload.group ?? 1,
        status: 'pending_verification',
        created_at: new Date().toISOString(),
      },
      verification: {
        email_sent: true,
        expires_at: new Date(Date.now() + 3600000).toISOString(),
      },
    },
  }
}

export function mockMe() {
  return {
    success: true,
    data: {
      user: {
        id: 1,
        email: 'test@mimeet.tw',
        nickname: '測試用戶',
        avatar: null,
        gender: 'male',
        status: 'active',
        credit_score: 75,
        membership_level: 1,
        verified: '1',
      },
    },
  }
}
