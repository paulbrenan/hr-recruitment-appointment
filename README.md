Here's the updated README — select all, delete, paste, save:

```markdown
# HR Recruitment & Appointment

Recruitment & Appointment module for an HR Management System (HRMS), built as a standalone Laravel 12 project. Handles the recruitment process, appointment, and onboarding of employees (regular, provisional, casual, job order, and On-the-Job Trainees).

Built independently from the existing Human Resource Management System (HRMS) and Leave Management System project, but structured conventionally so it can be merged or linked later.

## Tech Stack

- **Backend:** Laravel 12
- **Frontend:** Blade templates + Bootstrap 5 (via CDN, no npm/build step)
- **Database:** MySQL (via XAMPP), database name `hr_system`
- **Charts:** Chart.js
- **PDF import:** Tesseract OCR 5.5 + Poppler (`pdftoppm`) — for scanned DepEd Division Memo PDFs

## Intended Scope

This module is being built against the following requirements. Items already implemented are marked ✅; everything else is planned but not yet built.

### Documentation:
https://docs.google.com/document/d/1Sr5fsCJcqEC29AfBEqkGtpiUxWYWlPcL17MIvnnCJ6M/edit?usp=sharing

### Job Posting and Management
- ✅ Create, view, edit, and delete job postings — descriptions, duties & responsibilities, qualification standards, place of assignment
- ✅ Structured qualification standards (education, training, experience, eligibility — separate fields)
- ✅ Salary Grade selection (SG-1 to SG-33, validated against CSC schedule)
- ✅ Searchable job title dropdown (68 standardized DepEd position titles)
- ✅ Searchable place of assignment dropdown (121 schools + SDO units)
- ✅ Mandatory and additional requirements list builder (pre-filled with standard DepEd A–J items)
- ✅ Monitor filled and unfilled positions (status tracking: draft / open / filled / closed)
- 🔄 Import job postings from DepEd Division Memo PDFs — OCR extraction confirmed working (Tesseract + pdftoppm); parsing and review screen in progress
- ⬜ Publish postings to a public News & Announcements page

### Candidate Application and Tracking
- ⬜ Online application portal for candidates to submit applications and documents directly
- ✅ Track and manage candidate applications and statuses throughout the recruitment pipeline (submitted → screening → shortlisted → interview → assessed → ranked → offer → hired/rejected)

### Open Ranking, Interview and Exam Scheduling
- ✅ Schedule open ranking sessions, interviews, and exams
- ⬜ Automated invitations and reminders to candidates, interviewers, and evaluators

### Candidate Assessment and Ranking
- ✅ Define weighted assessment criteria per job posting (capped at 100% total)
- ✅ Score candidates per criterion and produce a ranked list
- ⬜ Automatic resume parsing and categorization
- ⬜ Automated screening/filtering of applicants against criteria
- ⬜ Automatically send ranking results to applicants
- ⬜ Generate comparative assessment result reports

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

## Current Status

All 8 recruitment pages are fully connected to the database with working create/edit/delete: **Job Postings, Applications, Dashboard, Scheduling, Assessment & Ranking, Offer Management, Talent Pool & Pipelines, and Appointment & Onboarding.**

### Recently completed

- **Talent Pool — Phase 1 (Build & maintain a talent pool):**
  - Candidates can be added to the talent pool in two ways: automatically via an "Add to Talent Pool" button that appears on any rejected application, or manually by HR picking any candidate from a dropdown on the Talent Pool page.
  - Each talent pool card shows the candidate's name, email, position applied for, skills (comma-separated, displayed as badges), and notes.
  - HR can edit skills and notes via a modal, search the pool live by name/skill/position, and remove candidates.
  - Skills, name, email, and position are stored directly on the `talent_pools` record (not read through the candidate relation) for resilience against candidate record deletion.

- **Talent Pool — Phase 2 (Pipeline management):**
  - HR can add any talent pool candidate to a pipeline for a specific open job posting directly from their card.
  - Pipelines page (`/pipelines`) shows a 5-column kanban-style board: **Contacted → Interested → Interviewing → Placed → Dropped**.
  - Stage can be updated via a dropdown that auto-submits on change.
  - Notes can be added and saved per pipeline card.
  - Candidates can be removed from a pipeline (they remain in the Talent Pool).
  - Pipelines link added to the sidebar navigation.

- **PDF import — OCR extraction confirmed working:** Upload a scanned DepEd Division Memo PDF via the "Import from PDF" button on Job Postings. The pipeline converts each page to an image (`pdftoppm` at 150 DPI) then runs Tesseract OCR, displaying extracted text per page in collapsible accordions. Confirmed readable output on real sample memos (SGOD-2026-DM-0079). Stage 3 (parsing extracted text into position blocks) and Stage 4 (review/confirm screen before database write) are next.
- **Job postings — structured qualifications:** Replaced single `qualification_standards` textarea with four separate fields (education, training, experience, eligibility). Legacy rows display gracefully via fallback.
- **Job postings — requirements list builder:** Replaced old `requirement_items` pivot table system with free-form newline-delimited text fields and a dynamic add/remove widget. New postings pre-fill standard DepEd A–J mandatory items.
- **Job postings — field order rewrite:** Form fields reordered to match standard DepEd posting format (Title → SG → Place → Qualifications → Requirements → Duties → Description → Dates/Status).
- **Job postings — index and show page improvements:** SG column added to index table; show page displays structured qualifications and parsed requirement lists.
- Dashboard fully wired to real data — all stat cards, both charts, and both activity lists pull from the live database.
- Scheduling module's create/edit forms fixed to actually persist to the database.
- Appointment & Onboarding: manual appointment creation, editing, deletion, and two printable documents (individual appointment paper + newly-hired summary), both exportable as PDF via the browser's print dialog.
- Collapsible sidebar navigation (icon-only mode with hover tooltips, preference remembered between visits).

### Not yet done
- PDF import Stage 3 — parse OCR text into position blocks (anchored on known 68-title list), extract SG, qualifications, vacancy count, duties, and place of assignment per block
- PDF import Stage 4 — review/confirm screen with editable fields and checkboxes before bulk-creating `job_postings` rows
- File uploads for resumes and supporting documents
- Application Documents management (table exists, not yet connected to any UI)
- User authentication
- Online candidate-facing application portal
- Automated notifications/reminders
- Pipeline automation (e.g. auto-move to "placed" when a job offer is accepted, email notifications on stage change)
- Other HRMS sections outside Recruitment (Performance Management, Learning & Development, Recognition & Rewards, System Administration) — intentionally deferred

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
   ```
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

> **Note:** If XAMPP's PHP process still cannot find the binaries after updating PATH, the controller uses hardcoded full paths as a fallback. See `JobPostingImportController.php` — update the `$pdftoppmCmd` and `$tesseractCmd` paths to match your installation if needed.

### Notes
- There is currently no authentication — every page is publicly accessible. This is intentional for now; auth is on the not-yet-done list.
- `php artisan db:show` may error on some XAMPP MySQL setups due to a missing `intl` PHP extension. Use `php artisan tinker` with `DB::select('SHOW COLUMNS FROM table_name')` instead to inspect tables.
- The PDF import feature requires a 5-minute execution time limit (`set_time_limit(300)` in `public/index.php`) due to the time Tesseract takes to OCR multi-page scanned documents. This is already set in the codebase — no manual change needed.
```

Three things updated from your original:
1. Talent Pool and Pipeline Management changed from `⬜` to `✅` in the scope list.
2. Current status updated — all 8 pages now fully connected, with detailed bullet points for Phase 1 and Phase 2.
3. Pipeline automation added to the "not yet done" list, and the `db:show` note updated to reflect the real workaround you used today.
