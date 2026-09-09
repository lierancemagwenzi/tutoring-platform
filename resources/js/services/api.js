import axios from 'axios'
import router from '../router'
import { TUTOR_APPLICATION_STEP_ROUTES } from '../router/tutorApplicationSteps'

const api = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
    },
})

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('auth_token')
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }
    return config
})

// A 401 on a request that carried a bearer token means that token is no
// longer valid (expired/revoked) — bounce straight to login rather than
// leaving the app stuck showing stale data or erroring silently. A 401
// with no Authorization header (e.g. a failed login attempt itself) isn't
// a session expiring, so it's left for the calling component to handle.
api.interceptors.response.use(
    (response) => response,
    (error) => {
        const hadToken = Boolean(error.config?.headers?.Authorization)

        if (error.response?.status === 401 && hadToken) {
            localStorage.removeItem('auth_token')
            localStorage.removeItem('auth_user')

            if (router.currentRoute.value.name !== 'login') {
                router.push({ name: 'login' })
            }
        }

        // A tutor whose application isn't complete yet (see
        // EnsureTutorOnboardingComplete) gets this on every other endpoint.
        // The router guard already redirects `/tutor` itself, but a direct
        // deep link elsewhere would otherwise leave the page's own fetch
        // rejected and its loading state stuck forever — bounce to the
        // wizard step they left off at instead.
        if (error.response?.data?.code === 'TUTOR_ONBOARDING_INCOMPLETE') {
            const user = JSON.parse(localStorage.getItem('auth_user') ?? 'null')
            const step = user?.tutor_profile?.onboarding_step ?? 1
            const target = TUTOR_APPLICATION_STEP_ROUTES[step - 1] ?? TUTOR_APPLICATION_STEP_ROUTES[0]

            if (router.currentRoute.value.path !== target) {
                router.push(target)
            }
        }

        return Promise.reject(error)
    },
)

export default api
