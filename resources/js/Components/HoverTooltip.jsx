import { useState, useRef } from 'react';
import { createPortal } from 'react-dom';

export default function HoverTooltip({ text, children, className = 'inline-flex' }) {
    const [coords, setCoords] = useState(null);
    const triggerRef = useRef(null);

    const show = () => {
        if (!triggerRef.current || !text) return;
        const rect = triggerRef.current.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const clampedX = Math.max(90, Math.min(window.innerWidth - 90, centerX));
        const showBelow = rect.top < 64;

        setCoords({
            x: clampedX,
            y: showBelow ? rect.bottom + 8 : rect.top - 8,
            showBelow,
        });
    };

    const hide = () => setCoords(null);

    return (
        <span
            ref={triggerRef}
            onMouseEnter={show}
            onMouseLeave={hide}
            onFocus={show}
            onBlur={hide}
            className={className}
        >
            {children}
            {coords &&
                typeof document !== 'undefined' &&
                createPortal(
                    <div
                        style={{
                            left: `${coords.x}px`,
                            top: `${coords.y}px`,
                            transform: coords.showBelow ? 'translate(-50%, 0)' : 'translate(-50%, -100%)',
                        }}
                        className="fixed z-[200] pointer-events-none px-3 py-1.5 rounded-lg bg-[#15171e]/95 border border-white/20 text-[11px] font-medium text-white shadow-[0_8px_24px_rgba(0,0,0,0.55)] whitespace-nowrap backdrop-blur-md"
                    >
                        {text}
                    </div>,
                    document.body
                )}
        </span>
    );
}
