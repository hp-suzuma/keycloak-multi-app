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
    return '403 Forbidden です。期限切れ token か権限不足の可能性があります。実運用では `SSO Login` へ戻して session を張り直し、debug を続ける時だけ Auth Entry で fresh token と API Base を確認してください。'
  }

  if (status === 401) {
    return '401 Unauthorized です。session が外れている可能性があります。実運用では `SSO Login` へ戻して session を張り直し、debug を続ける時だけ Auth Entry で Bearer token と API Base を確認してください。'
  }

  if (error.message?.includes('Failed to fetch')) {
    return 'API へ接続できませんでした。まず `ap-backend-fpm.example.com` の hosts と証明書許可を確認してください。network 前提が直ったあとに session 切れが残る場合だけ、`SSO Login` か Auth Entry Debug で再確認します。'
  }

  return firstValidationError ?? error.data?.message ?? error.statusMessage ?? error.message ?? fallback
}
