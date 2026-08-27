import { useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const TOKEN_KEY = 'pal_market_token';

export default function GoogleAuthSuccessPage() {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const { loadUser } = useAuth();

    useEffect(() => {
        const token = searchParams.get('token');

        if (!token) {
            navigate('/login', {
                replace: true,
                state: {
                    error: 'Google sign-in could not be completed.',
                },
            });

            return;
        }

        localStorage.setItem(TOKEN_KEY, token);

        loadUser()
            .then(() => {
                navigate('/dashboard', { replace: true });
            })
            .catch(() => {
                localStorage.removeItem(TOKEN_KEY);

                navigate('/login', {
                    replace: true,
                    state: {
                        error: 'Google sign-in could not be completed.',
                    },
                });
            });
    }, [navigate, searchParams, loadUser]);

    return (
        <div className="flex min-h-[60vh] items-center justify-center bg-surface">
            <p className="text-sm text-ink-600">
                Completing Google sign-in...
            </p>
        </div>
    );
}