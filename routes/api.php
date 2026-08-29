<?php

use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\AdminAccountController;
use App\Http\Controllers\Api\Admin\BookingManagementController;
use App\Http\Controllers\Api\Admin\CertificateManagementController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\FaqController as AdminFaqController;
use App\Http\Controllers\Api\Admin\FinancialRuleController;
use App\Http\Controllers\Api\Admin\FinancialTransactionController;
use App\Http\Controllers\Api\Admin\IntegrationStatusController;
use App\Http\Controllers\Api\Admin\PaymentManagementController;
use App\Http\Controllers\Api\Admin\PaymentTicketController as AdminPaymentTicketController;
use App\Http\Controllers\Api\Admin\QuickSetupController;
use App\Http\Controllers\Api\Admin\SelfPacedCourseManagementController;
use App\Http\Controllers\Api\Admin\SessionManagementController;
use App\Http\Controllers\Api\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\Admin\StudentManagementController;
use App\Http\Controllers\Api\Admin\SubjectController as AdminSubjectController;
use App\Http\Controllers\Api\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\Api\Admin\SystemHealthController;
use App\Http\Controllers\Api\Admin\TutorApprovalController;
use App\Http\Controllers\Api\Admin\TutoringServiceManagementController;
use App\Http\Controllers\Api\Admin\TutorManagementController;
use App\Http\Controllers\Api\Admin\TutorSubjectApprovalController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AssessmentTypeController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\Auth\AcceptAdminInviteController;
use App\Http\Controllers\Api\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\ResendEmailVerificationController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\StudentRegistrationController;
use App\Http\Controllers\Api\Auth\TutorRegistrationController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Api\CurriculumController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\LearningResourceController;
use App\Http\Controllers\Api\Marketplace\SelfPacedCourseController as MarketplaceSelfPacedCourseController;
use App\Http\Controllers\Api\Marketplace\TutorController as MarketplaceTutorController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PayFast\PayFastItnController;
use App\Http\Controllers\Api\ServiceCategoryController;
use App\Http\Controllers\Api\SessionFormatController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\Student\AttemptController as StudentAttemptController;
use App\Http\Controllers\Api\Student\BookingAvailabilityController;
use App\Http\Controllers\Api\Student\BookingChatController;
use App\Http\Controllers\Api\Student\BookingController;
use App\Http\Controllers\Api\Student\EnrollmentController;
use App\Http\Controllers\Api\Student\FinancialTransactionController as StudentFinancialTransactionController;
use App\Http\Controllers\Api\Student\LearningHubController;
use App\Http\Controllers\Api\Student\OrderController;
use App\Http\Controllers\Api\Student\QuizAttemptController as StudentQuizAttemptController;
use App\Http\Controllers\Api\Student\QuizController as StudentQuizController;
use App\Http\Controllers\Api\Student\SelfPaced\CourseCertificateController;
use App\Http\Controllers\Api\Student\SelfPaced\SelfPacedActivityController as StudentSelfPacedActivityController;
use App\Http\Controllers\Api\Student\SelfPaced\SelfPacedAssessmentAttemptController;
use App\Http\Controllers\Api\Student\SelfPaced\SelfPacedAssessmentController as StudentSelfPacedAssessmentController;
use App\Http\Controllers\Api\Student\SelfPaced\StudentSelfPacedCourseController;
use App\Http\Controllers\Api\Student\SessionContentController;
use App\Http\Controllers\Api\Student\SubmissionAttachmentController as StudentSubmissionAttachmentController;
use App\Http\Controllers\Api\Student\SubmissionController as StudentSubmissionController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\Tutor\ActivityAttachmentController;
use App\Http\Controllers\Api\Tutor\AttemptController as TutorAttemptController;
use App\Http\Controllers\Api\Tutor\AvailabilityController;
use App\Http\Controllers\Api\Tutor\BankingDetailsController;
use App\Http\Controllers\Api\Tutor\BasicInformationController;
use App\Http\Controllers\Api\Tutor\BookingChatController as TutorBookingChatController;
use App\Http\Controllers\Api\Tutor\BookingRequestController;
use App\Http\Controllers\Api\Tutor\BookingSessionController;
use App\Http\Controllers\Api\Tutor\ChapterController;
use App\Http\Controllers\Api\Tutor\ConnectedAccountController;
use App\Http\Controllers\Api\Tutor\CourseController;
use App\Http\Controllers\Api\Tutor\EarningsController;
use App\Http\Controllers\Api\Tutor\H5pContentController;
use App\Http\Controllers\Api\Tutor\IdentityDocumentController;
use App\Http\Controllers\Api\Tutor\LearningActivityController;
use App\Http\Controllers\Api\Tutor\LessonBlockController;
use App\Http\Controllers\Api\Tutor\LessonController;
use App\Http\Controllers\Api\Tutor\MediaItemController;
use App\Http\Controllers\Api\Tutor\MeetingProviderSettingController;
use App\Http\Controllers\Api\Tutor\PaymentTicketController;
use App\Http\Controllers\Api\Tutor\ProfessionalProfileController;
use App\Http\Controllers\Api\Tutor\QuizController;
use App\Http\Controllers\Api\Tutor\QuizQuestionController;
use App\Http\Controllers\Api\Tutor\SelfPaced\Analytics\CourseAnalyticsController;
use App\Http\Controllers\Api\Tutor\SelfPaced\Analytics\CourseCertificatesController;
use App\Http\Controllers\Api\Tutor\SelfPaced\Analytics\CourseEnrollmentsController;
use App\Http\Controllers\Api\Tutor\SelfPaced\Analytics\StudentAssessmentsController;
use App\Http\Controllers\Api\Tutor\SelfPaced\Analytics\StudentProgressController;
use App\Http\Controllers\Api\Tutor\SelfPaced\SelfPacedActivityAttachmentController;
use App\Http\Controllers\Api\Tutor\SelfPaced\SelfPacedActivityController;
use App\Http\Controllers\Api\Tutor\SelfPaced\SelfPacedAssessmentController;
use App\Http\Controllers\Api\Tutor\SelfPaced\SelfPacedCourseController;
use App\Http\Controllers\Api\Tutor\SelfPaced\SelfPacedDiscountCodeController;
use App\Http\Controllers\Api\Tutor\SelfPaced\SelfPacedH5pContentController;
use App\Http\Controllers\Api\Tutor\SelfPaced\SelfPacedModuleContentController;
use App\Http\Controllers\Api\Tutor\SelfPaced\SelfPacedModuleController;
use App\Http\Controllers\Api\Tutor\SelfPaced\SelfPacedSurveyContentController;
use App\Http\Controllers\Api\Tutor\SelfPaced\SelfPacedSurveyQuestionController;
use App\Http\Controllers\Api\Tutor\ServiceController;
use App\Http\Controllers\Api\Tutor\SessionLessonBlockController;
use App\Http\Controllers\Api\Tutor\SessionLessonController;
use App\Http\Controllers\Api\Tutor\SubmissionController as TutorSubmissionController;
use App\Http\Controllers\Api\Tutor\SubmissionFeedbackAttachmentController;
use App\Http\Controllers\Api\Tutor\SubmitTutorApplicationController;
use App\Http\Controllers\Api\Tutor\TeachingSessionController;
use App\Http\Controllers\Api\Tutor\TutorApplicationController;
use App\Http\Controllers\Api\Tutor\TutorDocumentController;
use App\Http\Controllers\Api\Tutor\TutorQualificationController;
use App\Http\Controllers\Api\Tutor\TutorSubjectController;
use App\Http\Controllers\Api\Tutor\TutorWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::post('/register/student', StudentRegistrationController::class);
Route::post('/register/tutor', TutorRegistrationController::class);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:6,1');
Route::post('/reset-password', ResetPasswordController::class)->middleware('throttle:6,1');
Route::post('/accept-admin-invite', AcceptAdminInviteController::class)->middleware('throttle:6,1');

// Deliberately NOT gated by the `verified` middleware — an unverified
// account must still be able to log out, check who it is, and verify
// itself. Nothing else lives in this group.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/me', [AuthenticatedSessionController::class, 'me']);
    Route::post('/email/verify', VerifyEmailController::class);
    Route::post('/email/resend', ResendEmailVerificationController::class)->middleware('throttle:6,1');
});

// Everything below requires a verified account. `verified` sits alongside
// `auth:sanctum` (not nested outside it) so the authenticated user is
// already resolved by the time it runs.
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::get('/grades', [GradeController::class, 'index']);
    Route::get('/service-categories', [ServiceCategoryController::class, 'index']);
    Route::get('/session-formats', [SessionFormatController::class, 'index']);
    Route::get('/learning-resources', [LearningResourceController::class, 'index']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::get('/assessment-types', [AssessmentTypeController::class, 'index']);
    Route::get('/curricula', [CurriculumController::class, 'index']);

    Route::middleware('tutor')->prefix('tutor/application')->group(function () {
        Route::get('/', [TutorApplicationController::class, 'show']);
        Route::post('/basic-info', BasicInformationController::class);
        Route::post('/professional-profile', ProfessionalProfileController::class);
        Route::post('/qualifications', [TutorQualificationController::class, 'store']);
        Route::put('/qualifications/{qualification}', [TutorQualificationController::class, 'update']);
        Route::delete('/qualifications/{qualification}', [TutorQualificationController::class, 'destroy']);
        Route::post('/identity-document', IdentityDocumentController::class);
        Route::post('/documents', [TutorDocumentController::class, 'store']);
        Route::post('/documents/{document}/replace', [TutorDocumentController::class, 'replace']);
        Route::delete('/documents/{document}', [TutorDocumentController::class, 'destroy']);
        Route::post('/submit', SubmitTutorApplicationController::class);
    });

    Route::middleware('tutor')->prefix('tutor/subjects')->group(function () {
        Route::get('/', [TutorSubjectController::class, 'index']);
        Route::post('/', [TutorSubjectController::class, 'store']);
        Route::put('/{subject}', [TutorSubjectController::class, 'update']);
        Route::delete('/{subject}', [TutorSubjectController::class, 'destroy']);
    });

    Route::middleware('tutor')->get('/tutor/approved-subjects', [SubjectController::class, 'approvedForTutor']);

    Route::middleware('tutor')->prefix('tutor/availability')->group(function () {
        Route::get('/', [AvailabilityController::class, 'index']);
        Route::post('/', [AvailabilityController::class, 'store']);
        Route::put('/{slot}', [AvailabilityController::class, 'update']);
        Route::delete('/{slot}', [AvailabilityController::class, 'destroy']);
    });

    Route::middleware('tutor')->prefix('tutor/services')->group(function () {
        Route::get('/', [ServiceController::class, 'index']);
        Route::post('/', [ServiceController::class, 'store']);
        Route::get('/{service}', [ServiceController::class, 'show']);
        Route::put('/{service}', [ServiceController::class, 'update']);
        Route::patch('/{service}/publish', [ServiceController::class, 'publish']);
        Route::patch('/{service}/pause', [ServiceController::class, 'pause']);
    });

    Route::prefix('student/learning-hub')->group(function () {
        Route::get('/', [LearningHubController::class, 'index']);
        Route::get('/upcoming-sessions', [LearningHubController::class, 'upcomingSessions']);
    });

    Route::middleware('tutor')->prefix('tutor/workspace')->group(function () {
        Route::get('/', [TutorWorkspaceController::class, 'index']);
        Route::get('/upcoming-sessions', [TutorWorkspaceController::class, 'upcomingSessions']);
    });

    Route::prefix('marketplace')->group(function () {
        Route::get('/tutors', [MarketplaceTutorController::class, 'index']);
        Route::get('/tutors/{tutor}', [MarketplaceTutorController::class, 'show']);
        Route::get('/tutors/{tutor}/services/{service}/availability', [BookingAvailabilityController::class, 'index']);
        Route::post('/tutors/{tutor}/services/{service}/bookings', [BookingController::class, 'store']);
        Route::get('/self-paced-courses', [MarketplaceSelfPacedCourseController::class, 'index']);
        Route::get('/self-paced-courses/filters', [MarketplaceSelfPacedCourseController::class, 'filters']);
        Route::get('/self-paced-courses/{selfPacedCourse}', [MarketplaceSelfPacedCourseController::class, 'show']);
    });

    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::get('/{order}/pay', [OrderController::class, 'pay']);
    });

    Route::prefix('enrollments')->group(function () {
        Route::get('/', [EnrollmentController::class, 'index']);
        Route::get('/access/{selfPacedCourse}', [EnrollmentController::class, 'access']);
        Route::get('/{enrollment}', [EnrollmentController::class, 'show']);
    });

    Route::prefix('student/self-paced-courses/{selfPacedCourse}')->group(function () {
        Route::get('/', [StudentSelfPacedCourseController::class, 'show']);

        Route::get('/activities/{activity}', [StudentSelfPacedActivityController::class, 'show']);
        Route::patch('/activities/{activity}/complete', [StudentSelfPacedActivityController::class, 'complete']);

        Route::get('/assessments/{assessment}', [StudentSelfPacedAssessmentController::class, 'show']);
        Route::get('/assessments/{assessment}/h5p-player-model', [StudentSelfPacedAssessmentController::class, 'h5pPlayerModel']);
        Route::post('/assessments/{assessment}/attempts', [SelfPacedAssessmentAttemptController::class, 'store']);
        Route::patch('/assessments/{assessment}/attempts/{attempt}/in-progress', [SelfPacedAssessmentAttemptController::class, 'markInProgress']);
        Route::post('/assessments/{assessment}/attempts/{attempt}/complete', [SelfPacedAssessmentAttemptController::class, 'complete']);
    });

    Route::prefix('student/certificates')->group(function () {
        Route::get('/', [CourseCertificateController::class, 'index']);
        Route::get('/{certificate}/download', [CourseCertificateController::class, 'download']);
    });

    Route::get('/student/financial-transactions', [StudentFinancialTransactionController::class, 'index']);

    Route::middleware('tutor')->prefix('tutor/settings/connected-accounts')->group(function () {
        Route::get('/', [ConnectedAccountController::class, 'index']);
        Route::get('/{provider}/redirect', [ConnectedAccountController::class, 'redirect']);
        Route::delete('/{tutorConnectedAccount}', [ConnectedAccountController::class, 'disconnect']);
    });

    Route::middleware('tutor')->prefix('tutor/settings/meeting-providers')->group(function () {
        Route::get('/', [MeetingProviderSettingController::class, 'index']);
        Route::put('/', [MeetingProviderSettingController::class, 'update']);
    });

    Route::prefix('bookings')->group(function () {
        Route::get('/', [BookingController::class, 'index']);
        Route::get('/{booking}', [BookingController::class, 'show']);
        Route::patch('/{booking}/cancel', [BookingController::class, 'cancel']);
        Route::get('/{booking}/lessons', [SessionContentController::class, 'index']);
        Route::get('/{booking}/lesson-blocks/{lessonBlock}', [SessionContentController::class, 'showBlock']);
        Route::get('/{booking}/lesson-blocks/{lessonBlock}/h5p-player-model', [SessionContentController::class, 'h5pPlayerModel']);
        Route::get('/{booking}/lesson-blocks/{lessonBlock}/submissions', [StudentSubmissionController::class, 'index']);
        Route::post('/{booking}/lesson-blocks/{lessonBlock}/submissions', [StudentSubmissionController::class, 'store']);
        Route::get('/{booking}/lesson-blocks/{lessonBlock}/attempts', [StudentAttemptController::class, 'index']);
        Route::post('/{booking}/lesson-blocks/{lessonBlock}/attempts', [StudentAttemptController::class, 'store']);
        Route::get('/{booking}/messages', [BookingChatController::class, 'index']);
        Route::post('/{booking}/messages', [BookingChatController::class, 'store']);
    });

    Route::get('/faqs', [FaqController::class, 'index']);

    Route::prefix('support-tickets')->group(function () {
        Route::get('/', [SupportTicketController::class, 'index']);
        Route::post('/', [SupportTicketController::class, 'store']);
        Route::get('/{supportTicket}', [SupportTicketController::class, 'show']);
        Route::post('/{supportTicket}/comments', [SupportTicketController::class, 'storeComment']);
    });

    Route::prefix('submissions')->group(function () {
        Route::patch('/{submission}', [StudentSubmissionController::class, 'update']);
        Route::post('/{submission}/submit', [StudentSubmissionController::class, 'submit']);
        Route::post('/{submission}/attachments', [StudentSubmissionAttachmentController::class, 'store']);
    });

    Route::prefix('submission-attachments')->group(function () {
        Route::delete('/{submissionAttachment}', [StudentSubmissionAttachmentController::class, 'destroy']);
    });

    Route::prefix('attempts')->group(function () {
        Route::patch('/{attempt}/in-progress', [StudentAttemptController::class, 'markInProgress']);
        Route::post('/{attempt}/complete', [StudentAttemptController::class, 'complete']);
    });

    Route::middleware('tutor')->prefix('tutor/booking-requests')->group(function () {
        Route::get('/', [BookingRequestController::class, 'index']);
        Route::get('/{booking}', [BookingRequestController::class, 'show']);
        Route::patch('/{booking}/accept', [BookingRequestController::class, 'accept']);
        Route::patch('/{booking}/reject', [BookingRequestController::class, 'reject']);
    });

    Route::middleware('tutor')->prefix('tutor/earnings')->group(function () {
        Route::get('/', [EarningsController::class, 'summary']);
        Route::get('/transactions', [EarningsController::class, 'transactions']);
    });

    Route::middleware('tutor')->prefix('tutor/banking-details')->group(function () {
        Route::get('/', [BankingDetailsController::class, 'show']);
        Route::put('/', [BankingDetailsController::class, 'store']);
    });

    Route::middleware('tutor')->post('/tutor/financial-transactions/{financialTransaction}/tickets', [PaymentTicketController::class, 'store']);

    Route::middleware('tutor')->prefix('tutor/payment-tickets')->group(function () {
        Route::get('/', [PaymentTicketController::class, 'index']);
        Route::get('/{paymentTicket}', [PaymentTicketController::class, 'show']);
        Route::post('/{paymentTicket}/comments', [PaymentTicketController::class, 'storeComment']);
    });

    Route::middleware('tutor')->prefix('tutor/bookings/{booking}')->group(function () {
        Route::get('/', [BookingSessionController::class, 'show']);
        Route::post('/sessions', [BookingSessionController::class, 'store']);
        Route::get('/messages', [TutorBookingChatController::class, 'index']);
        Route::post('/messages', [TutorBookingChatController::class, 'store']);
    });

    Route::middleware('tutor')->prefix('tutor/sessions')->group(function () {
        Route::get('/', [TeachingSessionController::class, 'index']);
        Route::get('/{session}', [TeachingSessionController::class, 'show']);
        Route::post('/{session}/meeting/retry', [TeachingSessionController::class, 'retryMeeting']);
        Route::patch('/{session}/complete', [TeachingSessionController::class, 'complete']);
        Route::patch('/{session}/cancel', [TeachingSessionController::class, 'cancel']);
    });

    Route::middleware('tutor')->prefix('tutor/sessions/{session}/lessons')->group(function () {
        Route::get('/', [SessionLessonController::class, 'index']);
        Route::post('/', [SessionLessonController::class, 'store']);
        Route::patch('/reorder', [SessionLessonController::class, 'reorder']);
        Route::delete('/{sessionLesson}', [SessionLessonController::class, 'destroy']);
    });

    Route::middleware('tutor')->prefix('tutor/session-lessons/{sessionLesson}/blocks')->group(function () {
        Route::get('/', [SessionLessonBlockController::class, 'index']);
        Route::post('/', [SessionLessonBlockController::class, 'store']);
        Route::patch('/{sessionLessonBlock}', [SessionLessonBlockController::class, 'update']);
        Route::delete('/{sessionLessonBlock}', [SessionLessonBlockController::class, 'destroy']);
    });

    Route::middleware('tutor')->prefix('tutor/session-lesson-blocks/{sessionLessonBlock}/submissions')->group(function () {
        Route::get('/', [TutorSubmissionController::class, 'index']);
    });

    Route::middleware('tutor')->prefix('tutor/submissions')->group(function () {
        Route::get('/{submission}', [TutorSubmissionController::class, 'show']);
        Route::patch('/{submission}/review', [TutorSubmissionController::class, 'review']);
        Route::patch('/{submission}/return', [TutorSubmissionController::class, 'returnForRevision']);
        Route::patch('/{submission}/grade', [TutorSubmissionController::class, 'grade']);
        Route::patch('/{submission}/publish', [TutorSubmissionController::class, 'publish']);
        Route::post('/{submission}/feedback-attachments', [SubmissionFeedbackAttachmentController::class, 'store']);
    });

    Route::middleware('tutor')->prefix('tutor/feedback-attachments')->group(function () {
        Route::delete('/{submissionAttachment}', [SubmissionFeedbackAttachmentController::class, 'destroy']);
    });

    Route::middleware('tutor')->prefix('tutor/session-lesson-blocks/{sessionLessonBlock}/attempts')->group(function () {
        Route::get('/', [TutorAttemptController::class, 'index']);
    });

    Route::middleware('tutor')->prefix('tutor/attempts')->group(function () {
        Route::get('/{attempt}', [TutorAttemptController::class, 'show']);
    });

    Route::middleware('tutor')->prefix('tutor/courses')->group(function () {
        Route::get('/', [CourseController::class, 'index']);
        Route::post('/', [CourseController::class, 'store']);
        Route::get('/{course}', [CourseController::class, 'show']);
        Route::put('/{course}', [CourseController::class, 'update']);
        Route::delete('/{course}', [CourseController::class, 'destroy']);
        Route::patch('/{course}/archive', [CourseController::class, 'archive']);

        Route::get('/{course}/chapters', [ChapterController::class, 'index']);
        Route::post('/{course}/chapters', [ChapterController::class, 'store']);
        Route::patch('/{course}/chapters/reorder', [ChapterController::class, 'reorder']);
    });

    Route::middleware('tutor')->prefix('tutor/chapters')->group(function () {
        Route::put('/{chapter}', [ChapterController::class, 'update']);
        Route::delete('/{chapter}', [ChapterController::class, 'destroy']);

        Route::get('/{chapter}/lessons', [LessonController::class, 'index']);
        Route::post('/{chapter}/lessons', [LessonController::class, 'store']);
        Route::patch('/{chapter}/lessons/reorder', [LessonController::class, 'reorder']);
    });

    Route::middleware('tutor')->prefix('tutor/lessons')->group(function () {
        Route::put('/{lesson}', [LessonController::class, 'update']);
        Route::delete('/{lesson}', [LessonController::class, 'destroy']);

        Route::get('/{lesson}/blocks', [LessonBlockController::class, 'index']);
        Route::post('/{lesson}/blocks', [LessonBlockController::class, 'store']);
        Route::patch('/{lesson}/blocks/reorder', [LessonBlockController::class, 'reorder']);
    });

    Route::middleware('tutor')->prefix('tutor/lesson-blocks')->group(function () {
        Route::get('/{lessonBlock}', [LessonBlockController::class, 'show']);
        Route::put('/{lessonBlock}', [LessonBlockController::class, 'update']);
        Route::delete('/{lessonBlock}', [LessonBlockController::class, 'destroy']);
        Route::post('/{lessonBlock}/duplicate', [LessonBlockController::class, 'duplicate']);

        Route::get('/{lessonBlock}/media-items', [MediaItemController::class, 'index']);
        Route::post('/{lessonBlock}/media-items', [MediaItemController::class, 'store']);
        Route::patch('/{lessonBlock}/media-items/reorder', [MediaItemController::class, 'reorder']);
    });

    Route::middleware('tutor')->prefix('tutor/media-items')->group(function () {
        Route::put('/{mediaItem}', [MediaItemController::class, 'update']);
        Route::delete('/{mediaItem}', [MediaItemController::class, 'destroy']);
    });

    Route::middleware('tutor')->prefix('tutor/quizzes')->group(function () {
        Route::get('/{quiz}', [QuizController::class, 'show']);
        Route::put('/{quiz}', [QuizController::class, 'update']);
        Route::patch('/{quiz}/publish', [QuizController::class, 'publish']);
        Route::delete('/{quiz}', [QuizController::class, 'destroy']);

        Route::post('/{quiz}/questions', [QuizQuestionController::class, 'store']);
        Route::patch('/{quiz}/questions/reorder', [QuizQuestionController::class, 'reorder']);
    });

    Route::middleware('tutor')->prefix('tutor/quiz-questions')->group(function () {
        Route::put('/{question}', [QuizQuestionController::class, 'update']);
        Route::delete('/{question}', [QuizQuestionController::class, 'destroy']);
    });

    Route::prefix('quizzes')->group(function () {
        Route::get('/{quiz}', [StudentQuizController::class, 'show']);
        Route::post('/{quiz}/attempts', [StudentQuizAttemptController::class, 'store']);
        Route::get('/{quiz}/attempts', [StudentQuizAttemptController::class, 'index']);
        Route::post('/attempts/{attempt}/submit', [StudentQuizAttemptController::class, 'submit']);
    });

    Route::middleware('tutor')->prefix('tutor/learning-activities')->group(function () {
        Route::get('/{learningActivity}', [LearningActivityController::class, 'show']);
        Route::put('/{learningActivity}', [LearningActivityController::class, 'update']);
        Route::delete('/{learningActivity}', [LearningActivityController::class, 'destroy']);

        Route::get('/{learningActivity}/attachments', [ActivityAttachmentController::class, 'index']);
        Route::post('/{learningActivity}/attachments', [ActivityAttachmentController::class, 'store']);
        Route::patch('/{learningActivity}/attachments/reorder', [ActivityAttachmentController::class, 'reorder']);
    });

    Route::middleware('tutor')->prefix('tutor/activity-attachments')->group(function () {
        Route::put('/{activityAttachment}', [ActivityAttachmentController::class, 'update']);
        Route::delete('/{activityAttachment}', [ActivityAttachmentController::class, 'destroy']);
    });

    // --- Course Authoring Engine (Idea B) — entirely independent of the
    // Tutor-Led Learning routes above; never shares a controller, request, or
    // resource with them.

    Route::middleware('tutor')->prefix('tutor/self-paced-courses')->group(function () {
        Route::get('/', [SelfPacedCourseController::class, 'index']);
        Route::post('/', [SelfPacedCourseController::class, 'store']);
        Route::get('/{selfPacedCourse}', [SelfPacedCourseController::class, 'show']);
        Route::put('/{selfPacedCourse}', [SelfPacedCourseController::class, 'update']);
        Route::delete('/{selfPacedCourse}', [SelfPacedCourseController::class, 'destroy']);
        Route::patch('/{selfPacedCourse}/publish', [SelfPacedCourseController::class, 'publish']);
        Route::patch('/{selfPacedCourse}/unpublish', [SelfPacedCourseController::class, 'unpublish']);
        Route::patch('/{selfPacedCourse}/make-private', [SelfPacedCourseController::class, 'makePrivate']);
        Route::patch('/{selfPacedCourse}/archive', [SelfPacedCourseController::class, 'archive']);

        Route::get('/{selfPacedCourse}/modules', [SelfPacedModuleController::class, 'index']);
        Route::post('/{selfPacedCourse}/modules', [SelfPacedModuleController::class, 'store']);
        Route::patch('/{selfPacedCourse}/modules/reorder', [SelfPacedModuleController::class, 'reorder']);

        Route::get('/{selfPacedCourse}/discount-codes', [SelfPacedDiscountCodeController::class, 'index']);
        Route::post('/{selfPacedCourse}/discount-codes', [SelfPacedDiscountCodeController::class, 'store']);
        Route::post('/{selfPacedCourse}/discount-codes/preview', [SelfPacedDiscountCodeController::class, 'preview']);

        Route::get('/{selfPacedCourse}/analytics', [CourseAnalyticsController::class, 'show']);
        Route::get('/{selfPacedCourse}/enrollments', [CourseEnrollmentsController::class, 'index']);
        Route::get('/{selfPacedCourse}/enrollments/{enrollment}', [StudentProgressController::class, 'show']);
        Route::get('/{selfPacedCourse}/enrollments/{enrollment}/assessments', [StudentAssessmentsController::class, 'index']);
        Route::get('/{selfPacedCourse}/certificates', [CourseCertificatesController::class, 'index']);
        Route::get('/{selfPacedCourse}/certificates/{certificate}/download', [CourseCertificatesController::class, 'download']);
    });

    Route::middleware('tutor')->prefix('tutor/self-paced-modules')->group(function () {
        Route::put('/{selfPacedModule}', [SelfPacedModuleController::class, 'update']);
        Route::delete('/{selfPacedModule}', [SelfPacedModuleController::class, 'destroy']);

        Route::get('/{selfPacedModule}/activities', [SelfPacedActivityController::class, 'index']);
        Route::post('/{selfPacedModule}/activities', [SelfPacedActivityController::class, 'store']);

        Route::get('/{selfPacedModule}/assessments', [SelfPacedAssessmentController::class, 'index']);
        Route::post('/{selfPacedModule}/assessments', [SelfPacedAssessmentController::class, 'store']);

        Route::patch('/{selfPacedModule}/content/reorder', [SelfPacedModuleContentController::class, 'reorder']);
    });

    Route::middleware('tutor')->prefix('tutor/self-paced-activities')->group(function () {
        Route::put('/{selfPacedActivity}', [SelfPacedActivityController::class, 'update']);
        Route::delete('/{selfPacedActivity}', [SelfPacedActivityController::class, 'destroy']);

        Route::post('/{selfPacedActivity}/attachments', [SelfPacedActivityAttachmentController::class, 'store']);
        Route::patch('/{selfPacedActivity}/attachments/reorder', [SelfPacedActivityAttachmentController::class, 'reorder']);
    });

    Route::middleware('tutor')->prefix('tutor/self-paced-activity-attachments')->group(function () {
        Route::put('/{selfPacedActivityAttachment}', [SelfPacedActivityAttachmentController::class, 'update']);
        Route::delete('/{selfPacedActivityAttachment}', [SelfPacedActivityAttachmentController::class, 'destroy']);
    });

    Route::middleware('tutor')->prefix('tutor/self-paced-assessments')->group(function () {
        Route::put('/{selfPacedAssessment}', [SelfPacedAssessmentController::class, 'update']);
        Route::delete('/{selfPacedAssessment}', [SelfPacedAssessmentController::class, 'destroy']);
    });

    Route::middleware('tutor')->prefix('tutor/self-paced-discount-codes')->group(function () {
        Route::put('/{selfPacedDiscountCode}', [SelfPacedDiscountCodeController::class, 'update']);
        Route::delete('/{selfPacedDiscountCode}', [SelfPacedDiscountCodeController::class, 'destroy']);
    });

    Route::middleware('tutor')->prefix('tutor/self-paced-h5p-contents')->group(function () {
        Route::get('/', [SelfPacedH5pContentController::class, 'index']);
        Route::post('/', [SelfPacedH5pContentController::class, 'store']);
    });

    Route::middleware('tutor')->prefix('tutor/self-paced-survey-contents')->group(function () {
        Route::get('/', [SelfPacedSurveyContentController::class, 'index']);
        Route::post('/', [SelfPacedSurveyContentController::class, 'store']);
        Route::get('/{selfPacedSurveyContent}', [SelfPacedSurveyContentController::class, 'show']);
        Route::put('/{selfPacedSurveyContent}', [SelfPacedSurveyContentController::class, 'update']);
        Route::delete('/{selfPacedSurveyContent}', [SelfPacedSurveyContentController::class, 'destroy']);

        Route::post('/{selfPacedSurveyContent}/questions', [SelfPacedSurveyQuestionController::class, 'store']);
        Route::patch('/{selfPacedSurveyContent}/questions/reorder', [SelfPacedSurveyQuestionController::class, 'reorder']);
    });

    Route::middleware('tutor')->prefix('tutor/self-paced-survey-questions')->group(function () {
        Route::put('/{selfPacedSurveyQuestion}', [SelfPacedSurveyQuestionController::class, 'update']);
        Route::delete('/{selfPacedSurveyQuestion}', [SelfPacedSurveyQuestionController::class, 'destroy']);
    });

    Route::middleware('tutor')->prefix('tutor/h5p-content')->group(function () {
        Route::get('/', [H5pContentController::class, 'index']);
        Route::get('/editor-model', [H5pContentController::class, 'newEditorModel']);
        Route::get('/{contentId}/editor-model', [H5pContentController::class, 'editorModel']);
        Route::get('/{contentId}/player-model', [H5pContentController::class, 'playerModel']);
        Route::post('/', [H5pContentController::class, 'store']);
        Route::patch('/{contentId}', [H5pContentController::class, 'update']);
        Route::delete('/{contentId}', [H5pContentController::class, 'destroy']);
        Route::post('/import', [H5pContentController::class, 'import']);
        Route::get('/{contentId}/export', [H5pContentController::class, 'export']);
    });

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'show']);
        Route::get('/activity-log', [ActivityLogController::class, 'index']);

        Route::prefix('subjects')->group(function () {
            Route::get('/', [AdminSubjectController::class, 'index']);
            Route::post('/', [AdminSubjectController::class, 'store']);
            Route::get('/{subject}', [AdminSubjectController::class, 'show']);
            Route::patch('/{subject}', [AdminSubjectController::class, 'update']);
            Route::post('/{subject}/activate', [AdminSubjectController::class, 'activate']);
            Route::post('/{subject}/deactivate', [AdminSubjectController::class, 'deactivate']);
            Route::post('/{subject}/archive', [AdminSubjectController::class, 'archive']);
        });

        Route::prefix('faqs')->group(function () {
            Route::get('/', [AdminFaqController::class, 'index']);
            Route::post('/', [AdminFaqController::class, 'store']);
            Route::patch('/{faq}', [AdminFaqController::class, 'update']);
            Route::delete('/{faq}', [AdminFaqController::class, 'destroy']);
        });

        Route::prefix('tutors')->group(function () {
            Route::get('/pending', [TutorApprovalController::class, 'index']);
            Route::get('/{user}', [TutorApprovalController::class, 'show']);
            Route::post('/{user}/approve', [TutorApprovalController::class, 'approve']);
            Route::post('/{user}/reject', [TutorApprovalController::class, 'reject']);
            Route::post('/{user}/request-changes', [TutorApprovalController::class, 'requestChanges']);
        });

        Route::prefix('tutor-subject-requests')->group(function () {
            Route::get('/', [TutorSubjectApprovalController::class, 'index']);
            Route::post('/{tutorSubject}/approve', [TutorSubjectApprovalController::class, 'approve']);
            Route::post('/{tutorSubject}/reject', [TutorSubjectApprovalController::class, 'reject']);
            Route::post('/{tutorSubject}/suspend', [TutorSubjectApprovalController::class, 'suspend']);
        });

        Route::get('/settings', [AdminSettingsController::class, 'index']);
        Route::patch('/settings', [AdminSettingsController::class, 'update']);

        Route::prefix('financial-rules')->group(function () {
            Route::get('/', [FinancialRuleController::class, 'index']);
            Route::post('/', [FinancialRuleController::class, 'store']);
            Route::get('/global', [FinancialRuleController::class, 'showGlobal']);
            Route::patch('/global', [FinancialRuleController::class, 'updateGlobal']);
            Route::patch('/{financialRule}', [FinancialRuleController::class, 'update']);
            Route::post('/{financialRule}/deactivate', [FinancialRuleController::class, 'deactivate']);
            Route::post('/{financialRule}/activate', [FinancialRuleController::class, 'activate']);
        });

        Route::prefix('financial-transactions')->group(function () {
            Route::get('/', [FinancialTransactionController::class, 'index']);
            Route::post('/{financialTransaction}/mark-paid', [FinancialTransactionController::class, 'markPaid']);
            Route::patch('/{financialTransaction}/payout-status', [FinancialTransactionController::class, 'updatePayoutStatus']);
        });

        Route::prefix('payment-tickets')->group(function () {
            Route::get('/', [AdminPaymentTicketController::class, 'index']);
            Route::get('/{paymentTicket}', [AdminPaymentTicketController::class, 'show']);
            Route::patch('/{paymentTicket}/status', [AdminPaymentTicketController::class, 'updateStatus']);
            Route::post('/{paymentTicket}/comments', [AdminPaymentTicketController::class, 'storeComment']);
        });

        Route::prefix('support-tickets')->group(function () {
            Route::get('/', [AdminSupportTicketController::class, 'index']);
            Route::get('/{supportTicket}', [AdminSupportTicketController::class, 'show']);
            Route::patch('/{supportTicket}/status', [AdminSupportTicketController::class, 'updateStatus']);
            Route::post('/{supportTicket}/comments', [AdminSupportTicketController::class, 'storeComment']);
        });

        Route::get('/quick-setup', [QuickSetupController::class, 'index']);
        Route::post('/quick-setup/email/test', [QuickSetupController::class, 'sendTestEmail']);

        Route::get('/integrations', [IntegrationStatusController::class, 'index']);
        Route::get('/system-health', [SystemHealthController::class, 'index']);

        Route::prefix('users')->group(function () {
            Route::get('/', [AdminUserController::class, 'index']);
            Route::get('/{user}', [AdminUserController::class, 'show']);
            Route::post('/{user}/disable', [AdminUserController::class, 'disable']);
            Route::post('/{user}/enable', [AdminUserController::class, 'enable']);
        });

        Route::middleware('super_admin')->prefix('admins')->group(function () {
            Route::get('/', [AdminAccountController::class, 'index']);
            Route::post('/', [AdminAccountController::class, 'store']);
            Route::post('/{user}/resend-invite', [AdminAccountController::class, 'resendInvite']);
            Route::post('/{user}/deactivate', [AdminAccountController::class, 'deactivate']);
            Route::post('/{user}/activate', [AdminAccountController::class, 'activate']);
            Route::delete('/{user}', [AdminAccountController::class, 'destroy']);
        });

        // The full tutor directory (every tutor, searchable) — distinct
        // from admin/tutors/{user} above, which is the pending-approval
        // review queue and its approve/reject/request-changes actions.
        Route::prefix('tutor-profiles')->group(function () {
            Route::get('/', [TutorManagementController::class, 'index']);
            Route::get('/{tutorProfile}', [TutorManagementController::class, 'show']);
        });

        Route::prefix('students')->group(function () {
            Route::get('/', [StudentManagementController::class, 'index']);
            Route::get('/{student}', [StudentManagementController::class, 'show']);
        });

        Route::prefix('self-paced-courses')->group(function () {
            Route::get('/', [SelfPacedCourseManagementController::class, 'index']);
            Route::get('/{selfPacedCourse}', [SelfPacedCourseManagementController::class, 'show']);
        });

        Route::get('/tutoring-services', [TutoringServiceManagementController::class, 'index']);

        Route::get('/bookings', [BookingManagementController::class, 'index']);
        Route::post('/bookings/{booking}/cancel', [BookingManagementController::class, 'cancel']);

        Route::get('/sessions', [SessionManagementController::class, 'index']);

        Route::get('/payments', [PaymentManagementController::class, 'index']);

        Route::prefix('certificates')->group(function () {
            Route::get('/', [CertificateManagementController::class, 'index']);
            Route::get('/verify', [CertificateManagementController::class, 'verify']);
            Route::get('/{certificate}', [CertificateManagementController::class, 'show']);
            Route::get('/{certificate}/download', [CertificateManagementController::class, 'download']);
        });
    });
});

// PayFast's server posts here directly — no Sanctum token exists, and the
// `api` middleware group carries no CSRF, so no exemption is needed either.
Route::post('/payfast/itn', [PayFastItnController::class, 'handle']);

// Google's server redirects the browser here directly after consent — no
// Sanctum token exists on a plain browser navigation, so this must sit
// outside auth:sanctum (same reasoning as the PayFast ITN route above).
Route::get('/tutor/settings/connected-accounts/{provider}/callback', [ConnectedAccountController::class, 'callback']);
