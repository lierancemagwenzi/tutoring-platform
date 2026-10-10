<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import {
    ArrowDownTrayIcon,
    ArrowPathIcon,
    ArrowUpTrayIcon,
    CheckCircleIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    SignalIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline'
import { HEALTH, METRIC_TIPS, lastResult, runSpeedTest } from '../../services/speedTest'

// The network check: run it, watch progress, and see whether the
// connection is healthy enough for a live video session.
const props = defineProps({
    // Start testing as soon as the panel opens (e.g. right before a session).
    autoStart: { type: Boolean, default: false },
    // Show a previous result (instead of an empty state) until a new run.
    initialResult: { type: Object, default: undefined },
})

const emit = defineEmits(['running', 'finished'])

const running = ref(false)
const phase = ref('')
const error = ref('')
const result = ref(props.initialResult === undefined ? lastResult() : props.initialResult)
let controller = null

const METRICS = [
    { key: 'download', label: 'Download', unit: 'Mbps', icon: ArrowDownTrayIcon, help: 'How fast you receive — your learner’s video.' },
    { key: 'upload', label: 'Upload', unit: 'Mbps', icon: ArrowUpTrayIcon, help: 'How fast you send — your camera and screen share.' },
    { key: 'latency', label: 'Response time', unit: 'ms', icon: ClockIcon, help: 'Delay before your learner hears you. Lower is better.' },
    { key: 'jitter', label: 'Stability', unit: 'ms jitter', icon: SignalIcon, help: 'How much the delay varies. Lower means smoother video.' },
]

const RATING_STYLES = {
    great: 'bg-green-100 text-green-700',
    good: 'bg-green-100 text-green-700',
    fair: 'bg-amber-100 text-amber-700',
    poor: 'bg-red-100 text-red-700',
}

const health = computed(() => (result.value?.health ? { level: result.value.health, ...HEALTH[result.value.health] } : null))
// Specific advice for each measurement rated fair or poor, then the general tips.
const tips = computed(() => {
    if (!health.value?.tips) return []
    const weak = Object.entries(result.value.ratings ?? {})
        .filter(([, rating]) => rating === 'fair' || rating === 'poor')
        .map(([metric]) => METRIC_TIPS[metric])
    return [...weak, ...health.value.tips].filter((tip, index, all) => tip && all.indexOf(tip) === index)
})
const healthIcon = computed(() => ({ great: CheckCircleIcon, good: CheckCircleIcon, fair: ExclamationTriangleIcon, poor: XCircleIcon })[health.value?.level])
const testedAgo = computed(() => {
    if (!result.value?.testedAt) return ''
    const minutes = Math.round((Date.now() - new Date(result.value.testedAt).getTime()) / 60000)
    if (minutes < 1) return 'just now'
    if (minutes < 60) return `${minutes} min ago`
    const hours = Math.round(minutes / 60)
    return hours < 24 ? `${hours} h ago` : new Date(result.value.testedAt).toLocaleDateString()
})

function format(metric, value) {
    if (value === null || value === undefined) return '—'
    return metric === 'download' || metric === 'upload' ? (value >= 10 ? Math.round(value) : value.toFixed(1)) : Math.round(value)
}

async function run() {
    if (running.value) return
    running.value = true
    error.value = ''
    emit('running', true)
    controller = new AbortController()
    try {
        result.value = await runSpeedTest({ onPhase: (label) => (phase.value = label), signal: controller.signal })
        emit('finished', result.value)
    } catch (err) {
        if (err.name !== 'AbortError') error.value = err.message
    } finally {
        running.value = false
        phase.value = ''
        emit('running', false)
    }
}

defineExpose({ run })

onMounted(() => {
    if (props.autoStart) run()
})

// Closing the dialog / leaving the page mid-test stops it, so it doesn't
// keep using the Insider's data in the background.
onBeforeUnmount(() => controller?.abort())
</script>

<template>
    <div class="space-y-5">
        <div v-if="running" class="rounded-2xl bg-card-alt p-6 text-center" role="status" aria-live="polite">
            <div class="border-amber mx-auto h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
            <p class="text-body mt-4 font-semibold">{{ phase || 'Testing your connection' }}…</p>
            <p class="text-muted mt-1 text-sm">This takes about 20 seconds.</p>
        </div>

        <template v-else>
            <p v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

            <div v-if="health" class="flex items-start gap-3 rounded-2xl p-4" :class="RATING_STYLES[health.level]">
                <component :is="healthIcon" class="mt-0.5 h-6 w-6 shrink-0" />
                <div class="min-w-0">
                    <p class="font-bold">Network health: {{ health.label }}</p>
                    <p class="text-sm">{{ health.summary }}</p>
                </div>
            </div>
            <div v-else-if="!error" class="rounded-2xl bg-card-alt p-6 text-center">
                <SignalIcon class="text-accent mx-auto h-10 w-10" />
                <p class="text-body mt-3 font-semibold">Check your connection before teaching</p>
                <p class="text-muted mt-1 text-sm">We'll measure your speed and tell you if it's ready for a video session.</p>
            </div>
        </template>

        <div v-if="result && !running" class="grid grid-cols-2 gap-3">
            <div v-for="metric in METRICS" :key="metric.key" class="rounded-xl border border-border p-3" :title="metric.help">
                <div class="text-muted flex items-center gap-1.5 text-xs font-semibold">
                    <component :is="metric.icon" class="h-4 w-4" />
                    {{ metric.label }}
                </div>
                <p class="text-body mt-1 text-xl font-bold tabular-nums">
                    {{ format(metric.key, result.metrics[metric.key]) }}
                    <span class="text-muted text-xs font-medium">{{ metric.unit }}</span>
                </p>
                <span
                    v-if="result.ratings[metric.key]"
                    class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize"
                    :class="RATING_STYLES[result.ratings[metric.key]]"
                >
                    {{ result.ratings[metric.key] }}
                </span>
            </div>
        </div>

        <ul v-if="tips.length && !running" class="text-body list-disc space-y-1 pl-5 text-sm">
            <li v-for="tip in tips" :key="tip">{{ tip }}</li>
        </ul>

        <div v-if="!running" class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-muted text-xs">
                <template v-if="result">Last tested {{ testedAgo }}</template>
            </p>
            <button
                type="button"
                class="flex items-center gap-2 rounded-full border border-border px-4 py-2 text-sm font-semibold text-body hover:border-accent"
                @click="run"
            >
                <ArrowPathIcon v-if="result || error" class="h-4 w-4" />
                <SignalIcon v-else class="h-4 w-4" />
                {{ result || error ? 'Test again' : 'Start test' }}
            </button>
        </div>
    </div>
</template>
