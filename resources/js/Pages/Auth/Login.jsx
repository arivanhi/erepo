import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Login({ status }) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        login: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'));
    };

    return (
        <div className="bg-background text-on-background min-h-screen flex flex-col font-body-md overflow-x-hidden">
            <Head title="Login" />

            {/* Abstract Background */}
            <div aria-hidden="true" className="bg-shapes">
                <div className="shape bg-primary" style={{ width: '40vw', height: '40vw', top: '-10vw', left: '-10vw', animation: 'float 15s infinite ease-in-out alternate' }}></div>
                <div className="shape bg-tertiary-container" style={{ width: '35vw', height: '35vw', bottom: '-5vw', right: '-5vw', animation: 'float 20s infinite ease-in-out alternate-reverse' }}></div>
                <div className="shape bg-secondary-container" style={{ width: '25vw', height: '25vw', top: '40%', left: '30%', animation: 'pulse 12s infinite ease-in-out alternate' }}></div>
            </div>

            {/* Main Content Area */}
            <main className="flex-grow flex flex-col lg:flex-row relative z-10">
                {/* Left Side Branding */}
                <div className="hidden lg:flex flex-col items-center justify-center w-full lg:w-[55%] p-12 text-center">
                    <img src="/images/logo/logo.png" alt="University Logo" className="w-48 h-auto mb-8 drop-shadow-[0_0_15px_rgba(255,255,255,0.2)]" onError={(e) => { e.target.style.display='none'; }} />
                    <h1 className="font-display-lg text-display-lg text-transparent bg-clip-text bg-gradient-to-r from-primary to-tertiary tracking-wider mb-2">KNOWLEDGE BANK</h1>
                    <p className="font-body-lg text-body-lg text-on-surface-variant uppercase tracking-widest mt-4">University Digital Repository</p>
                </div>

                {/* Right Side Login Panel */}
                <div className="flex items-center justify-center w-full lg:w-[45%] p-container-padding-mobile md:p-container-padding-desktop min-h-[80vh] lg:min-h-screen">
                    <div className="glass-panel w-full max-w-lg lg:max-w-md rounded-xl p-8 md:p-12 shadow-[0_8px_32px_0_rgba(0,0,0,0.37)]">
                        {/* Header */}
                        <div className="text-center mb-10 lg:hidden">
                            <h1 className="font-display-lg-mobile text-transparent bg-clip-text bg-gradient-to-r from-primary to-tertiary tracking-wider mb-2">LOGIN</h1>
                            <p className="font-label-sm text-on-surface-variant uppercase tracking-widest">KNOWLEDGE BANK</p>
                        </div>
                        <div className="hidden lg:block text-center mb-10">
                            <h2 className="font-display-lg-mobile text-on-surface tracking-wider mb-2">Login</h2>
                            <p className="font-label-sm text-on-surface-variant uppercase tracking-widest">Welcome Back</p>
                        </div>

                        {status && (
                            <div className="mb-4 text-sm font-medium text-green-600">
                                {status}
                            </div>
                        )}

                        {/* Form */}
                        <form onSubmit={submit} className="space-y-6">
                            <div>
                                <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="login">NIM / NPP / Email</label>
                                <div className="relative">
                                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-on-surface-variant">
                                        <span className="material-symbols-outlined text-sm">badge</span>
                                    </div>
                                    <input 
                                        className="glass-input w-full pl-10 pr-4 py-3 rounded-lg text-on-surface font-body-md placeholder-on-surface-variant/50 focus:ring-0" 
                                        id="login" 
                                        name="login" 
                                        type="text" 
                                        value={data.login}
                                        onChange={(e) => setData('login', e.target.value)}
                                        placeholder="Masukkan NIM / NPP / Email" 
                                        required 
                                        autoComplete="username" 
                                    />
                                </div>
                                <InputError message={errors.login} className="mt-2" />
                            </div>

                            <div>
                                <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="password">Password</label>
                                <div className="relative">
                                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-on-surface-variant">
                                        <span className="material-symbols-outlined text-sm">lock</span>
                                    </div>
                                    <input 
                                        className="glass-input w-full pl-10 pr-10 py-3 rounded-lg text-on-surface font-body-md placeholder-on-surface-variant/50 focus:ring-0" 
                                        id="password" 
                                        name="password" 
                                        type={showPassword ? 'text' : 'password'} 
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="••••••••" 
                                        required 
                                        autoComplete="current-password" 
                                    />
                                    <button 
                                        type="button"
                                        onClick={() => setShowPassword(!showPassword)}
                                        className="absolute inset-y-0 right-0 pr-3 flex items-center text-on-surface-variant hover:text-on-surface focus:outline-none transition-colors"
                                    >
                                        <span className="material-symbols-outlined text-sm">
                                            {showPassword ? 'visibility_off' : 'visibility'}
                                        </span>
                                    </button>
                                </div>
                                <InputError message={errors.password} className="mt-2" />
                            </div>

                            <div className="pt-4">
                                <button disabled={processing} className="gradient-btn w-full py-4 rounded-lg font-label-sm text-label-sm uppercase flex items-center justify-center gap-2 relative overflow-hidden group" type="submit">
                                    <span className="relative z-10 font-bold">Login</span>
                                    <span className="material-symbols-outlined relative z-10 text-sm">arrow_forward</span>
                                    <div className="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                                </button>
                            </div>
                        </form>

                        {/* Links */}
                        <div className="mt-8 flex flex-col items-center gap-4">
                            <Link href={route('password.request')} className="font-label-sm text-label-sm text-secondary hover:text-primary transition-colors">
                                Lupa Password?
                            </Link>
                            <Link href={route('register')} className="font-label-sm text-label-sm text-on-surface-variant hover:text-on-surface transition-colors">
                                Belum punya akun? <span className="text-secondary">Daftar</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </main>

            {/* Footer Component Execution */}
            <footer className="bg-surface-container-lowest/80 backdrop-blur-md w-full py-6 border-t border-white/5 flex flex-col md:flex-row items-center justify-between px-container-padding-desktop gap-4 relative z-10">
                <div className="font-label-sm text-label-sm text-on-surface-variant text-center md:text-left">
                    © 2024 Knowledge Bank University Digital Repository
                </div>
                <div className="flex flex-wrap justify-center gap-4">
                    <a className="font-label-sm text-label-sm text-on-surface-variant hover:text-secondary transition-colors" href="#">Privacy Policy</a>
                    <a className="font-label-sm text-label-sm text-on-surface-variant hover:text-secondary transition-colors" href="#">Terms of Service</a>
                    <a className="font-label-sm text-label-sm text-on-surface-variant hover:text-secondary transition-colors" href="#">Help Center</a>
                </div>
            </footer>
        </div>
    );
}
