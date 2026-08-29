<script setup>
import { computed } from 'vue'
import {
    AcademicCapIcon,
    BanknotesIcon,
    BookOpenIcon,
    BriefcaseIcon,
    CalendarIcon,
    ChatBubbleLeftRightIcon,
    ClipboardDocumentListIcon,
    Cog6ToothIcon,
    LifebuoyIcon,
    PhoneIcon,
    QuestionMarkCircleIcon,
    RectangleStackIcon,
    VideoCameraIcon,
} from '@heroicons/vue/24/outline'
import SidebarShell from '../components/navigation/SidebarShell.vue'
import { useAuthStore } from '../stores/auth'

const TUTOR_GROUPS = [
    {
        key: 'teaching',
        label: 'Teaching',
        items: [
            { to: '/tutor/subjects', label: 'Subjects', icon: BookOpenIcon },
            { to: '/tutor/calendar', label: 'Calendar', icon: CalendarIcon },
            { to: '/tutor/services', label: 'Services', icon: BriefcaseIcon },
            { to: '/tutor/courses', label: 'My Courses', icon: AcademicCapIcon },
            { to: '/tutor/self-paced-courses', label: 'Self-Paced', icon: RectangleStackIcon },
        ],
    },
    {
        key: 'bookings',
        label: 'Bookings & Sessions',
        items: [
            { to: '/tutor/booking-requests', label: 'Requests', icon: ClipboardDocumentListIcon },
            { to: '/tutor/sessions', label: 'Sessions', icon: VideoCameraIcon },
        ],
    },
    {
        key: 'business',
        label: 'Business',
        items: [
            { to: '/tutor/earnings', label: 'Earnings', icon: BanknotesIcon },
            { to: '/tutor/payment-tickets', label: 'Tickets', icon: ChatBubbleLeftRightIcon },
        ],
    },
    {
        key: 'support',
        label: 'Support',
        items: [
            { to: '/tutor/faqs', label: 'FAQs', icon: QuestionMarkCircleIcon },
            { to: '/tutor/help-tickets', label: 'Help', icon: LifebuoyIcon },
        ],
    },
]

const TUTOR_TRAILING_LINKS = [
    { to: '/tutor/settings', label: 'Settings', icon: Cog6ToothIcon },
    { href: '#', label: 'Contact us', icon: PhoneIcon },
]

const auth = useAuthStore()

const userName = computed(() => {
    const user = auth.user
    return user ? `${user.first_name} ${user.last_name}` : 'Guide'
})
</script>

<template>
    <SidebarShell :user-name="userName" user-role="Guide" home-route="/tutor" :groups="TUTOR_GROUPS" :trailing-links="TUTOR_TRAILING_LINKS">
        <router-view />
    </SidebarShell>
</template>
