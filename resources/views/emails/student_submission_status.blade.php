<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Status Unggahan {{ $projectType }}</title>
</head>
<body style="font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #1f2937; background-color: #f3f4f6; padding: 24px 12px; margin: 0;">
    @php
        $accentColor = match ($statusType) {
            'reupload_ai', 'reupload_reviewer' => '#dc2626',
            'pending_verification' => '#d97706',
            default => '#4f46e5',
        };
        $badgeLabel = match ($statusType) {
            'uploaded_checking' => 'Berhasil Dikirim — Sedang Diperiksa Kelengkapan AI',
            'pending_verification' => 'Menunggu Hasil Verifikasi',
            'reupload_ai' => 'Tertahan AI — Wajib Upload Ulang',
            'reupload_reviewer' => 'Diminta Upload Ulang oleh ' . ($reviewerLabel ?: 'Pemeriksa'),
            default => 'Pembaruan Status',
        };
    @endphp
    <div style="max-width: 620px; margin: 0 auto; background: #ffffff; padding: 28px; border: 1px solid #e5e7eb; border-radius: 12px;">
        <div style="border-bottom: 2px solid {{ $accentColor }}; padding-bottom: 14px; margin-bottom: 20px;">
            <div style="font-size: 12px; font-weight: bold; color: {{ $accentColor }}; text-transform: uppercase; letter-spacing: 0.08em;">EREPO Teknik Elektro</div>
            <h2 style="color: #111827; margin: 4px 0 0 0; font-size: 20px;">{{ $badgeLabel }}</h2>
        </div>

        <p style="margin-top: 0;">Halo Tim Mahasiswa (<strong>{{ $memberNames }}</strong>),</p>

        @if($statusType === 'uploaded_checking')
            <p>Dokumen <strong>{{ $projectType }}</strong> kelompok Anda telah berhasil diterima oleh sistem EREPO dan saat ini sedang menjalani pemeriksaan kelengkapan otomatis (tanda tangan pengesahan, nomor halaman Daftar Isi, serta kelengkapan butir wajib dokumen E100–E500 sesuai Buku Pedoman TA) oleh AI sebelum masuk ke antrean verifikasi.</p>
        @elseif($statusType === 'pending_verification')
            <p>Dokumen <strong>{{ $projectType }}</strong> kelompok Anda telah siap dan saat ini sedang <strong>menunggu hasil verifikasi</strong> dari Dosen Pembimbing / Koordinator TA / Admin.</p>
        @elseif($statusType === 'reupload_ai')
            <p>Berdasarkan pemeriksaan kelengkapan otomatis AI, dokumen PDF <strong>{{ $projectType }}</strong> kelompok Anda <strong>ditahan sementara</strong> karena masih terdapat bagian yang belum lengkap. Anda <strong>wajib mengunggah ulang</strong> file PDF yang sudah diperbaiki.</p>
        @elseif($statusType === 'reupload_reviewer')
            <p>Dokumen <strong>{{ $projectType }}</strong> kelompok Anda diminta untuk <strong>diperbaiki dan diunggah ulang</strong> oleh <strong>{{ $reviewerLabel ?: 'Pemeriksa' }}</strong>.</p>
        @endif

        <table style="width: 100%; border-collapse: collapse; margin: 18px 0; font-size: 14px;">
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
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600;">Status Saat Ini</td>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; color: {{ $accentColor }}; font-weight: 600;">{{ $badgeLabel }}</td>
            </tr>
        </table>

        @if($statusType === 'reupload_ai' && count($missingItems) > 0)
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin: 18px 0;">
                <div style="font-weight: 700; color: #991b1b; margin-bottom: 8px;">Rincian Kekurangan yang Wajib Dilengkapi:</div>
                <ul style="margin: 0; padding-left: 20px; color: #7f1d1d; font-size: 14px;">
                    @foreach($missingItems as $item)
                        <li style="margin-bottom: 6px;">{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($statusType === 'reupload_reviewer' && !empty($revisionNote))
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin: 18px 0;">
                <div style="font-weight: 700; color: #991b1b; margin-bottom: 6px;">Catatan Revisi / Keterangan Kekurangan ({{ $reviewerLabel ?: 'Pemeriksa' }}):</div>
                <div style="color: #7f1d1d; font-size: 14px; white-space: pre-line;">{{ $revisionNote }}</div>
            </div>
        @endif

        @if(in_array($statusType, ['reupload_ai', 'reupload_reviewer']))
            <p>Silakan login ke sistem EREPO melalui akun salah satu anggota kelompok, perbaiki dokumen sesuai keterangan di atas, lalu klik tombol <strong>Upload Ulang</strong>.</p>
        @else
            <p>Kami akan mengirimkan pemberitahuan email berikutnya apabila terdapat pembaruan status verifikasi dokumen kelompok Anda.</p>
        @endif

        <p style="margin-top: 24px; margin-bottom: 0; font-size: 13px; color: #6b7280;">
            Salam hormat,<br>
            <strong>Sistem EREPO — S1 Teknik Elektro</strong>
        </p>
    </div>
</body>
</html>
