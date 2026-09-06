<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import api from '../../services/api'
import { useAdminFinancialRulesStore } from '../../stores/adminFinancialRules'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'
import Pagination from '../../components/common/Pagination.vue'
import Modal from '../../components/common/Modal.vue'

const SCOPE_TABS = [
    { value: '', label: 'All' },
    { value: 'tutor', label: 'Tutor' },
    { value: 'service', label: 'Service' },
    { value: 'course', label: 'Course' },
]

const LOOKUP_ENDPOINTS = {
    tutor: { url: '/admin/tutor-profiles', key: 'tutors', label: (t) => `${t.display_name} (${t.email})` },
    service: { url: '/admin/tutoring-services', key: 'services', label: (s) => s.title },
    course: { url: '/admin/self-paced-courses', key: 'courses', label: (c) => c.title },
}

const store = useAdminFinancialRulesStore()
const loading = ref(true)
const errorMessage = ref('')
const activeScope = ref('')
const actioningId = ref(null)

async function load(page = 1) {
    loading.value = true
    errorMessage.value = ''
    try {
        await Promise.all([store.fetchGlobal(), store.fetchOverrides({ scope: activeScope.value, page })])
        syncGlobalForm()
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading financial rules.'
    } finally {
        loading.value = false
    }
}

onMounted(() => load())

function selectScope(scope) {
    activeScope.value = scope
    load()
}

// Global rule form
const globalForm = reactive({ percentage: '', fixed_fee: '', provider_fee_percentage: '', provider_fee_fixed: '', effective_from: '' })
const globalSaving = ref(false)
const globalError = ref('')
const globalSuccessMessage = ref('')

function syncGlobalForm() {
    if (!store.global) return
    globalForm.percentage = store.global.percentage
    globalForm.fixed_fee = store.global.fixed_fee
    globalForm.provider_fee_percentage = store.global.provider_fee_percentage ?? ''
    globalForm.provider_fee_fixed = store.global.provider_fee_fixed ?? ''
    globalForm.effective_from = ''
}

async function saveGlobal() {
    globalSaving.value = true
    globalError.value = ''
    globalSuccessMessage.value = ''
    try {
        const scheduled = Boolean(globalForm.effective_from) && new Date(globalForm.effective_from) > new Date()
        await store.updateGlobal({
            percentage: globalForm.percentage,
            fixed_fee: globalForm.fixed_fee || 0,
            provider_fee_percentage: globalForm.provider_fee_percentage || null,
            provider_fee_fixed: globalForm.provider_fee_fixed || null,
            effective_from: globalForm.effective_from || null,
        })
        // Re-fetch rather than trusting the response directly — if the
        // change was scheduled for a future date, the currently-effective
        // rate is still the old one until that date arrives.
        await store.fetchGlobal()
        syncGlobalForm()
        globalSuccessMessage.value = scheduled ? 'Scheduled — takes effect on the date you chose.' : 'Saved — effective immediately.'
    } catch (error) {
        const errors = error.response?.data?.errors
        globalError.value = errors ? Object.values(errors).flat().join(' ') : (error.response?.data?.message ?? 'Something went wrong.')
    } finally {
        globalSaving.value = false
    }
}

// Activate/deactivate
async function toggleActive(rule) {
    const action = rule.is_active ? 'Deactivate' : 'Activate'
    if (!confirm(`${action} this ${rule.scope}-scope commission rule?`)) return

    actioningId.value = rule.id
    errorMessage.value = ''
    try {
        if (rule.is_active) {
            await store.deactivateOverride(rule.id)
        } else {
            await store.activateOverride(rule.id)
        }
        await load(store.meta.current_page)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong.'
    } finally {
        actioningId.value = null
    }
}

// Create/edit override modal
const showModal = ref(false)
const editing = ref(null)
const overrideForm = reactive({
    scope: 'tutor',
    target: null,
    percentage: '',
    fixed_fee: '',
    provider_fee_percentage: '',
    provider_fee_fixed: '',
    effective_from: '',
})
const saving = ref(false)
const formError = ref('')
const searchQuery = ref('')
const searchResults = ref([])
const searching = ref(false)
let searchTimeout = null

const lookup = computed(() => LOOKUP_ENDPOINTS[overrideForm.scope])

function openCreate() {
    editing.value = null
    overrideForm.scope = 'tutor'
    overrideForm.target = null
    overrideForm.percentage = ''
    overrideForm.fixed_fee = ''
    overrideForm.provider_fee_percentage = ''
    overrideForm.provider_fee_fixed = ''
    overrideForm.effective_from = ''
    searchQuery.value = ''
    searchResults.value = []
    formError.value = ''
    showModal.value = true
}

function openEdit(rule) {
    editing.value = rule
    overrideForm.scope = rule.scope
    overrideForm.target = rule.target
    overrideForm.percentage = rule.percentage
    overrideForm.fixed_fee = rule.fixed_fee
    overrideForm.provider_fee_percentage = rule.provider_fee_percentage ?? ''
    overrideForm.provider_fee_fixed = rule.provider_fee_fixed ?? ''
    overrideForm.effective_from = ''
    formError.value = ''
    showModal.value = true
}

function onScopeChange() {
    overrideForm.target = null
    searchQuery.value = ''
    searchResults.value = []
}

function onSearchInput() {
    clearTimeout(searchTimeout)
    const query = searchQuery.value
    if (!query) {
        searchResults.value = []
        return
    }
    searchTimeout = setTimeout(async () => {
        searching.value = true
        try {
            const { data } = await api.get(lookup.value.url, { params: { search: query, per_page: 5 } })
            searchResults.value = data[lookup.value.key]
        } finally {
            searching.value = false
        }
    }, 300)
}

function pickTarget(item) {
    overrideForm.target = { id: item.id, name: lookup.value.label(item) }
    searchResults.value = []
    searchQuery.value = lookup.value.label(item)
}

const targetFieldKey = computed(() => ({
    tutor: 'tutor_profile_id',
    service: 'service_id',
    course: 'self_paced_course_id',
}[overrideForm.scope]))

async function submitOverride() {
    saving.value = true
    formError.value = ''
    try {
        const payload = {
            percentage: overrideForm.percentage,
            fixed_fee: overrideForm.fixed_fee || 0,
            provider_fee_percentage: overrideForm.provider_fee_percentage || null,
            provider_fee_fixed: overrideForm.provider_fee_fixed || null,
            effective_from: overrideForm.effective_from || null,
        }

        if (editing.value) {
            await store.updateOverride(editing.value.id, payload)
        } else {
            if (!overrideForm.target) {
                formError.value = 'Pick a target before creating the rule.'
                saving.value = false
                return
            }
            await store.createOverride({
                scope: overrideForm.scope,
                [targetFieldKey.value]: overrideForm.target.id,
                ...payload,
            })
        }

        showModal.value = false
        await load(store.meta.current_page)
    } catch (error) {
        const errors = error.response?.data?.errors
        formError.value = errors ? Object.values(errors).flat().join(' ') : (error.response?.data?.message ?? 'Something went wrong.')
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-body text-2xl font-bold">Financial Rules</h1>
        <p class="mt-1 text-sm text-muted">Commission configuration — global default plus tutor/service/course overrides.</p>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <template v-else>
            <section class="mt-6 max-w-xl rounded-2xl bg-card p-6 shadow-elevated">
                <h2 class="text-body font-bold">Global Default</h2>
                <p class="mt-1 text-sm text-muted">Applies when no tutor/service/course override matches.</p>

                <p v-if="globalError" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ globalError }}</p>
                <p v-if="globalSuccessMessage" class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ globalSuccessMessage }}</p>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-body">Platform Percentage</label>
                        <input
                            v-model="globalForm.percentage"
                            type="number" step="0.01" min="0" max="100"
                            class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-body">Platform Fixed Fee</label>
                        <input
                            v-model="globalForm.fixed_fee"
                            type="number" step="0.01" min="0"
                            class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-body">Provider Fee % <span class="font-normal text-muted">(optional)</span></label>
                        <input
                            v-model="globalForm.provider_fee_percentage"
                            type="number" step="0.01" min="0" max="100"
                            class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-body">Provider Fixed Fee <span class="font-normal text-muted">(optional)</span></label>
                        <input
                            v-model="globalForm.provider_fee_fixed"
                            type="number" step="0.01" min="0"
                            class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                        />
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold text-body">
                            Effective From <span class="font-normal text-muted">(leave blank to apply immediately)</span>
                        </label>
                        <input
                            v-model="globalForm.effective_from"
                            type="date"
                            class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                        />
                    </div>
                </div>

                <button
                    type="button"
                    :disabled="globalSaving"
                    class="bg-amber mt-5 rounded-full px-6 py-2.5 text-sm font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="saveGlobal"
                >
                    {{ globalSaving ? 'Saving…' : 'Save Global Rule' }}
                </button>
            </section>

            <section class="mt-8">
                <div class="flex items-center justify-between">
                    <h2 class="text-body font-bold">Overrides</h2>
                    <button
                        type="button"
                        class="bg-amber rounded-full px-6 py-2.5 text-sm font-semibold text-white shadow-elevated transition hover:brightness-95"
                        @click="openCreate"
                    >
                        New Override
                    </button>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-for="tab in SCOPE_TABS"
                        :key="tab.value"
                        type="button"
                        class="rounded-full px-4 py-2 text-sm font-semibold transition"
                        :class="activeScope === tab.value ? 'bg-amber text-white' : 'border border-border text-body hover:brightness-95'"
                        @click="selectScope(tab.value)"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div v-if="store.overrides.length === 0" class="mt-8 text-center text-muted">No overrides configured.</div>

                <div v-else class="mt-4 space-y-3">
                    <div v-for="rule in store.overrides" :key="rule.id" class="rounded-2xl bg-card p-5 shadow-elevated">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <span class="rounded-full bg-card-alt px-2.5 py-0.5 text-xs font-semibold text-muted capitalize">{{ rule.scope }}</span>
                                <p class="text-body mt-1.5 font-bold">{{ rule.target?.name ?? '—' }}</p>
                                <p class="mt-1 text-sm text-muted">{{ rule.percentage }}% + R{{ rule.fixed_fee }} fixed</p>
                                <p v-if="rule.is_scheduled" class="mt-1 text-xs text-blue-600">Takes effect {{ rule.effective_from?.slice(0, 10) }}</p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1.5">
                                <span v-if="rule.is_scheduled" class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">Scheduled</span>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="adminStatusBadge(rule.is_active ? 'active' : 'inactive')">
                                    {{ adminStatusLabel(rule.is_active ? 'active' : 'inactive') }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-border pt-4">
                            <button type="button" class="text-accent text-sm font-semibold" @click="openEdit(rule)">Edit</button>
                            <button
                                type="button"
                                :disabled="actioningId === rule.id"
                                class="text-sm font-semibold disabled:opacity-40"
                                :class="rule.is_active ? 'text-red-600' : 'text-green-600'"
                                @click="toggleActive(rule)"
                            >
                                {{ rule.is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </div>
                    </div>
                </div>

                <Pagination :meta="store.meta" @change="load" />
            </section>
        </template>

        <Modal v-model="showModal" :title="editing ? 'Edit Override' : 'New Override'">
            <div class="space-y-4">
                <p v-if="formError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ formError }}</p>

                <template v-if="!editing">
                    <div>
                        <label class="block text-sm font-semibold text-body">Scope</label>
                        <select
                            v-model="overrideForm.scope"
                            class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                            @change="onScopeChange"
                        >
                            <option value="tutor">Tutor</option>
                            <option value="service">Service</option>
                            <option value="course">Course</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-body">{{ overrideForm.scope === 'tutor' ? 'Tutor' : overrideForm.scope === 'service' ? 'Service' : 'Course' }}</label>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search by name…"
                            class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                            @input="onSearchInput"
                        />
                        <ul v-if="searchResults.length > 0" class="mt-1.5 max-h-40 overflow-y-auto rounded-xl border border-border">
                            <li v-for="item in searchResults" :key="item.id">
                                <button
                                    type="button"
                                    class="block w-full px-3.5 py-2 text-left text-sm hover:brightness-95"
                                    @click="pickTarget(item)"
                                >
                                    {{ lookup.label(item) }}
                                </button>
                            </li>
                        </ul>
                        <p v-if="overrideForm.target" class="mt-1.5 text-xs text-green-700">Selected: {{ overrideForm.target.name }}</p>
                    </div>
                </template>
                <template v-else>
                    <p class="text-sm text-muted">
                        <span class="font-semibold capitalize">{{ overrideForm.scope }}</span> — {{ overrideForm.target?.name }}
                    </p>
                </template>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-body">Percentage</label>
                        <input
                            v-model="overrideForm.percentage"
                            type="number" step="0.01" min="0" max="100"
                            class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-body">Fixed Fee</label>
                        <input
                            v-model="overrideForm.fixed_fee"
                            type="number" step="0.01" min="0"
                            class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-body">Provider Fee % <span class="font-normal text-muted">(optional)</span></label>
                        <input
                            v-model="overrideForm.provider_fee_percentage"
                            type="number" step="0.01" min="0" max="100"
                            class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-body">Provider Fixed Fee <span class="font-normal text-muted">(optional)</span></label>
                        <input
                            v-model="overrideForm.provider_fee_fixed"
                            type="number" step="0.01" min="0"
                            class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                        />
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold text-body">
                            Effective From <span class="font-normal text-muted">(leave blank to apply immediately)</span>
                        </label>
                        <input
                            v-model="overrideForm.effective_from"
                            type="date"
                            class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                        />
                        <p v-if="editing" class="mt-1.5 text-xs text-muted">Saving creates a new rate rather than changing the existing one — past bookings keep the rate they were made under.</p>
                    </div>
                </div>
            </div>

            <template #footer>
                <button
                    type="button"
                    class="rounded-full border border-border px-4 py-2 text-sm font-semibold text-body hover:brightness-95"
                    @click="showModal = false"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="saving"
                    class="bg-amber rounded-full px-6 py-2 text-sm font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="submitOverride"
                >
                    {{ saving ? 'Saving…' : editing ? 'Save Changes' : 'Create Override' }}
                </button>
            </template>
        </Modal>
    </div>
</template>
