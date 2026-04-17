import { expect, test } from '@playwright/test'

const usersPath = '/users?service_scope_id=2&tenant_scope_id=3&keyword=alice&sort=-email'

test('AP Frontend recovers to the same users query after SSO login', async ({ page }) => {
  const globalLoginUrl = new URL('https://global.example.com/login')
  globalLoginUrl.searchParams.set(
    'return_to',
    `https://ap.example.com/auth/bridge?next=${encodeURIComponent(usersPath)}`
  )

  await page.goto(globalLoginUrl.toString())

  await page.getByLabel(/username|email/i).fill(process.env.KEYCLOAK_USERNAME ?? 'alice')
  await page.getByLabel(/password/i).fill(process.env.KEYCLOAK_PASSWORD ?? 'password')
  await page.getByRole('button', { name: /sign in|log in/i }).click()

  await page.waitForURL(`**${usersPath}`)

  await expect(page.getByText('Alice A')).toBeVisible()
  await expect(page.getByText('user.manage')).toBeVisible()
})
