import './bootstrap';
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { useThemeStore } from './stores/theme'
import { DASHBOARD_BY_ROLE } from './router/dashboardByRole'

const app = createApp(App)
app.use(createPinia()).use(router)

useThemeStore().init()

// The auth token lives in localStorage, which every tab shares. If another
// tab logs in as a different account (or logs out), this tab's in-memory
// state still reflects the old user while its requests now carry the new
// token — e.g. a Guide's sidebar calling tutor-only endpoints as a Member.
// Reload onto the new account's dashboard (or login) so the two stay in sync.
window.addEventListener('storage', (event) => {
    if (event.key !== 'auth_token' && event.key !== null) {
        return
    }

    const user = JSON.parse(localStorage.getItem('auth_user') ?? 'null')
    const hasToken = Boolean(localStorage.getItem('auth_token'))

    window.location.assign(hasToken && user ? (DASHBOARD_BY_ROLE[user.role] ?? '/login') : '/login')
})

app.mount('#app')
