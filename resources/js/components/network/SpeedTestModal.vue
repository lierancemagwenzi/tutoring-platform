<script setup>
import { computed, ref, watch } from 'vue'
import { VideoCameraIcon } from '@heroicons/vue/24/outline'
import Modal from '../common/Modal.vue'
import SpeedTestPanel from './SpeedTestPanel.vue'
import { recentResult } from '../../services/speedTest'

// The network check in a dialog. Given a joinUrl it's the pre-session
// check: it tests straight away (unless there's a result from the last
// 15 minutes) and offers "Join meeting" throughout — the tutor is told
// about their connection, never blocked by it.
const props = defineProps({
    modelValue: { type: Boolean, required: true },
    joinUrl: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

const running = ref(false)
const recent = ref(null)
const isPreSession = computed(() => Boolean(props.joinUrl))

// Decided each time the dialog opens, before the panel renders (it's
// remounted per open via v-if/key, so its autoStart reads this fresh).
watch(
    () => props.modelValue,
    (open) => {
        if (open) recent.value = isPreSession.value ? recentResult() : null
    },
    { immediate: true, flush: 'pre' },
)

function join() {
    window.open(props.joinUrl, '_blank', 'noopener')
    emit('update:modelValue', false)
}
</script>

<template>
    <Modal
        :model-value="modelValue"
        :title="isPreSession ? 'Check your connection before joining' : 'Internet speed test'"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <SpeedTestPanel
            v-if="modelValue"
            :key="String(modelValue)"
            :auto-start="isPreSession && !recent"
            :initial-result="isPreSession ? recent : undefined"
            @running="running = $event"
        />

        <template v-if="isPreSession" #footer>
            <button
                type="button"
                class="rounded-full border border-border px-4 py-2 text-sm font-semibold text-body hover:brightness-95"
                @click="emit('update:modelValue', false)"
            >
                Cancel
            </button>
            <button
                type="button"
                class="bg-amber flex items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold text-white shadow-elevated transition hover:brightness-95"
                @click="join"
            >
                <VideoCameraIcon class="h-4 w-4" />
                {{ running ? 'Skip test & join' : 'Join meeting' }}
            </button>
        </template>
    </Modal>
</template>
