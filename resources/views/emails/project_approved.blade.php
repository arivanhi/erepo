<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Dokumen {{ $projectType }} Telah Diverifikasi</title>
</head>
<body style="font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #1f2937; background-color: #f3f4f6; padding: 24px 12px; margin: 0;">
    <div style="max-width: 620px; margin: 0 auto; background: #ffffff; padding: 28px; border: 1px solid #e5e7eb; border-radius: 12px;">
        <div style="border-bottom: 2px solid #059669; padding-bottom: 14px; margin-bottom: 20px;">
            <div style="font-size: 12px; font-weight: bold; color: #059669; text-transform: uppercase; letter-spacing: 0.08em;">EREPO Teknik Elektro</div>
            <h2 style="color: #111827; margin: 4px 0 0 0; font-size: 20px;">Dokumen Telah Diverifikasi & Disetujui</h2>
        </div>

        <p style="margin-top: 0;">Selamat Tim Mahasiswa (<strong>{{ $memberNames }}</strong>),</p>
        <p>Unggahan dokumen <strong>{{ $projectType }}</strong> kelompok Anda telah selesai diperiksa dan resmi <strong>Diverifikasi / Disetujui (Approved)</strong> pada repositori EREPO.</p>

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
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600;">Anggota Kelompok</td>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb;">{{ $memberNames }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600;">Diverifikasi Oleh</td>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb;">{{ $reviewerLabel }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600;">Status</td>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; color: #059669; font-weight: 700;">Approved (Terverifikasi)</td>
            </tr>
        </table>

        <p>Dokumen kelompok Anda kini telah tercatat resmi pada repositori digital EREPO.</p>

        <p style="margin-top: 24px; margin-bottom: 0; font-size: 13px; color: #6b7280;">
            Salam hormat,<br>
            <strong>Sistem EREPO — S1 Teknik Elektro</strong>
        </p>
    </div>
</body>
</html>
