# muhindo-app — Build Plan

**Portfolio (public) → Login → LMS (students) + Client Project Management (clients) + Personal Ops (you)**

Status: planning document. Nothing has been copied or executed yet — this is the complete step-by-step guide to build it.

---

## 0. The decision in one paragraph

`true-doctor` is a mature, well-engineered Laravel 12 app (hand-built Blade+Livewire+Alpine admin, Sanctum auth, Spatie RBAC/activity-log, service-layer conventions, invoicing/payments via Flutterwave, PDF/QR generation, a clean design system called `td-admin.css`). About 90% of it is Hospital-Management-System domain code (patients, wards, labs, pharmacy...) that is useless to you — but the other 10% (auth, RBAC, admin shell, dashboard framework, billing machinery) is exactly the skeleton a personal management platform needs, and it would take weeks to hand-build from nothing. `muhindomubaraka` contributes something true-doctor doesn't have at all: your actual portfolio content (`amout-muhindo.md` + `config/portfolio.php` — bio, projects, skills, experience, research, products) and its database, which is real, populated, and already shaped like a personal-platform DB (rich user profile fields, `contact_messages`, and a legal-practice `clients/legal_cases/documents/case_notes` schema that is a genuinely strong template for a client/project-tracking module even though it was built for a law firm).

**Plan**: copy true-doctor's entire codebase into muhindo-app (full copy, then a deliberate deletion pass — you chose "copy everything, then strip" over "copy selectively"), clone the `muhindomubaraka` MySQL database into a brand-new `muhindo_app` database (you chose independence over sharing a live DB with muhindomubaraka), reconcile the schema down to a single-tenant foundation, then build two new modules (LMS, Client/Project Management) plus a database-backed portfolio CMS on top of it.

---

## 1. Target information architecture

```
Public (no login)                    /
  ├─ Home (hero, about, stats)       ← from muhindomubaraka's config/portfolio.php + amout-muhindo.md
  ├─ Projects showcase + /projects/{slug}
  ├─ Skills / Research / Products
  ├─ Contact (persists to contact_messages, was broken in muhindomubaraka — fix here)
  ├─ Course catalogue (new) + course checkout (Flutterwave, reused from true-doctor)
  └─ /login  (single login form for every role, like true-doctor's /admin/login)

Authenticated — role-driven dashboard (reuses true-doctor's DashboardService pattern)
  ├─ owner / admin   → full back office: portfolio CMS, courses, students, clients, projects,
  │                     invoices/payments, users & roles, settings
  ├─ student         → "My Courses": enrolled courses, lesson player, progress, materials, certificates
  └─ client          → "My Projects": project status, timeline/updates, documents, invoices
```

One Laravel app, one login, three experiences gated by role — this is structurally identical to what true-doctor already does (`super_admin` down to `records_officer`, one login, per-role dashboard view). You're just swapping the role list and the domain models behind it.

---

## 2. Tech stack (inherited from true-doctor, confirmed suitable — no changes needed)

| Layer | Choice | Why keep it |
|---|---|---|
| Framework | Laravel 12, PHP 8.2 | Current, both source projects already on it |
| Views | Blade + Livewire v3 (tables/forms) + Alpine.js | No SPA build complexity; matches both source projects |
| CSS | Tailwind 3 + `td-admin.css` design system | Already a clean, licence-free, hand-built admin skin — reskin colors to navy/gold, don't rebuild |
| Auth | Session auth (Breeze-derived) for web, Sanctum for API | Reuse as-is |
| RBAC | spatie/laravel-permission | Reuse as-is, redefine roles |
| Audit | spatie/laravel-activitylog | Reuse as-is — useful for both client project history and student progress audit |
| Payments | Flutterwave (`FlutterwaveGateway` behind `PaymentGateway` interface) | Reuse as-is for course checkout + client invoices |
| PDF/QR | DomPDF + simple-qrcode | Reuse for invoices/receipts and course completion certificates |
| Queue/cache | Redis + Horizon | Optional at your scale — keep config, don't have to run Horizon locally |
| Mobile (future) | muhindomubaraka's Flutter skeleton (`mobile/`) | Not part of this phase; revisit once the web app + API are stable |

No new packages are required to hit v1. `spatie/laravel-medialibrary` (mentioned in muhindomubaraka's platform plan but never installed) is worth adding in Phase 9 for course video/material uploads and project documents — flagged there, not before.

---

## 3. Database plan

### 3.1 Strategy
Clone `muhindomubaraka` (MySQL, MAMP instance, socket `/Applications/MAMP/tmp/mysql/mysql.sock`) into a new database `muhindo_app`. muhindo-app gets an independent copy seeded with your real content; `muhindomubaraka` keeps running untouched as-is. All new/changed schema happens only in `muhindo_app` from that point on.

### 3.2 What happens to each table group

**A. Foundation — keep, migrate as-is (from either project, they're nearly identical)**
`users`, `password_reset_tokens`, `sessions`, `cache`, `jobs`, `personal_access_tokens` (Sanctum), `permissions`/`roles`/`model_has_permissions`/`model_has_roles`/`role_has_permissions` (Spatie), `activity_log`, `settings`, `notifications`, `device_tokens`.

**B. Billing — keep from true-doctor, repurpose**
`invoices`, `invoice_items`, `payments`, `gateway_logs` — reused for both course purchases and client project invoices. Drop the SaaS-specific `plans`/`subscriptions`/`subscription_payments` triad (that modeled *hospitals* subscribing to *you*; not your shape) unless you later want a retainer/subscription billing model for clients — defer, don't build now.

**C. HMS domain — drop entirely**
Everything patient/clinical/hospital-ops shaped: `hospitals`, `patients`*, `departments`, `rooms`, `staff_profiles`, `doctor_schedules`, `appointments`*, `consultations`*, `prescriptions`, `dose_items`*, `services`, `medical_services`, `stock_*`, `dispensations`*, `lab_*`, `radiology_*`, `treatment_records`*, `wards`, `beds`, `admissions`, `bed_transfers`, `nursing_notes`, `vital_rounds`, `medication_administrations`, `insurance_*`, `financial_years`. None of it is single-tenant-portfolio shaped and none of it survives the copy.

**D. From muhindomubaraka — keep, useful as-is**
`contact_messages`, `newsletter_subscribers`, `districts` (Uganda reference list — useful for client address fields).

**E. From muhindomubaraka — keep as design reference, rebuild under new names**
`clients` / `legal_cases` / `case_officers` / `case_notes` / `documents` → becomes the Client/Project Management module (§5). The shape (status/stage/priority enums, officer assignment, notes, confidential documents) is good; the domain words ("legal_cases", "court tracking") aren't. Rebuild rather than rename-in-place so you're not carrying dead legal-specific columns (`court_name`, `police_station`, etc.).

**F. From muhindomubaraka — drop entirely**
Trading simulator (`trading_*`, `live_account_*`), dating/social platform (`intro_requests`, `consents`, `likes`, `matches`, `conversations`, `blocks`, `reports`, `profile_views`, dating-facet `users` columns), DevRoots LMS remnants (`students`, `courses`, `instructors`, `enrollments`, `payments`(course), `chats`, `messages`) — these are a *different, unrelated* course platform's tables; your new LMS (§4) replaces them cleanly. Also drop the trading-course `education_categories/articles/progress` tables.

**G. New — build from scratch**
LMS tables (§4) and Client/Project Management tables (§5), plus a small portfolio-CMS set (§6).

### 3.3 Clone commands (MAMP MySQL, verified working: root/root over the MAMP socket)

```bash
# 1. Create the new database
/Applications/MAMP/Library/bin/mysql80/bin/mysql -uroot -proot \
  -S /Applications/MAMP/tmp/mysql/mysql.sock \
  -e "CREATE DATABASE IF NOT EXISTS muhindo_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Dump muhindomubaraka (schema + data)
/Applications/MAMP/Library/bin/mysql80/bin/mysqldump -uroot -proot \
  -S /Applications/MAMP/tmp/mysql/mysql.sock \
  --routines --triggers --single-transaction \
  muhindomubaraka > /Applications/MAMP/htdocs/muhindo-app/_db_seed/muhindomubaraka_dump.sql

# 3. Import into muhindo_app
/Applications/MAMP/Library/bin/mysql80/bin/mysql -uroot -proot \
  -S /Applications/MAMP/tmp/mysql/mysql.sock \
  muhindo_app < /Applications/MAMP/htdocs/muhindo-app/_db_seed/muhindomubaraka_dump.sql
```

(Adjust `-uroot -proot` if your MAMP root password differs from the default. `_db_seed/` should be `.gitignore`d — it's a one-time transplant artifact, not a migration.)

After this, `muhindo_app` has *all* of muhindomubaraka's tables (including the ones slated for removal in §3.2.F/C). That's fine — Phase 6 (below) removes them via real Laravel migrations so the change is tracked and reversible, instead of hand-editing a cloned dump.

---

## 4. New module: LMS

Models / migrations to create fresh in muhindo-app:

- `courses` — title, slug, description, cover_image, price, currency, level (enum), category, is_published, created_by
- `course_modules` — course_id, title, sort_order
- `lessons` — course_module_id, title, slug, body (long text / rich content), video_url or file path, duration_minutes, sort_order, is_free_preview
- `lesson_materials` — lesson_id, title, file_path, type (pdf/zip/link/etc.)
- `enrollments` — user_id, course_id, status (enum: pending/active/completed/cancelled), source (self-checkout/admin-added), enrolled_at, completed_at
- `lesson_progress` — enrollment_id, lesson_id, completed_at, watch_seconds
- `certificates` — enrollment_id, certificate_no, issued_at, pdf_path *(reuse true-doctor's `QrService` + DomPDF certificate pattern from `_legacy/app/Services/CertificateService.php` — salvage that one file even though the rest of `_legacy/` is dead)*

Flows:
- **Admin**: create/publish courses, build modules → lessons, upload materials, manually enroll a student, view all students' progress.
- **Public checkout**: visitor picks a course → registers/logs in → pays via Flutterwave (reuse `GatewayPaymentService`/`FlutterwaveGateway`/`Invoice`/`Payment` as-is, same pattern true-doctor uses for hospital subscription checkout) → `enrollments` row created on payment success.
- **Student**: "My Courses" dashboard widget → course player (sequential or free-navigation lessons) → progress bar → certificate download on 100%.

---

## 5. New module: Client & Project Management

Rebuilt from muhindomubaraka's legal-case schema, generalized:

- `clients` — client_number (unique, auto), name, email, phone, company, address, district_id, notes, photo, created_by
- `projects` — project_number (unique), title, description, client_id, category, status (enum: proposal/active/on_hold/completed/cancelled), priority (enum), start_date, due_date, completed_date, budget, currency, created_by
- `project_team` — project_id, user_id, role (owner/collaborator) *(only meaningful once you have staff; fine to have just yourself for now)*
- `project_tasks` — project_id, title, description, status (todo/doing/done), due_date, completed_at, assigned_to, sort_order — this table also backs **your own personal task list** (§7) via a nullable `project_id`
- `project_notes` — project_id, user_id, note, is_client_visible *(mirrors `case_notes.is_private`, inverted — default private, opt-in visible to client)*
- `project_updates` — project_id, user_id, update_text, percent_complete, created_at — the client-visible progress log (this is the "track their learning/progress" mechanism from your brief, applied to project work)
- `project_documents` — project_id, title, category, file_path/name/size/mime, is_confidential, uploaded_by

Flows:
- **Admin (you)**: create client → create project(s) under them → log tasks/notes/updates/documents as work happens → mark milestones done.
- **Client**: logs in → sees only their own project(s) (policy-scoped, same pattern as true-doctor's `Policies/` per-resource authorization) → timeline of `project_updates`, task checklist (read-only), documents, invoices/payment status.
- **Billing**: `projects` can generate `invoices` (reuse true-doctor's Invoice/InvoiceItem/Payment/Flutterwave machinery exactly as it does for hospital billing).

---

## 6. New: Portfolio CMS (replaces muhindomubaraka's config-file-only approach)

muhindomubaraka deliberately kept portfolio content in `config/portfolio.php` because it *had no admin panel* to edit it through ("DB-free public site" was a stated design goal). muhindo-app **does** have a real admin now (inherited from true-doctor) — so move content into the database and build simple CRUD screens for it, seeded once from `config/portfolio.php` / `amout-muhindo.md`:

- `settings` (reuse true-doctor's existing key-value table) — identity/hero text, about paragraph, stats, contact details, social links, navy/gold theme tokens
- `portfolio_projects` — title, slug, description, tags (json), highlights (json), cover_image, external_link, is_featured, sort_order
- `skills` — name, category (e.g. Backend/Frontend/Mobile/DevOps), proficiency, sort_order
- `experiences` — company, role, start_date, end_date, description, sort_order
- `education` — institution, degree, field, start_date, end_date, description, sort_order
- `services` — title, description, icon, sort_order

Seed once from the content already fully catalogued in muhindomubaraka's `amout-muhindo.md` (identity, 9+ years experience, MSc Makerere research, ULITS/School Dynamics/Hospital Management System/Wildlife Offenders DB/etc. flagship projects, full skills list, "Learn It With Muhindo" brand, navy-and-gold design intent) — that document is your seeder's source data, don't re-type it from memory.

---

## 7. Personal ops (you managing yourself)

No new table needed — `project_tasks` (§5) with a nullable `project_id` and a `client_id = null` doubles as your personal to-do/goal tracker, visible only on your own admin dashboard widget ("My Tasks", unscoped from any client). This avoids building a second parallel task system.

---

## 8. Roles & access

Replace true-doctor's HMS role list (`hospital_admin, doctor, nurse, receptionist, ...`) with:

| Role | Access |
|---|---|
| `super_admin` (you) | Everything: portfolio CMS, courses, clients/projects, billing, users/roles, settings |
| `admin` (future staff, optional) | Same as above minus user/role management — Spatie permission-gated like true-doctor already does |
| `student` | Own enrollments/courses/progress/certificates only |
| `client` | Own project(s)/updates/documents/invoices only |

Reuse true-doctor's exact mechanism: `IsAdmin` middleware pattern (rename/generalize), Spatie roles+permissions, one `/login` route for all roles, `DashboardController` + a `DashboardService`-style per-role widget map (swap HMS widgets for LMS/PM widgets — e.g. student sees "courses in progress / next lesson / certificates earned"; client sees "active projects / open tasks / last update / balance due"; admin/owner sees the aggregate KPIs across both).

Drop: `ResolveHospital`, `EnsureSubscribed`, `RequireOnboarding`, `RequirePasswordChange` can stay (still useful, generic), multi-tenancy trait `BelongsToHospital`/`HospitalScope` (you're single-tenant — delete).

---

## 9. Step-by-step execution

### Phase 0 — Prerequisites
- [ ] Confirm PHP 8.2, Composer, Node/npm, MAMP MySQL running (already confirmed: `mysql80`, socket live, `root/root` works)
- [ ] `mkdir -p /Applications/MAMP/htdocs/muhindo-app/_db_seed` (gitignored scratch area for the DB dump)

### Phase 1 — Copy the codebase
```bash
rsync -a --exclude='.git' --exclude='node_modules' --exclude='vendor' \
  --exclude='storage/logs/*' --exclude='.phpunit.result.cache' \
  /Applications/MAMP/htdocs/true-doctor/ /Applications/MAMP/htdocs/muhindo-app/
```
Full copy, as decided — HMS-specific code gets deleted in Phase 5, not filtered now.

### Phase 2 — Fresh install & sanity boot
```bash
cd /Applications/MAMP/htdocs/muhindo-app
composer install
cp .env.example .env
php artisan key:generate
npm install
```
Edit `.env`: `APP_NAME="Muhindo Mubaraka"`, `APP_URL=http://localhost/muhindo-app` (or your local host), `DB_DATABASE=muhindo_app`, `DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock`, `DB_USERNAME=root`, `DB_PASSWORD=root`. Leave `FLW_*` blank until you have real/sandbox Flutterwave keys; leave `AT_*` (Africa's Talking SMS) blank/unused — you likely don't need SMS/USSD for this app, revisit in Phase 15 whether to strip that integration too.

### Phase 3 — Database clone
Run the three commands in §3.3. Verify:
```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -uroot -proot -S /Applications/MAMP/tmp/mysql/mysql.sock \
  muhindo_app -e "SHOW TABLES;"
```
You should see muhindomubaraka's full table list (68 tables) inside `muhindo_app`.

### Phase 4 — Baseline check
```bash
php artisan migrate:status
```
Expect a mismatch (the cloned DB has muhindomubaraka's migration history, not true-doctor's). Don't run `migrate` yet — reconcile first (Phase 6), otherwise Laravel will try to re-run true-doctor's HMS migrations against tables that don't match.

### Phase 5 — Strip HMS-specific code from the copied codebase
Delete (full list derived from the true-doctor analysis):
- `app/Models/{Hospital,Plan,Subscription,SubscriptionPayment,Patient,PatientCard,CardRecord,PatientDependent,PatientDocument,PatientInsurance,Department,Room,StaffProfile,DoctorSchedule,Appointment,AppointmentStatusHistory,Consultation,ConsultationStatusHistory,Prescription,DoseItem,DoseItemRecord,Service,MedicalService,StockCategory,StockItem,StockMovement,Dispensation,DispensationItem,LabTest,LabOrder,LabOrderItem,RadiologyStudy,RadiologyOrder,RadiologyOrderItem,TreatmentRecord,TreatmentRecordItem,Ward,Bed,Admission,BedTransfer,NursingNote,VitalRound,MedicationAdministration,InsuranceProvider,InsuranceClaim,FinancialYear}.php`
- `app/Models/Concerns/BelongsToHospital.php`, `app/Models/Scopes/HospitalScope.php`
- `app/Http/Controllers/Admin/*` — delete every HMS controller (Patients, Appointments, Consultations, Invoices *(keep — repurpose)*, Payments *(keep)*, Stock*, LabOrders, RadiologyOrders, Admissions, Beds, Wards, Nursing, Insurance*, FinancialYears, Onboarding, Theme, DeviceToken *(keep)*, GatewayPayment *(keep)*, SubscriptionCheckout, StaffProfile, DoctorSchedule, Department, Room, Service, LabTest, RadiologyStudy) — **keep** `DashboardController`, `SettingsController`, `UsersController`
- `app/Http/Controllers/Api/V1/*` — delete `PatientController`, `AppointmentController`, `ConsultationController`, `LabOrderController`, `StockItemController` — keep `AuthController`, `InvoiceController` (repurpose), `OpenApiController`
- `app/Http/Controllers/Super/*` — delete entirely (SaaS-central multi-tenant admin, not needed)
- `app/Services/*` — delete `PatientService, AppointmentService, ConsultationService, DispensationService, StockService, LabService, RadiologyService, TreatmentService, AdmissionService, InsuranceService, FinancialYearService, PrescriptionService, DosageScheduleGenerator, CardService, RegistrationService, SubscriptionService, SubscriptionCheckoutService` — **keep** `BillingService`, `DocumentService`, `QrService`, `ReportService`, `AvatarService`, all of `Services/Gateway/*`, `Services/Channels/*` (or drop Channels if dropping SMS)
- `app/Enums/*` — delete HMS-status enums (`AppointmentStatus, ConsultationStatus, AdmissionStatus, LabOrderStatus, RadiologyOrderStatus, ClaimStatus, SubscriptionStatus, HospitalStatus, BillingCycle, BedStatus, RoomStatus, RoomType, DoseSlot, DoseRecordStatus, MedicationAdminStatus, StockMovementReason, PatientStatus, PatientSex, CardStatus, CardEntryType, FinancialYearStatus, ResultFlag, Weekday`) — keep `InvoiceStatus`, `PaymentMethod`, `ApiErrorCode`; add new enums for `CourseLevel`, `EnrollmentStatus`, `ProjectStatus`, `ProjectPriority`, `TaskStatus`
- `app/Policies/*` — delete all HMS resource policies; keep the *pattern*, write new ones for `Project`, `Client`, `Course`, `Enrollment`
- `app/Http/Middleware/{ResolveHospital,EnsureSubscribed}.php` — delete
- `app/Livewire/*` — delete all HMS `Index`/`Form` components and `Super/*`; keep the `WithTable` concern (generic, reusable)
- `resources/views/admin/{patients,appointments,consultations,stock*,lab*,radiology*,wards,beds,admissions,nursing,insurance*,financial-years,departments,rooms,doctor-schedules,services}/*` and `resources/views/admin/dashboard/roles/*` (rebuild role dashboards for the new role list) — delete
- `resources/views/pdf/{patient-card,lab-result,radiology-report,discharge-summary}.blade.php` — delete; keep `invoice.blade.php`, `receipt.blade.php` as templates
- `database/migrations/*` — delete every HMS-domain migration file (see §3.2.C list); **keep** the foundation ones (§3.2.A) and billing ones you're repurposing (§3.2.B, drop `plans/subscriptions/subscription_payments` unless wanted)
- `database/migrations/archive/` — delete outright, it's already-dead schema evidence from true-doctor's own prior pivots, not needed
- `_legacy/` — delete, **except** salvage `_legacy/app/Services/CertificateService.php` and `_legacy/app/Services/QrService.php` into `app/Services/` first (referenced in §4 for course certificates) if `app/Services/QrService.php` doesn't already cover it — check for overlap before copying both
- `config/tenancy.php`, `config/trading.php`, `config/onboarding.php` (unless keeping onboarding flow) — delete
- Root docs: delete `HMS_PLAN.md`, `ADMIN_UI_PLAN.md` *(skim first — the design-system section is useful reference, but the plan itself is HMS-specific)*, `DASHBOARD_PLAN.md` *(same — skim for the widget-catalogue pattern, then delete)*, `ONYX_LEGAL_PLAN.md`, `ARCHITECTURE.md`, `PLAN.md`, `AGENT_COMMAND.md`, `FRONTEND_AUDIT.md`, `docs/` (all HMS feature docs + `docs/trading/`), `ssh.txt` (check contents — may contain true-doctor's actual server creds, do not carry into a new repo), `googlefe4fcb0c9b8eddc9.html` (true-doctor's Google verification file, not yours), `logo-horizontal.png`/`logo-square.png` (true-doctor branding — replace with your own)
- `resources/js/courses.js` — delete (dead leftover, confirmed unreferenced)
- `routes/`: strip `web.php` down to marketing routes + `/login` + `auth.php` include; delete the entire `/admin/*` HMS route block (patients/appointments/... ) and the whole `/super/*` group; delete `api.php`'s patient/appointment/consultation/stock/lab routes

Keep untouched: `app/Models/User.php` (extend, don't replace), `app/Http/Controllers/Auth/*`, `routes/auth.php`, `app/Http/Middleware/{IsAdmin,SetLocale,RequirePasswordChange}.php`, `app/Support/{Money,Settings,ApiResponse,VerificationCode→repurpose for course/project numbering,Dashboard/DashboardService}.php`, `resources/views/layouts/*`, `public/css/td-admin.css`, `resources/views/components/dash/*`, `lang/`.

### Phase 6 — Reconcile migrations
1. Rename the copied migration files' intent mentally to muhindo_app's domain (don't rename filenames/timestamps — Laravel doesn't care, but keep chronological order sane for new ones).
2. Since `muhindo_app` was seeded via raw SQL dump (Phase 3), not via `migrate`, its `migrations` table reflects muhindomubaraka's history, not true-doctor's. Two clean options:
   - **(Recommended) Reset migration tracking**: `php artisan migrate:fresh` is *not* safe here (it'd drop your just-cloned data). Instead, manually curate the `migrations` table: truncate it, then run `php artisan migrate --pretend` against the surviving (post-Phase-5) migration set to see what Laravel *thinks* needs running, and cross-check by hand against `SHOW TABLES` — mark migrations whose table already exists (from the clone) as already-run by inserting matching rows into `migrations`, then run `php artisan migrate` for genuinely new ones (LMS/PM/portfolio-CMS tables from §4/§5/§6).
   - **(Simpler, slightly lossy) Rebuild clean**: export just the *data* you care about (users, contact_messages, and the portfolio content) from the clone via `php artisan tinker` or a one-off seeder script, drop `muhindo_app`, recreate it empty, run `php artisan migrate` fresh against the curated Phase-5 migration set (this builds a clean schema from true-doctor's foundation migrations), then re-import the preserved data. Cleaner long-term, more manual work now.
3. Write new migrations for §4 (LMS), §5 (Client/Project), §6 (Portfolio CMS) using `php artisan make:migration`.
4. `php artisan migrate` until clean, `php artisan migrate:status` all green.

### Phase 7 — Users & roles
- [ ] Update `app/Models/User.php`: drop HMS relations/`STAFF_ROLES`, add `student`/`client` role constants, keep Spatie `HasRoles`
- [ ] Seed roles/permissions: `super_admin`, `admin`, `student`, `client` + granular permissions per module
- [ ] Seed yourself as `super_admin`
- [ ] Decide: self-registration open for students (course checkout flow) but **not** for clients (you create client accounts manually when you onboard a project) — matches "I onboard them" in your brief

### Phase 8 — Public portfolio site
- [ ] Build `PortfolioController` (adapt from muhindomubaraka's, same controller shape) reading from new DB tables (§6) instead of `config/portfolio.php`
- [ ] Port `resources/views/portfolio/{home,project}.blade.php` from muhindomubaraka, reskin onto true-doctor's marketing layout conventions
- [ ] Fix the contact form to persist into `contact_messages` (known bug in the source — fix it here, add an admin inbox view)
- [ ] Build a seeder that reads the content catalogued in §6 (sourced from `amout-muhindo.md`) into the new tables
- [ ] Add course catalogue page + course checkout flow (§4)

### Phase 9 — Admin/back-office rebuild
- [ ] Reskin `td-admin.css` colors from medical-blue to navy-and-gold (muhindomubaraka's stated brand identity)
- [ ] Build CRUD (controller + Livewire `Index` + Blade views, following true-doctor's existing per-module pattern exactly) for: Portfolio content (§6), Courses/Modules/Lessons/Materials, Clients/Projects/Tasks/Notes/Documents, Invoices/Payments (reuse largely as-is)
- [ ] Rebuild `resources/views/admin/dashboard/roles/*` for the new 4-role list, using `DashboardService`'s existing widget-catalogue mechanism with new widgets (see §8 table)
- [ ] Install `spatie/laravel-medialibrary` for course video/material and project document uploads (flagged in §2)

### Phase 10 — Student & client portals
- [ ] Student: "My Courses" view, lesson player, progress bar, certificate download (DomPDF + QrService)
- [ ] Client: "My Projects" view, timeline of `project_updates`, task checklist, documents, invoice/payment status
- [ ] Authorization policies: a student/client can only ever see their own records — write `ProjectPolicy`, `EnrollmentPolicy` etc. mirroring true-doctor's existing per-resource policy pattern

### Phase 11 — Billing wiring
- [ ] Point `FlutterwaveGateway`/`GatewayPaymentService` at course checkout (new) and project invoices (adapted from HMS billing flow)
- [ ] Get real (or sandbox) Flutterwave keys, fill `.env`

### Phase 12 — Cleanup & polish
- [ ] `composer.json`/`package.json`: rename to `muhindo-app`, update description
- [ ] Replace true-doctor's logo assets with your own branding
- [ ] Write a fresh `README.md` for muhindo-app (don't carry true-doctor's HMS-flavored one)
- [ ] Remove `.env.example` leftovers for HMS-only keys (`AT_*` if SMS not needed, `SUBSCRIPTION_GRACE_DAYS`)
- [ ] `git init` in muhindo-app (currently no VCS in either source project — start clean history here)

### Phase 13 — Testing
- [ ] Delete HMS feature tests inherited from true-doctor's `tests/`
- [ ] Write feature tests per new module: course enrollment happy-path, project visibility (client A can't see client B's project), payment webhook handling
- [ ] `./vendor/bin/pint`, `./vendor/bin/phpstan analyse` (config already present, just re-point at surviving code), `php artisan test`

### Phase 14 — Local smoke test checklist
- [ ] `npm run build` (or `npm run dev`), `php artisan serve` (or via MAMP vhost)
- [ ] Visit `/` — portfolio renders from DB, not config file
- [ ] Submit contact form — row appears in `contact_messages`, admin inbox shows it
- [ ] Log in as `super_admin` — dashboard shows portfolio/course/client KPIs, not HMS ones
- [ ] Create a course, enroll a test student, verify progress tracking + certificate
- [ ] Create a client + project, log an update, verify the client-scoped portal shows only that project
- [ ] Run a Flutterwave sandbox payment end-to-end for a course purchase

### Phase 15 — Deploy considerations (when ready, not part of this pass)
- [ ] Production `.env` (real Flutterwave keys, mail, `APP_ENV=production`, `APP_DEBUG=false`)
- [ ] `php artisan storage:link`, queue worker if using Horizon, HTTPS, backups for `muhindo_app`

---

## 10. Open items to decide as you go (not blocking, revisit when you get there)

- Keep or drop SMS/Africa's Talking integration entirely (currently unused in your target feature set)
- Whether `admin` (non-owner staff) role is needed now or only `super_admin` for v1
- Retainer/subscription billing for ongoing clients (deferred `plans/subscriptions` triad) vs. one-off project invoices only
- Course content hosting: self-hosted video files vs. YouTube/Vimeo embeds (your existing "Learn It With Muhindo" YouTube channel may make embeds the pragmatic v1 choice over building video infrastructure)
