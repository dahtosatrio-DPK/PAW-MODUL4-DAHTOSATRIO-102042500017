<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        if (!Auth::user()->isMahasiswa()) {
            abort(403, 'Akses ditolak. Pengumpulan tugas hanya dapat dilakukan oleh Mahasiswa.');
        }

        $user = Auth::user();

        $enrollment = $assignment->course->students()->where('users.id', $user->id)->first();
        if (!$enrollment || $enrollment->pivot->status !== 'approved') {
            abort(403, 'Akses ditolak. Anda belum disetujui oleh Dosen untuk mengikuti mata kuliah tugas ini.');
        }

        if ($assignment->isOverdue()) {
            return back()->with('error', 'Gagal mengumpulkan: Batas waktu pengumpulan (deadline) tugas telah berakhir.');
        }

        $validated = $request->validate([
            'jawaban' => ['required', 'string', 'min:10', 'max:20000'],
        ], [
            'jawaban.required' => 'Teks jawaban tugas wajib diisi.',
            'jawaban.min' => 'Jawaban terlalu pendek. Tuliskan jawaban minimal 10 karakter.',
            'jawaban.max' => 'Jawaban melebihi batas maksimal 20.000 karakter.',
        ]);

        Submission::updateOrCreate(
            [
                'assignment_id' => $assignment->id,
                'user_id' => $user->id,
            ],
            [
                'jawaban' => $validated['jawaban'],
                'submitted_at' => now(),
            ]
        );

        return back()->with('success', 'Jawaban tugas Anda berhasil disimpan ke sistem.');
    }

    public function grade(Request $request, Submission $submission): RedirectResponse
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // 1. Pastikan hanya dosen pengampu mata kuliah yang dapat memberi nilai
        if ($submission->assignment->course->dosen_id !== Auth::id()) {
            abort(403, 'Akses ditolak. Anda bukan dosen pengampu mata kuliah ini.');
        }

        // 2. Validasi input nilai dan catatan dosen
        $validated = $request->validate([
            'nilai' => ['required', 'integer', 'min:0', 'max:100'],
            'catatan_dosen' => ['nullable', 'string', 'max:1000'],
        ], [
            'nilai.required' => 'Nilai wajib diisi.',
            'nilai.integer' => 'Nilai harus berupa bilangan bulat.',
            'nilai.min' => 'Nilai minimal adalah 0.',
            'nilai.max' => 'Nilai maksimal adalah 100.',
            'catatan_dosen.max' => 'Catatan dosen melebihi batas maksimal 1.000 karakter.',
        ]);

        // 3. Simpan perubahan
        $submission->update([
            'nilai' => $validated['nilai'],
            'catatan_dosen' => $validated['catatan_dosen'] ?? null,
        ]);

        return back()->with('success', 'Nilai untuk mahasiswa ' . $submission->user->name . ' berhasil disimpan.');
    }

    public function exportCsv(Assignment $assignment): StreamedResponse
    {
        if (!Auth::check()) {
            abort(403, 'Akses ditolak. Silakan login terlebih dahulu.');
        }

        // 1. Pastikan hanya dosen pengampu yang dapat mengekspor nilai
        if ($assignment->course->dosen_id !== Auth::id()) {
            abort(403, 'Akses ditolak. Anda bukan dosen pengampu mata kuliah ini.');
        }

        // 2. Muat relasi yang dibutuhkan
        $assignment->load(['course.approvedStudents', 'submissions']);

        $students = $assignment->course->approvedStudents->sortBy('name');
        $submissions = $assignment->submissions->keyBy('user_id');

        $filename = 'nilai-tugas-' . $assignment->id . '-' . now()->format('Ymd_His') . '.csv';

        // 3. Tulis CSV secara streaming
        return response()->streamDownload(function () use ($students, $submissions) {
            $output = fopen('php://output', 'w');

            // BOM UTF-8 agar karakter terbaca benar di Excel
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'No',
                'NIM',
                'Nama Mahasiswa',
                'Status Pengumpulan',
                'Waktu Pengumpulan',
                'Nilai',
                'Status Kelulusan',
                'Catatan Dosen',
            ]);

            $no = 1;
            foreach ($students as $student) {
                $submission = $submissions->get($student->id);

                $sudahKumpul = $submission !== null;
                $nilai = $sudahKumpul ? $submission->nilai : null;

                if ($nilai === null) {
                    $kelulusan = 'Belum Dinilai';
                } else {
                    // Batas kelulusan 70 (sesuaikan dengan accessor di model Submission)
                    $kelulusan = $nilai >= 70 ? 'Lulus' : 'Tidak Lulus';
                }

                fputcsv($output, [
                    $no++,
                    $student->nomor_induk,
                    $student->name,
                    $sudahKumpul ? 'Sudah Mengumpulkan' : 'Belum Mengumpulkan',
                    $sudahKumpul && $submission->submitted_at
                        ? $submission->submitted_at->format('Y-m-d H:i:s')
                        : '-',
                    $nilai ?? '-',
                    $kelulusan,
                    $sudahKumpul ? ($submission->catatan_dosen ?? '-') : '-',
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}