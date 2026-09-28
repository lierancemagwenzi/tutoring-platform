<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useAuthStore } from '../../stores/auth'

// Shared by every role — mounted at /student/profile, /tutor/profile and
// /admin/profile inside that role's layout.
const auth = useAuthStore()

const isGuide = computed(() => auth.user?.role === 'tutor')
const hasGuideProfile = computed(() => isGuide.value && Boolean(auth.user?.tutor_profile))

const inputClass = 'focus:border-accent mt-1.5 w-full rounded-xl border border-border bg-card px-3.5 py-2.5 text-sm text-body outline-none'
const labelClass = 'block text-sm font-semibold text-body'
const buttonClass =
    'bg-amber mt-6 rounded-full px-6 py-2.5 text-sm font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40'

const profile = reactive({ profile_photo: null })
const photoPreview = ref(null)

function fill(user) {
    Object.assign(profile, {
        first_name: user?.first_name ?? '',
        last_name: user?.last_name ?? '',
        phone: user?.phone ?? '',
        date_of_birth: user?.date_of_birth ?? '',
        display_name: user?.tutor_profile?.display_name ?? '',
        bio: user?.tutor_profile?.bio ?? '',
    })
    photoPreview.value = user?.tutor_profile?.profile_photo_url ?? null
}

fill(auth.user)

// The cached user in localStorage may predate fields this page needs.
onMounted(async () => {
    try {
        fill(await auth.fetchUser())
    } catch {
        // Keep the cached details; saving still works.
    }
})
const photoInput = ref(null)
const savingProfile = ref(false)
const profileErrors = ref({})
const profileMessage = ref('')

const password = reactive({ current_password: '', password: '', password_confirmation: '' })
const savingPassword = ref(false)
const passwordErrors = ref({})
const passwordMessage = ref('')

function onPhotoSelected(event) {
    const file = event.target.files?.[0]
    if (!file) return
    profile.profile_photo = file
    photoPreview.value = URL.createObjectURL(file)
}

function errorsFrom(error) {
    return error.response?.data?.errors ?? { general: [error.response?.data?.message ?? 'Something went wrong. Please try again.'] }
}

async function saveProfile() {
    savingProfile.value = true
    profileErrors.value = {}
    profileMessage.value = ''

    const payload = {
        first_name: profile.first_name,
        last_name: profile.last_name,
        phone: profile.phone,
        date_of_birth: profile.date_of_birth || null,
    }
    if (hasGuideProfile.value) {
        Object.assign(payload, { display_name: profile.display_name, bio: profile.bio, profile_photo: profile.profile_photo })
    }

    try {
        const user = await auth.updateProfile(payload)
        profile.profile_photo = null
        if (photoInput.value) photoInput.value.value = ''
        photoPreview.value = user.tutor_profile?.profile_photo_url ?? photoPreview.value
        profileMessage.value = 'Your profile has been updated.'
    } catch (error) {
        profileErrors.value = errorsFrom(error)
    } finally {
        savingProfile.value = false
    }
}

async function savePassword() {
    savingPassword.value = true
    passwordErrors.value = {}
    passwordMessage.value = ''

    try {
        const data = await auth.changePassword({ ...password })
        Object.assign(password, { current_password: '', password: '', password_confirmation: '' })
        passwordMessage.value = `${data.message} Any other devices you were signed in on have been signed out.`
    } catch (error) {
        passwordErrors.value = errorsFrom(error)
    } finally {
        savingPassword.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-body text-2xl font-bold">My Profile</h1>
        <p class="text-muted mt-1 text-sm">Manage your personal details and password.</p>

        <div class="mt-6 grid max-w-5xl grid-cols-1 gap-6 lg:grid-cols-2">
            <form class="bg-card shadow-elevated rounded-2xl p-6" novalidate @submit.prevent="saveProfile">
                <h2 class="text-body text-lg font-bold">Profile details</h2>

                <p v-if="profileMessage" class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ profileMessage }}</p>
                <p v-if="profileErrors.general" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ profileErrors.general[0] }}</p>

                <div class="mt-4 space-y-4">
                    <div v-if="hasGuideProfile" class="flex items-center gap-4">
                        <img v-if="photoPreview" :src="photoPreview" alt="Profile photo" class="h-16 w-16 rounded-full object-cover" />
                        <span v-else class="bg-amber flex h-16 w-16 items-center justify-center rounded-full text-xl font-semibold text-white">
                            {{ profile.first_name.charAt(0) }}
                        </span>
                        <div>
                            <input ref="photoInput" type="file" accept=".jpg,.jpeg,.png" class="hidden" @change="onPhotoSelected" />
                            <button type="button" class="text-accent text-sm font-semibold" @click="photoInput.click()">Change photo</button>
                            <p class="text-muted text-xs">JPG or PNG, up to 4 MB</p>
                            <p v-if="profileErrors.profile_photo" class="mt-1 text-xs text-red-600">{{ profileErrors.profile_photo[0] }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label :class="labelClass" for="first-name">First name</label>
                            <input id="first-name" v-model="profile.first_name" type="text" :class="inputClass" autocomplete="given-name" />
                            <p v-if="profileErrors.first_name" class="mt-1 text-xs text-red-600">{{ profileErrors.first_name[0] }}</p>
                        </div>
                        <div>
                            <label :class="labelClass" for="last-name">Last name</label>
                            <input id="last-name" v-model="profile.last_name" type="text" :class="inputClass" autocomplete="family-name" />
                            <p v-if="profileErrors.last_name" class="mt-1 text-xs text-red-600">{{ profileErrors.last_name[0] }}</p>
                        </div>
                    </div>

                    <div>
                        <label :class="labelClass" for="email">Email</label>
                        <input id="email" :value="auth.user?.email" type="email" disabled :class="[inputClass, 'text-muted cursor-not-allowed opacity-70']" />
                    </div>

                    <div>
                        <label :class="labelClass" for="phone">Phone</label>
                        <input id="phone" v-model="profile.phone" type="tel" :class="inputClass" autocomplete="tel" />
                        <p v-if="profileErrors.phone" class="mt-1 text-xs text-red-600">{{ profileErrors.phone[0] }}</p>
                    </div>

                    <div>
                        <label :class="labelClass" for="date-of-birth">Date of birth</label>
                        <input id="date-of-birth" v-model="profile.date_of_birth" type="date" :class="inputClass" autocomplete="bday" />
                        <p v-if="profileErrors.date_of_birth" class="mt-1 text-xs text-red-600">{{ profileErrors.date_of_birth[0] }}</p>
                    </div>

                    <template v-if="hasGuideProfile">
                        <div>
                            <label :class="labelClass" for="display-name">Display name</label>
                            <input id="display-name" v-model="profile.display_name" type="text" :class="inputClass" />
                            <p class="text-muted mt-1 text-xs">Shown to learners on your public profile and courses.</p>
                            <p v-if="profileErrors.display_name" class="mt-1 text-xs text-red-600">{{ profileErrors.display_name[0] }}</p>
                        </div>
                        <div>
                            <label :class="labelClass" for="bio">Bio</label>
                            <textarea id="bio" v-model="profile.bio" rows="4" :class="inputClass" />
                            <p v-if="profileErrors.bio" class="mt-1 text-xs text-red-600">{{ profileErrors.bio[0] }}</p>
                        </div>
                    </template>
                </div>

                <button type="submit" :disabled="savingProfile" :class="buttonClass">
                    {{ savingProfile ? 'Saving…' : 'Save changes' }}
                </button>
            </form>

            <form class="bg-card shadow-elevated self-start rounded-2xl p-6" novalidate @submit.prevent="savePassword">
                <h2 class="text-body text-lg font-bold">Change password</h2>

                <p v-if="passwordMessage" class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ passwordMessage }}</p>
                <p v-if="passwordErrors.general" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ passwordErrors.general[0] }}</p>

                <div class="mt-4 space-y-4">
                    <div>
                        <label :class="labelClass" for="current-password">Current password</label>
                        <input id="current-password" v-model="password.current_password" type="password" :class="inputClass" autocomplete="current-password" />
                        <p v-if="passwordErrors.current_password" class="mt-1 text-xs text-red-600">{{ passwordErrors.current_password[0] }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="new-password">New password</label>
                        <input id="new-password" v-model="password.password" type="password" :class="inputClass" autocomplete="new-password" />
                        <p class="text-muted mt-1 text-xs">At least 8 characters, with upper and lower case letters, a number and a symbol.</p>
                        <p v-for="message in passwordErrors.password ?? []" :key="message" class="mt-1 text-xs text-red-600">{{ message }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="confirm-password">Confirm new password</label>
                        <input id="confirm-password" v-model="password.password_confirmation" type="password" :class="inputClass" autocomplete="new-password" />
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="savingPassword || !password.current_password || !password.password || !password.password_confirmation"
                    :class="buttonClass"
                >
                    {{ savingPassword ? 'Updating…' : 'Update password' }}
                </button>
            </form>
        </div>
    </div>
</template>
