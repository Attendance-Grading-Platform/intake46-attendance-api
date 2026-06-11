<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\EngagementSession;
use App\Models\ExcuseRequest;

class ExcuseSeeder extends Seeder
{
    public function run(): void
    {
        $students = User::where('role', 'student')->take(10)->get();
        if ($students->isEmpty()) return;
        
        $sessions = EngagementSession::take(10)->get();
        if ($sessions->isEmpty()) return;

        $excuses = [
            ['status' => 'requested', 'reason' => 'I had a severe medical issue and could not attend the session. Doctor note attached.', 'attachment_path' => 'dummy/medical_note.pdf'],
            ['status' => 'approved', 'reason' => 'Family emergency required me to travel back home.', 'attachment_path' => null],
            ['status' => 'rejected', 'reason' => 'I overslept and missed the bus.', 'attachment_path' => null],
            ['status' => 'requested', 'reason' => 'Attending a mandatory university midterm exam.', 'attachment_path' => 'dummy/medical_note.pdf'],
            ['status' => 'approved', 'reason' => 'Military conscription paperwork appointment.', 'attachment_path' => null],
            ['status' => 'requested', 'reason' => 'Car broke down on the highway.', 'attachment_path' => null],
            ['status' => 'rejected', 'reason' => 'I was working on the assignment.', 'attachment_path' => null],
            ['status' => 'approved', 'reason' => 'Sick with the flu, doctor advised rest.', 'attachment_path' => 'dummy/medical_note.pdf'],
            ['status' => 'requested', 'reason' => 'Internet went down during the remote session.', 'attachment_path' => null],
            ['status' => 'approved', 'reason' => 'Death in the family.', 'attachment_path' => null]
        ];

        foreach ($excuses as $i => $data) {
            $student = $students[$i % count($students)];
            $session = $sessions[$i % count($sessions)];
            
            // ensure no duplicates
            if (!ExcuseRequest::where('student_id', $student->id)->where('session_id', $session->id)->exists()) {
                ExcuseRequest::create([
                    'student_id' => $student->id,
                    'session_id' => $session->id,
                    'status' => $data['status'],
                    'reason' => $data['reason'],
                    'attachment_path' => $data['attachment_path'],
                ]);
            }
        }
    }
}
