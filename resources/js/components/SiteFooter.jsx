import { Link } from 'react-router-dom';
import { useT } from '../i18n';
import Logo from './Logo';

/** Shared footer. Links only to pages that exist; the year is the visitor's current year. */
export default function SiteFooter({ compact = false }) {
    const t = useT();
    const year = new Date().getFullYear();

    return (
        <footer className="border-t border-rule bg-paper">
            <div className={`mx-auto flex max-w-6xl flex-col gap-4 px-4 sm:px-6 ${compact ? 'py-5' : 'py-8'}`}>
                {!compact && (
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="max-w-sm">
                            <Link to="/" className="inline-block" aria-label={t('layout.home')}>
                                <Logo className="h-6" />
                            </Link>
                            <p className="mt-3 text-sm text-muted">{t('layout.footerAbout')}</p>
                        </div>
                        <nav aria-label={t('layout.footerNav')} className="flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold">
                            <Link className="text-ink-soft hover:text-ink hover:underline" to="/materials">
                                {t('layout.navMaterials')}
                            </Link>
                            <Link className="text-ink-soft hover:text-ink hover:underline" to="/register?role=supplier">
                                {t('layout.navSell')}
                            </Link>
                            <Link className="text-ink-soft hover:text-ink hover:underline" to="/help">
                                {t('layout.navHelp')}
                            </Link>
                        </nav>
                    </div>
                )}
                <p className="text-sm text-muted">{t('layout.copyright', { year })}</p>
            </div>
        </footer>
    );
}
