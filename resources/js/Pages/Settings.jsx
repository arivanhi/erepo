import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Settings({ smtpSettings = null }) {
    const user = usePage().props.auth.user;
    const isAdmin = user.role === 'admin';
    const isPlaceholderAdminEmail = isAdmin && user.email?.endsWith('@example.com');

    const formatRoleLabel = (role) => (role === 'koordinator_ta' ? 'Koordinator TA' : role);

    // 1. Profile Form
    const {
        data: profileData,
        setData: setProfileData,
        patch: patchProfile,
        processing: profileProcessing,
        errors: profileErrors,
    } = useForm({
        name: user.name || '',
        email: user.email || '',
    });

    const submitProfile = (e) => {
        e.preventDefault();
        patchProfile(route('settings.profile.update'), { preserveScroll: true });
    };

    // 2. Password Form
    const [showCurrentPassword, setShowCurrentPassword] = useState(false);
    const [showNewPassword, setShowNewPassword] = useState(false);
    const {
        data: pwdData,
        setData: setPwdData,
        put: putPassword,
        processing: pwdProcessing,
        errors: pwdErrors,
        reset: resetPwd,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submitPassword = (e) => {
        e.preventDefault();
        putPassword(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => resetPwd(),
        });
    };

    // 3. Google SMTP Form (Admin only)
    const [showSmtpPassword, setShowSmtpPassword] = useState(false);
    const {
        data: smtpData,
        setData: setSmtpData,
        post: postSmtp,
        processing: smtpProcessing,
        errors: smtpErrors,
    } = useForm({
        host: smtpSettings?.host || 'smtp.gmail.com',
        port: smtpSettings?.port || 587,
        encryption: smtpSettings?.encryption || 'tls',
        username: smtpSettings?.username || '',
        password: '',
        from_address: smtpSettings?.from_address || '',
        from_name: smtpSettings?.from_name || 'EREPO Teknik Elektro',
    });

    const submitSmtp = (e) => {
        e.preventDefault();
        postSmtp(route('settings.smtp.update'), {
            preserveScroll: true,
            onSuccess: () => setSmtpData('password', ''),
        });
    };

    // 4. Test SMTP Email Form (Admin only)
    const {
        data: testData,
        setData: setTestData,
        post: postTest,
        processing: testProcessing,
        errors: testErrors,
    } = useForm({
        test_email: isPlaceholderAdminEmail ? (smtpSettings?.username || '') : (user.email || ''),
    });

    const submitTestEmail = (e) => {
        e.preventDefault();
        postTest(route('settings.smtp.test'), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Pengaturan" />

            {/* Background Elements */}
            <div className="fixed inset-0 pointer-events-none z-[-1] overflow-hidden">
                <div className="absolute top-[-10%] right-[-10%] w-[50%] h-[50%] bg-primary opacity-20 blur-[120px] rounded-full"></div>
                <div className="absolute bottom-[-10%] left-[-10%] w-[50%] h-[50%] bg-tertiary opacity-20 blur-[120px] rounded-full"></div>
            </div>

            <div className="max-w-4xl mx-auto w-full pb-12">
                <header className="mb-10">
                    <h1 className="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-background mb-2">
                        Pengaturan
                    </h1>
                    <p className="text-on-surface-variant font-body-lg text-body-lg">
                        {isAdmin
                            ? 'Kelola profil akun, keamanan kata sandi, dan konfigurasi pengiriman email Google SMTP.'
                            : 'Kelola informasi profil dan keamanan kata sandi akun Anda.'}
                    </p>
                </header>

                <div className="grid grid-cols-1 md:grid-cols-12 gap-8">
                    {/* Left Column (Summary) */}
                    <div className="md:col-span-4 space-y-6 hidden md:block">
                        <div className="glass-panel rounded-xl p-6 flex flex-col items-center text-center">
                            <div className="w-24 h-24 rounded-full overflow-hidden mb-4 border-2 border-primary">
                                <img
                                    alt="User Portrait"
                                    className="w-full h-full object-cover"
                                    src={`https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=random`}
                                />
                            </div>
                            <h3 className="font-headline-md text-headline-md text-on-background capitalize">
                                {formatRoleLabel(user.role)} Aktif
                            </h3>
                            <p className="text-on-surface-variant font-body-md text-body-md mt-1 break-all">
                                {user.email}
                            </p>
                        </div>

                        {isAdmin && smtpSettings && (
                            <div className="glass-panel rounded-xl p-5 space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">
                                        Status Email SMTP
                                    </span>
                                    <span
                                        className={`px-2.5 py-0.5 rounded-full text-xs font-semibold border ${
                                            smtpSettings.is_configured
                                                ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30'
                                                : 'bg-tertiary/15 text-tertiary border-tertiary/30'
                                        }`}
                                    >
                                        {smtpSettings.is_configured ? 'Aktif' : 'Belum Diisi'}
                                    </span>
                                </div>
                                <p className="text-xs text-on-surface-variant">
                                    {smtpSettings.is_configured
                                        ? `Mengirim melalui ${smtpSettings.username}`
                                        : 'Isi akun Google & App Password di samping agar notifikasi otomatis aktif.'}
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Right Column (Forms) */}
                    <div className="md:col-span-8 space-y-8">
                        {/* Google SMTP Section (Admin Only) */}
                        {isAdmin && smtpSettings && (
                            <section className="glass-panel rounded-xl p-8 transition-all duration-300 border border-primary/25">
                                <div className="flex flex-wrap items-center justify-between gap-3 mb-6 border-b border-white/10 pb-4">
                                    <div className="flex items-center gap-3">
                                        <div className="p-2 bg-primary/20 rounded-lg text-primary">
                                            <span className="material-symbols-outlined">mail</span>
                                        </div>
                                        <div>
                                            <h2 className="font-headline-md text-headline-md text-on-background">
                                                Pengaturan Google SMTP
                                            </h2>
                                            <p className="text-xs text-on-surface-variant">
                                                Digunakan untuk mengirim email notifikasi ke Dosen Pembimbing, Koordinator TA, Admin, dan Mahasiswa.
                                            </p>
                                        </div>
                                    </div>
                                    <span
                                        className={`px-3 py-1 rounded-full text-xs font-semibold border inline-flex items-center gap-1 ${
                                            smtpSettings.is_configured
                                                ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30'
                                                : 'bg-tertiary/15 text-tertiary border-tertiary/30'
                                        }`}
                                    >
                                        <span className="material-symbols-outlined text-[14px]">
                                            {smtpSettings.is_configured ? 'check_circle' : 'info'}
                                        </span>
                                        {smtpSettings.is_configured ? 'Siap Mengirim' : 'Perlu Konfigurasi'}
                                    </span>
                                </div>

                                <div className="mb-6 p-4 rounded-xl bg-secondary/10 border border-secondary/25 text-xs text-on-surface-variant space-y-1">
                                    <div className="font-semibold text-secondary flex items-center gap-1.5">
                                        <span className="material-symbols-outlined text-[16px]">tips_and_updates</span>
                                        Panduan Singkat Akun Google:
                                    </div>
                                    <p>
                                        Gunakan alamat Gmail Anda pada kolom <b>Email Akun Google</b> dan masukkan <b>16 karakter App Password</b> (dari menu <i>Google Account → Security → 2-Step Verification → App Passwords</i>). Spasi pada App Password akan dibersihkan otomatis.
                                    </p>
                                </div>

                                {isPlaceholderAdminEmail && (
                                    <div className="mb-6 p-4 rounded-xl bg-tertiary/10 border border-tertiary/30 text-xs text-on-surface-variant">
                                        <b className="text-tertiary">Catatan Email Admin:</b> Email akun Admin Anda saat ini masih <code className="text-on-surface">{user.email}</code>. Ubah email Admin pada bagian <b>Informasi Profil</b> di bawah ke alamat email aktif Anda apabila Anda ingin ikut menerima email notifikasi Admin.
                                    </div>
                                )}

                                <form onSubmit={submitSmtp} className="space-y-5">
                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div className="sm:col-span-1">
                                            <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="smtp-host">
                                                SMTP Host
                                            </label>
                                            <input
                                                id="smtp-host"
                                                type="text"
                                                value={smtpData.host}
                                                onChange={(e) => setSmtpData('host', e.target.value)}
                                                className="glass-input w-full rounded-lg px-3.5 py-2.5 text-sm"
                                                placeholder="smtp.gmail.com"
                                                required
                                            />
                                            {smtpErrors.host && <p className="text-xs text-error mt-1">{smtpErrors.host}</p>}
                                        </div>

                                        <div>
                                            <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="smtp-port">
                                                Port
                                            </label>
                                            <input
                                                id="smtp-port"
                                                type="number"
                                                value={smtpData.port}
                                                onChange={(e) => setSmtpData('port', Number(e.target.value))}
                                                className="glass-input w-full rounded-lg px-3.5 py-2.5 text-sm"
                                                placeholder="587"
                                                required
                                            />
                                            {smtpErrors.port && <p className="text-xs text-error mt-1">{smtpErrors.port}</p>}
                                        </div>

                                        <div>
                                            <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="smtp-encryption">
                                                Enkripsi
                                            </label>
                                            <select
                                                id="smtp-encryption"
                                                value={smtpData.encryption}
                                                onChange={(e) => {
                                                    const enc = e.target.value;
                                                    setSmtpData((prev) => ({
                                                        ...prev,
                                                        encryption: enc,
                                                        port: enc === 'ssl' && prev.port === 587 ? 465 : enc === 'tls' && prev.port === 465 ? 587 : prev.port,
                                                    }));
                                                }}
                                                className="glass-input w-full rounded-lg px-3.5 py-2.5 text-sm [&>option]:bg-surface-container [&>option]:text-on-surface"
                                            >
                                                <option value="tls">TLS / STARTTLS (587)</option>
                                                <option value="ssl">SSL / SMTPS (465)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="smtp-username">
                                                Email Akun Google (Username)
                                            </label>
                                            <input
                                                id="smtp-username"
                                                type="email"
                                                value={smtpData.username}
                                                onChange={(e) => {
                                                    const val = e.target.value;
                                                    setSmtpData((prev) => ({
                                                        ...prev,
                                                        username: val,
                                                        from_address: !prev.from_address || prev.from_address === prev.username ? val : prev.from_address,
                                                    }));
                                                }}
                                                className="glass-input w-full rounded-lg px-3.5 py-2.5 text-sm"
                                                placeholder="contoh@gmail.com"
                                                required
                                            />
                                            {smtpErrors.username && <p className="text-xs text-error mt-1">{smtpErrors.username}</p>}
                                        </div>

                                        <div>
                                            <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="smtp-password">
                                                Google App Password
                                            </label>
                                            <div className="relative">
                                                <input
                                                    id="smtp-password"
                                                    type={showSmtpPassword ? 'text' : 'password'}
                                                    value={smtpData.password}
                                                    onChange={(e) => setSmtpData('password', e.target.value)}
                                                    className="glass-input w-full rounded-lg pl-3.5 pr-10 py-2.5 text-sm"
                                                    placeholder={
                                                        smtpSettings.has_password
                                                            ? `${smtpSettings.masked_password} (Tersimpan)`
                                                            : '16 digit App Password Google'
                                                    }
                                                    required={!smtpSettings.has_password}
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => setShowSmtpPassword(!showSmtpPassword)}
                                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary"
                                                >
                                                    <span className="material-symbols-outlined text-[18px]">
                                                        {showSmtpPassword ? 'visibility_off' : 'visibility'}
                                                    </span>
                                                </button>
                                            </div>
                                            {smtpSettings.has_password && (
                                                <p className="text-[11px] text-on-surface-variant mt-1">
                                                    Kosongkan jika tidak ingin mengganti App Password yang sudah tersimpan.
                                                </p>
                                            )}
                                            {smtpErrors.password && <p className="text-xs text-error mt-1">{smtpErrors.password}</p>}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="smtp-from-address">
                                                Alamat Email Pengirim (From)
                                            </label>
                                            <input
                                                id="smtp-from-address"
                                                type="email"
                                                value={smtpData.from_address}
                                                onChange={(e) => setSmtpData('from_address', e.target.value)}
                                                className="glass-input w-full rounded-lg px-3.5 py-2.5 text-sm"
                                                placeholder="Sama dengan Email Akun Google"
                                            />
                                            {smtpErrors.from_address && <p className="text-xs text-error mt-1">{smtpErrors.from_address}</p>}
                                        </div>

                                        <div>
                                            <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="smtp-from-name">
                                                Nama Pengirim Email
                                            </label>
                                            <input
                                                id="smtp-from-name"
                                                type="text"
                                                value={smtpData.from_name}
                                                onChange={(e) => setSmtpData('from_name', e.target.value)}
                                                className="glass-input w-full rounded-lg px-3.5 py-2.5 text-sm"
                                                placeholder="EREPO Teknik Elektro"
                                                required
                                            />
                                            {smtpErrors.from_name && <p className="text-xs text-error mt-1">{smtpErrors.from_name}</p>}
                                        </div>
                                    </div>

                                    <div className="flex justify-end pt-2">
                                        <button
                                            disabled={smtpProcessing}
                                            className="gradient-btn px-6 py-2.5 rounded-lg font-label-sm text-label-sm font-semibold flex items-center gap-2 disabled:opacity-50"
                                            type="submit"
                                        >
                                            <span className="material-symbols-outlined text-[18px]">save</span>
                                            {smtpProcessing ? 'Menyimpan...' : 'Simpan Pengaturan SMTP'}
                                        </button>
                                    </div>
                                </form>

                                {/* Test Email Sub-Panel */}
                                <div className="mt-7 pt-6 border-t border-white/10">
                                    <h3 className="font-body-md font-semibold text-on-surface mb-1 flex items-center gap-2">
                                        <span className="material-symbols-outlined text-[18px] text-secondary">forward_to_inbox</span>
                                        Uji Coba Pengiriman Email (Tes Koneksi)
                                    </h3>
                                    <p className="text-xs text-on-surface-variant mb-4">
                                        Setelah menyimpan pengaturan di atas, kirimkan email tes untuk memastikan akun Google Anda sudah berhasil terhubung.
                                    </p>
                                    <form onSubmit={submitTestEmail} className="flex flex-col sm:flex-row gap-3">
                                        <div className="flex-1">
                                            <input
                                                type="email"
                                                value={testData.test_email}
                                                onChange={(e) => setTestData('test_email', e.target.value)}
                                                placeholder="Masukkan alamat email tujuan tes..."
                                                className="glass-input w-full rounded-lg px-4 py-2.5 text-sm"
                                                required
                                            />
                                            {testErrors.test_email && (
                                                <p className="text-xs text-error mt-1">{testErrors.test_email}</p>
                                            )}
                                        </div>
                                        <button
                                            type="submit"
                                            disabled={testProcessing}
                                            className="px-5 py-2.5 rounded-lg bg-secondary/20 border border-secondary/40 text-secondary hover:bg-secondary/30 transition-all font-label-sm text-label-sm font-semibold flex items-center justify-center gap-2 shrink-0 disabled:opacity-50"
                                        >
                                            <span className="material-symbols-outlined text-[18px]">send</span>
                                            {testProcessing ? 'Mengirim Tes...' : 'Kirim Email Tes'}
                                        </button>
                                    </form>
                                </div>
                            </section>
                        )}

                        {/* Profile Information Section */}
                        <section className="glass-panel rounded-xl p-8 transition-all duration-300">
                            <div className="flex items-center gap-3 mb-6 border-b border-white/10 pb-4">
                                <div className="p-2 bg-primary/20 rounded-lg text-primary">
                                    <span className="material-symbols-outlined">person</span>
                                </div>
                                <h2 className="font-headline-md text-headline-md text-on-background">Informasi Profil</h2>
                            </div>
                            <form onSubmit={submitProfile} className="space-y-5">
                                <div>
                                    <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="profile-name">
                                        Nama Lengkap
                                    </label>
                                    <div className="relative">
                                        <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">badge</span>
                                        <input
                                            className="glass-input w-full rounded-lg pl-10 pr-4 py-3 font-body-md text-body-md"
                                            id="profile-name"
                                            type="text"
                                            value={profileData.name}
                                            onChange={(e) => setProfileData('name', e.target.value)}
                                            required
                                        />
                                    </div>
                                    {profileErrors.name && <p className="text-xs text-error mt-1">{profileErrors.name}</p>}
                                </div>

                                <div>
                                    <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="profile-email">
                                        Alamat Email Notifikasi
                                    </label>
                                    <div className="relative">
                                        <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">alternate_email</span>
                                        <input
                                            className="glass-input w-full rounded-lg pl-10 pr-4 py-3 font-body-md text-body-md"
                                            id="profile-email"
                                            type="email"
                                            value={profileData.email}
                                            onChange={(e) => setProfileData('email', e.target.value)}
                                            required
                                        />
                                    </div>
                                    {profileErrors.email && <p className="text-xs text-error mt-1">{profileErrors.email}</p>}
                                </div>

                                <div className="flex justify-end pt-2">
                                    <button
                                        disabled={profileProcessing}
                                        className="gradient-btn px-6 py-2.5 rounded-lg font-label-sm text-label-sm font-semibold flex items-center gap-2 disabled:opacity-50"
                                        type="submit"
                                    >
                                        <span className="material-symbols-outlined text-[18px]">save</span>
                                        {profileProcessing ? 'Menyimpan...' : 'Simpan Profil'}
                                    </button>
                                </div>
                            </form>
                        </section>

                        {/* Security Section */}
                        <section className="glass-panel rounded-xl p-8 transition-all duration-300">
                            <div className="flex items-center gap-3 mb-6 border-b border-white/10 pb-4">
                                <div className="p-2 bg-tertiary/20 rounded-lg text-tertiary">
                                    <span className="material-symbols-outlined">lock</span>
                                </div>
                                <h2 className="font-headline-md text-headline-md text-on-background">Keamanan Kata Sandi</h2>
                            </div>
                            <form onSubmit={submitPassword} className="space-y-6">
                                <div>
                                    <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="current-password">
                                        Kata Sandi Saat Ini
                                    </label>
                                    <div className="relative">
                                        <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">key</span>
                                        <input
                                            className="glass-input w-full rounded-lg pl-10 pr-10 py-3 font-body-md text-body-md"
                                            id="current-password"
                                            placeholder="••••••••"
                                            type={showCurrentPassword ? 'text' : 'password'}
                                            value={pwdData.current_password}
                                            onChange={(e) => setPwdData('current_password', e.target.value)}
                                            required
                                        />
                                        <button
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary"
                                            type="button"
                                            onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                                        >
                                            <span className="material-symbols-outlined">
                                                {showCurrentPassword ? 'visibility_off' : 'visibility'}
                                            </span>
                                        </button>
                                    </div>
                                    {pwdErrors.current_password && <p className="text-xs text-error mt-1">{pwdErrors.current_password}</p>}
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="new-password">
                                            Kata Sandi Baru
                                        </label>
                                        <div className="relative">
                                            <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">lock_reset</span>
                                            <input
                                                className="glass-input w-full rounded-lg pl-10 pr-10 py-3 font-body-md text-body-md"
                                                id="new-password"
                                                placeholder="••••••••"
                                                type={showNewPassword ? 'text' : 'password'}
                                                value={pwdData.password}
                                                onChange={(e) => setPwdData('password', e.target.value)}
                                                required
                                            />
                                            <button
                                                className="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary"
                                                type="button"
                                                onClick={() => setShowNewPassword(!showNewPassword)}
                                            >
                                                <span className="material-symbols-outlined">
                                                    {showNewPassword ? 'visibility_off' : 'visibility'}
                                                </span>
                                            </button>
                                        </div>
                                        {pwdErrors.password && <p className="text-xs text-error mt-1">{pwdErrors.password}</p>}
                                    </div>
                                    <div>
                                        <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="confirm-password">
                                            Konfirmasi Kata Sandi Baru
                                        </label>
                                        <div className="relative">
                                            <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">lock_clock</span>
                                            <input
                                                className="glass-input w-full rounded-lg pl-10 pr-4 py-3 font-body-md text-body-md"
                                                id="confirm-password"
                                                placeholder="••••••••"
                                                type={showNewPassword ? 'text' : 'password'}
                                                value={pwdData.password_confirmation}
                                                onChange={(e) => setPwdData('password_confirmation', e.target.value)}
                                                required
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="flex justify-end pt-2">
                                    <button
                                        disabled={pwdProcessing}
                                        className="gradient-btn px-6 py-2.5 rounded-lg font-label-sm text-label-sm font-semibold flex items-center gap-2 disabled:opacity-50"
                                        type="submit"
                                    >
                                        <span className="material-symbols-outlined text-[18px]">update</span>
                                        {pwdProcessing ? 'Memperbarui...' : 'Perbarui Kata Sandi'}
                                    </button>
                                </div>
                            </form>
                        </section>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
