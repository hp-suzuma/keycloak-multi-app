import process from 'node:process'
import { request as httpsRequest } from 'node:https'
import dns from 'node:dns/promises'

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

const timeoutMs = Number.parseInt(process.env.PLAYWRIGHT_WAIT_TIMEOUT_MS ?? '120000', 10)
const pollIntervalMs = Number.parseInt(process.env.PLAYWRIGHT_WAIT_INTERVAL_MS ?? '3000', 10)

const targets = [
  'https://ap.example.com/',
  'https://global.example.com/login',
  'https://keycloak.example.com/realms/myapp/.well-known/openid-configuration'
]

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
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

async function waitForTarget(url) {
  const startedAt = Date.now()

  while (Date.now() - startedAt < timeoutMs) {
    const result = await fetchStatus(url)

    if (result.ok) {
      console.log(`[ok] ${url} responded with HTTP ${result.statusCode}.`)
      return
    }

    const suffix = 'statusCode' in result ? `HTTP ${result.statusCode}` : result.error
    console.log(`[wait] ${url} is not ready yet (${suffix}).`)
    await sleep(pollIntervalMs)
  }

  throw new Error(`${url} did not become ready within ${timeoutMs}ms.`)
}

for (const target of targets) {
  await waitForTarget(target)
}

console.log('[pass] required browser targets are ready.')
