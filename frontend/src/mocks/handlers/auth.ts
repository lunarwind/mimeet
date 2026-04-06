export function mockLogin(_data: any) {
  return {
    success: true,
    data: {
      token: 'mock-token-12345',
      user: {
        id: 1,
        uuid: 'mock-uuid-001',
        nickname: '測試用戶',
        gender: 'male',
        membershipLevel: 1,
        isPaid: false,
        creditScore: 75,
        status: 'active',
        avatarUrl: null,
      },
    },
  }
}

export function mockRegister(_data: any) {
  return {
    success: true,
    data: {
      message: '註冊成功，請驗證 Email',
    },
  }
}

export function mockMe() {
  return {
    success: true,
    data: {
      id: 1,
      uuid: 'mock-uuid-001',
      nickname: '測試用戶',
      gender: 'male',
      membershipLevel: 1,
      isPaid: false,
      creditScore: 75,
      status: 'active',
      avatarUrl: null,
    },
  }
}
