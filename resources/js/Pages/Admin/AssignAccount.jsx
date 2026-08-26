import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage, useForm, router } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import Pagination from '@/Components/Pagination';

export default function AssignAccount({ users, filters }) {
    const { auth } = usePage().props;
    
    // Add Account State & Form
    const [showAdd, setShowAdd] = useState(false);
    const { data: addData, setData: setAddData, post: postAdd, processing: addProcessing, reset: resetAdd, errors: addErrors } = useForm({
        nama_lengkap: '',
        email: '',
        nim_npp: '',
        role: ''
    });

    const submitAdd = (e) => {
        e.preventDefault();
        postAdd('/assign-account/add', {
            onSuccess: () => {
                setShowAdd(false);
                resetAdd();
            }
        });
    };

    // Edit Account State
    const [editUser, setEditUser] = useState(null);
    const { data: editData, setData: setEditData, put: putEdit, processing: editProcessing, reset: resetEdit } = useForm({
        nama_lengkap: '',
        email: '',
        nim_npp: '',
        role: ''
    });

    const openEdit = (u) => {
        setEditData({
            nama_lengkap: u.name,
            email: u.email || '',
            nim_npp: u.identity_number || '',
            role: u.role
        });
        setEditUser(u);
    };

    const submitEdit = (e) => {
        e.preventDefault();
        putEdit(`/assign-account/${editUser.id}`, {
            onSuccess: () => {
                setEditUser(null);
                resetEdit();
            }
        });
    };

    // Delete Account State
    const [deleteUser, setDeleteUser] = useState(null);
    const { delete: destroyUser, processing: deleteProcessing } = useForm();

    const submitDelete = (e) => {
        e.preventDefault();
        destroyUser(`/assign-account/${deleteUser.id}`, {
            onSuccess: () => setDeleteUser(null)
        });
    };

    // Import Account State & Form
    const [showImport, setShowImport] = useState(false);
    const { data: importData, setData: setImportData, post: postImport, processing: importProcessing, reset: resetImport, errors: importErrors } = useForm({
        file: null
    });
    const fileInputRef = useRef(null);
    const [dragActive, setDragActive] = useState(false);

    const handleDrag = function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === "dragenter" || e.type === "dragover") {
            setDragActive(true);
        } else if (e.type === "dragleave") {
            setDragActive(false);
        }
    };

    const handleDrop = function(e) {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            setImportData('file', e.dataTransfer.files[0]);
        }
    };

    const handleChange = function(e) {
        e.preventDefault();
        if (e.target.files && e.target.files[0]) {
            setImportData('file', e.target.files[0]);
        }
    };

    const submitImport = (e) => {
        e.preventDefault();
        postImport('/assign-account/import', {
            onSuccess: () => {
                setShowImport(false);
                resetImport();
            }
        });
    };

    // Advanced Table Features (Search, Filter, Sort)
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');
    const [roleFilter, setRoleFilter] = useState(filters?.role || 'all');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'created_at');
    const [sortDir, setSortDir] = useState(filters?.sort_dir || 'desc');

    const fetchData = (params) => {
        router.get('/assign-account', {
            search: params.search ?? searchTerm,
            role: params.role ?? roleFilter,
            sort_by: params.sort_by ?? sortBy,
            sort_dir: params.sort_dir ?? sortDir
        }, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            if (searchTerm !== (filters?.search || '')) fetchData({ search: searchTerm });
        }, 300);
        return () => clearTimeout(timer);
    }, [searchTerm]);

    const handleSort = (column) => {
        const newDir = sortBy === column && sortDir === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortDir(newDir);
        fetchData({ sort_by: column, sort_dir: newDir });
    };

    const SortIcon = ({ column }) => {
        if (sortBy !== column) return null;
        return <span className="material-symbols-outlined text-[14px] align-middle ml-1">{sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward'}</span>;
    };

    const getInitials = (name) => {
        return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
    };

    const getRoleColor = (role) => {
        switch(role) {
            case 'admin': return 'error';
            case 'mahasiswa': return 'secondary';
            case 'dosen': return 'tertiary';
            case 'kaprodi': return 'primary';
            default: return 'primary';
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Assign Account" />
            
            {/* Page Header & Actions */}
            <div className="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
                <div>
                    <h2 className="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-surface mb-2">Assign Account</h2>
                    <p className="font-body-lg text-body-lg text-on-surface-variant">Manage user access, roles, and institutional identifiers securely.</p>
                </div>
                <div className="flex flex-wrap gap-3 w-full md:w-auto">
                    <button onClick={() => setShowImport(true)} className="flex-1 md:flex-none px-6 py-2.5 rounded-lg border border-white/20 bg-white/5 hover:bg-white/10 text-on-surface font-label-sm text-label-sm transition-all flex items-center justify-center gap-2 backdrop-blur-md">
                        <span className="material-symbols-outlined text-[18px]">upload_file</span> Import Massal
                    </button>
                    <button onClick={() => setShowAdd(true)} className="flex-1 md:flex-none px-6 py-2.5 rounded-lg bg-gradient-to-r from-inverse-primary to-primary-container text-white font-label-sm text-label-sm font-bold shadow-[0_0_15px_rgba(73,75,214,0.4)] hover:shadow-[0_0_25px_rgba(73,75,214,0.6)] transition-all flex items-center justify-center gap-2">
                        <span className="material-symbols-outlined text-[18px] font-bold">add</span>
                        + Tambah Akun
                    </button>
                </div>
            </div>

            {/* Data Grid (Glass Panel) */}
            <div className="glass-panel rounded-xl overflow-hidden shadow-2xl flex flex-col">
                <div className="px-6 py-4 border-b border-white/10 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-surface-container-low/50">
                    <div className="flex flex-wrap items-center gap-3">
                        <select 
                            value={roleFilter}
                            onChange={(e) => {
                                setRoleFilter(e.target.value);
                                fetchData({ role: e.target.value });
                            }}
                            className="bg-primary/20 border border-primary/30 text-primary font-label-sm text-[12px] rounded-full px-3 py-1 outline-none cursor-pointer focus:ring-1 focus:ring-primary/50 [&>option]:bg-surface-container [&>option]:text-on-surface"
                        >
                            <option value="all">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="mahasiswa">Mahasiswa</option>
                            <option value="dosen">Dosen</option>
                            <option value="kaprodi">Kaprodi</option>
                        </select>
                        <div className="relative">
                            <input 
                                type="text"
                                placeholder="Search Name/NIM/Email..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="bg-white/5 border border-white/10 rounded-full px-4 py-1.5 text-xs text-on-surface focus:outline-none focus:border-primary w-full sm:w-64 transition-all"
                            />
                            <span className="material-symbols-outlined absolute right-3 top-[7px] text-on-surface-variant text-[16px] pointer-events-none">search</span>
                        </div>
                    </div>
                    <span className="font-body-md text-sm text-on-surface-variant">Showing {users.from || 0} to {users.to || 0} of {users.total} users</span>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="border-b border-white/10 bg-black/20 text-on-surface-variant font-label-sm text-label-sm uppercase tracking-wider">
                                <th className="px-6 py-4 font-semibold cursor-pointer hover:text-white transition-colors" onClick={() => handleSort('name')}>
                                    Nama Pengguna <SortIcon column="name" />
                                </th>
                                <th className="px-6 py-4 font-semibold cursor-pointer hover:text-white transition-colors" onClick={() => handleSort('role')}>
                                    Role <SortIcon column="role" />
                                </th>
                                <th className="px-6 py-4 font-semibold cursor-pointer hover:text-white transition-colors" onClick={() => handleSort('identity_number')}>
                                    NIM/NPP <SortIcon column="identity_number" />
                                </th>
                                <th className="px-6 py-4 font-semibold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="font-body-md text-sm divide-y divide-white/5">
                            {users.data.map(u => {
                                const color = getRoleColor(u.role);
                                return (
                                    <tr key={u.id} className="hover:bg-white/5 transition-colors group">
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-3">
                                                <div className={`w-8 h-8 rounded-full bg-${color}/20 flex items-center justify-center text-${color} font-bold text-xs border border-${color}/30`}>
                                                    {getInitials(u.name)}
                                                </div>
                                                <div>
                                                    <div className="text-on-surface font-medium group-hover:text-primary transition-colors">{u.name}</div>
                                                    <div className="text-on-surface-variant text-xs">{u.email}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`px-2.5 py-1 rounded-full bg-${color}/20 text-${color} border border-${color}/30 text-xs font-medium inline-flex items-center gap-1 capitalize`}>
                                                <span className={`w-1.5 h-1.5 rounded-full bg-${color}`}></span>
                                                {u.role}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-on-surface-variant font-mono text-xs">
                                            {u.identity_number || '-'}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button onClick={() => openEdit(u)} className="w-8 h-8 rounded glass-panel flex items-center justify-center text-on-surface-variant hover:text-secondary hover:border-secondary/50 transition-colors" title="Edit">
                                                    <span className="material-symbols-outlined text-[18px]">edit</span>
                                                </button>
                                                <button onClick={() => setDeleteUser(u)} className="w-8 h-8 rounded glass-panel flex items-center justify-center text-on-surface-variant hover:text-error hover:border-error/50 transition-colors" title="Delete">
                                                    <span className="material-symbols-outlined text-[18px]">delete</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                            {users.data.length === 0 && (
                                <tr>
                                    <td colSpan="4" className="px-6 py-8 text-center text-on-surface-variant">No users found.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination links={users.links} />
            </div>

            {/* Modal Tambah Akun */}
            {showAdd && (
                <div className="fixed inset-0 bg-background/80 backdrop-blur-md z-50 flex items-center justify-center p-4">
                    <div className="bg-surface-container-high/80 backdrop-blur-3xl border border-white/15 border-t-white/25 shadow-[0_8px_32px_rgba(0,0,0,0.37)] w-full max-w-lg rounded-2xl p-8 relative flex flex-col gap-6">
                        <button onClick={() => setShowAdd(false)} aria-label="Tutup" className="absolute top-6 right-6 text-on-surface-variant hover:text-white transition-colors">
                            <span className="material-symbols-outlined" data-icon="close">close</span>
                        </button>
                        
                        <div>
                            <h2 className="font-headline-md text-headline-md font-bold text-primary mb-2">Tambah Akun Manual</h2>
                            <p className="font-body-md text-body-md text-on-surface-variant">Masukkan detail pengguna baru ke dalam sistem ThesisCloud.</p>
                        </div>
                        
                        <form onSubmit={submitAdd} className="flex flex-col gap-5">
                            <div className="flex flex-col gap-2">
                                <label className="font-label-sm text-label-sm text-on-surface" htmlFor="nama-lengkap">Nama Lengkap</label>
                                <input value={addData.nama_lengkap} onChange={e => setAddData('nama_lengkap', e.target.value)} className="bg-black/20 border border-white/20 text-on-surface rounded-lg px-4 py-3 font-body-md w-full focus:border-secondary focus:ring-1 focus:ring-secondary/50 outline-none transition-all" id="nama-lengkap" placeholder="Contoh: Budi Santoso" required type="text"/>
                            </div>
                            <div className="flex flex-col gap-2">
                                <label className="font-label-sm text-label-sm text-on-surface" htmlFor="email">Alamat Email</label>
                                <input value={addData.email} onChange={e => setAddData('email', e.target.value)} className="bg-black/20 border border-white/20 text-on-surface rounded-lg px-4 py-3 font-body-md w-full focus:border-secondary focus:ring-1 focus:ring-secondary/50 outline-none transition-all" id="email" placeholder="Contoh: budi@univ.edu" required type="email"/>
                            </div>
                            <div className="flex flex-col gap-2">
                                <label className="font-label-sm text-label-sm text-on-surface" htmlFor="nim-npp">NIM / NPP</label>
                                <input value={addData.nim_npp} onChange={e => setAddData('nim_npp', e.target.value)} className="bg-black/20 border border-white/20 text-on-surface rounded-lg px-4 py-3 font-body-md w-full focus:border-secondary focus:ring-1 focus:ring-secondary/50 outline-none transition-all" id="nim-npp" placeholder="Masukkan nomor identitas" required type="text"/>
                            </div>
                            <div className="flex flex-col gap-2">
                                <label className="font-label-sm text-label-sm text-on-surface" htmlFor="role">Pilih Role</label>
                                <div className="relative">
                                    <select value={addData.role} onChange={e => setAddData('role', e.target.value)} className="bg-black/20 border border-white/20 text-on-surface rounded-lg px-4 py-3 font-body-md w-full appearance-none pr-10 focus:border-secondary focus:ring-1 focus:ring-secondary/50 outline-none transition-all [&>option]:bg-surface-container [&>option]:text-on-surface" id="role" required>
                                        <option disabled hidden value="">Pilih peran pengguna...</option>
                                        <option value="mahasiswa">Mahasiswa</option>
                                        <option value="dosen">Dosen</option>
                                        <option value="kaprodi">Kaprodi</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div className="bg-secondary-container/10 border border-secondary/20 rounded-lg p-4 flex items-start space-x-3 mt-2">
                                <span className="material-symbols-outlined text-secondary mt-0.5" data-icon="info">info</span>
                                <div>
                                    <p className="font-label-sm text-label-sm text-secondary uppercase tracking-wider mb-1">Informasi Kredensial</p>
                                    <p className="font-body-md text-body-md text-on-surface-variant text-sm">Kata sandi default: <span className="font-mono text-white bg-white/10 px-2 py-0.5 rounded">knowledgebank123</span></p>
                                </div>
                            </div>
                            <div className="flex justify-end space-x-4 mt-4 pt-4 border-t border-white/10">
                                <button onClick={() => setShowAdd(false)} className="px-6 py-2.5 rounded-lg font-body-md text-body-md font-semibold text-on-surface-variant hover:text-white border border-transparent hover:border-white/20 transition-all" type="button">
                                    Batal
                                </button>
                                <button disabled={addProcessing} className="bg-gradient-to-r from-inverse-primary to-tertiary-container text-white px-6 py-2.5 rounded-lg font-body-md text-body-md font-semibold shadow-[0_0_15px_rgba(73,75,214,0.4)] hover:shadow-[0_0_25px_rgba(73,75,214,0.6)] transition-all disabled:opacity-50" type="submit">
                                    Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Import Massal */}
            {showImport && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="absolute inset-0 bg-background/80 backdrop-blur-md"></div>
                    <div className="bg-surface-container-high/80 backdrop-blur-3xl border border-white/15 w-full max-w-lg rounded-2xl relative overflow-hidden flex flex-col shadow-2xl">
                        <div className="px-8 py-6 border-b border-white/10 flex justify-between items-center relative z-10">
                            <h2 className="font-headline-md text-headline-md font-bold text-on-surface">Import Massal Akun</h2>
                            <button onClick={() => setShowImport(false)} className="text-on-surface-variant hover:text-on-surface transition-colors rounded-full p-1 hover:bg-white/10">
                                <span className="material-symbols-outlined">close</span>
                            </button>
                        </div>
                        <form onSubmit={submitImport} className="p-8 flex flex-col gap-6 relative z-10">
                            <div className="flex items-center justify-between bg-surface-container/50 border border-white/5 rounded-xl p-4">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-lg bg-secondary-container/20 flex items-center justify-center text-secondary">
                                        <span className="material-symbols-outlined">description</span>
                                    </div>
                                    <div>
                                        <h3 className="font-label-sm text-label-sm text-on-surface">Format Template</h3>
                                        <p className="text-xs text-on-surface-variant mt-0.5">Name,Email,Role,NIM/NPP</p>
                                    </div>
                                </div>
                                <a href="/assign-account/template" download className="px-3 py-1.5 rounded bg-white/5 border border-white/10 hover:bg-white/10 text-on-surface font-label-sm text-xs transition-colors flex items-center gap-1">
                                    <span className="material-symbols-outlined text-[14px]">download</span> Unduh .xlsx
                                </a>
                            </div>
                            
                            <div className={`border-2 border-dashed rounded-xl p-10 flex flex-col items-center justify-center text-center gap-4 transition-all duration-200 cursor-pointer ${dragActive ? "border-primary bg-primary/5" : "border-outline-variant hover:border-primary hover:bg-primary/5"}`} onDragEnter={handleDrag} onDragLeave={handleDrag} onDragOver={handleDrag} onDrop={handleDrop} onClick={() => fileInputRef.current.click()}>
                                <div className="w-16 h-16 rounded-full bg-surface-container-highest flex items-center justify-center text-on-surface-variant mb-2">
                                    <span className="material-symbols-outlined text-[32px]">cloud_upload</span>
                                </div>
                                <div>
                                    <p className="font-body-md text-body-md text-on-surface"><span className="font-bold text-primary">Klik untuk unggah</span> atau seret file ke sini</p>
                                    <p className="font-label-sm text-label-sm text-on-surface-variant mt-1">Mendukung format .csv (Maks. 5MB)</p>
                                    {importData.file && (
                                        <p className="mt-2 text-primary text-sm font-semibold border border-primary/30 bg-primary/10 px-2 py-1 rounded">{importData.file.name}</p>
                                    )}
                                </div>
                                <input ref={fileInputRef} onChange={handleChange} accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" className="hidden" type="file"/>
                            </div>
                            
                            <div className="flex justify-end gap-3 mt-4">
                                <button type="button" onClick={() => setShowImport(false)} className="px-5 py-2.5 rounded-lg border border-outline-variant text-on-surface hover:bg-white/5 transition-colors font-label-sm text-label-sm">
                                    Batal
                                </button>
                                <button type="submit" disabled={!importData.file || importProcessing} className={`px-5 py-2.5 rounded-lg bg-inverse-primary text-white font-label-sm text-label-sm flex items-center gap-2 ${!importData.file ? 'opacity-50 cursor-not-allowed' : 'hover:opacity-90 shadow-[0_0_20px_-5px_rgba(73,75,214,0.6)]'}`}>
                                    <span className="material-symbols-outlined text-sm">upload</span>
                                    Unggah File
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Edit User Modal */}
            {editUser && (
                <div className="fixed inset-0 bg-background/80 backdrop-blur-md z-50 flex items-center justify-center p-4">
                    <div className="bg-surface-container-high/80 backdrop-blur-3xl border border-white/15 border-t-white/25 shadow-[0_8px_32px_rgba(0,0,0,0.37)] w-full max-w-lg rounded-2xl p-8 relative flex flex-col gap-6">
                        <button onClick={() => setEditUser(null)} aria-label="Tutup" className="absolute top-6 right-6 text-on-surface-variant hover:text-white transition-colors">
                            <span className="material-symbols-outlined">close</span>
                        </button>
                        
                        <div>
                            <h2 className="font-headline-md text-headline-md font-bold text-secondary mb-2">Edit Akun</h2>
                            <p className="font-body-md text-body-md text-on-surface-variant">Ubah detail pengguna <b>{editUser.name}</b>.</p>
                        </div>
                        
                        <form onSubmit={submitEdit} className="flex flex-col gap-5">
                            <div className="flex flex-col gap-2">
                                <label className="font-label-sm text-label-sm text-on-surface" htmlFor="edit-nama-lengkap">Nama Lengkap</label>
                                <input value={editData.nama_lengkap} onChange={e => setEditData('nama_lengkap', e.target.value)} className="bg-black/20 border border-white/20 text-on-surface rounded-lg px-4 py-3 font-body-md w-full focus:border-secondary focus:ring-1 focus:ring-secondary/50 outline-none transition-all" id="edit-nama-lengkap" required type="text"/>
                            </div>
                            <div className="flex flex-col gap-2">
                                <label className="font-label-sm text-label-sm text-on-surface" htmlFor="edit-email">Alamat Email</label>
                                <input value={editData.email} onChange={e => setEditData('email', e.target.value)} className="bg-black/20 border border-white/20 text-on-surface rounded-lg px-4 py-3 font-body-md w-full focus:border-secondary focus:ring-1 focus:ring-secondary/50 outline-none transition-all" id="edit-email" required type="email"/>
                            </div>
                            <div className="flex flex-col gap-2">
                                <label className="font-label-sm text-label-sm text-on-surface" htmlFor="edit-nim-npp">NIM / NPP</label>
                                <input value={editData.nim_npp} onChange={e => setEditData('nim_npp', e.target.value)} className="bg-black/20 border border-white/20 text-on-surface rounded-lg px-4 py-3 font-body-md w-full focus:border-secondary focus:ring-1 focus:ring-secondary/50 outline-none transition-all" id="edit-nim-npp" required type="text"/>
                            </div>
                            <div className="flex flex-col gap-2">
                                <label className="font-label-sm text-label-sm text-on-surface" htmlFor="edit-role">Role</label>
                                <select value={editData.role} onChange={e => setEditData('role', e.target.value)} className="bg-black/20 border border-white/20 text-on-surface rounded-lg px-4 py-3 font-body-md w-full appearance-none pr-10 focus:border-secondary focus:ring-1 focus:ring-secondary/50 outline-none transition-all [&>option]:bg-surface-container [&>option]:text-on-surface" id="edit-role" required>
                                    <option value="mahasiswa">Mahasiswa</option>
                                    <option value="dosen">Dosen</option>
                                    <option value="kaprodi">Kaprodi</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            
                            <div className="flex justify-end space-x-4 mt-4 pt-4 border-t border-white/10">
                                <button onClick={() => setEditUser(null)} className="px-6 py-2.5 rounded-lg font-body-md text-body-md font-semibold text-on-surface-variant hover:text-white border border-transparent hover:border-white/20 transition-all" type="button">
                                    Batal
                                </button>
                                <button disabled={editProcessing} className="bg-secondary text-on-secondary px-6 py-2.5 rounded-lg font-body-md text-body-md font-semibold hover:bg-secondary/90 shadow-[0_0_15px_rgba(204,204,255,0.2)] transition-all disabled:opacity-50" type="submit">
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Delete Confirmation Modal */}
            {deleteUser && (
                <div className="fixed inset-0 bg-background/90 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-surface-container-high/90 border border-error/30 w-full max-w-md rounded-2xl p-6 relative flex flex-col gap-4 shadow-[0_10px_40px_rgba(255,84,73,0.15)]">
                        <div className="w-12 h-12 rounded-full bg-error/20 flex items-center justify-center text-error mb-2">
                            <span className="material-symbols-outlined text-[28px]">warning</span>
                        </div>
                        <div>
                            <h2 className="font-headline-sm text-headline-sm font-bold text-on-surface mb-1">Hapus Akun Pengguna?</h2>
                            <p className="font-body-md text-body-md text-on-surface-variant">
                                Anda yakin ingin menghapus akun <b>{deleteUser.name}</b>? Semua data yang terkait dengan akun ini mungkin akan terpengaruh. Tindakan ini tidak dapat dibatalkan.
                            </p>
                        </div>
                        <div className="flex justify-end gap-3 mt-4">
                            <button onClick={() => setDeleteUser(null)} className="px-5 py-2 rounded-lg font-label-md text-on-surface border border-outline-variant hover:bg-white/5 transition-colors">
                                Batal
                            </button>
                            <form onSubmit={submitDelete}>
                                <button disabled={deleteProcessing} type="submit" className="px-5 py-2 rounded-lg font-label-md bg-error text-on-error hover:bg-error/90 transition-colors shadow-md shadow-error/20 disabled:opacity-50">
                                    Hapus Permanen
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
