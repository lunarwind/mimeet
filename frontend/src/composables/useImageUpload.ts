/**
 * useImageUpload.ts
 * 圖片上傳 composable（頭像/相冊/回報截圖）
 * 對應 API-001 §16
 */
import { ref } from 'vue'
import client from '@/api/client'

const USE_MOCK = import.meta.env.DEV

const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const MAX_SIZE = 5 * 1024 * 1024 // 5MB

type UploadContext = 'avatar' | 'profile_photo' | 'report_image'

interface UploadResult {
  url: string
  originalFilename: string
}

function delay(ms: number) {
  return new Promise(resolve => setTimeout(resolve, ms))
}

/** Canvas 壓縮產生縮圖預覽（200x200） */
function createThumbnail(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const img = new Image()
    const url = URL.createObjectURL(file)
    img.onload = () => {
      const canvas = document.createElement('canvas')
      canvas.width = 200
      canvas.height = 200
      const ctx = canvas.getContext('2d')
      if (!ctx) { reject(new Error('Canvas not supported')); return }
      // 裁切為正方形
      const size = Math.min(img.width, img.height)
      const sx = (img.width - size) / 2
      const sy = (img.height - size) / 2
      ctx.drawImage(img, sx, sy, size, size, 0, 0, 200, 200)
      URL.revokeObjectURL(url)
      resolve(canvas.toDataURL('image/jpeg', 0.7))
    }
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Image load failed')) }
    img.src = url
  })
}

export function useImageUpload() {
  const isUploading = ref(false)
  const uploadProgress = ref(0)
  const error = ref<string | null>(null)
  const preview = ref<string | null>(null)

  function validate(file: File): string | null {
    if (!ALLOWED_TYPES.includes(file.type)) {
      return '僅支援 JPG、PNG、WebP 格式'
    }
    if (file.size > MAX_SIZE) {
      return '檔案大小不能超過 5MB'
    }
    return null
  }

  async function upload(file: File, context: UploadContext): Promise<UploadResult | null> {
    error.value = null
    uploadProgress.value = 0

    const validationError = validate(file)
    if (validationError) {
      error.value = validationError
      return null
    }

    // 產生預覽
    try {
      preview.value = await createThumbnail(file)
    } catch {
      // 預覽失敗不阻擋上傳
    }

    isUploading.value = true

    try {
      if (USE_MOCK) {
        // 模擬上傳進度
        for (let i = 0; i <= 100; i += 20) {
          uploadProgress.value = i
          await delay(300)
        }
        const randomId = Math.floor(Math.random() * 70) + 1
        return {
          url: `https://i.pravatar.cc/400?img=${randomId}`,
          originalFilename: file.name,
        }
      }

      const formData = new FormData()
      formData.append('file', file)
      formData.append('context', context)

      const res = await client.post<{
        data: { url: string; original_filename: string }
      }>('/uploads', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (e) => {
          if (e.total) {
            uploadProgress.value = Math.round((e.loaded / e.total) * 100)
          }
        },
      })

      return {
        url: res.data.data.url,
        originalFilename: res.data.data.original_filename,
      }
    } catch (e) {
      error.value = '上傳失敗，請稍後再試'
      console.error('[useImageUpload] upload error:', e)
      return null
    } finally {
      isUploading.value = false
    }
  }

  function uploadAvatar(file: File) {
    return upload(file, 'avatar')
  }

  function uploadPhoto(file: File) {
    return upload(file, 'profile_photo')
  }

  function uploadReport(file: File) {
    return upload(file, 'report_image')
  }

  function reset() {
    error.value = null
    uploadProgress.value = 0
    preview.value = null
    isUploading.value = false
  }

  return {
    isUploading,
    uploadProgress,
    error,
    preview,
    upload,
    uploadAvatar,
    uploadPhoto,
    uploadReport,
    validate,
    reset,
  }
}
