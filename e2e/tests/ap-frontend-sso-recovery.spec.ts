import { expect, test, type Page } from '@playwright/test'

const usersPath = '/users?service_scope_id=2&tenant_scope_id=3&keyword=alice&sort=-email'
const usersListWithoutKeywordPath = '/users?service_scope_id=2&tenant_scope_id=3&sort=-email'
const usersDetailPath = '/users/tenant-user-b?service_scope_id=2&tenant_scope_id=3&sort=-email'
const authEntryPath = '/?logged_out=1#auth-entry'

async function submitKeycloakLogin(page: Page) {
  await page.locator('input[name="username"]').fill(process.env.KEYCLOAK_USERNAME ?? 'alice')
  await page.locator('input[name="password"]').fill(process.env.KEYCLOAK_PASSWORD ?? 'password')
  await page.getByRole('button', { name: /sign in|log in/i }).click()
}

async function loginViaGlobalSso(page: Page, nextPath = usersPath) {
  const globalLoginUrl = new URL('https://global.example.com/login')
  globalLoginUrl.searchParams.set(
    'return_to',
    `https://ap.example.com/auth/bridge?next=${encodeURIComponent(nextPath)}`
  )

  await page.goto(globalLoginUrl.toString())
  await submitKeycloakLogin(page)
  await page.waitForURL(`**${nextPath}`)
}

async function openUserMenu(page: Page) {
  await page.getByRole('button', { name: 'Alice A' }).click()
}

async function closeUserMenu(page: Page) {
  await page.keyboard.press('Escape')
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

  await expect(page.getByText('Logout Complete')).toBeVisible()
  await expect(page.getByRole('link', { name: 'SSO Login' })).toBeVisible()
  await expect(page.getByText('Bearer Token: missing')).toBeVisible()
  await expect(page.getByText('Not resolved yet')).toBeVisible()
  await expect(page.getByText(/SSO Logout.*Auth Entry/i)).toBeVisible()
})

test('AP Frontend returns to the same users detail context after logout and SSO re-login', async ({ page }) => {
  await loginViaGlobalSso(page, usersListWithoutKeywordPath)

  await page.getByRole('main').getByRole('link').filter({ hasText: 'tenant-user-b' }).first().click()
  await page.waitForURL(`**${usersDetailPath}`)

  await expect(page.getByRole('heading', { name: 'Bob B' })).toBeVisible()

  await openUserMenu(page)
  await page.getByRole('menuitem', { name: 'SSO Logout' }).click()

  await page.waitForURL(`**${authEntryPath}`)
  await expect(page.getByRole('link', { name: 'SSO Login' })).toBeVisible()
  await page.getByRole('link', { name: 'SSO Login' }).click()
  await submitKeycloakLogin(page)

  await page.waitForURL(`**${usersDetailPath}`)
  await expect(page.getByRole('heading', { name: 'Bob B' })).toBeVisible()
  await page.getByRole('link', { name: '一覧へ戻る' }).click()
  await page.waitForURL(`**${usersListWithoutKeywordPath}`)
})
