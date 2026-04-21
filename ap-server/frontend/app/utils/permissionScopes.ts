import type { ApAuthorization } from '../composables/useApAuth'

export type PermissionAccessKind = 'direct' | 'descendant' | 'none'

export interface PermissionAccessStatus {
  kind: PermissionAccessKind
  label: string
  tone: 'success' | 'warning' | 'error'
}

export function resolvePermissionAccessStatus(
  authorization: ApAuthorization | null,
  permission: string,
  scopeId?: number
): PermissionAccessStatus {
  if (!scopeId) {
    return {
      kind: 'none',
      label: 'scope 未選択',
      tone: 'error'
    }
  }

  const permissionScope = authorization?.permission_scopes?.[permission]

  if (!permissionScope) {
    return {
      kind: 'none',
      label: '権限なし',
      tone: 'error'
    }
  }

  if (permissionScope.granted_scope_ids.includes(scopeId)) {
    return {
      kind: 'direct',
      label: 'direct grant',
      tone: 'success'
    }
  }

  if (permissionScope.accessible_scope_ids.includes(scopeId)) {
    return {
      kind: 'descendant',
      label: 'descendant access',
      tone: 'warning'
    }
  }

  return {
    kind: 'none',
    label: '権限なし',
    tone: 'error'
  }
}
