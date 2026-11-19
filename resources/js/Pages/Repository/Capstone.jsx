import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import HoverTooltip from '@/Components/HoverTooltip';
import { Head, usePage, router, useForm } from '@inertiajs/react';
import { useState, useRef, useEffect, useMemo } from 'react';
import Pagination from '@/Components/Pagination';

export default function Capstone({ projects, dosens = [], mahasiswas = [] }) {
    const { user, capstoneTeam } = usePage().props.auth;
    const isRepoAdmin = ['admin', 'koordinator_ta', 'kaprodi'].includes(user.role);
    const isCoordinatorOrAdmin = ['admin', 'koordinator_ta'].includes(user.role);
    const isStaffView = ['admin', 'koordinator_ta', 'dosen', 'kaprodi'].includes(user.role);
    const isLockedToTeam = user.role === 'mahasiswa' && Boolean(capstoneTeam);

    const [confirmApproveId, setConfirmApproveId] = useState(null);
    const [confirmDeleteId, setConfirmDeleteId] = useState(null);
    const [detailProject, setDetailProject] = useState(null);
    const [reuploadTarget, setReuploadTarget] = useState(null);
    const [acceptNoteTarget, setAcceptNoteTarget] = useState(null);

    const [showModal, setShowModal] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [editId, setEditId] = useState(null);
    const [editingProject, setEditingProject] = useState(null);
    const [memberSearch, setMemberSearch] = useState('');

    const { data, setData, post, processing, reset, errors, clearErrors } = useForm({
        title: '',
        academic_year: '',
        group_id: '',
        dosen_id: '',
        member_ids: user.role === 'mahasiswa' ? [user.id] : [],
        document: null,
    });

    const {
        data: revData,
        setData: setRevData,
        post: postReuploadReq,
        processing: revProcessing,
        reset: resetRev,
        errors: revErrors,
        clearErrors: clearRevErrors,
    } = useForm({
        revision_note: '',
    });

    const {
        data: acceptData,
        setData: setAcceptData,
        post: postAcceptNote,
        processing: acceptProcessing,
        reset: resetAccept,
        errors: acceptErrors,
        clearErrors: clearAcceptErrors,
    } = useForm({
        acceptance_note: '',
    });

    const fileInputRef = useRef(null);

    const formatRoleLabel = (role) => {
        if (role === 'koordinator_ta') return 'Koordinator TA';
        if (role === 'dosen') return 'Dosen Pembimbing';
        if (role === 'kaprodi') return 'Kaprodi';
        if (role === 'admin') return 'Admin';
        return 'Pemeriksa';
    };

    const getReviewerSourceLabel = (project) => {
        if (project.revision_note || project.acceptance_note) {
            const roleStr = formatRoleLabel(project.reviewer_role);
            return project.reviewer?.name ? `${roleStr} (${project.reviewer.name})` : roleStr;
        }
        return 'Pemeriksaan Otomatis AI';
    };

    const hasCheckingProject = useMemo(
        () => Array.isArray(projects?.data) && projects.data.some((p) => p.status === 'checking'),
        [projects?.data]
    );

    useEffect(() => {
        if (!hasCheckingProject) return;
        const interval = setInterval(() => {
            router.reload({
                only: ['projects'],
                preserveScroll: true,
                preserveState: true,
            });
        }, 4000);
        return () => clearInterval(interval);
    }, [hasCheckingProject]);

    const isProjectMember = (project) => {
        if (project.user_id === user.id) return true;
        return Array.isArray(project.members) && project.members.some((m) => m.id === user.id);
    };

    const getMemberNames = (project) => {
        if (Array.isArray(project.members) && project.members.length > 0) {
            return project.members.map((m) => m.name).join(', ');
        }
        return project.user?.name || '-';
    };

    const mahasiswaMap = useMemo(() => {
        const map = new Map();
        mahasiswas.forEach((m) => map.set(m.id, m));
        if (capstoneTeam?.members) {
            capstoneTeam.members.forEach((m) => map.set(m.id, m));
        }
        if (user.role === 'mahasiswa' && !map.has(user.id)) {
            map.set(user.id, user);
        }
        return map;
    }, [mahasiswas, capstoneTeam, user]);

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

    const addMember = (id) => {
        if (isLockedToTeam) return;
        if (!data.member_ids.includes(id)) {
            setData('member_ids', [...data.member_ids, id]);
        }
        setMemberSearch('');
    };

    const removeMember = (id) => {
        if (isLockedToTeam) return;
        if (user.role === 'mahasiswa' && id === user.id) return;
        setData('member_ids', data.member_ids.filter((mId) => mId !== id));
    };

    const handleApprove = () => {
        if (confirmApproveId) {
            router.post(route('repository.capstone.approve', confirmApproveId), {}, {
                onSuccess: () => setConfirmApproveId(null),
            });
        }
    };

    const openAcceptWithNote = (project) => {
        clearAcceptErrors();
        setAcceptData('acceptance_note', project.acceptance_note || '');
        setDetailProject(null);
        setAcceptNoteTarget(project);
    };

    const submitAcceptWithNote = (e) => {
        e.preventDefault();
        if (!acceptNoteTarget) return;
        postAcceptNote(route('repository.capstone.accept-with-note', acceptNoteTarget.id), {
            preserveScroll: true,
            onSuccess: () => {
                setAcceptNoteTarget(null);
                resetAccept();
            },
        });
    };

    const openRequestReupload = (project) => {
        clearRevErrors();
        setRevData('revision_note', project.revision_note || '');
        setReuploadTarget(project);
    };

    const submitRequestReupload = (e) => {
        e.preventDefault();
        if (!reuploadTarget) return;
        postReuploadReq(route('repository.capstone.request-reupload', reuploadTarget.id), {
            preserveScroll: true,
            onSuccess: () => {
                setReuploadTarget(null);
                resetRev();
            },
        });
    };

    const handleRecheck = (projectId) => {
        router.post(route('repository.capstone.recheck', projectId), {}, {
            preserveScroll: true,
        });
    };

    const handleDelete = () => {
        if (confirmDeleteId) {
            router.delete(route('repository.capstone.delete', confirmDeleteId), {
                onSuccess: () => setConfirmDeleteId(null),
            });
        }
    };

    const handleNew = () => {
        clearErrors();
        if (isLockedToTeam && capstoneTeam) {
            setData({
                title: capstoneTeam.team_name || '',
                academic_year: capstoneTeam.academic_year || '',
                group_id: capstoneTeam.group_code || '',
                dosen_id: capstoneTeam.dosen_id || '',
                member_ids: Array.isArray(capstoneTeam.members) ? capstoneTeam.members.map((m) => m.id) : [user.id],
                document: null,
            });
        } else {
            setData({
                title: '',
                academic_year: '',
                group_id: '',
                dosen_id: '',
                member_ids: user.role === 'mahasiswa' ? [user.id] : [],
                document: null,
            });
        }
        setMemberSearch('');
        setIsEditing(false);
        setEditId(null);
        setEditingProject(null);
        setShowModal(true);
    };

    const handleEdit = (project) => {
        clearErrors();
        const existingMemberIds = Array.isArray(project.members) && project.members.length > 0
            ? project.members.map((m) => m.id)
            : (project.user_id ? [project.user_id] : []);

        if (user.role === 'mahasiswa' && !existingMemberIds.includes(user.id)) {
            existingMemberIds.unshift(user.id);
        }

        setData({
            title: project.title || '',
            academic_year: isLockedToTeam ? (capstoneTeam.academic_year || project.academic_year) : (project.academic_year || ''),
            group_id: isLockedToTeam ? (capstoneTeam.group_code || project.group_id) : (project.group_id || ''),
            dosen_id: isLockedToTeam ? (capstoneTeam.dosen_id || project.dosen_id) : (project.dosen_id || ''),
            member_ids: isLockedToTeam && Array.isArray(capstoneTeam.members)
                ? capstoneTeam.members.map((m) => m.id)
                : existingMemberIds,
            document: null,
        });
        setMemberSearch('');
        setIsEditing(true);
        setEditId(project.id);
        setEditingProject(project);
        setDetailProject(null);
        setShowModal(true);
    };

    const submit = (e) => {
        e.preventDefault();
        const url = isEditing
            ? route('repository.capstone.update', editId)
            : route('repository.capstone.store');

        post(url, {
            forceFormData: true,
            onSuccess: () => {
                setShowModal(false);
                reset();
                setIsEditing(false);
                setEditId(null);
                setEditingProject(null);
            },
        });
    };

    const renderStatusBadge = (project) => {
        if (isStaffView) {
            if (project.status === 'approved') {
                const tooltipText = project.acceptance_note
                    ? 'Approved — Diterima dengan Catatan (Klik untuk lihat catatan)'
                    : 'Approved (Terverifikasi)';
                return (
                    <HoverTooltip text={tooltipText}>
                        <button
                            type="button"
                            onClick={() => project.acceptance_note && setDetailProject(project)}
                            className={`w-9 h-9 rounded-full bg-primary/20 text-primary border border-primary/35 inline-flex items-center justify-center transition-all ${
                                project.acceptance_note ? 'cursor-pointer hover:bg-primary/30' : 'cursor-default'
                            }`}
                        >
                            <span className="material-symbols-outlined text-[18px]">
                                {project.acceptance_note ? 'task_alt' : 'check_circle'}
                            </span>
                        </button>
                    </HoverTooltip>
                );
            }

            if (project.status === 'checking') {
                return (
                    <HoverTooltip text="Sedang Memeriksa Kelengkapan PDF (AI)...">
                        <span className="w-9 h-9 rounded-full bg-secondary/20 text-secondary border border-secondary/35 inline-flex items-center justify-center">
                            <span className="material-symbols-outlined text-[18px] animate-spin">sync</span>
                        </span>
                    </HoverTooltip>
                );
            }

            if (project.status === 'rejected') {
                const tooltipText = project.revision_note
                    ? 'Diminta Upload Ulang — Klik untuk lihat catatan revisi'
                    : 'Tertahan AI (Wajib Upload Ulang) — Klik untuk lihat rincian kekurangan';
                return (
                    <HoverTooltip text={tooltipText}>
                        <button
                            type="button"
                            onClick={() => setDetailProject(project)}
                            className="w-9 h-9 rounded-full bg-error/20 text-error border border-error/45 hover:bg-error/30 inline-flex items-center justify-center transition-all"
                        >
                            <span className="material-symbols-outlined text-[18px]">gpp_bad</span>
                        </button>
                    </HoverTooltip>
                );
            }

            const pendingTooltip =
                project.ai_check_status === 'passed'
                    ? 'Pending — Lolos Cek AI, Menunggu Verifikasi (Klik detail)'
                    : 'Pending — Menunggu Verifikasi';

            return (
                <HoverTooltip text={pendingTooltip}>
                    <button
                        type="button"
                        onClick={() => project.ai_check_status === 'passed' && setDetailProject(project)}
                        className={`w-9 h-9 rounded-full bg-tertiary/20 text-tertiary border border-tertiary/35 inline-flex items-center justify-center relative transition-all ${
                            project.ai_check_status === 'passed' ? 'cursor-pointer hover:bg-tertiary/30' : 'cursor-default'
                        }`}
                    >
                        <span className="material-symbols-outlined text-[18px]">pending</span>
                        {project.ai_check_status === 'passed' && (
                            <span className="w-2.5 h-2.5 rounded-full bg-emerald-400 border border-surface absolute -top-0.5 -right-0.5"></span>
                        )}
                    </button>
                </HoverTooltip>
            );
        }

        if (project.status === 'approved') {
            return (
                <div className="flex flex-col items-start gap-1">
                    <span className="px-2.5 py-1 rounded-full bg-primary/20 text-primary border border-primary/30 text-xs font-medium inline-flex items-center gap-1">
                        <span className="material-symbols-outlined text-[14px]">check_circle</span> Approved
                    </span>
                    {project.acceptance_note && (
                        <button
                            type="button"
                            onClick={() => setDetailProject(project)}
                            className="text-[11px] text-secondary hover:underline inline-flex items-center gap-1"
                        >
                            <span className="material-symbols-outlined text-[13px]">sticky_note_2</span>
                            Diterima dgn Catatan
                        </button>
                    )}
                </div>
            );
        }

        if (project.status === 'checking') {
            return (
                <span className="px-2.5 py-1 rounded-full bg-secondary/20 text-secondary border border-secondary/30 text-xs font-medium inline-flex items-center gap-1.5">
                    <span className="material-symbols-outlined text-[14px] animate-spin">sync</span> Memeriksa PDF (AI)...
                </span>
            );
        }

        if (project.status === 'rejected') {
            return (
                <div className="flex flex-col items-start gap-1.5">
                    <span className="px-2.5 py-1 rounded-full bg-error/20 text-error border border-error/40 text-xs font-semibold inline-flex items-center gap-1">
                        <span className="material-symbols-outlined text-[14px]">block</span>
                        {project.revision_note ? 'Diminta Upload Ulang' : 'Tertahan - Wajib Upload Ulang'}
                    </span>
                    <button
                        type="button"
                        onClick={() => setDetailProject(project)}
                        className="text-xs text-error hover:underline inline-flex items-center gap-1 font-medium"
                    >
                        <span className="material-symbols-outlined text-[14px]">info</span>
                        Lihat Keterangan Kekurangan
                    </button>
                </div>
            );
        }

        return (
            <div className="flex flex-col items-start gap-1">
                <span className="px-2.5 py-1 rounded-full bg-tertiary/20 text-tertiary border border-tertiary/30 text-xs font-medium inline-flex items-center gap-1">
                    <span className="material-symbols-outlined text-[14px]">pending</span> Pending
                </span>
                {project.ai_check_status === 'passed' && (
                    <button
                        type="button"
                        onClick={() => setDetailProject(project)}
                        className="text-[11px] text-emerald-400 hover:underline inline-flex items-center gap-1"
                    >
                        <span className="material-symbols-outlined text-[13px]">verified</span>
                        Lolos Cek AI
                    </button>
                )}
            </div>
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Capstone Repository" />

            {/* Header Section */}
            <header className="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
                <div>
                    <h2 className="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-surface mb-2">
                        Capstone Design Repository
                    </h2>
                    <p className="font-body-lg text-body-lg text-on-surface-variant max-w-2xl">
                        Kelola dokumen laporan Capstone Design kelompok beserta pemeriksaan kelengkapan otomatis AI &amp; verifikasi dosen.
                    </p>
                </div>
                <button
                    onClick={handleNew}
                    className="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-inverse-primary to-primary-container text-white rounded-xl font-label-sm text-label-sm font-semibold shadow-[0_0_20px_rgba(73,75,214,0.4)] hover:shadow-[0_0_30px_rgba(73,75,214,0.6)] transition-all transform hover:-translate-y-0.5 backdrop-blur-md border border-white/10"
                >
                    <span className="material-symbols-outlined text-[20px]">upload_file</span>
                    Upload New Document
                </button>
            </header>

            {/* Glassmorphic Table Container */}
            <div className="bg-white/[0.08] backdrop-blur-[24px] border border-white/10 rounded-2xl shadow-2xl overflow-x-auto relative">
                <div className="absolute inset-x-0 top-0 h-[1px] bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
                <table className="w-full text-left border-collapse min-w-[700px]">
                    <thead>
                        <tr className="bg-surface-container/40 border-b border-white/10 font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">
                            <th className="px-6 py-4 font-semibold w-full">Project &amp; Anggota Kelompok</th>
                            <th className="px-4 py-4 font-semibold whitespace-nowrap">Academic Year</th>
                            <th className="px-4 py-4 font-semibold whitespace-nowrap">Group ID</th>
                            <th className={`px-4 py-4 font-semibold whitespace-nowrap ${isStaffView ? 'text-center' : ''}`}>Status</th>
                            <th className="px-6 py-4 font-semibold text-right whitespace-nowrap">Action</th>
                        </tr>
                    </thead>
                    <tbody className="font-body-md text-sm divide-y divide-white/5">
                        {projects.data.map((project) => {
                            const canEdit = isRepoAdmin || isProjectMember(project) || project.dosen_id === user.id;
                            const canManageApproval = isRepoAdmin || project.dosen_id === user.id;
                            const missingItems = project.ai_check_result?.missing_items || [];

                            return (
                                <tr key={project.id} className="hover:bg-white/5 transition-colors group align-top">
                                    <td className="px-6 py-5">
                                        <div className="font-medium text-on-surface mb-1.5 group-hover:text-primary transition-colors">
                                            {project.title}
                                        </div>
                                        <div className="text-xs text-on-surface-variant flex flex-col gap-1">
                                            <span className="flex items-start gap-1.5">
                                                <span className="material-symbols-outlined text-[15px] mt-0.5 text-secondary shrink-0">groups</span>
                                                <span>{getMemberNames(project)}</span>
                                            </span>
                                            <span className="flex items-center gap-1.5">
                                                <span className="material-symbols-outlined text-[15px] text-primary shrink-0">school</span>
                                                <span>{project.dosen?.name || '-'}</span>
                                            </span>
                                        </div>

                                        {project.acceptance_note && project.status === 'approved' && (
                                            <div className="mt-3 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-xs">
                                                <div className="font-semibold text-emerald-400 mb-0.5">
                                                    Catatan Penerimaan ({getReviewerSourceLabel(project)}):
                                                </div>
                                                <p className="text-on-surface-variant whitespace-pre-line">{project.acceptance_note}</p>
                                            </div>
                                        )}

                                        {project.status === 'rejected' && (project.revision_note || missingItems.length > 0) && (
                                            <div className="mt-3 p-3 rounded-xl bg-error/10 border border-error/30 text-xs space-y-2">
                                                {project.revision_note && (
                                                    <div>
                                                        <div className="font-semibold text-error flex items-center gap-1 mb-0.5">
                                                            <span className="material-symbols-outlined text-[15px]">assignment_return</span>
                                                            Diminta Upload Ulang oleh {getReviewerSourceLabel(project)}:
                                                        </div>
                                                        <p className="text-on-surface-variant whitespace-pre-line">{project.revision_note}</p>
                                                    </div>
                                                )}
                                                {!project.revision_note && missingItems.length > 0 && (
                                                    <div>
                                                        <div className="font-semibold text-error flex items-center gap-1 mb-1">
                                                            <span className="material-symbols-outlined text-[15px]">warning</span>
                                                            Dokumen Ditahan AI — Keterangan Kekurangan:
                                                        </div>
                                                        <ul className="list-disc pl-4 space-y-1 text-on-surface-variant">
                                                            {missingItems.slice(0, 3).map((item, idx) => (
                                                                <li key={idx}>{item}</li>
                                                            ))}
                                                            {missingItems.length > 3 && (
                                                                <li className="list-none pt-0.5">
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => setDetailProject(project)}
                                                                        className="text-error font-semibold hover:underline"
                                                                    >
                                                                        + {missingItems.length - 3} kekurangan lainnya (klik detail)
                                                                    </button>
                                                                </li>
                                                            )}
                                                        </ul>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-5 text-on-surface-variant text-sm whitespace-nowrap">
                                        {project.academic_year}
                                    </td>
                                    <td className="px-4 py-5 whitespace-nowrap">
                                        <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-white/5 border border-white/10 text-on-surface-variant font-label-sm text-label-sm">
                                            {project.group_id}
                                        </span>
                                    </td>
                                    <td className={`px-4 py-5 ${isStaffView ? 'text-center' : ''}`}>
                                        {renderStatusBadge(project)}
                                    </td>
                                    <td className="px-6 py-5 text-right whitespace-nowrap">
                                        <div className="flex items-center justify-end gap-1.5">
                                            {project.document_path && (
                                                <>
                                                    <HoverTooltip text="Lihat Dokumen PDF">
                                                        <a
                                                            href={route('repository.capstone.view', project.id)}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="w-9 h-9 rounded-lg border border-white/10 text-on-surface-variant hover:text-primary hover:border-primary hover:bg-primary/10 transition-all inline-flex items-center justify-center"
                                                        >
                                                            <span className="material-symbols-outlined text-[18px]">visibility</span>
                                                        </a>
                                                    </HoverTooltip>
                                                    <HoverTooltip text="Unduh Dokumen PDF">
                                                        <a
                                                            href={route('repository.capstone.download', project.id)}
                                                            className="w-9 h-9 rounded-lg border border-white/10 text-on-surface-variant hover:text-secondary hover:border-secondary hover:bg-secondary/10 transition-all inline-flex items-center justify-center"
                                                        >
                                                            <span className="material-symbols-outlined text-[18px]">download</span>
                                                        </a>
                                                    </HoverTooltip>
                                                </>
                                            )}

                                            {canEdit && (
                                                <HoverTooltip text={project.status === 'rejected' ? 'Upload Ulang PDF yang Sudah Diperbaiki' : 'Edit Dokumen & Anggota'}>
                                                    <button
                                                        onClick={() => handleEdit(project)}
                                                        className={`inline-flex items-center justify-center gap-1.5 rounded-lg border transition-all duration-200 font-label-sm text-label-sm ${
                                                            isStaffView ? 'w-9 h-9' : 'px-3 py-2'
                                                        } ${
                                                            project.status === 'rejected'
                                                                ? 'border-error/50 bg-error/15 text-error hover:bg-error/25 font-semibold'
                                                                : 'border-white/10 text-on-surface-variant hover:text-primary hover:border-primary hover:bg-primary/10'
                                                        }`}
                                                    >
                                                        <span className="material-symbols-outlined text-[18px]">
                                                            {project.status === 'rejected' ? 'upload_file' : 'edit'}
                                                        </span>
                                                        {!isStaffView && project.status === 'rejected' && <span className="text-xs">Upload Ulang</span>}
                                                    </button>
                                                </HoverTooltip>
                                            )}

                                            {isCoordinatorOrAdmin && project.status === 'rejected' && (
                                                <HoverTooltip text="Terima dengan Catatan (Simpan & Kirim Catatan ke Mahasiswa + Pembimbing)">
                                                    <button
                                                        type="button"
                                                        onClick={() => openAcceptWithNote(project)}
                                                        className="w-9 h-9 rounded-lg border border-emerald-500/40 bg-emerald-500/15 text-emerald-400 hover:bg-emerald-500/25 transition-all inline-flex items-center justify-center"
                                                    >
                                                        <span className="material-symbols-outlined text-[18px]">task_alt</span>
                                                    </button>
                                                </HoverTooltip>
                                            )}

                                            {canEdit && (project.ai_check_status === 'error' || (isRepoAdmin && project.status !== 'approved' && project.status !== 'checking')) && (
                                                <HoverTooltip text="Periksa Ulang Kelengkapan PDF dengan AI">
                                                    <button
                                                        onClick={() => handleRecheck(project.id)}
                                                        className="w-9 h-9 rounded-lg border border-white/10 text-on-surface-variant hover:text-secondary hover:border-secondary hover:bg-secondary/10 transition-all inline-flex items-center justify-center"
                                                    >
                                                        <span className="material-symbols-outlined text-[18px]">refresh</span>
                                                    </button>
                                                </HoverTooltip>
                                            )}

                                            {canManageApproval && (
                                                <>
                                                    {project.status === 'pending' && (
                                                        <HoverTooltip text="Setujui & Verifikasi Dokumen (Approve)">
                                                            <button
                                                                onClick={() => setConfirmApproveId(project.id)}
                                                                className="w-9 h-9 rounded-lg border border-white/10 text-on-surface-variant hover:text-primary hover:border-primary hover:bg-primary/10 transition-all inline-flex items-center justify-center"
                                                            >
                                                                <span className="material-symbols-outlined text-[18px]">thumb_up</span>
                                                            </button>
                                                        </HoverTooltip>
                                                    )}
                                                    {project.status !== 'checking' && (
                                                        <HoverTooltip text="Minta Mahasiswa Upload Ulang (Kirim Catatan Revisi)">
                                                            <button
                                                                onClick={() => openRequestReupload(project)}
                                                                className="w-9 h-9 rounded-lg border border-white/10 text-on-surface-variant hover:text-tertiary hover:border-tertiary hover:bg-tertiary/10 transition-all inline-flex items-center justify-center"
                                                            >
                                                                <span className="material-symbols-outlined text-[18px]">assignment_return</span>
                                                            </button>
                                                        </HoverTooltip>
                                                    )}
                                                    <HoverTooltip text="Hapus Dokumen">
                                                        <button
                                                            onClick={() => setConfirmDeleteId(project.id)}
                                                            className="w-9 h-9 rounded-lg border border-white/10 text-on-surface-variant hover:text-error hover:border-error hover:bg-error/10 transition-all inline-flex items-center justify-center"
                                                        >
                                                            <span className="material-symbols-outlined text-[18px]">delete</span>
                                                        </button>
                                                    </HoverTooltip>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                        {projects.data.length === 0 && (
                            <tr>
                                <td colSpan="5" className="px-6 py-12 text-center text-on-surface-variant bg-white/5">
                                    <span className="material-symbols-outlined text-[48px] mb-2 opacity-50">folder_open</span>
                                    <p>Belum ada Repository Capstone Design.</p>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
                <Pagination links={projects.links} />
            </div>

            {/* New / Edit / Re-upload Submission Modal */}
            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/70 backdrop-blur-md">
                    <div className="glass-panel w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col overflow-hidden relative max-h-[92vh]">
                        <button
                            onClick={() => setShowModal(false)}
                            aria-label="Close modal"
                            className="absolute top-6 right-6 text-on-surface-variant hover:text-on-surface transition-colors z-10"
                        >
                            <span className="material-symbols-outlined">close</span>
                        </button>

                        <div className="px-8 pt-7 pb-5 border-b border-white/10 shrink-0">
                            <h2 className="font-headline-md text-headline-md text-on-surface flex items-center gap-3">
                                <span className="material-symbols-outlined text-primary">
                                    {editingProject?.status === 'rejected' ? 'upload_file' : isEditing ? 'edit_document' : 'upload_file'}
                                </span>
                                {editingProject?.status === 'rejected'
                                    ? 'Upload Ulang PDF Capstone'
                                    : isEditing
                                        ? 'Edit Submission'
                                        : 'New Capstone Submission'}
                            </h2>
                            <p className="font-body-md text-body-md text-on-surface-variant mt-1.5">
                                {isLockedToTeam
                                    ? `Data Kelompok ${capstoneTeam.group_code} telah terisi otomatis. Masukkan judul lengkap & unggah berkas PDF.`
                                    : 'Isi detail Capstone, pilih anggota kelompok mahasiswa, dan unggah file laporan PDF.'}
                            </p>
                        </div>

                        <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                            <div className="p-8 flex flex-col gap-5 overflow-y-auto flex-1 scrollbar-thin">
                                {editingProject?.status === 'rejected' && (editingProject?.revision_note || Array.isArray(editingProject?.ai_check_result?.missing_items)) && (
                                    <div className="p-4 rounded-xl bg-error/10 border border-error/40 text-sm space-y-2">
                                        {editingProject.revision_note && (
                                            <div>
                                                <div className="font-semibold text-error flex items-center gap-1.5 mb-1">
                                                    <span className="material-symbols-outlined text-[18px]">assignment_return</span>
                                                    Catatan Revisi dari {getReviewerSourceLabel(editingProject)}:
                                                </div>
                                                <p className="text-xs text-on-surface-variant whitespace-pre-line">{editingProject.revision_note}</p>
                                            </div>
                                        )}
                                        {Array.isArray(editingProject?.ai_check_result?.missing_items) && editingProject.ai_check_result.missing_items.length > 0 && (
                                            <div>
                                                <div className="font-semibold text-error flex items-center gap-1.5 mb-1">
                                                    <span className="material-symbols-outlined text-[18px]">error</span>
                                                    Daftar Kekurangan Pemeriksaan AI:
                                                </div>
                                                <ul className="list-disc pl-5 space-y-1 text-on-surface-variant text-xs">
                                                    {editingProject.ai_check_result.missing_items.map((item, idx) => (
                                                        <li key={idx}>{item}</li>
                                                    ))}
                                                </ul>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {/* Title Field */}
                                <div className="flex flex-col gap-2">
                                    <label className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider" htmlFor="capstone-title">
                                        Title of Capstone Design
                                    </label>
                                    <input
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                        className="glass-input w-full rounded-lg px-4 py-3 font-body-md text-body-md"
                                        id="capstone-title"
                                        placeholder="Contoh: Rancang Bangun Sistem Pemantauan..."
                                        required
                                        type="text"
                                    />
                                    {errors.title && <p className="text-xs text-error">{errors.title}</p>}
                                </div>

                                {isLockedToTeam && capstoneTeam ? (
                                    <div className="p-4 rounded-xl bg-primary/10 border border-primary/30 space-y-2 text-xs">
                                        <div className="font-semibold text-primary uppercase tracking-wider">
                                            Data Kelompok Capstone (Otomatis dari Koordinator TA)
                                        </div>
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-on-surface">
                                            <div><b>Kode Kelompok:</b> {capstoneTeam.group_code}</div>
                                            <div><b>Tahun Ajaran:</b> {capstoneTeam.academic_year}</div>
                                            <div className="sm:col-span-2"><b>Dosen Pembimbing:</b> {capstoneTeam.dosen?.name || '-'}</div>
                                            <div className="sm:col-span-2">
                                                <b>Anggota:</b> {capstoneTeam.members?.map((m) => m.name).join(', ')}
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                                            <div className="flex flex-col gap-2">
                                                <label className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider" htmlFor="group-id">
                                                    Group ID
                                                </label>
                                                <input
                                                    value={data.group_id}
                                                    onChange={(e) => setData('group_id', e.target.value)}
                                                    className="glass-input w-full rounded-lg px-4 py-3 font-body-md text-body-md"
                                                    id="group-id"
                                                    placeholder="Contoh: E11-VI-82"
                                                    required
                                                    type="text"
                                                />
                                                {errors.group_id && <p className="text-xs text-error">{errors.group_id}</p>}
                                            </div>
                                            <div className="flex flex-col gap-2">
                                                <label className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider" htmlFor="academic-year">
                                                    Academic Year
                                                </label>
                                                <input
                                                    value={data.academic_year}
                                                    onChange={(e) => setData('academic_year', e.target.value)}
                                                    className="glass-input w-full rounded-lg px-4 py-3 font-body-md text-body-md"
                                                    id="academic-year"
                                                    placeholder="Contoh: 2025-2026"
                                                    required
                                                    type="text"
                                                />
                                                {errors.academic_year && <p className="text-xs text-error">{errors.academic_year}</p>}
                                            </div>
                                        </div>

                                        {/* Anggota Kelompok Selector */}
                                        <div className="flex flex-col gap-2">
                                            <label className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">
                                                Anggota Kelompok (Mahasiswa)
                                            </label>
                                            <div className="flex flex-wrap gap-2 p-3 rounded-xl bg-black/20 border border-white/15 min-h-[48px]">
                                                {data.member_ids.map((mId) => {
                                                    const m = mahasiswaMap.get(mId);
                                                    const isSelfLocked = user.role === 'mahasiswa' && mId === user.id;
                                                    return (
                                                        <span
                                                            key={mId}
                                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-primary/20 border border-primary/40 text-xs text-on-surface"
                                                        >
                                                            <span className="font-medium">
                                                                {m ? m.name : `User #${mId}`}
                                                                {m?.identity_number ? ` (${m.identity_number})` : ''}
                                                            </span>
                                                            {!isSelfLocked && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => removeMember(mId)}
                                                                    className="text-on-surface-variant hover:text-error ml-0.5 flex items-center"
                                                                >
                                                                    <span className="material-symbols-outlined text-[15px]">close</span>
                                                                </button>
                                                            )}
                                                        </span>
                                                    );
                                                })}
                                            </div>

                                            <div className="relative">
                                                <input
                                                    type="text"
                                                    value={memberSearch}
                                                    onChange={(e) => setMemberSearch(e.target.value)}
                                                    placeholder="Ketik nama atau NIM teman sekelompok untuk menambahkan..."
                                                    className="glass-input w-full rounded-lg px-4 py-2.5 pr-10 text-sm"
                                                />
                                                {memberSearch.trim() !== '' && (
                                                    <div className="mt-1 rounded-xl bg-surface-container-high border border-white/15 shadow-2xl max-h-48 overflow-y-auto divide-y divide-white/5">
                                                        {filteredMahasiswas.map((m) => (
                                                            <button
                                                                key={m.id}
                                                                type="button"
                                                                onClick={() => addMember(m.id)}
                                                                className="w-full px-4 py-2.5 text-left hover:bg-primary/15 flex items-center justify-between text-sm"
                                                            >
                                                                <div>
                                                                    <div className="font-medium text-on-surface">{m.name}</div>
                                                                    <div className="text-xs text-on-surface-variant">{m.identity_number || m.email}</div>
                                                                </div>
                                                                <span className="text-xs text-primary font-semibold">+ Tambah</span>
                                                            </button>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        {/* Dosen Selection Field */}
                                        <div className="flex flex-col gap-2">
                                            <label className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider" htmlFor="dosen-id">
                                                Dosen Pembimbing
                                            </label>
                                            <select
                                                value={data.dosen_id}
                                                onChange={(e) => setData('dosen_id', e.target.value)}
                                                className="glass-input w-full rounded-lg px-4 py-3 font-body-md text-body-md cursor-pointer [&>option]:bg-surface [&>option]:text-on-surface"
                                                id="dosen-id"
                                                required
                                            >
                                                <option value="" disabled>Pilih Dosen Pembimbing</option>
                                                {dosens.map((dosen) => (
                                                    <option key={dosen.id} value={dosen.id}>{dosen.name}</option>
                                                ))}
                                            </select>
                                        </div>
                                    </>
                                )}

                                {/* Upload PDF Section */}
                                <div className="flex flex-col gap-2 mt-1">
                                    <label className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">
                                        Document Upload (PDF) {(!isEditing || editingProject?.status === 'rejected') && <span className="text-error">*</span>}
                                    </label>
                                    <div
                                        onClick={() => fileInputRef.current?.click()}
                                        className="border-2 border-dashed border-white/30 hover:border-primary transition-colors w-full rounded-xl p-6 flex flex-col items-center justify-center text-center cursor-pointer hover:bg-white/5 relative overflow-hidden"
                                    >
                                        <input
                                            type="file"
                                            accept=".pdf,application/pdf"
                                            className="hidden"
                                            ref={fileInputRef}
                                            onChange={(e) => setData('document', e.target.files[0] || null)}
                                        />
                                        <div className="w-14 h-14 rounded-full bg-surface-container-highest/50 flex items-center justify-center mb-3">
                                            <span className="material-symbols-outlined text-3xl text-secondary">
                                                {data.document ? 'task' : 'picture_as_pdf'}
                                            </span>
                                        </div>
                                        <p className="font-body-md text-body-md text-on-surface mb-1">
                                            {data.document ? data.document.name : 'Klik untuk memilih file laporan PDF'}
                                        </p>
                                        <p className="font-label-sm text-label-sm text-on-surface-variant">
                                            {data.document
                                                ? `${(data.document.size / 1024 / 1024).toFixed(2)} MB`
                                                : 'Pastikan TTD Pengesahan & nomor halaman Daftar Isi sudah lengkap (Maks. 50MB)'}
                                        </p>
                                    </div>
                                    {errors.document && <p className="text-xs text-error font-medium">{errors.document}</p>}
                                </div>
                            </div>

                            <div className="px-8 py-5 border-t border-white/10 bg-surface-container/30 flex justify-end gap-4 rounded-b-2xl shrink-0">
                                <button
                                    onClick={() => setShowModal(false)}
                                    className="bg-white/5 border border-white/20 hover:bg-white/10 text-on-surface transition-all rounded-lg px-6 py-2.5 font-label-sm text-label-sm uppercase tracking-wider"
                                    type="button"
                                >
                                    Batal
                                </button>
                                <button
                                    disabled={processing}
                                    className="bg-gradient-to-r from-primary to-tertiary-container text-on-primary-container rounded-lg px-8 py-2.5 font-label-sm text-label-sm uppercase tracking-wider flex items-center gap-2 disabled:opacity-50"
                                    type="submit"
                                >
                                    <span>
                                        {processing
                                            ? 'Mengunggah...'
                                            : editingProject?.status === 'rejected'
                                                ? 'Unggah Ulang & Cek AI'
                                                : isEditing
                                                    ? 'Simpan Perubahan'
                                                    : 'Submit & Cek Kelengkapan'}
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Accept Incomplete PDF with Note Modal (Koordinator TA & Admin) */}
            {acceptNoteTarget && (
                <div className="fixed inset-0 bg-background/85 backdrop-blur-md z-50 flex items-center justify-center p-4">
                    <div className="bg-surface-container-high/95 border border-emerald-500/40 w-full max-w-lg rounded-2xl p-7 relative flex flex-col gap-5 shadow-2xl">
                        <button
                            onClick={() => setAcceptNoteTarget(null)}
                            className="absolute top-6 right-6 text-on-surface-variant hover:text-white"
                        >
                            <span className="material-symbols-outlined">close</span>
                        </button>

                        <div className="flex items-start gap-3 pr-8">
                            <div className="w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                                <span className="material-symbols-outlined text-[26px]">task_alt</span>
                            </div>
                            <div>
                                <h2 className="font-headline-sm text-headline-sm font-bold text-on-surface">
                                    Terima Dokumen dengan Catatan
                                </h2>
                                <p className="text-xs text-on-surface-variant mt-1">
                                    {acceptNoteTarget.title} ({acceptNoteTarget.group_id})
                                </p>
                            </div>
                        </div>

                        {Array.isArray(acceptNoteTarget.ai_check_result?.missing_items) &&
                            acceptNoteTarget.ai_check_result.missing_items.length > 0 && (
                                <div className="p-3.5 rounded-xl bg-tertiary/10 border border-tertiary/30 text-xs space-y-1 max-h-36 overflow-y-auto">
                                    <div className="font-semibold text-tertiary">Temuan AI Sebelumnya:</div>
                                    <ul className="list-disc pl-4 space-y-0.5 text-on-surface-variant">
                                        {acceptNoteTarget.ai_check_result.missing_items.map((it, idx) => (
                                            <li key={idx}>{it}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                        <form onSubmit={submitAcceptWithNote} className="flex flex-col gap-4">
                            <div className="flex flex-col gap-2">
                                <label className="font-label-sm text-label-sm text-on-surface">
                                    Catatan Penerimaan (Disimpan &amp; Diemailkan ke Mahasiswa + Pembimbing) <span className="text-error">*</span>
                                </label>
                                <textarea
                                    rows="4"
                                    value={acceptData.acceptance_note}
                                    onChange={(e) => setAcceptData('acceptance_note', e.target.value)}
                                    placeholder="Contoh: Diterima dengan catatan berkas fisik pengesahan telah diserahkan ke Koordinator TA..."
                                    className="glass-input w-full rounded-xl p-3.5 text-sm"
                                    required
                                />
                                {acceptErrors.acceptance_note && (
                                    <p className="text-xs text-error">{acceptErrors.acceptance_note}</p>
                                )}
                            </div>

                            <div className="flex justify-end gap-3 pt-3 border-t border-white/10">
                                <button
                                    type="button"
                                    onClick={() => setAcceptNoteTarget(null)}
                                    className="px-5 py-2 rounded-lg text-xs border border-outline-variant hover:bg-white/5"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={acceptProcessing}
                                    className="px-5 py-2 rounded-lg text-xs bg-emerald-600 text-white hover:bg-emerald-500 font-semibold flex items-center gap-1.5 disabled:opacity-50"
                                >
                                    <span className="material-symbols-outlined text-[16px]">check_circle</span>
                                    {acceptProcessing ? 'Menyimpan...' : 'Terima & Kirim Email Catatan'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Request Re-upload Modal */}
            {reuploadTarget && (
                <div className="fixed inset-0 bg-background/85 backdrop-blur-md z-50 flex items-center justify-center p-4">
                    <div className="bg-surface-container-high/95 border border-tertiary/40 w-full max-w-lg rounded-2xl p-7 relative flex flex-col gap-5 shadow-2xl">
                        <button
                            onClick={() => setReuploadTarget(null)}
                            className="absolute top-6 right-6 text-on-surface-variant hover:text-white"
                        >
                            <span className="material-symbols-outlined">close</span>
                        </button>

                        <div className="flex items-start gap-3 pr-8">
                            <div className="w-12 h-12 rounded-full bg-tertiary/20 text-tertiary flex items-center justify-center shrink-0">
                                <span className="material-symbols-outlined text-[26px]">assignment_return</span>
                            </div>
                            <div>
                                <h2 className="font-headline-sm text-headline-sm font-bold text-on-surface">
                                    Minta Mahasiswa Upload Ulang
                                </h2>
                                <p className="text-xs text-on-surface-variant mt-1">
                                    {reuploadTarget.title} ({reuploadTarget.group_id})
                                </p>
                            </div>
                        </div>

                        <form onSubmit={submitRequestReupload} className="flex flex-col gap-4">
                            <div className="flex flex-col gap-2">
                                <label className="font-label-sm text-label-sm text-on-surface">
                                    Keterangan Kekurangan / Catatan Revisi <span className="text-error">*</span>
                                </label>
                                <textarea
                                    rows="4"
                                    value={revData.revision_note}
                                    onChange={(e) => setRevData('revision_note', e.target.value)}
                                    placeholder="Tuliskan bagian laporan PDF yang perlu diperbaiki oleh mahasiswa sebelum upload ulang..."
                                    className="glass-input w-full rounded-xl p-3.5 text-sm"
                                    required
                                />
                                {revErrors.revision_note && <p className="text-xs text-error">{revErrors.revision_note}</p>}
                            </div>

                            <div className="flex justify-end gap-3 pt-3 border-t border-white/10">
                                <button
                                    type="button"
                                    onClick={() => setReuploadTarget(null)}
                                    className="px-5 py-2 rounded-lg text-xs border border-outline-variant hover:bg-white/5"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={revProcessing}
                                    className="px-5 py-2 rounded-lg text-xs bg-error text-on-error hover:bg-error/90 font-semibold flex items-center gap-1.5 disabled:opacity-50"
                                >
                                    <span className="material-symbols-outlined text-[16px]">send</span>
                                    {revProcessing ? 'Mengirim...' : 'Tahan & Kirim Email Revisi'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* AI Check & Note Detail Modal */}
            {detailProject && (
                <div className="fixed inset-0 bg-background/85 backdrop-blur-md z-50 flex items-center justify-center p-4">
                    <div className="bg-surface-container-high/95 border border-white/15 w-full max-w-xl rounded-2xl p-7 relative flex flex-col gap-5 shadow-2xl max-h-[88vh] overflow-y-auto">
                        <button
                            onClick={() => setDetailProject(null)}
                            className="absolute top-6 right-6 text-on-surface-variant hover:text-white"
                        >
                            <span className="material-symbols-outlined">close</span>
                        </button>

                        <div className="flex items-start gap-3 pr-8">
                            <div
                                className={`w-12 h-12 rounded-full flex items-center justify-center shrink-0 ${
                                    detailProject.status === 'rejected' ? 'bg-error/20 text-error' : 'bg-emerald-500/20 text-emerald-400'
                                }`}
                            >
                                <span className="material-symbols-outlined text-[26px]">
                                    {detailProject.status === 'rejected' ? 'gpp_bad' : 'verified'}
                                </span>
                            </div>
                            <div>
                                <h2 className="font-headline-sm text-headline-sm font-bold text-on-surface">
                                    {detailProject.status === 'rejected'
                                        ? 'Rincian Penahanan Dokumen'
                                        : 'Rincian Pemeriksaan & Catatan Verifikasi'}
                                </h2>
                                <p className="text-xs text-on-surface-variant mt-1">{detailProject.title}</p>
                            </div>
                        </div>

                        {detailProject.acceptance_note && (
                            <div className="p-4 rounded-xl border bg-emerald-500/10 border-emerald-500/35 text-sm space-y-1">
                                <div className="font-semibold text-emerald-400">
                                    Catatan Penerimaan ({getReviewerSourceLabel(detailProject)}):
                                </div>
                                <div className="text-on-surface whitespace-pre-line">{detailProject.acceptance_note}</div>
                            </div>
                        )}

                        {detailProject.revision_note && (
                            <div className="p-4 rounded-xl border bg-error/10 border-error/35 text-sm space-y-1">
                                <div className="font-semibold text-error">
                                    Catatan Revisi dari {getReviewerSourceLabel(detailProject)}:
                                </div>
                                <div className="text-on-surface whitespace-pre-line">{detailProject.revision_note}</div>
                            </div>
                        )}

                        {Array.isArray(detailProject.ai_check_result?.missing_items) &&
                            detailProject.ai_check_result.missing_items.length > 0 && (
                                <div className="flex flex-col gap-2">
                                    <h3 className="font-label-sm text-label-sm uppercase tracking-wider text-error font-semibold">
                                        Rincian Temuan AI:
                                    </h3>
                                    <ul className="space-y-2">
                                        {detailProject.ai_check_result.missing_items.map((item, idx) => (
                                            <li key={idx} className="p-3 rounded-lg bg-black/25 border border-error/25 text-sm text-on-surface flex items-start gap-2.5">
                                                <span className="material-symbols-outlined text-error text-[18px] mt-0.5 shrink-0">cancel</span>
                                                <span>{item}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                        <div className="flex flex-wrap justify-end gap-3 pt-3 border-t border-white/10">
                            <button
                                type="button"
                                onClick={() => setDetailProject(null)}
                                className="px-5 py-2 rounded-lg text-xs border border-outline-variant hover:bg-white/5"
                            >
                                Tutup
                            </button>
                            {detailProject.status === 'rejected' && isCoordinatorOrAdmin && (
                                <button
                                    type="button"
                                    onClick={() => openAcceptWithNote(detailProject)}
                                    className="px-5 py-2 rounded-lg text-xs bg-emerald-600 text-white hover:bg-emerald-500 font-semibold flex items-center gap-1.5"
                                >
                                    <span className="material-symbols-outlined text-[16px]">task_alt</span>
                                    Terima dgn Catatan
                                </button>
                            )}
                            {detailProject.status === 'rejected' &&
                                (isRepoAdmin || isProjectMember(detailProject) || detailProject.dosen_id === user.id) && (
                                    <button
                                        type="button"
                                        onClick={() => handleEdit(detailProject)}
                                        className="px-5 py-2 rounded-lg text-xs bg-error text-on-error hover:bg-error/90 font-semibold flex items-center gap-1.5"
                                    >
                                        <span className="material-symbols-outlined text-[16px]">upload_file</span>
                                        Upload Ulang PDF
                                    </button>
                                )}
                        </div>
                    </div>
                </div>
            )}

            {/* Approval Confirmation Modal */}
            {confirmApproveId && (
                <div className="fixed inset-0 bg-background/90 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-surface-container-high/90 border border-primary/30 w-full max-w-md rounded-2xl p-6 flex flex-col gap-4">
                        <h2 className="font-headline-sm text-headline-sm font-bold text-on-surface">Verifikasi &amp; Setujui Dokumen?</h2>
                        <p className="text-sm text-on-surface-variant">
                            Dokumen ini akan tampil di Katalog Publik dan email konfirmasi akan dikirim ke seluruh anggota kelompok.
                        </p>
                        <div className="flex justify-end gap-3 mt-2">
                            <button onClick={() => setConfirmApproveId(null)} className="px-5 py-2 rounded-lg text-xs border border-outline-variant">
                                Tidak
                            </button>
                            <button onClick={handleApprove} className="px-5 py-2 rounded-lg text-xs bg-primary text-on-primary font-semibold">
                                Ya, Setujui
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Delete Confirmation Modal */}
            {confirmDeleteId && (
                <div className="fixed inset-0 bg-background/90 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-surface-container-high/90 border border-error/30 w-full max-w-md rounded-2xl p-6 flex flex-col gap-4">
                        <h2 className="font-headline-sm text-headline-sm font-bold text-on-surface">Hapus Dokumen?</h2>
                        <p className="text-sm text-on-surface-variant">Tindakan ini tidak dapat dibatalkan.</p>
                        <div className="flex justify-end gap-3 mt-2">
                            <button onClick={() => setConfirmDeleteId(null)} className="px-5 py-2 rounded-lg text-xs border border-outline-variant">
                                Batal
                            </button>
                            <button onClick={handleDelete} className="px-5 py-2 rounded-lg text-xs bg-error text-on-error font-semibold">
                                Hapus Permanen
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
