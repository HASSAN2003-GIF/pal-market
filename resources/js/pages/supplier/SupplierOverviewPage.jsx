import { useQuery } from '@tanstack/react-query';
import { ButtonLink, Notice, PageHeader, QueryBoundary, Stat } from '../../components/ui';
import { useAuth } from '../../context/AuthContext';
import { useT } from '../../i18n';
import { api } from '../../lib/api';

export default function SupplierOverviewPage() {
    const t = useT();
    const { user } = useAuth();
    const summary = useQuery({ queryKey: ['summary'], queryFn: api.summary });

    return (
        <>
            <PageHeader
                title={user?.supplier?.business_name ?? t('nav.sellerHome')}
                description={t('supplier.overviewDescription')}
                actions={<ButtonLink to="/supplier/market">{t('supplier.seeRequests')}</ButtonLink>}
            />
            <QueryBoundary query={summary}>
                {({ summary: { supplier: s } }) => {
                    const quotes = s.quotations_by_status ?? {};
                    const setupMissing = s.status === 'approved' && (s.catalog_products === 0 || s.locations === 0);
                    return (
                        <>
                            {setupMissing && (
                                <Notice
                                    tone="attention"
                                    className="mb-6"
                                    title={t('supplier.setupTitle')}
                                    action={
                                        <ButtonLink size="sm" to={s.locations === 0 ? '/supplier/locations' : '/supplier/catalog'}>
                                            {s.locations === 0 ? t('supplier.addShop') : t('supplier.addProducts')}
                                        </ButtonLink>
                                    }
                                >
                                    <ol className="mt-1 list-decimal space-y-0.5 pl-5">
                                        <li className={s.locations > 0 ? 'line-through opacity-70' : ''}>{t('supplier.setupStep1')}</li>
                                        <li className={s.catalog_products > 0 ? 'line-through opacity-70' : ''}>{t('supplier.setupStep2')}</li>
                                        <li>{t('supplier.setupStep3')}</li>
                                    </ol>
                                </Notice>
                            )}
                            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                                <Stat label={t('supplier.statNotQuoted')} value={s.open_market_requests} to="/supplier/market?quoted=no" attention={s.open_market_requests > 0} />
                                <Stat label={t('supplier.statAwaiting')} value={quotes.submitted ?? 0} to="/supplier/quotations?status=submitted" />
                                <Stat label={t('supplier.statToFulfil')} value={s.orders_to_fulfil} to="/orders" attention={s.orders_to_fulfil > 0} />
                                <Stat label={t('supplier.statLowStock')} value={s.low_stock_items} to="/supplier/catalog" attention={s.low_stock_items > 0} />
                            </div>
                            <div className="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                                <Stat label={t('supplier.statWon')} value={quotes.accepted ?? 0} to="/supplier/quotations?status=accepted" />
                                <Stat label={t('supplier.statDeclined')} value={quotes.rejected ?? 0} to="/supplier/quotations?status=rejected" />
                                <Stat label={t('supplier.statProducts')} value={s.catalog_products} to="/supplier/catalog" />
                                <Stat label={t('supplier.statShops')} value={s.locations} to="/supplier/locations" />
                            </div>
                        </>
                    );
                }}
            </QueryBoundary>
        </>
    );
}
