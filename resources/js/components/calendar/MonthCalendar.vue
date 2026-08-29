<script setup>
import { computed } from 'vue'
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    year: { type: Number, required: true },
    month: { type: Number, required: true }, // 1-12
    selectedDate: { type: String, default: null }, // 'YYYY-MM-DD'
    availableDates: { type: Set, default: () => new Set() },
})

const emit = defineEmits(['prev-month', 'next-month', 'select-date'])

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const MONTH_NAMES = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
]

function pad(value) {
    return String(value).padStart(2, '0')
}

function toDateString(year, month, day) {
    return `${year}-${pad(month)}-${pad(day)}`
}

const now = new Date()
const todayString = toDateString(now.getFullYear(), now.getMonth() + 1, now.getDate())

const monthLabel = computed(() => `${MONTH_NAMES[props.month - 1]} ${props.year}`)

const weeks = computed(() => {
    const firstOfMonth = new Date(props.year, props.month - 1, 1)
    const daysInMonth = new Date(props.year, props.month, 0).getDate()
    const startOffset = firstOfMonth.getDay()

    const cells = []
    for (let i = 0; i < startOffset; i++) {
        cells.push(null)
    }
    for (let day = 1; day <= daysInMonth; day++) {
        cells.push(day)
    }
    while (cells.length % 7 !== 0) {
        cells.push(null)
    }

    const rows = []
    for (let i = 0; i < cells.length; i += 7) {
        rows.push(cells.slice(i, i + 7))
    }
    return rows
})

function dateStringFor(day) {
    return toDateString(props.year, props.month, day)
}

function isPast(day) {
    return dateStringFor(day) < todayString
}

function isToday(day) {
    return dateStringFor(day) === todayString
}

function isSelected(day) {
    return dateStringFor(day) === props.selectedDate
}

function hasAvailability(day) {
    return props.availableDates.has(dateStringFor(day))
}

function selectDay(day) {
    if (day === null || isPast(day)) {
        return
    }
    emit('select-date', dateStringFor(day))
}
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-ink text-lg font-bold">{{ monthLabel }}</h2>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    aria-label="Previous month"
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:bg-gray-50"
                    @click="$emit('prev-month')"
                >
                    <ChevronLeftIcon class="h-5 w-5" />
                </button>
                <button
                    type="button"
                    aria-label="Next month"
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:bg-gray-50"
                    @click="$emit('next-month')"
                >
                    <ChevronRightIcon class="h-5 w-5" />
                </button>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-7 gap-1 text-center text-xs font-semibold text-gray-400">
            <span v-for="weekday in WEEKDAYS" :key="weekday">{{ weekday }}</span>
        </div>

        <div class="mt-2 grid grid-cols-7 gap-1">
            <template v-for="(week, weekIndex) in weeks" :key="weekIndex">
                <button
                    v-for="(day, dayIndex) in week"
                    :key="dayIndex"
                    type="button"
                    :disabled="day === null || isPast(day)"
                    class="relative flex aspect-square flex-col items-center justify-center rounded-xl text-sm transition"
                    :class="[
                        day === null ? 'invisible' : '',
                        isSelected(day)
                            ? 'bg-amber font-bold text-white'
                            : isPast(day)
                              ? 'cursor-not-allowed text-gray-300'
                              : isToday(day)
                                ? 'border-accent text-accent border font-semibold'
                                : 'text-gray-700 hover:bg-gray-50',
                    ]"
                    @click="selectDay(day)"
                >
                    {{ day }}
                    <span
                        v-if="day !== null && hasAvailability(day)"
                        class="absolute bottom-1.5 h-1.5 w-1.5 rounded-full"
                        :class="isSelected(day) ? 'bg-white' : 'bg-amber'"
                    />
                </button>
            </template>
        </div>
    </div>
</template>
