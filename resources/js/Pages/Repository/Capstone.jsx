import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage, router, useForm } from '@inertiajs/react';
import { useState, useRef } from 'react';
import Pagination from '@/Components/Pagination';

export default function Capstone({ projects, dosens }) {
    const user = usePage().props.auth.user;

    const [confirmApproveId, setConfirmApproveId] = useState(null);
    const [confirmDeleteId, setConfirmDeleteId] = useState(null);

    const handleApprove = () => {
        if (confirmApproveId) {
            router.post(route('repository.capstone.approve', confirmApproveId), {}, {
                onSuccess: () => setConfirmApproveId(null)
            });
        }
    };

    const handleDelete = () => {
        if (confirmDeleteId) {
            router.delete(route('repository.capstone.delete', confirmDeleteId), {
                onSuccess: () => setConfirmDeleteId(null)
            });
        }
    };
    
    const [showModal, setShowModal] = useState(false);
    
    const { data, setData, post, processing, reset, errors } = useForm({
        title: '',
        academic_year: '',
        group_id: '',
        dosen_id: '',
        document: null
    });

    const fileInputRef = useRef(null);

    const submit = (e) => {
        e.preventDefault();
        post(route('repository.capstone.store'), {
            onSuccess: () => {
                setShowModal(false);
                reset();
            }
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Capstone Repository" />

            {/* Header Section */}
            <header className="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
                <div>
                    <h2 className="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-surface mb-2">Capstone Design Repository</h2>
                    <p className="font-body-lg text-body-lg text-on-surface-variant max-w-2xl">Manage and download your capstone design documents.</p>
                </div>
                <button 
                    onClick={() => setShowModal(true)}
                    className="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-inverse-primary to-primary-container text-white rounded-xl font-label-sm text-label-sm font-semibold shadow-[0_0_20px_rgba(73,75,214,0.4)] hover:shadow-[0_0_30px_rgba(73,75,214,0.6)] transition-all transform hover:-translate-y-0.5 backdrop-blur-md border border-white/10"
                >
                    <span className="material-symbols-outlined text-[20px]">upload_file</span>
                    Upload New Document
                </button>
            </header>

            {/* Glassmorphic Table Container */}
            <div className="bg-white/[0.08] backdrop-blur-[24px] border border-white/10 rounded-2xl shadow-2xl overflow-x-auto relative">
                <div className="absolute inset-x-0 top-0 h-[1px] bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
                <table className="w-full text-left border-collapse min-w-[600px]">
                    <thead>
                        <tr className="bg-surface-container/40 border-b border-white/10 font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">
                            <th className="px-6 py-4 font-semibold w-1/3">Project Name</th>
                            <th className="px-6 py-4 font-semibold w-1/6">Academic Year</th>
                            <th className="px-6 py-4 font-semibold w-1/6">Group ID</th>
                            <th className="px-6 py-4 font-semibold w-1/6">Status</th>
                            <th className="px-6 py-4 font-semibold text-right w-1/6">Action</th>
                        </tr>
                    </thead>
                    <tbody className="font-body-md text-sm divide-y divide-white/5">
                        {projects.data.map(project => (
                            <tr key={project.id} className="hover:bg-white/5 transition-colors group">
                                <td className="px-6 py-5">
                                    <div className="font-medium text-on-surface mb-1 group-hover:text-primary transition-colors">{project.title}</div>
                                    <div className="text-xs text-on-surface-variant flex items-center gap-3">
                                        <span className="flex items-center gap-1"><span className="material-symbols-outlined text-[14px]">person</span> {project.user.name}</span>
                                        <span className="flex items-center gap-1"><span className="material-symbols-outlined text-[14px]">school</span> {project.dosen.name}</span>
                                    </div>
                                </td>
                                <td className="px-6 py-5">
                                    <div className="text-on-surface font-mono text-xs mb-1">{project.group_id}</div>
                                    <div className="text-on-surface-variant text-xs">{project.academic_year}</div>
                                </td>
                                <td className="px-6 py-5">
                                    <div className="flex items-center gap-2">
                                        {project.status === 'approved' ? (
                                            <span className="px-2.5 py-1 rounded-full bg-primary/20 text-primary border border-primary/30 text-xs font-medium inline-flex items-center gap-1">
                                                <span className="material-symbols-outlined text-[14px]">check_circle</span> Approved
                                            </span>
                                        ) : (
                                            <span className="px-2.5 py-1 rounded-full bg-tertiary/20 text-tertiary border border-tertiary/30 text-xs font-medium inline-flex items-center gap-1">
                                                <span className="material-symbols-outlined text-[14px]">pending</span> Pending
                                            </span>
                                        )}
                                    </div>
                                </td>
                                <td className="px-6 py-5 text-right whitespace-nowrap">
                                    <div className="flex items-center justify-end gap-2">
                                        <a href={route('repository.capstone.download', project.id)} title="Download Document" className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-white/10 text-on-surface-variant hover:text-secondary hover:border-secondary hover:bg-secondary/10 transition-all duration-200 font-label-sm text-label-sm">
                                            <span className="material-symbols-outlined text-[18px]">download</span>
                                        </a>
                                        {(user.role === 'dosen' || user.role === 'kaprodi') && (
                                            <>
                                                {project.status === 'pending' && (
                                                    <button onClick={() => setConfirmApproveId(project.id)} title="Approve Document" className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-white/10 text-on-surface-variant hover:text-primary hover:border-primary hover:bg-primary/10 transition-all duration-200 font-label-sm text-label-sm">
                                                        <span className="material-symbols-outlined text-[18px]">thumb_up</span>
                                                    </button>
                                                )}
                                                <button onClick={() => setConfirmDeleteId(project.id)} title="Delete Document" className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-white/10 text-on-surface-variant hover:text-error hover:border-error hover:bg-error/10 transition-all duration-200 font-label-sm text-label-sm">
                                                    <span className="material-symbols-outlined text-[18px]">delete</span>
                                                </button>
                                            </>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {projects.data.length === 0 && (
                            <tr>
                                <td colSpan="4" className="px-6 py-12 text-center text-on-surface-variant bg-white/5">
                                    <span className="material-symbols-outlined text-[48px] mb-2 opacity-50">folder_open</span>
                                    <p>Belum ada Repository Capstone Design.</p>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
                <Pagination links={projects.links} />
            </div>

            {/* New Submission Modal */}
            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/60 backdrop-blur-md">
                    <div className="glass-panel w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col overflow-hidden relative">
                        {/* Close Button */}
                        <button 
                            onClick={() => setShowModal(false)}
                            aria-label="Close modal" 
                            className="absolute top-6 right-6 text-on-surface-variant hover:text-on-surface transition-colors z-10"
                        >
                            <span className="material-symbols-outlined">close</span>
                        </button>
                        
                        {/* Modal Header */}
                        <div className="px-8 pt-8 pb-6 border-b border-white/10">
                            <h2 className="font-headline-md text-headline-md text-on-surface flex items-center gap-3">
                                <span className="material-symbols-outlined text-primary">upload_file</span>
                                New Submission
                            </h2>
                            <p className="font-body-md text-body-md text-on-surface-variant mt-2">
                                Enter the details of the capstone design project to add it to the repository.
                            </p>
                        </div>
                        
                        {/* Modal Body (Form) */}
                        <form onSubmit={submit}>
                            <div className="p-8 flex flex-col gap-6 overflow-y-auto max-h-[60vh] scrollbar-thin">
                                {/* Title Field */}
                                <div className="flex flex-col gap-2">
                                    <label className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider" htmlFor="capstone-title">Title of Capstone Design</label>
                                    <input value={data.title} onChange={e => setData('title', e.target.value)} className="glass-input w-full rounded-lg px-4 py-3 font-body-md text-body-md" id="capstone-title" placeholder="e.g. Quantum Cryptography in Decentralized Networks" required type="text"/>
                                </div>
                                
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    {/* Group ID Field */}
                                    <div className="flex flex-col gap-2">
                                        <label className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider" htmlFor="group-id">Group ID</label>
                                        <input value={data.group_id} onChange={e => setData('group_id', e.target.value)} className="glass-input w-full rounded-lg px-4 py-3 font-body-md text-body-md" id="group-id" placeholder="e.g. CS-2024-G12" required type="text"/>
                                    </div>
                                    {/* Academic Year Field */}
                                    <div className="flex flex-col gap-2">
                                        <label className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider" htmlFor="academic-year">Academic Year</label>
                                        <input value={data.academic_year} onChange={e => setData('academic_year', e.target.value)} className="glass-input w-full rounded-lg px-4 py-3 font-body-md text-body-md" id="academic-year" placeholder="e.g. 2024-2025" required type="text"/>
                                    </div>
                                </div>

                                {/* Dosen Selection Field */}
                                <div className="flex flex-col gap-2">
                                    <label className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider" htmlFor="dosen-id">Dosen Pembimbing</label>
                                    <div className="relative">
                                        <select value={data.dosen_id} onChange={e => setData('dosen_id', e.target.value)} className="glass-input w-full rounded-lg px-4 py-3 font-body-md text-body-md appearance-none cursor-pointer" id="dosen-id" required>
                                            <option className="bg-surface text-on-surface" value="" disabled>Select Dosen</option>
                                            {dosens && dosens.map(dosen => (
                                                <option key={dosen.id} className="bg-surface text-on-surface" value={dosen.id}>{dosen.name}</option>
                                            ))}
                                        </select>
                                        <span className="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">expand_more</span>
                                    </div>
                                </div>
                            
                                {/* Upload PDF Section */}
                                <div className="flex flex-col gap-2 mt-2">
                                    <label className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Document Upload (PDF)</label>
                                    <div onClick={() => fileInputRef.current?.click()} className="border-2 border-dashed border-white/30 hover:border-primary transition-colors w-full rounded-xl p-8 flex flex-col items-center justify-center text-center cursor-pointer hover:bg-white/5 relative overflow-hidden">
                                        <input type="file" accept=".pdf" className="hidden" ref={fileInputRef} onChange={e => setData('document', e.target.files[0])} />
                                        <div className="w-16 h-16 rounded-full bg-surface-container-highest/50 flex items-center justify-center mb-4">
                                            <span className="material-symbols-outlined text-4xl text-secondary">{data.document ? 'task' : 'picture_as_pdf'}</span>
                                        </div>
                                        <p className="font-body-md text-body-md text-on-surface mb-1">
                                            {data.document ? data.document.name : 'Drag and drop your PDF here'}
                                        </p>
                                        <p className="font-label-sm text-label-sm text-on-surface-variant mb-4">
                                            {data.document ? `${(data.document.size / 1024 / 1024).toFixed(2)} MB` : 'or click to browse from your computer (Max 50MB)'}
                                        </p>
                                        <button className="bg-white/5 border border-white/20 hover:bg-white/10 text-on-surface transition-all rounded-full px-6 py-2 font-label-sm text-label-sm" type="button">
                                            Browse Files
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            {/* Modal Footer (Actions) */}
                            <div className="px-8 py-6 border-t border-white/10 bg-surface-container/30 flex justify-end gap-4 rounded-b-2xl">
                                <button 
                                    onClick={() => setShowModal(false)}
                                    className="bg-white/5 border border-white/20 hover:bg-white/10 text-on-surface transition-all rounded-lg px-6 py-2.5 font-label-sm text-label-sm uppercase tracking-wider" 
                                    type="button"
                                >
                                    Cancel
                                </button>
                                <button disabled={processing} className="bg-gradient-to-r from-primary to-tertiary-container text-on-primary-container shadow-[0_0_15px_rgba(192,193,255,0.4)] hover:shadow-[0_0_25px_rgba(192,193,255,0.6)] rounded-lg px-8 py-2.5 font-label-sm text-label-sm uppercase tracking-wider flex items-center gap-2 disabled:opacity-50" type="submit">
                                    <span className="material-symbols-outlined text-[18px]">add</span>
                                    Submit Project
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Approval Confirmation Modal */}
            {confirmApproveId && (
                <div className="fixed inset-0 bg-background/90 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-surface-container-high/90 border border-primary/30 w-full max-w-md rounded-2xl p-6 relative flex flex-col gap-4 shadow-[0_10px_40px_rgba(73,75,214,0.15)]">
                        <div className="w-12 h-12 rounded-full bg-primary/20 flex items-center justify-center text-primary mb-2">
                            <span className="material-symbols-outlined text-[28px]">thumb_up</span>
                        </div>
                        <div>
                            <h2 className="font-headline-sm text-headline-sm font-bold text-on-surface mb-1">Setujui Dokumen?</h2>
                            <p className="font-body-md text-body-md text-on-surface-variant">
                                Apakah Anda yakin ingin menyetujui dokumen ini?
                            </p>
                        </div>
                        <div className="flex justify-end gap-3 mt-4">
                            <button onClick={() => setConfirmApproveId(null)} className="px-5 py-2 rounded-lg font-label-md text-on-surface border border-outline-variant hover:bg-white/5 transition-colors">
                                Tidak
                            </button>
                            <button onClick={handleApprove} className="px-5 py-2 rounded-lg font-label-md bg-primary text-on-primary hover:bg-primary/90 transition-colors shadow-md shadow-primary/20">
                                Ya, Setujui
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Delete Confirmation Modal */}
            {confirmDeleteId && (
                <div className="fixed inset-0 bg-background/90 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-surface-container-high/90 border border-error/30 w-full max-w-md rounded-2xl p-6 relative flex flex-col gap-4 shadow-[0_10px_40px_rgba(255,84,73,0.15)]">
                        <div className="w-12 h-12 rounded-full bg-error/20 flex items-center justify-center text-error mb-2">
                            <span className="material-symbols-outlined text-[28px]">warning</span>
                        </div>
                        <div>
                            <h2 className="font-headline-sm text-headline-sm font-bold text-on-surface mb-1">Hapus Dokumen?</h2>
                            <p className="font-body-md text-body-md text-on-surface-variant">
                                Apakah Anda yakin ingin menghapus dokumen ini secara permanen? Tindakan ini tidak dapat dibatalkan.
                            </p>
                        </div>
                        <div className="flex justify-end gap-3 mt-4">
                            <button onClick={() => setConfirmDeleteId(null)} className="px-5 py-2 rounded-lg font-label-md text-on-surface border border-outline-variant hover:bg-white/5 transition-colors">
                                Batal
                            </button>
                            <button onClick={handleDelete} className="px-5 py-2 rounded-lg font-label-md bg-error text-on-error hover:bg-error/90 transition-colors shadow-md shadow-error/20">
                                Hapus Permanen
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
