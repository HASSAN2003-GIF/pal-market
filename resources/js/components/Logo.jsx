/**
 * PAL Market wordmark: condensed signage letters with a safety-yellow block,
 * echoing the favicon. Rendered as text so it scales and stays crisp.
 */
export default function Logo({ inverted = false, className = '' }) {
    return (
        <span className={`inline-flex items-center gap-2 ${className}`}>
            <span
                aria-hidden="true"
                className={`relative grid h-8 w-8 place-items-center rounded-[6px] font-display text-[1.25rem] font-bold leading-none ${
                    inverted ? 'bg-white text-ink' : 'bg-ink text-white'
                }`}
            >
                P
                <span className="absolute right-[5px] bottom-[5px] h-[6px] w-[6px] bg-safety" />
            </span>
            <span className={`font-display text-[1.45rem] font-bold leading-none tracking-[0.02em] ${inverted ? 'text-white' : 'text-ink'}`}>
                PAL Market
            </span>
        </span>
    );
}
