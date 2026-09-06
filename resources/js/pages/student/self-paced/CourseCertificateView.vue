<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ArrowDownTrayIcon, TrophyIcon } from '@heroicons/vue/24/solid'
import { useCertificatesStore } from '../../../stores/certificates'
import { useSelfPacedPlayerStore } from '../../../stores/selfPacedPlayer'

// Never regenerates a certificate — only ever reads the record the backend
// already created when the course was completed (CertificateService).
const route = useRoute()
const certificatesStore = useCertificatesStore()
const playerStore = useSelfPacedPlayerStore()

const loading = ref(true)
const errorMessage = ref('')
const certificate = ref(null)
const pdfObjectUrl = ref(null)
const downloading = ref(false)

const courseTitle = computed(() => playerStore.course?.title)
const justCompleted = computed(() => playerStore.course?.enrollment.status === 'completed')

async function load() {
    loading.value = true
    errorMessage.value = ''

    try {
        const certificates = await certificatesStore.fetchCertificates()
        certificate.value = certificates.find((item) => item.self_paced_course_id === Number(route.params.courseId)) ?? null

        if (!certificate.value) {
            errorMessage.value = 'Your certificate is not available yet.'
            return
        }

        const blob = await certificatesStore.fetchCertificateBlob(certificate.value.id)
        pdfObjectUrl.value = URL.createObjectURL(blob)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Your certificate could not be loaded right now.'
    } finally {
        loading.value = false
    }
}

async function download() {
    if (!certificate.value) return

    downloading.value = true
    try {
        await certificatesStore.downloadCertificate(certificate.value.id, `${certificate.value.certificate_number}.pdf`)
    } catch {
        errorMessage.value = 'The certificate could not be downloaded right now.'
    } finally {
        downloading.value = false
    }
}

onMounted(load)
onBeforeUnmount(() => {
    if (pdfObjectUrl.value) URL.revokeObjectURL(pdfObjectUrl.value)
})
</script>

<template>
    <div class="mx-auto max-w-3xl p-4 sm:p-8">
        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <p v-else-if="errorMessage" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <template v-else-if="certificate">
            <div v-if="justCompleted" class="mb-6 rounded-2xl bg-green-500/10 p-6 text-center">
                <TrophyIcon class="mx-auto h-10 w-10 text-green-600" />
                <h1 class="text-body mt-3 text-2xl font-bold">Congratulations!</h1>
                <p class="text-muted mt-1 text-sm">You've completed <strong>{{ courseTitle }}</strong>. Your certificate is ready.</p>
            </div>

            <div class="flex items-center justify-between">
                <h2 class="text-body text-lg font-bold">Your Certificate</h2>
                <button
                    type="button"
                    class="bg-amber shadow-elevated inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-bold text-white transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="downloading"
                    @click="download"
                >
                    <ArrowDownTrayIcon class="h-4 w-4" />
                    {{ downloading ? 'Downloading…' : 'Download PDF' }}
                </button>
            </div>

            <div class="border-border shadow-elevated mt-4 overflow-hidden rounded-2xl border">
                <iframe v-if="pdfObjectUrl" :src="pdfObjectUrl" title="Certificate of Completion" class="h-[70vh] w-full" />
            </div>

            <dl class="mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-muted text-xs">Certificate No.</dt>
                    <dd class="text-body font-semibold">{{ certificate.certificate_number }}</dd>
                </div>
                <div>
                    <dt class="text-muted text-xs">Issued</dt>
                    <dd class="text-body font-semibold">{{ new Date(certificate.issued_at).toLocaleDateString() }}</dd>
                </div>
                <div>
                    <dt class="text-muted text-xs">Instructor</dt>
                    <dd class="text-body font-semibold">{{ certificate.tutor_name }}</dd>
                </div>
            </dl>
        </template>
    </div>
</template>
