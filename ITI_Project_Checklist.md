# ITI Attendance & Grading Platform — Full Project Checklist & Rules

> **Stack:** Laravel REST API (Backend) + Vue 3 SPA (Frontend) | Team of 4–5

---

## Table of Contents

1. [Naming Conventions](#1-naming-conventions)
2. [Database Schema Checklist](#2-database-schema-checklist)
3. [Roles & Access Rules](#3-roles--access-rules)
4. [Cohort Lifecycle Checklist](#4-cohort-lifecycle-checklist)
5. [Engagements & Scheduling Checklist](#5-engagements--scheduling-checklist)
6. [Attendance Checklist](#6-attendance-checklist)
7. [Grading Checklist](#7-grading-checklist)
8. [Billing Checklist](#8-billing-checklist)
9. [Student Portal & Announcements Checklist](#9-student-portal--announcements-checklist)
10. [Accounts, Auth & Security Checklist](#10-accounts-auth--security-checklist)
11. [Analytics & Dashboards Checklist](#11-analytics--dashboards-checklist)
12. [API Endpoints Checklist](#12-api-endpoints-checklist)
13. [Deliverables Checklist](#13-deliverables-checklist)
14. [Bonus Features Checklist](#14-bonus-features-checklist)

---

## 1. Naming Conventions

> These are the **exact English names** to use consistently across DB, API, and code.

### Domain Terms (Use As-Is)

| Term | Description | Do NOT call it |
|------|-------------|----------------|
| `branch` | The single ITI location | office, site, center |
| `track` | A discipline (e.g. Web Development, Mobile Development) | department, course |
| `cohort` | One intake of a track (e.g. "Web — Intake 45") | class, batch, group |
| `course` | A graded subject inside a cohort, scored out of 100 | subject, module |
| `engagement` | A teaching booking (lecture/lab/business session) | class, lesson, event |
| `lab_group` | A subset of ~15 students inside a cohort | team, section, division |
| `attendance_ledger` | The standalone 250-point balance per student | attendance score, points |
| `excuse` | An absence justification submitted by a student | justification, appeal |
| `tag` | A predefined label on a student (e.g. "uses AI", "Cheating") | label, badge, flag |
| `note` | A free-text comment on a student | comment, remark |
| `billing_rollup` | Consolidated instructor billing sent to accounting | invoice, payroll |
| `override` | Track Admin changing an instructor's grade | correction, edit |

### Role Names (Exact, Consistent)

| Role | DB/Code Value | Display Name |
|------|--------------|--------------|
| Branch Manager | `branch_manager` | Branch Manager |
| Track Admin | `track_admin` | Track Admin |
| Instructor | `instructor` | Instructor |
| Student | `student` | Student |

### Engagement Types (Exact)

| Type | DB/Code Value |
|------|--------------|
| Lecture | `lecture` |
| Lab | `lab` |
| Business Session | `business_session` |

### Compensation Types (Exact)

| Type | DB/Code Value |
|------|--------------|
| External Instructor | `external` |
| Internal Track Admin | `internal` |

### Excuse Status Values (Exact)

| Status | DB/Code Value |
|--------|--------------|
| Requested | `requested` |
| Approved | `approved` |
| Rejected | `rejected` |

### Attendance Event Values (Exact)

| Event | DB/Code Value |
|-------|--------------|
| Scan In | `scan_in` |
| Scan Out | `scan_out` |
| Unexcused Absence | `unexcused` |
| Excused Absence | `excused` |

---

## 2. Database Schema Checklist

### Tables to Create

- [x] `users` — id, name, email, password, role (enum), compensation_type (nullable), hourly_rate (nullable), salary (nullable), expires_at, track_id (nullable FK)
- [x] `tracks` — id, name, branch_id
- [x] `cohorts` — id, track_id (FK), name (e.g. "Web — Intake 45"), status (active/closed), started_at, ended_at
- [x] `courses` — id, cohort_id (FK), name, total_weight (always 100)
- [x] `course_components` — id, course_id (FK), name (lab_deliverables / final_exam_project), weight (must sum to 100 per course)
- [ ] `lab_groups` — id, cohort_id (FK), name, instructor_id (FK → users)
- [ ] `lab_group_students` — lab_group_id (FK), student_id (FK → users)
- [ ] `engagements` — id, cohort_id (FK), type (lecture/lab/business_session), instructor_id (FK → users), date_start, date_end, hours_per_session
- [ ] `sessions` — id, engagement_id (FK), held_on (date), status (delivered/cancelled)
- [ ] `attendance_records` — id, student_id (FK), session_id (FK), scan_in_at (timestamp nullable), scan_out_at (timestamp nullable), status (present/absent)
- [ ] `attendance_ledger` — id, student_id (FK unique), balance (default 250)
- [ ] `ledger_transactions` — id, student_id (FK), session_id (FK), type (unexcused/excused), deduction (-25 or -5), created_at
- [ ] `excuses` — id, student_id (FK), session_id (FK), reason, attachment_path (nullable), status (requested/approved/rejected), reviewed_by (FK → users nullable), reviewed_at
- [ ] `grades` — id, student_id (FK), course_component_id (FK), raw_score, raw_max, normalized_score (computed), graded_by (FK → users), lab_group_id (FK nullable)
- [ ] `grade_overrides` — id, grade_id (FK), original_value, new_value, override_note, overridden_by (FK → users), overridden_at
- [ ] `lab_deliverables` — id, student_id (FK), engagement_id (FK), submitted_at, submission_type (url/file), submission_url (nullable), file_path (nullable), raw_score (nullable), days_late (computed), final_score (computed after penalty)
- [ ] `student_tags` — id, student_id (FK), tag_name, created_by (FK → users), cohort_id (FK)
- [ ] `student_notes` — id, student_id (FK), note_text, created_by (FK → users), cohort_id (FK), created_at
- [ ] `announcements` — id, cohort_id (FK), posted_by (FK → users), title, body, posted_at
- [ ] `billing_entries` — id, user_id (FK), engagement_id (FK), delivered_hours, billed_at
- [ ] `billing_rollups` — id, cohort_id (FK), generated_at, total_internal_hours, total_external_hours, total_cost

### Key Constraints to Enforce in Migrations

- [ ] One active cohort per track at a time (unique partial index or application-level check)
- [ ] Excuse attachment max 1 MB enforced at API level
- [ ] Course component weights must sum to 100 per course (enforce in seeder/service)
- [ ] Attendance ledger starts at exactly 250 for every student
- [ ] `grade_overrides` must always store original value before update

---

## 3. Roles & Access Rules

### Must Implement (from ACC rules)

- [ ] **ACC-1** Branch Manager sees branch-wide analytics with drill-down into any track/cohort
- [ ] **ACC-2** Track Admin sees full roster and all grades for their own track's active cohort only
- [ ] **ACC-3** Instructor sees only students in their assigned lab group(s) — never other groups
- [ ] **ACC-4** Student sees only their own grades, attendance, submissions — zero peer data
- [ ] **ACC-5** Student tags/notes visible to everyone who grades that student (internal and external)

### Critical Role Logic

- [ ] A person's **role** is independent of their **engagements** — a Track Admin for Web can teach labs in Mobile; their access stays scoped to their role
- [ ] An internal Track Admin who teaches sessions earns **salary + hourly** for delivered hours
- [ ] An external instructor earns **hourly only**
- [ ] Authorization is enforced **server-side in Laravel** on every endpoint, never only in Vue

---

## 4. Cohort Lifecycle Checklist

- [x] **LC-1** A track has at most ONE active cohort at any time — enforce this
- [x] **LC-2** Only Branch Manager can create cohorts and assign Track Admins
- [x] Implement cohort stages: Open → Configure → Deliver → Participate → Roll Up
- [x] Branch Manager creates cohort and assigns Track Admin(s)
- [/] Track Admin configures: courses, grade weights, lab groups, engagements, instructors
- [ ] Instructors deliver sessions, record attendance, grade their lab groups
- [ ] Track Admin enters final exam/project grades
- [ ] Students can attend (scan in/out), submit, request excuses, view their data
- [ ] Roll-up: billable hours → accounting; metrics → Branch Manager dashboard

---

## 5. Engagements & Scheduling Checklist

### Engagement Features

- [ ] **ENG-3** Each engagement stores: type, instructor_id, date_start, date_end, hours_per_session
- [ ] **ENG-4** A person can hold multiple engagements across different tracks simultaneously
- [ ] **ENG-5** Instructor account access is limited to their engagement's date range (see Section 10)
- [ ] Lecture: attendance required, no deliverable
- [ ] Lab: attendance required, has 10-point deliverable, splits into 2–3 groups of ~15 students
- [ ] Business Session: attendance tracked per track, cross-track (multiple tracks same session), no deliverable

### Lab Deliverable Penalty Logic

- [ ] **ENG-1** Lab deliverable is worth 10 points
- [ ] **ENG-2** Penalty = 25% of total per full day late
  - Day 1 late: score × 0.75
  - Day 2 late: score × 0.50
  - Day 3 late: score × 0.25
  - Day 4+ late: 0 points (floor at zero)
- [ ] Formula: `final_score = raw_score - (days_late × 0.25 × 10)`, minimum 0
- [ ] Auto-calculate `days_late` from submission timestamp vs. engagement end date
- [ ] Store both `raw_score` and `final_score` (after penalty) in the database

---

## 6. Attendance Checklist

### Check-In Mechanism

- [ ] **ATT-1** QR code check-in AND check-out — record timestamp for each
- [ ] **ATT-2** Scanner interface is a single simple screen optimized for fast repeated scanning
- [ ] **ATT-3** Business session attendance recorded per track even when multiple tracks attend together
- [ ] Generate unique QR code per student per session (or per student globally — document your choice)

### Attendance Ledger

- [ ] **ATT-4** Every new student ledger starts at **250 points** automatically on enrollment
- [ ] **ATT-5** Unexcused absence = **-25 points**; approved excused absence = **-5 points**
- [ ] **ATT-6** All session types (lecture, lab, business session) affect the same single ledger
- [ ] Ledger is additive to Grand Total (not normalized — added as-is)
- [ ] Never fold the ledger into any course score

### Excuse Workflow

- [ ] **EXC-1** Student submits excuse with a reason and optional attachment
- [ ] **EXC-2** Attachment: max **1 MB**, formats: **PDF or image only** — validate both size and type on upload
- [ ] **EXC-3** Track Admin approves → deduction changes from 25 to 5; rejects → stays at 25
- [ ] State machine: `requested → approved | rejected` (no other transitions)
- [ ] Store original deduction and update only on approval
- [ ] Track Admin cannot approve their own excuse (if applicable)

---

## 7. Grading Checklist

### Grand Total Formula

```
Grand Total = Attendance Ledger (out of 250) + Σ Course Scores (each out of 100)
```

- [ ] Ledger is **not** part of any course — it's a separate line item
- [ ] Display Grand Total breakdown clearly in student view (ledger + each course)

### Course Scoring

- [ ] **GRD-1** Every course is scored out of 100
- [ ] **GRD-2** Track Admin sets component weights **once per cohort** — cannot change mid-cohort
- [ ] Components: lab deliverables + final exam/project (weights sum to 100)

### Normalization

- [ ] **GRD-3** Apply normalization formula to every component:
  ```
  componentScore = (rawScore ÷ rawMax) × componentWeight
  ```
- [ ] Example: weight=40, rawMax=70, student raw=67 → (67÷70)×40 = 38.3
- [ ] Store both `raw_score`, `raw_max`, and computed `normalized_score` in `grades` table
- [ ] Round to 1 or 2 decimal places consistently — document your choice

### Lab Groups & Grading Rules

- [ ] **GRD-4** Each lab instructor grades **only their assigned lab group** — enforce at API level
- [ ] **GRD-5** Track Admin enters final exam/project grade for each course (not instructors)
- [ ] Split of ~45 students into 2–3 groups of ~15 enforced or at least documented

### Overrides

- [ ] **GRD-6** Track Admin can override any instructor's grade
- [ ] Override requires a **mandatory note** — cannot override without explanation
- [ ] Store original value in `grade_overrides` table for audit trail
- [ ] Show override indicator in grade views (e.g. "overridden by Track Admin")

### Student Tags & Notes

- [ ] **GRD-7** Support predefined tags: `"uses AI"`, `"Cheating"`, `"loves extra work"` + free-text notes
- [ ] Allow adding custom tags beyond the predefined ones (or lock to predefined — document choice)
- [ ] **GRD-8** Tags and notes accumulate across courses within the cohort
- [ ] Tags and notes visible to **everyone who grades that student** — internal and external instructors

---

## 8. Billing Checklist

- [ ] **BIL-1** Auto-calculate billable hours per person from their scheduled and delivered sessions — no manual entry
- [ ] **BIL-2** External instructors: billed on **delivered hours only**
- [ ] **BIL-2** Internal Track Admins: fixed salary **+ delivered hours × hourly rate**
- [ ] **BIL-3** Produce consolidated billing rollup and forward to central accounting (API call or export)
- [ ] **BIL-4** Branch Manager sees billing rollup in their dashboard including internal vs external split
- [ ] Calculate hours from `sessions` table (delivered sessions only, not cancelled)
- [ ] Store result in `billing_entries` and `billing_rollups` tables
- [ ] Show cost-per-student metric in Branch Manager dashboard

---

## 9. Student Portal & Announcements Checklist

### Student Portal

- [ ] **POR-1** Student views their attendance ledger AND per-course score breakdown by component
- [ ] **POR-2** Student views their own attendance record session by session (date, status, scan times)
- [ ] **POR-3** Student views their own progress over time — **no peer comparison shown ever**
- [ ] **POR-4** Student submits assignments as **URL** (repo/drive link) OR **file upload** — both must work
- [ ] **POR-5** Student can submit excuse requests and track their status (requested / approved / rejected)

### Announcements

- [ ] **ANN-1** Track Admin can post announcements to their cohort **at any time**
- [ ] **ANN-2** Instructor can post announcements **only during their active engagement window**
- [ ] **ANN-3** Announcements are article-style (title + rich body text) and appear in student feed
- [ ] Order announcements newest-first in student feed
- [ ] Instructor posting is blocked by the API if outside their engagement date range

---

## 10. Accounts, Auth & Security Checklist

- [ ] **SEC-1** No public self-registration — all accounts provisioned top-down:
  - Branch Manager creates Track Admin accounts
  - Track Admin creates Instructor and Student accounts
- [ ] **SEC-2** Every account has an `expires_at` field — expired accounts cannot log in
  - External instructor account expires at contract/engagement end
  - Student account expires at cohort end
  - Enforce expiry check on every login and token validation
- [ ] **SEC-3** Role- and scope-based authorization on **every API endpoint** — server-side in Laravel
  - Never rely solely on Vue client to hide/show things
  - Test: each role can only access their permitted endpoints
- [ ] **SEC-4** File uploads (excuse attachments, assignment files):
  - Validate file size (max 1 MB for excuses)
  - Validate file type (PDF or image for excuses)
  - Store safely — never store in publicly accessible path without authorization check
- [ ] Use token-based authentication (Laravel Sanctum or Passport)
- [ ] Hash all passwords with bcrypt
- [ ] Scope API tokens to role permissions

---

## 11. Analytics & Dashboards Checklist

### At-Risk Rule

- [ ] **ANL-1** Flag a student as at-risk when **either** condition is true:
  - Attendance ledger balance < 150 points, OR
  - Any single course grade < 60 points
- [ ] Surface at-risk students in Track Admin and Branch Manager dashboards
- [ ] Show which condition triggered the flag (ledger or which course)

### Branch Manager Dashboard

- [ ] Cross-track comparison: attendance %, average grade, pass/dropout rate per track
- [ ] Cohort attendance trend over time (chart)
- [ ] Billing/cost view: total hours, internal vs external split, cost-per-student
- [ ] Drill-down: branch → track → cohort

### Track Admin Dashboard

- [ ] Cohort grade distribution (histogram/chart)
- [ ] Lab-group grader-consistency check (are groups graded consistently?)
- [ ] Attendance per session and per student
- [ ] Deliverable submission status (submitted / late / missing)
- [ ] Tag-flagged students (e.g. "Cheating")
- [ ] At-risk students list

### Instructor Dashboard

- [ ] Their lab group's grade distribution
- [ ] Submission tracker: late and missing deliverables in their group
- [ ] Their own delivered hours total

### Student Dashboard

- [ ] Own grade breakdown by component (per course)
- [ ] Own attendance record (session by session)
- [ ] Own progress over time (chart)
- [ ] Attendance ledger current balance

---

## 12. API Endpoints Checklist

> Document all of these in Postman or OpenAPI. Use RESTful naming.

### Auth

- [x] `POST /api/auth/login`
- [x] `POST /api/auth/logout`
- [x] `GET  /api/auth/me`

### Users / Account Provisioning

- [ ] `POST /api/users` — Branch Manager creates any account
- [ ] `GET  /api/users` — scoped by role
- [ ] `PUT  /api/users/{id}`
- [ ] `DELETE /api/users/{id}`

### Tracks & Cohorts

- [x] `GET  /api/tracks`
- [x] `POST /api/cohorts` — Branch Manager only
- [x] `GET  /api/cohorts/{id}`
- [x] `PUT  /api/cohorts/{id}`

### Courses & Components

- [x] `POST /api/cohorts/{id}/courses`
- [x] `PUT  /api/courses/{id}`
- [x] `POST /api/courses/{id}/components`
- [x] `PUT  /api/course-components/{id}`

### Lab Groups

- [ ] `POST /api/cohorts/{id}/lab-groups`
- [ ] `POST /api/lab-groups/{id}/students` — assign students to group
- [ ] `DELETE /api/lab-groups/{id}/students/{studentId}`

### Engagements & Sessions

- [ ] `POST /api/cohorts/{id}/engagements`
- [ ] `GET  /api/cohorts/{id}/engagements`
- [ ] `PUT  /api/engagements/{id}`
- [ ] `POST /api/engagements/{id}/sessions` — log a delivered session
- [ ] `GET  /api/engagements/{id}/sessions`

### Attendance

- [ ] `POST /api/sessions/{id}/scan` — QR scan-in or scan-out
- [ ] `GET  /api/sessions/{id}/attendance`
- [ ] `GET  /api/students/{id}/attendance`
- [ ] `GET  /api/students/{id}/ledger`

### Excuses

- [ ] `POST /api/excuses` — student submits
- [ ] `GET  /api/excuses` — Track Admin sees all pending for their cohort
- [ ] `PUT  /api/excuses/{id}/approve`
- [ ] `PUT  /api/excuses/{id}/reject`

### Grades

- [ ] `POST /api/grades` — instructor grades their group
- [ ] `PUT  /api/grades/{id}` — update grade
- [ ] `POST /api/grades/{id}/override` — Track Admin override (requires note)
- [ ] `GET  /api/students/{id}/grades`
- [ ] `GET  /api/cohorts/{id}/grades` — Track Admin / Branch Manager

### Lab Deliverables

- [ ] `POST /api/engagements/{id}/deliverables` — student submits (URL or file)
- [ ] `PUT  /api/deliverables/{id}/grade` — instructor grades

### Tags & Notes

- [ ] `POST /api/students/{id}/tags`
- [ ] `DELETE /api/students/{id}/tags/{tagId}`
- [ ] `POST /api/students/{id}/notes`
- [ ] `GET  /api/students/{id}/tags-and-notes`

### Announcements

- [ ] `POST /api/cohorts/{id}/announcements`
- [ ] `GET  /api/cohorts/{id}/announcements`
- [ ] `DELETE /api/announcements/{id}`

### Analytics

- [ ] `GET  /api/analytics/branch` — Branch Manager dashboard data
- [ ] `GET  /api/analytics/cohorts/{id}` — Track Admin dashboard data
- [ ] `GET  /api/analytics/lab-groups/{id}` — Instructor dashboard data
- [ ] `GET  /api/students/{id}/analytics` — Student dashboard data
- [ ] `GET  /api/analytics/at-risk/{cohortId}` — At-risk students list

### Billing

- [ ] `GET  /api/billing/rollup` — Branch Manager
- [ ] `POST /api/billing/rollup/generate` — trigger rollup generation
- [ ] `GET  /api/billing/instructors/{id}` — per-person billing summary

---

## 13. Deliverables Checklist

- [ ] Working full-stack application (all mandatory requirements implemented)
- [ ] Database migrations covering all tables
- [ ] Seeders with enough data to demonstrate every role and every flow
  - [ ] 1 Branch Manager account
  - [ ] At least 2 Track Admins (different tracks)
  - [ ] At least 1 external Instructor + 1 internal Track Admin teaching
  - [ ] At least 30 Students split across lab groups
  - [ ] At least 2 Tracks with 1 active Cohort each
  - [ ] Multiple Courses, Sessions, Attendance Records, Grades
- [ ] API documentation (Postman collection or OpenAPI/Swagger spec)
- [ ] README file containing:
  - [ ] Setup and installation steps
  - [ ] Seeded test account credentials for each role
  - [ ] Any assumptions or decisions made beyond the spec
- [ ] Live demo walkthrough for each role's experience end to end

---

## 14. Bonus Features Checklist

- [ ] **NFC Check-In** via Web NFC API (Chrome for Android only)
  - Must fall back gracefully to QR on unsupported devices/browsers
  - Show clear UI message when NFC not available
- [ ] **Grader-Consistency Analytics** — surface whether one lab instructor grades harder/more leniently than others (e.g. group 3 mean vs group 1 and 2 mean before normalization)
- [ ] **Advanced At-Risk Early Warning** — beyond the flat rule: attendance trend slope, sudden grade drop between courses
- [ ] **MinIO / S3-compatible storage** for file uploads (instead of local disk)
- [ ] **Requirement ID in Git commits** — use tags like `ATT-1`, `GRD-3`, `ACC-2` in commit messages and branch names

---

## Quick Reference: Key Business Rules

| Rule | Value |
|------|-------|
| Attendance ledger starting balance | 250 points |
| Unexcused absence deduction | -25 points |
| Excused absence deduction | -5 points |
| At-risk threshold: ledger | < 150 points |
| At-risk threshold: course | < 60 points |
| Lab deliverable total worth | 10 points |
| Late penalty per day | -25% of deliverable total |
| Days until deliverable = 0 | 4 days |
| Max file size for excuse attachment | 1 MB |
| Allowed excuse attachment formats | PDF, image |
| Course total score | Out of 100 |
| Max students per lab group | ~15 |
| Lab groups per cohort | 2–3 |
| Active cohorts per track | 1 (maximum) |

---

*Based on ITI Attendance & Grading Platform Requirements Specification v1.0*
