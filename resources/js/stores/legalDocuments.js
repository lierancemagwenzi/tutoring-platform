import { defineStore } from 'pinia'
import api from '../services/api'

// Public, unauthenticated content — read by the registration wizard (and
// anyone else linking to these) before an account exists.
export const useLegalDocumentsStore = defineStore('legalDocuments', {
    state: () => ({
        termsAndConditions: '',
        privacyPolicy: '',
        loaded: false,
    }),

    actions: {
        async fetchDocuments() {
            if (this.loaded) {
                return
            }

            const { data } = await api.get('/legal-documents')
            this.termsAndConditions = data.terms_and_conditions
            this.privacyPolicy = data.privacy_policy
            this.loaded = true
        },
    },
})
