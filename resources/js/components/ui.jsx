import { createContext, useCallback, useContext, useEffect, useId, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { statusInfo } from '../lib/statuses';
import { formatMoney } from '../lib/format';
import { useT } from '../i18n';

const cx = (...classes) => classes.filter(Boolean).join(' ');

/* ------------------------------------------------------------------ Buttons */

const BUTTON_VARIANTS = {
    primary: 'bg-action text-on-action hover:bg-action-hover border border-action hover:border-action-hover',
    secondary: 'bg-paper text-ink border border-rule hover:border-ink-soft',
    ghost: 'bg-transparent text-pal-700 border border-transparent hover:bg-pal-50',
    danger: 'bg-paper text-danger border border-danger/40 hover:bg-danger-soft',
    dark: 'bg-inverse text-on-inverse border border-inverse hover:opacity-90',
};

const BUTTON_SIZES = {
    sm: 'h-10 px-3 text-sm lg:h-9',
    md: 'h-11 px-4 text-[0.95rem]',
    lg: 'h-12 px-5 text-base',
};

export function buttonClasses({ variant = 'primary', size = 'md', block = false, className } = {}) {
    return cx(
        'inline-flex items-center justify-center gap-2 rounded-[var(--radius-control)] font-semibold whitespace-nowrap',
        'transition-colors disabled:cursor-not-allowed disabled:opacity-55',
        BUTTON_VARIANTS[variant],
        BUTTON_SIZES[size],
        block && 'w-full',
        className,
    );
}

export function Button({ variant, size, block, loading = false, loadingText, className, children, disabled, type = 'button', ...props }) {
    return (
        <button
            type={type}
            className={buttonClasses({ variant, size, block, className })}
            disabled={disabled || loading}
            aria-busy={loading || undefined}
            {...props}
        >
            {loading && <Spinner />}
            {loading && loadingText ? loadingText : children}
        </button>
    );
}

export function ButtonLink({ variant, size, block, className, ...props }) {
    return <Link className={buttonClasses({ variant, size, block, className })} {...props} />;
}

export function Spinner({ className = 'h-4 w-4' }) {
    return (
        <svg className={cx('animate-spin', className)} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeOpacity="0.25" strokeWidth="3" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
        </svg>
    );
}

/* ------------------------------------------------------------------- Fields */

/**
 * Label + control + hint + error, wired with aria attributes.
 * children is a render function receiving the control props.
 */
export function Field({ label, hint, error, optional, className, children }) {
    const id = useId();
    const t = useT();
    const describedBy = [hint && `${id}-hint`, error && `${id}-error`].filter(Boolean).join(' ') || undefined;

    return (
        <div className={cx('space-y-1.5', className)}>
            <label htmlFor={id} className="block text-sm font-semibold text-ink">
                {label}
                {optional && <span className="ml-1.5 font-normal text-muted">{t('common.optional')}</span>}
            </label>
            {children({ id, 'aria-describedby': describedBy, 'aria-invalid': error ? 'true' : undefined })}
            {hint && !error && (
                <p id={`${id}-hint`} className="text-sm text-muted">
                    {hint}
                </p>
            )}
            {error && (
                <p id={`${id}-error`} className="text-sm font-medium text-danger">
                    {error}
                </p>
            )}
        </div>
    );
}

export function TextField({ label, hint, error, optional, className, ...inputProps }) {
    return (
        <Field label={label} hint={hint} error={error} optional={optional} className={className}>
            {(aria) => <input className="field-input" {...aria} {...inputProps} />}
        </Field>
    );
}

export function TextAreaField({ label, hint, error, optional, className, rows = 3, ...inputProps }) {
    return (
        <Field label={label} hint={hint} error={error} optional={optional} className={className}>
            {(aria) => <textarea className="field-input" rows={rows} {...aria} {...inputProps} />}
        </Field>
    );
}

export function SelectField({ label, hint, error, optional, className, children, ...inputProps }) {
    return (
        <Field label={label} hint={hint} error={error} optional={optional} className={className}>
            {(aria) => (
                <select className="field-input pr-8" {...aria} {...inputProps}>
                    {children}
                </select>
            )}
        </Field>
    );
}

/* ------------------------------------------------------------ Status, money */

const TONES = {
    neutral: 'bg-concrete-deep text-ink-soft',
    info: 'bg-pal-100 text-pal-800',
    progress: 'bg-pal-50 text-pal-700 ring-1 ring-pal-200',
    good: 'bg-ok-soft text-ok',
    bad: 'bg-danger-soft text-danger',
    attention: 'bg-safety-soft text-warn',
};

export function StatusBadge({ kind, status, className }) {
    useT(); // re-render on language change
    const info = statusInfo(kind, status);
    return (
        <span
            className={cx('inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[0.8rem] font-semibold whitespace-nowrap', TONES[info.tone], className)}
            title={info.hint}
        >
            <span className="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
            {info.label}
        </span>
    );
}

export function Money({ amount, currency = 'TZS', className }) {
    return <span className={cx('tabular', className)}>{formatMoney(amount, currency)}</span>;
}

export function DocNumber({ children, className }) {
    return <span className={cx('doc-number text-[0.95rem] text-muted', className)}>{children}</span>;
}

/* ------------------------------------------------------------------- Layout */

export function PageHeader({ title, description, actions, back }) {
    return (
        <header className="mb-6 flex flex-col gap-4 sm:mb-8 sm:flex-row sm:items-end sm:justify-between">
            <div className="min-w-0">
                {back && (
                    <Link to={back.to} className="tap mb-1 inline-flex items-center gap-1 text-sm font-semibold text-pal-700 hover:underline">
                        <span aria-hidden="true">‹</span> {back.label}
                    </Link>
                )}
                <h1 className="text-[1.75rem] break-words sm:text-[2.2rem] lg:text-[2.4rem]">{title}</h1>
                {description && <p className="mt-1.5 max-w-2xl text-muted">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </header>
    );
}

/** A docket: white sheet with a header strip for title, number and status. */
export function Panel({ title, number, status, actions, children, className, bodyClassName, as: Tag = 'section' }) {
    return (
        <Tag className={cx('overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper', className)}>
            {(title || number || status || actions) && (
                <div className="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-rule px-4 py-3 sm:px-5">
                    <div className="flex min-w-0 flex-1 flex-wrap items-baseline gap-x-3 gap-y-1">
                        {title && <h2 className="text-[1.35rem]">{title}</h2>}
                        {number && <DocNumber>{number}</DocNumber>}
                    </div>
                    {status}
                    {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
                </div>
            )}
            <div className={cx('px-4 py-4 sm:px-5', bodyClassName)}>{children}</div>
        </Tag>
    );
}

export function Stat({ label, value, to, attention }) {
    const body = (
        <>
            <span className="block text-sm text-muted">{label}</span>
            <span className={cx('mt-1 block text-[2rem] leading-none font-extrabold tabular', attention && 'text-warn')}>
                {value}
            </span>
        </>
    );
    const classes = cx(
        'block rounded-[var(--radius-panel)] border bg-paper px-4 py-3.5',
        attention ? 'border-safety' : 'border-rule',
        to && 'hover:border-pal-500',
    );
    return to ? (
        <Link to={to} className={classes}>
            {body}
        </Link>
    ) : (
        <div className={classes}>{body}</div>
    );
}

/* ---------------------------------------------------- Empty, error, loading */

export function EmptyState({ title, children, action }) {
    return (
        <div className="rounded-[var(--radius-panel)] border border-dashed border-rule bg-paper px-6 py-10 text-center">
            <h3 className="text-[1.4rem]">{title}</h3>
            {children && <p className="mx-auto mt-2 max-w-md text-muted">{children}</p>}
            {action && <div className="mt-5 flex justify-center">{action}</div>}
        </div>
    );
}

export function ErrorState({ error, onRetry, title }) {
    const t = useT();
    return (
        <div role="alert" className="rounded-[var(--radius-panel)] border border-danger/30 bg-danger-soft px-5 py-5">
            <p className="font-semibold text-danger">{title ?? t('common.loadFailed')}</p>
            <p className="mt-1 text-sm text-ink-soft">{error?.message ?? t('common.pleaseTryAgain')}</p>
            {onRetry && (
                <Button variant="secondary" size="sm" className="mt-3" onClick={onRetry}>
                    {t('common.tryAgain')}
                </Button>
            )}
        </div>
    );
}

export function LoadingRows({ rows = 4, label }) {
    const t = useT();
    return (
        <div role="status" aria-label={label ?? t('common.loading')} className="space-y-2">
            {Array.from({ length: rows }).map((_, i) => (
                <div key={i} className="h-14 animate-pulse rounded-[var(--radius-control)] bg-concrete-deep/70" />
            ))}
        </div>
    );
}

/** Renders loading / error / children for a react-query result. */
export function QueryBoundary({ query, loading, children }) {
    if (query.isPending) return loading ?? <LoadingRows />;
    if (query.isError) return <ErrorState error={query.error} onRetry={() => query.refetch()} />;
    return children(query.data);
}

export function Notice({ tone = 'info', title, children, action, className }) {
    const tones = {
        info: 'border-pal-200 bg-pal-50',
        attention: 'border-safety bg-safety-soft',
        bad: 'border-danger/30 bg-danger-soft',
        good: 'border-ok/30 bg-ok-soft',
    };
    return (
        <div role={tone === 'bad' ? 'alert' : 'status'} className={cx('rounded-[var(--radius-panel)] border px-4 py-3', tones[tone], className)}>
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    {title && <p className="font-semibold text-ink">{title}</p>}
                    {children && <div className="text-sm text-ink-soft">{children}</div>}
                </div>
                {action}
            </div>
        </div>
    );
}

export function Pagination({ meta, onPage }) {
    const t = useT();
    if (!meta || meta.last_page <= 1) return null;
    return (
        <nav aria-label={t('common.pagination')} className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
            <span className="text-muted">{t('common.pageOf', { page: meta.current_page, pages: meta.last_page, total: meta.total })}</span>
            <div className="flex gap-2">
                <Button variant="secondary" size="sm" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>
                    {t('common.previous')}
                </Button>
                <Button variant="secondary" size="sm" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>
                    {t('common.next')}
                </Button>
            </div>
        </nav>
    );
}

export function FormError({ error }) {
    if (!error) return null;
    return (
        <div role="alert" className="rounded-[var(--radius-control)] border border-danger/30 bg-danger-soft px-3.5 py-2.5 text-sm font-medium text-danger">
            {error}
        </div>
    );
}

/* ------------------------------------------------------------------- Dialog */

/**
 * Confirmation dialog built on <dialog> for native focus trapping and Esc.
 */
export function ConfirmDialog({ open, title, children, confirmLabel, confirmVariant = 'primary', onConfirm, onCancel, busy, error }) {
    const ref = useRef(null);
    const titleId = useId();
    const t = useT();

    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) return;
        if (open && !dialog.open) dialog.showModal();
        if (!open && dialog.open) dialog.close();
    }, [open]);

    return (
        <dialog
            ref={ref}
            onCancel={(event) => {
                event.preventDefault();
                if (!busy) onCancel();
            }}
            className="m-auto w-[min(32rem,calc(100vw-2rem))] rounded-[var(--radius-panel)] border border-rule bg-paper p-0 text-ink shadow-2xl backdrop:bg-black/60"
            aria-labelledby={titleId}
        >
            <div className="p-5 sm:p-6">
                <h2 id={titleId} className="text-[1.6rem]">
                    {title}
                </h2>
                <div className="mt-2 text-ink-soft">{children}</div>
                {error && (
                    <div className="mt-3">
                        <FormError error={error} />
                    </div>
                )}
                <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button variant="secondary" onClick={onCancel} disabled={busy}>
                        {t('common.goBack')}
                    </Button>
                    <Button variant={confirmVariant} onClick={onConfirm} loading={busy}>
                        {confirmLabel}
                    </Button>
                </div>
            </div>
        </dialog>
    );
}

/* ------------------------------------------------------------------- Toasts */

const ToastContext = createContext(() => {});

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);

    const notify = useCallback((message, tone = 'good') => {
        const id = Math.random().toString(36).slice(2);
        setToasts((current) => [...current, { id, message, tone }]);
        setTimeout(() => setToasts((current) => current.filter((t) => t.id !== id)), 4500);
    }, []);

    return (
        <ToastContext.Provider value={notify}>
            {children}
            <div aria-live="polite" className="pointer-events-none fixed inset-x-0 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-50 md:bottom-4 flex flex-col items-center gap-2 px-4">
                {toasts.map((toast) => (
                    <div
                        key={toast.id}
                        className={cx(
                            'pointer-events-auto max-w-md rounded-[var(--radius-control)] px-4 py-2.5 text-center text-sm font-semibold shadow-lg',
                            toast.tone === 'bad' ? 'bg-danger-solid text-white' : 'bg-inverse text-on-inverse',
                        )}
                    >
                        {toast.message}
                    </div>
                ))}
            </div>
        </ToastContext.Provider>
    );
}

export function useToast() {
    return useContext(ToastContext);
}

export { cx };
