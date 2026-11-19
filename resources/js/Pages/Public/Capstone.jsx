import PublicLayout from '@/Layouts/PublicLayout';
import Pagination from '@/Components/Pagination';
import { Head, router, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';

export default function PublicCapstone({ projects, yearOptions = [], filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const [academicYear, setAcademicYear] = useState(filters.academic_year || 'all');
    const [perPage, setPerPage] = useState(filters.per_page || 10);

    // Abstract Preview Modal state
    const [abstractModal, setAbstractModal] = useState(null);
    const [abstractLoading, setAbstractLoading] = useState(false);

    // Download Token Modal state
    const [downloadTarget, setDownloadTarget] = useState(null);
    const [downloadTab, setDownloadTab] = useState('request'); // 'request' | 'verify'

    const applyFilters = (next = {}) => {
        router.get(
            route('public.capstone'),
            {
                search: next.search ?? search,
                academic_year: next.academic_year ?? academicYear,
                per_page: next.per_page ?? perPage,
            },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            if (search !== (filters.search || '')) {
                applyFilters({ search });
            }
        }, 350);
        return () => clearTimeout(timer);
    }, [search]);

    const openAbstract = async (project) => {
        setAbstractModal({ project, pages: [], error: null });
        setAbstractLoading(true);
        try {
            const res = await fetch(route('public.capstone.abstract', project.id), {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('Gagal memuat halaman abstrak.');
            const json = await res.json();
            setAbstractModal({ project, pages: json.pages || [], error: null });
        } catch (err) {
            setAbstractModal({ project, pages: [], error: err.message });
        } finally {
            setAbstractLoading(false);
        }
    };

    // Request Token Form
    const {
        data: reqData,
        setData: setReqData,
        post: postReqToken,
        processing: reqProcessing,
        errors: reqErrors,
        clearErrors: clearReqErrors,
    } = useForm({
        email: '',
    });

    // Verify Token & Download Form
    const {
        data: verData,
        setData: setVerData,
        post: postVerify,
        processing: verProcessing,
        errors: verErrors,
        clearErrors: clearVerErrors,
    } = useForm({
        type: 'capstone_pdf',
        project_id: '',
        email: '',
        token: '',
    });

    const openDownloadModal = (project) => {
        clearReqErrors();
        clearVerErrors();
        setDownloadTarget(project);
        setDownloadTab('request');
        setVerData({
            type: 'capstone_pdf',
            project_id: project.id,
            email: reqData.email || '',
            token: '',
        });
    };

    const submitRequestToken = (e) => {
        e.preventDefault();
        if (!downloadTarget) return;
        postReqToken(route('public.capstone.request-token', downloadTarget.id), {
            preserveScroll: true,
            onSuccess: () => {
                setVerData((prev) => ({
                    ...prev,
                    project_id: downloadTarget.id,
                    email: reqData.email,
                }));
                setDownloadTab('verify');
            },
        });
    };

    const submitVerifyToken = (e) => {
        e.preventDefault();
        postVerify(route('public.download.verify'), {
            preserveScroll: true,
        });
    };

    return (
        <PublicLayout
            header={
                <div>
                    <h1 className="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-surface">
                        Repository PDF (Capstone Design)
                    </h1>
                    <p className="text-on-surface-variant mt-1.5 max-w-2xl">
                        Klik <b>Lihat Abstrak</b> untuk membaca halaman Intisari/Abstract secara langsung, atau masukkan email resmi UDINUS untuk mendapatkan <b>Token Download (aktif 1 hari)</b>.
                    </p>
                </div>
            }
        >
            <Head title="Repository PDF — EREPO" />

            {/* Filter & Per-Page Controls */}
            <div className="glass-panel rounded-2xl overflow-hidden shadow-2xl">
                <div className="px-6 py-4 border-b border-white/10 flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-4 bg-surface-container-low/50">
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative flex-1 sm:flex-none">
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari judul, kode kelompok, nama..."
                                className="glass-input rounded-full pl-4 pr-9 py-2 text-xs w-full sm:w-72"
                            />
                            <span className="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[16px]">
                                search
                            </span>
                        </div>

                        <select
                            value={academicYear}
                            onChange={(e) => {
                                setAcademicYear(e.target.value);
                                applyFilters({ academic_year: e.target.value });
                            }}
                            className="glass-input rounded-full px-3.5 py-2 text-xs cursor-pointer [&>option]:bg-surface-container [&>option]:text-on-surface"
                        >
                            <option value="all">Semua Tahun Ajaran</option>
                            {yearOptions.map((yr) => (
                                <option key={yr} value={yr}>
                                    Tahun {yr}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex items-center justify-between lg:justify-end gap-3 text-xs text-on-surface-variant">
                        <div className="flex items-center gap-2">
                            <span>Tampilkan:</span>
                            <select
                                value={perPage}
                                onChange={(e) => {
                                    const nextVal = Number(e.target.value);
                                    setPerPage(nextVal);
                                    applyFilters({ per_page: nextVal });
                                }}
                                className="glass-input rounded-lg px-2.5 py-1.5 text-xs font-semibold cursor-pointer [&>option]:bg-surface-container [&>option]:text-on-surface"
                            >
                                <option value={10}>10 data</option>
                                <option value={25}>25 data</option>
                                <option value={50}>50 data</option>
                            </select>
                        </div>
                        <span>Total {projects.total} dokumen</span>
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse min-w-[680px]">
                        <thead>
                            <tr className="bg-surface-container/40 border-b border-white/10 font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">
                                <th className="px-6 py-4 font-semibold w-1/2">Judul &amp; Anggota Kelompok</th>
                                <th className="px-6 py-4 font-semibold">Tahun Ajaran</th>
                                <th className="px-6 py-4 font-semibold">Kode Kelompok</th>
                                <th className="px-6 py-4 font-semibold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5 text-sm">
                            {projects.data.map((project) => (
                                <tr key={project.id} className="hover:bg-white/5 transition-colors align-top">
                                    <td className="px-6 py-5">
                                        <div className="font-medium text-on-surface mb-1.5">{project.title}</div>
                                        <div className="text-xs text-on-surface-variant space-y-1">
                                            <div className="flex items-start gap-1.5">
                                                <span className="material-symbols-outlined text-[15px] text-secondary mt-0.5 shrink-0">groups</span>
                                                <span>{project.member_names}</span>
                                            </div>
                                            <div className="flex items-center gap-1.5">
                                                <span className="material-symbols-outlined text-[15px] text-primary shrink-0">school</span>
                                                <span>{project.dosen_name}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-5 text-on-surface-variant whitespace-nowrap">{project.academic_year}</td>
                                    <td className="px-6 py-5 whitespace-nowrap">
                                        <span className="px-2.5 py-1 rounded-full bg-white/5 border border-white/10 text-xs text-on-surface-variant">
                                            {project.group_id}
                                        </span>
                                    </td>
                                    <td className="px-6 py-5 text-right whitespace-nowrap">
                                        {project.has_document ? (
                                            <div className="inline-flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => openAbstract(project)}
                                                    className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-primary/30 bg-primary/10 text-primary hover:bg-primary/20 transition-all text-xs font-semibold"
                                                >
                                                    <span className="material-symbols-outlined text-[16px]">visibility</span>
                                                    View Abstrak
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => openDownloadModal(project)}
                                                    className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-secondary/30 bg-secondary/10 text-secondary hover:bg-secondary/20 transition-all text-xs font-semibold"
                                                >
                                                    <span className="material-symbols-outlined text-[16px]">download</span>
                                                    Download
                                                </button>
                                            </div>
                                        ) : (
                                            <span className="text-xs text-on-surface-variant italic">Berkas belum tersedia</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {projects.data.length === 0 && (
                                <tr>
                                    <td colSpan="4" className="px-6 py-12 text-center text-on-surface-variant">
                                        Tidak ada dokumen Repository PDF yang ditemukan.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={projects.links} />
            </div>

            {/* Abstract Viewer Modal (ONLY Abstract / Intisari pages) */}
            {abstractModal && (
                <div className="fixed inset-0 z-50 bg-background/85 backdrop-blur-md flex items-center justify-center p-4">
                    <div className="glass-panel w-full max-w-4xl rounded-2xl overflow-hidden flex flex-col max-h-[92vh] border border-white/15 shadow-2xl">
                        <div className="px-6 py-4 border-b border-white/10 flex items-center justify-between gap-4 shrink-0">
                            <div className="min-w-0">
                                <div className="text-xs text-primary font-semibold uppercase tracking-wider">
                                    Pratinjau Halaman Abstrak / Intisari ({abstractModal.project.group_id})
                                </div>
                                <h2 className="font-headline-sm text-headline-sm text-on-surface truncate">
                                    {abstractModal.project.title}
                                </h2>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                <button
                                    type="button"
                                    onClick={() => {
                                        const proj = abstractModal.project;
                                        setAbstractModal(null);
                                        openDownloadModal(proj);
                                    }}
                                    className="px-3.5 py-1.5 rounded-lg bg-secondary/20 border border-secondary/40 text-secondary text-xs font-semibold hover:bg-secondary/30 flex items-center gap-1.5"
                                >
                                    <span className="material-symbols-outlined text-[16px]">download</span>
                                    Minta Token Full PDF
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setAbstractModal(null)}
                                    className="p-1.5 text-on-surface-variant hover:text-white"
                                >
                                    <span className="material-symbols-outlined">close</span>
                                </button>
                            </div>
                        </div>

                        <div className="p-6 overflow-y-auto flex-1 space-y-4 bg-black/40">
                            {abstractLoading && (
                                <div className="py-16 text-center text-on-surface-variant flex flex-col items-center gap-3">
                                    <span className="material-symbols-outlined animate-spin text-3xl text-primary">sync</span>
                                    <span className="text-sm">Memuat halaman Abstrak / Intisari...</span>
                                </div>
                            )}

                            {!abstractLoading && abstractModal.error && (
                                <div className="p-4 rounded-xl bg-error/15 border border-error/30 text-error text-sm text-center">
                                    {abstractModal.error}
                                </div>
                            )}

                            {!abstractLoading &&
                                abstractModal.pages.map((imgUrl, idx) => (
                                    <div key={idx} className="bg-white rounded-xl overflow-hidden shadow-lg max-w-3xl mx-auto">
                                        <img
                                            src={imgUrl}
                                            alt={`Halaman Abstrak ${idx + 1}`}
                                            className="w-full h-auto select-none pointer-events-none"
                                            draggable={false}
                                        />
                                    </div>
                                ))}

                            {!abstractLoading && !abstractModal.error && abstractModal.pages.length === 0 && (
                                <div className="py-12 text-center text-sm text-on-surface-variant">
                                    Halaman abstrak tidak ditemukan pada berkas ini.
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* Download Full PDF Token Modal (1-Day UDINUS Email Token) */}
            {downloadTarget && (
                <div className="fixed inset-0 z-50 bg-background/85 backdrop-blur-md flex items-center justify-center p-4">
                    <div className="bg-surface-container-high/95 border border-white/15 w-full max-w-md rounded-2xl p-7 relative flex flex-col gap-5 shadow-2xl">
                        <button
                            type="button"
                            onClick={() => setDownloadTarget(null)}
                            className="absolute top-5 right-5 text-on-surface-variant hover:text-white"
                        >
                            <span className="material-symbols-outlined">close</span>
                        </button>

                        <div>
                            <span className="text-xs font-semibold text-secondary uppercase tracking-wider">
                                Unduh Full PDF (Token 1 Hari)
                            </span>
                            <h2 className="font-headline-sm text-headline-sm font-bold text-on-surface mt-1 line-clamp-2">
                                {downloadTarget.title}
                            </h2>
                        </div>

                        {/* Tabs */}
                        <div className="grid grid-cols-2 gap-2 p-1 rounded-xl bg-black/25 border border-white/10 text-xs">
                            <button
                                type="button"
                                onClick={() => setDownloadTab('request')}
                                className={`py-2 rounded-lg font-semibold transition-all ${
                                    downloadTab === 'request' ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:text-white'
                                }`}
                            >
                                1. Minta Token Email
                            </button>
                            <button
                                type="button"
                                onClick={() => setDownloadTab('verify')}
                                className={`py-2 rounded-lg font-semibold transition-all ${
                                    downloadTab === 'verify' ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:text-white'
                                }`}
                            >
                                2. Masukkan Token
                            </button>
                        </div>

                        {downloadTab === 'request' ? (
                            <form onSubmit={submitRequestToken} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-on-surface-variant mb-1.5">
                                        Email Resmi UDINUS
                                    </label>
                                    <input
                                        type="email"
                                        value={reqData.email}
                                        onChange={(e) => setReqData('email', e.target.value)}
                                        placeholder="nim@mhs.dinus.ac.id / nama@dsn.dinus.ac.id"
                                        className="glass-input w-full rounded-lg px-4 py-2.5 text-sm"
                                        required
                                    />
                                    {reqErrors.email && <p className="text-xs text-error mt-1">{reqErrors.email}</p>}
                                    <p className="text-[11px] text-on-surface-variant mt-2">
                                        Sistem akan mengirimkan kode token serta tautan unduhan yang berlaku selama <b>1 hari (24 jam)</b> ke email UDINUS Anda.
                                    </p>
                                </div>
                                <button
                                    type="submit"
                                    disabled={reqProcessing}
                                    className="gradient-btn w-full py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider flex items-center justify-center gap-2 disabled:opacity-50"
                                >
                                    <span className="material-symbols-outlined text-[16px]">mail</span>
                                    {reqProcessing ? 'Mengirim Token...' : 'Kirim Token ke Email'}
                                </button>
                            </form>
                        ) : (
                            <form onSubmit={submitVerifyToken} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-on-surface-variant mb-1.5">
                                        Email UDINUS Anda
                                    </label>
                                    <input
                                        type="email"
                                        value={verData.email}
                                        onChange={(e) => setVerData('email', e.target.value)}
                                        placeholder="nim@mhs.dinus.ac.id"
                                        className="glass-input w-full rounded-lg px-4 py-2.5 text-sm"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-on-surface-variant mb-1.5">
                                        Kode Token Download (dari Email)
                                    </label>
                                    <input
                                        type="text"
                                        value={verData.token}
                                        onChange={(e) => setVerData('token', e.target.value.toUpperCase())}
                                        placeholder="XXXX-XXXX"
                                        className="glass-input w-full rounded-lg px-4 py-2.5 text-sm font-mono tracking-widest uppercase"
                                        required
                                    />
                                    {verErrors.token && <p className="text-xs text-error mt-1">{verErrors.token}</p>}
                                </div>
                                <button
                                    type="submit"
                                    disabled={verProcessing}
                                    className="gradient-btn w-full py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider flex items-center justify-center gap-2 disabled:opacity-50"
                                >
                                    <span className="material-symbols-outlined text-[16px]">download</span>
                                    {verProcessing ? 'Memverifikasi...' : 'Verifikasi & Unduh PDF'}
                                </button>
                            </form>
                        )}
                    </div>
                </div>
            )}
        </PublicLayout>
    );
}
