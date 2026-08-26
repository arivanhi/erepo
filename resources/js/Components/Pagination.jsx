import { Link } from '@inertiajs/react';

export default function Pagination({ links }) {
    if (!links || links.length <= 3) return null; // Don't show if there's only 1 page (Prev, 1, Next)

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
                        {links.map((link, index) => {
                            const isFirst = index === 0;
                            const isLast = index === links.length - 1;
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
                                    className="relative inline-flex items-center rounded-md px-3 py-2 text-sm font-semibold text-on-surface-variant opacity-50 cursor-not-allowed border border-transparent"
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
