<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAdminTutorApprovalsStore } from '../../stores/adminTutorApprovals'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'
import ReasonPromptModal from '../../components/common/ReasonPromptModal.vue'

const LEVEL_LABELS = {
    certificate: 'Certificate',
    diploma: 'Diploma',
    bachelors_degree: "Bachelor's Degree",
    honours: 'Honours',
    masters: "Master's",
    phd: 'PhD',
    other: 'Other',
}

const DOCUMENT_TYPE_LABELS = {
    degree: 'Degree',
    teaching_certificate: 'Teaching Certificate',
    police_clearance: 'Police Clearance',
    other: 'Other',
}

const route = useRoute()
const router = useRouter()
const store = useAdminTutorApprovalsStore()

const loading = ref(true)
const errorMessage = ref('')
const actioning = ref(false)

async function load() {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchDetail(route.params.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading this tutor.'
    } finally {
        loading.value = false
    }
}

onMounted(() => load())

async function approve() {
    if (!confirm('Approve this tutor?')) {
        return
    }
    actioning.value = true
    errorMessage.value = ''
    try {
        await store.approve(route.params.id)
        router.push({ name: 'admin.tutor-approvals' })
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong.'
    } finally {
        actioning.value = false
    }
}

const reasonModal = ref({ open: false, mode: null })
const reasonSubmitting = ref(false)

function openReasonModal(mode) {
    reasonModal.value = { open: true, mode }
}

async function submitReason(text) {
    reasonSubmitting.value = true
    errorMessage.value = ''
    try {
        if (reasonModal.value.mode === 'reject') {
            await store.reject(route.params.id, text)
        } else {
            await store.requestChanges(route.params.id, text)
        }
        reasonModal.value = { open: false, mode: null }
        router.push({ name: 'admin.tutor-approvals' })
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong.'
    } finally {
        reasonSubmitting.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <router-link :to="{ name: 'admin.tutor-approvals' }" class="text-accent text-sm font-semibold">&larr; Back to Tutor Approvals</router-link>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <template v-else-if="store.current">
            <div class="mt-4 flex items-start justify-between gap-4 rounded-2xl bg-card p-6 shadow-elevated">
                <div class="flex items-start gap-4">
                    <img
                        v-if="store.current.profile.profile_photo_url"
                        :src="store.current.profile.profile_photo_url"
                        alt="Profile photo"
                        class="h-16 w-16 rounded-full object-cover"
                    />
                    <div>
                        <p class="text-body text-lg font-bold">{{ store.current.user.name }}</p>
                        <p class="text-sm text-muted">{{ store.current.user.email }}</p>
                        <p v-if="store.current.user.phone" class="text-sm text-muted">{{ store.current.user.phone }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                            <span>Registered {{ store.current.user.registered_at?.slice(0, 10) }}</span>
                            <span>{{ store.current.user.email_verified ? 'Email verified' : 'Email not verified' }}</span>
                        </div>
                    </div>
                </div>
                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="adminStatusBadge(store.current.user.status)">
                    {{ adminStatusLabel(store.current.user.status) }}
                </span>
            </div>

            <div
                v-if="store.current.profile.missing_requirements.length > 0"
                class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5"
            >
                <p class="font-semibold text-amber-800">Incomplete</p>
                <ul class="mt-1 list-inside list-disc text-sm text-amber-700">
                    <li v-for="req in store.current.profile.missing_requirements" :key="req">{{ req }}</li>
                </ul>
            </div>

            <p v-if="store.current.profile.admin_note" class="mt-6 rounded-lg bg-card-alt px-4 py-3 text-sm text-muted">
                <span class="font-semibold text-body">Admin note:</span> {{ store.current.profile.admin_note }}
            </p>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <section class="rounded-2xl bg-card p-6 shadow-elevated">
                    <h2 class="text-body font-bold">Basic Information</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div>
                            <dt class="text-muted">Display name</dt>
                            <dd class="text-body font-medium">{{ store.current.profile.display_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Bio</dt>
                            <dd class="text-body">{{ store.current.profile.bio ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Years of experience</dt>
                            <dd class="text-body font-medium">{{ store.current.profile.years_experience ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Occupation</dt>
                            <dd class="text-body font-medium">{{ store.current.profile.occupation ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Languages</dt>
                            <dd class="text-body font-medium">
                                {{ store.current.profile.languages.length > 0 ? store.current.profile.languages.join(', ') : '—' }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-2xl bg-card p-6 shadow-elevated">
                    <h2 class="text-body font-bold">Professional Profile</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div>
                            <dt class="text-muted">Teaching style</dt>
                            <dd class="text-body">{{ store.current.profile.teaching_style ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">About me</dt>
                            <dd class="text-body">{{ store.current.profile.about_me ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Why choose me</dt>
                            <dd class="text-body">{{ store.current.profile.why_choose_me ?? '—' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-2xl bg-card p-6 shadow-elevated">
                    <h2 class="text-body font-bold">Qualifications</h2>
                    <ul v-if="store.current.profile.qualifications.length > 0" class="mt-3 space-y-3 text-sm">
                        <li v-for="qualification in store.current.profile.qualifications" :key="qualification.id">
                            <p class="text-body font-medium">{{ qualification.title }}</p>
                            <p class="text-muted">
                                {{ LEVEL_LABELS[qualification.level] ?? qualification.level }} &middot;
                                {{ qualification.field_of_study }} &middot; {{ qualification.institution }}
                            </p>
                            <p class="text-muted">
                                {{ qualification.start_year }} –
                                {{ qualification.is_currently_studying ? 'Present' : qualification.completion_year }}
                            </p>
                            <p v-if="qualification.description" class="mt-1 text-muted">{{ qualification.description }}</p>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted">No qualifications added yet.</p>
                </section>

                <section class="rounded-2xl bg-card p-6 shadow-elevated">
                    <h2 class="text-body font-bold">Identity Verification</h2>
                    <div v-if="store.current.profile.government_id" class="mt-3 text-sm">
                        <a
                            :href="store.current.profile.government_id.url"
                            target="_blank"
                            class="text-accent font-semibold underline"
                        >
                            {{ store.current.profile.government_id.name }}
                        </a>
                    </div>
                    <p v-else class="mt-3 text-sm text-muted">Not uploaded.</p>

                    <h2 class="mt-6 text-body font-bold">Supporting Documents</h2>
                    <ul v-if="store.current.profile.documents.length > 0" class="mt-3 space-y-1.5 text-sm">
                        <li v-for="document in store.current.profile.documents" :key="document.id">
                            <a :href="document.url" target="_blank" class="text-accent font-semibold underline">
                                {{ DOCUMENT_TYPE_LABELS[document.type] ?? document.type }} — {{ document.original_name }}
                            </a>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted">None uploaded.</p>
                </section>

                <section class="rounded-2xl bg-card p-6 shadow-elevated">
                    <h2 class="text-body font-bold">Requested Subjects</h2>
                    <ul v-if="store.current.requested_subjects.length > 0" class="mt-3 space-y-2 text-sm">
                        <li v-for="subject in store.current.requested_subjects" :key="subject.id">
                            <div class="flex items-center justify-between">
                                <span class="text-body font-medium">{{ subject.subject }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="adminStatusBadge(subject.status)">
                                    {{ adminStatusLabel(subject.status) }}
                                </span>
                            </div>
                            <p v-if="subject.grades.length > 0" class="text-muted">{{ subject.grades.join(', ') }}</p>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted">No subjects requested yet.</p>
                </section>

                <section class="rounded-2xl bg-card p-6 shadow-elevated">
                    <h2 class="text-body font-bold">Banking Details</h2>
                    <dl v-if="store.current.bank_account" class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div>
                            <dt class="text-muted">Bank</dt>
                            <dd class="text-body font-medium">{{ store.current.bank_account.bank_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Account holder</dt>
                            <dd class="text-body font-medium">{{ store.current.bank_account.account_holder_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Account number</dt>
                            <dd class="text-body font-medium">{{ store.current.bank_account.account_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Branch code</dt>
                            <dd class="text-body font-medium">{{ store.current.bank_account.branch_code }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Account type</dt>
                            <dd class="text-body font-medium">{{ store.current.bank_account.account_type }}</dd>
                        </div>
                    </dl>
                    <p v-else class="mt-3 text-sm font-semibold text-red-600">
                        No banking details on file — this tutor cannot be approved until they add them.
                    </p>
                </section>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-4 rounded-2xl bg-card p-6 shadow-elevated">
                <button type="button" class="text-sm font-semibold text-muted" @click="openReasonModal('request-changes')">
                    Request Changes
                </button>
                <button type="button" class="text-sm font-semibold text-red-600" @click="openReasonModal('reject')">Reject</button>
                <button
                    type="button"
                    :disabled="actioning || !store.current.bank_account"
                    :title="store.current.bank_account ? '' : 'This tutor has no banking details on file.'"
                    class="bg-amber ml-auto rounded-full px-6 py-2 text-sm font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:bg-card-alt disabled:text-muted"
                    @click="approve"
                >
                    Approve
                </button>
            </div>
        </template>

        <ReasonPromptModal
            v-model="reasonModal.open"
            :title="reasonModal.mode === 'reject' ? 'Reject Tutor' : 'Request Changes'"
            :label="reasonModal.mode === 'reject' ? 'Reason for rejection' : 'What needs to change'"
            confirm-label="Submit"
            :loading="reasonSubmitting"
            @confirm="submitReason"
        />
    </div>
</template>
