<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Tes Google SMTP EREPO</title>
</head>
<body style="font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #1f2937; background-color: #f3f4f6; padding: 24px 12px; margin: 0;">
    <div style="max-width: 580px; margin: 0 auto; background: #ffffff; padding: 28px; border: 1px solid #e5e7eb; border-radius: 12px;">
        <h2 style="color: #059669; margin-top: 0;">Koneksi Google SMTP EREPO Berhasil!</h2>
        <p>Email ini dikirim secara otomatis dari halaman Pengaturan Admin EREPO untuk memastikan konfigurasi pengiriman email aktif.</p>
        <ul style="font-size: 14px; color: #374151;">
            <li><strong>Server SMTP:</strong> {{ $smtpHost }}</li>
            <li><strong>Akun Pengirim:</strong> {{ $smtpUsername }}</li>
            <li><strong>Diuji Oleh:</strong> {{ $triggeredByName }}</li>
            <li><strong>Waktu Pengiriman:</strong> {{ now()->timezone('Asia/Jakarta')->format('d M Y H:i:s') }} WIB</li>
        </ul>
        <p style="margin-bottom: 0; font-size: 13px; color: #6b7280;">Sistem notifikasi verifikasi, revisi AI/Dosen/Koordinator, dan persetujuan kini siap digunakan.</p>
    </div>
</body>
</html>
