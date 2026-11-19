import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { Head, router, useForm } from '@inertiajs/react';
import { useState, useEffect, useMemo } from 'react';

export default function CapstoneTeams({ teams, dosens = [], mahasiswas = [], filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const [showModal, setShowModal] = useState(false);
    const [editingTeam, setEditingTeam] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [memberSearch, setMemberSearch] = useState('');

    // Inline new student form state inside modal
    const [newStudentName, setNewStudentName] = useState('');
    const [newStudentNim, setNewStudentNim] = useState('');
    const [newStudentEmail, setNewStudentEmail] = useState('');

    const { data, setData, post, put, processing, reset, errors, clearErrors } = useForm({
        group_code: '',
        team_name: '',
        academic_year: '2025-2026',
        dosen_id: '',
        member_ids: [],
        new_members: [],
        send_token_now: true,
    });

    useEffect(() => {
        const timer = setTimeout(() => {
            if (search !== (filters.search || '')) {
                router.get(route('capstone-teams'), { search }, { preserveState: true, preserveScroll: true, replace: true });
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [search]);

    const mahasiswaMap = useMemo(() => {
        const map = new Map();
        mahasiswas.forEach((m) => map.set(m.id, m));
        return map;
    }, [mahasiswas]);

    const filteredMahasiswas = useMemo(() => {
        const q = memberSearch.trim().toLowerCase();
        return mahasiswas
            .filter((m) => !data.member_ids.includes(m.id))
            .filter((m) => {
                if (!q) return true;
                return (
                    (m.name && m.name.toLowerCase().includes(q)) ||
                    (m.identity_number && m.identity_number.toLowerCase().includes(q)) ||
                    (m.email && m.email.toLowerCase().includes(q))
                );
            })
            .slice(0, 8);
    }, [mahasiswas, data.member_ids, memberSearch]);

    const openCreate = () => {
        clearErrors();
        setEditingTeam(null);
        setData({
            group_code: '',
            team_name: '',
            academic_year: '2025-2026',
            dosen_id: '',
            member_ids: [],
            new_members: [],
            send_token_now: true,
        });
        setMemberSearch('');
        setShowModal(true);
    };

    const openEdit = (team) => {
        clearErrors();
        setEditingTeam(team);
        setData({
            group_code: team.group_code || '',
            team_name: team.team_name || '',
            academic_year: team.academic_year || '2025-2026',
            dosen_id: team.dosen_id || '',
            member_ids: Array.isArray(team.members) ? team.members.map((m) => m.id) : [],
            new_members: [],
            send_token_now: false,
        });
        setMemberSearch('');
        setShowModal(true);
    };

    const addExistingMember = (id) => {
        if (!data.member_ids.includes(id)) {
            setData('member_ids', [...data.member_ids, id]);
        }
        setMemberSearch('');
    };

    const removeExistingMember = (id) => {
        setData('member_ids', data.member_ids.filter((mId) => mId !== id));
    };

    const addInlineNewMember = () => {
        if (!newStudentName.trim() || !newStudentEmail.trim()) return;
        setData('new_members', [
            ...data.new_members,
            {
                name: newStudentName.trim(),
                identity_number: newStudentNim.trim(),
                email: newStudentEmail.trim().toLowerCase(),
            },
        ]);
        setNewStudentName('');
        setNewStudentNim('');
        setNewStudentEmail('');
    };

    const removeInlineNewMember = (idx) => {
        setData(
            'new_members',
            data.new_members.filter((_, i) => i !== idx)
        );
    };

    const submitTeam = (e) => {
        e.preventDefault();
        if (editingTeam) {
            put(route('capstone-teams.update', editingTeam.id), {
                onSuccess: () => {
                    setShowModal(false);
                    reset();
                },
            });
        } else {
            post(route('capstone-teams.store'), {
                onSuccess: () => {
                    setShowModal(false);
                    reset();
                },
            });
        }
    };

    const sendOrResetToken = (teamId, regenerate = false) => {
        router.post(
            route('capstone-teams.send-token', teamId),
            { regenerate },
            { preserveScroll: true }
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route('capstone-teams.destroy', deleteTarget.id), {
            onSuccess: () => setDeleteTarget(null),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Add Capstone Team" />

            <header className="flex flex-col md:flex-row justify-between items-start md:items-end mb-6 gap-4">
                <div>
                    <h2 className="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-surface mb-2">
                        Add Capstone Team
                    </h2>
                    <p className="font-body-lg text-body-lg text-on-surface-variant max-w-2xl">
                        Atur Kode Capstone kelompok, Tahun Ajaran, Dosen Pembimbing, dan Anggota Mahasiswa untuk akses login Token 1 Bulan.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={openCreate}
                    className="px-6 py-3 rounded-xl bg-gradient-to-r from-inverse-primary to-primary-container text-white font-label-sm text-label-sm font-bold shadow-[0_0_15px_rgba(73,75,214,0.4)] hover:shadow-[0_0_25px_rgba(73,75,214,0.6)] transition-all flex items-center gap-2"
                >
                    <span className="material-symbols-outlined text-[20px]">group_add</span>
                    + Tambah Tim Capstone
                </button>
            </header>

            <div className="glass-panel rounded-2xl overflow-hidden shadow-2xl">
                <div className="px-6 py-4 border-b border-white/10 flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3 bg-surface-container-low/50">
                    <div className="relative">
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari Kode Capstone, Nama / NIM Anggota..."
                            className="glass-input rounded-full pl-4 pr-9 py-2 text-xs w-full sm:w-80"
                        />
                        <span className="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[16px]">
                            search
                        </span>
                    </div>
                    <span className="text-xs text-on-surface-variant">Total {teams.total} kelompok terdaftar</span>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse min-w-[760px]">
                        <thead>
                            <tr className="bg-surface-container/40 border-b border-white/10 font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">
                                <th className="px-6 py-4 font-semibold">Kode Capstone</th>
                                <th className="px-6 py-4 font-semibold">Anggota Kelompok</th>
                                <th className="px-6 py-4 font-semibold">Dosen Pembimbing</th>
                                <th className="px-6 py-4 font-semibold">Token Login (1 Bulan)</th>
                                <th className="px-6 py-4 font-semibold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5 text-sm">
                            {teams.data.map((team) => (
                                <tr key={team.id} className="hover:bg-white/5 transition-colors align-top">
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <div className="font-mono font-bold text-primary text-sm">{team.group_code}</div>
                                        <div className="text-xs text-on-surface-variant mt-0.5">{team.academic_year}</div>
                                        {team.team_name && (
                                            <div className="text-xs text-on-surface-variant/80 mt-1 max-w-[200px] truncate" title={team.team_name}>
                                                {team.team_name}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-6 py-4">
                                        <div className="space-y-1">
                                            {team.members?.map((m) => (
                                                <div key={m.id} className="text-xs flex flex-wrap items-center gap-1.5">
                                                    <span className="font-medium text-on-surface">{m.name}</span>
                                                    {m.identity_number && (
                                                        <span className="font-mono text-[11px] text-secondary">({m.identity_number})</span>
                                                    )}
                                                    <span className="text-[11px] text-on-surface-variant">• {m.email}</span>
                                                </div>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 text-xs text-on-surface-variant">
                                        {team.dosen?.name || '-'}
                                    </td>
                                    <td className="px-6 py-4">
                                        {team.has_active_token ? (
                                            <div>
                                                <span className="px-2.5 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-xs font-semibold inline-flex items-center gap-1">
                                                    <span className="material-symbols-outlined text-[13px]">check_circle</span>
                                                    Aktif
                                                </span>
                                                <div className="text-[11px] text-on-surface-variant mt-1">
                                                    s/d {team.token_expires_formatted} WIB
                                                </div>
                                            </div>
                                        ) : (
                                            <span className="px-2.5 py-0.5 rounded-full bg-white/5 border border-white/10 text-on-surface-variant text-xs">
                                                Belum Aktif
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-right whitespace-nowrap">
                                        <div className="inline-flex items-center gap-2">
                                            <button
                                                type="button"
                                                onClick={() => sendOrResetToken(team.id, false)}
                                                title="Kirim Token Login 1 Bulan ke Email Anggota"
                                                className="px-3 py-1.5 rounded-lg border border-secondary/35 bg-secondary/10 text-secondary hover:bg-secondary/20 transition-all text-xs font-semibold inline-flex items-center gap-1"
                                            >
                                                <span className="material-symbols-outlined text-[16px]">mail</span>
                                                Kirim Token
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => openEdit(team)}
                                                title="Edit Tim"
                                                className="p-2 rounded-lg border border-white/10 text-on-surface-variant hover:text-primary hover:border-primary/40"
                                            >
                                                <span className="material-symbols-outlined text-[18px]">edit</span>
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setDeleteTarget(team)}
                                                title="Hapus Tim"
                                                className="p-2 rounded-lg border border-white/10 text-on-surface-variant hover:text-error hover:border-error/40"
                                            >
                                                <span className="material-symbols-outlined text-[18px]">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {teams.data.length === 0 && (
                                <tr>
                                    <td colSpan="5" className="px-6 py-12 text-center text-on-surface-variant">
                                        Belum ada Tim Capstone terdaftar. Klik <b>+ Tambah Tim Capstone</b> untuk membuat kelompok baru.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={teams.links} />
            </div>

            {/* Create / Edit Capstone Team Modal */}
            {showModal && (
                <div className="fixed inset-0 z-50 bg-background/80 backdrop-blur-md flex items-center justify-center p-4">
                    <div className="glass-panel w-full max-w-2xl rounded-2xl overflow-hidden flex flex-col max-h-[92vh] border border-white/15 shadow-2xl relative">
                        <button
                            type="button"
                            onClick={() => setShowModal(false)}
                            className="absolute top-6 right-6 text-on-surface-variant hover:text-white z-10"
                        >
                            <span className="material-symbols-outlined">close</span>
                        </button>

                        <div className="px-8 pt-7 pb-5 border-b border-white/10 shrink-0">
                            <h2 className="font-headline-md text-headline-md font-bold text-on-surface flex items-center gap-2.5">
                                <span className="material-symbols-outlined text-primary">group_add</span>
                                {editingTeam ? `Edit Tim Capstone (${editingTeam.group_code})` : 'Tambah Tim Capstone Baru'}
                            </h2>
                            <p className="text-xs text-on-surface-variant mt-1">
                                Mahasiswa dapat langsung login di halaman /login cukup dengan Kode Capstone dan Token yang dikirim ke email anggota.
                            </p>
                        </div>

                        <form onSubmit={submitTeam} className="flex flex-col flex-1 min-h-0">
                            <div className="p-8 space-y-5 overflow-y-auto flex-1">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold uppercase tracking-wider text-on-surface-variant mb-2">
                                            Kode Capstone <span className="text-error">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={data.group_code}
                                            onChange={(e) => setData('group_code', e.target.value.toUpperCase())}
                                            placeholder="Contoh: E11-VI-85"
                                            className="glass-input w-full rounded-lg px-4 py-2.5 text-sm font-mono uppercase"
                                            required
                                        />
                                        {errors.group_code && <p className="text-xs text-error mt-1">{errors.group_code}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold uppercase tracking-wider text-on-surface-variant mb-2">
                                            Tahun Ajaran <span className="text-error">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={data.academic_year}
                                            onChange={(e) => setData('academic_year', e.target.value)}
                                            placeholder="Contoh: 2025-2026"
                                            className="glass-input w-full rounded-lg px-4 py-2.5 text-sm"
                                            required
                                        />
                                        {errors.academic_year && <p className="text-xs text-error mt-1">{errors.academic_year}</p>}
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold uppercase tracking-wider text-on-surface-variant mb-2">
                                        Topik / Nama Proyek Awal (Opsional)
                                    </label>
                                    <input
                                        type="text"
                                        value={data.team_name}
                                        onChange={(e) => setData('team_name', e.target.value)}
                                        placeholder="Opsional — judul lengkap dapat diisi mahasiswa saat upload"
                                        className="glass-input w-full rounded-lg px-4 py-2.5 text-sm"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold uppercase tracking-wider text-on-surface-variant mb-2">
                                        Dosen Pembimbing <span className="text-error">*</span>
                                    </label>
                                    <select
                                        value={data.dosen_id}
                                        onChange={(e) => setData('dosen_id', e.target.value)}
                                        className="glass-input w-full rounded-lg px-4 py-2.5 text-sm [&>option]:bg-surface-container [&>option]:text-on-surface"
                                        required
                                    >
                                        <option value="" disabled>
                                            Pilih Dosen Pembimbing
                                        </option>
                                        {dosens.map((d) => (
                                            <option key={d.id} value={d.id}>
                                                {d.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.dosen_id && <p className="text-xs text-error mt-1">{errors.dosen_id}</p>}
                                </div>

                                {/* Student Members Selection */}
                                <div className="space-y-3">
                                    <label className="block text-xs font-semibold uppercase tracking-wider text-on-surface-variant">
                                        Anggota Kelompok Mahasiswa <span className="text-error">*</span>
                                    </label>

                                    {/* Selected Chips */}
                                    <div className="flex flex-wrap gap-2 p-3 rounded-xl bg-black/25 border border-white/15 min-h-[46px]">
                                        {data.member_ids.length === 0 && data.new_members.length === 0 && (
                                            <span className="text-xs text-on-surface-variant italic">
                                                Cari mahasiswa terdaftar atau tambahkan mahasiswa baru di bawah.
                                            </span>
                                        )}

                                        {data.member_ids.map((mId) => {
                                            const m = mahasiswaMap.get(mId);
                                            return (
                                                <span
                                                    key={`ex-${mId}`}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/20 border border-primary/40 text-xs text-on-surface"
                                                >
                                                    <span>
                                                        {m ? m.name : `#${mId}`}
                                                        {m?.identity_number ? ` (${m.identity_number})` : ''}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        onClick={() => removeExistingMember(mId)}
                                                        className="hover:text-error flex items-center"
                                                    >
                                                        <span className="material-symbols-outlined text-[14px]">close</span>
                                                    </button>
                                                </span>
                                            );
                                        })}

                                        {data.new_members.map((nm, idx) => (
                                            <span
                                                key={`new-${idx}`}
                                                className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-secondary/20 border border-secondary/40 text-xs text-secondary"
                                            >
                                                <span>
                                                    [Baru] {nm.name} ({nm.email})
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => removeInlineNewMember(idx)}
                                                    className="hover:text-error flex items-center"
                                                >
                                                    <span className="material-symbols-outlined text-[14px]">close</span>
                                                </button>
                                            </span>
                                        ))}
                                    </div>

                                    {/* Search Existing Mahasiswa */}
                                    <div className="relative">
                                        <input
                                            type="text"
                                            value={memberSearch}
                                            onChange={(e) => setMemberSearch(e.target.value)}
                                            placeholder="Cari mahasiswa yang sudah terdaftar (Nama / NIM / Email)..."
                                            className="glass-input w-full rounded-lg px-4 py-2.5 text-xs"
                                        />
                                        {memberSearch.trim() !== '' && (
                                            <div className="mt-1 rounded-xl bg-surface-container-high border border-white/15 max-h-44 overflow-y-auto divide-y divide-white/5 shadow-xl">
                                                {filteredMahasiswas.length > 0 ? (
                                                    filteredMahasiswas.map((m) => (
                                                        <button
                                                            key={m.id}
                                                            type="button"
                                                            onClick={() => addExistingMember(m.id)}
                                                            className="w-full px-4 py-2 text-left hover:bg-primary/15 flex items-center justify-between text-xs"
                                                        >
                                                            <div>
                                                                <div className="font-medium text-on-surface">{m.name}</div>
                                                                <div className="text-[11px] text-on-surface-variant">
                                                                    {m.identity_number || '-'} • {m.email}
                                                                </div>
                                                            </div>
                                                            <span className="text-primary font-semibold">+ Pilih</span>
                                                        </button>
                                                    ))
                                                ) : (
                                                    <div className="px-4 py-2.5 text-xs text-on-surface-variant">
                                                        Tidak ditemukan. Gunakan baris input cepat di bawah jika mahasiswa belum punya akun.
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>

                                    {/* Quick Inline Add New Student */}
                                    <div className="p-3.5 rounded-xl bg-white/[0.03] border border-white/10 space-y-2.5">
                                        <div className="text-[11px] font-semibold text-secondary">
                                            Mahasiswa belum terdaftar di sistem? Tambahkan langsung di sini:
                                        </div>
                                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                            <input
                                                type="text"
                                                value={newStudentName}
                                                onChange={(e) => setNewStudentName(e.target.value)}
                                                placeholder="Nama Mahasiswa"
                                                className="glass-input rounded-lg px-3 py-2 text-xs"
                                            />
                                            <input
                                                type="text"
                                                value={newStudentNim}
                                                onChange={(e) => setNewStudentNim(e.target.value)}
                                                placeholder="NIM (E11.2023.xxxx)"
                                                className="glass-input rounded-lg px-3 py-2 text-xs"
                                            />
                                            <div className="flex gap-1.5">
                                                <input
                                                    type="email"
                                                    value={newStudentEmail}
                                                    onChange={(e) => setNewStudentEmail(e.target.value)}
                                                    placeholder="nim@mhs.dinus.ac.id"
                                                    className="glass-input rounded-lg px-3 py-2 text-xs flex-1 min-w-0"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={addInlineNewMember}
                                                    className="px-3 py-2 rounded-lg bg-secondary/20 border border-secondary/40 text-secondary text-xs font-semibold shrink-0 hover:bg-secondary/30"
                                                >
                                                    +
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    {errors.member_ids && <p className="text-xs text-error">{errors.member_ids}</p>}
                                </div>

                                {!editingTeam && (
                                    <label className="flex items-center gap-2.5 text-xs text-on-surface cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={data.send_token_now}
                                            onChange={(e) => setData('send_token_now', e.target.checked)}
                                            className="rounded border-white/20 bg-black/30 text-primary focus:ring-primary"
                                        />
                                        <span>Langsung kirimkan Token Login (aktif 1 bulan) ke email seluruh anggota tim setelah disimpan</span>
                                    </label>
                                )}
                            </div>

                            <div className="px-8 py-5 border-t border-white/10 bg-surface-container/30 flex justify-end gap-3 shrink-0">
                                <button
                                    type="button"
                                    onClick={() => setShowModal(false)}
                                    className="px-5 py-2.5 rounded-lg border border-white/20 text-xs font-semibold text-on-surface"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="gradient-btn px-7 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider disabled:opacity-50"
                                >
                                    {processing ? 'Menyimpan...' : editingTeam ? 'Simpan Perubahan' : 'Buat Tim Capstone'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Delete Modal */}
            {deleteTarget && (
                <div className="fixed inset-0 z-50 bg-background/85 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-surface-container-high border border-error/30 w-full max-w-md rounded-2xl p-6 space-y-4">
                        <h3 className="font-headline-sm text-headline-sm font-bold text-on-surface">
                            Hapus Tim {deleteTarget.group_code}?
                        </h3>
                        <p className="text-xs text-on-surface-variant">
                            Data konfigurasi login tim ini akan dihapus (dokumen repositori yang sudah ada tidak terhapus).
                        </p>
                        <div className="flex justify-end gap-3 pt-2">
                            <button
                                type="button"
                                onClick={() => setDeleteTarget(null)}
                                className="px-4 py-2 rounded-lg border border-white/15 text-xs"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                onClick={confirmDelete}
                                className="px-4 py-2 rounded-lg bg-error text-on-error text-xs font-semibold"
                            >
                                Hapus Tim
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
