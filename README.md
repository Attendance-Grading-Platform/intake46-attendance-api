<div align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="300" alt="Laravel Logo">

  <br />
  <br />

  [![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20.svg?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
  [![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4.svg?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
  [![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16.0+-4169E1.svg?style=for-the-badge&logo=postgresql&logoColor=white)](https://postgresql.org)
  [![Sanctum](https://img.shields.io/badge/Auth-Sanctum-4A5568.svg?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/docs/sanctum)

  <h3 align="center">ITI Attendance & Grading Platform - API</h3>

  <p align="center">
    A robust, secure, and scalable backend system for academic management at ITI.
    <br />
    <a href="#about-the-project"><strong>Explore the docs »</strong></a>
  </p>
</div>

---

<details open>
  <summary>Table of Contents</summary>
  <ol>
    <li><a href="#about-the-project">About The Project</a></li>
    <li><a href="#technology-stack">Technology Stack</a></li>
    <li><a href="#key-features-by-role">Key Features By Role</a></li>
    <li><a href="#getting-started">Getting Started</a></li>
    <li><a href="#seeded-test-accounts">Seeded Test Accounts</a></li>
    <li><a href="#api-structure">API Structure</a></li>
    <li><a href="#assumptions--technical-decisions">Assumptions & Technical Decisions</a></li>
    <li><a href="#meet-the-team">Meet The Team</a></li>
  </ol>
</details>

---

## 📖 About The Project

The ITI Attendance & Grading Platform is an enterprise-grade academic management system designed specifically for the Information Technology Institute (ITI). This repository contains the **Laravel API Backend**, which serves as the central brain of the platform.

It is engineered to handle complex hierarchical data (Branches ➔ Tracks ➔ Cohorts ➔ Lab Groups ➔ Students), process real-time attendance tracking via QR codes, and dynamically calculate grading analytics based on submission timestamps and customizable penalty logic.

---

## 🛠️ Technology Stack

* **Framework:** Laravel 11.x
* **Language:** PHP 8.2+
* **Database:** PostgreSQL 16.0+
* **Authentication:** Laravel Sanctum (Token-based SPA Auth)
* **API Architecture:** RESTful JSON API

---

## ✨ Key Features By Role

### 🏢 Branch Manager
- **Global Overview:** View aggregated analytics across all tracks and cohorts.
- **Financial Processing:** Generate and approve billing snapshots for instructors based on their compensation models (Hourly vs Fixed).

### 📋 Track Admin
- **Curriculum Management:** Create and configure courses, deliverables, and final exams.
- **Scheduling:** Assign instructors to lab groups and schedule engagement sessions.
- **Cohort Oversight:** Monitor at-risk students and overall grading throughput within their specific track.

### 👨‍🏫 Instructor
- **Attendance Tracking:** Mark sessions as delivered and monitor student attendance.
- **Grading Engine:** Review submissions and assign raw scores (system automatically calculates late penalties).
- **Communication:** Post announcements to assigned cohorts.
- **Excuse Processing:** Review and approve/reject absence excuses submitted by students.

### 🎓 Student
- **Progress Tracking:** View a personalized dashboard with attendance ledger balances and normalized course grades.
- **Deliverables:** Submit assignments securely.
- **Excuses:** Upload medical or official documents to request absence waivers.

---

## 🚀 Getting Started

To get a local copy up and running, follow these simple steps.

### Prerequisites
* PHP >= 8.2
* Composer
* PostgreSQL >= 16.0

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/Attendance-Grading-Platform/intake46-attendance-api.git
   cd intake46-attendance-api
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Configure Environment**
   ```bash
   cp .env.example .env
   ```
   *Update the `.env` file with your PostgreSQL database credentials.*

4. **Generate App Key**
   ```bash
   php artisan key:generate
   ```

5. **Link Storage**
   *(Critical for accessing uploaded attachments like excuses and submissions)*
   ```bash
   php artisan storage:link
   ```

6. **Migrate & Seed the Database**
   This command will build the schema and populate it with a comprehensive set of test data.
   ```bash
   php artisan migrate:fresh --seed
   ```

7. **Run the Development Server**
   ```bash
   php artisan serve
   ```

---

## 🔑 Seeded Test Accounts

The `AcademicStructureSeeder` populates the database with a complete academic hierarchy to test the platform's role-based authorization perfectly.

**Password for ALL accounts:** `password`

| Role | Email Address | Assigned To |
| :--- | :--- | :--- |
| **Branch Manager** | `manager@iti.edu.eg` | Global Access |
| **Track Admin** | `karim.ashraf@iti.edu.eg` | Web Development Track |
| **Track Admin** | `nour.samir@iti.edu.eg` | Mobile Development Track |
| **Instructor** | `amira.khaled@iti.edu.eg` | Web Track Lab Groups |
| **Instructor** | `youssef.nabil@iti.edu.eg` | Web Track Lab Groups |
| **Instructor** | `sara.elsayed@iti.edu.eg` | Mobile Track Lab Groups |
| **Student** | `ahmed.ali.46@student.iti.edu.eg` | Web Track Cohort |
| **Student** | `alaa.ibrahim.46@student.iti.edu.eg`| Web Track Cohort |
| **Student** | `adam.sherif.46@student.iti.edu.eg`| Mobile Track Cohort |
| **Student** | `bishoy.emad.46@student.iti.edu.eg`| Mobile Track Cohort |
| **Edge Case** | `expired@iti.edu.eg` | Testing Account Expiry |
| **Edge Case** | `inactive@iti.edu.eg` | Testing Account Deactivation |

---

## 🔀 API Structure

The application routes are logically separated in `routes/api.php` to handle different authentication contexts:

* `/api/auth/*` - Public endpoints (Login, Password Reset).
* `/api/v1/*` - The core protected REST API. Requires Sanctum Token and validates account expiry/activation.
* `/api/scan/*` - High-performance fast-path endpoints specifically designed for IoT devices and mobile QR scanners.

---

## 🧠 Assumptions & Technical Decisions

1. **Strict Query Isolation (RBAC):**
   Security is not just an afterthought. We enforce Authorization Policies *and* query-level scoping. For example, when an Instructor requests submissions, the database query `whereIn` clause restricts the results exclusively to students in their assigned lab groups.
   
2. **Ephemeral vs Persistent Storage:**
   By default, files are stored on the `local` disk (`storage/app/public`). If deploying to a cloud platform like Railway, **ephemeral storage will wipe uploaded files on every redeploy**. It is assumed that production deployments will update `FILESYSTEM_DISK=s3` or mount a persistent volume.

3. **Dynamic Penalty System:**
   The grading system allows instructors to input a `raw_score` and `raw_max`. The `LatePenaltyService` automatically calculates the final normalized score based on the difference between the submission `created_at` timestamp and the `CourseComponent` due date.

4. **Analytics Heuristics:**
   Determining if a student is "At Risk" is a computationally heavy operation. We determine risk by checking if the student's Attendance Ledger balance falls below `150` OR if any of their normalized course grades fall below `60%`.

---

## 👥 Meet The Team

This project was brought to life by an incredible team of developers:

* **Mostafa Khalifa** - [@Mostafa-Khalifaa](https://github.com/Mostafa-Khalifaa)
* **Alaa Abdullah** - [@AlaaAbdullah13](https://github.com/AlaaAbdullah13)
* **Hashim Abdulaziz** - [@HashimAbdulaziz](https://github.com/HashimAbdulaziz)
* **Haneen Elasawy** - [@Haneenelasawy](https://github.com/Haneenelasawy)
* **Mohamed Hamdy** - [@mohamedhamdy1](https://github.com/mohamedhamdy1)

<div align="center">
  <sub>Built with ❤️ at the Information Technology Institute (ITI).</sub>
</div>
