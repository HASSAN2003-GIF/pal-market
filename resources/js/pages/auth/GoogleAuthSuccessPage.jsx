import { useEffect, useRef } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Spinner } from '../../components/ui';
import { homePathFor, useAuth } from '../../context/AuthContext';
import { translate, useT } from '../../i18n';

/**
 * Exchanges the single-use code from the Google callback for an API token.
 * The code is removed from the address bar immediately.
 */
export default function GoogleAuthSuccessPage() {
    const t = useT();
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const { completeGoogleSignIn } = useAuth();
    const started = useRef(false);

    useEffect(() => {
        if (started.current) return; // StrictMode runs effects twice; codes are single-use.
        started.current = true;

        const code = searchParams.get('code');
        window.history.replaceState(null, '', '/auth/google/success');

        const fail = (message) => navigate('/login', { replace: true, state: { error: message } });

        if (!code) {
            fail(translate('auth.googleFailed'));
            return;
        }

        completeGoogleSignIn(code)
            .then((result) =>
                result?.twoFactorChallenge
                    ? navigate('/login', { replace: true, state: { twoFactorChallenge: result.twoFactorChallenge } })
                    : navigate(homePathFor(result), { replace: true }),
            )
            .catch((err) => fail(err.fieldErrors?.code ?? err.message));
    }, [completeGoogleSignIn, navigate, searchParams]);

    return (
        <div role="status" className="flex min-h-[50vh] items-center justify-center gap-2 text-muted">
            <Spinner /> {t('auth.googleFinishing')}
        </div>
    );
}
