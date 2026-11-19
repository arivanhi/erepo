import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Dashboard({ stats, recentActivities }) {
    const { user, capstoneTeam } = usePage().props.auth;

    const renderStatusLabel = (status) => {
        if (status === 'approved') return 'Approved';
        if (status === 'rejected') return 'Tertahan - Upload Ulang';
        if (status === 'checking') return 'Memeriksa AI...';
        return 'Pending';
    };

    const renderStatusClass = (status) => {
        if (status === 'approved') return 'border-primary/50 text-primary bg-primary/10';
        if (status === 'rejected') return 'border-error/50 text-error bg-error/10';
        if (status === 'checking') return 'border-secondary/50 text-secondary bg-secondary/10';
        return 'border-tertiary/50 text-tertiary bg-tertiary/10';
    };

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
                        Welcome back, <span className="gradient-text">{capstoneTeam ? `Tim ${capstoneTeam.group_code}` : user.name}</span>
                    </h2>
                    {capstoneTeam && (
                        <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-secondary">
                            <span className="inline-flex items-center gap-1">
                                <span className="material-symbols-outlined text-[15px]">groups</span>
                                {capstoneTeam.members?.map((m) => m.name).join(', ')}
                            </span>
                            <span className="inline-flex items-center gap-1 text-on-surface-variant">
                                <span className="material-symbols-outlined text-[15px]">school</span>
                                {capstoneTeam.dosen?.name || '-'} ({capstoneTeam.academic_year})
                            </span>
                        </div>
                    )}
                    <p className="font-body-lg text-body-lg text-on-surface-variant mt-2 max-w-2xl">
                        Ringkasan progres repositori Anda dan anggota kelompok.
                        {stats.rejected > 0
                            ? ` Terdapat ${stats.rejected} dokumen tertahan yang wajib diunggah ulang.`
                            : stats.pending > 0
                                ? ` Terdapat ${stats.pending} dokumen sedang menunggu persetujuan.`
                                : ' Seluruh dokumen kelompok Anda dalam kondisi aman.'}
                    </p>
                </div>
                <div className="flex items-center gap-4 shrink-0">
                    <Link href={route('repository.capstone')} className="bg-primary/20 text-primary border border-primary/30 hover:bg-primary/30 hover:shadow-[0_0_15px_rgba(192,193,255,0.3)] transition-all px-6 py-3 rounded-lg font-label-sm text-label-sm flex items-center gap-2">
                        <span className="material-symbols-outlined text-sm">upload</span>
                        New Submission
                    </Link>
                </div>
            </header>

            {/* Held Document Alert Banner */}
            {stats.rejected > 0 && (
                <div className="rounded-xl border border-error/40 bg-error/10 p-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <span className="material-symbols-outlined text-error text-2xl mt-0.5">error</span>
                        <div>
                            <h3 className="font-body-md font-bold text-error">
                                Dokumen Capstone Kelompok Anda Tertahan ({stats.rejected} Dokumen)
                            </h3>
                            <p className="text-sm text-on-surface-variant mt-1">
                                Pemeriksaan kelengkapan mendeteksi item yang belum lengkap (seperti tanda tangan pengesahan atau nomor halaman Daftar Isi). Silakan periksa rincian kekurangan dan unggah ulang file PDF yang sudah diperbaiki.
                            </p>
                        </div>
                    </div>
                    <Link href={route('repository.capstone')} className="shrink-0 px-4 py-2 rounded-lg bg-error text-on-error font-label-sm text-label-sm font-semibold hover:opacity-90 transition-opacity flex items-center gap-1.5">
                        <span className="material-symbols-outlined text-[16px]">upload_file</span>
                        Lihat Kekurangan & Upload Ulang
                    </Link>
                </div>
            )}

            {/* Stats Grid (Bento Style) */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-gutter">
                <div className="glass-panel glass-panel-hover rounded-xl p-6 flex flex-col justify-between relative overflow-hidden group">
                    <div className="absolute top-0 left-0 right-0 h-[1px] bg-white/20"></div>
                    <div className="flex justify-between items-start mb-4">
                        <div className="bg-secondary/10 p-3 rounded-lg text-secondary group-hover:bg-secondary/20 transition-colors">
                            <span className="material-symbols-outlined">description</span>
                        </div>
                        <span className="bg-surface-tint/20 text-surface-tint font-label-sm text-label-sm px-2 py-1 rounded-full">Kelompok & Pribadi</span>
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
                    <Link className="font-label-sm text-label-sm text-secondary hover:text-secondary-fixed transition-colors flex items-center gap-1" href={route('repository.capstone')}>
                        View All <span className="material-symbols-outlined text-sm">arrow_forward</span>
                    </Link>
                </div>
                <div className="flex flex-col gap-4">
                    {recentActivities && recentActivities.length > 0 ? recentActivities.map((activity) => (
                        <Link
                            key={activity.id}
                            href={activity.type === 'Capstone' ? route('repository.capstone') : route('repository.script')}
                            className="glass-panel glass-panel-hover rounded-lg p-4 flex flex-col gap-2 transition-all hover:bg-white/5 cursor-pointer"
                        >
                            <div className="flex items-center gap-4">
                                <div className={`bg-surface-container-high p-3 rounded-lg shrink-0 ${activity.type === 'Capstone' ? 'text-primary' : 'text-secondary'}`}>
                                    <span className="material-symbols-outlined">{activity.type === 'Capstone' ? 'upload_file' : 'code'}</span>
                                </div>
                                <div className="flex-1 min-w-0">
                                    <h4 className="font-body-md text-body-md text-on-surface truncate">{activity.title}</h4>
                                    <p className="font-label-sm text-label-sm text-on-surface-variant mt-1 truncate">
                                        {activity.author_names ? `Anggota: ${activity.author_names} • ` : ''}{activity.time}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2 shrink-0">
                                    <span className={`px-3 py-1 rounded-full font-label-sm text-label-sm border ${renderStatusClass(activity.status)}`}>
                                        {renderStatusLabel(activity.status)}
                                    </span>
                                </div>
                            </div>
                            {activity.status === 'rejected' && (activity.revision_note || (Array.isArray(activity.missing_items) && activity.missing_items.length > 0)) && (
                                <div className="ml-14 mt-1 p-3 rounded-lg bg-error/10 border border-error/30 text-xs text-on-surface space-y-1.5">
                                    {activity.revision_note && (
                                        <div>
                                            <div className="font-semibold text-error">
                                                Catatan Revisi ({activity.reviewer_role === 'koordinator_ta' ? 'Koordinator TA' : activity.reviewer_role === 'dosen' ? 'Dosen Pembimbing' : activity.reviewer_role === 'admin' ? 'Admin' : 'Pemeriksa'}):
                                            </div>
                                            <p className="text-on-surface-variant whitespace-pre-line mt-0.5">{activity.revision_note}</p>
                                        </div>
                                    )}
                                    {Array.isArray(activity.missing_items) && activity.missing_items.length > 0 && (
                                        <div>
                                            <div className="font-semibold text-error mb-1">Keterangan kekurangan AI:</div>
                                            <ul className="list-disc pl-4 space-y-0.5 text-on-surface-variant">
                                                {activity.missing_items.slice(0, 3).map((m, idx) => (
                                                    <li key={idx}>{m}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                </div>
                            )}
                        </Link>
                    )) : (
                        <div className="text-center text-on-surface-variant p-4">Belum ada dokumen yang diunggah.</div>
                    )}
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
