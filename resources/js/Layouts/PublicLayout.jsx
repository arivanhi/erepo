import { Link, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Toaster, toast } from 'react-hot-toast';

export default function PublicLayout({ header, children }) {
    const { auth, flash, url } = usePage().props;
    const pageUrl = usePage().url;
    const user = auth?.user;
    const [showingMobileMenu, setShowingMobileMenu] = useState(false);

    useEffect(() => {
        if (flash?.message) {
            toast.success(flash.message, {
                duration: 5000,
                style: {
                    background: '#1a1b1e',
                    color: '#fff',
                    border: '1px solid rgba(255,255,255,0.1)',
                },
            });
        }
        if (flash?.error) {
            toast.error(flash.error, {
                duration: 5500,
                style: {
                    background: '#1a1b1e',
                    color: '#fff',
                    border: '1px solid rgba(255,84,73,0.4)',
                },
            });
        }
    }, [flash]);

    const menuItems = [
        { name: 'Dashboard Repositori', icon: 'space_dashboard', routeName: 'public.dashboard', href: route('public.dashboard') },
        { name: 'Repository PDF', icon: 'picture_as_pdf', routeName: 'public.capstone', href: route('public.capstone') },
        { name: 'Repository Script', icon: 'folder_zip', routeName: 'public.script', href: route('public.script') },
    ];

    const currentRoute = route().current();

    return (
        <div className="bg-background text-on-surface font-body-md bg-radial-gradient h-screen w-full flex flex-col md:flex-row overflow-hidden">
            <Toaster position="top-right" />

            {/* Mobile Top Header */}
            <nav className="md:hidden w-full h-20 flex-shrink-0 bg-surface/40 backdrop-blur-md shadow-sm flex justify-between items-center px-container-padding-mobile sticky top-0 z-40 border-b border-white/5">
                <Link href={route('public.dashboard')} className="font-headline-md text-headline-md font-bold text-primary flex items-center gap-2">
                    <img src="/images/logo/logo.png" alt="Logo" className="w-8 h-8 rounded-full" onError={(e) => { e.target.style.display = 'none'; }} />
                    EREPO Elektro
                </Link>
                <button
                    type="button"
                    onClick={() => setShowingMobileMenu(!showingMobileMenu)}
                    className="text-primary p-2"
                    aria-label="Menu"
                >
                    <span className="material-symbols-outlined">menu</span>
                </button>
            </nav>

            {/* Mobile Drawer */}
            {showingMobileMenu && (
                <div className="md:hidden fixed inset-0 z-50 bg-background/95 backdrop-blur-md flex flex-col">
                    <div className="flex justify-end p-6">
                        <span className="material-symbols-outlined text-primary cursor-pointer text-3xl" onClick={() => setShowingMobileMenu(false)}>
                            close
                        </span>
                    </div>
                    <div className="px-6 pb-6">
                        <div className="font-headline-md text-headline-md font-bold text-primary mb-1">EREPO Elektro</div>
                        <div className="text-on-surface-variant font-label-sm text-label-sm">Digital Repository S1 Teknik Elektro</div>
                    </div>
                    <ul className="flex-1 px-6 space-y-2">
                        {menuItems.map((item) => {
                            const isActive = currentRoute === item.routeName;
                            return (
                                <li key={item.routeName}>
                                    <Link
                                        href={item.href}
                                        onClick={() => setShowingMobileMenu(false)}
                                        className={`flex items-center gap-3 p-3 rounded transition-colors duration-200 ${
                                            isActive
                                                ? 'text-primary font-bold border-r-4 border-primary bg-white/5'
                                                : 'text-on-surface-variant hover:text-on-surface hover:bg-white/10'
                                        }`}
                                    >
                                        <span className="material-symbols-outlined" style={isActive ? { fontVariationSettings: "'FILL' 1" } : {}}>
                                            {item.icon}
                                        </span>
                                        <span>{item.name}</span>
                                    </Link>
                                </li>
                            );
                        })}
                        {user && (
                            <li className="pt-4 mt-4 border-t border-white/10">
                                <Link
                                    href={route('dashboard')}
                                    className="flex items-center gap-3 p-3 rounded text-secondary hover:bg-white/10"
                                >
                                    <span className="material-symbols-outlined">upload_file</span>
                                    <span>Dashboard Unggah Internal</span>
                                </Link>
                            </li>
                        )}
                    </ul>
                </div>
            )}

            {/* Desktop Sidebar */}
            <nav className="hidden md:flex bg-surface-container/60 backdrop-blur-xl h-full w-72 flex-col flex-shrink-0 border-r border-white/10 shadow-xl justify-between py-6 z-40">
                <div className="px-6 mb-8 flex flex-col items-start">
                    <Link href={route('public.dashboard')} className="flex flex-row items-center gap-3.5 w-full">
                        <div className="w-12 h-12 rounded-full overflow-hidden border-2 border-primary/50 bg-white/5 flex items-center justify-center shrink-0">
                            <img
                                src="/images/logo/logo.png"
                                alt="UDINUS"
                                className="w-full h-full object-cover"
                                onError={(e) => {
                                    e.target.style.display = 'none';
                                    e.target.nextSibling.style.display = 'block';
                                }}
                            />
                            <span className="material-symbols-outlined text-primary hidden" style={{ display: 'none' }}>
                                school
                            </span>
                        </div>
                        <div>
                            <div className="font-headline-md text-headline-md font-bold text-primary">EREPO Elektro</div>
                            <div className="text-on-surface-variant font-label-sm text-xs mt-0.5">Katalog Publik Repositori</div>
                        </div>
                    </Link>
                </div>

                <ul className="flex-1 px-4 space-y-2">
                    {menuItems.map((item) => {
                        const isActive = currentRoute === item.routeName;
                        return (
                            <li key={item.routeName}>
                                <Link
                                    href={item.href}
                                    className={`flex items-center gap-3 p-3 rounded transition-all duration-200 ${
                                        isActive
                                            ? 'text-primary font-bold border-r-4 border-primary bg-white/5 active:scale-95'
                                            : 'text-on-surface-variant hover:text-on-surface hover:bg-white/10'
                                    }`}
                                >
                                    <span className="material-symbols-outlined" style={isActive ? { fontVariationSettings: "'FILL' 1" } : {}}>
                                        {item.icon}
                                    </span>
                                    <span className="font-body-md text-body-md">{item.name}</span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>

                <div className="px-5 mt-auto pt-4 border-t border-white/10 space-y-3">
                    {user && (
                        <Link
                            href={route('dashboard')}
                            className="w-full px-4 py-2.5 rounded-lg bg-primary/15 border border-primary/30 text-primary hover:bg-primary/25 transition-colors text-xs font-semibold flex items-center justify-center gap-2"
                        >
                            <span className="material-symbols-outlined text-[16px]">dashboard</span>
                            Ke Dashboard Internal
                        </Link>
                    )}
                    <div className="text-[11px] text-on-surface-variant leading-relaxed">
                        S1 Teknik Elektro — Universitas Dian Nuswantoro
                    </div>
                </div>
            </nav>

            {/* Main Content */}
            <main className="flex-1 flex flex-col overflow-y-auto px-container-padding-mobile md:px-container-padding-desktop py-8 md:py-10 gap-panel-gap relative">
                {header && <header className="mb-2 relative z-10">{header}</header>}
                <div key={pageUrl || url} className="animate-page-enter flex-1 flex flex-col gap-panel-gap w-full">
                    {children}
                </div>
            </main>
        </div>
    );
}
