<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Dokumen Capstone Diterima dengan Catatan</title>
</head>
<body style="font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #1f2937; background-color: #f3f4f6; padding: 24px 12px; margin: 0;">
    <div style="max-width: 620px; margin: 0 auto; background: #ffffff; padding: 28px; border: 1px solid #e5e7eb; border-radius: 12px;">
        <div style="border-bottom: 2px solid #059669; padding-bottom: 14px; margin-bottom: 20px;">
            <div style="font-size: 12px; font-weight: bold; color: #059669; text-transform: uppercase; letter-spacing: 0.08em;">EREPO Teknik Elektro</div>
            <h2 style="color: #111827; margin: 4px 0 0 0; font-size: 20px;">Dokumen Capstone Diterima dengan Catatan</h2>
        </div>

        <p style="margin-top: 0;">Yth. Dosen Pembimbing (<strong>{{ $dosenName }}</strong>) dan Tim Mahasiswa (<strong>{{ $memberNames }}</strong>),</p>
        <p>Dokumen laporan Capstone Design berikut telah <strong>diterima dan diverifikasi dengan catatan</strong> oleh <strong>{{ $reviewerLabel }}</strong> meskipun terdapat temuan pada pemeriksaan otomatis AI:</p>

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
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600;">Diterima Oleh</td>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb;">{{ $reviewerLabel }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600;">Status</td>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; color: #059669; font-weight: 700;">Approved (Dengan Catatan)</td>
            </tr>
        </table>

        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 16px; margin: 18px 0;">
            <div style="font-weight: 700; color: #065f46; margin-bottom: 6px;">Catatan Penerimaan ({{ $reviewerLabel }}):</div>
            <div style="color: #064e3b; font-size: 14px; white-space: pre-line;">{{ $acceptanceNote }}</div>
        </div>

        @if(count($aiMissingItems) > 0)
            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 14px; margin: 16px 0;">
                <div style="font-weight: 700; color: #92400e; font-size: 13px; margin-bottom: 6px;">Riwayat Catatan Pemeriksaan Awal AI:</div>
                <ul style="margin: 0; padding-left: 18px; color: #78350f; font-size: 13px;">
                    @foreach($aiMissingItems as $item)
                        <li style="margin-bottom: 4px;">{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <p style="margin-top: 24px; margin-bottom: 0; font-size: 13px; color: #6b7280;">
            Salam hormat,<br>
            <strong>Sistem EREPO — S1 Teknik Elektro</strong>
        </p>
    </div>
</body>
</html>
