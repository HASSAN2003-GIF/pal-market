import { useEffect, useRef } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Spinner } from '../../components/ui';
import { homePathFor, useAuth } from '../../context/AuthContext';

/**
 * Exchanges the single-use code from the Google callback for an API token.
 * The code is removed from the address bar immediately.
 */
export default function GoogleAuthSuccessPage() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const { completeGoogleSignIn } = useAuth();
    const started = useRef(false);

    useEffect(() => {
        if (started.current) return; // StrictMode runs effects twice; codes are single-use.
        started.current = true;

        const code = searchParams.get('code');
        window.history.replaceState(null, '', '/auth/google/success');

        const fail = (message) => navigate(`/login?error=${encodeURIComponent(message)}`, { replace: true });

        if (!code) {
            fail('Google sign-in could not be completed. Please try again.');
            return;
        }

        completeGoogleSignIn(code)
            .then((user) => navigate(homePathFor(user), { replace: true }))
            .catch((err) => fail(err.fieldErrors?.code ?? err.message));
    }, [completeGoogleSignIn, navigate, searchParams]);

    return (
        <div role="status" className="flex min-h-[50vh] items-center justify-center gap-2 text-muted">
            <Spinner /> Finishing Google sign-in
        </div>
    );
}
