import PublicLayout from '@/Layouts/PublicLayout';
import Pagination from '@/Components/Pagination';
import { Head, router, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';

export default function PublicScript({ projects, yearOptions = [], filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const [academicYear, setAcademicYear] = useState(filters.academic_year || 'all');
    const [perPage, setPerPage] = useState(filters.per_page || 10);

    const [scriptModalTarget, setScriptModalTarget] = useState(null);
    const [modalTab, setModalTab] = useState('request'); // 'request' | 'token'

    const applyFilters = (next = {}) => {
        router.get(
            route('public.script'),
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

    const {
        data: permData,
        setData: setPermData,
        post: postPerm,
        processing: permProcessing,
        reset: resetPerm,
        errors: permErrors,
        clearErrors: clearPermErrors,
    } = useForm({
        requester_name: '',
        requester_email: '',
        purpose: '',
    });

    const {
        data: verData,
        setData: setVerData,
        post: postVerify,
        processing: verProcessing,
        errors: verErrors,
        clearErrors: clearVerErrors,
    } = useForm({
        type: 'script_archive',
        project_id: '',
        email: '',
        token: '',
    });

    const openScriptModal = (project, initialTab = 'request') => {
        clearPermErrors();
        clearVerErrors();
        setScriptModalTarget(project);
        setModalTab(initialTab);
        setVerData({
            type: 'script_archive',
            project_id: project.id,
            email: permData.requester_email || '',
            token: '',
        });
    };

    const submitPermissionRequest = (e) => {
        e.preventDefault();
        if (!scriptModalTarget) return;
        postPerm(route('public.script.request-access', scriptModalTarget.id), {
            preserveScroll: true,
            onSuccess: () => {
                resetPerm();
                setScriptModalTarget(null);
            },
        });
    };

    const submitVerifyToken = (e) => {
        e.preventDefault();
        postVerify(route('public.download.verify'), { preserveScroll: true });
    };

    return (
        <PublicLayout
            header={
                <div>
                    <h1 className="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-surface">
                        Repository Script (Source Code)
                    </h1>
                    <p className="text-on-surface-variant mt-1.5 max-w-2xl">
                        Untuk mengunduh source code program, ajukan permohonan izin di bawah ini. Notifikasi akan dikirim ke <b>Dosen Pembimbing, Koordinator TA, Admin</b>, dan pemilik repositori untuk diverifikasi melalui akun EREPO.
                    </p>
                </div>
            }
        >
            <Head title="Repository Script — EREPO" />

            <div className="glass-panel rounded-2xl overflow-hidden shadow-2xl">
                <div className="px-6 py-4 border-b border-white/10 flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-4 bg-surface-container-low/50">
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative flex-1 sm:flex-none">
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari judul script, kode kelompok, nama..."
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
                        <span>Total {projects.total} arsip</span>
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse min-w-[680px]">
                        <thead>
                            <tr className="bg-surface-container/40 border-b border-white/10 font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">
                                <th className="px-6 py-4 font-semibold w-1/2">Judul Script &amp; Pemilik</th>
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
                                        {project.has_archive ? (
                                            <div className="inline-flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => openScriptModal(project, 'request')}
                                                    className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-secondary/35 bg-secondary/15 text-secondary hover:bg-secondary/25 transition-all text-xs font-semibold"
                                                >
                                                    <span className="material-symbols-outlined text-[16px]">download</span>
                                                    Download Script
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => openScriptModal(project, 'token')}
                                                    title="Masukkan token download yang diterima di email setelah diverifikasi"
                                                    className="inline-flex items-center gap-1 px-3 py-2 rounded-lg border border-white/15 text-on-surface-variant hover:text-primary hover:border-primary/40 transition-all text-xs"
                                                >
                                                    <span className="material-symbols-outlined text-[16px]">vpn_key</span>
                                                    Input Token
                                                </button>
                                            </div>
                                        ) : (
                                            <span className="text-xs text-on-surface-variant italic">Arsip belum tersedia</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {projects.data.length === 0 && (
                                <tr>
                                    <td colSpan="4" className="px-6 py-12 text-center text-on-surface-variant">
                                        Tidak ada arsip Repository Script yang ditemukan.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={projects.links} />
            </div>

            {/* Request Access / Use Token Modal */}
            {scriptModalTarget && (
                <div className="fixed inset-0 z-50 bg-background/85 backdrop-blur-md flex items-center justify-center p-4">
                    <div className="bg-surface-container-high/95 border border-white/15 w-full max-w-lg rounded-2xl p-7 relative flex flex-col gap-5 shadow-2xl">
                        <button
                            type="button"
                            onClick={() => setScriptModalTarget(null)}
                            className="absolute top-5 right-5 text-on-surface-variant hover:text-white"
                        >
                            <span className="material-symbols-outlined">close</span>
                        </button>

                        <div>
                            <span className="text-xs font-semibold text-secondary uppercase tracking-wider">
                                Unduh Source Code ({scriptModalTarget.group_id})
                            </span>
                            <h2 className="font-headline-sm text-headline-sm font-bold text-on-surface mt-1 line-clamp-2">
                                {scriptModalTarget.title}
                            </h2>
                        </div>

                        <div className="grid grid-cols-2 gap-2 p-1 rounded-xl bg-black/25 border border-white/10 text-xs">
                            <button
                                type="button"
                                onClick={() => setModalTab('request')}
                                className={`py-2 rounded-lg font-semibold transition-all ${
                                    modalTab === 'request' ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:text-white'
                                }`}
                            >
                                Ajukan Izin Download
                            </button>
                            <button
                                type="button"
                                onClick={() => setModalTab('token')}
                                className={`py-2 rounded-lg font-semibold transition-all ${
                                    modalTab === 'token' ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:text-white'
                                }`}
                            >
                                Sudah Punya Token
                            </button>
                        </div>

                        {modalTab === 'request' ? (
                            <form onSubmit={submitPermissionRequest} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-on-surface-variant mb-1">
                                        Nama Lengkap Pemohon
                                    </label>
                                    <input
                                        type="text"
                                        value={permData.requester_name}
                                        onChange={(e) => setPermData('requester_name', e.target.value)}
                                        placeholder="Masukkan nama lengkap Anda"
                                        className="glass-input w-full rounded-lg px-4 py-2.5 text-sm"
                                        required
                                    />
                                    {permErrors.requester_name && <p className="text-xs text-error mt-1">{permErrors.requester_name}</p>}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-on-surface-variant mb-1">
                                        Email Resmi UDINUS
                                    </label>
                                    <input
                                        type="email"
                                        value={permData.requester_email}
                                        onChange={(e) => setPermData('requester_email', e.target.value)}
                                        placeholder="nim@mhs.dinus.ac.id / nama@dsn.dinus.ac.id"
                                        className="glass-input w-full rounded-lg px-4 py-2.5 text-sm"
                                        required
                                    />
                                    {permErrors.requester_email && <p className="text-xs text-error mt-1">{permErrors.requester_email}</p>}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-on-surface-variant mb-1">
                                        Keperluan / Alasan Permintaan
                                    </label>
                                    <textarea
                                        rows="3"
                                        value={permData.purpose}
                                        onChange={(e) => setPermData('purpose', e.target.value)}
                                        placeholder="Jelaskan keperluan referensi / pengembangan lanjutan..."
                                        className="glass-input w-full rounded-lg p-3 text-sm"
                                        required
                                    />
                                    {permErrors.purpose && <p className="text-xs text-error mt-1">{permErrors.purpose}</p>}
                                    <p className="text-[11px] text-on-surface-variant mt-1.5">
                                        Token <b>belum dikirim pada tahap ini</b>. Permohonan akan diverifikasi terlebih dahulu melalui akun Dosen Pembimbing / Koordinator TA / Admin, dan barulah <b>Token Download (aktif 1 hari)</b> dikirim ke email Anda.
                                    </p>
                                </div>

                                <button
                                    type="submit"
                                    disabled={permProcessing}
                                    className="gradient-btn w-full py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider flex items-center justify-center gap-2 disabled:opacity-50"
                                >
                                    <span className="material-symbols-outlined text-[16px]">send</span>
                                    {permProcessing ? 'Mengirim Permohonan...' : 'Kirim Permohonan Izin'}
                                </button>
                            </form>
                        ) : (
                            <form onSubmit={submitVerifyToken} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-on-surface-variant mb-1.5">
                                        Email UDINUS Pemohon
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
                                        Kode Token Download Script
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
                                    {verProcessing ? 'Memverifikasi...' : 'Verifikasi & Unduh Script'}
                                </button>
                            </form>
                        )}
                    </div>
                </div>
            )}
        </PublicLayout>
    );
}
