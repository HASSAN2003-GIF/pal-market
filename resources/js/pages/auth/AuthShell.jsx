import { Link } from 'react-router-dom';
import Logo from '../../components/Logo';
import { useT } from '../../i18n';

/**
 * Sign-in pages: one centred column on phones and tablets; on wide screens a
 * second column explains, in three steps, what the account is for.
 */
export default function AuthShell({ title, intro, children, footer }) {
    const t = useT();
    return (
        <div className="mx-auto grid max-w-6xl gap-10 px-4 py-8 sm:px-6 sm:py-12 lg:grid-cols-[minmax(0,28rem)_minmax(0,1fr)] lg:items-start lg:gap-16 lg:py-16 2xl:max-w-7xl">
            <div className="mx-auto w-full max-w-md lg:mx-0">
                <Link to="/" aria-label={t('layout.home')} className="mb-6 inline-block py-1">
                    <Logo variant="mark" className="h-10" />
                </Link>
                <h1 className="text-[1.9rem] sm:text-[2.2rem]">{title}</h1>
                {intro && <p className="mt-2 text-ink-soft">{intro}</p>}
                <div className="mt-6 rounded-[var(--radius-panel)] border border-rule bg-paper p-4 sm:p-6">{children}</div>
                {footer && <div className="mt-5 text-center text-sm text-ink-soft">{footer}</div>}
            </div>
            <aside aria-label={t('home.howTitle')} className="hidden rounded-[var(--radius-panel)] bg-inverse p-10 text-on-inverse lg:block">
                <Logo className="h-8 [--logo-ink:var(--color-on-inverse)]" />
                <p className="mt-6 max-w-md text-2xl leading-snug font-extrabold">{t('home.title')}</p>
                <ol className="mt-8 space-y-6">
                    {['1', '2', '3'].map((n) => (
                        <li key={n} className="flex gap-4">
                            <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-action font-extrabold text-on-action">{n}</span>
                            <span>
                                <span className="block font-bold">{t(`home.step${n}Title`)}</span>
                                <span className="mt-0.5 block opacity-80">{t(`home.step${n}Body`)}</span>
                            </span>
                        </li>
                    ))}
                </ol>
                <p className="mt-8 text-sm opacity-75">{t('home.noPayments')}</p>
            </aside>
        </div>
    );
}

export function GoogleButton({ role }) {
    const t = useT();
    return (
        <a
            href={`/auth/google/redirect${role === 'supplier' ? '?role=supplier' : ''}`}
            className="flex min-h-12 w-full items-center justify-center gap-3 rounded-[var(--radius-control)] border border-rule bg-paper px-3 text-center font-semibold hover:border-ink-soft"
        >
            <svg viewBox="0 0 24 24" className="h-5 w-5 shrink-0" aria-hidden="true">
                <path fill="#4285F4" d="M21.35 12.27c0-.71-.06-1.4-.18-2.05H12v3.88h5.24a4.48 4.48 0 0 1-1.94 2.94v2.45h3.14c1.84-1.69 2.91-4.18 2.91-7.22Z" />
                <path fill="#34A853" d="M12 21.5c2.63 0 4.84-.87 6.45-2.36l-3.14-2.45c-.87.58-1.98.93-3.31.93-2.54 0-4.69-1.72-5.46-4.03H3.29v2.53A9.74 9.74 0 0 0 12 21.5Z" />
                <path fill="#FBBC05" d="M6.54 13.59A5.86 5.86 0 0 1 6.23 12c0-.55.11-1.08.31-1.59V7.88H3.29A9.5 9.5 0 0 0 2.25 12c0 1.53.37 2.98 1.04 4.12l3.25-2.53Z" />
                <path fill="#EA4335" d="M12 6.38c1.43 0 2.71.49 3.72 1.45l2.79-2.79C16.84 3.5 14.63 2.5 12 2.5a9.74 9.74 0 0 0-8.71 5.38l3.25 2.53C7.31 8.1 9.46 6.38 12 6.38Z" />
            </svg>
            {t('auth.google')}
        </a>
    );
}

export function OrDivider() {
    const t = useT();
    return (
        <div className="my-5 flex items-center gap-3 text-sm text-muted" aria-hidden="true">
            <span className="h-px flex-1 bg-rule" />
            {t('auth.or')}
            <span className="h-px flex-1 bg-rule" />
        </div>
    );
}
