import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { homePathFor, useAuth } from '../context/AuthContext';
import { useT } from '../i18n';
import { ButtonLink, Spinner } from './ui';

/**
 * Client-side routing guard. This only decides what to show: every API
 * endpoint enforces the same rules on the server, so reaching a URL (such
 * as /admin) never reveals data the account may not see.
 */
export default function ProtectedRoute({ roles }) {
    const t = useT();
    const { user, loading } = useAuth();
    const location = useLocation();

    if (loading) {
        return (
            <div role="status" className="flex min-h-[50vh] items-center justify-center gap-2 text-muted">
                <Spinner /> {t('auth.checking')}
            </div>
        );
    }

    if (!user) {
        return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />;
    }

    if (roles && !roles.includes(user.role)) {
        // The admin area is the one place where a plain redirect would hide
        // why nothing happened; say so instead.
        if (roles.includes('admin')) return <NoAccess home={homePathFor(user)} />;
        return <Navigate to={homePathFor(user)} replace />;
    }

    return <Outlet />;
}

function NoAccess({ home }) {
    const t = useT();
    return (
        <div role="alert" className="mx-auto max-w-lg px-4 py-20 text-center">
            <h1 className="text-[2.2rem]">{t('auth.noAccessTitle')}</h1>
            <p className="mt-3 text-ink-soft">{t('auth.noAccessBody')}</p>
            <div className="mt-6 flex justify-center">
                <ButtonLink to={home}>{t('auth.noAccessHome')}</ButtonLink>
            </div>
        </div>
    );
}
