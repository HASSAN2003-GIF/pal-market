import { useQuery } from '@tanstack/react-query';
import { ButtonLink, Notice, PageHeader, QueryBoundary, Stat } from '../../components/ui';
import { useAuth } from '../../context/AuthContext';
import { api } from '../../lib/api';

export default function SupplierOverviewPage() {
    const { user } = useAuth();
    const summary = useQuery({ queryKey: ['summary'], queryFn: api.summary });

    return (
        <>
            <PageHeader
                title={user?.supplier?.business_name ?? 'Supplier workspace'}
                description="Quote on open requests and fulfil the orders you win."
                actions={<ButtonLink to="/supplier/market">See open requests</ButtonLink>}
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
                                    title="Finish setting up to quote"
                                    action={
                                        <ButtonLink size="sm" to={s.locations === 0 ? '/supplier/locations' : '/supplier/catalog'}>
                                            {s.locations === 0 ? 'Add a location' : 'Add products'}
                                        </ButtonLink>
                                    }
                                >
                                    You can only quote on products in your catalog. Add at least one location and the products you stock.
                                </Notice>
                            )}
                            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                                <Stat label="Requests you have not quoted" value={s.open_market_requests} to="/supplier/market?quoted=no" attention={s.open_market_requests > 0} />
                                <Stat label="Quotes awaiting decision" value={quotes.submitted ?? 0} to="/supplier/quotations?status=submitted" />
                                <Stat label="Orders to fulfil" value={s.orders_to_fulfil} to="/orders" attention={s.orders_to_fulfil > 0} />
                                <Stat label="Low-stock lines" value={s.low_stock_items} to="/supplier/catalog" attention={s.low_stock_items > 0} />
                            </div>
                            <div className="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                                <Stat label="Quotes won" value={quotes.accepted ?? 0} to="/supplier/quotations?status=accepted" />
                                <Stat label="Quotes declined" value={quotes.rejected ?? 0} to="/supplier/quotations?status=rejected" />
                                <Stat label="Products in catalog" value={s.catalog_products} to="/supplier/catalog" />
                                <Stat label="Locations" value={s.locations} to="/supplier/locations" />
                            </div>
                        </>
                    );
                }}
            </QueryBoundary>
        </>
    );
}
