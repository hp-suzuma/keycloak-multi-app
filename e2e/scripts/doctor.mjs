import dns from 'node:dns/promises'
import process from 'node:process'
import { request as httpsRequest } from 'node:https'

const hostMap = new Map(
  (process.env.PLAYWRIGHT_HOST_MAP ??
    'ap.example.com=127.0.0.1,global.example.com=127.0.0.1,keycloak.example.com=127.0.0.1,ap-backend-fpm.example.com=127.0.0.1')
    .split(',')
    .map((entry) => entry.trim())
    .filter(Boolean)
    .map((entry) => entry.split('='))
    .filter((entry) => entry.length === 2)
    .map(([host, address]) => [host.trim(), address.trim()])
)

const requiredNodeMajor = 22
const requiredHosts = [
  'ap.example.com',
  'global.example.com',
  'keycloak.example.com',
  'ap-backend-fpm.example.com'
]

const requiredUrls = [
  'https://ap.example.com/',
  'https://global.example.com/login',
  'https://keycloak.example.com/realms/myapp/.well-known/openid-configuration'
]

function checkNodeVersion() {
  const major = Number.parseInt(process.versions.node.split('.')[0] ?? '', 10)

  if (Number.isNaN(major) || major < requiredNodeMajor) {
    return {
      ok: false,
      message: `Node ${requiredNodeMajor}+ is required, but current version is ${process.version}.`
    }
  }

  return {
    ok: true,
    message: `Node version ${process.version} is compatible.`
  }
}

async function resolveHost(host) {
  const mappedAddress = hostMap.get(host)
  if (mappedAddress) {
    return {
      ok: true,
      message: `${host} resolves to ${mappedAddress} via PLAYWRIGHT_HOST_MAP.`
    }
  }

  try {
    const addresses = await dns.lookup(host, { all: true })

    return {
      ok: true,
      message: `${host} resolves to ${addresses.map(({ address }) => address).join(', ')}.`
    }
  } catch (error) {
    return {
      ok: false,
      message: `${host} did not resolve. Add it to /etc/hosts or DNS before running Playwright.`,
      detail: error instanceof Error ? error.message : String(error)
    }
  }
}

function fetchStatus(url) {
  return new Promise((resolve) => {
    const target = new URL(url)
    const req = httpsRequest(
      url,
      {
        method: 'GET',
        rejectUnauthorized: false,
        lookup(hostname, options, callback) {
          const mappedAddress = hostMap.get(hostname)
          if (mappedAddress) {
            if (typeof options === 'object' && options?.all) {
              callback(null, [{ address: mappedAddress, family: 4 }])
              return
            }

            callback(null, mappedAddress, 4)
            return
          }

          dns.lookup(hostname, options)
            .then((result) => {
              if (typeof result === 'string') {
                callback(null, result, 4)
                return
              }

              callback(null, result.address, result.family)
            })
            .catch((error) => callback(error))
        },
        servername: target.hostname
      },
      (res) => {
        res.resume()

        resolve({
          ok: Boolean(res.statusCode && res.statusCode < 500),
          statusCode: res.statusCode ?? 0
        })
      }
    )

    req.setTimeout(10_000, () => {
      req.destroy(new Error('Request timed out'))
    })

    req.on('error', (error) => {
      resolve({
        ok: false,
        error: error instanceof Error ? error.message : String(error)
      })
    })

    req.end()
  })
}

async function checkUrl(url) {
  const result = await fetchStatus(url)

  if (!result.ok) {
    const suffix = 'statusCode' in result
      ? `HTTP ${result.statusCode}`
      : result.error

    return {
      ok: false,
      message: `${url} is not ready yet (${suffix}).`
    }
  }

  return {
    ok: true,
    message: `${url} responded with HTTP ${result.statusCode}.`
  }
}

function logResult(label, result) {
  const prefix = result.ok ? '[ok]' : '[ng]'
  console.log(`${prefix} ${label}: ${result.message}`)

  if (result.detail) {
    console.log(`     ${result.detail}`)
  }
}

const username = process.env.KEYCLOAK_USERNAME ?? 'alice'
const password = process.env.KEYCLOAK_PASSWORD ?? 'password'
const failures = []

const nodeCheck = checkNodeVersion()
logResult('node', nodeCheck)
if (!nodeCheck.ok) {
  failures.push('node')
}

for (const host of requiredHosts) {
  const hostCheck = await resolveHost(host)
  logResult(`dns ${host}`, hostCheck)
  if (!hostCheck.ok) {
    failures.push(`dns:${host}`)
  }
}

for (const url of requiredUrls) {
  const urlCheck = await checkUrl(url)
  logResult(`http ${url}`, urlCheck)
  if (!urlCheck.ok) {
    failures.push(`http:${url}`)
  }
}

console.log(`[info] keycloak test user: ${username}`)
console.log(`[info] keycloak password source: ${process.env.KEYCLOAK_PASSWORD ? 'env' : 'default value "password"'}`)
console.log(`[info] host mapping source: ${process.env.PLAYWRIGHT_HOST_MAP ? 'env' : 'default PLAYWRIGHT_HOST_MAP'}`)
console.log('[info] next command: pnpm --dir e2e run wait:stack && pnpm --dir e2e run test:sso')

if (failures.length > 0) {
  console.error(`[fail] browser environment is not ready (${failures.join(', ')}).`)
  process.exitCode = 1
} else {
  console.log('[pass] browser environment looks ready for Playwright.')
}
