import { Outlet } from 'react-router-dom';
import TwoFactorSetup from '../components/TwoFactorSetup';
import { PageHeader } from '../components/ui';
import { useAuth } from '../context/AuthContext';
import { useT } from '../i18n';

/**
 * Administrators turn on two-step sign-in before the console opens. The
 * server enforces the same rule on every admin API call; this screen only
 * explains it and offers the setup.
 */
export default function AdminTwoFactorGate() {
    const t = useT();
    const { user } = useAuth();

    if (user?.two_factor_required && !user.two_factor_enabled) {
        return (
            <div className="max-w-2xl">
                <PageHeader title={t('twoFactor.gateTitle')} description={t('twoFactor.gateBody')} />
                <TwoFactorSetup />
            </div>
        );
    }

    return <Outlet />;
}
