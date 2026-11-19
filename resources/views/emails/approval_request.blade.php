<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Permintaan Verifikasi {{ $projectType }}</title>
</head>
<body style="font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #1f2937; background-color: #f3f4f6; padding: 24px 12px; margin: 0;">
    <div style="max-width: 620px; margin: 0 auto; background: #ffffff; padding: 28px; border: 1px solid #e5e7eb; border-radius: 12px;">
        <div style="border-bottom: 2px solid #4f46e5; padding-bottom: 14px; margin-bottom: 20px;">
            <div style="font-size: 12px; font-weight: bold; color: #4f46e5; text-transform: uppercase; letter-spacing: 0.08em;">EREPO Teknik Elektro</div>
            <h2 style="color: #111827; margin: 4px 0 0 0; font-size: 20px;">Permintaan Verifikasi {{ $projectType }}</h2>
        </div>

        <p style="margin-top: 0;">Yth. Bapak/Ibu Dosen Pembimbing, Koordinator TA, dan Admin,</p>
        <p>Terdapat unggahan dokumen <strong>{{ $projectType }}</strong> mahasiswa yang siap untuk diperiksa dan diverifikasi pada sistem EREPO:</p>

        <table style="width: 100%; border-collapse: collapse; margin: 18px 0 22px 0; font-size: 14px;">
            <tr>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600; width: 35%;">Judul</td>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb;">{{ $project->title }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600;">Group ID</td>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb;">{{ $project->group_id }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600;">Tahun Akademik</td>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb;">{{ $project->academic_year }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600;">Anggota Kelompok</td>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb;">{{ $memberNames }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600;">Dosen Pembimbing</td>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb;">{{ $dosenName }}</td>
            </tr>
            @if(($project->ai_check_status ?? null) === 'passed')
            <tr>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600;">Pemeriksaan Awal AI</td>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; color: #059669; font-weight: 600;">Lolos Cek Kelengkapan (TTD & Daftar Isi)</td>
            </tr>
            @endif
        </table>

        <p>Silakan masuk ke aplikasi EREPO untuk meninjau dokumen, menyetujui (<em>Approve</em>), atau meminta mahasiswa melakukan upload ulang apabila diperlukan perbaikan.</p>

        <p style="margin-top: 24px; margin-bottom: 0; font-size: 13px; color: #6b7280;">
            Salam hormat,<br>
            <strong>Sistem EREPO — S1 Teknik Elektro</strong>
        </p>
    </div>
</body>
</html>
