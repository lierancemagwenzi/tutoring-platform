import { createRouter, createWebHistory } from 'vue-router'
import AuthLayout from '../layouts/AuthLayout.vue'
import WizardLayout from '../layouts/WizardLayout.vue'
import StudentLayout from '../layouts/StudentLayout.vue'
import TutorLayout from '../layouts/TutorLayout.vue'
import CoursePlayerLayout from '../layouts/CoursePlayerLayout.vue'
import AdminLayout from '../layouts/AdminLayout.vue'
import Login from '../pages/auth/Login.vue'
import ForgotPassword from '../pages/auth/ForgotPassword.vue'
import ResetPassword from '../pages/auth/ResetPassword.vue'
import AcceptAdminInvite from '../pages/auth/AcceptAdminInvite.vue'
import RoleSelect from '../pages/register/RoleSelect.vue'
import AgeGate from '../pages/register/AgeGate.vue'
import PersonalDetails from '../pages/register/PersonalDetails.vue'
import Otp from '../pages/register/Otp.vue'
import CreatePassword from '../pages/register/CreatePassword.vue'
import Success from '../pages/register/Success.vue'
import StudentDashboard from '../pages/student/Dashboard.vue'
import Marketplace from '../pages/student/Marketplace.vue'
import TutorProfile from '../pages/student/TutorProfile.vue'
import SelfPacedCourseDetails from '../pages/student/SelfPacedCourseDetails.vue'
import BookService from '../pages/student/BookService.vue'
import MyBookings from '../pages/student/MyBookings.vue'
import BookingDetail from '../pages/student/BookingDetail.vue'
import MyCourses from '../pages/student/MyCourses.vue'
import CoursePlayerEntry from '../pages/student/self-paced/CoursePlayerEntry.vue'
import LessonActivity from '../pages/student/self-paced/LessonActivity.vue'
import LessonAssessment from '../pages/student/self-paced/LessonAssessment.vue'
import CourseCertificateView from '../pages/student/self-paced/CourseCertificateView.vue'
import PurchaseHistory from '../pages/student/PurchaseHistory.vue'
import StudentFinancialTransactions from '../pages/student/FinancialTransactions.vue'
import OrderDetail from '../pages/student/OrderDetail.vue'
import OrderPayment from '../pages/student/OrderPayment.vue'
import OrderComplete from '../pages/student/OrderComplete.vue'
import TutorDashboard from '../pages/tutor/Dashboard.vue'
import TutorSubjects from '../pages/tutor/Subjects.vue'
import TutorCalendar from '../pages/tutor/Calendar.vue'
import TutorServices from '../pages/tutor/Services.vue'
import TutorServiceForm from '../pages/tutor/ServiceForm.vue'
import TutorServiceDetail from '../pages/tutor/ServiceDetail.vue'
import BookingRequests from '../pages/tutor/BookingRequests.vue'
import TutorBookingDetail from '../pages/tutor/BookingDetail.vue'
import TutorSessions from '../pages/tutor/Sessions.vue'
import TutorSessionDetail from '../pages/tutor/SessionDetail.vue'
import TutorSessionContent from '../pages/tutor/SessionContent.vue'
import TutorSessionLessonBlocks from '../pages/tutor/SessionLessonBlocks.vue'
import TutorSessionLessonBlockSubmissions from '../pages/tutor/SessionLessonBlockSubmissions.vue'
import TutorSubmissionDetail from '../pages/tutor/SubmissionDetail.vue'
import TutorSessionLessonBlockAttempts from '../pages/tutor/SessionLessonBlockAttempts.vue'
import TutorAttemptDetail from '../pages/tutor/AttemptDetail.vue'
import TutorSettingsGeneral from '../pages/tutor/settings/General.vue'
import TutorSettingsConnectedAccounts from '../pages/tutor/settings/ConnectedAccounts.vue'
import TutorSettingsMeetingProviders from '../pages/tutor/settings/MeetingProviders.vue'
import TutorSettingsBankingDetails from '../pages/tutor/settings/BankingDetails.vue'
import TutorCourses from '../pages/tutor/lms/Courses.vue'
import TutorCourseForm from '../pages/tutor/lms/CourseForm.vue'
import TutorChapterManager from '../pages/tutor/lms/ChapterManager.vue'
import TutorLessonManager from '../pages/tutor/lms/LessonManager.vue'
import TutorLessonBuilder from '../pages/tutor/lms/LessonBuilder.vue'
import TutorMediaManager from '../pages/tutor/lms/MediaManager.vue'
import TutorQuizBuilder from '../pages/tutor/lms/QuizBuilder.vue'
import TutorH5pManager from '../pages/tutor/lms/H5pManager.vue'
import TutorH5pEditor from '../pages/tutor/lms/H5pEditor.vue'
import TutorH5pLibrary from '../pages/tutor/lms/H5pLibrary.vue'
import TutorActivityBuilder from '../pages/tutor/lms/ActivityBuilder.vue'
import SelfPacedCourseList from '../pages/tutor/self-paced/CourseList.vue'
import SelfPacedCourseEditor from '../pages/tutor/self-paced/CourseEditor.vue'
import SelfPacedSurveyContentList from '../pages/tutor/self-paced/SurveyContentList.vue'
import SelfPacedSurveyContentBuilder from '../pages/tutor/self-paced/SurveyContentBuilder.vue'
import StudentQuizAttempt from '../pages/student/lms/QuizAttempt.vue'
import StudentBlockDetail from '../pages/student/lms/BlockDetail.vue'
import BasicInformation from '../pages/tutor/application/BasicInformation.vue'
import ProfessionalProfile from '../pages/tutor/application/ProfessionalProfile.vue'
import Qualifications from '../pages/tutor/application/Qualifications.vue'
import IdentityDocument from '../pages/tutor/application/IdentityDocument.vue'
import SupportingDocuments from '../pages/tutor/application/SupportingDocuments.vue'
import Review from '../pages/tutor/application/Review.vue'
import ApplicationSuccess from '../pages/tutor/application/Success.vue'
import AdminDashboard from '../pages/admin/Dashboard.vue'
import AdminQuickSetup from '../pages/admin/QuickSetup.vue'
import AdminSubjects from '../pages/admin/Subjects.vue'
import AdminFaqs from '../pages/admin/Faqs.vue'
import AdminSubjectDetail from '../pages/admin/SubjectDetail.vue'
import AdminTutorApprovals from '../pages/admin/TutorApprovals.vue'
import AdminTutorSubjectRequests from '../pages/admin/TutorSubjectRequests.vue'
import AdminSettings from '../pages/admin/Settings.vue'
import AdminIntegrations from '../pages/admin/Integrations.vue'
import AdminSystemHealth from '../pages/admin/SystemHealth.vue'
import AdminActivityLog from '../pages/admin/ActivityLog.vue'
import AdminAccounts from '../pages/admin/AdminAccounts.vue'
import AdminFinancialRules from '../pages/admin/FinancialRules.vue'
import AdminFinancialTransactions from '../pages/admin/FinancialTransactions.vue'
import AdminBookingManagement from '../pages/admin/BookingManagement.vue'
import AdminPaymentTickets from '../pages/admin/PaymentTickets.vue'
import AdminPaymentTicketDetail from '../pages/admin/PaymentTicketDetail.vue'
import AdminSupportTickets from '../pages/admin/SupportTickets.vue'
import AdminSupportTicketDetail from '../pages/admin/SupportTicketDetail.vue'
import TutorEarnings from '../pages/tutor/Earnings.vue'
import TutorPaymentTickets from '../pages/tutor/PaymentTickets.vue'
import FaqAccordion from '../components/faq/FaqAccordion.vue'
import HelpTickets from '../components/support/HelpTickets.vue'
import HelpTicketDetail from '../components/support/HelpTicketDetail.vue'
import TutorPaymentTicketDetail from '../pages/tutor/PaymentTicketDetail.vue'
import { TUTOR_APPLICATION_STEP_ROUTES } from './tutorApplicationSteps'
import { DASHBOARD_BY_ROLE } from './dashboardByRole'

const routes = [
    {
        path: '/',
        component: AuthLayout,
        children: [
            { path: '', redirect: { name: 'login' } },
            { path: 'login', name: 'login', component: Login },
            { path: 'forgot-password', name: 'forgot-password', component: ForgotPassword },
            { path: 'reset-password', name: 'reset-password', component: ResetPassword },
            { path: 'accept-admin-invite', name: 'accept-admin-invite', component: AcceptAdminInvite },
            { path: 'register', name: 'register.role', component: RoleSelect },
            { path: 'register/age', name: 'register.age', component: AgeGate },
            {
                path: 'verify-account',
                name: 'verify-account',
                component: Otp,
                meta: { requiresAuth: true, afterVerify: 'dashboard' },
            },
        ],
    },
    {
        path: '/register',
        component: WizardLayout,
        children: [
            { path: 'details', name: 'register.details', component: PersonalDetails, meta: { step: 1 } },
            { path: 'password', name: 'register.password', component: CreatePassword, meta: { step: 2 } },
            { path: 'otp', name: 'register.otp', component: Otp, meta: { step: 3, afterVerify: 'success' } },
        ],
    },
    {
        path: '/register/success',
        name: 'register.success',
        component: Success,
    },
    {
        path: '/student',
        component: StudentLayout,
        meta: { requiresAuth: true },
        children: [
            { path: '', name: 'student.dashboard', component: StudentDashboard },
            { path: 'marketplace', name: 'student.marketplace', component: Marketplace },
            { path: 'marketplace/tutors/:id', name: 'student.marketplace.tutor', component: TutorProfile },
            { path: 'marketplace/courses/:id', name: 'student.marketplace.course', component: SelfPacedCourseDetails },
            {
                path: 'marketplace/tutors/:tutorId/services/:serviceId/book',
                name: 'student.booking.create',
                component: BookService,
            },
            { path: 'bookings', name: 'student.bookings', component: MyBookings },
            { path: 'bookings/:id', name: 'student.bookings.show', component: BookingDetail },
            { path: 'my-courses', name: 'student.my-courses', component: MyCourses },
            { path: 'orders', name: 'student.orders', component: PurchaseHistory },
            { path: 'financial-transactions', name: 'student.financial-transactions', component: StudentFinancialTransactions },
            { path: 'orders/:id', name: 'student.orders.show', component: OrderDetail },
            { path: 'orders/:id/pay', name: 'student.orders.pay', component: OrderPayment },
            { path: 'orders/:id/complete', name: 'student.orders.complete', component: OrderComplete },
            {
                path: 'bookings/:bookingId/lesson-blocks/:blockId',
                name: 'student.bookings.lesson-blocks.show',
                component: StudentBlockDetail,
            },
            { path: 'quizzes/:id/attempt', name: 'student.quizzes.attempt', component: StudentQuizAttempt },
            { path: 'faqs', name: 'student.faqs', component: FaqAccordion },
            { path: 'help-tickets', name: 'student.help-tickets', component: HelpTickets },
            { path: 'help-tickets/:id', name: 'student.help-tickets.show', component: HelpTicketDetail },
        ],
    },
    {
        // Its own minimal-chrome layout (not StudentLayout/SidebarShell) —
        // a distraction-free course player doesn't want the dashboard sidebar.
        path: '/student/self-paced-courses/:courseId/learn',
        component: CoursePlayerLayout,
        meta: { requiresAuth: true },
        children: [
            { path: '', name: 'student.self-paced.entry', component: CoursePlayerEntry },
            { path: 'activities/:activityId', name: 'student.self-paced.activity', component: LessonActivity },
            { path: 'assessments/:assessmentId', name: 'student.self-paced.assessment', component: LessonAssessment },
            { path: 'certificate', name: 'student.self-paced.certificate', component: CourseCertificateView },
        ],
    },
    {
        path: '/tutor',
        component: TutorLayout,
        meta: { requiresAuth: true },
        children: [
            { path: '', name: 'tutor.dashboard', component: TutorDashboard },
            { path: 'subjects', name: 'tutor.subjects', component: TutorSubjects, meta: { requiresTutor: true } },
            { path: 'calendar', name: 'tutor.calendar', component: TutorCalendar, meta: { requiresTutor: true } },
            { path: 'services', name: 'tutor.services', component: TutorServices, meta: { requiresTutor: true } },
            { path: 'services/create', name: 'tutor.services.create', component: TutorServiceForm, meta: { requiresTutor: true } },
            { path: 'services/:id', name: 'tutor.services.show', component: TutorServiceDetail, meta: { requiresTutor: true } },
            { path: 'services/:id/edit', name: 'tutor.services.edit', component: TutorServiceForm, meta: { requiresTutor: true } },
            { path: 'booking-requests', name: 'tutor.booking-requests', component: BookingRequests, meta: { requiresTutor: true } },
            { path: 'bookings/:id', name: 'tutor.bookings.show', component: TutorBookingDetail, meta: { requiresTutor: true } },
            { path: 'sessions', name: 'tutor.sessions', component: TutorSessions, meta: { requiresTutor: true } },
            { path: 'sessions/:id', name: 'tutor.sessions.show', component: TutorSessionDetail, meta: { requiresTutor: true } },
            {
                path: 'sessions/:id/content',
                name: 'tutor.sessions.content',
                component: TutorSessionContent,
                meta: { requiresTutor: true },
            },
            {
                path: 'session-lessons/:id/blocks',
                name: 'tutor.session-lessons.blocks',
                component: TutorSessionLessonBlocks,
                meta: { requiresTutor: true },
            },
            {
                path: 'session-lesson-blocks/:id/submissions',
                name: 'tutor.session-lesson-blocks.submissions',
                component: TutorSessionLessonBlockSubmissions,
                meta: { requiresTutor: true },
            },
            {
                path: 'submissions/:id',
                name: 'tutor.submissions.show',
                component: TutorSubmissionDetail,
                meta: { requiresTutor: true },
            },
            {
                path: 'session-lesson-blocks/:id/attempts',
                name: 'tutor.session-lesson-blocks.attempts',
                component: TutorSessionLessonBlockAttempts,
                meta: { requiresTutor: true },
            },
            {
                path: 'attempts/:id',
                name: 'tutor.attempts.show',
                component: TutorAttemptDetail,
                meta: { requiresTutor: true },
            },
            { path: 'courses', name: 'tutor.courses', component: TutorCourses, meta: { requiresTutor: true } },
            { path: 'courses/create', name: 'tutor.courses.create', component: TutorCourseForm, meta: { requiresTutor: true } },
            { path: 'courses/:id/edit', name: 'tutor.courses.edit', component: TutorCourseForm, meta: { requiresTutor: true } },
            {
                path: 'courses/:id/chapters',
                name: 'tutor.courses.chapters',
                component: TutorChapterManager,
                meta: { requiresTutor: true },
            },
            {
                path: 'chapters/:id/lessons',
                name: 'tutor.chapters.lessons',
                component: TutorLessonManager,
                meta: { requiresTutor: true },
            },
            {
                path: 'lessons/:id/builder',
                name: 'tutor.lessons.builder',
                component: TutorLessonBuilder,
                meta: { requiresTutor: true },
            },
            {
                path: 'lesson-blocks/:id/media',
                name: 'tutor.lesson-blocks.media',
                component: TutorMediaManager,
                meta: { requiresTutor: true },
            },
            {
                path: 'quizzes/:id/builder',
                name: 'tutor.quizzes.builder',
                component: TutorQuizBuilder,
                meta: { requiresTutor: true },
            },
            {
                path: 'lesson-blocks/:id/h5p',
                name: 'tutor.lesson-blocks.h5p',
                component: TutorH5pManager,
                meta: { requiresTutor: true },
            },
            {
                path: 'h5p-content',
                name: 'tutor.h5p-content',
                component: TutorH5pLibrary,
                meta: { requiresTutor: true },
            },
            {
                path: 'h5p-content/new',
                name: 'tutor.h5p-content.new',
                component: TutorH5pEditor,
                meta: { requiresTutor: true },
            },
            {
                path: 'h5p-content/:id/edit',
                name: 'tutor.h5p-content.edit',
                component: TutorH5pEditor,
                meta: { requiresTutor: true },
            },
            {
                path: 'learning-activities/:id/builder',
                name: 'tutor.learning-activities.builder',
                component: TutorActivityBuilder,
                meta: { requiresTutor: true },
            },
            {
                path: 'self-paced-courses',
                name: 'tutor.self-paced-courses',
                component: SelfPacedCourseList,
                meta: { requiresTutor: true },
            },
            {
                path: 'self-paced-courses/:id',
                name: 'tutor.self-paced-courses.edit',
                component: SelfPacedCourseEditor,
                meta: { requiresTutor: true },
            },
            {
                path: 'self-paced-survey-contents',
                name: 'tutor.self-paced-survey-contents',
                component: SelfPacedSurveyContentList,
                meta: { requiresTutor: true },
            },
            {
                path: 'self-paced-survey-contents/:id',
                name: 'tutor.self-paced-survey-contents.builder',
                component: SelfPacedSurveyContentBuilder,
                meta: { requiresTutor: true },
            },
            { path: 'settings', name: 'tutor.settings', component: TutorSettingsGeneral, meta: { requiresTutor: true } },
            {
                path: 'settings/connected-accounts',
                name: 'tutor.settings.connected-accounts',
                component: TutorSettingsConnectedAccounts,
                meta: { requiresTutor: true },
            },
            {
                path: 'settings/meeting-providers',
                name: 'tutor.settings.meeting-providers',
                component: TutorSettingsMeetingProviders,
                meta: { requiresTutor: true },
            },
            {
                path: 'settings/banking-details',
                name: 'tutor.settings.banking-details',
                component: TutorSettingsBankingDetails,
                meta: { requiresTutor: true },
            },
            { path: 'earnings', name: 'tutor.earnings', component: TutorEarnings, meta: { requiresTutor: true } },
            { path: 'payment-tickets', name: 'tutor.payment-tickets', component: TutorPaymentTickets, meta: { requiresTutor: true } },
            {
                path: 'payment-tickets/:id',
                name: 'tutor.payment-tickets.show',
                component: TutorPaymentTicketDetail,
                meta: { requiresTutor: true },
            },
            { path: 'faqs', name: 'tutor.faqs', component: FaqAccordion, meta: { requiresTutor: true } },
            { path: 'help-tickets', name: 'tutor.help-tickets', component: HelpTickets, meta: { requiresTutor: true } },
            { path: 'help-tickets/:id', name: 'tutor.help-tickets.show', component: HelpTicketDetail, meta: { requiresTutor: true } },
        ],
    },
    {
        path: '/tutor/application',
        component: WizardLayout,
        meta: { requiresAuth: true, requiresTutor: true },
        children: [
            { path: 'basic-info', name: 'tutor.application.basic-info', component: BasicInformation, meta: { step: 1, totalSteps: 6 } },
            {
                path: 'professional-profile',
                name: 'tutor.application.professional-profile',
                component: ProfessionalProfile,
                meta: { step: 2, totalSteps: 6 },
            },
            { path: 'qualifications', name: 'tutor.application.qualifications', component: Qualifications, meta: { step: 3, totalSteps: 6 } },
            {
                path: 'identity-document',
                name: 'tutor.application.identity-document',
                component: IdentityDocument,
                meta: { step: 4, totalSteps: 6 },
            },
            { path: 'documents', name: 'tutor.application.documents', component: SupportingDocuments, meta: { step: 5, totalSteps: 6 } },
            { path: 'review', name: 'tutor.application.review', component: Review, meta: { step: 6, totalSteps: 6 } },
        ],
    },
    {
        path: '/tutor/application/success',
        name: 'tutor.application.success',
        component: ApplicationSuccess,
        meta: { requiresAuth: true, requiresTutor: true },
    },
    {
        path: '/admin',
        component: AdminLayout,
        meta: { requiresAuth: true, requiresAdmin: true },
        children: [
            { path: '', name: 'admin.dashboard', component: AdminDashboard },
            { path: 'quick-setup', name: 'admin.quick-setup', component: AdminQuickSetup },
            { path: 'subjects', name: 'admin.subjects', component: AdminSubjects },
            { path: 'faqs', name: 'admin.faqs', component: AdminFaqs },
            { path: 'subjects/:id', name: 'admin.subjects.show', component: AdminSubjectDetail },
            { path: 'tutor-approvals', name: 'admin.tutor-approvals', component: AdminTutorApprovals },
            { path: 'tutor-subject-requests', name: 'admin.tutor-subject-requests', component: AdminTutorSubjectRequests },
            { path: 'settings', name: 'admin.settings', component: AdminSettings },
            { path: 'financial-rules', name: 'admin.financial-rules', component: AdminFinancialRules },
            { path: 'financial-transactions', name: 'admin.financial-transactions', component: AdminFinancialTransactions },
            { path: 'bookings', name: 'admin.bookings', component: AdminBookingManagement },
            { path: 'payment-tickets', name: 'admin.payment-tickets', component: AdminPaymentTickets },
            { path: 'payment-tickets/:id', name: 'admin.payment-tickets.show', component: AdminPaymentTicketDetail },
            { path: 'support-tickets', name: 'admin.support-tickets', component: AdminSupportTickets },
            { path: 'support-tickets/:id', name: 'admin.support-tickets.show', component: AdminSupportTicketDetail },
            { path: 'integrations', name: 'admin.integrations', component: AdminIntegrations },
            { path: 'system-health', name: 'admin.system-health', component: AdminSystemHealth },
            { path: 'activity-log', name: 'admin.activity-log', component: AdminActivityLog },
            { path: 'admins', name: 'admin.admins', component: AdminAccounts },
        ],
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

function storedUser() {
    return JSON.parse(localStorage.getItem('auth_user') ?? 'null')
}

router.beforeEach((to) => {
    const token = localStorage.getItem('auth_token')

    if (to.matched.some((record) => record.meta.requiresAuth) && !token) {
        return { name: 'login' }
    }

    if (to.matched.some((record) => record.meta.requiresTutor) && storedUser()?.role !== 'tutor') {
        return { name: 'login' }
    }

    if (to.matched.some((record) => record.meta.requiresAdmin) && storedUser()?.role !== 'admin') {
        return { name: 'login' }
    }

    const user = token ? storedUser() : null

    if (
        to.matched.some((record) => record.meta.requiresAuth) &&
        user &&
        !user.email_verified_at &&
        to.name !== 'verify-account'
    ) {
        return { name: 'verify-account' }
    }

    if (to.name === 'verify-account' && user?.email_verified_at) {
        return DASHBOARD_BY_ROLE[user.role] ?? { name: 'login' }
    }

    const onboardingComplete = user?.tutor_profile?.onboarding_complete

    if (user?.role === 'tutor' && onboardingComplete === false && to.path === '/tutor') {
        const step = user.tutor_profile?.onboarding_step ?? 1
        return TUTOR_APPLICATION_STEP_ROUTES[step - 1] ?? TUTOR_APPLICATION_STEP_ROUTES[0]
    }

    if (onboardingComplete === true && to.path.startsWith('/tutor/application') && to.name !== 'tutor.application.success') {
        return '/tutor'
    }
})

export default router
