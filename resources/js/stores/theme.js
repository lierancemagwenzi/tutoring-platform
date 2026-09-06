import { defineStore } from 'pinia'

const STORAGE_KEY = 'theme'
const THEMES = ['default', 'dark']
const ENV_DEFAULT = THEMES.includes(import.meta.env.VITE_THEME) ? import.meta.env.VITE_THEME : 'dark'

function applyTheme(theme) {
    document.documentElement.dataset.theme = theme
}

export const useThemeStore = defineStore('theme', {
    state: () => ({
        theme: localStorage.getItem(STORAGE_KEY) ?? ENV_DEFAULT,
    }),

    actions: {
        // Applies the current theme to <html> — call once at app startup.
        init() {
            applyTheme(this.theme)
        },

        setTheme(theme) {
            if (!THEMES.includes(theme)) {
                return
            }
            this.theme = theme
            localStorage.setItem(STORAGE_KEY, theme)
            applyTheme(theme)
        },

        toggle() {
            this.setTheme(this.theme === 'dark' ? 'default' : 'dark')
        },
    },
})
