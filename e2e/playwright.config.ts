import { defineConfig, devices } from '@playwright/test'

const hostMap = process.env.PLAYWRIGHT_HOST_MAP ??
  'ap.example.com=127.0.0.1,global.example.com=127.0.0.1,keycloak.example.com=127.0.0.1,ap-backend-fpm.example.com=127.0.0.1'

const hostResolverRules = hostMap
  .split(',')
  .map((entry) => entry.trim())
  .filter(Boolean)
  .map((entry) => {
    const [host, address] = entry.split('=')
    return host && address ? `MAP ${host.trim()} ${address.trim()}` : null
  })
  .filter((rule): rule is string => Boolean(rule))
  .join(',')

export default defineConfig({
  testDir: './tests',
  timeout: 60_000,
  expect: {
    timeout: 10_000
  },
  fullyParallel: false,
  reporter: [['list']],
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'https://ap.example.com',
    ignoreHTTPSErrors: true,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure'
  },
  projects: [
    {
      name: 'chromium',
      use: {
      ...devices['Desktop Chrome'],
        channel: 'chromium',
        launchOptions: hostResolverRules
          ? {
              args: [`--host-resolver-rules=${hostResolverRules}`]
            }
          : undefined
      }
    }
  ]
})
