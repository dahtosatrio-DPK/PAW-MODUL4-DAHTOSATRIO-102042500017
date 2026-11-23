<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\MataKuliah;
use App\Models\Material;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Dosen Pengampu Default
        $dosen = User::updateOrCreate(
            ['email' => 'dosen@celoe.test'],
            [
                'name' => 'Dr. Ir. Budi Santoso, M.T.',
                'password' => Hash::make('dosen123'),
                'role' => 'dosen',
                'nomor_induk' => '198501012015041001',
            ]
        );

        // 2. Akun Mahasiswa Default
        $mahasiswa1 = User::updateOrCreate(
            ['email' => 'mahasiswa@celoe.test'],
            [
                'name' => 'Ahmad Pratama',
                'password' => Hash::make('mahasiswa123'),
                'role' => 'mahasiswa',
                'nomor_induk' => '1301210001',
            ]
        );

        // 3. Akun Mahasiswa Tambahan
        $mahasiswa2 = User::updateOrCreate(
            ['email' => 'mahasiswa2@celoe.test'],
            [
                'name' => 'Siti Nurhaliza',
                'password' => Hash::make('mahasiswa123'),
                'role' => 'mahasiswa',
                'nomor_induk' => '1301210002',
            ]
        );

        // 4. Mata Kuliah Semester Ganjil
        $course1 = MataKuliah::updateOrCreate(
            ['kode_mk' => 'IF301'],
            [
                'nama_mk' => 'Pengembangan Aplikasi Website',
                'deskripsi' => 'Mata kuliah yang membahas arsitektur aplikasi web modern menggunakan framework Laravel, MVC, Eloquent ORM, dan keamanan web.',
                'sks' => 4,
                'semester' => 'Ganjil 2026/2027',
                'dosen_id' => $dosen->id,
            ]
        );

        // 5. Mata Kuliah Semester Genap
        $course2 = MataKuliah::updateOrCreate(
            ['kode_mk' => 'IF302'],
            [
                'nama_mk' => 'Pemrograman Web Lanjut',
                'deskripsi' => 'Mempelajari pengembangan aplikasi web menggunakan framework Laravel.',
                'sks' => 3,
                'semester' => 'Genap 2026/2027',
                'dosen_id' => $dosen->id,
            ]
        );

        // 6. Mata Kuliah Semester Berikutnya
        $course3 = MataKuliah::updateOrCreate(
            ['kode_mk' => 'IF303'],
            [
                'nama_mk' => 'Basis Data Lanjut',
                'deskripsi' => 'Mempelajari pengelolaan dan pengembangan basis data.',
                'sks' => 3,
                'semester' => 'Ganjil 2027/2028',
                'dosen_id' => $dosen->id,
            ]
        );

        // 7. Daftarkan Mahasiswa ke Mata Kuliah
        $course1->students()->syncWithoutDetaching([
            $mahasiswa1->id => ['status' => 'approved'],
            $mahasiswa2->id => ['status' => 'approved'],
        ]);

        $course2->students()->syncWithoutDetaching([
            $mahasiswa1->id => ['status' => 'approved'],
            $mahasiswa2->id => ['status' => 'approved'],
        ]);

        $course3->students()->syncWithoutDetaching([
            $mahasiswa2->id => ['status' => 'approved'],
        ]);

        // 8. Tambahkan Materi Contoh
        Material::updateOrCreate(
            [
                'course_id' => $course1->id,
                'minggu_ke' => 1,
                'urutan' => 1,
            ],
            [
                'judul' => 'Pengenalan Arsitektur MVC & Routing di Laravel 12',
                'konten' => 'Mempelajari alur HTTP Request, Route Middleware, Controller, dan pembuatan View menggunakan Blade Template.',
            ]
        );

        // 9. Tambahkan Tugas Pertama
        $assignment1 = Assignment::updateOrCreate(
            [
                'course_id' => $course1->id,
                'judul' => 'Tugas Praktik Laravel MVC',
            ],
            [
                'deskripsi' => 'Buat aplikasi sederhana menggunakan konsep MVC Laravel.',
                'deadline' => now()->addDays(7),
            ]
        );

        // 10. Tambahkan Tugas Kedua
        $assignment2 = Assignment::updateOrCreate(
            [
                'course_id' => $course2->id,
                'judul' => 'Tugas CRUD Laravel',
            ],
            [
                'deskripsi' => 'Buat fitur CRUD menggunakan Laravel dan database MySQL.',
                'deadline' => now()->addDays(14),
            ]
        );

        // 11. Contoh Submission Mahasiswa Pertama
        Submission::updateOrCreate(
            [
                 'assignment_id' => $assignment1->id,
        'user_id' => $mahasiswa1->id,
    ],
    [
        'jawaban' => 'https://github.com/mahasiswa/tugas-laravel',
        'submitted_at' => now(),
        'nilai' => 85,
        'catatan_dosen' => 'Tugas sudah baik dan sesuai ketentuan.',
    ]
);
                

        // 12. Contoh Submission Mahasiswa Kedua
        Submission::updateOrCreate(
            [
                'assignment_id' => $assignment2->id,
                'user_id' => $mahasiswa2->id,
            ],
            [
                'jawaban' => 'https://github.com/mahasiswa2/tugas-crud',
                'submitted_at' => now(),
                'nilai' => null,
                'catatan_dosen' => null,
            ]
        );
    }
}
