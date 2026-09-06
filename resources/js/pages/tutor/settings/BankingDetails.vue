<script setup>
import { onMounted, reactive, ref } from 'vue'
import SettingsTabs from '../../../components/tutor/SettingsTabs.vue'
import { useTutorBankingDetailsStore } from '../../../stores/tutorBankingDetails'

const ACCOUNT_TYPES = [
    { value: 'savings', label: 'Savings' },
    { value: 'cheque', label: 'Cheque / Current' },
]

const store = useTutorBankingDetailsStore()
const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const success = ref(false)

const form = reactive({
    bank_name: '',
    account_holder_name: '',
    account_number: '',
    branch_code: '',
    account_type: 'savings',
})

onMounted(async () => {
    loading.value = true
    try {
        const bankAccount = await store.fetch()
        if (bankAccount) {
            form.bank_name = bankAccount.bank_name
            form.account_holder_name = bankAccount.account_holder_name
            form.account_number = bankAccount.account_number
            form.branch_code = bankAccount.branch_code
            form.account_type = bankAccount.account_type
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading your banking details.'
    } finally {
        loading.value = false
    }
})

async function save() {
    saving.value = true
    errorMessage.value = ''
    success.value = false

    try {
        await store.save({ ...form })
        success.value = true
    } catch (error) {
        const errors = error.response?.data?.errors
        errorMessage.value = errors ? Object.values(errors).flat().join(' ') : (error.response?.data?.message ?? 'Something went wrong.')
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-body text-2xl font-bold">Settings</h1>

        <div class="mt-6">
            <SettingsTabs />
        </div>

        <div class="bg-card shadow-elevated mt-6 max-w-xl rounded-2xl p-6">
            <h2 class="text-body font-bold">Banking Details</h2>
            <p class="text-muted mt-1 text-sm">
                Used by the platform to pay out your earnings. You can't create a service or self-paced course, or be
                approved as a tutor, without these on file.
            </p>

            <div v-if="loading" class="flex justify-center py-16">
                <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
            </div>

            <div v-else class="mt-6">
                <p v-if="errorMessage" class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>
                <p v-if="success" class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">Saved.</p>

                <div class="space-y-4">
                    <div>
                        <label class="text-body block text-sm font-semibold" for="bank_name">Bank Name</label>
                        <input
                            id="bank_name"
                            v-model="form.bank_name"
                            type="text"
                            class="focus:border-accent bg-card text-body border-border mt-1.5 w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none"
                        />
                    </div>
                    <div>
                        <label class="text-body block text-sm font-semibold" for="account_holder_name">Account Holder Name</label>
                        <input
                            id="account_holder_name"
                            v-model="form.account_holder_name"
                            type="text"
                            class="focus:border-accent bg-card text-body border-border mt-1.5 w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none"
                        />
                    </div>
                    <div>
                        <label class="text-body block text-sm font-semibold" for="account_number">Account Number</label>
                        <input
                            id="account_number"
                            v-model="form.account_number"
                            type="text"
                            class="focus:border-accent bg-card text-body border-border mt-1.5 w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-body block text-sm font-semibold" for="branch_code">Branch Code</label>
                            <input
                                id="branch_code"
                                v-model="form.branch_code"
                                type="text"
                                class="focus:border-accent bg-card text-body border-border mt-1.5 w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none"
                            />
                        </div>
                        <div>
                            <label class="text-body block text-sm font-semibold" for="account_type">Account Type</label>
                            <select
                                id="account_type"
                                v-model="form.account_type"
                                class="focus:border-accent bg-card text-body border-border mt-1.5 w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none"
                            >
                                <option v-for="type in ACCOUNT_TYPES" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    :disabled="saving"
                    class="bg-amber shadow-elevated mt-6 rounded-full px-6 py-2.5 text-sm font-semibold text-white transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="save"
                >
                    {{ saving ? 'Saving…' : 'Save Banking Details' }}
                </button>
            </div>
        </div>
    </div>
</template>
