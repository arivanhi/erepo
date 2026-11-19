import { Link, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Toaster, toast } from 'react-hot-toast';

export default function AuthenticatedLayout({ header, children }) {
    const { auth, flash } = usePage().props;
    const { url } = usePage();
    const user = auth.user;
    const [showingMobileMenu, setShowingMobileMenu] = useState(false);

    const formatRoleLabel = (role) => {
        if (role === 'koordinator_ta') return 'Koordinator TA';
        return role;
    };

    useEffect(() => {
        if (flash?.message) {
            toast.success(flash.message, {
                style: {
                    background: '#1a1b1e',
                    color: '#fff',
                    border: '1px solid rgba(255,255,255,0.1)',
                },
                iconTheme: {
                    primary: '#4ade80',
                    secondary: '#fff',
                },
            });
        }
        if (flash?.error) {
            toast.error(flash.error, {
                duration: 5000,
                style: {
                    background: '#1a1b1e',
                    color: '#fff',
                    border: '1px solid rgba(255,84,73,0.4)',
                },
            });
        }
    }, [flash]);

    // Dynamic menu items based on role (Assign Account is strictly Admin-only)
    const menuItems = [
        { name: 'Dashboard Utama', icon: 'dashboard', route: 'dashboard' },
    ];

    if (user.role === 'admin') {
        menuItems.push({ name: 'Assign Account', icon: 'person_add', route: 'assign-account' });
    }

    menuItems.push(
        { name: 'Repository Capstone Design', icon: 'school', route: 'repository.capstone' },
        { name: 'Repository Script', icon: 'description', route: 'repository.script' }
    );

    const currentRoute = route().current();

    return (
        <div className="bg-background text-on-surface font-body-md bg-radial-gradient h-screen w-full flex flex-col md:flex-row overflow-hidden">
            <Toaster position="top-right" />
            {/* TopNavBar for Mobile */}
            <nav className="md:hidden w-full h-20 flex-shrink-0 bg-surface/40 backdrop-blur-md shadow-sm flex justify-between items-center px-container-padding-mobile sticky top-0 z-40 border-b border-white/5">
                <div className="font-headline-md text-headline-md font-bold text-primary flex items-center gap-2">
                    <img src="/images/logo/logo.png" alt="Logo" className="w-8 h-8 rounded-full" onError={(e) => e.target.style.display='none'} />
                    ThesisCloud
                </div>
                <div className="flex gap-4">
                    <span className="material-symbols-outlined text-primary hover:text-primary transition-all duration-200 cursor-pointer">notifications</span>
                    <span className="material-symbols-outlined text-primary hover:text-primary transition-all duration-200 cursor-pointer" onClick={() => setShowingMobileMenu(!showingMobileMenu)}>menu</span>
                </div>
            </nav>

            {/* Mobile Menu Overlay */}
            {showingMobileMenu && (
                <div className="md:hidden fixed inset-0 z-50 bg-background/95 backdrop-blur-md flex flex-col">
                    <div className="flex justify-end p-6">
                        <span className="material-symbols-outlined text-primary cursor-pointer text-3xl" onClick={() => setShowingMobileMenu(false)}>close</span>
                    </div>
                    <div className="px-6 pb-6">
                        <div className="font-headline-md text-headline-md font-bold text-primary mb-1">ThesisCloud</div>
                        <div className="text-on-surface-variant font-label-sm text-label-sm capitalize">{formatRoleLabel(user.role)} Account</div>
                    </div>
                    <ul className="flex-1 px-6 space-y-2">
                        {menuItems.map((item, idx) => {
                            const isActive = currentRoute === item.route;
                            return (
                                <li key={idx}>
                                    <Link href={route(item.route)} onClick={() => setShowingMobileMenu(false)} className={`flex items-center gap-3 p-3 rounded transition-colors duration-200 ${isActive ? 'text-primary font-bold border-r-4 border-primary bg-white/5' : 'text-on-surface-variant hover:text-on-surface hover:bg-white/10'}`}>
                                        <span className="material-symbols-outlined" style={isActive ? { fontVariationSettings: "'FILL' 1" } : {}}>{item.icon}</span>
                                        <span className="font-body-md text-body-md">{item.name}</span>
                                    </Link>
                                </li>
                            );
                        })}
                        <li className="mt-4 pt-4 border-t border-white/10">
                            <Link href={route('settings')} onClick={() => setShowingMobileMenu(false)} className={`flex items-center gap-3 p-3 rounded transition-colors duration-200 ${currentRoute === 'settings' ? 'text-primary font-bold border-r-4 border-primary bg-white/5' : 'text-on-surface-variant hover:text-on-surface hover:bg-white/10'}`}>
                                <span className="material-symbols-outlined" style={currentRoute === 'settings' ? { fontVariationSettings: "'FILL' 1" } : {}}>settings</span>
                                <span className="font-body-md text-body-md">Settings</span>
                            </Link>
                        </li>
                        <li>
                            <Link href={route('logout')} method="post" as="button" className="w-full flex items-center gap-3 p-3 rounded text-on-surface-variant hover:text-on-surface hover:bg-white/10 transition-colors duration-200">
                                <span className="material-symbols-outlined">logout</span>
                                <span className="font-body-md text-body-md">Logout</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            )}

            {/* SideNavBar for Desktop */}
            <nav className="hidden md:flex bg-surface-container/60 backdrop-blur-xl h-full w-72 flex-col flex-shrink-0 border-r border-white/10 shadow-xl justify-between py-6 z-40">
                <div className="px-6 mb-8 flex flex-col items-start">
                    <div className="flex flex-row items-center gap-4 w-full">
                        <div className="w-12 h-12 rounded-full overflow-hidden border-2 border-primary/50 bg-white/5 flex items-center justify-center shrink-0">
                            <img src="/images/logo/logo.png" alt="Profile" className="w-full h-full object-cover" onError={(e) => { e.target.style.display='none'; e.target.nextSibling.style.display='block'; }} />
                            <span className="material-symbols-outlined text-primary hidden" style={{ display: 'none' }}>person</span>
                        </div>
                        <div>
                            <div className="font-headline-md text-headline-md font-bold text-primary">
                                ThesisCloud {user.role === 'admin' ? 'Admin' : user.role === 'koordinator_ta' ? 'Koordinator' : ''}
                            </div>
                            <div className="text-on-surface-variant font-label-sm text-label-sm mt-1 capitalize">{formatRoleLabel(user.role)} Account</div>
                        </div>
                    </div>
                </div>
                <ul className="flex-1 px-4 space-y-2">
                    {menuItems.map((item, idx) => {
                        const isActive = currentRoute === item.route;
                        return (
                            <li key={idx}>
                                <Link href={route(item.route)} className={`flex items-center gap-3 p-3 rounded transition-all duration-200 ${isActive ? 'text-primary font-bold border-r-4 border-primary bg-white/5 active:scale-95' : 'text-on-surface-variant hover:text-on-surface hover:bg-white/10'}`}>
                                    <span className="material-symbols-outlined" style={isActive ? { fontVariationSettings: "'FILL' 1" } : {}}>{item.icon}</span>
                                    <span className="font-body-md text-body-md">{item.name}</span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
                <ul className="px-4 mt-auto space-y-2 border-t border-white/10 pt-4">
                    <li>
                        <Link href={route('settings')} className={`flex items-center gap-3 p-3 rounded transition-colors duration-200 cursor-pointer ${currentRoute === 'settings' ? 'text-primary font-bold border-r-4 border-primary bg-white/5' : 'text-on-surface-variant hover:text-on-surface hover:bg-white/10'}`}>
                            <span className="material-symbols-outlined" style={currentRoute === 'settings' ? { fontVariationSettings: "'FILL' 1" } : {}}>settings</span>
                            <span className="font-body-md text-body-md">Settings</span>
                        </Link>
                    </li>
                    <li>
                        <Link href={route('logout')} method="post" as="button" className="w-full flex items-center gap-3 p-3 rounded text-on-surface-variant hover:text-on-surface hover:bg-white/10 transition-colors duration-200 cursor-pointer">
                            <span className="material-symbols-outlined">logout</span>
                            <span className="font-body-md text-body-md">Logout</span>
                        </Link>
                    </li>
                </ul>
            </nav>

            {/* Main Content Canvas */}
            <main className="flex-1 flex flex-col overflow-y-auto px-container-padding-mobile md:px-container-padding-desktop py-8 md:py-12 gap-panel-gap relative">
                {header && (
                    <header className="mb-4 relative z-10">
                        {header}
                    </header>
                )}
                <div key={url} className="animate-page-enter flex-1 flex flex-col gap-panel-gap w-full h-full">
                    {children}
                </div>
            </main>
        </div>
    );
}
