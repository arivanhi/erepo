<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Notifikasi Verifikasi Permintaan Download Source Code</title>
</head>
<body style="font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #1f2937; background-color: #f3f4f6; padding: 24px 12px; margin: 0;">
    <div style="max-width: 620px; margin: 0 auto; background: #ffffff; padding: 28px; border: 1px solid #e5e7eb; border-radius: 12px;">
        <div style="border-bottom: 2px solid #d97706; padding-bottom: 14px; margin-bottom: 20px;">
            <div style="font-size: 12px; font-weight: bold; color: #d97706; text-transform: uppercase; letter-spacing: 0.08em;">EREPO Teknik Elektro</div>
            <h2 style="color: #111827; margin: 4px 0 0 0; font-size: 20px;">Permintaan Download Source Code (Menunggu Verifikasi)</h2>
        </div>

        <p style="margin-top: 0;">Yth. Dosen Pembimbing, Koordinator TA, Admin, dan Tim Mahasiswa Pemilik,</p>
        <p>Terdapat pengajuan permohonan baru untuk mengunduh berkas <strong>Source Code (Script)</strong> pada repositori berikut:</p>

        <table style="width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px;">
            <tr>
                <td style="padding: 9px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600; width: 36%;">Judul Script</td>
                <td style="padding: 9px 12px; border: 1px solid #e5e7eb;">{{ $scriptProject->title }}</td>
            </tr>
            <tr>
                <td style="padding: 9px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600;">Kelompok &amp; Anggota</td>
                <td style="padding: 9px 12px; border: 1px solid #e5e7eb;">{{ $scriptProject->group_id }} — {{ $memberNames }}</td>
            </tr>
            <tr>
                <td style="padding: 9px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600;">Nama Pemohon</td>
                <td style="padding: 9px 12px; border: 1px solid #e5e7eb;">{{ $downloadRequest->requester_name }}</td>
            </tr>
            <tr>
                <td style="padding: 9px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600;">Email UDINUS Pemohon</td>
                <td style="padding: 9px 12px; border: 1px solid #e5e7eb;">{{ $downloadRequest->requester_email }}</td>
            </tr>
            <tr>
                <td style="padding: 9px 12px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: 600;">Keperluan / Alasan</td>
                <td style="padding: 9px 12px; border: 1px solid #e5e7eb; white-space: pre-line;">{{ $downloadRequest->purpose }}</td>
            </tr>
        </table>

        <p style="font-size: 14px; color: #1f2937;">
            Bagi <strong>Dosen Pembimbing, Koordinator TA, atau Admin</strong>, silakan login ke akun EREPO Anda dan buka menu <strong>Verifikasi Permintaan Download</strong> untuk menyetujui atau menolak permohonan ini:
        </p>

        <p style="text-align: center; margin: 22px 0;">
            <a href="{{ $approvalPageUrl }}" style="display: inline-block; background: #4f46e5; color: #ffffff; text-decoration: none; font-weight: 700; padding: 12px 24px; border-radius: 8px; font-size: 14px;">
                Buka Menu Verifikasi Permintaan Download
            </a>
        </p>

        <p style="font-size: 13px; color: #4b5563;">Setelah diverifikasi melalui akun Dosen Pembimbing / Koordinator TA / Admin, sistem otomatis mengirimkan token unduhan yang berlaku selama <strong>1 hari (24 jam)</strong> ke email pemohon ({{ $downloadRequest->requester_email }}).</p>

        <p style="margin-top: 24px; margin-bottom: 0; font-size: 13px; color: #6b7280;">
            Salam hormat,<br>
            <strong>Sistem EREPO — S1 Teknik Elektro</strong>
        </p>
    </div>
</body>
</html>
