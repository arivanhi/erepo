<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Token Login Kelompok Capstone {{ $team->group_code }}</title>
</head>
<body style="font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #1f2937; background-color: #f3f4f6; padding: 24px 12px; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 28px; border: 1px solid #e5e7eb; border-radius: 12px;">
        <div style="border-bottom: 2px solid #4f46e5; padding-bottom: 14px; margin-bottom: 20px;">
            <div style="font-size: 12px; font-weight: bold; color: #4f46e5; text-transform: uppercase; letter-spacing: 0.08em;">EREPO Teknik Elektro</div>
            <h2 style="color: #111827; margin: 4px 0 0 0; font-size: 20px;">Kode Token Login Kelompok Capstone</h2>
        </div>

        <p style="margin-top: 0;">Halo Tim <strong>{{ $team->group_code }}</strong> ({{ $memberNames }}),</p>
        <p>Berikut adalah kredensial login kelompok Capstone Anda pada sistem EREPO untuk mengunggah berkas laporan PDF &amp; Source Code:</p>

        <div style="background: #eef2ff; border: 2px dashed #6366f1; border-radius: 10px; padding: 18px; text-align: center; margin: 20px 0;">
            <div style="font-size: 12px; color: #4338ca; text-transform: uppercase; font-weight: 700; letter-spacing: 0.08em;">Kode Capstone</div>
            <div style="font-size: 20px; font-weight: 800; color: #111827; margin-bottom: 12px; font-family: monospace;">{{ $team->group_code }}</div>

            <div style="font-size: 12px; color: #4338ca; text-transform: uppercase; font-weight: 700; letter-spacing: 0.08em;">Token Login (Aktif 1 Bulan)</div>
            <div style="font-size: 28px; font-weight: 800; color: #4f46e5; letter-spacing: 0.14em; font-family: monospace;">{{ $plainToken }}</div>
            <div style="font-size: 12px; color: #4b5563; margin-top: 8px;">Berlaku sampai: <strong>{{ $expiresAtFormatted }} WIB</strong></div>
        </div>

        <p style="font-size: 14px;">Buka halaman <strong>/login</strong> pada aplikasi EREPO, pilih tab <strong>Login Mahasiswa (Kode Capstone)</strong>, lalu masukkan Kode Capstone dan Token di atas. Token yang sama dapat dipakai berulang kali oleh seluruh anggota tim selama masa aktif 1 bulan.</p>

        <p style="margin-top: 24px; margin-bottom: 0; font-size: 13px; color: #6b7280;">
            Salam hormat,<br>
            <strong>Sistem EREPO — S1 Teknik Elektro</strong>
        </p>
    </div>
</body>
</html>
