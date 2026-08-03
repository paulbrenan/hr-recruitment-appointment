# HR Recruitment & Appointment

Recruitment & Appointment module for an HR Management System (HRMS), built as a standalone Laravel 12 project. Handles the full recruitment lifecycle — job posting, candidate application, screening, scheduling, assessment, orientation scheduling, and onboarding — for regular, provisional, casual, job order, and On-the-Job Trainee positions.

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

## Features

What the module actually does, by area.

### Job Posting and Management

Job postings can be created, viewed, edited, and deleted, each carrying a description, duties & responsibilities, qualification standards, and place of assignment. Qualification standards are captured as four separate fields — Education, Training, Experience, and Eligibility — instead of one free-text block. Salary Grade is selected from SG-1 to SG-33 and validated against the imported CSC schedule, and the job title itself comes from a searchable dropdown of 68 standardized DepEd position titles. Place of assignment is likewise a searchable dropdown covering 121 schools and SDO units. Mandatory and additional requirements are built with a list builder pre-filled with the standard DepEd A–J items. Every posting's status (open, interview scheduled, ranking, closed, archived) doubles as a way to monitor which positions are filled and which are still open.

Job postings can also be imported directly from scanned DepEd Division Memo PDFs via OCR, with each detected position landing on a review screen for HR to confirm or correct before it's created.

### Candidate Application and Tracking

Candidates submit applications and supporting documents through an online application portal. From there, every application is tracked through a defined pipeline — submitted, qualification checking, interview, assessed, ranked, and hired or rejected — so HR always knows where a given candidate stands.

### Open Ranking, Interview and Exam Scheduling

Open ranking sessions, interviews, and exams can all be scheduled, with automated invitations and reminders going out to candidates, interviewers, and evaluators alike.

### Candidate Assessment and Ranking

Each job posting gets its own set of weighted assessment criteria, capped at a 100% total. Candidates are scored against those criteria to produce a ranked list, and applicants are automatically screened and filtered against the criteria as scoring happens. Ranking results can be sent to applicants automatically, and comparative assessment result reports can be generated straight from the ranking data.

### Orientation Scheduling

Offer Management has been replaced by Orientation Scheduling — instead of a separate draft/send/accept/decline offer flow, HR schedules an orientation directly with a ranked applicant: a date, time, and place, set right from the same ranking table used earlier in the pipeline rather than a dedicated screen.

That ranking table doesn't lock once a posting is closed or archived, either — HR can always come back to it to schedule an orientation for a different applicant on the list, for instance if the original hire backs out, or another vacancy on the same posting still needs to be filled.

### Talent Pool and Pipeline Management

Past candidates can be kept in a talent pool for future hiring needs, and moved through their own dedicated pipelines for ongoing or future recruitment efforts.

### Appointment and Onboarding

Appointment records — position, item number, appointment status, and dates — are generated and managed for every hired candidate, with printable outputs for both an individual Notice of Appointment and a newly-hired summary list for onboarding and induction.

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
