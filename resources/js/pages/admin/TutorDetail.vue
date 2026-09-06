<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAdminTutorsStore } from '../../stores/adminTutors'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'

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
const store = useAdminTutorsStore()

const loading = ref(true)
const errorMessage = ref('')
const togglingDisabled = ref(false)

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

async function toggleDisabled() {
    const disabling = !store.current.user.disabled
    if (!confirm(disabling ? 'Disable this tutor account?' : 'Re-enable this tutor account?')) {
        return
    }
    togglingDisabled.value = true
    errorMessage.value = ''
    try {
        if (disabling) {
            await store.disableUser(store.current.user.id)
        } else {
            await store.enableUser(store.current.user.id)
        }
        store.current.user.disabled = disabling
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong.'
    } finally {
        togglingDisabled.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <router-link :to="{ name: 'admin.tutors' }" class="text-accent text-sm font-semibold">&larr; Back to Tutors</router-link>

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
                <div class="flex shrink-0 flex-col items-end gap-2">
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="adminStatusBadge(store.current.user.approval_status)">
                        {{ adminStatusLabel(store.current.user.approval_status) }}
                    </span>
                    <span
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="store.current.user.disabled ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'"
                    >
                        {{ store.current.user.disabled ? 'Disabled' : 'Active' }}
                    </span>
                </div>
            </div>

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
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted">No qualifications added yet.</p>
                </section>

                <section class="rounded-2xl bg-card p-6 shadow-elevated">
                    <h2 class="text-body font-bold">Identity Verification</h2>
                    <div v-if="store.current.profile.government_id" class="mt-3 text-sm">
                        <a :href="store.current.profile.government_id.url" target="_blank" class="text-accent font-semibold underline">
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
                    <h2 class="text-body font-bold">Subjects</h2>
                    <div class="mt-3 space-y-3 text-sm">
                        <div v-if="store.current.approved_subjects.length > 0">
                            <p class="text-xs font-semibold tracking-wide text-muted uppercase">Approved</p>
                            <ul class="mt-1 space-y-1">
                                <li v-for="subject in store.current.approved_subjects" :key="subject.id" class="text-body">{{ subject.subject }}</li>
                            </ul>
                        </div>
                        <div v-if="store.current.pending_subjects.length > 0">
                            <p class="text-xs font-semibold tracking-wide text-muted uppercase">Pending</p>
                            <ul class="mt-1 space-y-1">
                                <li v-for="subject in store.current.pending_subjects" :key="subject.id" class="text-body">{{ subject.subject }}</li>
                            </ul>
                        </div>
                        <div v-if="store.current.rejected_subjects.length > 0">
                            <p class="text-xs font-semibold tracking-wide text-muted uppercase">Rejected</p>
                            <ul class="mt-1 space-y-1">
                                <li v-for="subject in store.current.rejected_subjects" :key="subject.id" class="text-body">{{ subject.subject }}</li>
                            </ul>
                        </div>
                        <div v-if="store.current.suspended_subjects.length > 0">
                            <p class="text-xs font-semibold tracking-wide text-muted uppercase">Suspended</p>
                            <ul class="mt-1 space-y-1">
                                <li v-for="subject in store.current.suspended_subjects" :key="subject.id" class="text-body">{{ subject.subject }}</li>
                            </ul>
                        </div>
                        <p
                            v-if="
                                store.current.approved_subjects.length === 0 &&
                                store.current.pending_subjects.length === 0 &&
                                store.current.rejected_subjects.length === 0 &&
                                store.current.suspended_subjects.length === 0
                            "
                            class="text-muted"
                        >
                            No subjects requested yet.
                        </p>
                    </div>
                </section>

                <section class="rounded-2xl bg-card p-6 shadow-elevated">
                    <h2 class="text-body font-bold">Activity</h2>
                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div>
                            <dt class="text-muted">Services</dt>
                            <dd class="text-body font-medium">{{ store.current.services_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Self-paced courses</dt>
                            <dd class="text-body font-medium">{{ store.current.self_paced_courses_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Bookings</dt>
                            <dd class="text-body font-medium">{{ store.current.bookings_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Teaching sessions</dt>
                            <dd class="text-body font-medium">{{ store.current.teaching_sessions_count }}</dd>
                        </div>
                    </dl>

                    <h2 class="mt-6 text-body font-bold">Connected Meeting Providers</h2>
                    <ul v-if="store.current.connected_meeting_providers.length > 0" class="mt-3 space-y-1 text-sm">
                        <li v-for="account in store.current.connected_meeting_providers" :key="account.provider" class="text-body capitalize">
                            {{ account.provider }} — {{ account.email }}
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted">None connected.</p>
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
                    <p v-else class="mt-3 text-sm text-muted">No banking details on file.</p>
                </section>
            </div>

            <div class="mt-6 flex justify-end rounded-2xl bg-card p-6 shadow-elevated">
                <button
                    type="button"
                    :disabled="togglingDisabled"
                    class="rounded-full px-6 py-2 text-sm font-semibold shadow-elevated transition disabled:cursor-not-allowed disabled:opacity-40"
                    :class="store.current.user.disabled ? 'bg-amber text-white hover:brightness-95' : 'border border-red-300 text-red-600 hover:bg-red-50'"
                    @click="toggleDisabled"
                >
                    {{ store.current.user.disabled ? 'Enable Account' : 'Disable Account' }}
                </button>
            </div>
        </template>
    </div>
</template>
