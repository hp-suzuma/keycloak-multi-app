type AuthRecoveryKind = 'none' | 'setup' | 'refresh' | 'retry'
type AuthRecoverySurface = 'auth-entry' | 'users-index' | 'users-detail'

interface AuthRecoveryCopyOptions {
  errorMessage?: string | null
}

interface AuthRecoveryCopy {
  title: string
  body: string | null
  steps: string[]
  logoutHint: string | null
}

function getSurfaceLabel(surface: AuthRecoverySurface) {
  if (surface === 'users-index') {
    return 'users 一覧'
  }

  if (surface === 'users-detail') {
    return 'users 詳細'
  }

  return 'Auth Entry'
}

function getFailureLabel(surface: AuthRecoverySurface) {
  if (surface === 'users-index') {
    return 'users API'
  }

  if (surface === 'users-detail') {
    return 'assignment 系 API や users 詳細 API'
  }

  return 'live API'
}

export function buildAuthRecoveryCopy(
  kind: AuthRecoveryKind,
  surface: AuthRecoverySurface,
  options: AuthRecoveryCopyOptions = {}
): AuthRecoveryCopy {
  if (kind === 'setup') {
    return {
      title: 'Session Setup',
      body: `${getSurfaceLabel(surface)} の実運用向け復旧導線は \`SSO Login\` です。 \`global.example.com/login\` へ戻して session を張り直し、Auth Entry の token 設定は live debug 用としてだけ使います。`,
      steps: surface === 'auth-entry'
        ? [
            '1. 実運用では `SSO Login` から global login へ戻る',
            '2. live debug が必要な時だけ Bearer token を貼って `Apply & Refresh` を押す',
            '3. `Current User` と `permissions` が埋まることを確認する'
          ]
        : [],
      logoutHint: surface === 'auth-entry'
        ? null
        : '`SSO Logout` は右上のユーザーメニューにあり、実行後は `Auth Entry` へ戻ります。live session を手放して切り分け直したい時はこちらを使います。'
    }
  }

  if (kind === 'refresh') {
    return {
      title: 'Re-auth Required',
      body: `期限切れ token では Auth Entry が \`null current_user\` に見え、${getFailureLabel(surface)} は \`403\` になりやすいので、まず \`SSO Login\` へ戻します。live debug を続ける時だけ fresh token を取り直して再確認してください。`,
      steps: surface === 'auth-entry'
        ? [
            '1. 実運用では `SSO Login` から global login へ戻る',
            '2. live debug が必要な時だけ fresh token を取り直して `Apply & Refresh` を押す',
            '3. `Current User` が復帰したあと users 画面へ戻る'
          ]
        : [],
      logoutHint: surface === 'auth-entry'
        ? null
        : '`SSO Logout` は右上のユーザーメニューにあり、実行後は `Auth Entry` へ戻ります。session を閉じてから復帰導線を踏み直したい時はこちらを使います。'
    }
  }

  if (kind === 'retry') {
    return {
      title: 'API Retry',
      body: options.errorMessage
        ?? `${getFailureLabel(surface)} で \`401/403\` が続く時は、実運用では \`SSO Login\` へ戻します。live debug の切り分けでは Auth Entry で token と API Base を確認してから再試行してください。`,
      steps: surface === 'auth-entry'
        ? [
            '1. 実運用では `SSO Login` から global login へ戻る',
            '2. live debug が必要な時だけ fresh token を取り直して `Apply & Refresh` を押す',
            '3. `Current User` が復帰したあと users 画面へ戻る'
          ]
        : [],
      logoutHint: surface === 'auth-entry'
        ? null
        : '`SSO Logout` は右上のユーザーメニューにあり、実行後は `Auth Entry` へ戻ります。session をいったん閉じてから再確認したい時はこちらを使います。'
    }
  }

  return {
    title: 'Auth Status',
    body: null,
    steps: [],
    logoutHint: null
  }
}
