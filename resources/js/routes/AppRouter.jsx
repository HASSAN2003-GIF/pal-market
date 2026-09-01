import { BrowserRouter, Route, Routes } from 'react-router-dom';

import AppLayout from '../layouts/AppLayout';
import HomePage from '../pages/HomePage';
import LoginPage from '../pages/LoginPage';
import MarketplacePage from '../pages/MarketplacePage';
import ProductDetailPage from '../pages/ProductDetailPage';
import RegisterPage from '../pages/RegisterPage';
import ProtectedRoute from '../components/ProtectedRoute';
import GoogleAuthSuccessPage from '../pages/GoogleAuthSuccessPage';
import BuyerDashboardPage from '../pages/BuyerDashboardPage';
import CreateRequestPage from '../pages/CreateRequestPage';
import RequestDetailPage from '../pages/RequestDetailPage';

export default function AppRouter() {
    return (
        <BrowserRouter>
            <AppLayout>
                <Routes>
                    <Route path="/" element={<HomePage />} />

                    <Route
                        path="/marketplace"
                        element={<MarketplacePage />}
                    />

                    <Route
                        path="/products/:productId"
                        element={<ProductDetailPage />}
                    />

                    <Route path="/login" element={<LoginPage />} />

                    <Route
                        path="/register"
                        element={<RegisterPage />}
                    />
                    
                    <Route
    path="/auth/google/success"
    element={<GoogleAuthSuccessPage />}
/>

                    <Route element={<ProtectedRoute />}>
                        <Route path="/dashboard" element={<BuyerDashboardPage />} />

                        <Route path="/requests/new" element={<CreateRequestPage />} />

                        <Route path="/requests/:requestId" element={<RequestDetailPage />} />

                        <Route
                            path="/supplier"
                            element={
                                <div className="mx-auto max-w-7xl px-6 py-16">
                                    <h1 className="text-3xl font-semibold text-ink-950">
                                        Supplier workspace
                                    </h1>

                                    <p className="mt-2 text-sm text-ink-600">
                                        Your supplier tools will appear here.
                                    </p>
                                </div>
                            }
                        />

                        <Route
                            path="/admin"
                            element={
                                <div className="mx-auto max-w-7xl px-6 py-16">
                                    <h1 className="text-3xl font-semibold text-ink-950">
                                        Admin workspace
                                    </h1>

                                    <p className="mt-2 text-sm text-ink-600">
                                        Your administration tools will appear
                                        here.
                                    </p>
                                </div>
                            }
                        />
                    </Route>
                </Routes>
            </AppLayout>
        </BrowserRouter>
    );
}