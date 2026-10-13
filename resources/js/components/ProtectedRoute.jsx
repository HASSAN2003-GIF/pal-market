import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { homePathFor, useAuth } from '../context/AuthContext';
import { Spinner } from './ui';

/**
 * Client-side routing guard. This only decides what to show: every API
 * endpoint enforces the same rules on the server.
 */
export default function ProtectedRoute({ roles }) {
    const { user, loading } = useAuth();
    const location = useLocation();

    if (loading) {
        return (
            <div role="status" className="flex min-h-[50vh] items-center justify-center gap-2 text-muted">
                <Spinner /> Checking your account
            </div>
        );
    }

    if (!user) {
        return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />;
    }

    if (roles && !roles.includes(user.role)) {
        return <Navigate to={homePathFor(user)} replace />;
    }

    return <Outlet />;
}
