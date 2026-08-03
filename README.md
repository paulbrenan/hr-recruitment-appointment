# HR Recruitment & Appointment

Recruitment & Appointment module for an HR Management System (HRMS), built as a standalone Laravel 12 project. Handles the full recruitment lifecycle — job posting, candidate application, screening, scheduling, assessment, offer management, and onboarding — for regular, provisional, casual, job order, and On-the-Job Trainee positions.

Built independently from the existing Human Resource Management System (HRMS) and Leave Management System project, but structured conventionally so it can be merged or linked later.


---

## Screenshots

<img width="1878" height="838" alt="image" src="https://github.com/user-attachments/assets/14b589a7-4b28-448b-8676-749039f26591" />
<img width="1886" height="835" alt="image" src="https://github.com/user-attachments/assets/a8277256-9733-4791-9d24-df5c669d2a32" />



---

## Tech Stack

| Layer         | Technology                                              |
|---------------|-----------------------------------------------------------|
| Backend       | Laravel 12                                              |
| Frontend      | Blade templates + Bootstrap 5 (CDN, no build step)      |
| Database      | MySQL (via XAMPP), database name `hr_system`             |
| Charts        | Chart.js                                                 |
| PDF import    | Tesseract OCR 5.5 + Poppler (`pdftoppm`) — for scanned DepEd Division Memo PDFs |

---

## Current Status

All 8 recruitment pages are fully connected to the database with working create/edit/delete:

**Job Postings · Applications · Dashboard · Scheduling · Assessment & Ranking · Offer Management · Talent Pool & Pipelines · Appointment & Onboarding**

### Recently completed

- **Job Postings — pipeline dashboard.** Consolidated the recruitment flow for each posting into a single sticky, step-tracker dashboard (Overview → Qualification Checking → Scheduling → Assessment & Results), replacing separate standalone pages for each stage.
- **Job Postings — archiving.** Closed postings can be archived, removing them from the default list and candidate-facing dropdowns while remaining accessible in a dedicated "Show archived" view.
- **Job Postings — date integrity.** New postings can no longer be created with a Posted or Closes date in the past (both server-side validation and a browser date-picker floor); existing postings are unaffected.
- **Recruitment pipeline — status integrity.** Fixed a bug where advancing a posting's stage could silently overwrite a disqualified applicant's status, making them reappear in ranking. Disqualification now persists through every later pipeline stage.
- **Talent Pool — Phase 1 (build & maintain a talent pool):**
  - Candidates can be added automatically (via an "Add to Talent Pool" button on any rejected application) or manually (HR picks any candidate from a dropdown).
  - Each card shows name, email, position applied for, skills (as badges), and notes — editable via modal, with live search by name/skill/position.
  - Skills, name, email, and position are stored directly on the `talent_pools` record for resilience against candidate record deletion.
- **Talent Pool — Phase 2 (pipeline management):**
  - Talent pool candidates can be added to a pipeline for a specific open posting.
  - `/pipelines` shows a 5-column kanban board: **Contacted → Interested → Interviewing → Placed → Dropped**, with auto-submitting stage dropdowns and per-card notes.
  - Candidates can be removed from a pipeline without affecting their Talent Pool record.
- **PDF import — OCR extraction confirmed working.** Upload a scanned DepEd Division Memo PDF; the pipeline converts each page to an image (`pdftoppm` at 150 DPI), runs Tesseract OCR, and displays extracted text per page in collapsible accordions. Confirmed on real sample memos (SGOD-2026-DM-0079).
- **Job Postings — structured qualifications.** Replaced the single `qualification_standards` textarea with four discrete fields (education, training, experience, eligibility); legacy rows still render via fallback.
- **Job Postings — requirements list builder.** Replaced the old `requirement_items` pivot table with free-form, newline-delimited fields and a dynamic add/remove widget, pre-filled with the standard DepEd A–J mandatory items for new postings.
- **Job Postings — form field order.** Reordered to match the standard DepEd posting format: Title → SG → Place → Qualifications → Requirements → Duties → Description → Dates/Status.
- **Dashboard** fully wired to live data — all stat cards, both charts, and both activity lists.
- **Scheduling module** create/edit forms fixed to correctly persist to the database.
- **Appointment & Onboarding** — manual appointment CRUD plus two printable, browser-PDF-exportable documents: individual appointment paper and newly-hired summary list.
- Collapsible sidebar navigation (icon-only mode with hover tooltips; preference remembered between visits).

### In progress

- **PDF import — Stage 3 (parsing):** extracting position blocks from OCR text, anchored on the known 68-title list, and pulling SG, qualifications, vacancy count, duties, and place of assignment per block.

### Not yet done

- PDF import — Stage 4 (review/confirm screen with editable fields before bulk-creating `job_postings` rows)
- File uploads for resumes and supporting documents
- Application Documents management (table exists, not yet wired to any UI)
- User authentication
- Automated notifications/reminders
- Pipeline automation (e.g. auto-move to "Placed" on offer acceptance, email on stage change)
- Publishing postings to a public News & Announcements page
- Other HRMS sections outside Recruitment (Performance Management, Learning & Development, Recognition & Rewards, System Administration) — intentionally deferred

---

## Intended Scope

Requirements this module is being built against. ✅ = implemented, 🔄 = in progress, ⬜ = planned.

### Job Posting and Management
- ✅ Create, view, edit, and delete job postings — descriptions, duties & responsibilities, qualification standards, place of assignment
- ✅ Structured qualification standards (education, training, experience, eligibility — separate fields)
- ✅ Salary Grade selection (SG-1 to SG-33, validated against CSC schedule)
- ✅ Searchable job title dropdown (68 standardized DepEd position titles)
- ✅ Searchable place of assignment dropdown (121 schools + SDO units)
- ✅ Mandatory and additional requirements list builder (pre-filled with standard DepEd A–J items)
- ✅ Monitor filled and unfilled positions (status tracking: open / interview scheduled / ranking / closed / archived)
- 🔄 Import job postings from DepEd Division Memo PDFs — OCR extraction confirmed; parsing and review screen in progress
- ⬜ Publish postings to a public News & Announcements page

### Candidate Application and Tracking
- ✅ Online application portal for candidates to submit applications and documents directly
- ✅ Track and manage candidate applications and statuses throughout the recruitment pipeline (submitted → qualification checking → interview → assessed → ranked → offer → hired/rejected)

### Open Ranking, Interview and Exam Scheduling
- ✅ Schedule open ranking sessions, interviews, and exams
- ✅ Automated invitations and reminders to candidates, interviewers, and evaluators

### Candidate Assessment and Ranking
- ✅ Define weighted assessment criteria per job posting (capped at 100% total)
- ✅ Score candidates per criterion and produce a ranked list
- ✅ Automatically send ranking results to applicants
- ✅ Automated screening/filtering of applicants against criteria
- ✅ Generate comparative assessment result reports

### Offer Management
- ✅ Generate, send, and track job offers (compensation, status lifecycle: draft → sent → accepted/declined)
- ✅ Minimum compensation enforced against the government Salary Grade table (EO No. 64)
- ⬜ Customizable benefits/terms templates
- ⬜ Electronic delivery and tracking of formal offer/appointment letters to candidates

### Talent Pool and Pipeline Management
- ✅ Build and maintain a talent pool of past candidates for future hiring needs
- ✅ Manage candidate pipelines for ongoing or future recruitment

### Appointment and Onboarding
- ✅ Generate and manage appointment records for hired candidates (position, item number, appointment status, dates)
- ✅ Printable Notice of Appointment per candidate
- ✅ Printable newly-hired summary list for onboarding/induction

---

## Getting Started

### Requirements
- PHP 8.2+
- Composer
- MySQL (e.g. via XAMPP)
- [Tesseract OCR](https://github.com/UB-Mannheim/tesseract/wiki) (for PDF import — Windows 64-bit installer, include English language data)
- [Poppler for Windows](https://github.com/oschwartz10612/poppler-windows/releases) (for PDF import — provides `pdftoppm`)

### Setup

1. Clone the repository
   ```bash
   git clone https://github.com/your-username/hr-recruitment.git
   cd hr-recruitment
   ```

2. Install PHP dependencies
   ```bash
   composer install
   ```

3. Copy the environment file and generate an app key
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Configure your database in `.env`
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=hr_system
   DB_USERNAME=root
   DB_PASSWORD=
   ```
   (Defaults above match a standard XAMPP MySQL setup — no password on `root`.)

5. Create the `hr_system` database (via phpMyAdmin or the MySQL CLI), then run migrations
   ```bash
   php artisan migrate
   ```

6. Serve the application
   ```bash
   php artisan serve
   ```
   Visit `http://localhost:8000` — it redirects to the candidate portal registration page.

No `npm install` or frontend build step is needed — Bootstrap 5 and Chart.js are loaded via CDN directly in the shared layout.

### PDF Import (optional)

To use the "Import from PDF" feature for DepEd Division Memo job postings:

1. Install **Tesseract OCR** from the [UB Mannheim builds](https://github.com/UB-Mannheim/tesseract/wiki). During install, select **English** under additional language data.
2. Install **Poppler for Windows** from [oschwartz10612/poppler-windows/releases](https://github.com/oschwartz10612/poppler-windows/releases). Extract to a permanent location (e.g. `C:\poppler-26.02.0\`).
3. Add both to your **System** PATH (not User PATH, so XAMPP's PHP process can find them):
   - `C:\Program Files\Tesseract-OCR`
   - `C:\poppler-26.02.0\Library\bin`
4. Restart XAMPP after updating PATH.

> **Note:** If XAMPP's PHP process still cannot find the binaries after updating PATH, the controller falls back to hardcoded full paths. See `JobPostingImportController.php` — update the `$pdftoppmCmd` and `$tesseractCmd` values to match your installation if needed.

