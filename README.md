# HR Recruitment & Appointment

Recruitment & Appointment module for an HR Management System (HRMS), built as a standalone Laravel 12 project. Handles the recruitment process, appointment, and onboarding of employees (regular, provisional, casual, job order, and On-the-Job Trainees).

Built independently from the existing Human Resource Management System (HRMS) and Leave Management System project, but structured conventionally so it can be merged or linked later.

## Tech Stack

- **Backend:** Laravel 12
- **Frontend:** Blade templates + Bootstrap 5 (via CDN, no npm/build step)
- **Database:** MySQL (via XAMPP), database name `hr_system`
- **Charts:** Chart.js

## Intended Scope

This module is being built against the following requirements. Items already implemented are marked ✅; everything else is planned but not yet built.

### Job Posting and Management
- ✅ Create, view, edit, and delete job postings — descriptions, duties & responsibilities, qualification standards, place of assignment
- ✅ Monitor filled and unfilled positions (status tracking: draft / open / filled / closed)
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
- ⬜ Build and maintain a talent pool of past candidates for future hiring needs
- ⬜ Manage candidate pipelines for ongoing or future recruitment

### Appointment and Onboarding
- ✅ Generate and manage appointment records for hired candidates (position, item number, appointment status, dates)
- ✅ Printable Notice of Appointment per candidate
- ✅ Printable newly-hired summary list for onboarding/induction

## Current Status

7 of 8 recruitment pages are fully connected to the database with working create/edit/delete: **Job Postings, Applications, Dashboard, Scheduling, Assessment & Ranking, Offer Management, and Appointment & Onboarding.**

Only the **Talent Pool** page still uses sample data.

### Recently completed
- Dashboard fully wired to real data — all stat cards, both charts, and both activity lists pull from the live database (no placeholder numbers remain).
- Scheduling module's create/edit forms fixed to actually persist to the database.
- Appointment & Onboarding: manual appointment creation, editing, deletion, and two printable documents (individual appointment paper + newly-hired summary), both exportable as PDF via the browser's print dialog.
- Collapsible sidebar navigation (icon-only mode with hover tooltips, preference remembered between visits).

### Not yet done
- Talent Pool page (still sample data)
- File uploads for resumes and supporting documents
- Application Documents management (table exists, not yet connected to any UI)
- User authentication
- Online candidate-facing application portal
- Automated notifications/reminders
- Other HRMS sections outside Recruitment (Performance Management, Learning & Development, Recognition & Rewards, System Administration) — intentionally deferred

## Getting Started

### Requirements
- PHP 8.2+
- Composer
- MySQL (e.g. via XAMPP)

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

5. Create the hr_system database (via phpMyAdmin or the MySQL CLI), then run migrations
   ```bash
   php artisan migrate
   ```
   
5. Serve the application
   ```bash
   php artisan serve
   ```
   Visit `http://localhost:8000` — it redirects to the Dashboard.

No `npm install` or frontend build step is needed — Bootstrap 5 and Chart.js are loaded via CDN directly in the shared layout.

### Notes
- There is currently no authentication — every page is publicly accessible. This is intentional for now; auth is on the not-yet-done list.
- `php artisan db:show` may error on some XAMPP MySQL setups due to a `performance_schema` permissions issue. Use `php artisan db:table {table_name}` instead to inspect individual tables.


