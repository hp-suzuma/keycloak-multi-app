import type { ApAuthorization, ApCurrentUser } from '../composables/useApAuth'

export interface DashboardNavItem {
  label: string
  to: string
  icon: string
  description: string
}

export interface DashboardNavGroup {
  title: string
  items: DashboardNavItem[]
}

export function dashboardHomeTitle(authorization: ApAuthorization | null) {
  const layer = authorization?.assignments[0]?.scope.layer

  switch (layer) {
    case 'server':
      return 'Server Dashboard'
    case 'service':
      return 'Service Dashboard'
    case 'tenant':
      return 'Tenant Dashboard'
    default:
      return 'Dashboard'
  }
}

export function dashboardSidebarMeta(user: ApCurrentUser | null, authorization: ApAuthorization | null) {
  const primaryAssignment = authorization?.assignments[0]

  if (!user) {
    return {
      line1: 'ゲスト',
      line2: 'CurrentUser 未取得'
    }
  }

  if (!primaryAssignment) {
    return {
      line1: user.name,
      line2: '権限未割り当て'
    }
  }

  return {
    line1: primaryAssignment.scope.name,
    line2: primaryAssignment.role.name
  }
}

export function dashboardRoleBadge(authorization: ApAuthorization | null) {
  const layer = authorization?.assignments[0]?.scope.layer

  switch (layer) {
    case 'server':
      return {
        label: 'Server Console',
        icon: 'i-lucide-network'
      }
    case 'service':
      return {
        label: 'Service Console',
        icon: 'i-lucide-building-2'
      }
    case 'tenant':
      return {
        label: 'Tenant Console',
        icon: 'i-lucide-house'
      }
    default:
      return {
        label: 'Prototype Console',
        icon: 'i-lucide-layout-dashboard'
      }
  }
}

export function dashboardNavGroups(user: ApCurrentUser | null, authorization: ApAuthorization | null): DashboardNavGroup[] {
  if (!user) {
    return [
      {
        title: 'Overview',
        items: [
          {
            label: 'Home',
            to: '/',
            icon: 'i-lucide-house',
            description: 'ログイン後ホーム'
          }
        ]
      }
    ]
  }

  const permissions = new Set(authorization?.permissions ?? [])
  const groups: DashboardNavGroup[] = [
    {
      title: 'Overview',
      items: [
        {
          label: 'Dashboard',
          to: '/',
          icon: 'i-lucide-layout-dashboard',
          description: 'ログイン後のホーム'
        }
      ]
    }
  ]

  if (permissions.has('user.manage')) {
    groups.push({
      title: 'Management',
      items: [
        {
          label: 'Users',
          to: '/users',
          icon: 'i-lucide-users',
          description: 'ユーザー管理'
        }
      ]
    })
  }

  const objectItems: DashboardNavItem[] = []

  if (permissions.has('object.read')) {
    objectItems.push(
      {
        label: 'Objects',
        to: '/objects',
        icon: 'i-lucide-box',
        description: '業務オブジェクト'
      },
      {
        label: 'Playbooks',
        to: '/playbooks',
        icon: 'i-lucide-book-open-text',
        description: '運用プレイブック'
      },
      {
        label: 'Policies',
        to: '/policies',
        icon: 'i-lucide-shield-check',
        description: 'ポリシー一覧'
      },
      {
        label: 'Checklists',
        to: '/checklists',
        icon: 'i-lucide-list-checks',
        description: 'チェックリスト'
      }
    )
  }

  if (objectItems.length > 0) {
    groups.push({
      title: 'Operations',
      items: objectItems
    })
  }

  groups.push({
    title: 'Settings',
    items: [
      {
        label: 'Security',
        to: '/settings/security',
        icon: 'i-lucide-lock-keyhole',
        description: '認証とアクセス設定'
      }
    ]
  })

  return groups
}
