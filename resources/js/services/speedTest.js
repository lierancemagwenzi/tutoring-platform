import SpeedTest from '@cloudflare/speedtest'

// Network check for live video sessions, run in the browser against
// Cloudflare's edge (the same network the platform is served through).
//
// A shorter measurement set than the library default, which goes up to
// 250 MB downloads and needs a TURN server for packet loss: this caps the
// largest request at 10 MB, and the engine stops stepping up as soon as a
// request takes over a second — so slow or metered connections use far
// less data. Takes roughly 15–25 seconds.
const MEASUREMENTS = [
    { type: 'latency', numPackets: 1 },
    { type: 'download', bytes: 1e5, count: 1, bypassMinDuration: true },
    { type: 'latency', numPackets: 20 },
    { type: 'download', bytes: 1e5, count: 8 },
    { type: 'download', bytes: 1e6, count: 6 },
    { type: 'upload', bytes: 1e5, count: 6 },
    { type: 'upload', bytes: 1e6, count: 4 },
    { type: 'download', bytes: 1e7, count: 3 },
    { type: 'upload', bytes: 1e7, count: 2 },
]

const PHASE_LABELS = { latency: 'Checking response time', download: 'Measuring download speed', upload: 'Measuring upload speed' }

const STORAGE_KEY = 'network-check:last-result'

// Video sessions need upload as much as download — the tutor's camera and
// screen share go up. Thresholds are per metric; the overall health is the
// worst of them, because one weak link (e.g. high jitter) is what learners
// actually notice. "Good" comfortably covers HD video in Google Meet.
const THRESHOLDS = {
    download: [10, 4, 1.5], // Mbps for great / good / fair (higher is better)
    upload: [5, 2.5, 1], // a 1:1 HD Google Meet call sends ~2.6 Mbps
    latency: [50, 100, 200], // ms (lower is better)
    jitter: [20, 30, 50],
}

const LEVELS = ['great', 'good', 'fair', 'poor']

function rate(metric, value) {
    if (value === null || value === undefined || Number.isNaN(value)) return null
    const [great, good, fair] = THRESHOLDS[metric]
    const higherIsBetter = metric === 'download' || metric === 'upload'
    const passes = (limit) => (higherIsBetter ? value >= limit : value <= limit)
    if (passes(great)) return 'great'
    if (passes(good)) return 'good'
    if (passes(fair)) return 'fair'
    return 'poor'
}

export const HEALTH = {
    great: { label: 'Great', summary: 'Your connection is great for HD video sessions.' },
    good: { label: 'Good', summary: 'Your connection is good for video sessions.' },
    fair: {
        label: 'Fair',
        summary: 'Video may drop in quality or freeze at times.',
        tips: [
            'Close other apps, tabs and downloads that use the internet.',
            'Move closer to your Wi-Fi router, or use a network cable.',
            'Ask others on your network to pause streaming during the session.',
        ],
    },
    poor: {
        label: 'Poor',
        summary: 'Your connection may struggle with a live video session.',
        tips: [
            'Use a network cable, or move right next to your Wi-Fi router.',
            'Close everything else using the internet, then test again.',
            'If it stays poor, turn your camera off and keep audio on — or switch networks (e.g. a mobile hotspot).',
        ],
    },
}

// What to do about whichever measurement is holding the connection back.
export const METRIC_TIPS = {
    download: 'Your download speed is low — pause streaming, downloads and updates on your network.',
    upload: 'Your upload speed is the weak point — pause cloud backups, file uploads and other video calls. A network cable helps most.',
    latency: 'Your response time is high — use a network cable or move closer to your router; VPNs can also add delay.',
    jitter: 'Your connection is unstable — Wi-Fi interference is the usual cause, so try a network cable or move closer to your router.',
}

/** Turn raw measurements (bps / ms) into display values and ratings. */
export function assessResults(summary) {
    const metrics = {
        download: summary.download ? summary.download / 1e6 : null,
        upload: summary.upload ? summary.upload / 1e6 : null,
        latency: summary.latency ?? null,
        jitter: summary.jitter ?? null,
    }
    const ratings = Object.fromEntries(Object.entries(metrics).map(([metric, value]) => [metric, rate(metric, value)]))
    const rated = Object.values(ratings).filter(Boolean)
    const health = rated.length ? LEVELS[Math.max(...rated.map((level) => LEVELS.indexOf(level)))] : null

    return { metrics, ratings, health, testedAt: new Date().toISOString() }
}

/**
 * Run the network check. Calls onPhase(label) as it moves between
 * latency/download/upload. Resolves with assessResults() output; rejects
 * if the test can't run (e.g. the browser is offline or blocks the
 * test servers). Aborting `signal` stops it (no more data is used) and
 * rejects with an AbortError.
 */
export function runSpeedTest({ onPhase, signal } = {}) {
    return new Promise((resolve, reject) => {
        let failed = false
        const engine = new SpeedTest({ autoStart: false, measurements: MEASUREMENTS })

        signal?.addEventListener('abort', () => {
            failed = true
            engine.pause()
            reject(new DOMException('The network check was stopped.', 'AbortError'))
        }, { once: true })

        engine.onResultsChange = ({ type }) => onPhase?.(PHASE_LABELS[type] ?? 'Testing')
        engine.onFinish = (results) => {
            if (failed) return
            const summary = results.getSummary()
            const assessment = assessResults({
                download: summary.download,
                upload: summary.upload,
                latency: results.getUnloadedLatency(),
                jitter: results.getUnloadedJitter(),
            })
            if (!assessment.health) {
                reject(new Error("The test couldn't measure your connection. Check that you're online and try again."))
                return
            }
            saveLastResult(assessment)
            resolve(assessment)
        }
        engine.onError = (error) => {
            if (failed) return
            failed = true
            engine.pause()
            reject(new Error(typeof error === 'string' && error ? `The test couldn't complete: ${error}` : "The test couldn't complete. Check that you're online and try again."))
        }

        onPhase?.(PHASE_LABELS.latency)
        engine.play()
    })
}

export function lastResult() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null')
    } catch {
        return null
    }
}

function saveLastResult(result) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(result))
    } catch {
        // Private mode / storage disabled — the result still shows, it just isn't remembered.
    }
}

/** A result recent enough to trust before joining a session. */
export function recentResult(maxAgeMinutes = 15) {
    const result = lastResult()
    if (!result?.testedAt) return null
    return Date.now() - new Date(result.testedAt).getTime() <= maxAgeMinutes * 60 * 1000 ? result : null
}
