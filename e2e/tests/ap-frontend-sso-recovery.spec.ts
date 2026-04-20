import { expect, test, type Page } from '@playwright/test'

const usersPath = '/users?service_scope_id=2&tenant_scope_id=3&keyword=alice&sort=-email'
const usersBobPath = '/users?service_scope_id=2&tenant_scope_id=3&keyword=bob&sort=-email'
const serviceOnlyUsersAlicePath = '/users?service_scope_id=2&keyword=alice&sort=-email'
const usersListWithoutKeywordPath = '/users?service_scope_id=2&tenant_scope_id=3&sort=-email'
const usersDetailPath = '/users/tenant-user-b?service_scope_id=2&tenant_scope_id=3&sort=-email'
const usersDetailWithKeywordPath = '/users/tenant-user-b?service_scope_id=2&tenant_scope_id=3&keyword=bob&sort=-email'
const serviceOnlyUsersDetailAlicePath = '/users/tenant-user-a?service_scope_id=2&keyword=alice&sort=-email'
const authEntryPath = '/?logged_out=1#auth-entry'

async function submitKeycloakLogin(page: Page) {
  await page.locator('input[name="username"]').fill(process.env.KEYCLOAK_USERNAME ?? 'alice')
  await page.locator('input[name="password"]').fill(process.env.KEYCLOAK_PASSWORD ?? 'password')
  await page.getByRole('button', { name: /sign in|log in/i }).click()
}

function buildGlobalSsoLoginUrl(nextPath = usersPath) {
  const globalLoginUrl = new URL('https://global.example.com/login')
  globalLoginUrl.searchParams.set(
    'return_to',
    `https://ap.example.com/auth/bridge?next=${encodeURIComponent(nextPath)}`
  )

  return globalLoginUrl.toString()
}

async function loginViaGlobalSso(page: Page, nextPath = usersPath) {
  await page.goto(buildGlobalSsoLoginUrl(nextPath))
  await submitKeycloakLogin(page)
  await page.waitForURL(`**${nextPath}`)
}

async function openUserMenu(page: Page) {
  await page.getByRole('button', { name: 'Alice A' }).click()
}

async function closeUserMenu(page: Page) {
  await page.keyboard.press('Escape')
}

function tenantOperatorAssignmentCard(page: Page) {
  return page
    .locator('section')
    .filter({ hasText: 'Tenant Operator' })
    .filter({ hasText: 'tenant_operator' })
    .filter({ has: page.getByRole('button', { name: 'Remove' }) })
}

async function ensureTenantOperatorAssignmentAbsent(page: Page) {
  const operatorAssignmentCard = tenantOperatorAssignmentCard(page)

  if (await operatorAssignmentCard.count() === 0) {
    return
  }

  await operatorAssignmentCard.getByRole('button', { name: 'Remove' }).click()
  await page.getByRole('button', { name: 'Remove' }).last().click()
  await expect(page.getByText('assignment を削除しました。')).toBeVisible()
  await expect(operatorAssignmentCard).toHaveCount(0)
}

test('AP Frontend recovers to the same users query after SSO login', async ({ page }) => {
  await loginViaGlobalSso(page)

  await expect(page.getByRole('main').getByText('Alice A', { exact: true })).toBeVisible()
  await expect(page.getByRole('main').getByText(/user\.manage:\s+descendant access/i)).toBeVisible()
})

test('AP Frontend clears the local session and returns to Auth Entry after SSO logout', async ({ page }) => {
  await loginViaGlobalSso(page, usersListWithoutKeywordPath)

  await openUserMenu(page)
  await expect(page.getByRole('menuitem', { name: 'SSO Logout' })).toBeVisible()
  await closeUserMenu(page)

  await page.getByRole('main').getByRole('link').filter({ hasText: 'tenant-user-b' }).first().click()
  await page.waitForURL('**/users/tenant-user-b**')

  await openUserMenu(page)
  await expect(page.getByRole('menuitem', { name: 'SSO Logout' })).toBeVisible()
  await page.getByRole('menuitem', { name: 'SSO Logout' }).click()

  await page.waitForURL(`**${authEntryPath}`)

  await expect(page.getByRole('link', { name: 'SSO Login' })).toBeVisible()
  await expect(page.getByText('Logout Complete')).toBeVisible()
  await expect(page.getByText('Bearer Token: missing')).toBeVisible()
  await expect(page.getByText('Not resolved yet')).toBeVisible()
  await expect(page.getByText(/global SSO logout が完了.*local token もクリア/i)).toBeVisible()
})

test('AP Frontend returns to the same users detail context after logout and SSO re-login', async ({ page }) => {
  await loginViaGlobalSso(page, usersListWithoutKeywordPath)

  await page.getByRole('main').getByRole('link').filter({ hasText: 'tenant-user-b' }).first().click()
  await page.waitForURL(`**${usersDetailPath}`)

  await expect(page.getByRole('heading', { name: 'Bob B' })).toBeVisible()

  await openUserMenu(page)
  await page.getByRole('menuitem', { name: 'SSO Logout' }).click()

  await page.waitForURL(`**${authEntryPath}`)
  const ssoLoginLink = page.getByRole('link', { name: 'SSO Login' })
  await expect(ssoLoginLink).toBeVisible()
  await ssoLoginLink.click()
  await submitKeycloakLogin(page)

  await page.waitForURL(`**${usersDetailPath}`)
  await expect(page.getByRole('heading', { name: 'Bob B' })).toBeVisible()
  await page.getByRole('link', { name: '一覧へ戻る' }).click()
  await page.waitForURL(`**${usersListWithoutKeywordPath}`)
})

test('AP Frontend preserves keyword and detail assignment context after logout and SSO re-login', async ({ page }) => {
  await loginViaGlobalSso(page, usersBobPath)

  await expect(page.getByText('keyword: bob')).toBeVisible()
  await page.getByRole('main').getByRole('link').filter({ hasText: 'tenant-user-b' }).first().click()
  await page.waitForURL(`**${usersDetailWithKeywordPath}`)
  await expect(page.getByRole('heading', { name: 'Bob B' })).toBeVisible()
  await ensureTenantOperatorAssignmentAbsent(page)

  await page.getByPlaceholder('role 名 / slug / permission で絞り込み').fill('tenant_operator')
  await page
    .locator('label')
    .filter({ hasText: 'Role' })
    .locator('select')
    .last()
    .selectOption({ label: 'Tenant Operator (tenant_operator)' })
  await page.getByRole('button', { name: 'Add Assignment' }).click()

  await expect(page.getByText('assignment を追加しました。')).toBeVisible()

  const operatorAssignmentCard = tenantOperatorAssignmentCard(page)
  await expect(operatorAssignmentCard).toBeVisible()

  await openUserMenu(page)
  await page.getByRole('menuitem', { name: 'SSO Logout' }).click()

  await page.waitForURL(`**${authEntryPath}`)
  const ssoLoginLink = page.getByRole('link', { name: 'SSO Login' })
  await expect(ssoLoginLink).toBeVisible()
  await ssoLoginLink.click()
  await submitKeycloakLogin(page)

  await page.waitForURL(`**${usersDetailWithKeywordPath}`)
  await expect(page.getByRole('heading', { name: 'Bob B' })).toBeVisible()
  await expect(operatorAssignmentCard).toBeVisible()

  await operatorAssignmentCard.getByRole('button', { name: 'Remove' }).click()
  await page.getByRole('button', { name: 'Remove' }).last().click()
  await expect(page.getByText('assignment を削除しました。')).toBeVisible()
  await expect(operatorAssignmentCard).toHaveCount(0)

  await page.getByRole('link', { name: '一覧へ戻る' }).click()
  await page.waitForURL(`**${usersBobPath}`)
  await expect(page.getByText('keyword: bob')).toBeVisible()
})

test('AP Frontend preserves service-only query context after logout and SSO re-login', async ({ page }) => {
  await loginViaGlobalSso(page, serviceOnlyUsersAlicePath)

  await expect(page.getByText('service: Service Alpha')).toBeVisible()
  await expect(page.getByText('keyword: alice')).toBeVisible()
  await expect(page.getByText('tenant: Tenant A')).toHaveCount(0)

  await page.getByRole('main').getByRole('link').filter({ hasText: 'tenant-user-a' }).first().click()
  await page.waitForURL(`**${serviceOnlyUsersDetailAlicePath}`)
  await expect(page.getByRole('heading', { name: 'Alice A' })).toBeVisible()

  await openUserMenu(page)
  await page.getByRole('menuitem', { name: 'SSO Logout' }).click()

  await page.waitForURL(`**${authEntryPath}`)
  const ssoLoginLink = page.getByRole('link', { name: 'SSO Login' })
  await expect(ssoLoginLink).toBeVisible()
  await ssoLoginLink.click()
  await submitKeycloakLogin(page)

  await page.waitForURL(`**${serviceOnlyUsersDetailAlicePath}`)
  await expect(page.getByRole('heading', { name: 'Alice A' })).toBeVisible()
  await page.getByRole('link', { name: '一覧へ戻る' }).click()
  await page.waitForURL(`**${serviceOnlyUsersAlicePath}`)
  await expect(page.getByText('service: Service Alpha')).toBeVisible()
  await expect(page.getByText('keyword: alice')).toBeVisible()
  await expect(page.getByText('tenant: Tenant A')).toHaveCount(0)
})
