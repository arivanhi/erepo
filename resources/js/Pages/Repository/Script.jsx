import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage, router, useForm } from '@inertiajs/react';
import { useState, useRef } from 'react';
import Pagination from '@/Components/Pagination';

export default function Script({ projects, capstones }) {
    const user = usePage().props.auth.user;

    const [confirmApproveId, setConfirmApproveId] = useState(null);
    const [confirmDeleteId, setConfirmDeleteId] = useState(null);

    const handleApprove = () => {
        if (confirmApproveId) {
            router.post(route('repository.script.approve', confirmApproveId), {}, {
                onSuccess: () => setConfirmApproveId(null)
            });
        }
    };

    const handleDelete = () => {
        if (confirmDeleteId) {
            router.delete(route('repository.script.delete', confirmDeleteId), {
                onSuccess: () => setConfirmDeleteId(null)
            });
        }
    };
    const [showModal, setShowModal] = useState(false);

    const { data, setData, post, processing, reset, errors } = useForm({
        title: '',
        capstone_project_id: '',
        archive: null
    });

    const fileInputRef = useRef(null);

    const submit = (e) => {
        e.preventDefault();
        post(route('repository.script.store'), {
            onSuccess: () => {
                setShowModal(false);
                reset();
            }
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Script Repository" />

            {/* Header Section */}
            <header className="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
                <div>
                    <h2 className="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-surface mb-2">Script Repository</h2>
                    <p className="font-body-lg text-body-lg text-on-surface-variant max-w-2xl">Manage and download your project source code.</p>
                </div>
                <button 
                    onClick={() => setShowModal(true)}
                    className="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-inverse-primary to-primary-container text-white rounded-xl font-label-sm text-label-sm font-semibold shadow-[0_0_20px_rgba(73,75,214,0.4)] hover:shadow-[0_0_30px_rgba(73,75,214,0.6)] transition-all transform hover:-translate-y-0.5 backdrop-blur-md border border-white/10"
                >
                    <span className="material-symbols-outlined text-[20px]">cloud_upload</span>
                    Upload New Script
                </button>
            </header>

            {/* Glassmorphic Table Container */}
            <div className="bg-white/[0.08] backdrop-blur-[24px] border border-white/10 rounded-2xl shadow-2xl overflow-x-auto relative">
                {/* Simulated Top-Down Lighting Edge */}
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
                    <tbody className="font-body-md text-body-md text-on-surface divide-y divide-white/5">
                        {projects.data.map((project, index) => {
                            const iconColors = [
                                'bg-secondary-container/20 border-secondary-container/30 text-secondary-container',
                                'bg-tertiary-container/20 border-tertiary-container/30 text-tertiary-container',
                                'bg-primary/20 border-primary/30 text-primary',
                                'bg-error/20 border-error/30 text-error',
                            ];
                            const icons = ['terminal', 'currency_bitcoin', 'database', 'precision_manufacturing'];
                            const colorClass = iconColors[index % iconColors.length];
                            const iconName = icons[index % icons.length];
                            
                            return (
                                <tr key={project.id} className="hover:bg-white/[0.04] transition-colors duration-200 group">
                                    <td className="px-6 py-5">
                                        <div className="flex items-center gap-3">
                                            <div className={`w-10 h-10 rounded-lg border flex items-center justify-center ${colorClass}`}>
                                                <span className="material-symbols-outlined">{iconName}</span>
                                            </div>
                                            <span className="font-semibold text-inverse-surface group-hover:text-primary transition-colors">{project.title}</span>
                                        </div>
                                    </td>
                                    <td className="px-6 py-5 text-on-surface-variant">{project.academic_year}</td>
                                    <td className="px-6 py-5">
                                        <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-white/5 border border-white/10 text-on-surface-variant font-label-sm text-label-sm">
                                            {project.group_id}
                                        </span>
                                    </td>
                                    <td className="px-6 py-5">
                                        <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full font-label-sm text-label-sm border ${
                                            project.status === 'approved' ? 'bg-primary/10 border-primary/20 text-primary' : 
                                            project.status === 'rejected' ? 'bg-error/10 border-error/20 text-error' : 
                                            'bg-tertiary/10 border-tertiary/20 text-tertiary'
                                        }`}>
                                            <span className="material-symbols-outlined text-[14px]">
                                                {project.status === 'approved' ? 'check_circle' : project.status === 'rejected' ? 'cancel' : 'schedule'}
                                            </span>
                                            <span className="capitalize">{project.status}</span>
                                        </span>
                                    </td>
                                    <td className="px-6 py-5 text-right whitespace-nowrap">
                                        <div className="flex items-center justify-end gap-2">
                                            <a href={route('repository.script.download', project.id)} title="Download Source Code" className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-white/10 text-on-surface-variant hover:text-secondary hover:border-secondary hover:bg-secondary/10 transition-all duration-200 font-label-sm text-label-sm">
                                                <span className="material-symbols-outlined text-[18px]">download</span>
                                            </a>
                                            {(user.role === 'dosen' || user.role === 'kaprodi') && (
                                                <>
                                                    {project.status === 'pending' && (
                                                        <button onClick={() => setConfirmApproveId(project.id)} title="Approve Source Code" className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-white/10 text-on-surface-variant hover:text-primary hover:border-primary hover:bg-primary/10 transition-all duration-200 font-label-sm text-label-sm">
                                                            <span className="material-symbols-outlined text-[18px]">thumb_up</span>
                                                        </button>
                                                    )}
                                                    <button onClick={() => setConfirmDeleteId(project.id)} title="Delete Source Code" className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-white/10 text-on-surface-variant hover:text-error hover:border-error hover:bg-error/10 transition-all duration-200 font-label-sm text-label-sm">
                                                        <span className="material-symbols-outlined text-[18px]">delete</span>
                                                    </button>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                        {projects.data.length === 0 && (
                            <tr>
                                <td colSpan="5" className="px-6 py-8 text-center text-on-surface-variant">
                                    No script projects found.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
                <div className="px-6 py-4 border-t border-white/10">
                    <Pagination links={projects.links} />
                </div>
            </div>

            {/* New Script Modal */}
            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/60 backdrop-blur-md">
                    <div className="glass-panel w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col overflow-hidden relative">
                        <button 
                            onClick={() => setShowModal(false)}
                            aria-label="Close modal" 
                            className="absolute top-6 right-6 text-on-surface-variant hover:text-on-surface transition-colors z-10"
                        >
                            <span className="material-symbols-outlined">close</span>
                        </button>
                        
                        <div className="px-8 pt-8 pb-6 border-b border-white/10">
                            <h2 className="font-headline-md text-headline-md text-on-surface flex items-center gap-3">
                                <span className="material-symbols-outlined text-primary">code</span>
                                Upload New Script
                            </h2>
                            <p className="font-body-md text-body-md text-on-surface-variant mt-2">
                                Upload your project source code (zip or rar) to the script repository.
                            </p>
                        </div>
                        
                        <form onSubmit={submit}>
                            <div className="p-8 flex flex-col gap-6 overflow-y-auto max-h-[60vh] scrollbar-thin">
                                <div className="flex flex-col gap-2">
                                    <label className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider" htmlFor="script-title">Title of Script</label>
                                    <input value={data.title} onChange={e => setData('title', e.target.value)} className="glass-input w-full rounded-lg px-4 py-3 font-body-md text-body-md" id="script-title" placeholder="e.g. Authentication API" required type="text"/>
                                </div>

                                <div className="flex flex-col gap-2">
                                    <label className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider" htmlFor="capstone-id">Connected Capstone Project</label>
                                    <div className="relative">
                                        <select value={data.capstone_project_id} onChange={e => setData('capstone_project_id', e.target.value)} className="glass-input w-full rounded-lg px-4 py-3 font-body-md text-body-md appearance-none cursor-pointer" id="capstone-id" required>
                                            <option className="bg-surface text-on-surface" value="" disabled>Select Capstone</option>
                                            {capstones && capstones.map(cap => (
                                                <option key={cap.id} className="bg-surface text-on-surface" value={cap.id}>{cap.title}</option>
                                            ))}
                                        </select>
                                        <span className="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">expand_more</span>
                                    </div>
                                </div>
                                
                                <div className="flex flex-col gap-2 mt-2">
                                    <label className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Source Code Upload (ZIP/RAR)</label>
                                    <div onClick={() => fileInputRef.current?.click()} className="border-2 border-dashed border-white/30 hover:border-primary transition-colors w-full rounded-xl p-8 flex flex-col items-center justify-center text-center cursor-pointer hover:bg-white/5 relative overflow-hidden">
                                        <input type="file" accept=".zip,.rar" className="hidden" ref={fileInputRef} onChange={e => setData('archive', e.target.files[0])} />
                                        <div className="w-16 h-16 rounded-full bg-surface-container-highest/50 flex items-center justify-center mb-4">
                                            <span className="material-symbols-outlined text-4xl text-secondary">{data.archive ? 'folder' : 'folder_zip'}</span>
                                        </div>
                                        <p className="font-body-md text-body-md text-on-surface mb-1">
                                            {data.archive ? data.archive.name : 'Drag and drop your ZIP here'}
                                        </p>
                                        <p className="font-label-sm text-label-sm text-on-surface-variant mb-4">
                                            {data.archive ? `${(data.archive.size / 1024 / 1024).toFixed(2)} MB` : 'or click to browse from your computer (Max 50MB)'}
                                        </p>
                                        <button className="bg-white/5 border border-white/20 hover:bg-white/10 text-on-surface transition-all rounded-full px-6 py-2 font-label-sm text-label-sm" type="button">
                                            Browse Files
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div className="px-8 py-6 border-t border-white/10 bg-surface-container/30 flex justify-end gap-4 rounded-b-2xl">
                                <button 
                                    onClick={() => setShowModal(false)}
                                    className="bg-white/5 border border-white/20 hover:bg-white/10 text-on-surface transition-all rounded-lg px-6 py-2.5 font-label-sm text-label-sm uppercase tracking-wider" 
                                    type="button"
                                >
                                    Cancel
                                </button>
                                <button disabled={processing} className="bg-gradient-to-r from-primary to-tertiary-container text-on-primary-container shadow-[0_0_15px_rgba(192,193,255,0.4)] hover:shadow-[0_0_25px_rgba(192,193,255,0.6)] rounded-lg px-8 py-2.5 font-label-sm text-label-sm uppercase tracking-wider flex items-center gap-2 disabled:opacity-50" type="submit">
                                    <span className="material-symbols-outlined text-[18px]">cloud_upload</span>
                                    Upload Script
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
                            <h2 className="font-headline-sm text-headline-sm font-bold text-on-surface mb-1">Setujui Source Code?</h2>
                            <p className="font-body-md text-body-md text-on-surface-variant">
                                Apakah Anda yakin ingin menyetujui Source Code ini?
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
                            <h2 className="font-headline-sm text-headline-sm font-bold text-on-surface mb-1">Hapus Source Code?</h2>
                            <p className="font-body-md text-body-md text-on-surface-variant">
                                Apakah Anda yakin ingin menghapus Source Code ini secara permanen? Tindakan ini tidak dapat dibatalkan.
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
