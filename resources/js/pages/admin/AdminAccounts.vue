<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useAdminAccountsStore } from '../../stores/adminAccounts'
import { useAuthStore } from '../../stores/auth'
import Modal from '../../components/common/Modal.vue'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'

const store = useAdminAccountsStore()
const auth = useAuthStore()
const loading = ref(true)
const errorMessage = ref('')
const actioningId = ref(null)

async function load() {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchList()
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading admins.'
    } finally {
        loading.value = false
    }
}

onMounted(() => load())

function rowStatus(admin) {
    if (admin.is_pending) return 'pending'
    if (admin.disabled) return 'disabled'
    return 'active'
}

function manageable(admin) {
    return !admin.is_super_admin && admin.id !== auth.user?.id
}

// Invite modal
const inviteModalOpen = ref(false)
const inviteForm = reactive({ first_name: '', last_name: '', email: '' })
const inviting = ref(false)
const inviteError = ref('')

function openInvite() {
    inviteForm.first_name = ''
    inviteForm.last_name = ''
    inviteForm.email = ''
    inviteError.value = ''
    inviteModalOpen.value = true
}

async function submitInvite() {
    inviting.value = true
    inviteError.value = ''
    try {
        await store.invite({ ...inviteForm })
        inviteModalOpen.value = false
    } catch (error) {
        const errors = error.response?.data?.errors
        inviteError.value = errors ? Object.values(errors).flat().join(' ') : (error.response?.data?.message ?? 'Something went wrong.')
    } finally {
        inviting.value = false
    }
}

async function resend(admin) {
    actioningId.value = admin.id
    errorMessage.value = ''
    try {
        await store.resendInvite(admin.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Could not resend the invite.'
    } finally {
        actioningId.value = null
    }
}

async function toggleActive(admin) {
    actioningId.value = admin.id
    errorMessage.value = ''
    try {
        if (admin.disabled) {
            await store.activate(admin.id)
        } else {
            await store.deactivate(admin.id)
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong.'
    } finally {
        actioningId.value = null
    }
}

async function remove(admin) {
    if (!confirm(`Delete the admin account for ${admin.name}? This cannot be undone.`)) {
        return
    }
    actioningId.value = admin.id
    errorMessage.value = ''
    try {
        await store.remove(admin.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Could not delete this admin.'
    } finally {
        actioningId.value = null
    }
}
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-body text-2xl font-bold">Admins</h1>
                <p class="mt-1 text-sm text-muted">Invite and manage other administrator accounts.</p>
            </div>
            <button
                type="button"
                class="bg-amber rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-elevated transition hover:brightness-95"
                @click="openInvite"
            >
                Invite Admin
            </button>
        </div>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.admins.length === 0" class="mt-16 text-center text-muted">No admins yet.</div>

        <div v-else class="mt-6 space-y-3">
            <div v-for="admin in store.admins" :key="admin.id" class="rounded-2xl bg-card p-5 shadow-elevated">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-body font-bold">{{ admin.name }}</p>
                            <span v-if="admin.is_super_admin" class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                                Super Admin
                            </span>
                            <span v-if="admin.id === auth.user?.id" class="text-xs text-muted">(you)</span>
                        </div>
                        <p class="mt-1 text-sm text-muted">{{ admin.email }}</p>
                        <p class="mt-2 text-xs text-muted">Invited {{ admin.invited_at.slice(0, 10) }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="adminStatusBadge(rowStatus(admin))">
                        {{ adminStatusLabel(rowStatus(admin)) }}
                    </span>
                </div>

                <div v-if="manageable(admin)" class="mt-4 flex flex-wrap items-center gap-4 border-t border-border pt-4">
                    <button
                        v-if="admin.is_pending"
                        type="button"
                        :disabled="actioningId === admin.id"
                        class="text-accent text-sm font-semibold disabled:opacity-40"
                        @click="resend(admin)"
                    >
                        Resend Invite
                    </button>
                    <button
                        v-else
                        type="button"
                        :disabled="actioningId === admin.id"
                        class="text-sm font-semibold disabled:opacity-40"
                        :class="admin.disabled ? 'text-green-600' : 'text-amber-600'"
                        @click="toggleActive(admin)"
                    >
                        {{ admin.disabled ? 'Activate' : 'Deactivate' }}
                    </button>
                    <button
                        type="button"
                        :disabled="actioningId === admin.id"
                        class="text-sm font-semibold text-red-600 disabled:opacity-40"
                        @click="remove(admin)"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>

        <Modal v-model="inviteModalOpen" title="Invite Admin">
            <div class="space-y-4">
                <p v-if="inviteError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ inviteError }}</p>

                <div>
                    <label class="block text-sm font-semibold text-body" for="invite-first-name">First Name</label>
                    <input
                        id="invite-first-name"
                        v-model="inviteForm.first_name"
                        type="text"
                        class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                    />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-body" for="invite-last-name">Last Name</label>
                    <input
                        id="invite-last-name"
                        v-model="inviteForm.last_name"
                        type="text"
                        class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                    />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-body" for="invite-email">Email</label>
                    <input
                        id="invite-email"
                        v-model="inviteForm.email"
                        type="email"
                        class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                    />
                </div>
            </div>

            <template #footer>
                <button type="button" class="rounded-full border border-border px-5 py-2.5 text-sm font-semibold text-body" @click="inviteModalOpen = false">
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="inviting"
                    class="bg-amber rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-elevated transition hover:brightness-95 disabled:opacity-40"
                    @click="submitInvite"
                >
                    {{ inviting ? 'Sending…' : 'Send Invite' }}
                </button>
            </template>
        </Modal>
    </div>
</template>
