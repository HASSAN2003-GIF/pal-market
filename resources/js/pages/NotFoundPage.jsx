import { ButtonLink } from '../components/ui';
import { useT } from '../i18n';

export default function NotFoundPage() {
    const t = useT();
    return (
        <div className="mx-auto max-w-xl px-4 py-20 text-center">
            <p className="doc-number text-lg text-muted">404</p>
            <h1 className="mt-2 text-[2.4rem]">{t('common.notFoundTitle')}</h1>
            <p className="mt-3 text-ink-soft">{t('common.notFoundBody')}</p>
            <div className="mt-6 flex flex-wrap justify-center gap-3">
                <ButtonLink to="/">{t('common.goHome')}</ButtonLink>
                <ButtonLink to="/materials" variant="secondary">
                    {t('layout.navMaterials')}
                </ButtonLink>
            </div>
        </div>
    );
}
