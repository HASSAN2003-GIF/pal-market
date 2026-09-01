const API_BASE_URL = '/api';

function getAuthToken() {
    return localStorage.getItem('pal_market_token');
}

async function request(endpoint, options = {}) {
    const token = getAuthToken();

    const response = await fetch(`${API_BASE_URL}${endpoint}`, {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',

            ...(token
                ? {
                      Authorization: `Bearer ${token}`,
                  }
                : {}),

            ...(options.headers || {}),
        },

        ...options,
    });

    const contentType = response.headers.get('content-type') || '';

    const data = contentType.includes('application/json')
        ? await response.json()
        : null;

    if (!response.ok) {
        const error = new Error(
            data?.message ||
                'Something went wrong while contacting the server.',
        );

        error.status = response.status;
        error.data = data;

        throw error;
    }

    return data;
}

export function getProducts(params = {}) {
    const searchParams = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            searchParams.set(key, value);
        }
    });

    const query = searchParams.toString();

    return request(`/products${query ? `?${query}` : ''}`);
}

export function getProduct(id) {
    return request(`/products/${id}`);
}

export function registerUser(payload) {
    return request('/register', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export function loginUser(payload) {
    return request('/login', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export function getCurrentUser() {
    return request('/me');
}

export function logoutUser() {
    return request('/logout', {
        method: 'POST',
    });
}

export function getBuyerRequests() {
    return request('/buyer-requests');
}

export function createBuyerRequest(payload) {
    return request('/buyer-requests', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export function getBuyerRequest(id) {
    return request(`/buyer-requests/${id}`);
}

export function addItemToRequest(requestId, payload) {
    return request(`/buyer-requests/${requestId}/items`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export function publishBuyerRequest(id) {
    return request(`/buyer-requests/${id}/publish`, {
        method: 'POST',
    });
}