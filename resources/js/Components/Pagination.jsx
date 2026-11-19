import { Link } from '@inertiajs/react';

export default function Pagination({ links }) {
    if (!links || links.length <= 3) return null; // Don't show if there's only 1 page (Prev, 1, Next)

    // Extract actual page numbers (filter out Laravel's '...')
    const pageLinks = links.slice(1, -1).filter(link => link.label !== '...');
    const activeIndex = pageLinks.findIndex(p => p.active);
    
    let start = Math.max(0, activeIndex - 1);
    let end = Math.min(pageLinks.length - 1, activeIndex + 1);
    
    if (activeIndex === 0) {
        end = Math.min(pageLinks.length - 1, 2);
    } else if (activeIndex === pageLinks.length - 1) {
        start = Math.max(0, pageLinks.length - 3);
    }
    
    const visiblePages = [];
    if (start > 0) {
        visiblePages.push({ label: '...', url: null, active: false, isDots: true });
    }
    for (let i = start; i <= end; i++) {
        visiblePages.push(pageLinks[i]);
    }
    if (end < pageLinks.length - 1) {
        visiblePages.push({ label: '...', url: null, active: false, isDots: true });
    }
    
    const finalLinks = [
        links[0], // Prev
        ...visiblePages,
        links[links.length - 1] // Next
    ];

    return (
        <div className="flex items-center justify-between border-t border-white/10 px-6 py-4 bg-surface-container-low/50">
            <div className="flex flex-1 justify-between sm:hidden">
                {links[0].url ? (
                    <Link
                        href={links[0].url}
                        className="relative inline-flex items-center rounded-md border border-white/10 px-4 py-2 text-sm font-medium text-on-surface hover:bg-white/5"
                    >
                        Previous
                    </Link>
                ) : (
                    <span className="relative inline-flex items-center rounded-md border border-white/10 px-4 py-2 text-sm font-medium text-on-surface-variant opacity-50 cursor-not-allowed">
                        Previous
                    </span>
                )}
                {links[links.length - 1].url ? (
                    <Link
                        href={links[links.length - 1].url}
                        className="relative ml-3 inline-flex items-center rounded-md border border-white/10 px-4 py-2 text-sm font-medium text-on-surface hover:bg-white/5"
                    >
                        Next
                    </Link>
                ) : (
                    <span className="relative ml-3 inline-flex items-center rounded-md border border-white/10 px-4 py-2 text-sm font-medium text-on-surface-variant opacity-50 cursor-not-allowed">
                        Next
                    </span>
                )}
            </div>
            <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm gap-1" aria-label="Pagination">
                        {finalLinks.map((link, index) => {
                            const isFirst = index === 0;
                            const isLast = index === finalLinks.length - 1;
                            const isActive = link.active;
                            const hasUrl = !!link.url;
                            
                            let label = link.label;
                            if (isFirst) label = <span className="material-symbols-outlined text-[18px]">chevron_left</span>;
                            if (isLast) label = <span className="material-symbols-outlined text-[18px]">chevron_right</span>;

                            const contentProps = isFirst || isLast 
                                ? { children: label } 
                                : { dangerouslySetInnerHTML: { __html: label } };

                            return hasUrl ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={`relative inline-flex items-center rounded-md px-3 py-2 text-sm font-semibold transition-colors focus:z-20
                                        ${isActive 
                                            ? 'z-10 bg-primary/20 text-primary border border-primary/30 font-bold shadow-[0_0_10px_rgba(192,193,255,0.2)]' 
                                            : 'text-on-surface-variant border border-transparent hover:bg-white/10 hover:text-white'
                                        }
                                    `}
                                    {...contentProps}
                                />
                            ) : (
                                <span
                                    key={index}
                                    className={`relative inline-flex items-center rounded-md px-3 py-2 text-sm font-semibold text-on-surface-variant border border-transparent ${link.isDots ? '' : 'opacity-50 cursor-not-allowed'}`}
                                    {...contentProps}
                                />
                            );
                        })}
                    </nav>
                </div>
            </div>
        </div>
    );
}
