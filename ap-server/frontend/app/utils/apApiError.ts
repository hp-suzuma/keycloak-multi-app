export function describeApApiError(cause: unknown, fallback: string): string {
  if (typeof cause !== 'object' || cause === null) {
    return fallback
  }

  const error = cause as {
    data?: { message?: string, errors?: Record<string, string[]> }
    response?: { status?: number }
    status?: number
    statusCode?: number
    statusMessage?: string
    message?: string
  }

  const status = error.status ?? error.statusCode ?? error.response?.status
  const firstValidationError = error.data?.errors
    ? Object.values(error.data.errors).flat()[0]
    : undefined

  if (status === 403) {
    return '403 Forbidden です。Bearer token の期限切れか権限不足の可能性があります。fresh token を取り直して Auth Entry で再取得してください。'
  }

  if (status === 401) {
    return '401 Unauthorized です。Bearer token を取り直して Auth Entry で再取得してください。'
  }

  if (error.message?.includes('Failed to fetch')) {
    return 'API へ接続できませんでした。`ap-backend-fpm.example.com` の hosts と証明書許可を確認してから再試行してください。'
  }

  return firstValidationError ?? error.data?.message ?? error.statusMessage ?? error.message ?? fallback
}
