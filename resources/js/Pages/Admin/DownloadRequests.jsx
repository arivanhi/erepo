import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { Head, router, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';

export default function DownloadRequests({ requests, filters = {} }) {
    const user = usePage().props.auth.user;

    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [perPage, setPerPage] = useState(filters.per_page || 10);
    const [processingId, setProcessingId] = useState(null);

    const applyFilters = (next = {}) => {
        router.get(
            route('download-requests.index'),
            {
                search: next.search ?? search,
                status: next.status ?? statusFilter,
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
        }, 300);
        return () => clearTimeout(timer);
    }, [search]);

    const handleDecision = (requestId, decision) => {
        setProcessingId(requestId);
        router.post(
            route('download-requests.decide', requestId),
            { decision },
            {
                preserveScroll: true,
                onFinish: () => setProcessingId(null),
            }
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Verifikasi Permintaan Download" />

            <header className="flex flex-col md:flex-row justify-between items-start md:items-end mb-6 gap-4">
                <div>
                    <h2 className="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-surface mb-2">
                        Verifikasi Permintaan Download
                    </h2>
                    <p className="font-body-lg text-body-lg text-on-surface-variant max-w-3xl">
                        {user.role === 'dosen'
                            ? 'Daftar pengajuan izin unduh Source Code (Script) untuk repositori bimbingan Anda. Setujui untuk mengirimkan token download (aktif 1 hari) ke email pemohon.'
                            : 'Daftar pengajuan izin unduh Source Code (Script) dari pengunjung katalog publik. Setujui untuk mengirimkan token download (aktif 1 hari) ke email pemohon.'}
                    </p>
                </div>
            </header>

            <div className="glass-panel rounded-2xl overflow-hidden shadow-2xl">
                {/* Filter Bar */}
                <div className="px-6 py-4 border-b border-white/10 flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-4 bg-surface-container-low/50">
                    <div className="flex flex-wrap items-center gap-3">
                        <select
                            value={statusFilter}
                            onChange={(e) => {
                                setStatusFilter(e.target.value);
                                applyFilters({ status: e.target.value });
                            }}
                            className="glass-input rounded-full px-3.5 py-2 text-xs font-semibold cursor-pointer [&>option]:bg-surface-container [&>option]:text-on-surface"
                        >
                            <option value="all">Semua Status</option>
                            <option value="pending">Menunggu Verifikasi</option>
                            <option value="approved">Disetujui</option>
                            <option value="rejected">Ditolak</option>
                        </select>

                        <div className="relative flex-1 sm:flex-none">
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari judul script, kelompok, nama/email pemohon..."
                                className="glass-input rounded-full pl-4 pr-9 py-2 text-xs w-full sm:w-80"
                            />
                            <span className="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[16px]">
                                search
                            </span>
                        </div>
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
                        <span>Total {requests.total} permintaan</span>
                    </div>
                </div>

                {/* Requests Table */}
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse min-w-[860px]">
                        <thead>
                            <tr className="bg-surface-container/40 border-b border-white/10 font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">
                                <th className="px-6 py-4 font-semibold w-1/4">Judul yang Ingin Didownload</th>
                                <th className="px-6 py-4 font-semibold w-1/5">Data Pemohon</th>
                                <th className="px-6 py-4 font-semibold w-1/4">Keperluan / Alasan</th>
                                <th className="px-6 py-4 font-semibold">Status</th>
                                <th className="px-6 py-4 font-semibold text-right">Aksi Verifikasi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5 text-sm">
                            {requests.data.map((item) => {
                                const isBusy = processingId === item.id;
                                return (
                                    <tr key={item.id} className="hover:bg-white/5 transition-colors align-top">
                                        <td className="px-6 py-5">
                                            <div className="font-semibold text-on-surface mb-1">{item.script_title}</div>
                                            <div className="text-xs text-on-surface-variant space-y-1">
                                                <div className="flex items-center gap-1.5">
                                                    <span className="px-2 py-0.5 rounded bg-primary/15 text-primary font-mono text-[11px] font-semibold">
                                                        {item.group_id}
                                                    </span>
                                                    <span>• {item.academic_year}</span>
                                                </div>
                                                <div className="flex items-start gap-1">
                                                    <span className="material-symbols-outlined text-[14px] text-secondary mt-0.5 shrink-0">groups</span>
                                                    <span>{item.member_names}</span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <span className="material-symbols-outlined text-[14px] text-primary shrink-0">school</span>
                                                    <span>{item.dosen_name}</span>
                                                </div>
                                            </div>
                                        </td>

                                        <td className="px-6 py-5">
                                            <div className="font-medium text-on-surface">{item.requester_name || '-'}</div>
                                            <div className="font-mono text-xs text-secondary break-all mt-0.5">
                                                {item.requester_email}
                                            </div>
                                            <div className="text-[11px] text-on-surface-variant mt-1.5">
                                                Diajukan: {item.created_at_formatted}
                                            </div>
                                        </td>

                                        <td className="px-6 py-5">
                                            <div className="p-3 rounded-xl bg-black/25 border border-white/10 text-xs text-on-surface-variant whitespace-pre-line">
                                                {item.purpose || '-'}
                                            </div>
                                        </td>

                                        <td className="px-6 py-5">
                                            {item.status === 'pending' && (
                                                <span className="px-2.5 py-1 rounded-full bg-tertiary/20 border border-tertiary/35 text-tertiary text-xs font-semibold inline-flex items-center gap-1">
                                                    <span className="material-symbols-outlined text-[14px]">schedule</span>
                                                    Menunggu Verifikasi
                                                </span>
                                            )}
                                            {item.status === 'approved' && (
                                                <div className="space-y-1">
                                                    <span className="px-2.5 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/35 text-emerald-400 text-xs font-semibold inline-flex items-center gap-1">
                                                        <span className="material-symbols-outlined text-[14px]">check_circle</span>
                                                        Disetujui
                                                    </span>
                                                    {item.verified_by && (
                                                        <div className="text-[11px] text-on-surface-variant">
                                                            Oleh: {item.verified_by}
                                                        </div>
                                                    )}
                                                    {item.expires_at_formatted && (
                                                        <div className="text-[11px] text-on-surface-variant">
                                                            Token s/d: {item.expires_at_formatted}
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                            {item.status === 'rejected' && (
                                                <div className="space-y-1">
                                                    <span className="px-2.5 py-1 rounded-full bg-error/15 border border-error/35 text-error text-xs font-semibold inline-flex items-center gap-1">
                                                        <span className="material-symbols-outlined text-[14px]">cancel</span>
                                                        Ditolak
                                                    </span>
                                                    {item.verified_by && (
                                                        <div className="text-[11px] text-on-surface-variant">
                                                            Oleh: {item.verified_by}
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </td>

                                        <td className="px-6 py-5 text-right whitespace-nowrap">
                                            {item.status === 'pending' ? (
                                                <div className="inline-flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        disabled={isBusy}
                                                        onClick={() => handleDecision(item.id, 'approve')}
                                                        className="px-3.5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold inline-flex items-center gap-1.5 transition-all disabled:opacity-50"
                                                    >
                                                        <span className="material-symbols-outlined text-[16px]">check_circle</span>
                                                        Setujui &amp; Kirim Token
                                                    </button>
                                                    <button
                                                        type="button"
                                                        disabled={isBusy}
                                                        onClick={() => handleDecision(item.id, 'reject')}
                                                        className="px-3 py-2 rounded-lg border border-error/40 text-error hover:bg-error/15 text-xs font-semibold inline-flex items-center gap-1 transition-all disabled:opacity-50"
                                                    >
                                                        <span className="material-symbols-outlined text-[16px]">close</span>
                                                        Tolak
                                                    </button>
                                                </div>
                                            ) : item.status === 'approved' ? (
                                                <button
                                                    type="button"
                                                    disabled={isBusy}
                                                    onClick={() => handleDecision(item.id, 'approve')}
                                                    title="Kirim ulang email token ke pemohon"
                                                    className="px-3 py-1.5 rounded-lg border border-secondary/35 bg-secondary/10 text-secondary hover:bg-secondary/20 text-xs font-semibold inline-flex items-center gap-1 disabled:opacity-50"
                                                >
                                                    <span className="material-symbols-outlined text-[15px]">forward_to_inbox</span>
                                                    Kirim Ulang Token
                                                </button>
                                            ) : (
                                                <span className="text-xs text-on-surface-variant italic">Selesai</span>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}

                            {requests.data.length === 0 && (
                                <tr>
                                    <td colSpan="5" className="px-6 py-12 text-center text-on-surface-variant">
                                        <span className="material-symbols-outlined text-[42px] opacity-40 mb-2">fact_check</span>
                                        <p>Belum ada permintaan download Repository Script.</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={requests.links} />
            </div>
        </AuthenticatedLayout>
    );
}
