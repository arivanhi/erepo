import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Dashboard({ stats, recentActivities }) {
    const user = usePage().props.auth.user;
    const isKoordinator = user.role === 'koordinator_ta';

    return (
        <AuthenticatedLayout
            header={
                <>
                    <h1 className="font-display-lg-mobile text-display-lg-mobile md:font-display-lg md:text-display-lg text-on-surface drop-shadow-md">
                        Dashboard Utama
                    </h1>
                    <p className="font-body-lg text-body-lg text-on-surface-variant mt-2">
                        {isKoordinator
                            ? `Selamat datang, ${user.name}. Ringkasan repositori Tugas Akhir, kelengkapan PDF AI, dan source code mahasiswa.`
                            : `Welcome back, ${user.name}. Overview of academic repositories and user accounts.`}
                    </p>
                </>
            }
        >
            <Head title="Dashboard" />

            {/* Stats Bento Grid */}
            <section className="grid grid-cols-1 md:grid-cols-3 gap-6">
                {/* Stat Card 1 */}
                {isKoordinator ? (
                    <div className="glass-panel glass-panel-hover rounded-xl p-6 flex flex-col justify-between h-48 transition-all duration-300 relative overflow-hidden group">
                        <div className="absolute -right-6 -top-6 w-24 h-24 bg-primary/10 rounded-full blur-xl group-hover:bg-primary/20 transition-all duration-500"></div>
                        <div className="flex justify-between items-start relative z-10">
                            <h3 className="font-body-md text-body-md text-on-surface-variant">Menunggu Persetujuan</h3>
                            <span className="material-symbols-outlined text-secondary p-2 bg-surface/30 rounded-lg">fact_check</span>
                        </div>
                        <div className="relative z-10">
                            <p className="font-display-lg-mobile text-display-lg-mobile md:font-display-lg md:text-display-lg text-on-surface">{stats.pendingReview ?? 0}</p>
                            <div className="flex items-center gap-2 mt-1 text-secondary font-label-sm text-label-sm">
                                <span>Siap direview</span>
                                {stats.heldCapstone > 0 && (
                                    <span className="px-2 py-0.5 rounded-full bg-error/20 text-error border border-error/30 text-xs">
                                        {stats.heldCapstone} tertahan AI
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="glass-panel glass-panel-hover rounded-xl p-6 flex flex-col justify-between h-48 transition-all duration-300 relative overflow-hidden group">
                        <div className="absolute -right-6 -top-6 w-24 h-24 bg-primary/10 rounded-full blur-xl group-hover:bg-primary/20 transition-all duration-500"></div>
                        <div className="flex justify-between items-start relative z-10">
                            <h3 className="font-body-md text-body-md text-on-surface-variant">Total Akun Terdaftar</h3>
                            <span className="material-symbols-outlined text-secondary p-2 bg-surface/30 rounded-lg">group</span>
                        </div>
                        <div className="relative z-10">
                            <p className="font-display-lg-mobile text-display-lg-mobile md:font-display-lg md:text-display-lg text-on-surface">{stats.totalUsers}</p>
                            <div className="flex items-center gap-1 mt-1 text-secondary font-label-sm text-label-sm">
                                <span className="material-symbols-outlined text-[16px]">group</span>
                                <span>Total accounts</span>
                            </div>
                        </div>
                    </div>
                )}

                {/* Stat Card 2 */}
                <div className="glass-panel glass-panel-hover rounded-xl p-6 flex flex-col justify-between h-48 transition-all duration-300 relative overflow-hidden group">
                    <div className="absolute -right-6 -top-6 w-24 h-24 bg-tertiary/10 rounded-full blur-xl group-hover:bg-tertiary/20 transition-all duration-500"></div>
                    <div className="flex justify-between items-start relative z-10">
                        <h3 className="font-body-md text-body-md text-on-surface-variant">Total Repository Capstone</h3>
                        <span className="material-symbols-outlined text-tertiary p-2 bg-surface/30 rounded-lg">science</span>
                    </div>
                    <div className="relative z-10">
                        <p className="font-display-lg-mobile text-display-lg-mobile md:font-display-lg md:text-display-lg text-on-surface">{stats.totalCapstone}</p>
                        <div className="flex items-center gap-1 mt-1 text-tertiary font-label-sm text-label-sm">
                            <span className="material-symbols-outlined text-[16px]">science</span>
                            <span>Total capstones</span>
                        </div>
                    </div>
                </div>

                {/* Stat Card 3 */}
                <div className="glass-panel glass-panel-hover rounded-xl p-6 flex flex-col justify-between h-48 transition-all duration-300 relative overflow-hidden group">
                    <div className="absolute -right-6 -top-6 w-24 h-24 bg-primary-container/10 rounded-full blur-xl group-hover:bg-primary-container/20 transition-all duration-500"></div>
                    <div className="flex justify-between items-start relative z-10">
                        <h3 className="font-body-md text-body-md text-on-surface-variant">Total Repository Script</h3>
                        <span className="material-symbols-outlined text-primary-container p-2 bg-surface/30 rounded-lg">description</span>
                    </div>
                    <div className="relative z-10">
                        <p className="font-display-lg-mobile text-display-lg-mobile md:font-display-lg md:text-display-lg text-on-surface">{stats.totalScript}</p>
                        <div className="flex items-center gap-1 mt-1 text-primary-container font-label-sm text-label-sm">
                            <span className="material-symbols-outlined text-[16px]">description</span>
                            <span>Total scripts</span>
                        </div>
                    </div>
                </div>
            </section>

            {/* Aktivitas Terbaru Section */}
            <section className="mt-8">
                <h2 className="font-headline-md text-headline-md text-on-surface mb-6 flex items-center gap-2">
                    <span className="material-symbols-outlined text-primary">history</span>
                    Aktivitas Terbaru
                </h2>
                <div className="glass-panel rounded-xl overflow-hidden">
                    <div className="p-6 space-y-6">
                        {recentActivities && recentActivities.length > 0 ? recentActivities.map((activity) => (
                            <div key={activity.id} className="flex items-start gap-4 p-4 rounded-lg hover:bg-white/5 transition-colors duration-200">
                                <div className={`w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 mt-1 border ${activity.type === 'Capstone' ? 'bg-secondary-container/20 border-secondary/30 text-secondary' : 'bg-primary-container/20 border-primary/30 text-primary'}`}>
                                    <span className="material-symbols-outlined">{activity.type === 'Capstone' ? 'school' : 'description'}</span>
                                </div>
                                <div className="flex-1">
                                    <div className="flex justify-between items-start mb-1 gap-3">
                                        <h4 className="font-body-md text-body-md font-bold text-on-surface">{activity.title}</h4>
                                        <span className="font-label-sm text-label-sm text-on-surface-variant shrink-0">{activity.time}</span>
                                    </div>
                                    <p className="font-body-md text-body-md text-on-surface-variant line-clamp-2">
                                        Oleh {activity.author_names || (activity.user ? activity.user.name : 'Mahasiswa')}
                                    </p>
                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                        <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-white/5 border border-white/10 font-label-sm text-label-sm text-primary">
                                            <span className="material-symbols-outlined text-[14px]">{activity.type === 'Capstone' ? 'school' : 'description'}</span> {activity.type}
                                        </span>
                                        {activity.status === 'rejected' && (
                                            <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-error/20 border border-error/30 font-label-sm text-label-sm text-error">
                                                Tertahan - Wajib Upload Ulang
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )) : (
                            <div className="text-center text-on-surface-variant">No recent activity.</div>
                        )}
                    </div>
                    <div className="p-4 border-t border-white/10 flex justify-center bg-surface/20">
                        <Link href={route('repository.capstone')} className="font-label-sm text-label-sm text-primary hover:text-white transition-colors flex items-center gap-1">
                            Buka Repository Capstone <span className="material-symbols-outlined text-[16px]">arrow_forward</span>
                        </Link>
                    </div>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
