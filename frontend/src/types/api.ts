// 統一 API Response 格式
export interface ApiResponse<T = unknown> {
  success: boolean
  data: T
  message?: string
}

export interface PaginatedData<T> {
  items: T[]
  meta: {
    total: number
    page: number
    perPage: number
    lastPage: number
  }
}

// 422 表單驗證錯誤格式
export interface ValidationError {
  message: string
  errors: Record<string, string[]>
}
