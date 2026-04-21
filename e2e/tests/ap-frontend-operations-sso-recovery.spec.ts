import { expect, test, type Page } from '@playwright/test'

const objectsPath = '/objects'
const policiesPath = '/policies'
const authEntryPath = '/?logged_out=1#auth-entry'
const ssoDebugEnabled = process.env.PLAYWRIGHT_SSO_DEBUG === '1'

async function submitKeycloakLogin(page: Page) {
  await page.locator('input[name="username"]').fill(process.env.KEYCLOAK_USERNAME ?? 'alice')
  await page.locator('input[name="password"]').fill(process.env.KEYCLOAK_PASSWORD ?? 'password')
  await page.getByRole('button', { name: /sign in|log in/i }).click()
}

function buildGlobalSsoLoginUrl(nextPath = objectsPath) {
  const globalLoginUrl = new URL('https://global.example.com/login')
  const returnToUrl = new URL('https://ap.example.com/auth/bridge')
  returnToUrl.searchParams.set('next', nextPath)

  if (ssoDebugEnabled) {
    returnToUrl.searchParams.set('sso_debug', '1')
  }

  globalLoginUrl.searchParams.set('return_to', returnToUrl.toString())

  return globalLoginUrl.toString()
}

async function readCallbackDebugTrace(page: Page) {
  if (page.isClosed()) {
    return 'unavailable: page already closed'
  }

  try {
    return await page.evaluate(() => sessionStorage.getItem('ap-sso-debug-trace'))
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error)

    return `unavailable: ${message}`
  }
}

async function waitForSsoArrival(page: Page, nextPath: string) {
  try {
    await page.waitForURL(`**${nextPath}`)
  } catch (error) {
    const currentUrl = page.isClosed() ? 'unavailable: page already closed' : page.url()

    if (currentUrl.includes('/auth/callback') || currentUrl.includes('page already closed')) {
      const trace = await readCallbackDebugTrace(page)
      throw new Error(
        [
          `SSO callback timeout while waiting for ${nextPath}.`,
          `Callback URL: ${currentUrl}`,
          `Callback trace: ${trace ?? 'missing'}`
        ].join('\n'),
        { cause: error }
      )
    }

    throw error
  }
}

async function loginViaGlobalSso(page: Page, nextPath = objectsPath) {
  await page.goto(buildGlobalSsoLoginUrl(nextPath))
  await submitKeycloakLogin(page)
  await waitForSsoArrival(page, nextPath)
}

async function openUserMenu(page: Page) {
  await page.getByRole('button', { name: 'Alice A' }).click()
}

async function expectObjectsPage(page: Page) {
  await expect(page.getByRole('heading', { name: 'Objects' })).toBeVisible()
  await expect(page.getByText('Filter & Sort')).toBeVisible()
  await expect(page.getByText('Objects List')).toBeVisible()
}

async function expectPoliciesPage(page: Page) {
  await expect(page.getByRole('heading', { name: 'Policies' })).toBeVisible()
  await expect(page.getByText('Policy List Placeholder')).toBeVisible()
}

test('AP Frontend operations nav exposes objects and policies for object.read users', async ({ page }) => {
  await loginViaGlobalSso(page, objectsPath)

  await expectObjectsPage(page)
  await expect(page.getByRole('link', { name: 'Objects' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Policies' })).toBeVisible()

  await page.getByRole('link', { name: 'Policies' }).click()
  await page.waitForURL(`**${policiesPath}`)
  await expectPoliciesPage(page)
})

test('AP Frontend returns to the same objects page after logout and SSO re-login', async ({ page }) => {
  await loginViaGlobalSso(page, objectsPath)

  await expectObjectsPage(page)

  await openUserMenu(page)
  await page.getByRole('menuitem', { name: 'SSO Logout' }).click()

  await page.waitForURL(`**${authEntryPath}`)
  const ssoLoginLink = page.getByRole('link', { name: 'SSO Login' })
  await expect(ssoLoginLink).toBeVisible()
  await expect(page.getByText('Logout Complete')).toBeVisible()

  await ssoLoginLink.click()
  await submitKeycloakLogin(page)

  await page.waitForURL(`**${objectsPath}`)
  await expectObjectsPage(page)
})

test('AP Frontend returns to the same policies page after logout and SSO re-login', async ({ page }) => {
  await loginViaGlobalSso(page, policiesPath)

  await expectPoliciesPage(page)

  await openUserMenu(page)
  await page.getByRole('menuitem', { name: 'SSO Logout' }).click()

  await page.waitForURL(`**${authEntryPath}`)
  const ssoLoginLink = page.getByRole('link', { name: 'SSO Login' })
  await expect(ssoLoginLink).toBeVisible()
  await expect(page.getByText('Logout Complete')).toBeVisible()

  await ssoLoginLink.click()
  await submitKeycloakLogin(page)

  await page.waitForURL(`**${policiesPath}`)
  await expectPoliciesPage(page)
})
