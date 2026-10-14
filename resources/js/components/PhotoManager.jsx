import { useEffect, useId, useRef, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { useT } from '../i18n';
import { Button, ConfirmDialog, cx, useToast } from './ui';

// Mirrors config/media.php; the server enforces the real limits.
const MAX_MB = 5;
const MIN_PX = 200;
const MAX_PHOTOS = 8;
const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Add, preview, order and remove photos for a listing or catalog product.
 * `upload(files)`, `remove(image)` and `makeMain(image)` return promises;
 * `onChanged` refreshes the owner's data afterwards.
 */
export default function PhotoManager({ images = [], upload, remove, makeMain, onChanged, catalog = false, name }) {
    const t = useT();
    const toast = useToast();
    const inputId = useId();
    const inputRef = useRef(null);
    const [pending, setPending] = useState([]); // [{ file, url }]
    const [problems, setProblems] = useState([]);
    const [removing, setRemoving] = useState(null);

    // Preview URLs are freed when cleared and when the component goes away.
    const latest = useRef(pending);
    latest.current = pending;
    useEffect(() => () => latest.current.forEach((p) => URL.revokeObjectURL(p.url)), []);
    const clearPending = () => {
        pending.forEach((p) => URL.revokeObjectURL(p.url));
        setPending([]);
    };

    const send = useMutation({
        mutationFn: () => upload(pending.map((p) => p.file)),
        onSuccess: () => {
            clearPending();
            onChanged?.();
            toast(t('photos.addedToast'));
        },
    });
    const drop = useMutation({
        mutationFn: (image) => remove(image),
        onSuccess: () => {
            setRemoving(null);
            onChanged?.();
            toast(t('photos.removedToast'));
        },
    });
    const main = useMutation({
        mutationFn: (image) => makeMain(image),
        onSuccess: () => {
            onChanged?.();
            toast(t('photos.mainToast'));
        },
    });

    function choose(fileList) {
        const room = MAX_PHOTOS - images.length - pending.length;
        const found = [];
        const accepted = [];
        Array.from(fileList).forEach((file) => {
            if (!TYPES.includes(file.type)) found.push(t('photos.wrongType', { name: file.name }));
            else if (file.size > MAX_MB * 1024 * 1024) found.push(t('photos.tooBig', { name: file.name, size: MAX_MB }));
            else accepted.push(file);
        });
        if (accepted.length > room) {
            found.push(t('photos.tooMany', { max: MAX_PHOTOS }));
            accepted.splice(Math.max(room, 0));
        }
        setProblems(found);
        setPending((current) => [...current, ...accepted.map((file) => ({ file, url: URL.createObjectURL(file) }))]);
        send.reset();
    }

    const serverError = send.error ? (Object.values(send.error.fieldErrors)[0] ?? send.error.message) : null;
    const full = images.length + pending.length >= MAX_PHOTOS;

    return (
        <div>
            <p className="text-sm text-muted">
                {t(catalog ? 'photos.helpCatalog' : 'photos.help', { size: MAX_MB, min: MIN_PX, max: MAX_PHOTOS })}
            </p>

            {images.length === 0 && pending.length === 0 && <p className="mt-2 text-sm text-ink-soft">{t('photos.none')}</p>}

            {images.length > 0 && (
                <ul className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {images.map((image) => (
                        <li key={image.id} className="overflow-hidden rounded-[var(--radius-control)] border border-rule bg-paper">
                            <div className="relative aspect-square bg-concrete">
                                <img src={image.urls.thumb} alt={image.alt_text || t('photos.photoOf', { name })} className="h-full w-full object-cover" loading="lazy" />
                                {image.is_primary && (
                                    <span className="absolute top-1.5 left-1.5 rounded-full bg-inverse px-2 py-0.5 text-xs font-bold text-on-inverse">{t('photos.main')}</span>
                                )}
                            </div>
                            <div className="flex flex-wrap gap-1 p-1.5">
                                {!image.is_primary && (
                                    <Button size="sm" variant="ghost" className="flex-1" loading={main.isPending && main.variables?.id === image.id} onClick={() => main.mutate(image)}>
                                        {t('photos.makeMain')}
                                    </Button>
                                )}
                                <Button size="sm" variant="ghost" className="flex-1 text-danger" onClick={() => setRemoving(image)}>
                                    {t('photos.remove')}
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {pending.length > 0 && (
                <div className="mt-4 rounded-[var(--radius-control)] border border-dashed border-pal-300 bg-pal-50 p-3">
                    <p className="text-sm font-semibold">{t('photos.selected')}</p>
                    <ul className="mt-2 flex flex-wrap gap-2">
                        {pending.map((p, index) => (
                            <li key={p.url} className="h-20 w-20 overflow-hidden rounded-[var(--radius-control)] border border-rule bg-paper">
                                <img src={p.url} alt={t('photos.previewAlt', { n: index + 1 })} className="h-full w-full object-cover" />
                            </li>
                        ))}
                    </ul>
                    <div className="mt-3 flex flex-wrap gap-2">
                        <Button size="sm" loading={send.isPending} loadingText={t('photos.uploading')} onClick={() => send.mutate()}>
                            {t('photos.upload', { count: pending.length })}
                        </Button>
                        <Button size="sm" variant="secondary" disabled={send.isPending} onClick={clearPending}>
                            {t('photos.discard')}
                        </Button>
                    </div>
                </div>
            )}

            {(problems.length > 0 || serverError) && (
                <ul className="mt-2 space-y-0.5 text-sm text-danger" role="alert">
                    {problems.map((p) => (
                        <li key={p}>{p}</li>
                    ))}
                    {serverError && <li>{serverError}</li>}
                </ul>
            )}
            {(main.error || drop.error) && !removing && <p className="mt-2 text-sm text-danger">{(main.error ?? drop.error).message}</p>}

            {!full && (
                <div className="mt-3">
                    <input
                        ref={inputRef}
                        id={inputId}
                        type="file"
                        accept={TYPES.join(',')}
                        multiple
                        className="sr-only"
                        tabIndex={-1}
                        onChange={(event) => {
                            choose(event.target.files);
                            event.target.value = '';
                        }}
                    />
                    <label
                        htmlFor={inputId}
                        className={cx(
                            'tap inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-[var(--radius-control)] border border-rule bg-paper px-3.5 font-semibold',
                            'hover:border-ink focus-within:outline-2',
                        )}
                        tabIndex={0}
                        role="button"
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' || event.key === ' ') {
                                event.preventDefault();
                                inputRef.current?.click();
                            }
                        }}
                    >
                        <span aria-hidden="true">＋</span>
                        {t('photos.add')}
                    </label>
                    <p className="mt-1 text-xs text-muted">{t('photos.pickHint')}</p>
                </div>
            )}

            <ConfirmDialog
                open={Boolean(removing)}
                title={t('photos.removeTitle')}
                confirmLabel={t('photos.remove')}
                confirmVariant="danger"
                busy={drop.isPending}
                error={drop.error?.message}
                onCancel={() => setRemoving(null)}
                onConfirm={() => drop.mutate(removing)}
            >
                <p>{t('photos.removeBody')}</p>
            </ConfirmDialog>
        </div>
    );
}
