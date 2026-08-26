import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';

export default function Dashboard({ stats, recentActivities }) {
    const user = usePage().props.auth.user;

    return (
        <AuthenticatedLayout>
            <Head title="Student Dashboard" />
            <div className="bg-glow-1"></div>
            <div className="bg-glow-2"></div>
            
            {/* Welcome Section */}
            <header className="glass-panel rounded-xl p-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 relative overflow-hidden">
                <div className="absolute top-0 left-0 right-0 h-[1px] bg-white/20"></div>
                <div>
                    <h2 className="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-surface tracking-tight">
                        Welcome back, <span className="gradient-text">{user.name}</span>
                    </h2>
                    <p className="font-body-lg text-body-lg text-on-surface-variant mt-2 max-w-2xl">
                        Here's an overview of your academic progress and recent repository activity. You have 1 document pending review.
                    </p>
                </div>
                <div className="flex items-center gap-4 shrink-0">
                    <button className="bg-primary/20 text-primary border border-primary/30 hover:bg-primary/30 hover:shadow-[0_0_15px_rgba(192,193,255,0.3)] transition-all px-6 py-3 rounded-lg font-label-sm text-label-sm flex items-center gap-2">
                        <span className="material-symbols-outlined text-sm">upload</span>
                        New Submission
                    </button>
                </div>
            </header>

            {/* Stats Grid (Bento Style) */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-gutter">
                <div className="glass-panel glass-panel-hover rounded-xl p-6 flex flex-col justify-between relative overflow-hidden group">
                    <div className="absolute top-0 left-0 right-0 h-[1px] bg-white/20"></div>
                    <div className="flex justify-between items-start mb-4">
                        <div className="bg-secondary/10 p-3 rounded-lg text-secondary group-hover:bg-secondary/20 transition-colors">
                            <span className="material-symbols-outlined">description</span>
                        </div>
                        <span className="bg-surface-tint/20 text-surface-tint font-label-sm text-label-sm px-2 py-1 rounded-full">+2 this week</span>
                    </div>
                    <div>
                        <h3 className="font-body-md text-body-md text-on-surface-variant">Documents Uploaded</h3>
                        <p className="font-headline-md text-headline-md text-on-surface mt-1">{stats.uploaded}</p>
                    </div>
                </div>
                <div className="glass-panel glass-panel-hover rounded-xl p-6 flex flex-col justify-between relative overflow-hidden group">
                    <div className="absolute top-0 left-0 right-0 h-[1px] bg-white/20"></div>
                    <div className="flex justify-between items-start mb-4">
                        <div className="bg-tertiary/10 p-3 rounded-lg text-tertiary group-hover:bg-tertiary/20 transition-colors">
                            <span className="material-symbols-outlined">hourglass_empty</span>
                        </div>
                    </div>
                    <div>
                        <h3 className="font-body-md text-body-md text-on-surface-variant">Pending Reviews</h3>
                        <p className="font-headline-md text-headline-md text-on-surface mt-1">{stats.pending}</p>
                    </div>
                </div>
                <div className="glass-panel glass-panel-hover rounded-xl p-6 flex flex-col justify-between relative overflow-hidden group">
                    <div className="absolute top-0 left-0 right-0 h-[1px] bg-white/20"></div>
                    <div className="flex justify-between items-start mb-4">
                        <div className="bg-primary/10 p-3 rounded-lg text-primary group-hover:bg-primary/20 transition-colors">
                            <span className="material-symbols-outlined">verified</span>
                        </div>
                    </div>
                    <div>
                        <h3 className="font-body-md text-body-md text-on-surface-variant">Approved Submissions</h3>
                        <p className="font-headline-md text-headline-md text-on-surface mt-1">{stats.approved}</p>
                    </div>
                </div>
            </div>

            {/* Recent Activity Section */}
            <section className="glass-panel rounded-xl p-6 md:p-8 relative overflow-hidden flex-1">
                <div className="absolute top-0 left-0 right-0 h-[1px] bg-white/20"></div>
                <div className="flex items-center justify-between mb-6">
                    <h3 className="font-headline-md text-headline-md text-on-surface">Recent Activity</h3>
                    <a className="font-label-sm text-label-sm text-secondary hover:text-secondary-fixed transition-colors flex items-center gap-1" href="#">
                        View All <span className="material-symbols-outlined text-sm">arrow_forward</span>
                    </a>
                </div>
                <div className="flex flex-col gap-4">
                    {recentActivities && recentActivities.length > 0 ? recentActivities.map((activity) => (
                        <div key={activity.id} className="glass-panel glass-panel-hover rounded-lg p-4 flex items-center gap-4 transition-all hover:bg-white/5 cursor-pointer">
                            <div className={`bg-surface-container-high p-3 rounded-lg shrink-0 ${activity.type === 'Capstone' ? 'text-primary' : 'text-secondary'}`}>
                                <span className="material-symbols-outlined">{activity.type === 'Capstone' ? 'upload_file' : 'code'}</span>
                            </div>
                            <div className="flex-1 min-w-0">
                                <h4 className="font-body-md text-body-md text-on-surface truncate">{activity.title}</h4>
                                <p className="font-label-sm text-label-sm text-on-surface-variant mt-1">
                                    {activity.type === 'Capstone' ? 'Uploaded to Capstone Repository' : 'Updated in Script Repository'} • {activity.time}
                                </p>
                            </div>
                            <div className="hidden md:flex items-center gap-2">
                                <span className={`px-3 py-1 rounded-full bg-surface-container text-on-surface-variant font-label-sm text-label-sm border ${activity.status === 'approved' ? 'border-primary/50 text-primary' : 'border-white/5'}`}>
                                    {activity.status}
                                </span>
                            </div>
                            <div className="shrink-0">
                                <span className="material-symbols-outlined text-on-surface-variant">more_vert</span>
                            </div>
                        </div>
                    )) : (
                        <div className="text-center text-on-surface-variant p-4">No recent activity.</div>
                    )}
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
