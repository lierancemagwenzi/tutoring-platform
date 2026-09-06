<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAdminStudentsStore } from '../../stores/adminStudents'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'

const route = useRoute()
const store = useAdminStudentsStore()

const loading = ref(true)
const errorMessage = ref('')
const togglingDisabled = ref(false)

async function load() {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchDetail(route.params.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading this student.'
    } finally {
        loading.value = false
    }
}

onMounted(() => load())

async function toggleDisabled() {
    const disabling = !store.current.student.disabled
    if (!confirm(disabling ? 'Disable this student account?' : 'Re-enable this student account?')) {
        return
    }
    togglingDisabled.value = true
    errorMessage.value = ''
    try {
        if (disabling) {
            await store.disableUser(store.current.student.id)
        } else {
            await store.enableUser(store.current.student.id)
        }
        store.current.student.disabled = disabling
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong.'
    } finally {
        togglingDisabled.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <router-link :to="{ name: 'admin.students' }" class="text-accent text-sm font-semibold">&larr; Back to Students</router-link>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <template v-else-if="store.current">
            <div class="mt-4 flex items-start justify-between gap-4 rounded-2xl bg-card p-6 shadow-elevated">
                <div>
                    <p class="text-body text-lg font-bold">{{ store.current.student.name }}</p>
                    <p class="text-sm text-muted">{{ store.current.student.email }}</p>
                    <p v-if="store.current.student.phone" class="text-sm text-muted">{{ store.current.student.phone }}</p>
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                        <span>Registered {{ store.current.student.registered_at?.slice(0, 10) }}</span>
                        <span v-if="store.current.student.date_of_birth">Born {{ store.current.student.date_of_birth }}</span>
                        <span>{{ store.current.student.email_verified ? 'Email verified' : 'Email not verified' }}</span>
                    </div>
                </div>
                <span
                    class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold"
                    :class="store.current.student.disabled ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'"
                >
                    {{ store.current.student.disabled ? 'Disabled' : 'Active' }}
                </span>
            </div>

            <section v-if="store.current.guardian" class="mt-6 rounded-2xl bg-card p-6 shadow-elevated">
                <h2 class="text-body font-bold">Guardian</h2>
                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <div>
                        <dt class="text-muted">Name</dt>
                        <dd class="text-body font-medium">{{ store.current.guardian.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Relationship</dt>
                        <dd class="text-body font-medium">{{ store.current.guardian.relationship_to_student }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Email</dt>
                        <dd class="text-body font-medium">{{ store.current.guardian.email }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Phone</dt>
                        <dd class="text-body font-medium">{{ store.current.guardian.phone }}</dd>
                    </div>
                </dl>
            </section>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <section class="rounded-2xl bg-card p-6 shadow-elevated">
                    <h2 class="text-body font-bold">Enrollments</h2>
                    <ul v-if="store.current.enrollments.length > 0" class="mt-3 space-y-3 text-sm">
                        <li v-for="enrollment in store.current.enrollments" :key="enrollment.id">
                            <div class="flex items-center justify-between">
                                <span class="text-body font-medium">{{ enrollment.course_title }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="adminStatusBadge(enrollment.status)">
                                    {{ adminStatusLabel(enrollment.status) }}
                                </span>
                            </div>
                            <p class="text-muted">
                                {{ enrollment.progress?.overall_percentage ?? 0 }}% complete
                                <span v-if="enrollment.certificate_issued">&middot; Certificate issued</span>
                            </p>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted">No enrollments yet.</p>
                </section>

                <section class="rounded-2xl bg-card p-6 shadow-elevated">
                    <h2 class="text-body font-bold">Bookings</h2>
                    <ul v-if="store.current.bookings.length > 0" class="mt-3 space-y-3 text-sm">
                        <li v-for="booking in store.current.bookings" :key="booking.id">
                            <div class="flex items-center justify-between">
                                <span class="text-body font-medium">{{ booking.service_title }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="adminStatusBadge(booking.status)">
                                    {{ adminStatusLabel(booking.status) }}
                                </span>
                            </div>
                            <p class="text-muted">{{ booking.tutor }} &middot; {{ booking.date }}</p>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted">No bookings yet.</p>
                </section>

                <section class="rounded-2xl bg-card p-6 shadow-elevated lg:col-span-2">
                    <h2 class="text-body font-bold">Certificates</h2>
                    <ul v-if="store.current.certificates.length > 0" class="mt-3 space-y-2 text-sm">
                        <li v-for="certificate in store.current.certificates" :key="certificate.id" class="flex items-center justify-between">
                            <span class="text-body font-medium">{{ certificate.course_title }}</span>
                            <span class="text-muted">{{ certificate.certificate_number }} &middot; {{ certificate.issued_at?.slice(0, 10) }}</span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted">No certificates issued yet.</p>
                </section>
            </div>

            <div class="mt-6 flex justify-end rounded-2xl bg-card p-6 shadow-elevated">
                <button
                    type="button"
                    :disabled="togglingDisabled"
                    class="rounded-full px-6 py-2 text-sm font-semibold shadow-elevated transition disabled:cursor-not-allowed disabled:opacity-40"
                    :class="
                        store.current.student.disabled ? 'bg-amber text-white hover:brightness-95' : 'border border-red-300 text-red-600 hover:bg-red-50'
                    "
                    @click="toggleDisabled"
                >
                    {{ store.current.student.disabled ? 'Enable Account' : 'Disable Account' }}
                </button>
            </div>
        </template>
    </div>
</template>
