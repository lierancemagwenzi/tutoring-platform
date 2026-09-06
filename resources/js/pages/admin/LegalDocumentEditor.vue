<script setup>
import { computed, ref, watch } from 'vue'
import { useAdminSettingsStore } from '../../stores/adminSettings'
import RichTextEditor from '../../components/lms/RichTextEditor.vue'

const props = defineProps({
    type: {
        type: String,
        required: true,
        validator: (value) => ['terms', 'privacy'].includes(value),
    },
})

const settingKey = computed(() => (props.type === 'terms' ? 'legal.terms_and_conditions' : 'legal.privacy_policy'))
const title = computed(() => (props.type === 'terms' ? 'Terms & Conditions' : 'Privacy Policy'))

const store = useAdminSettingsStore()
const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const success = ref(false)
const content = ref('')

// Both /admin/terms-and-conditions and /admin/privacy-policy render this
// same component, so Vue Router reuses the instance instead of remounting
// it — a watcher (not onMounted) is what actually reloads on navigation.
watch(
    () => props.type,
    async () => {
        loading.value = true
        errorMessage.value = ''
        success.value = false
        try {
            const settings = await store.fetchSettings()
            content.value = settings.legal?.[settingKey.value] ?? ''
        } catch (error) {
            errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading this document.'
        } finally {
            loading.value = false
        }
    },
    { immediate: true },
)

async function save() {
    saving.value = true
    errorMessage.value = ''
    success.value = false

    try {
        await store.updateSettings({ [settingKey.value]: content.value })
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
        <h1 class="text-body text-2xl font-bold">{{ title }}</h1>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else class="mt-6 max-w-3xl rounded-2xl bg-card p-6 shadow-elevated">
            <p v-if="errorMessage" class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>
            <p v-if="success" class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">Saved.</p>

            <RichTextEditor v-model="content" />

            <button
                type="button"
                :disabled="saving"
                class="bg-amber mt-6 rounded-full px-6 py-2.5 text-sm font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                @click="save"
            >
                {{ saving ? 'Saving…' : 'Save Changes' }}
            </button>
        </div>
    </div>
</template>
