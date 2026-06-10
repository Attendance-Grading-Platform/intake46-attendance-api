

ITI — Attendance & Grading PlatformRequirements Specification v1.0
Page 1 of 15
## INFORMATION TECHNOLOGY INSTITUTE
Full-Stack Web Development — Laravel & Vue.js
## Attendance & Grading Platform
## Project Requirements Specification
Capstone Project  |  Team of 4–5  |  Full Stack
## Version 1.0

ITI — Attendance & Grading PlatformRequirements Specification v1.0
Page 2 of 15
## 1. Project Overview
This document specifies the requirements for an Attendance & Grading Platform serving a
single ITI branch that runs multiple training tracks (for example, Web Development and Mobile
Development) in parallel. Each track runs one active cohort at a time.
The platform is a full-stack web application. The backend is a Laravel REST API and the
frontend is a Vue 3 single-page application that consumes it. The two are developed and graded
with equal weight.
The system manages the full operational loop of a cohort: opening the cohort, assigning staff,
scheduling teaching engagements, recording per-session attendance, grading students,
surfacing analytics to each role, and forwarding billable instructor hours to central accounting.
## 1.1 Goals
•Provide a single source of truth for attendance and grades across all tracks in the
branch.
•Give each role a dashboard scoped to exactly what they are allowed to see.
•Automate grade computation, including normalization and a standalone attendance
ledger.
•Automate billable-hour calculation from the session schedule and forward it to central
accounting.
•Flag at-risk students early, before the cohort ends.
1.2 Out of Scope
•Multi-branch operation. The system serves one branch only.
•Public self-registration. All accounts are provisioned top-down (see Section 9).
•Payment processing. The platform produces billing data and forwards it to central
accounting; it does not pay anyone.
## 1.3 Glossary
TermMeaning
BranchThe single ITI location the system serves.
TrackA discipline within the branch, e.g. Web or Mobile.
CohortOne intake of a track (e.g. “Web — Intake 45”). One active cohort per track.
CourseA graded subject inside a cohort, scored out of 100.
## Engagement
A teaching booking (lecture, lab, or business session) assigned to an
instructor.
Lab groupA subset of a cohort (~15 students) assigned to one lab instructor.
Attendance ledgerA standalone per-student point balance starting at 250.

ITI — Attendance & Grading PlatformRequirements Specification v1.0
Page 3 of 15
## 2. Roles & Access Model
The system distinguishes a person's access profile (what they can see and do) from their
engagements (sessions they are booked to teach) and their compensation type (how they are
paid). These are deliberately decoupled.
Key design principle
A role is not the same as a teaching seat. A person with an administrative role can also hold teaching
engagements across different tracks. For example, a Track Admin for Web may also be booked to
deliver lab sessions for Mobile. Their access stays tied to their role; their pay gains an hourly
component for the sessions they deliver.
## 2.1 Roles
RoleScopeCore responsibilities
Branch ManagerWhole branch
Creates cohorts; assigns Track Admins; views executive
analytics and the branch billing rollup.
## Track Admin
Their track's full
cohort roster
Configures courses, grade weights, lab groups;
schedules engagements and assigns instructors;
approves excuses; enters final exam/project grades;
overrides instructor evaluations; posts announcements
anytime.
## Instructor
Only their assigned
lab group(s)
Takes attendance; grades their own group; adds student
tags/notes; posts announcements during their active
engagement window only.
StudentTheir own data only
Views grades and attendance; reads announcements;
submits assignments; requests excuses.
## 2.2 Compensation Types
TypeFormula
External instructorPure hourly. Paid only for delivered session hours.
Track Admin (internal)Fixed salary + (delivered session hours × hourly rate).
All delivered hours are calculated automatically from the schedule and forwarded to central accounting
## (see Section 7).
## 2.3 Visibility Rules
ACC-1  The Branch Manager shall see branch-wide aggregated analytics with drill-down into
any track and cohort.
ACC-2  A Track Admin shall see the full roster and all grades for their own track's active
cohort at any time.

ITI — Attendance & Grading PlatformRequirements Specification v1.0
Page 4 of 15
ACC-3  An Instructor shall see only the students in the lab group(s) they are assigned to. An
instructor assigned to groups 1 and 2 shall not see group 3.
ACC-4  A Student shall see only their own grades, attendance, and submissions. No peer
data is visible to students.
ACC-5  Student tags/notes shall be visible to every person who grades that student, internal
or external (see Section 6.5).

ITI — Attendance & Grading PlatformRequirements Specification v1.0
Page 5 of 15
## 3. Cohort Lifecycle
A cohort moves through the following stages. Each stage is driven by a specific role.
1.Open cohort. The Branch Manager creates a cohort for a track and assigns one or
more Track Admins.
2.Configure. The Track Admin sets up courses and their grade weights, creates lab
groups, schedules engagements, and assigns/hires instructors.
3.Deliver. Instructors deliver sessions, record attendance, and grade their lab groups. The
Track Admin enters final exam/project grades.
4.Participate. Students attend (scan in/out), submit deliverables, request excuses, and
view their grades and announcements.
5.Roll up. Billable hours flow to central accounting; performance metrics flow to the
Branch Manager dashboard.
LC-1  A track shall have at most one active cohort at any time.
LC-2  Only the Branch Manager shall create cohorts and assign Track Admins.

ITI — Attendance & Grading PlatformRequirements Specification v1.0
Page 6 of 15
## 4. Engagements & Scheduling
An engagement is a teaching booking with a type, a date range, scheduled session hours, and
an assigned instructor. The Track Admin creates engagements; the system uses their
scheduled hours to compute billing.
## 4.1 Engagement Types
TypeAttendanceDeliverableNotes
LectureRequiredNoneStandard taught session.
LabRequired
Worth 10 points;
MANDATORY for the
student
Track splits into 2–3 lab groups of ~15;
each group's instructor grades only that
group. Late penalty applies (see 4.2).
## Business
session
Tracked per
track
## None
Cross-track: students from multiple
tracks (e.g. Web + Mobile) may attend
the same session. Attendance is
recorded per track.
## 4.2 Lab Deliverable Penalty
ENG-1  A lab deliverable shall be worth 10 points and shall be optional for the student.
ENG-2  Each full day late shall deduct 25% of the deliverable's total. After 4 full days late the
deliverable score reaches 0.
## Example:
A deliverable scored 10/10 submitted 2 days late: 10 − (2 × 25% × 10) = 5 points.
The same deliverable submitted 4 days late: 10 − (4 × 25% × 10) = 0 points.
## 4.3 Scheduling Rules
ENG-3  Each engagement shall record its type, assigned instructor, date range, and
scheduled hours per session.
ENG-4  A person may hold multiple engagements across different tracks within their active
window.
ENG-5  An instructor's account access shall be limited to their engagement's date range (see
Section 9 on account expiry).

ITI — Attendance & Grading PlatformRequirements Specification v1.0
Page 7 of 15
## 5. Attendance
Attendance is recorded per session as two timestamps: an “arrived at” scan-in and a “left at”
scan-out. The scanner interface is intentionally minimal: scan, show arrived-at, scan again,
show left-at.
## 5.1 Check-in Mechanism
ATT-1  The system shall support QR-code check-in and check-out, recording a timestamp for
each.
ATT-2  The scanner interface shall be a single, dead-simple screen optimized for fast
repeated scanning.
ATT-3  Business-session attendance shall be recorded per track even when multiple tracks
attend the same session.
BONUS  NFC check-in via the Web NFC API. Web NFC is supported only on Chrome for
Android, so any NFC implementation must fall back to QR on unsupported devices. This is
optional and earns bonus marks.
## 5.2 Attendance Ledger
Each student has one standalone attendance ledger that spans the whole program. It is a points
balance, not a percentage, and it is added to the grand total as-is (see Section 6.1).
EventEffect on ledger
Starting balance250 points
Unexcused absence (any session type)−25 points
Excused absence (Track Admin approved)−5 points
ATT-4  Every student's ledger shall start at 250 points.
ATT-5  An unexcused absence shall deduct 25 points; an approved excused absence shall
deduct 5 points.
ATT-6  Missing any session type, including business sessions, shall affect the same single
ledger.
## 5.3 Excuse Workflow
An absence becomes “excused” only after a Track Admin approves the student's request. The
workflow is a simple state machine.
requested → approved  |  rejected
EXC-1  A Student shall submit an excuse request with a reason and an optional attachment.

ITI — Attendance & Grading PlatformRequirements Specification v1.0
Page 8 of 15
EXC-2  An attachment shall be no larger than 1 MB and limited to PDF or image formats. The
system shall validate size and type on upload.
EXC-3  A Track Admin shall approve or reject each request. Approval changes the deduction
from 25 to 5; rejection leaves it at 25.

ITI — Attendance & Grading PlatformRequirements Specification v1.0
Page 9 of 15
## 6. Grading
## 6.1 Grade Architecture
Grading is two-tier. The attendance ledger stands on its own and is added directly to the grand
total. Every course is scored out of 100 and contributes its own score.
Grand total formula
Grand Total  =  Attendance Ledger (out of 250)  +  Sum of Course Scores (each out of 100)
The attendance ledger is NOT folded into any course. It is a separate line item added as-is.
6.2 Within a Course (out of 100)
Each course's 100 points are split by weight across its components —
- lab deliverables
- final exam/project. The Track Admin sets these weights once per cohort.
GRD-1  A course shall be scored out of 100, split by weight across its graded components.
GRD-2  Component weights shall be set once per cohort by the Track Admin.
## 6.3 Normalization
A component's raw maximum rarely equals the points it is worth in the course. The system
normalizes the student's raw score onto the component's weight:
componentScore = (rawScore ÷ rawMax) × componentWeight
## Example
The assignments component is worth 40 points of the course's 100.
Assignments were graded out of a raw maximum of 70.
A student scoring 67/70 raw earns: (67 ÷ 70) × 40 = 38.3 points toward the course.
GRD-3  The system shall normalize each component's raw score onto its configured weight
using the formula above.
## 6.4 Lab Groups, Final Grade & Overrides
A track of roughly 45 students splits into 2–3 lab groups of about 15. Each group's instructor
grades only their own students. As an example, all assignments of a course might be worth 40
of the 100, while the final exam/project carries the rest.
GRD-4  Each lab instructor shall grade only the students in their assigned group.
GRD-5  The Track Admin shall enter the final exam/project grade for each course.
GRD-6  A Track Admin shall be able to override an instructor's evaluation. An override shall
require a mandatory note explaining it, and the original value shall be retained for audit.

ITI — Attendance & Grading PlatformRequirements Specification v1.0
Page 10 of 15
## 6.5 Student Tags & Notes
Tags and notes accumulate on a student across courses and follow them through the cohort.
They give later instructors context from earlier ones.
GRD-7  The system shall support both predefined tags (e.g. “uses AI”, “Cheating”, “loves
extra work”) and free-text notes on a student.
GRD-8  Tags and notes shall be accumulative across courses and visible to everyone who
grades that student, internal or external.

ITI — Attendance & Grading PlatformRequirements Specification v1.0
Page 11 of 15
## 7. Billing & Central Accounting
Billing is derived from the schedule, not entered by hand. The system totals each person's
delivered session hours and forwards the result to central accounting.
BIL-1  The system shall auto-calculate billable hours per person from their scheduled and
delivered sessions.
BIL-2  External instructors shall be billed purely on delivered hours; internal Track Admins
shall be billed for hours on top of their fixed salary.
BIL-3  The system shall produce a consolidated billing rollup forwarded to central accounting.
BIL-4  The Branch Manager shall see the billing rollup, including the split between internal
and external hours, within their dashboard.

ITI — Attendance & Grading PlatformRequirements Specification v1.0
Page 12 of 15
## 8. Student Portal & Announcements
## 8.1 Student Portal
POR-1  A Student shall view their attendance ledger and their per-course score breakdown
by component.
POR-2  A Student shall view their own attendance record session by session.
POR-3  A Student shall view their own progress over time. No peer comparison shall be
shown.
POR-4  A Student shall submit assignments either as a URL (e.g. a repository or drive link) or
as a direct file upload. Both methods shall be supported.
POR-5  A Student shall submit and track excuse requests (see Section 5.3).
## 8.2 Announcements
Announcements are article-style posts shown in the student's feed.
ANN-1  A Track Admin shall post announcements to their cohort at any time.
ANN-2  An Instructor shall post announcements only during their active engagement window.
ANN-3  Announcements shall support article-style content and appear in the student feed.

ITI — Attendance & Grading PlatformRequirements Specification v1.0
Page 13 of 15
## 9. Accounts, Authentication & Security
SEC-1  There shall be no public self-registration. All accounts are provisioned top-down:
Branch Manager → Track Admins → instructors and students.
SEC-2  Every account shall carry an expiry date. Expired accounts shall be unable to log in
(e.g. an external instructor's account expires at the end of their contract; student accounts
expire at cohort end).
SEC-3  The API shall enforce role- and scope-based authorization on every endpoint,
matching the visibility rules in Section 2.3. Authorization shall be enforced server-side, never
only in the Vue client.
SEC-4  File uploads (excuse attachments, assignment files) shall be validated for size and
type and stored safely minio(s3 combatable storage is a bonus).

ITI — Attendance & Grading PlatformRequirements Specification v1.0
Page 14 of 15
## 10. Analytics & Dashboards
Each role gets a dashboard scoped to its visibility. The two highlighted analytics below are
where teams can differentiate and earn bonus marks.
10.1 At-Risk Rule
A student is flagged at-risk when either condition holds:
•Attendance ledger falls below 150 points, OR
•Any single course grade falls below 60.
ANL-1  The system shall flag at-risk students using the rule above and surface them in the
relevant dashboards.
10.2 Dashboards by Role
RoleDashboard contents
## Branch Manager
Cross-track comparison (attendance %, average grade, pass/dropout rate);
cohort attendance trend over time; billing/cost view (total hours, internal vs
external split, cost-per-student); drill-down track → cohort.
## Track Admin
Cohort grade distribution; lab-group grader-consistency check; attendance per
session and per student; deliverable submission status; tag-flagged and at-risk
students.
## Instructor
Their group's grade distribution; submission tracker (late/missing); their own
delivered hours.
## Student
Own grade breakdown by component; own attendance record; own progress
over time.
10.3 Highlighted (Bonus) Analytics
Grader-consistency analysis. Surface whether one lab instructor grades systematically harder
or more leniently than another — for example, group 3's mean sitting several points below
groups 1 and 2 before normalization. This helps the Track Admin spot inconsistent grading.
At-risk detection. Beyond the flat rule in 10.1, teams may add early-warning signals such as
attendance trend slope or a sudden drop between courses.

ITI — Attendance & Grading PlatformRequirements Specification v1.0
Page 15 of 15
## 11. Technical Requirements & Deliverables
## 11.1 Stack
•Backend: Laravel REST API, with database migrations, seeders, and validation.
•Frontend: Vue 3 single-page application consuming the API.
•Auth: Token-based authentication with server-side, role-scoped authorization.
Laravel and Vue are weighted equally in evaluation.
## 11.2 Expected Deliverables
6.A working full-stack application implementing all mandatory requirements in this
document.
7.Database schema with migrations and seed data sufficient to demonstrate every role
and flow.
8.API documentation (e.g. a Postman collection or OpenAPI spec).
9.A short README covering setup, seeded test accounts for each role, and any
assumptions made.
10.A live demo walking through each role's experience end to end.
## 11.3 Team
This is a team project for 4–5 members. Teams are expected to divide work across the backend
API, the Vue client, and the cross-cutting concerns (auth, analytics, attendance scanning), and
to integrate continuously rather than at the end.
11.4 Mandatory vs. Bonus
Bonus itemWhere
NFC check-in (Web NFC) with QR fallbackSection 5.1
Grader-consistency analyticsSection 10.3
Advanced at-risk early-warning signalsSection 10.3
A note on requirement IDs
Every requirement in this document is tagged (ACC-, ATT-, GRD-, and so on). Use these IDs in your
commit messages and branch names (BONUS)