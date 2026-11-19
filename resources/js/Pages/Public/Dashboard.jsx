import PublicLayout from '@/Layouts/PublicLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function PublicDashboard({
    stats,
    yearBreakdown = [],
    selectedYear = 'all',
    recentCapstones = [],
    recentScripts = [],
}) {
    const selectYear = (yr) => {
        router.get(
            route('public.dashboard'),
            yr === 'all' ? {} : { academic_year: yr },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    return (
        <PublicLayout
            header={
                <div className="flex flex-col md:flex-row md:items-end justify-between gap-4">
                    <div>
                        <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/15 border border-primary/30 text-primary text-xs font-semibold mb-3">
                            <span className="material-symbols-outlined text-[14px]">library_books</span>
                            Repositori Digital Terverifikasi v1.2
                        </span>
                        <h1 className="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-surface">
                            EREPO S1 Teknik Elektro
                        </h1>
                        <p className="font-body-lg text-body-lg text-on-surface-variant mt-1.5 max-w-2xl">
                            Jelajahi arsip laporan Capstone Design (PDF) dan Source Code program Tugas Akhir yang telah terverifikasi per tahun ajaran.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-3 shrink-0">
                        <Link
                            href={route('public.capstone')}
                            className="px-5 py-2.5 rounded-xl bg-primary/20 border border-primary/40 text-primary hover:bg-primary/30 transition-all text-sm font-semibold flex items-center gap-2"
                        >
                            <span className="material-symbols-outlined text-[18px]">picture_as_pdf</span>
                            Lihat Repository PDF
                        </Link>
                        <Link
                            href={route('public.script')}
                            className="px-5 py-2.5 rounded-xl bg-secondary/20 border border-secondary/40 text-secondary hover:bg-secondary/30 transition-all text-sm font-semibold flex items-center gap-2"
                        >
                            <span className="material-symbols-outlined text-[18px]">folder_zip</span>
                            Lihat Repository Script
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="EREPO S1 Teknik Elektro" />

            {/* Stat Cards */}
            <section className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="glass-panel rounded-xl p-6 flex flex-col justify-between h-40 relative overflow-hidden">
                    <div className="flex justify-between items-start">
                        <h3 className="text-sm text-on-surface-variant">Repository PDF Terverifikasi</h3>
                        <span className="material-symbols-outlined text-primary p-2 bg-primary/10 rounded-lg">picture_as_pdf</span>
                    </div>
                    <div>
                        <p className="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-surface">
                            {stats.totalCapstone}
                        </p>
                        <p className="text-xs text-primary mt-1">Dokumen Capstone Design</p>
                    </div>
                </div>

                <div className="glass-panel rounded-xl p-6 flex flex-col justify-between h-40 relative overflow-hidden">
                    <div className="flex justify-between items-start">
                        <h3 className="text-sm text-on-surface-variant">Repository Script Terverifikasi</h3>
                        <span className="material-symbols-outlined text-secondary p-2 bg-secondary/10 rounded-lg">folder_zip</span>
                    </div>
                    <div>
                        <p className="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-surface">
                            {stats.totalScript}
                        </p>
                        <p className="text-xs text-secondary mt-1">Arsip Source Code Program</p>
                    </div>
                </div>

                <div className="glass-panel rounded-xl p-6 flex flex-col justify-between h-40 relative overflow-hidden">
                    <div className="flex justify-between items-start">
                        <h3 className="text-sm text-on-surface-variant">Periode Tahun Ajaran</h3>
                        <span className="material-symbols-outlined text-tertiary p-2 bg-tertiary/10 rounded-lg">calendar_month</span>
                    </div>
                    <div>
                        <p className="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-surface">
                            {stats.totalYears}
                        </p>
                        <p className="text-xs text-tertiary mt-1">Tahun Ajaran Tersimpan</p>
                    </div>
                </div>
            </section>

            {/* Academic Year Filter Section */}
            <section className="glass-panel rounded-xl p-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <h2 className="font-headline-sm text-headline-sm font-bold text-on-surface flex items-center gap-2">
                        <span className="material-symbols-outlined text-primary text-[20px]">date_range</span>
                        Arsip Berdasarkan Tahun Ajaran
                    </h2>
                    {selectedYear !== 'all' && (
                        <button
                            type="button"
                            onClick={() => selectYear('all')}
                            className="text-xs text-primary hover:underline self-start sm:self-auto"
                        >
                            Tampilkan Semua Tahun Ajaran
                        </button>
                    )}
                </div>

                <div className="flex flex-wrap gap-3">
                    <button
                        type="button"
                        onClick={() => selectYear('all')}
                        className={`px-4 py-2.5 rounded-xl border text-xs font-semibold transition-all ${
                            selectedYear === 'all'
                                ? 'bg-primary text-on-primary border-primary shadow-md'
                                : 'bg-white/5 border-white/10 text-on-surface-variant hover:bg-white/10'
                        }`}
                    >
                        Semua Tahun ({stats.totalCapstone + stats.totalScript})
                    </button>

                    {yearBreakdown.map((item) => (
                        <button
                            key={item.academic_year}
                            type="button"
                            onClick={() => selectYear(item.academic_year)}
                            className={`px-4 py-2.5 rounded-xl border text-xs transition-all flex items-center gap-2 ${
                                selectedYear === item.academic_year
                                    ? 'bg-primary text-on-primary border-primary font-semibold shadow-md'
                                    : 'bg-white/5 border-white/10 text-on-surface hover:bg-white/10'
                            }`}
                        >
                            <span className="font-semibold">{item.academic_year}</span>
                            <span className="opacity-80">
                                ({item.capstone_count} PDF • {item.script_count} Script)
                            </span>
                        </button>
                    ))}
                    {yearBreakdown.length === 0 && (
                        <span className="text-xs text-on-surface-variant italic">Belum ada data tahun ajaran terverifikasi.</span>
                    )}
                </div>
            </section>

            {/* Recent Repositories Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 pb-8">
                {/* Latest PDFs */}
                <section className="glass-panel rounded-xl overflow-hidden flex flex-col">
                    <div className="px-6 py-4 border-b border-white/10 flex items-center justify-between">
                        <h3 className="font-headline-sm text-headline-sm font-semibold text-on-surface flex items-center gap-2">
                            <span className="material-symbols-outlined text-primary text-[20px]">picture_as_pdf</span>
                            Repository PDF Terbaru
                        </h3>
                        <Link
                            href={route('public.capstone', selectedYear !== 'all' ? { academic_year: selectedYear } : {})}
                            className="text-xs text-primary hover:underline flex items-center gap-1"
                        >
                            Lihat Semua <span className="material-symbols-outlined text-[14px]">arrow_forward</span>
                        </Link>
                    </div>
                    <div className="p-5 space-y-3 flex-1">
                        {recentCapstones.length > 0 ? (
                            recentCapstones.map((item) => (
                                <Link
                                    key={item.id}
                                    href={route('public.capstone', { search: item.group_id })}
                                    className="block p-4 rounded-xl bg-white/[0.03] hover:bg-white/[0.07] border border-white/5 transition-all"
                                >
                                    <div className="flex items-center justify-between gap-2 mb-1">
                                        <span className="px-2 py-0.5 rounded-full bg-primary/15 text-primary text-[11px] font-semibold">
                                            {item.group_id}
                                        </span>
                                        <span className="text-[11px] text-on-surface-variant">{item.academic_year}</span>
                                    </div>
                                    <h4 className="font-medium text-sm text-on-surface line-clamp-2">{item.title}</h4>
                                    <p className="text-xs text-on-surface-variant mt-1.5 truncate">
                                        Anggota: {item.member_names}
                                    </p>
                                </Link>
                            ))
                        ) : (
                            <div className="py-8 text-center text-xs text-on-surface-variant">
                                Belum ada dokumen PDF terverifikasi pada filter ini.
                            </div>
                        )}
                    </div>
                </section>

                {/* Latest Scripts */}
                <section className="glass-panel rounded-xl overflow-hidden flex flex-col">
                    <div className="px-6 py-4 border-b border-white/10 flex items-center justify-between">
                        <h3 className="font-headline-sm text-headline-sm font-semibold text-on-surface flex items-center gap-2">
                            <span className="material-symbols-outlined text-secondary text-[20px]">folder_zip</span>
                            Repository Script Terbaru
                        </h3>
                        <Link
                            href={route('public.script', selectedYear !== 'all' ? { academic_year: selectedYear } : {})}
                            className="text-xs text-secondary hover:underline flex items-center gap-1"
                        >
                            Lihat Semua <span className="material-symbols-outlined text-[14px]">arrow_forward</span>
                        </Link>
                    </div>
                    <div className="p-5 space-y-3 flex-1">
                        {recentScripts.length > 0 ? (
                            recentScripts.map((item) => (
                                <Link
                                    key={item.id}
                                    href={route('public.script', { search: item.group_id })}
                                    className="block p-4 rounded-xl bg-white/[0.03] hover:bg-white/[0.07] border border-white/5 transition-all"
                                >
                                    <div className="flex items-center justify-between gap-2 mb-1">
                                        <span className="px-2 py-0.5 rounded-full bg-secondary/15 text-secondary text-[11px] font-semibold">
                                            {item.group_id}
                                        </span>
                                        <span className="text-[11px] text-on-surface-variant">{item.academic_year}</span>
                                    </div>
                                    <h4 className="font-medium text-sm text-on-surface line-clamp-2">{item.title}</h4>
                                    <p className="text-xs text-on-surface-variant mt-1.5 truncate">
                                        Anggota: {item.member_names}
                                    </p>
                                </Link>
                            ))
                        ) : (
                            <div className="py-8 text-center text-xs text-on-surface-variant">
                                Belum ada source code terverifikasi pada filter ini.
                            </div>
                        )}
                    </div>
                </section>
            </div>
        </PublicLayout>
    );
}
