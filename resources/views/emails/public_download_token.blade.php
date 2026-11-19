<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Token Download {{ $documentTypeLabel }}</title>
</head>
<body style="font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #1f2937; background-color: #f3f4f6; padding: 24px 12px; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 28px; border: 1px solid #e5e7eb; border-radius: 12px;">
        <div style="border-bottom: 2px solid #059669; padding-bottom: 14px; margin-bottom: 20px;">
            <div style="font-size: 12px; font-weight: bold; color: #059669; text-transform: uppercase; letter-spacing: 0.08em;">EREPO Teknik Elektro</div>
            <h2 style="color: #111827; margin: 4px 0 0 0; font-size: 20px;">Token Download {{ $documentTypeLabel }}</h2>
        </div>

        <p style="margin-top: 0;">Yth. Sivitas Akademika UDINUS,</p>
        <p>Berikut token akses unduhan untuk dokumen <strong>{{ $projectTitle }}</strong> (Kelompok <strong>{{ $groupCode }}</strong>):</p>

        <div style="background: #ecfdf5; border: 2px dashed #10b981; border-radius: 10px; padding: 18px; text-align: center; margin: 20px 0;">
            <div style="font-size: 12px; color: #047857; text-transform: uppercase; font-weight: 700;">Kode Token Download (Aktif 1 Hari)</div>
            <div style="font-size: 26px; font-weight: 800; color: #065f46; letter-spacing: 0.14em; font-family: monospace; margin: 6px 0;">{{ $downloadCode }}</div>
            <div style="font-size: 12px; color: #4b5563;">Kedaluwarsa pada: <strong>{{ $expiresAtFormatted }} WIB</strong></div>
        </div>

        <p style="text-align: center; margin: 22px 0;">
            <a href="{{ $directDownloadUrl }}" style="display: inline-block; background: #059669; color: #ffffff; text-decoration: none; font-weight: 700; padding: 12px 24px; border-radius: 8px; font-size: 14px;">
                Unduh Berkas Sekarang
            </a>
        </p>

        <p style="font-size: 13px; color: #4b5563;">Anda dapat mengklik tombol di atas secara langsung atau memasukkan kode token <code>{{ $downloadCode }}</code> pada jendela unduhan EREPO sebelum masa berlaku 24 jam berakhir.</p>

        <p style="margin-top: 24px; margin-bottom: 0; font-size: 13px; color: #6b7280;">
            Salam hormat,<br>
            <strong>Sistem EREPO — S1 Teknik Elektro</strong>
        </p>
    </div>
</body>
</html>
