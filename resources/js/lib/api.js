const API_BASE_URL = '/api';
export const TOKEN_KEY = 'pal_market_token';

let unauthorizedHandler = null;

/** Called by AuthProvider so an expired/revoked token signs the user out. */
export function onUnauthorized(handler) {
    unauthorizedHandler = handler;
}

export function getToken() {
    try {
        return localStorage.getItem(TOKEN_KEY);
    } catch {
        return null;
    }
}

export function setToken(token) {
    try {
        if (token) localStorage.setItem(TOKEN_KEY, token);
        else localStorage.removeItem(TOKEN_KEY);
    } catch {
        // Storage unavailable (private mode); the session lasts for this tab.
    }
}

export class ApiError extends Error {
    constructor(message, status, data) {
        super(message);
        this.status = status;
        this.data = data;
        /** Field errors keyed by field name, first message only. */
        this.fieldErrors = Object.fromEntries(
            Object.entries(data?.errors ?? {}).map(([key, messages]) => [key, [].concat(messages)[0]]),
        );
    }
}

export async function request(endpoint, { method = 'GET', body, query, signal } = {}) {
    const token = getToken();
    const search = query ? toQueryString(query) : '';

    let response;
    try {
        response = await fetch(`${API_BASE_URL}${endpoint}${search}`, {
            method,
            signal,
            headers: {
                Accept: 'application/json',
                ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
                ...(token ? { Authorization: `Bearer ${token}` } : {}),
            },
            body: body !== undefined ? JSON.stringify(body) : undefined,
        });
    } catch (error) {
        if (error.name === 'AbortError') throw error;
        throw new ApiError('Could not reach PAL Market. Check your connection and try again.', 0, null);
    }

    const isJson = (response.headers.get('content-type') || '').includes('application/json');
    const data = isJson ? await response.json().catch(() => null) : null;

    if (!response.ok) {
        if (response.status === 401 && token && unauthorizedHandler) {
            unauthorizedHandler('expired');
        }
        if (response.status === 403 && data?.code === 'account_suspended' && unauthorizedHandler) {
            unauthorizedHandler('suspended');
        }

        throw new ApiError(messageFor(response.status, data), response.status, data);
    }

    return data;
}

function messageFor(status, data) {
    if (data?.message && status !== 500) return data.message;
    if (status === 429) return 'Too many attempts. Please wait a moment and try again.';
    if (status === 404) return 'This item could not be found. It may have been removed.';
    if (status === 403) return 'You do not have permission to do that.';
    if (status >= 500) return 'Something went wrong on our side. Please try again shortly.';
    return 'The request could not be completed.';
}

function toQueryString(params) {
    const search = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') search.set(key, value);
    });
    const text = search.toString();
    return text ? `?${text}` : '';
}

const post = (url, body) => request(url, { method: 'POST', body: body ?? {} });
const put = (url, body) => request(url, { method: 'PUT', body });
const patch = (url, body) => request(url, { method: 'PATCH', body });
const del = (url) => request(url, { method: 'DELETE' });

export const api = {
    // Account
    register: (payload) => post('/register', payload),
    login: (payload) => post('/login', payload),
    logout: () => post('/logout'),
    me: () => request('/me'),
    updateProfile: (payload) => put('/me', payload),
    updatePassword: (payload) => put('/me/password', payload),
    forgotPassword: (email) => post('/forgot-password', { email }),
    resetPassword: (payload) => post('/reset-password', payload),
    exchangeGoogleCode: (code) => post('/auth/google/exchange', { code }),
    summary: () => request('/workspace/summary'),

    // Catalog
    products: (query) => request('/products', { query }),
    product: (id) => request(`/products/${id}`),
    catalogFilters: () => request('/catalog/filters'),

    // Buyer requests
    buyerRequests: (query) => request('/buyer-requests', { query }),
    buyerRequest: (id) => request(`/buyer-requests/${id}`),
    createBuyerRequest: (payload) => post('/buyer-requests', payload),
    updateBuyerRequest: (id, payload) => put(`/buyer-requests/${id}`, payload),
    deleteBuyerRequest: (id) => del(`/buyer-requests/${id}`),
    addRequestItem: (id, payload) => post(`/buyer-requests/${id}/items`, payload),
    updateRequestItem: (id, itemId, payload) => put(`/buyer-requests/${id}/items/${itemId}`, payload),
    removeRequestItem: (id, itemId) => del(`/buyer-requests/${id}/items/${itemId}`),
    publishBuyerRequest: (id, openForDays) => post(`/buyer-requests/${id}/publish`, { open_for_days: openForDays }),
    cancelBuyerRequest: (id) => post(`/buyer-requests/${id}/cancel`),

    // Market and quotations
    marketRequests: (query) => request('/market-requests', { query }),
    marketRequest: (id) => request(`/market-requests/${id}`),
    quotations: (query) => request('/quotations', { query }),
    quotation: (id) => request(`/quotations/${id}`),
    createQuotation: (requestId, payload) => post(`/buyer-requests/${requestId}/quotations`, payload),
    withdrawQuotation: (id) => post(`/quotations/${id}/withdraw`),
    acceptQuotation: (id, createPurchaseOrder = true) =>
        post(`/quotations/${id}/accept`, { create_purchase_order: createPurchaseOrder }),
    rejectQuotation: (id) => post(`/quotations/${id}/reject`),
    createPurchaseOrder: (quotationId) => post(`/quotations/${quotationId}/purchase-order`),

    // Purchase orders
    purchaseOrders: (query) => request('/purchase-orders', { query }),
    purchaseOrder: (id) => request(`/purchase-orders/${id}`),
    transitionPurchaseOrder: (id, action) => post(`/purchase-orders/${id}/${action}`),

    // Supplier catalog
    supplierProducts: () => request('/supplier-products'),
    addSupplierProduct: (payload) => post('/supplier-products', payload),
    updateSupplierProduct: (id, payload) => put(`/supplier-products/${id}`, payload),
    removeSupplierProduct: (id) => del(`/supplier-products/${id}`),
    addSupplierPrice: (payload) => post('/supplier-prices', payload),
    updateSupplierPrice: (id, payload) => put(`/supplier-prices/${id}`, payload),
    addInventory: (payload) => post('/supplier-inventory', payload),
    updateInventory: (id, payload) => put(`/supplier-inventory/${id}`, payload),
    supplierLocations: () => request('/supplier-locations'),
    addLocation: (payload) => post('/supplier-locations', payload),
    updateLocation: (id, payload) => put(`/supplier-locations/${id}`, payload),
    removeLocation: (id) => del(`/supplier-locations/${id}`),

    // Administration
    admin: {
        dashboard: () => request('/admin/dashboard'),
        users: (query) => request('/admin/users', { query }),
        user: (id) => request(`/admin/users/${id}`),
        setRole: (id, role) => patch(`/admin/users/${id}/role`, { role }),
        suspendUser: (id, reason) => post(`/admin/users/${id}/suspend`, { reason }),
        reactivateUser: (id) => post(`/admin/users/${id}/reactivate`),
        deleteUser: (id) => del(`/admin/users/${id}`),
        suppliers: (query) => request('/admin/suppliers', { query }),
        supplierAction: (id, action) => post(`/admin/suppliers/${id}/${action}`),
        buyers: (query) => request('/admin/buyers', { query }),
        buyerAction: (id, action) => post(`/admin/buyers/${id}/${action}`),
        products: (query) => request('/admin/catalog/products', { query }),
        createProduct: (payload) => post('/admin/catalog/products', payload),
        updateProduct: (id, payload) => patch(`/admin/catalog/products/${id}`, payload),
        taxonomy: () => request('/admin/catalog/taxonomy'),
        createCategory: (payload) => post('/admin/catalog/categories', payload),
        updateCategory: (id, payload) => patch(`/admin/catalog/categories/${id}`, payload),
        createBrand: (payload) => post('/admin/catalog/brands', payload),
        updateBrand: (id, payload) => patch(`/admin/catalog/brands/${id}`, payload),
        requests: (query) => request('/admin/buyer-requests', { query }),
        orders: (query) => request('/admin/purchase-orders', { query }),
        auditLogs: (query) => request('/admin/audit-logs', { query }),
    },
};
