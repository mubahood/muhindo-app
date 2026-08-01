<?php

use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Admin\CourseModuleController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EducationController;
use App\Http\Controllers\Admin\EnrollmentController;
use App\Http\Controllers\Admin\ExperienceController;
use App\Http\Controllers\Admin\GatewayPaymentController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\LessonMaterialController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PortfolioProjectController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectDocumentController;
use App\Http\Controllers\Admin\ProjectNoteController;
use App\Http\Controllers\Admin\ProjectTaskController;
use App\Http\Controllers\Admin\ProjectUpdateController;
use App\Http\Controllers\Admin\ServicePageController;
use App\Http\Controllers\Admin\SkillController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\Client\PortalController;
use App\Http\Controllers\CourseCatalogueController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Student\LearningController;
use App\Http\Controllers\Student\LessonMaterialController as StudentLessonMaterialController;
use App\Http\Controllers\Student\QuizAttemptController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public — portfolio
|--------------------------------------------------------------------------
*/
Route::get('/', [PortfolioController::class, 'home'])->name('home');
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('/work', [PortfolioController::class, 'work'])->name('portfolio.work');
Route::get('/work/{portfolioProject:slug}', [PortfolioController::class, 'project'])->name('portfolio.project');
Route::get('/about', [PortfolioController::class, 'about'])->name('portfolio.about');
Route::get('/services', [PortfolioController::class, 'services'])->name('portfolio.services');
Route::get('/skills', [PortfolioController::class, 'skills'])->name('portfolio.skills');
Route::get('/experience', [PortfolioController::class, 'experience'])->name('portfolio.experience');
Route::get('/education', [PortfolioController::class, 'education'])->name('portfolio.education');
Route::get('/research', [PortfolioController::class, 'research'])->name('portfolio.research');
Route::get('/cv', [PortfolioController::class, 'cv'])->name('portfolio.cv');
Route::get('/products', [PortfolioController::class, 'products'])->name('portfolio.products');
Route::get('/contact', [PortfolioController::class, 'contactPage'])->name('contact');
Route::post('/contact', [PortfolioController::class, 'contact'])->middleware('throttle:5,1')->name('contact.store');
Route::get('/start-a-project', [\App\Http\Controllers\ProjectInquiryController::class, 'create'])->name('start-a-project');
Route::post('/start-a-project', [\App\Http\Controllers\ProjectInquiryController::class, 'store'])->middleware('throttle:5,1')->name('start-a-project.store');
Route::view('/privacy', 'marketing.privacy')->name('privacy');
Route::view('/terms', 'marketing.terms')->name('terms');
Route::get('/verify/{certificate}', [CertificateVerificationController::class, 'show'])->name('certificates.verify');

/*
|--------------------------------------------------------------------------
| Public — e‑Learning catalogue & checkout
|--------------------------------------------------------------------------
| Public-facing URL is /e-learning (§2.1 of PUBLIC_SITE_PLAN.md — "e‑Learning" is the
| product name shown to visitors). Route *names* stay `courses.*` deliberately: every
| `route('courses.show', ...)` call site (controllers, views, tests) keeps working
| unchanged and automatically generates the new /e-learning URL — only the URI
| pattern changed, not the internal naming, so this is zero backend churn.
| /courses/* is kept as a permanent redirect below for anyone who already bookmarked
| or indexed the old URL.
*/
Route::get('/e-learning', [CourseCatalogueController::class, 'index'])->name('courses.index');
Route::get('/e-learning/{course:slug}', [CourseCatalogueController::class, 'show'])->name('courses.show');
Route::get('/e-learning/{course:slug}/preview/{lesson}', [CourseCatalogueController::class, 'preview'])->name('courses.preview');
Route::post('/e-learning/{course:slug}/enroll', [CourseCatalogueController::class, 'enroll'])
    ->middleware(['auth', 'throttle:10,1'])->name('courses.enroll');
Route::get('/e-learning/{course:slug}/checkout', [CourseCatalogueController::class, 'checkout'])
    ->middleware('auth')->name('courses.checkout');

Route::redirect('/courses', '/e-learning', 301);
Route::redirect('/courses/{course}/preview/{lesson}', '/e-learning/{course}/preview/{lesson}', 301);
Route::redirect('/courses/{course}/checkout', '/e-learning/{course}/checkout', 301);
Route::redirect('/courses/{course}', '/e-learning/{course}', 301);

/*
|--------------------------------------------------------------------------
| Authentication (single login for every role: owner, admin, student, client)
|--------------------------------------------------------------------------
*/
Route::get('/login', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store']);
Route::post('/logout', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'])->name('logout');

Route::get('/register', [\App\Http\Controllers\Auth\StudentRegistrationController::class, 'create'])->name('register');
Route::post('/register', [\App\Http\Controllers\Auth\StudentRegistrationController::class, 'store']);

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Dashboard — one entry point, content branches by role
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/onboarding/dismiss', [DashboardController::class, 'dismissOnboarding'])->name('dashboard.onboarding.dismiss');
    Route::post('/theme', [\App\Http\Controllers\Admin\ThemeController::class, 'update'])->name('theme');
    Route::get('/notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Admin\NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\Admin\NotificationController::class, 'readAll'])->name('notifications.read-all');
});

// Your account — profile & settings, one screen for every signed-in person
Route::prefix('account')->middleware(['auth'])->name('account.')->group(function () {
    Route::get('/', [\App\Http\Controllers\AccountController::class, 'edit'])->name('edit');
    Route::post('/', [\App\Http\Controllers\AccountController::class, 'update'])->name('update');
    Route::post('type', [\App\Http\Controllers\AccountController::class, 'updateAccountType'])->name('type');
    Route::post('avatar', [\App\Http\Controllers\AccountController::class, 'updateAvatar'])->name('avatar');
    Route::delete('avatar', [\App\Http\Controllers\AccountController::class, 'removeAvatar'])->name('avatar.remove');
});

/*
|--------------------------------------------------------------------------
| Admin back-office (super_admin / admin only)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth', 'admin'])->name('admin.')->group(function () {
    // Portfolio CMS
    Route::resource('portfolio-projects', PortfolioProjectController::class)->except('show');
    Route::resource('skills', SkillController::class)->except('show');
    Route::resource('experience', ExperienceController::class)->except('show');
    Route::resource('education', EducationController::class)->except('show');
    Route::resource('services', ServicePageController::class)->except('show');
    Route::get('messages', [PortfolioController::class, 'inbox'])->name('messages.index');
    Route::post('messages/{contactMessage}/read', [PortfolioController::class, 'markRead'])->name('messages.read');

    // Courses / LMS
    Route::resource('courses', AdminCourseController::class);
    Route::get('courses/{course}/students', \App\Livewire\Admin\CourseStudents::class)->name('courses.students');
    Route::resource('courses.modules', CourseModuleController::class)
        ->shallow()->except('show');
    Route::resource('modules.lessons', LessonController::class)
        ->shallow()->except('show');
    Route::post('modules/{module}/lessons-quick', [LessonController::class, 'storeInline'])->name('modules.lessons.quick-store');
    Route::post('lessons/{lesson}/toggle-publish', [LessonController::class, 'togglePublish'])->name('lessons.toggle-publish');
    Route::post('lessons/preview-markdown', [LessonController::class, 'previewMarkdown'])->name('lessons.preview-markdown');
    Route::post('lessons/fetch-video-duration', [LessonController::class, 'fetchVideoDuration'])->name('lessons.fetch-video-duration');
    Route::post('courses/{course}/curriculum/reorder', [\App\Http\Controllers\Admin\CurriculumController::class, 'reorder'])->name('courses.curriculum.reorder');
    Route::resource('lessons.materials', LessonMaterialController::class)
        ->shallow()->only(['store', 'destroy']);
    Route::post('lessons/{lesson}/content-images', [\App\Http\Controllers\Admin\LessonContentImageController::class, 'store'])
        ->name('lessons.content-images.store');
    Route::resource('courses.quizzes', \App\Http\Controllers\Admin\QuizController::class)
        ->shallow()->except(['index', 'show']);
    Route::get('quizzes/{quiz}/analysis', [\App\Http\Controllers\Admin\QuizController::class, 'analysis'])->name('quizzes.analysis');
    Route::resource('quizzes.questions', \App\Http\Controllers\Admin\QuestionController::class)
        ->shallow()->except(['index', 'show']);
    Route::resource('courses.assignments', \App\Http\Controllers\Admin\AssignmentController::class)
        ->shallow()->except(['index', 'show']);
    Route::resource('courses.announcements', \App\Http\Controllers\Admin\AnnouncementController::class)
        ->shallow()->except(['index', 'show']);
    Route::post('announcements/{announcement}/publish', [\App\Http\Controllers\Admin\AnnouncementController::class, 'publish'])
        ->name('announcements.publish');
    Route::get('courses/{course}/analytics', [\App\Http\Controllers\Admin\CourseAnalyticsController::class, 'show'])->name('courses.analytics');
    Route::get('courses/{course}/gradebook', \App\Livewire\Admin\GradeMatrix::class)->name('courses.gradebook');
    Route::get('courses/{course}/discussions', \App\Livewire\Admin\CourseDiscussions::class)->name('courses.discussions');
    Route::get('courses/{course}/bulk-enroll', [\App\Http\Controllers\Admin\BulkEnrollController::class, 'create'])->name('courses.bulk-enroll.create');
    Route::post('courses/{course}/bulk-enroll', [\App\Http\Controllers\Admin\BulkEnrollController::class, 'store'])->name('courses.bulk-enroll.store');
    Route::get('courses/{course}/gradebook/export', \App\Http\Controllers\Admin\GradebookExportController::class)->name('courses.gradebook.export');
    Route::get('enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
    Route::get('enrollments/{enrollment}', \App\Livewire\Admin\EnrollmentDrilldown::class)->name('enrollments.show');
    Route::get('grading-queue', \App\Livewire\Admin\GradingQueue::class)->name('grading-queue');
    Route::get('reviews', \App\Livewire\Admin\ReviewModeration::class)->name('reviews.index');
    Route::post('courses/{course}/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
    Route::delete('enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');

    // Clients & Projects
    Route::resource('clients', ClientController::class);
    Route::resource('projects', ProjectController::class);
    Route::get('project-inquiries', [\App\Http\Controllers\Admin\ProjectInquiryController::class, 'index'])->name('project-inquiries.index');
    Route::get('project-inquiries/{projectInquiry}', [\App\Http\Controllers\Admin\ProjectInquiryController::class, 'show'])->name('project-inquiries.show');
    Route::patch('project-inquiries/{projectInquiry}/status', [\App\Http\Controllers\Admin\ProjectInquiryController::class, 'updateStatus'])->name('project-inquiries.status');
    Route::post('projects/{project}/tasks', [ProjectTaskController::class, 'store'])->name('projects.tasks.store');
    Route::put('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'update'])->name('projects.tasks.update');
    Route::delete('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'destroy'])->name('projects.tasks.destroy');
    Route::post('projects/{project}/notes', [ProjectNoteController::class, 'store'])->name('projects.notes.store');
    Route::post('projects/{project}/updates', [ProjectUpdateController::class, 'store'])->name('projects.updates.store');
    Route::post('projects/{project}/documents', [ProjectDocumentController::class, 'store'])->name('projects.documents.store');
    Route::get('projects/{project}/documents/{document}', [ProjectDocumentController::class, 'download'])->name('projects.documents.download');
    Route::delete('projects/{project}/documents/{document}', [ProjectDocumentController::class, 'destroy'])->name('projects.documents.destroy');

    // Billing
    Route::resource('invoices', AdminInvoiceController::class);
    Route::get('invoices/{invoice}/pdf', [AdminInvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::post('invoices/{invoice}/payments', [AdminPaymentController::class, 'store'])->name('invoices.payments.store');
    Route::post('invoices/{invoice}/pay/flutterwave', [GatewayPaymentController::class, 'start'])->name('invoices.flutterwave');
    Route::get('payments/{payment}/receipt', [AdminPaymentController::class, 'receipt'])->name('payments.receipt');
    Route::resource('coupons', \App\Http\Controllers\Admin\CouponController::class)->except(['show']);

    // Users & settings
    Route::resource('users', UserController::class)->middleware('permission:manage-users');
    Route::get('settings', [\App\Http\Controllers\Admin\SettingController::class, 'index'])->name('settings.index');
    Route::post('settings', [\App\Http\Controllers\Admin\SettingController::class, 'update'])->name('settings.update');
});

/*
|--------------------------------------------------------------------------
| Student learning portal
|--------------------------------------------------------------------------
*/
Route::prefix('learn')->middleware(['auth'])->name('learn.')->group(function () {
    Route::get('/', [LearningController::class, 'index'])->name('index');
    Route::get('certificates/{certificate}', [LearningController::class, 'certificate'])->name('certificate');
    Route::get('{course:slug}', [LearningController::class, 'show'])->name('course');

    // These literal-prefix routes (GET {course:slug}/quizzes, {course:slug}/assignments) MUST be
    // registered before the generic {course:slug}/{lesson} route below — Laravel matches GET
    // routes in registration order at the URI-pattern level, before route-model binding ever
    // runs, so a later, more specific route is unreachable dead code once a bare {lesson}
    // wildcard of the same segment count is registered first (confirmed: it 404s on failing to
    // resolve "quizzes"/"assignments" as a Lesson, never falling through to try this route).
    Route::get('{course:slug}/quizzes', [QuizAttemptController::class, 'index'])->name('quizzes.index');
    Route::get('{course:slug}/quizzes/{quiz}', [QuizAttemptController::class, 'show'])->name('quiz.show');
    Route::post('{course:slug}/quizzes/{quiz}/start', [QuizAttemptController::class, 'start'])->name('quiz.start');
    Route::get('{course:slug}/quizzes/{quiz}/attempts/{attempt}', [QuizAttemptController::class, 'run'])->name('quiz.attempt');
    Route::post('{course:slug}/quizzes/{quiz}/attempts/{attempt}/questions/{question}/answer', [QuizAttemptController::class, 'answer'])
        ->middleware('throttle:60,1')->name('quiz.answer');
    Route::post('{course:slug}/quizzes/{quiz}/attempts/{attempt}/submit', [QuizAttemptController::class, 'submit'])->name('quiz.submit');
    Route::get('{course:slug}/quizzes/{quiz}/attempts/{attempt}/review', [QuizAttemptController::class, 'review'])->name('quiz.review');

    Route::get('{course:slug}/assignments', [\App\Http\Controllers\Student\AssignmentController::class, 'index'])->name('assignments.index');
    Route::get('{course:slug}/assignments/{assignment}', [\App\Http\Controllers\Student\AssignmentController::class, 'show'])->name('assignment.show');
    Route::post('{course:slug}/assignments/{assignment}/draft', [\App\Http\Controllers\Student\AssignmentController::class, 'saveDraft'])->name('assignment.draft');
    Route::post('{course:slug}/assignments/{assignment}/submit', [\App\Http\Controllers\Student\AssignmentController::class, 'submit'])->name('assignment.submit');
    Route::get('{course:slug}/assignments/{assignment}/submissions/{submission}/download', [\App\Http\Controllers\Student\AssignmentController::class, 'download'])->name('assignment.download');

    Route::get('{course:slug}/grades', [\App\Http\Controllers\Student\GradesController::class, 'show'])->name('grades');

    Route::get('{course:slug}/announcements', [\App\Http\Controllers\Student\AnnouncementController::class, 'index'])->name('announcements.index');

    Route::get('{course:slug}/review', [\App\Http\Controllers\Student\CourseReviewController::class, 'create'])->name('review.create');
    Route::post('{course:slug}/review', [\App\Http\Controllers\Student\CourseReviewController::class, 'store'])->name('review.store');

    Route::get('{course:slug}/discussions', [\App\Http\Controllers\Student\DiscussionController::class, 'index'])->name('discussions.index');
    Route::get('{course:slug}/discussions/create', [\App\Http\Controllers\Student\DiscussionController::class, 'create'])->name('discussions.create');
    Route::post('{course:slug}/discussions', [\App\Http\Controllers\Student\DiscussionController::class, 'store'])->name('discussions.store');
    Route::get('{course:slug}/discussions/{discussion}', [\App\Http\Controllers\Student\DiscussionController::class, 'show'])->name('discussions.show');
    Route::post('{course:slug}/discussions/{discussion}/reply', [\App\Http\Controllers\Student\DiscussionController::class, 'reply'])->name('discussions.reply');
    Route::post('{course:slug}/discussions/{discussion}/resolve', [\App\Http\Controllers\Student\DiscussionController::class, 'resolve'])->name('discussions.resolve');

    Route::get('{course:slug}/{lesson}', [LearningController::class, 'lesson'])->name('lesson');
    Route::post('{course:slug}/{lesson}/complete', [LearningController::class, 'complete'])->name('lesson.complete');
    Route::post('{course:slug}/{lesson}/heartbeat', [LearningController::class, 'heartbeat'])->middleware('throttle:20,1')->name('lesson.heartbeat');
    Route::post('{course:slug}/{lesson}/time', [LearningController::class, 'activeTime'])->middleware('throttle:20,1')->name('lesson.time');
    Route::get('{course:slug}/{lesson}/materials/{material}', [StudentLessonMaterialController::class, 'download'])->name('materials.download');
    Route::get('{course:slug}/{lesson}/materials/{material}/preview', [StudentLessonMaterialController::class, 'preview'])->name('materials.preview');
    Route::get('{course:slug}/{lesson}/video-stream', [\App\Http\Controllers\Student\LessonVideoController::class, 'stream'])
        ->middleware('signed')->name('lesson.video-stream');
    Route::get('{course:slug}/{lesson}/content-images/{filename}', [\App\Http\Controllers\Student\LessonContentImageController::class, 'show'])->name('content-images.show');
    Route::post('{course:slug}/{lesson}/notes', [\App\Http\Controllers\Student\LessonNoteController::class, 'store'])->name('notes.store');
    Route::delete('{course:slug}/{lesson}/notes/{note}', [\App\Http\Controllers\Student\LessonNoteController::class, 'destroy'])->name('notes.destroy');
});

/*
|--------------------------------------------------------------------------
| Client portal
|--------------------------------------------------------------------------
*/
Route::prefix('portal')->middleware(['auth'])->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('index');
    Route::get('projects/{project}', [PortalController::class, 'project'])->name('project');
    Route::get('projects/{project}/documents/{document}', [PortalController::class, 'downloadDocument'])->name('project.document');
    Route::get('invoices', [PortalController::class, 'invoices'])->name('invoices');
    Route::get('invoices/{invoice}/pdf', [PortalController::class, 'invoicePdf'])->name('invoice.pdf');
    Route::post('invoices/{invoice}/pay', [GatewayPaymentController::class, 'start'])->name('invoice.pay');
});

/*
|--------------------------------------------------------------------------
| Payment gateway (public — Flutterwave callback + webhook)
|--------------------------------------------------------------------------
*/
Route::get('gateway/flutterwave/callback', [GatewayPaymentController::class, 'callback'])->name('gateway.callback');
Route::post('gateway/flutterwave/webhook', [GatewayPaymentController::class, 'webhook'])->name('gateway.webhook');
