<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useOrderStore } from '../../stores/orders'
import PricingCard from './PricingCard.vue'

const props = defineProps({
    course: { type: Object, required: true },
})

const router = useRouter()
const store = useOrderStore()
const enrolling = ref(false)
const errorMessage = ref('')

async function enroll() {
    enrolling.value = true
    errorMessage.value = ''

    try {
        const order = await store.createOrder(props.course.id)

        if (order.status === 'paid') {
            // A free course is paid and enrolled synchronously — no PayFast round-trip needed.
            router.push('/student/my-courses')
        } else {
            router.push(`/student/orders/${order.id}/pay`)
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Enrollment could not be started. Please try again.'
    } finally {
        enrolling.value = false
    }
}
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <PricingCard :pricing="course.pricing" />

        <router-link
            v-if="course.is_enrolled"
            :to="`/student/marketplace/courses/${course.id}`"
            class="bg-amber mt-5 block w-full rounded-full px-6 py-3 text-center font-bold text-white shadow-sm transition hover:brightness-95"
        >
            Go To Course
        </router-link>

        <template v-else>
            <button
                type="button"
                :disabled="enrolling"
                class="bg-amber mt-5 w-full rounded-full px-6 py-3 font-bold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60"
                @click="enroll"
            >
                {{ enrolling ? 'Processing…' : 'Enroll' }}
            </button>
            <p v-if="errorMessage" class="mt-2 text-center text-xs text-red-600">{{ errorMessage }}</p>
        </template>

        <div class="mt-5 space-y-2 border-t border-gray-100 pt-5 text-sm text-gray-600">
            <div class="flex justify-between"><span>Modules</span><span class="font-semibold">{{ course.modules_count }}</span></div>
            <div class="flex justify-between"><span>Activities</span><span class="font-semibold">{{ course.activities_count }}</span></div>
            <div class="flex justify-between"><span>Assessments</span><span class="font-semibold">{{ course.assessments_count }}</span></div>
            <div class="flex justify-between"><span>Language</span><span class="font-semibold">{{ course.language ?? '—' }}</span></div>
            <div class="flex justify-between">
                <span>Difficulty</span><span class="font-semibold capitalize">{{ course.difficulty ?? '—' }}</span>
            </div>
        </div>
    </div>
</template>
