<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /** @return list<array{0: string, 1: string}> */
    public static function webStudents(): array
    {
        return [
            ['Ahmed Mohamed Ali',     'ahmed.ali.46@student.iti.edu.eg'],
            ['Alaa Ibrahim Hassan',   'alaa.ibrahim.46@student.iti.edu.eg'],
            ['Bassem Khaled Nour',    'bassem.khaled.46@student.iti.edu.eg'],
            ['Dina Mostafa Kamal',    'dina.mostafa.46@student.iti.edu.eg'],
            ['Eslam Youssef Omar',    'eslam.youssef.46@student.iti.edu.eg'],
            ['Fatma Ahmed Saleh',     'fatma.ahmed.46@student.iti.edu.eg'],
            ['Galal Hassan Mahmoud',  'galal.hassan.46@student.iti.edu.eg'],
            ['Heba Tarek Ibrahim',    'heba.tarek.46@student.iti.edu.eg'],
            ['Islam Nabil Ramadan',   'islam.nabil.46@student.iti.edu.eg'],
            ['Jana Sameh Fawzy',      'jana.sameh.46@student.iti.edu.eg'],
            ['Karim Adel Sayed',      'karim.adel.46@student.iti.edu.eg'],
            ['Lara Mohamed Gamal',    'lara.gamal.46@student.iti.edu.eg'],
            ['Mahmoud Wael Khalil',   'mahmoud.wael.46@student.iti.edu.eg'],
            ['Nada Hossam Zaki',      'nada.hossam.46@student.iti.edu.eg'],
            ['Omar Sherif Badawi',    'omar.sherif.46@student.iti.edu.eg'],
            ['Passant Emad Lotfy',    'passant.emad.46@student.iti.edu.eg'],
            ['Ramy Ayman Gouda',      'ramy.ayman.46@student.iti.edu.eg'],
            ['Salma Tamer Shawky',    'salma.tamer.46@student.iti.edu.eg'],
            ['Tamer Hesham Awad',     'tamer.hesham.46@student.iti.edu.eg'],
            ['Aya Osama Fathy',       'aya.osama.46@student.iti.edu.eg'],
            ['Baher Ramzy Nazem',     'baher.ramzy.46@student.iti.edu.eg'],
            ['Celine Adham Waheed',   'celine.adham.46@student.iti.edu.eg'],
            ['Diaa Magdy Abdel',      'diaa.magdy.46@student.iti.edu.eg'],
            ['Ehab Walid Mansour',    'ehab.walid.46@student.iti.edu.eg'],
            ['Farida Khaled Soliman', 'farida.khaled.46@student.iti.edu.eg'],
            ['Gasser Nour Eldin',     'gasser.nour.46@student.iti.edu.eg'],
            ['Habiba Samir Khalaf',   'habiba.samir.46@student.iti.edu.eg'],
            ['Ibrahim Hazem Ragab',   'ibrahim.hazem.46@student.iti.edu.eg'],
            ['Jasmine Wael Fahmy',    'jasmine.wael.46@student.iti.edu.eg'],
            ['Kareem Tarek Osman',    'kareem.tarek.46@student.iti.edu.eg'],
            ['Layla Sherif Hamdi',    'layla.sherif.46@student.iti.edu.eg'],
            ['Mona Khaled Ghoneim',   'mona.khaled.46@student.iti.edu.eg'],
            ['Nour Amr Wahdan',       'nour.amr.46@student.iti.edu.eg'],
            ['Omar Hazem Elmasry',    'omar.hazem.46@student.iti.edu.eg'],
            ['Passant Ramy Helmy',    'passant.ramy.46@student.iti.edu.eg'],
            ['Qusay Adel Morsy',      'qusay.adel.46@student.iti.edu.eg'],
            ['Rana Ibrahim Kassem',   'rana.ibrahim.46@student.iti.edu.eg'],
            ['Sameh Gamal Younis',    'sameh.gamal.46@student.iti.edu.eg'],
            ['Tasneem Hany Barakat',  'tasneem.hany.46@student.iti.edu.eg'],
            ['Umar Yasser Selim',     'umar.yasser.46@student.iti.edu.eg'],
            ['Veronica Maged Hanna',  'veronica.maged.46@student.iti.edu.eg'],
            ['Walaa Samy Abdalla',    'walaa.samy.46@student.iti.edu.eg'],
            ['Yara Amir Barsoum',     'yara.amir.46@student.iti.edu.eg'],
            ['Ziad Fady Mikhail',     'ziad.fady.46@student.iti.edu.eg'],
        ];
    }

    /** @return list<array{0: string, 1: string}> */
    public static function mobileStudents(): array
    {
        return [
            ['Adam Sherif Naguib',    'adam.sherif.46@student.iti.edu.eg'],
            ['Bishoy Emad Salib',     'bishoy.emad.46@student.iti.edu.eg'],
            ['Christine Hany Aziz',   'christine.hany.46@student.iti.edu.eg'],
            ['David Ramzy Hanna',     'david.ramzy.46@student.iti.edu.eg'],
            ['Engy Tarek Wahba',      'engy.tarek.46@student.iti.edu.eg'],
            ['Fady Maged Boshra',     'fady.maged.46@student.iti.edu.eg'],
            ['George Nabil Ghali',    'george.nabil.46@student.iti.edu.eg'],
            ['Hany Samir Youssef',    'hany.samir.46@student.iti.edu.eg'],
            ['Irene Amir Halim',      'irene.amir.46@student.iti.edu.eg'],
            ['Joseph Makram Girgis',  'joseph.makram.46@student.iti.edu.eg'],
            ['Kerolos Nader Botros',  'kerolos.nader.46@student.iti.edu.eg'],
            ['Lilian Maher Henein',   'lilian.maher.46@student.iti.edu.eg'],
            ['Marina Mounir Tawfik',  'marina.mounir.46@student.iti.edu.eg'],
            ['Nabil Adel Haddad',     'nabil.adel.46@student.iti.edu.eg'],
            ['Olivia Fawzy Khalil',   'olivia.fawzy.46@student.iti.edu.eg'],
            ['Peter Wagih Abdou',     'peter.wagih.46@student.iti.edu.eg'],
            ['Rita Medhat Boulos',    'rita.medhat.46@student.iti.edu.eg'],
            ['Samer Rafik Guirguis',  'samer.rafik.46@student.iti.edu.eg'],
            ['Tina Nessim Eskander',  'tina.nessim.46@student.iti.edu.eg'],
            ['Usama Fouad Elshafei',  'usama.fouad.46@student.iti.edu.eg'],
            ['Victor Ramy Tadros',    'victor.ramy.46@student.iti.edu.eg'],
            ['Warda Sayed Amin',      'warda.sayed.46@student.iti.edu.eg'],
            ['Youssef Adly Labib',    'youssef.adly.46@student.iti.edu.eg'],
            ['Zeinab Sobhy Marcos',   'zeinab.sobhy.46@student.iti.edu.eg'],
            ['Amr Wahid Heikal',      'amr.wahid.46@student.iti.edu.eg'],
            ['Basma Ihab Salama',     'basma.ihab.46@student.iti.edu.eg'],
            ['Cyril Nader Wahba',     'cyril.nader.46@student.iti.edu.eg'],
            ['Dalia Emad Samir',      'dalia.emad.46@student.iti.edu.eg'],
            ['Ehab Samir Gerges',     'ehab.samir.46@student.iti.edu.eg'],
            ['Fibi Wagih Tawfik',     'fibi.wagih.46@student.iti.edu.eg'],
        ];
    }

    /** @return list<string> */
    public static function webStudentEmails(): array
    {
        return array_column(self::webStudents(), 1);
    }

    /** @return list<string> */
    public static function mobileStudentEmails(): array
    {
        return array_column(self::mobileStudents(), 1);
    }

    public function run(): void
    {
        // ── Branch Manager ─────────────────────────────
        User::create([
            'name'              => 'Dr. Hana Mostafa',
            'email'             => 'manager@iti.edu.eg',
            'password'          => Hash::make('password'),
            'role'              => 'branch_manager',
            'compensation_type' => 'internal',
            'fixed_salary'      => 15000,
            'hourly_rate'       => 0,
            'is_active'         => true,
            'expiry_date'       => '2027-12-31',
        ]);

        // ── Track Admins ───────────────────────────────
        User::create([
            'name'              => 'Eng. Karim Ashraf',
            'email'             => 'karim.ashraf@iti.edu.eg',
            'password'          => Hash::make('password'),
            'role'              => 'track_admin',
            'compensation_type' => 'internal',
            'fixed_salary'      => 10000,
            'hourly_rate'       => 250,
            'is_active'         => true,
            'expiry_date'       => '2027-12-31',
        ]);

        User::create([
            'name'              => 'Eng. Nour El-Din Samir',
            'email'             => 'nour.samir@iti.edu.eg',
            'password'          => Hash::make('password'),
            'role'              => 'track_admin',
            'compensation_type' => 'internal',
            'fixed_salary'      => 10000,
            'hourly_rate'       => 250,
            'is_active'         => true,
            'expiry_date'       => '2027-12-31',
        ]);

        // ── External Instructors ───────────────────────
        $instructors = [
            ['Dr. Amira Khaled',   'amira.khaled@iti.edu.eg',   400],
            ['Eng. Youssef Nabil', 'youssef.nabil@iti.edu.eg',  350],
            ['Dr. Sara El-Sayed',  'sara.elsayed@iti.edu.eg',    400],
            ['Eng. Ahmed Fouad',   'ahmed.fouad@iti.edu.eg',     450],
            ['Eng. Laila Hassan',  'laila.hassan@iti.edu.eg',    350],
        ];

        foreach ($instructors as [$name, $email, $rate]) {
            User::create([
                'name'              => $name,
                'email'             => $email,
                'password'          => Hash::make('password'),
                'role'              => 'instructor',
                'compensation_type' => 'external',
                'fixed_salary'      => 0,
                'hourly_rate'       => $rate,
                'is_active'         => true,
                'expiry_date'       => '2026-08-31',
            ]);
        }

        // ── Edge Case Accounts (for testing) ───────────
        User::create([
            'name'              => 'Expired User',
            'email'             => 'expired@iti.edu.eg',
            'password'          => Hash::make('password'),
            'role'              => 'student',
            'compensation_type' => null,
            'fixed_salary'      => null,
            'hourly_rate'       => null,
            'is_active'         => true,
            'expiry_date'       => '2025-01-01', // already expired
        ]);

        User::create([
            'name'              => 'Inactive User',
            'email'             => 'inactive@iti.edu.eg',
            'password'          => Hash::make('password'),
            'role'              => 'student',
            'compensation_type' => null,
            'fixed_salary'      => null,
            'hourly_rate'       => null,
            'is_active'         => false, // deactivated
            'expiry_date'       => '2026-08-31',
        ]);

        foreach (self::webStudents() as [$name, $email]) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name'              => $name,
                    'password'          => Hash::make('password'),
                    'role'              => 'student',
                    'compensation_type' => null,
                    'fixed_salary'      => null,
                    'hourly_rate'       => null,
                    'is_active'         => true,
                    'expiry_date'       => '2026-08-31',
                ]
            );
        }

        foreach (self::mobileStudents() as [$name, $email]) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name'              => $name,
                    'password'          => Hash::make('password'),
                    'role'              => 'student',
                    'compensation_type' => null,
                    'fixed_salary'      => null,
                    'hourly_rate'       => null,
                    'is_active'         => true,
                    'expiry_date'       => '2026-08-31',
                ]
            );
        }
    }
}