import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname } from 'node:path'

const logPath = process.argv[2]
const reportPath = process.argv[3] ?? null

if (!logPath) {
  console.error('[e2e] missing log path for callback trace summary')
  process.exit(1)
}

const summaryLines = []
const pushSummary = (line) => {
  console.log(line)
  summaryLines.push(line)
}

const flushSummaryReport = () => {
  if (!reportPath) {
    return
  }

  mkdirSync(dirname(reportPath), { recursive: true })
  writeFileSync(reportPath, `${summaryLines.join('\n')}\n`, 'utf8')
}

const log = readFileSync(logPath, 'utf8')
const traceLines = log
  .split('\n')
  .filter(line => line.includes('Callback trace:'))

if (traceLines.length === 0) {
  pushSummary('[e2e] debug rerun did not emit Callback trace.')
  flushSummaryReport()
  process.exit(0)
}

const latestTraceLine = traceLines.at(-1) ?? ''
const rawTrace = latestTraceLine.slice(latestTraceLine.indexOf('Callback trace:') + 'Callback trace:'.length).trim()

pushSummary('[e2e] extracted Callback trace lines:')
for (const line of traceLines) {
  pushSummary(line)
}

if (rawTrace === 'missing') {
  pushSummary('[e2e] Callback trace payload is missing.')
  flushSummaryReport()
  process.exit(0)
}

try {
  const trace = JSON.parse(rawTrace)

  if (!Array.isArray(trace) || trace.length === 0) {
    pushSummary('[e2e] Callback trace payload was empty.')
    flushSummaryReport()
    process.exit(0)
  }

  const latestEntry = trace.at(-1)
  pushSummary(`[e2e] Callback trace count: ${trace.length}`)
  pushSummary(`[e2e] Callback latest stage: ${latestEntry?.stage ?? 'unknown'}`)

  if (latestEntry?.at) {
    pushSummary(`[e2e] Callback latest at: ${latestEntry.at}`)
  }
} catch (error) {
  pushSummary('[e2e] failed to parse Callback trace JSON.')
  pushSummary(`[e2e] parser error: ${error instanceof Error ? error.message : String(error)}`)
}

flushSummaryReport()
