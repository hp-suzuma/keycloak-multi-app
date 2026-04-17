import { expect, test, type Page } from '@playwright/test'

const usersPath = '/users?service_scope_id=2&tenant_scope_id=3&keyword=alice&sort=-email'
const authEntryPath = '/?logged_out=1#auth-entry'

async function loginViaGlobalSso(page: Page) {
  const globalLoginUrl = new URL('https://global.example.com/login')
  globalLoginUrl.searchParams.set(
    'return_to',
    `https://ap.example.com/auth/bridge?next=${encodeURIComponent(usersPath)}`
  )

  await page.goto(globalLoginUrl.toString())

  await page.locator('input[name="username"]').fill(process.env.KEYCLOAK_USERNAME ?? 'alice')
  await page.locator('input[name="password"]').fill(process.env.KEYCLOAK_PASSWORD ?? 'password')
  await page.getByRole('button', { name: /sign in|log in/i }).click()
  await page.waitForURL(`**${usersPath}`)
}

test('AP Frontend recovers to the same users query after SSO login', async ({ page }) => {
  await loginViaGlobalSso(page)

  await expect(page.getByRole('main').getByText('Alice A', { exact: true })).toBeVisible()
  await expect(page.getByRole('main').getByText(/user\.manage:\s+descendant access/i)).toBeVisible()
})

test('AP Frontend clears the local session and returns to Auth Entry after SSO logout', async ({ page }) => {
  await loginViaGlobalSso(page)

  await page.getByRole('button', { name: 'Alice A' }).click()
  await page.getByRole('menuitem', { name: 'SSO Logout' }).click()

  await page.waitForURL(`**${authEntryPath}`)

  await expect(page.getByText('Logout Complete')).toBeVisible()
  await expect(page.getByRole('link', { name: 'SSO Login' })).toBeVisible()
  await expect(page.getByText('Bearer Token: missing')).toBeVisible()
  await expect(page.getByText('Not resolved yet')).toBeVisible()
})
