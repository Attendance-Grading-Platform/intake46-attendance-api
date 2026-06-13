<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Announcement;
use App\Models\Cohort;
use App\Models\User;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $webCohort    = Cohort::whereHas('track', fn($q) =>
            $q->where('name', 'like', '%Web%'))->first();
        $mobileCohort = Cohort::whereHas('track', fn($q) =>
            $q->where('name', 'like', '%Mobile%'))->first();
        $webAdmin     = User::where('email', 'karim.ashraf@iti.edu.eg')->first();
        $mobileAdmin  = User::where('email', 'nour.samir@iti.edu.eg')->first();
        $instructor   = User::where('email', 'amira.khaled@iti.edu.eg')->first();

        // ── Web Cohort Announcements ───────────────────
        $webAnnouncements = [
            [
                'title'        => 'Welcome to Web Development Intake 46',
                'body'         => "Dear students,\n\nWelcome to the ITI Full-Stack Web Development track. This program covers Laravel, Vue.js, PostgreSQL, and DevOps.\n\nPlease review the course schedule and ensure your development environment is set up before Day 1.\n\nBest regards,\nEng. Karim Ashraf",
                'published_at' => now()->subDays(45),
            ],
            [
                'title'        => 'Laravel Lab Deliverable — Submission Deadline Extended',
                'body'         => "Important notice:\n\nThe deadline for the Laravel CRUD application lab deliverable has been extended to March 20th.\n\nMake sure your GitHub repository is public and the link is submitted via the platform.\n\nLate submissions will incur the standard 25% per day penalty.",
                'published_at' => now()->subDays(20),
            ],
            [
                'title'        => 'Mid-Program Assessment Schedule',
                'body'         => "The mid-program assessments will be held as follows:\n\n• Laravel & PHP: April 1st at 09:00 AM\n• Vue.js & TypeScript: April 3rd at 09:00 AM\n• Database Design: April 5th at 09:00 AM\n\nLocation: Main Lab, Building B\n\nPlease bring your student ID.",
                'published_at' => now()->subDays(10),
            ],
            [
                'title'        => 'Business Session: Agile & Scrum Workshop',
                'body'         => "All Web and Mobile track students are invited to attend the Agile & Scrum Workshop.\n\nDate: This Thursday\nTime: 10:00 AM – 1:00 PM\nLocation: Auditorium, Building A\n\nAttendance is mandatory and will be recorded.",
                'published_at' => now()->subDays(5),
            ],
            [
                'title'        => 'Vue.js Project Guidelines Released',
                'body'         => "The final project guidelines for Vue.js & TypeScript have been published on the platform.\n\nKey requirements:\n• Vue 3 with Composition API\n• TypeScript throughout\n• Pinia for state management\n• Responsive design with Tailwind CSS\n\nDeadline: May 1st",
                'published_at' => now()->subDays(2),
            ],
            [
                'title'        => 'Attendance Policy Reminder',
                'body'         => "Reminder: Students with attendance ledger below 150 points will be flagged as at-risk.\n\nCurrent deduction rates:\n• Unexcused absence: -25 points\n• Excused absence: -5 points\n\nSubmit excuse requests within 48 hours of absence with supporting documentation.",
                'published_at' => now()->subDays(1),
            ],
        ];

        foreach ($webAnnouncements as $data) {
            Announcement::create([
                'cohort_id'    => $webCohort->id,
                'author_id'    => $webAdmin->id,
                'title'        => $data['title'],
                'body'         => $data['body'],
                'published_at' => $data['published_at'],
            ]);
        }

        // ── Instructor Announcement ────────────────────
        Announcement::create([
            'cohort_id'    => $webCohort->id,
            'author_id'    => $instructor->id,
            'title'        => 'Group A — Lab Session Notes Available',
            'body'         => "Hi Group A,\n\nThe notes and code samples from today's Laravel Eloquent session are available on the shared drive.\n\nPlease review before next session. The lab deliverable on relationships is due Friday.\n\nDr. Amira Khaled",
            'published_at' => now()->subDays(3),
        ]);

        // ── Mobile Cohort Announcements ────────────────
        $mobileAnnouncements = [
            [
                'title'        => 'Welcome to Mobile Development Intake 46',
                'body'         => "Dear students,\n\nWelcome to the ITI Mobile Application Development track. This program covers Flutter, React Native, and Mobile UI/UX Design.\n\nEnsure Flutter SDK is installed before Day 1.\n\nBest regards,\nEng. Nour El-Din Samir",
                'published_at' => now()->subDays(45),
            ],
            [
                'title'        => 'Flutter Project Structure Workshop',
                'body'         => "A special workshop on Flutter project structure and state management will be held this week.\n\nTopic: BLoC Pattern vs Provider vs Riverpod\nDate: Wednesday\nTime: 09:00 AM\n\nAttendance will be recorded.",
                'published_at' => now()->subDays(7),
            ],
            [
                'title'        => 'React Native Lab Deliverable Guidelines',
                'body'         => "The React Native lab deliverable requirements:\n\n1. Build a multi-screen navigation app\n2. Implement AsyncStorage for local data\n3. Connect to at least one external API\n4. Submit GitHub repo link by April 20th\n\nLate penalty: 25% per day",
                'published_at' => now()->subDays(4),
            ],
        ];

        foreach ($mobileAnnouncements as $data) {
            Announcement::create([
                'cohort_id'    => $mobileCohort->id,
                'author_id'    => $mobileAdmin->id,
                'title'        => $data['title'],
                'body'         => $data['body'],
                'published_at' => $data['published_at'],
            ]);
        }
    }
}