import { useEffect, useId, useRef, useState } from 'react';
import { useI18n } from '../i18n';
import { useTheme } from '../theme/ThemeContext';
import { useArea } from '../location/LocationContext';
import { detectRegion, REGIONS, regionLabel } from '../location/regions';
import { Button, cx } from './ui';
import { CheckIcon, CloseIcon, DeviceIcon, LocateIcon, MoonIcon, PinIcon, SearchIcon, SunIcon } from './icons';

/* ----------------------------------------------------------------- Language */

/** Two-button EN / SW switch. Interface text only; what people typed is never translated. */
export function LanguageSwitch({ className }) {
    const { locale, setLocale, t } = useI18n();
    return (
        <div role="group" aria-label={t('layout.language')} className={cx('inline-flex rounded-[var(--radius-control)] border border-rule bg-paper p-0.5', className)}>
            {[
                ['en', 'EN', 'English'],
                ['sw', 'SW', 'Kiswahili'],
            ].map(([code, short, name]) => (
                <button
                    key={code}
                    type="button"
                    lang={code}
                    aria-pressed={locale === code}
                    title={name}
                    onClick={() => setLocale(code)}
                    className={cx(
                        'h-10 min-w-10 rounded-[6px] px-2 text-sm font-bold lg:h-8',
                        locale === code ? 'bg-inverse text-on-inverse' : 'text-ink-soft hover:text-ink',
                    )}
                >
                    <span aria-hidden="true">{short}</span>
                    <span className="sr-only">{name}</span>
                </button>
            ))}
        </div>
    );
}

/* -------------------------------------------------------------------- Theme */

function usePopover() {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    useEffect(() => {
        if (!open) return undefined;
        const onPointer = (event) => ref.current && !ref.current.contains(event.target) && setOpen(false);
        const onKey = (event) => event.key === 'Escape' && setOpen(false);
        document.addEventListener('pointerdown', onPointer);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('pointerdown', onPointer);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);
    return { open, setOpen, ref };
}

const THEME_OPTIONS = [
    ['light', SunIcon, 'layout.themeLight'],
    ['dark', MoonIcon, 'layout.themeDark'],
    ['system', DeviceIcon, 'layout.themeSystem'],
];

export function ThemeMenu({ className }) {
    const { t } = useI18n();
    const { choice, resolved, setChoice } = useTheme();
    const { open, setOpen, ref } = usePopover();
    const menuId = useId();
    const CurrentIcon = choice === 'system' ? DeviceIcon : resolved === 'dark' ? MoonIcon : SunIcon;

    return (
        <div ref={ref} className={cx('relative', className)}>
            <button
                type="button"
                aria-haspopup="true"
                aria-expanded={open}
                aria-controls={menuId}
                onClick={() => setOpen((value) => !value)}
                className="inline-flex h-10 w-10 items-center justify-center rounded-[var(--radius-control)] border border-rule bg-paper text-ink-soft hover:text-ink lg:h-9 lg:w-9"
            >
                <CurrentIcon />
                <span className="sr-only">{t('layout.theme')}</span>
            </button>
            {open && (
                <div
                    id={menuId}
                    role="radiogroup"
                    aria-label={t('layout.theme')}
                    className="absolute right-0 z-40 mt-2 w-56 rounded-[var(--radius-panel)] border border-rule bg-paper p-1.5 shadow-xl"
                >
                    {THEME_OPTIONS.map(([value, OptionIcon, label]) => (
                        <button
                            key={value}
                            type="button"
                            role="radio"
                            aria-checked={choice === value}
                            onClick={() => {
                                setChoice(value);
                                setOpen(false);
                            }}
                            className={cx(
                                'flex min-h-11 w-full items-center gap-3 rounded-[6px] px-3 py-2.5 text-left font-semibold',
                                choice === value ? 'bg-pal-50 text-pal-700' : 'text-ink hover:bg-concrete',
                            )}
                        >
                            <OptionIcon className="h-5 w-5 shrink-0" />
                            <span className="flex-1">{t(label)}</span>
                            {choice === value && <CheckIcon className="h-4 w-4" />}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

/* --------------------------------------------------------------------- Area */

/** Header chip showing the person's area; opens the area dialog. */
export function AreaButton({ className, compact = false }) {
    const { t, locale } = useI18n();
    const { region } = useArea();
    const [open, setOpen] = useState(false);

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className={cx(
                    'group inline-flex min-h-10 min-w-0 items-center gap-2 rounded-[var(--radius-control)] text-left hover:bg-concrete',
                    compact ? 'px-1 py-1' : 'px-2 py-1',
                    className,
                )}
            >
                <PinIcon className="h-5 w-5 shrink-0 text-pal-600" />
                <span className="min-w-0 leading-tight">
                    <span className={cx('block text-xs text-muted', compact && 'sr-only sm:not-sr-only')}>{t('location.yourArea')}</span>
                    <span className="block truncate text-sm font-bold text-ink group-hover:underline">
                        {region ? regionLabel(region, locale) : t('location.choose')}
                    </span>
                </span>
            </button>
            <AreaDialog open={open} onClose={() => setOpen(false)} />
        </>
    );
}

const DETECT_ERRORS = {
    denied: 'location.errorDenied',
    unavailable: 'location.errorUnavailable',
    outside: 'location.errorOutside',
    unsupported: 'location.errorUnsupported',
};

export function AreaDialog({ open, onClose }) {
    const { t, locale } = useI18n();
    const { region, setRegion, clearRegion } = useArea();
    const ref = useRef(null);
    const titleId = useId();
    const [filter, setFilter] = useState('');
    const [detecting, setDetecting] = useState(false);
    const [suggestion, setSuggestion] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) return;
        if (open && !dialog.open) {
            setFilter('');
            setSuggestion(null);
            setError(null);
            dialog.showModal();
        }
        if (!open && dialog.open) dialog.close();
    }, [open]);

    const choose = (name) => {
        setRegion(name);
        onClose();
    };

    const detect = async () => {
        setDetecting(true);
        setError(null);
        setSuggestion(null);
        const result = await detectRegion();
        setDetecting(false);
        if (result.region) setSuggestion(result.region);
        else setError(DETECT_ERRORS[result.error]);
    };

    const needle = filter.trim().toLowerCase();
    const regions = REGIONS.filter(
        (r) => !needle || r.name.toLowerCase().includes(needle) || (r.sw ?? '').toLowerCase().includes(needle),
    );

    return (
        <dialog
            ref={ref}
            aria-labelledby={titleId}
            onCancel={(event) => {
                event.preventDefault();
                onClose();
            }}
            className="m-auto max-h-[min(42rem,calc(100vh-2rem))] w-[min(30rem,calc(100vw-2rem))] overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper p-0 text-ink shadow-2xl backdrop:bg-black/60"
        >
            <div className="flex max-h-[inherit] flex-col">
                <div className="flex items-start justify-between gap-3 border-b border-rule px-5 py-4">
                    <div>
                        <h2 id={titleId} className="text-[1.5rem]">
                            {t('location.title')}
                        </h2>
                        <p className="mt-1 text-sm text-muted">{t('location.why')}</p>
                    </div>
                    <button type="button" onClick={onClose} className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[6px] text-muted hover:bg-concrete hover:text-ink">
                        <CloseIcon />
                        <span className="sr-only">{t('common.close')}</span>
                    </button>
                </div>

                <div className="space-y-4 overflow-y-auto px-5 py-4">
                    <div>
                        <Button variant="secondary" block onClick={detect} loading={detecting} loadingText={t('location.detecting')}>
                            <LocateIcon className="h-5 w-5" />
                            {t('location.useMyLocation')}
                        </Button>
                        <p className="mt-1.5 text-xs text-muted">{t('location.privacy')}</p>
                    </div>

                    {suggestion && (
                        <div role="status" className="rounded-[var(--radius-control)] border border-pal-200 bg-pal-50 px-4 py-3">
                            <p className="font-semibold">{t('location.suggestion', { region: regionLabel(suggestion, locale) })}</p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                <Button size="sm" onClick={() => choose(suggestion)}>
                                    {t('location.confirmSuggestion', { region: regionLabel(suggestion, locale) })}
                                </Button>
                                <Button size="sm" variant="ghost" onClick={() => setSuggestion(null)}>
                                    {t('location.chooseMyself')}
                                </Button>
                            </div>
                        </div>
                    )}
                    {error && (
                        <p role="alert" className="rounded-[var(--radius-control)] bg-safety-soft px-4 py-3 text-sm font-medium text-ink">
                            {t(error)}
                        </p>
                    )}

                    <div>
                        <label className="relative block">
                            <span className="mb-1.5 block text-sm font-semibold">{t('location.orChoose')}</span>
                            <SearchIcon className="pointer-events-none absolute bottom-3 left-3 h-5 w-5 text-faint" />
                            <input
                                type="search"
                                className="field-input pl-10"
                                placeholder={t('location.searchRegions')}
                                value={filter}
                                onChange={(event) => setFilter(event.target.value)}
                            />
                        </label>
                        <ul className="mt-2 grid grid-cols-2 gap-1" aria-label={t('location.regions')}>
                            {regions.map((r) => (
                                <li key={r.name}>
                                    <button
                                        type="button"
                                        aria-pressed={region === r.name}
                                        onClick={() => choose(r.name)}
                                        className={cx(
                                            'flex min-h-11 w-full items-center justify-between gap-2 rounded-[6px] px-3 py-2 text-left text-sm font-semibold',
                                            region === r.name ? 'bg-pal-50 text-pal-700' : 'hover:bg-concrete',
                                        )}
                                    >
                                        {regionLabel(r.name, locale)}
                                        {region === r.name && <CheckIcon className="h-4 w-4 shrink-0" />}
                                    </button>
                                </li>
                            ))}
                        </ul>
                        {regions.length === 0 && <p className="mt-2 text-sm text-muted">{t('location.noMatch')}</p>}
                    </div>
                </div>

                {region && (
                    <div className="flex items-center justify-between gap-3 border-t border-rule px-5 py-3">
                        <span className="text-sm text-muted">{t('location.current', { region: regionLabel(region, locale) })}</span>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                                clearRegion();
                                onClose();
                            }}
                        >
                            {t('location.clear')}
                        </Button>
                    </div>
                )}
            </div>
        </dialog>
    );
}
