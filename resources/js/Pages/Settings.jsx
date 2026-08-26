import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';

export default function Settings() {
    const user = usePage().props.auth.user;

    return (
        <AuthenticatedLayout>
            <Head title="Settings" />
            
            {/* Background Elements */}
            <div className="fixed inset-0 pointer-events-none z-[-1] overflow-hidden">
                <div className="absolute top-[-10%] right-[-10%] w-[50%] h-[50%] bg-primary opacity-20 blur-[120px] rounded-full"></div>
                <div className="absolute bottom-[-10%] left-[-10%] w-[50%] h-[50%] bg-tertiary opacity-20 blur-[120px] rounded-full"></div>
            </div>

            <div className="max-w-4xl mx-auto w-full">
                <header className="mb-10">
                    <h1 className="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-background mb-2">Pengaturan</h1>
                    <p className="text-on-surface-variant font-body-lg text-body-lg">Kelola profil dan keamanan akun Anda.</p>
                </header>

                <div className="grid grid-cols-1 md:grid-cols-12 gap-8">
                    {/* Left Column (Nav/Summary) */}
                    <div className="md:col-span-4 space-y-6 hidden md:block">
                        <div className="glass-panel rounded-xl p-6 flex flex-col items-center text-center">
                            <div className="w-24 h-24 rounded-full overflow-hidden mb-4 border-2 border-primary">
                                {/* Use UI avatar for demo purposes */}
                                <img 
                                    alt="User Portrait" 
                                    className="w-full h-full object-cover" 
                                    src={`https://ui-avatars.com/api/?name=${user.name}&background=random`} 
                                />
                            </div>
                            <h3 className="font-headline-md text-headline-md text-on-background capitalize">{user.role} Aktif</h3>
                            <p className="text-on-surface-variant font-body-md text-body-md mt-1">{user.email}</p>
                        </div>
                    </div>

                    {/* Right Column (Forms) */}
                    <div className="md:col-span-8 space-y-8">
                        {/* Profile Information Section */}
                        <section className="glass-panel rounded-xl p-8 transition-all duration-300">
                            <div className="flex items-center gap-3 mb-6 border-b border-white/10 pb-4">
                                <div className="p-2 bg-primary/20 rounded-lg text-primary">
                                    <span className="material-symbols-outlined">person</span>
                                </div>
                                <h2 className="font-headline-md text-headline-md text-on-background">Informasi Profil</h2>
                            </div>
                            <form className="space-y-6">
                                <div>
                                    <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="username">Ganti Nama Pengguna</label>
                                    <div className="relative">
                                        <span className="material-symbols-outlined absolute left-3 top-1/2 transform -translate-y-1/2 text-on-surface-variant">badge</span>
                                        <input className="glass-input w-full rounded-lg pl-10 pr-4 py-3 font-body-md text-body-md" id="username" name="username" placeholder="Masukkan nama pengguna baru" type="text" defaultValue={user.name}/>
                                    </div>
                                </div>
                                <div className="flex justify-end pt-2">
                                    <button className="gradient-btn px-6 py-2.5 rounded-lg font-label-sm text-label-sm font-semibold flex items-center gap-2" type="button">
                                        <span className="material-symbols-outlined text-[18px]">save</span>
                                        Simpan Perubahan
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
                                <h2 className="font-headline-md text-headline-md text-on-background">Keamanan</h2>
                            </div>
                            <form className="space-y-6">
                                <h3 className="font-body-lg text-body-lg text-on-background mb-4">Ubah Kata Sandi</h3>
                                <div>
                                    <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="current-password">Kata Sandi Saat Ini</label>
                                    <div className="relative">
                                        <span className="material-symbols-outlined absolute left-3 top-1/2 transform -translate-y-1/2 text-on-surface-variant">key</span>
                                        <input className="glass-input w-full rounded-lg pl-10 pr-10 py-3 font-body-md text-body-md" id="current-password" name="current-password" placeholder="••••••••" type="password"/>
                                        <button className="absolute right-3 top-1/2 transform -translate-y-1/2 text-on-surface-variant hover:text-primary" type="button">
                                            <span className="material-symbols-outlined">visibility</span>
                                        </button>
                                    </div>
                                </div>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="new-password">Kata Sandi Baru</label>
                                        <div className="relative">
                                            <span className="material-symbols-outlined absolute left-3 top-1/2 transform -translate-y-1/2 text-on-surface-variant">lock_reset</span>
                                            <input className="glass-input w-full rounded-lg pl-10 pr-10 py-3 font-body-md text-body-md" id="new-password" name="new-password" placeholder="••••••••" type="password"/>
                                        </div>
                                    </div>
                                    <div>
                                        <label className="block font-label-sm text-label-sm text-on-surface-variant mb-2" htmlFor="confirm-password">Konfirmasi Kata Sandi Baru</label>
                                        <div className="relative">
                                            <span className="material-symbols-outlined absolute left-3 top-1/2 transform -translate-y-1/2 text-on-surface-variant">lock_clock</span>
                                            <input className="glass-input w-full rounded-lg pl-10 pr-10 py-3 font-body-md text-body-md" id="confirm-password" name="confirm-password" placeholder="••••••••" type="password"/>
                                        </div>
                                    </div>
                                </div>
                                <div className="flex justify-end pt-2">
                                    <button className="gradient-btn px-6 py-2.5 rounded-lg font-label-sm text-label-sm font-semibold flex items-center gap-2" type="button">
                                        <span className="material-symbols-outlined text-[18px]">update</span>
                                        Perbarui Kata Sandi
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
