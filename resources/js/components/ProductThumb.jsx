import { useState } from 'react';
import { useT } from '../i18n';
import { cx } from './ui';

const SIZES = {
    xs: 'h-9 w-9',
    sm: 'h-12 w-12',
    md: 'h-16 w-16 sm:h-20 sm:w-20',
    lg: 'h-24 w-24',
};

/**
 * A product photo, or a neutral placeholder when there is none (or it fails
 * to load). Never a stock photo: what buyers see is what was uploaded.
 */
export default function ProductThumb({ src, alt = '', size = 'md', className }) {
    const t = useT();
    const [failed, setFailed] = useState(false);
    const box = cx('shrink-0 overflow-hidden rounded-[var(--radius-control)] border border-rule bg-concrete', SIZES[size] ?? size, className);

    if (!src || failed) {
        return (
            <span className={cx(box, 'inline-flex items-center justify-center text-faint')} role="img" aria-label={t('photos.noPhoto')}>
                <svg viewBox="0 0 24 24" className="h-1/2 w-1/2" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true">
                    <rect x="3.5" y="5" width="17" height="14" rx="2" />
                    <circle cx="9" cy="10" r="1.6" />
                    <path d="m4 17 5-4.5 3.5 3 3-2.5L20 17" strokeLinejoin="round" />
                </svg>
            </span>
        );
    }

    return (
        <span className={cx(box, 'inline-block')}>
            <img src={src} alt={alt} loading="lazy" decoding="async" className="h-full w-full object-cover" onError={() => setFailed(true)} />
        </span>
    );
}
