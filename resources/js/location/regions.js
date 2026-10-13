/**
 * Tanzania's 31 regions with the approximate position of each regional
 * capital. `name` is the value stored and sent to the API (it must match
 * App\Support\Regions on the server); `sw` is the Kiswahili display name.
 *
 * The coordinates are only used on the device to suggest the nearest region
 * after the person allows location access. They are never stored or sent.
 */
export const REGIONS = [
    { name: 'Arusha', lat: -3.387, lng: 36.683 },
    { name: 'Dar es Salaam', lat: -6.792, lng: 39.208 },
    { name: 'Dodoma', lat: -6.163, lng: 35.752 },
    { name: 'Geita', lat: -2.873, lng: 32.23 },
    { name: 'Iringa', lat: -7.77, lng: 35.699 },
    { name: 'Kagera', lat: -1.332, lng: 31.812 },
    { name: 'Katavi', lat: -6.344, lng: 31.069 },
    { name: 'Kigoma', lat: -4.877, lng: 29.627 },
    { name: 'Kilimanjaro', lat: -3.335, lng: 37.34 },
    { name: 'Lindi', lat: -9.997, lng: 39.714 },
    { name: 'Manyara', lat: -4.212, lng: 35.748 },
    { name: 'Mara', lat: -1.5, lng: 33.8 },
    { name: 'Mbeya', lat: -8.909, lng: 33.461 },
    { name: 'Morogoro', lat: -6.821, lng: 37.661 },
    { name: 'Mtwara', lat: -10.274, lng: 40.183 },
    { name: 'Mwanza', lat: -2.516, lng: 32.918 },
    { name: 'Njombe', lat: -9.333, lng: 34.767 },
    { name: 'Pemba North', sw: 'Kaskazini Pemba', lat: -5.057, lng: 39.728 },
    { name: 'Pemba South', sw: 'Kusini Pemba', lat: -5.246, lng: 39.767 },
    { name: 'Pwani', lat: -6.767, lng: 38.917 },
    { name: 'Rukwa', lat: -7.967, lng: 31.617 },
    { name: 'Ruvuma', lat: -10.683, lng: 35.65 },
    { name: 'Shinyanga', lat: -3.662, lng: 33.423 },
    { name: 'Simiyu', lat: -2.8, lng: 33.983 },
    { name: 'Singida', lat: -4.817, lng: 34.75 },
    { name: 'Songwe', lat: -9.117, lng: 32.933 },
    { name: 'Tabora', lat: -5.017, lng: 32.8 },
    { name: 'Tanga', lat: -5.069, lng: 39.099 },
    { name: 'Unguja North', sw: 'Kaskazini Unguja', lat: -5.877, lng: 39.256 },
    { name: 'Unguja South', sw: 'Kusini Unguja', lat: -6.133, lng: 39.3 },
    { name: 'Mjini Magharibi', lat: -6.166, lng: 39.203 },
];

export const REGION_NAMES = REGIONS.map((region) => region.name);

export function isRegion(name) {
    return REGION_NAMES.includes(name);
}

/** Display name in the interface language; the stored value never changes. */
export function regionLabel(name, locale) {
    const region = REGIONS.find((r) => r.name === name);
    if (!region) return name ?? '';
    return locale === 'sw' && region.sw ? region.sw : region.name;
}

function distanceKm(a, b) {
    const rad = (deg) => (deg * Math.PI) / 180;
    const dLat = rad(b.lat - a.lat);
    const dLng = rad(b.lng - a.lng);
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
    return 2 * 6371 * Math.asin(Math.sqrt(h));
}

// Rough box around mainland Tanzania and Zanzibar.
const inTanzania = ({ lat, lng }) => lat <= -0.9 && lat >= -11.8 && lng >= 29.2 && lng <= 40.6;

/**
 * The region whose capital is nearest. An approximation near borders, which
 * is why the person is always asked to confirm it.
 */
export function nearestRegion(point) {
    if (!inTanzania(point)) return null;
    let best = null;
    for (const region of REGIONS) {
        const km = distanceKm(point, region);
        if (!best || km < best.km) best = { name: region.name, km };
    }
    return best?.name ?? null;
}

/**
 * Asks the browser for a one-off, low-accuracy position and resolves to
 * { region } or { error: 'denied' | 'unavailable' | 'outside' | 'unsupported' }.
 * Coordinates stay inside this function.
 */
export function detectRegion() {
    return new Promise((resolve) => {
        if (!('geolocation' in navigator)) {
            resolve({ error: 'unsupported' });
            return;
        }
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                const region = nearestRegion({ lat: coords.latitude, lng: coords.longitude });
                resolve(region ? { region } : { error: 'outside' });
            },
            (error) => resolve({ error: error.code === 1 ? 'denied' : 'unavailable' }),
            { enableHighAccuracy: false, timeout: 15000, maximumAge: 600000 },
        );
    });
}
