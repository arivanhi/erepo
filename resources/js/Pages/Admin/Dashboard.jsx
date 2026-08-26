import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';

export default function Dashboard({ stats, recentActivities }) {
    const user = usePage().props.auth.user;

    return (
        <AuthenticatedLayout
            header={
                <>
                    <h1 className="font-display-lg-mobile text-display-lg-mobile md:font-display-lg md:text-display-lg text-on-surface drop-shadow-md">
                        Dashboard Utama
                    </h1>
                    <p className="font-body-lg text-body-lg text-on-surface-variant mt-2">
                        Welcome back, {user.name}. Overview of academic repositories and user accounts.
                    </p>
                </>
            }
        >
            <Head title="Dashboard" />

            {/* Stats Bento Grid */}
            <section className="grid grid-cols-1 md:grid-cols-3 gap-6">
                {/* Stat Card 1 */}
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
                                    <div className="flex justify-between items-start mb-1">
                                        <h4 className="font-body-md text-body-md font-bold text-on-surface">{activity.title}</h4>
                                        <span className="font-label-sm text-label-sm text-on-surface-variant">{activity.time}</span>
                                    </div>
                                    <p className="font-body-md text-body-md text-on-surface-variant line-clamp-2">By {activity.user ? activity.user.name : 'Unknown User'}</p>
                                    <div className="mt-2 inline-flex items-center gap-1 px-2 py-1 rounded-full bg-white/5 border border-white/10 font-label-sm text-label-sm text-primary">
                                        <span className="material-symbols-outlined text-[14px]">{activity.type === 'Capstone' ? 'school' : 'description'}</span> {activity.type}
                                    </div>
                                </div>
                            </div>
                        )) : (
                            <div className="text-center text-on-surface-variant">No recent activity.</div>
                        )}
                    </div>
                    <div className="p-4 border-t border-white/10 flex justify-center bg-surface/20">
                        <button className="font-label-sm text-label-sm text-primary hover:text-white transition-colors flex items-center gap-1">
                            View All Activity <span className="material-symbols-outlined text-[16px]">arrow_forward</span>
                        </button>
                    </div>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
