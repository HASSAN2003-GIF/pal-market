import { useState } from 'react';
import { useT } from '../i18n';
import ProductThumb from './ProductThumb';
import { cx } from './ui';

/**
 * Main photo with a row of thumbnails. Supplier photos say whose they are,
 * so buyers know the picture shows that seller's stock.
 */
export default function ProductGallery({ images = [], name }) {
    const t = useT();
    const [index, setIndex] = useState(0);
    const current = images[Math.min(index, images.length - 1)];

    if (!current) {
        // No photo: a small placeholder on larger screens, nothing on phones.
        return (
            <div className="hidden sm:block">
                <ProductThumb size="aspect-square w-full" className="max-w-xs" />
            </div>
        );
    }

    return (
        <figure className="w-full max-w-[15rem] sm:max-w-sm" aria-label={t('photos.gallery')}>
            <div className="aspect-square overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-concrete">
                <img src={current.large} alt={current.alt || t('photos.photoOf', { name })} className="h-full w-full object-contain" decoding="async" />
            </div>
            {current.supplier && <figcaption className="mt-1.5 text-xs text-muted">{t('photos.photoFrom', { name: current.supplier })}</figcaption>}
            {images.length > 1 && (
                <ul className="mt-2 flex gap-2 overflow-x-auto pb-1">
                    {images.map((image, i) => (
                        <li key={image.thumb} className="shrink-0">
                            <button
                                type="button"
                                aria-label={t('photos.showPhoto', { n: i + 1, total: images.length })}
                                aria-pressed={i === index}
                                onClick={() => setIndex(i)}
                                className={cx(
                                    'block h-14 w-14 overflow-hidden rounded-[var(--radius-control)] border-2',
                                    i === index ? 'border-action' : 'border-rule hover:border-ink',
                                )}
                            >
                                <img src={image.thumb} alt="" className="h-full w-full object-cover" loading="lazy" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </figure>
    );
}
