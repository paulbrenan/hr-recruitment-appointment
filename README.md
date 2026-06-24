Building the Recruitment & Appointment module of an HRMS, as a new, separate Laravel 12 project called hr-recruitment,

Stack: Blade + Bootstrap 5 (via CDN, no npm), MySQL via XAMPP, database name hr_system. 

Summary of all finished tasks and features to date:


Project Setup



Set up a new, separate Laravel 12 project (hr-recruitment) with its own database (hr_system), kept apart from the existing Leave Management System project for now but built conventionally so it can be merged.



Database & Models



Created and ran 10 database migrations: job postings, candidates, applications, application documents, interview schedules, assessment criteria, candidate assessments, job offers, talent pools, and appointments.
Built all 10 corresponding Eloquent models with proper relationships between them (e.g., an application links to a candidate, a job posting, its documents, schedules, assessments, an offer, and an appointment).



Layout & Navigation



Built a shared layout used by all pages: full-width header with the system title and a back button, plus a sidebar for navigation.
Added a collapsible sidebar — can shrink to icons-only to give pages more screen space, shows tooltips on hover when collapsed, and remembers the user's preference between page visits. Fixed alignment so the collapse button lines up neatly with the navigation icons.



Job Postings Module



Built full create, view, edit, and delete functionality for job postings.
Added validation rules (e.g., closing date cannot be earlier than the posting date).
Fixed date fields so they display and save correctly.
Added delete confirmation and success messages.
Added 12 sample job postings for testing.



Applications Module



Built the applications list and detail pages, pulling real candidate and job posting information.
Added a working status filter (e.g., view only "Shortlisted" or "Hired" applicants).
Added the ability to update an application's status and notes directly from its detail page.
Added delete functionality (with a warning that deleting an application also removes any linked documents, schedules, assessments, offers, or appointments).
Added 12 sample candidates and 12 sample applications across all application statuses for testing.



Dashboard



Connected all dashboard statistics to real data: open job postings, total applications, pending offers, and interviews scheduled this week.
Built a 6-month activity chart showing applications received and job postings opened.
Built a chart showing the breakdown of applications by status.
Connected the "Recent Applications" and "Upcoming Interviews & Exams" lists to real data.
The dashboard no longer uses any placeholder/sample numbers — everything reflects the actual database.



Scheduling Module (Interviews & Exams)



Fixed the "New Schedule" form so it actually saves to the database (it previously didn't work).
Added a proper "Edit Schedule" feature, since there was previously no way to update a schedule after creating it (e.g., marking it completed, cancelled, or adding remarks).
Added delete functionality for schedules.
Added 10 sample interview/exam schedules covering all types and statuses for testing.



Assessment & Ranking Module



Built a posting-specific assessment system where HR can add scoring criteria (e.g., Communication Skills, Technical Knowledge) with assigned weight percentages.
Added a safeguard so total weight per posting cannot exceed 100%, with the system auto-suggesting the remaining percentage.
Built a ranked candidates table showing scores per criterion and overall rank.
Added working forms for adding criteria and entering/editing scores.
Added sample assessment data for testing.



Job Offers Module



Built the full offer lifecycle: generating an offer, sending it, and recording whether the candidate accepted or declined.
Linked offer status changes to automatically update the related application's status.
Enforced a minimum compensation amount based on the government Salary Grade table (Executive Order No. 64), both on the form and on the server side.
Prevented duplicate offers from being created for the same application.
Added delete functionality for offers.



Appointment & Onboarding Module



Built the appointments list showing appointed candidates, their position, item number, status, and appointment/onboarding dates.
Added a "New Appointment" form where HR can appoint a candidate who has accepted a job offer, entering the position, item number, status, and dates manually.
Added an edit feature to update any appointment's details later (useful since item numbers and onboarding dates are often finalized after the initial appointment).
Added delete functionality.
Built two printable documents: an individual "Notice of Appointment" paper per candidate, and a "Newly-Hired Summary" list of all appointees for onboarding/induction — both can be printed or saved as PDF directly from the browser.
Added sample data for testing (including applicants ready to be appointed).



Version Control



Initialized Git in the project and set up a .gitignore to exclude unnecessary files (cache, backups, environment settings).
Created a GitHub repository and pushed the full project for backup and version tracking.


Current Status: 7 of 8 recruitment pages are fully connected to the database and working end-to-end (Job Postings, Applications, Dashboard, Scheduling, Assessment & Ranking, Offer Management, Appointment & Onboarding). Only the Talent Pool page still uses sample data.

Not Yet Done:


Talent Pool page (still using sample data).
File uploads for resumes and other documents.
Viewing/managing application documents (table exists but isn't connected yet).
User login/authentication (not yet implemented).
Other HRMS sections outside Recruitment (e.g., Performance Management, Learning & Development) — intentionally deferred.


Next Steps: Connect the Talent Pool page to real data; look into file upload functionality for resumes and documents.


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
