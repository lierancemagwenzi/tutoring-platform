<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { ShieldCheckIcon } from '@heroicons/vue/24/outline'
import { useRegistrationStore } from '../../stores/registration'
import FloatingLabelInput from '../../components/forms/FloatingLabelInput.vue'
import PhoneInput from '../../components/forms/PhoneInput.vue'

const registration = useRegistrationStore()
const router = useRouter()

const isUnderage = computed(() => registration.isUnderage)

const canContinue = computed(() => {
    if (isUnderage.value) {
        return (
            registration.learner.firstName &&
            registration.learner.surname &&
            registration.guardian.firstName &&
            registration.guardian.surname &&
            registration.guardian.email &&
            registration.guardian.cellphone &&
            registration.guardian.relationshipToStudent &&
            registration.guardian.consent
        )
    }
    return (
        registration.learner.firstName &&
        registration.learner.surname &&
        registration.learner.email &&
        registration.learner.cellphone
    )
})

function continueRegistration() {
    router.push('/register/password')
}
</script>

<template>
    <div class="w-full max-w-md">
        <div class="text-center">
            <h1 class="text-body text-3xl font-bold">Let's get you set up</h1>
            <p class="text-muted mt-2">
                <template v-if="isUnderage">
                    Since the learner is under 18, a guardian will need to complete this sign-up on their behalf.
                </template>
                <template v-else> Please enter your personal details. </template>
            </p>
        </div>

        <form class="mt-10 space-y-6" novalidate @submit.prevent="continueRegistration">
            <template v-if="isUnderage">
                <div class="space-y-6">
                    <p class="text-muted font-semibold">Learner's details</p>
                    <FloatingLabelInput id="learner-first-name" v-model="registration.learner.firstName" label="Full name(s)" />
                    <FloatingLabelInput id="learner-surname" v-model="registration.learner.surname" label="Surname" />
                </div>

                <div class="space-y-6">
                    <p class="text-muted font-semibold">Guardian's details</p>
                    <FloatingLabelInput id="guardian-first-name" v-model="registration.guardian.firstName" label="Full name(s)" />
                    <FloatingLabelInput id="guardian-surname" v-model="registration.guardian.surname" label="Surname" />
                    <FloatingLabelInput id="guardian-email" v-model="registration.guardian.email" label="Email address" type="email" />
                    <PhoneInput
                        id="guardian-cellphone"
                        v-model="registration.guardian.cellphone"
                        v-model:country-code="registration.guardian.countryCode"
                    />
                    <FloatingLabelInput
                        id="guardian-relationship"
                        v-model="registration.guardian.relationshipToStudent"
                        label="Relationship to student"
                    />
                </div>

                <label class="flex cursor-pointer items-start gap-3">
                    <input
                        v-model="registration.guardian.consent"
                        type="checkbox"
                        class="accent-accent border-border mt-0.5 h-5 w-5 rounded"
                    />
                    <span class="text-muted">As the learner's guardian I consent to their use of this platform</span>
                </label>
            </template>

            <template v-else>
                <FloatingLabelInput id="first-name" v-model="registration.learner.firstName" label="Full name(s)" />
                <FloatingLabelInput id="surname" v-model="registration.learner.surname" label="Surname" />
                <FloatingLabelInput id="email" v-model="registration.learner.email" label="Email address" type="email" />
                <PhoneInput
                    id="cellphone"
                    v-model="registration.learner.cellphone"
                    v-model:country-code="registration.learner.countryCode"
                />
            </template>

            <div class="text-muted flex items-center justify-center gap-2 text-sm">
                <ShieldCheckIcon class="h-5 w-5" />
                Your information is confidential and secure
            </div>

            <button
                type="submit"
                :disabled="!canContinue"
                class="bg-amber w-full rounded-full py-3.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
            >
                Continue
            </button>
        </form>

        <p class="mt-6 text-center">
            <a href="#" class="text-accent text-sm font-semibold underline">Need help?</a>
        </p>
    </div>
</template>
