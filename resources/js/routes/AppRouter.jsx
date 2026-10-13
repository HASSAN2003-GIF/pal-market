import { Suspense, lazy } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import ProtectedRoute from '../components/ProtectedRoute';
import { LoadingRows } from '../components/ui';
import { homePathFor, useAuth } from '../context/AuthContext';
import PublicLayout from '../layouts/PublicLayout';
import WorkspaceLayout from '../layouts/WorkspaceLayout';
import HomePage from '../pages/HomePage';
import MaterialsPage from '../pages/MaterialsPage';
import ProductDetailPage from '../pages/ProductDetailPage';
import NotFoundPage from '../pages/NotFoundPage';
import LoginPage from '../pages/auth/LoginPage';
import RegisterPage from '../pages/auth/RegisterPage';
import ForgotPasswordPage from '../pages/auth/ForgotPasswordPage';
import ResetPasswordPage from '../pages/auth/ResetPasswordPage';
import GoogleAuthSuccessPage from '../pages/auth/GoogleAuthSuccessPage';

const page = (loader) => lazy(loader);

const AccountPage = page(() => import('../pages/AccountPage'));
const OrdersPage = page(() => import('../pages/orders/OrdersPage'));
const OrderDetailPage = page(() => import('../pages/orders/OrderDetailPage'));

const BuyerOverviewPage = page(() => import('../pages/buyer/BuyerOverviewPage'));
const BuyerRequestsPage = page(() => import('../pages/buyer/BuyerRequestsPage'));
const NewRequestPage = page(() => import('../pages/buyer/NewRequestPage'));
const BuyerRequestDetailPage = page(() => import('../pages/buyer/BuyerRequestDetailPage'));

const SupplierOverviewPage = page(() => import('../pages/supplier/SupplierOverviewPage'));
const MarketPage = page(() => import('../pages/supplier/MarketPage'));
const MarketRequestPage = page(() => import('../pages/supplier/MarketRequestPage'));
const SupplierQuotationsPage = page(() => import('../pages/supplier/SupplierQuotationsPage'));
const CatalogPage = page(() => import('../pages/supplier/CatalogPage'));
const LocationsPage = page(() => import('../pages/supplier/LocationsPage'));

const AdminOverviewPage = page(() => import('../pages/admin/AdminOverviewPage'));
const AdminSuppliersPage = page(() => import('../pages/admin/AdminSuppliersPage'));
const AdminBuyersPage = page(() => import('../pages/admin/AdminBuyersPage'));
const AdminUsersPage = page(() => import('../pages/admin/AdminUsersPage'));
const AdminCatalogPage = page(() => import('../pages/admin/AdminCatalogPage'));
const AdminRequestsPage = page(() => import('../pages/admin/AdminRequestsPage'));
const AdminOrdersPage = page(() => import('../pages/admin/AdminOrdersPage'));
const AdminAuditLogPage = page(() => import('../pages/admin/AdminAuditLogPage'));

function WorkspaceHome() {
    const { user } = useAuth();
    return <Navigate to={homePathFor(user)} replace />;
}

export default function AppRouter() {
    return (
        <BrowserRouter>
            <Suspense fallback={<div className="p-6"><LoadingRows rows={3} /></div>}>
                <Routes>
                    <Route element={<PublicLayout />}>
                        <Route index element={<HomePage />} />
                        <Route path="materials" element={<MaterialsPage />} />
                        <Route path="materials/:productId" element={<ProductDetailPage />} />
                        <Route path="login" element={<LoginPage />} />
                        <Route path="register" element={<RegisterPage />} />
                        <Route path="forgot-password" element={<ForgotPasswordPage />} />
                        <Route path="reset-password" element={<ResetPasswordPage />} />
                        <Route path="auth/google/success" element={<GoogleAuthSuccessPage />} />
                        {/* Old URLs kept working */}
                        <Route path="marketplace" element={<Navigate to="/materials" replace />} />
                        <Route path="products/:productId" element={<LegacyProductRedirect />} />
                    </Route>

                    <Route element={<ProtectedRoute />}>
                        <Route element={<WorkspaceLayout />}>
                            <Route path="dashboard" element={<WorkspaceHome />} />
                            <Route path="account" element={<AccountPage />} />
                            <Route path="orders" element={<OrdersPage />} />
                            <Route path="orders/:orderId" element={<OrderDetailPage />} />
                        </Route>
                    </Route>

                    <Route element={<ProtectedRoute roles={['buyer']} />}>
                        <Route element={<WorkspaceLayout />}>
                            <Route path="buyer" element={<BuyerOverviewPage />} />
                            <Route path="buyer/requests" element={<BuyerRequestsPage />} />
                            <Route path="buyer/requests/new" element={<NewRequestPage />} />
                            <Route path="buyer/requests/:requestId" element={<BuyerRequestDetailPage />} />
                        </Route>
                    </Route>

                    <Route element={<ProtectedRoute roles={['supplier']} />}>
                        <Route element={<WorkspaceLayout />}>
                            <Route path="supplier" element={<SupplierOverviewPage />} />
                            <Route path="supplier/market" element={<MarketPage />} />
                            <Route path="supplier/market/:requestId" element={<MarketRequestPage />} />
                            <Route path="supplier/quotations" element={<SupplierQuotationsPage />} />
                            <Route path="supplier/catalog" element={<CatalogPage />} />
                            <Route path="supplier/locations" element={<LocationsPage />} />
                        </Route>
                    </Route>

                    <Route element={<ProtectedRoute roles={['admin']} />}>
                        <Route element={<WorkspaceLayout />}>
                            <Route path="admin" element={<AdminOverviewPage />} />
                            <Route path="admin/suppliers" element={<AdminSuppliersPage />} />
                            <Route path="admin/buyers" element={<AdminBuyersPage />} />
                            <Route path="admin/users" element={<AdminUsersPage />} />
                            <Route path="admin/catalog" element={<AdminCatalogPage />} />
                            <Route path="admin/requests" element={<AdminRequestsPage />} />
                            <Route path="admin/orders" element={<AdminOrdersPage />} />
                            <Route path="admin/audit-log" element={<AdminAuditLogPage />} />
                        </Route>
                    </Route>

                    <Route element={<PublicLayout />}>
                        <Route path="*" element={<NotFoundPage />} />
                    </Route>
                </Routes>
            </Suspense>
        </BrowserRouter>
    );
}

function LegacyProductRedirect() {
    const id = window.location.pathname.split('/').pop();
    return <Navigate to={`/materials/${id}`} replace />;
}
