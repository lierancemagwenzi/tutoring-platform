<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAdminSettingsStore } from '../../stores/adminSettings'

const TABS = [
    {
        key: 'general',
        label: 'General',
        fields: [
            { key: 'general.platform_name', label: 'Platform Name', type: 'text' },
            { key: 'general.logo_url', label: 'Logo URL', type: 'text' },
            { key: 'general.description', label: 'Description', type: 'textarea' },
            { key: 'general.timezone', label: 'Default Timezone', type: 'text' },
            { key: 'general.currency', label: 'Default Currency', type: 'text' },
            { key: 'general.contact_email', label: 'Contact Email', type: 'text' },
        ],
    },
    {
        key: 'email',
        label: 'Email',
        fields: [
            { key: 'email.sender_name', label: 'Sender Name', type: 'text' },
            { key: 'email.sender_email', label: 'Sender Email', type: 'text' },
        ],
    },
    {
        key: 'authentication',
        label: 'Authentication',
        fields: [
            { key: 'auth.registration_enabled', label: 'Registration Enabled', type: 'boolean' },
            { key: 'auth.email_verification_enabled', label: 'Email Verification Enabled', type: 'boolean' },
            { key: 'auth.otp_expiry_minutes', label: 'OTP Expiry (minutes)', type: 'number' },
            { key: 'auth.forgot_password_enabled', label: 'Forgot Password Enabled', type: 'boolean' },
        ],
    },
    {
        key: 'courses',
        label: 'Courses',
        fields: [{ key: 'courses.certificates_enabled', label: 'Certificate Generation Enabled', type: 'boolean' }],
    },
    {
        key: 'pricing',
        label: 'Pricing',
        fields: [
            { key: 'pricing.booking_fee_enabled', label: 'Platform & Booking Fee Enabled', type: 'boolean' },
            { key: 'pricing.booking_fee_percentage', label: 'Platform & Booking Fee (%)', type: 'number' },
            { key: 'pricing.min_tutor_price', label: 'Minimum Tutor Price (leave blank to disable)', type: 'number' },
            { key: 'pricing.max_tutor_price', label: 'Maximum Tutor Price (leave blank to disable)', type: 'number' },
        ],
    },
]

const route = useRoute()
const store = useAdminSettingsStore()
const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const success = ref(false)
const activeTab = ref(TABS.some((tab) => tab.key === route.query.tab) ? route.query.tab : 'general')

const form = reactive({})

onMounted(async () => {
    loading.value = true
    try {
        await store.fetchSettings()
        for (const tab of TABS) {
            for (const field of tab.fields) {
                form[field.key] = store.settings[tab.key]?.[field.key] ?? (field.type === 'boolean' ? false : '')
            }
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading settings.'
    } finally {
        loading.value = false
    }
})

const currentTab = computed(() => TABS.find((tab) => tab.key === activeTab.value))

async function save() {
    saving.value = true
    errorMessage.value = ''
    success.value = false

    const changed = {}
    for (const field of currentTab.value.fields) {
        changed[field.key] = form[field.key]
    }

    try {
        await store.updateSettings(changed)
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
        <h1 class="text-ink text-2xl font-bold">Settings</h1>

        <div class="mt-6 flex gap-1 overflow-x-auto border-b border-gray-200">
            <button
                v-for="tab in TABS"
                :key="tab.key"
                type="button"
                class="shrink-0 border-b-2 px-4 py-2 text-sm font-semibold whitespace-nowrap transition"
                :class="activeTab === tab.key ? 'border-accent text-accent' : 'border-transparent text-gray-500 hover:text-gray-700'"
                @click="activeTab = tab.key; success = false; errorMessage = ''"
            >
                {{ tab.label }}
            </button>
        </div>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else class="mt-6 max-w-xl rounded-2xl bg-white p-6 shadow-sm">
            <p v-if="errorMessage" class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>
            <p v-if="success" class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">Saved.</p>

            <div class="space-y-4">
                <div v-for="field in currentTab.fields" :key="field.key">
                    <label v-if="field.type !== 'boolean'" class="block text-sm font-semibold text-gray-700" :for="field.key">
                        {{ field.label }}
                    </label>

                    <textarea
                        v-if="field.type === 'textarea'"
                        :id="field.key"
                        v-model="form[field.key]"
                        rows="3"
                        class="focus:border-accent mt-1.5 w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 outline-none"
                    />
                    <input
                        v-else-if="field.type === 'number'"
                        :id="field.key"
                        v-model.number="form[field.key]"
                        type="number"
                        class="focus:border-accent mt-1.5 w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 outline-none"
                    />
                    <label v-else-if="field.type === 'boolean'" class="flex items-center gap-3 text-sm font-medium text-gray-700">
                        <input :id="field.key" v-model="form[field.key]" type="checkbox" class="text-accent h-4 w-4 rounded border-gray-300" />
                        {{ field.label }}
                    </label>
                    <input
                        v-else
                        :id="field.key"
                        v-model="form[field.key]"
                        type="text"
                        class="focus:border-accent mt-1.5 w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 outline-none"
                    />
                </div>
            </div>

            <button
                type="button"
                :disabled="saving"
                class="bg-amber mt-6 rounded-full px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                @click="save"
            >
                {{ saving ? 'Saving…' : 'Save Changes' }}
            </button>
        </div>
    </div>
</template>
