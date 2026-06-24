Building the Recruitment & Appointment module of an HRMS, as a new, separate Laravel 12 project called hr-recruitment,

Stack: Blade + Bootstrap 5 (via CDN, no npm), MySQL via XAMPP, database name hr_system. 

Tasks accomplished:


Finished wiring the Appointment & Onboarding page to the real database. Replaced the original auto-generate flow with a manual "New Appointment" form (HR selects an applicant with an accepted offer and fills in position, item number, status, and dates). Added an edit modal, delete function, and two printable views (individual Appointment Paper and a Newly-Hired Summary list) using the browser's built-in print-to-PDF feature.
Connected the two remaining placeholder values on the Dashboard ("Pending Offers" and "Interviews This Week") to real data now that the Offers and Scheduling modules are complete. The Dashboard no longer uses any sample/placeholder data.
Improved the sidebar navigation: added a collapse/expand toggle so it can shrink to icons-only, giving more screen space to page content. Collapsed state shows tooltips on hover and is remembered across page visits. Fixed alignment so the toggle button lines up with the navigation icons.
Set up version control for the project and pushed the full codebase to a GitHub repository for backup and tracking.


Status: 7 of 8 recruitment pages are now fully connected to the database (Job Postings, Applications, Dashboard, Scheduling, Assessment & Ranking, Offer Management, Appointment & Onboarding). Only the Talent Pool page still uses sample data.

Next steps: Wire the Talent Pool page to real data; explore file upload functionality for resumes and documents.

Recruitment & Appointment

This will handle the recruitment process, appointment, and onboarding of employees (regular, provisional, casual, job orders including On-the-Job Trainees).

•	Job Posting and Management
(a)	Monitor filled and unfilled positions.
(b)	Create and manage job postings, including details about the job descriptions, duties and responsibilities, qualification standards, and place of assignment (will be posted on News and Announcement).

•	Candidate application and Tracking
(a)	Online application portal for candidates to submit required documents.
(b)	Track and manage candidate applications and statuses throughout the recruitment process. 

•	Open Ranking, Interview and Exam Scheduling
(a)	Schedule open ranking, interviews, examination and send automated invitations and reminders to candidates and interviewers and evaluators.

•	Candidate Assessment and Ranking
(a)	Automatically parse and categorize candidate resumes.
(b)	Implement screening criteria to filter and prioritize applicants.
(c)	Evaluate candidates using predefined assessment criteria and ranking systems.
(d)	Automatically send results of ranking to applicants
(e)	Generate Comparative Assessment Results

•	Offer Management
(a)	Generate and customize job offers, including compensation, benefits, and other terms.
(b)	Electronically deliver and track offer acceptance (letter to qualified applicants for appointment).

•	Talent Pool and Pipeline Management
(a)	Build and maintain a talent pool for future hiring needs
(b)	Manage candidate pipelines for ongoing or future recruitment

•	Appointment and Onboarding
(a)	Generate appointment papers of qualified applicants
(b)	Generate summary list of newly-hired employees for onboarding/induction
