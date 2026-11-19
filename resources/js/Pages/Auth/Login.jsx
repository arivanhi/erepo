import InputError from '@/Components/InputError';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Login({ status }) {
    const [loginMode, setLoginMode] = useState('capstone'); // 'capstone' | 'staff'
    const [showPassword, setShowPassword] = useState(false);
    const [requestingToken, setRequestingToken] = useState(false);

    // 1. Capstone Team Login Form (Mahasiswa)
    const {
        data: capData,
        setData: setCapData,
        post: postCapLogin,
        processing: capProcessing,
        errors: capErrors,
    } = useForm({
        group_code: '',
        token: '',
    });

    const submitCapstoneLogin = (e) => {
        e.preventDefault();
        postCapLogin(route('login.capstone.submit'));
    };

    const requestTeamEmailToken = () => {
        if (!capData.group_code.trim()) return;
        setRequestingToken(true);
        router.post(
            route('login.capstone.token'),
            { group_code: capData.group_code },
            {
                preserveState: true,
                onFinish: () => setRequestingToken(false),
            }
        );
    };

    // 2. Staff Password Login Form (Dosen, Koordinator TA, Kaprodi, Admin)
    const {
        data: staffData,
        setData: setStaffData,
        post: postStaffLogin,
        processing: staffProcessing,
        errors: staffErrors,
    } = useForm({
        login: '',
        password: '',
        remember: false,
    });

    const submitStaffLogin = (e) => {
        e.preventDefault();
        postStaffLogin(route('login'));
    };

    return (
        <div className="bg-background text-on-background min-h-screen flex flex-col font-body-md overflow-x-hidden">
            <Head title="Login — EREPO" />

            {/* Abstract Background */}
            <div aria-hidden="true" className="bg-shapes">
                <div className="shape bg-primary" style={{ width: '40vw', height: '40vw', top: '-10vw', left: '-10vw', animation: 'float 15s infinite ease-in-out alternate' }}></div>
                <div className="shape bg-tertiary-container" style={{ width: '35vw', height: '35vw', bottom: '-5vw', right: '-5vw', animation: 'float 20s infinite ease-in-out alternate-reverse' }}></div>
                <div className="shape bg-secondary-container" style={{ width: '25vw', height: '25vw', top: '40%', left: '30%', animation: 'pulse 12s infinite ease-in-out alternate' }}></div>
            </div>

            <main className="flex-grow flex flex-col lg:flex-row relative z-10">
                {/* Left Side Branding */}
                <div className="hidden lg:flex flex-col items-center justify-center w-full lg:w-[52%] p-12 text-center">
                    <img src="/images/logo/logo.png" alt="University Logo" className="w-44 h-auto mb-8 drop-shadow-[0_0_15px_rgba(255,255,255,0.2)]" onError={(e) => { e.target.style.display = 'none'; }} />
                    <h1 className="font-display-lg text-display-lg text-transparent bg-clip-text bg-gradient-to-r from-primary to-tertiary tracking-wider mb-2">
                        EREPO ELEKTRO
                    </h1>
                    <p className="font-body-lg text-body-lg text-on-surface-variant uppercase tracking-widest mt-3">
                        Portal Unggah &amp; Verifikasi Tugas Akhir
                    </p>
                    <Link
                        href={route('public.dashboard')}
                        className="mt-8 inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-white/5 border border-white/15 text-xs text-on-surface-variant hover:text-white hover:bg-white/10 transition-all"
                    >
                        <span className="material-symbols-outlined text-[16px]">arrow_back</span>
                        Kembali ke Katalog Publik Repositori
                    </Link>
                </div>

                {/* Right Side Login Panel */}
                <div className="flex items-center justify-center w-full lg:w-[48%] p-container-padding-mobile md:p-container-padding-desktop min-h-[85vh] lg:min-h-screen">
                    <div className="glass-panel w-full max-w-lg rounded-2xl p-8 md:p-10 shadow-[0_8px_32px_0_rgba(0,0,0,0.37)]">
                        <div className="text-center mb-6">
                            <h2 className="font-headline-md text-headline-md font-bold text-on-surface tracking-wide">
                                Masuk Sistem Internal
                            </h2>
                            <p className="text-xs text-on-surface-variant mt-1">
                                Pilih metode masuk sesuai peran Anda di bawah ini
                            </p>
                        </div>

                        {/* Mode Switch Tabs */}
                        <div className="grid grid-cols-2 gap-2 p-1.5 rounded-xl bg-black/30 border border-white/10 mb-6 text-xs">
                            <button
                                type="button"
                                onClick={() => setLoginMode('capstone')}
                                className={`py-2.5 px-3 rounded-lg font-semibold transition-all flex items-center justify-center gap-1.5 ${
                                    loginMode === 'capstone'
                                        ? 'bg-primary text-on-primary shadow-md'
                                        : 'text-on-surface-variant hover:text-on-surface'
                                }`}
                            >
                                <span className="material-symbols-outlined text-[16px]">groups</span>
                                Login Mahasiswa
                            </button>
                            <button
                                type="button"
                                onClick={() => setLoginMode('staff')}
                                className={`py-2.5 px-3 rounded-lg font-semibold transition-all flex items-center justify-center gap-1.5 ${
                                    loginMode === 'staff'
                                        ? 'bg-primary text-on-primary shadow-md'
                                        : 'text-on-surface-variant hover:text-on-surface'
                                }`}
                            >
                                <span className="material-symbols-outlined text-[16px]">shield_person</span>
                                Dosen / Koordinator / Admin
                            </button>
                        </div>

                        {status && (
                            <div className="mb-5 p-3.5 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-xs text-emerald-300 font-medium">
                                {status}
                            </div>
                        )}

                        {loginMode === 'capstone' ? (
                            <form onSubmit={submitCapstoneLogin} className="space-y-5">
                                <div>
                                    <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="group-code">
                                        Kode Capstone Kelompok
                                    </label>
                                    <div className="flex gap-2">
                                        <div className="relative flex-1">
                                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-on-surface-variant">
                                                <span className="material-symbols-outlined text-sm">tag</span>
                                            </div>
                                            <input
                                                id="group-code"
                                                type="text"
                                                value={capData.group_code}
                                                onChange={(e) => setCapData('group_code', e.target.value.toUpperCase())}
                                                placeholder="Contoh: E11-VI-82"
                                                className="glass-input w-full pl-9 pr-4 py-3 rounded-lg text-on-surface font-mono uppercase tracking-wider text-sm"
                                                required
                                            />
                                        </div>
                                        <button
                                            type="button"
                                            disabled={!capData.group_code.trim() || requestingToken}
                                            onClick={requestTeamEmailToken}
                                            className="px-3.5 py-2 rounded-lg bg-secondary/20 border border-secondary/40 text-secondary hover:bg-secondary/30 transition-all text-xs font-semibold shrink-0 disabled:opacity-40 flex items-center gap-1.5"
                                            title="Kirim kode token aktif 1 bulan ke email anggota kelompok"
                                        >
                                            <span className="material-symbols-outlined text-[16px]">mail</span>
                                            {requestingToken ? 'Mengirim...' : 'Minta Token'}
                                        </button>
                                    </div>
                                    <InputError message={capErrors.group_code} className="mt-1.5" />
                                    <p className="text-[11px] text-on-surface-variant mt-1.5">
                                        Masukkan Kode Capstone Anda lalu klik <b>Minta Token</b> apabila belum menerima kode token (aktif selama 1 bulan) di email anggota kelompok.
                                    </p>
                                </div>

                                <div>
                                    <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="capstone-token">
                                        Kode Token Email (Aktif 1 Bulan)
                                    </label>
                                    <div className="relative">
                                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-on-surface-variant">
                                            <span className="material-symbols-outlined text-sm">vpn_key</span>
                                        </div>
                                        <input
                                            id="capstone-token"
                                            type="text"
                                            value={capData.token}
                                            onChange={(e) => setCapData('token', e.target.value.toUpperCase())}
                                            placeholder="XXXX-XXXX"
                                            className="glass-input w-full pl-10 pr-4 py-3 rounded-lg text-on-surface font-mono tracking-widest uppercase text-sm"
                                            required
                                        />
                                    </div>
                                    <InputError message={capErrors.token} className="mt-1.5" />
                                </div>

                                <div className="pt-2">
                                    <button
                                        disabled={capProcessing}
                                        className="gradient-btn w-full py-3.5 rounded-lg font-label-sm text-label-sm uppercase flex items-center justify-center gap-2 font-bold disabled:opacity-50"
                                        type="submit"
                                    >
                                        <span>{capProcessing ? 'Memverifikasi...' : 'Masuk Sebagai Kelompok Capstone'}</span>
                                        <span className="material-symbols-outlined text-sm">arrow_forward</span>
                                    </button>
                                </div>
                            </form>
                        ) : (
                            <form onSubmit={submitStaffLogin} className="space-y-5">
                                <div>
                                    <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="login">
                                        NPP / Email
                                    </label>
                                    <div className="relative">
                                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-on-surface-variant">
                                            <span className="material-symbols-outlined text-sm">badge</span>
                                        </div>
                                        <input
                                            className="glass-input w-full pl-10 pr-4 py-3 rounded-lg text-on-surface font-body-md text-sm"
                                            id="login"
                                            name="login"
                                            type="text"
                                            value={staffData.login}
                                            onChange={(e) => setStaffData('login', e.target.value)}
                                            placeholder="Masukkan NPP atau Email Dosen / Admin"
                                            required
                                            autoComplete="username"
                                        />
                                    </div>
                                    <InputError message={staffErrors.login} className="mt-2" />
                                </div>

                                <div>
                                    <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="password">
                                        Password
                                    </label>
                                    <div className="relative">
                                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-on-surface-variant">
                                            <span className="material-symbols-outlined text-sm">lock</span>
                                        </div>
                                        <input
                                            className="glass-input w-full pl-10 pr-10 py-3 rounded-lg text-on-surface font-body-md text-sm"
                                            id="password"
                                            name="password"
                                            type={showPassword ? 'text' : 'password'}
                                            value={staffData.password}
                                            onChange={(e) => setStaffData('password', e.target.value)}
                                            placeholder="••••••••"
                                            required
                                            autoComplete="current-password"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword(!showPassword)}
                                            className="absolute inset-y-0 right-0 pr-3 flex items-center text-on-surface-variant hover:text-on-surface"
                                        >
                                            <span className="material-symbols-outlined text-sm">
                                                {showPassword ? 'visibility_off' : 'visibility'}
                                            </span>
                                        </button>
                                    </div>
                                    <InputError message={staffErrors.password} className="mt-2" />
                                </div>

                                <div className="pt-2">
                                    <button
                                        disabled={staffProcessing}
                                        className="gradient-btn w-full py-3.5 rounded-lg font-label-sm text-label-sm uppercase flex items-center justify-center gap-2 font-bold disabled:opacity-50"
                                        type="submit"
                                    >
                                        <span>{staffProcessing ? 'Memproses...' : 'Login Staff'}</span>
                                        <span className="material-symbols-outlined text-sm">arrow_forward</span>
                                    </button>
                                </div>

                                <div className="text-center pt-2">
                                    <Link href={route('password.request')} className="text-xs text-secondary hover:text-primary transition-colors">
                                        Lupa Password Staff?
                                    </Link>
                                </div>
                            </form>
                        )}

                        <div className="mt-6 pt-4 border-t border-white/10 text-center lg:hidden">
                            <Link href={route('public.dashboard')} className="text-xs text-on-surface-variant hover:text-white inline-flex items-center gap-1">
                                <span className="material-symbols-outlined text-[14px]">arrow_back</span>
                                Kembali ke Katalog Publik
                            </Link>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    );
}
