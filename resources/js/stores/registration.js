import { defineStore } from 'pinia'

export const useRegistrationStore = defineStore('registration', {
    state: () => ({
        role: null, // 'guide' | 'learner'
        ageBracket: null, // 'over18' | 'under18'
        learner: {
            firstName: '',
            surname: '',
            email: '',
            countryCode: '+27',
            cellphone: '',
        },
        guardian: {
            firstName: '',
            surname: '',
            email: '',
            countryCode: '+27',
            cellphone: '',
            relationshipToStudent: '',
            consent: false,
        },
        password: '',
        confirmPassword: '',
    }),
    getters: {
        isUnderage: (state) => state.role === 'learner' && state.ageBracket === 'under18',
    },
    actions: {
        reset() {
            this.$reset()
        },
    },
})
