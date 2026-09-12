<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { CalendarDaysIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useTutorAvailabilityStore } from '../../stores/tutorAvailability'
import { useTutorServicesStore } from '../../stores/tutorServices'
import MonthCalendar from '../../components/calendar/MonthCalendar.vue'
import Modal from '../../components/common/Modal.vue'
import FloatingLabelInput from '../../components/forms/FloatingLabelInput.vue'

const store = useTutorAvailabilityStore()
const servicesStore = useTutorServicesStore()

const today = new Date()
const year = ref(today.getFullYear())
const month = ref(today.getMonth() + 1) // 1-12
const selectedDate = ref(null)

const loading = ref(true)
const saving = ref(false)
const deleting = ref(false)
const formError = ref('')
const successMessage = ref('')

const showFormModal = ref(false)
const editingId = ref(null)
const deleteTarget = ref(null)

const form = reactive({
    startTime: '',
    endTime: '',
})

let successTimer = null

const monthParam = computed(() => `${year.value}-${String(month.value).padStart(2, '0')}`)
const isEditing = computed(() => editingId.value !== null)
const daySlots = computed(() => (selectedDate.value ? store.slotsByDate(selectedDate.value) : []))

function formatDateLong(dateString) {
    const [y, m, d] = dateString.split('-').map(Number)
    return new Date(y, m - 1, d).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })
}

const selectedDateLabel = computed(() => (selectedDate.value ? formatDateLong(selectedDate.value) : ''))

function showSuccess(message) {
    successMessage.value = message
    clearTimeout(successTimer)
    successTimer = setTimeout(() => {
        successMessage.value = ''
    }, 3000)
}

async function loadMonth() {
    loading.value = true
    try {
        await store.fetchMonth(monthParam.value)
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    loadMonth()
    servicesStore.fetchServices()
})

// A slot's own window has no service tied to it — students only see it as
// bookable for a service whose full session_duration_minutes fits inside
// it (see BookingAvailabilityService::bookableStartTimesForSlot()). A slot
// shorter than a published service's duration silently never appears as
// an option for that service, with nothing on the tutor's side to explain
// why — this surfaces that instead of leaving it invisible.
const slotDurationMinutes = computed(() => {
    if (!form.startTime || !form.endTime) return null

    const [startH, startM] = form.startTime.split(':').map(Number)
    const [endH, endM] = form.endTime.split(':').map(Number)
    const minutes = endH * 60 + endM - (startH * 60 + startM)

    return minutes > 0 ? minutes : null
})

const servicesTooLongForSlot = computed(() => {
    if (slotDurationMinutes.value === null) return []

    return servicesStore.services.filter(
        (service) => service.visibility === 'published' && service.session_duration_minutes > slotDurationMinutes.value,
    )
})

function selectDate(dateString) {
    selectedDate.value = dateString
}

function prevMonth() {
    if (month.value === 1) {
        month.value = 12
        year.value -= 1
    } else {
        month.value -= 1
    }
    selectedDate.value = null
    loadMonth()
}

function nextMonth() {
    if (month.value === 12) {
        month.value = 1
        year.value += 1
    } else {
        month.value += 1
    }
    selectedDate.value = null
    loadMonth()
}

function openAddModal() {
    form.startTime = ''
    form.endTime = ''
    editingId.value = null
    formError.value = ''
    showFormModal.value = true
}

function openEditModal(slot) {
    form.startTime = slot.start_time
    form.endTime = slot.end_time
    editingId.value = slot.id
    formError.value = ''
    showFormModal.value = true
}

async function saveSlot() {
    saving.value = true
    formError.value = ''

    try {
        if (isEditing.value) {
            await store.updateSlot(editingId.value, { start_time: form.startTime, end_time: form.endTime })
            showSuccess('Time slot updated.')
        } else {
            await store.addSlot({ date: selectedDate.value, start_time: form.startTime, end_time: form.endTime })
            showSuccess('Time slot added.')
        }
        showFormModal.value = false
    } catch (error) {
        const errors = error.response?.data?.errors
        formError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        saving.value = false
    }
}

function confirmDelete(slot) {
    deleteTarget.value = slot
}

async function removeSlot() {
    deleting.value = true

    try {
        await store.removeSlot(deleteTarget.value.id, selectedDate.value)
        showSuccess('Time slot removed.')
        deleteTarget.value = null
    } finally {
        deleting.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-body text-2xl font-bold">Teaching Calendar</h1>
        <p class="mt-1 text-muted">Manage the days and times you're available to teach.</p>

        <p v-if="successMessage" class="mt-6 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-600">{{ successMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <MonthCalendar
                    :year="year"
                    :month="month"
                    :selected-date="selectedDate"
                    :available-dates="store.datesWithAvailability"
                    @select-date="selectDate"
                    @prev-month="prevMonth"
                    @next-month="nextMonth"
                />
            </div>

            <div class="rounded-2xl bg-card p-6 shadow-elevated lg:col-span-1">
                <div v-if="!selectedDate" class="flex flex-col items-center py-12 text-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-card-alt text-muted">
                        <CalendarDaysIcon class="h-8 w-8" />
                    </span>
                    <p class="mt-4 text-muted">Select a date to view or add availability.</p>
                </div>

                <template v-else>
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-body font-bold">{{ selectedDateLabel }}</h2>
                        <button
                            v-if="daySlots.length > 0"
                            type="button"
                            class="bg-amber flex shrink-0 items-center gap-1 rounded-full px-4 py-2 text-xs font-bold text-white shadow-elevated transition hover:brightness-95"
                            @click="openAddModal"
                        >
                            <PlusIcon class="h-4 w-4" />
                            Add slot
                        </button>
                    </div>

                    <div v-if="daySlots.length === 0" class="mt-8 flex flex-col items-center text-center">
                        <p class="text-muted">No availability has been added for this date.</p>
                        <button
                            type="button"
                            class="bg-amber mt-5 flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-elevated transition hover:brightness-95"
                            @click="openAddModal"
                        >
                            <PlusIcon class="h-4 w-4" />
                            Add Time Slot
                        </button>
                    </div>

                    <ul v-else class="mt-5 space-y-3">
                        <li
                            v-for="slot in daySlots"
                            :key="slot.id"
                            class="flex items-center justify-between rounded-xl border border-border px-4 py-3"
                        >
                            <span class="text-body font-semibold">{{ slot.start_time }} - {{ slot.end_time }}</span>
                            <span class="flex items-center gap-3">
                                <button
                                    type="button"
                                    aria-label="Edit time slot"
                                    class="text-accent"
                                    @click="openEditModal(slot)"
                                >
                                    <PencilSquareIcon class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    aria-label="Delete time slot"
                                    class="text-red-600"
                                    @click="confirmDelete(slot)"
                                >
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                            </span>
                        </li>
                    </ul>
                </template>
            </div>
        </div>

        <Modal v-model="showFormModal" :title="isEditing ? 'Edit Time Slot' : 'Add Time Slot'">
            <form class="space-y-6" novalidate @submit.prevent="saveSlot">
                <p v-if="formError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ formError }}</p>

                <div>
                    <p class="mb-1 text-sm font-medium text-muted">Selected Date</p>
                    <p class="text-body font-semibold">{{ selectedDateLabel }}</p>
                </div>

                <FloatingLabelInput id="start-time" v-model="form.startTime" type="time" label="Start Time" />
                <FloatingLabelInput id="end-time" v-model="form.endTime" type="time" label="End Time" />

                <p v-if="servicesTooLongForSlot.length > 0" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    This is a {{ slotDurationMinutes }}-minute window. Students won't be able to book it for
                    {{ servicesTooLongForSlot.length === 1 ? 'this service' : 'these services' }}, since
                    {{ servicesTooLongForSlot.length === 1 ? 'its session is' : 'their sessions are' }} longer than that:
                    {{ servicesTooLongForSlot.map((service) => `${service.title} (${service.session_duration_minutes} min)`).join(', ') }}.
                </p>

                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-full border border-border px-5 py-2.5 font-semibold text-body"
                        @click="showFormModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="saving || !form.startTime || !form.endTime"
                        class="bg-amber rounded-full px-5 py-2.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {{ saving ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </form>
        </Modal>

        <Modal
            :model-value="deleteTarget !== null"
            :title="`Remove ${deleteTarget?.start_time} - ${deleteTarget?.end_time}?`"
            @update:model-value="deleteTarget = null"
        >
            <p class="text-muted">This time slot will no longer be available.</p>
            <template #footer>
                <button
                    type="button"
                    class="rounded-full border border-border px-5 py-2.5 font-semibold text-body"
                    @click="deleteTarget = null"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="deleting"
                    class="rounded-full bg-red-600 px-5 py-2.5 font-semibold text-white shadow-elevated transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="removeSlot"
                >
                    {{ deleting ? 'Removing…' : 'Remove' }}
                </button>
            </template>
        </Modal>
    </div>
</template>
