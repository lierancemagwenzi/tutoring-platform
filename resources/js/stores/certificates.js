import { defineStore } from 'pinia'
import api from '../services/api'

// Certificate downloads require the Authorization header, so a plain
// `<a href>` won't work (the request would go out unauthenticated) — every
// consumer must fetch the PDF as a blob through this store instead.
export const useCertificatesStore = defineStore('certificates', {
    actions: {
        async fetchCertificates() {
            const { data } = await api.get('/student/certificates')
            return data.certificates
        },

        async fetchCertificateBlob(certificateId) {
            const response = await api.get(`/student/certificates/${certificateId}/download`, {
                responseType: 'blob',
            })
            return new Blob([response.data], { type: 'application/pdf' })
        },

        async downloadCertificate(certificateId, filename) {
            const blob = await this.fetchCertificateBlob(certificateId)
            const url = URL.createObjectURL(blob)

            const link = document.createElement('a')
            link.href = url
            link.download = filename || 'certificate.pdf'
            document.body.appendChild(link)
            link.click()
            link.remove()

            URL.revokeObjectURL(url)
        },
    },
})
