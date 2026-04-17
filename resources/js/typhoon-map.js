import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
}

function alertColor(alert) {
    const a = String(alert || '');
    if (a === 'Red') {
        return '#b91c1c';
    }
    if (a === 'Orange') {
        return '#ea580c';
    }
    return '#15803d';
}

export function initTyphoonMap() {
    const el = document.getElementById('zc-typhoon-map');
    const dataEl = document.getElementById('zc-typhoon-data');
    if (!el || !dataEl) {
        return;
    }

    let data;
    try {
        data = JSON.parse(dataEl.textContent || '{}');
    } catch {
        return;
    }

    const ref = data.ref;
    const storms = Array.isArray(data.storms) ? data.storms : [];
    const radiusKm = typeof data.radius_km === 'number' ? data.radius_km : 2800;

    if (!ref || typeof ref.lat !== 'number' || typeof ref.lon !== 'number') {
        return;
    }

    const map = L.map(el, {
        scrollWheelZoom: false,
        attributionControl: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    const group = L.featureGroup();

    L.circle([ref.lat, ref.lon], {
        radius: radiusKm * 1000,
        color: '#15629a',
        weight: 1,
        opacity: 0.3,
        fillOpacity: 0.02,
        dashArray: '6 8',
    }).addTo(group);

    L.circleMarker([ref.lat, ref.lon], {
        radius: 9,
        color: '#00426d',
        weight: 2,
        fillColor: '#15629a',
        fillOpacity: 1,
    })
        .bindPopup(`<strong>${esc(ref.label)}</strong>`)
        .addTo(group);

    for (const st of storms) {
        const col = alertColor(st.alert);
        const past = Array.isArray(st.past) ? st.past : [];
        const forecast = Array.isArray(st.forecast) ? st.forecast : [];

        for (const seg of past) {
            if (Array.isArray(seg) && seg.length >= 2) {
                L.polyline(seg, {
                    color: col,
                    weight: 3,
                    opacity: 0.92,
                }).addTo(group);
            }
        }
        for (const seg of forecast) {
            if (Array.isArray(seg) && seg.length >= 2) {
                L.polyline(seg, {
                    color: col,
                    weight: 2,
                    opacity: 0.55,
                    dashArray: '8 7',
                }).addTo(group);
            }
        }

        if (typeof st.lat === 'number' && typeof st.lon === 'number') {
            const name = esc(st.name || '');
            const dist =
                typeof st.distance_km === 'number' ? `${esc(String(st.distance_km))} km` : '—';
            const safeUrl =
                typeof st.url === 'string' && st.url.startsWith('http') ? st.url : '';
            const pop = `<strong>${name}</strong><br><small>${dist}</small>${
                safeUrl
                    ? `<br><a href="${safeUrl.replace(/&/g, '&amp;').replace(/"/g, '&quot;')}" target="_blank" rel="noopener noreferrer">GDACS</a>`
                    : ''
            }`;

            L.circleMarker([st.lat, st.lon], {
                radius: 11,
                color: '#1e293b',
                weight: 2,
                fillColor: col,
                fillOpacity: 0.95,
            })
                .bindPopup(pop)
                .addTo(group);
        }
    }

    group.addTo(map);

    try {
        if (storms.length) {
            map.fitBounds(group.getBounds().pad(0.12));
        } else {
            map.setView([ref.lat, ref.lon], 5);
        }
    } catch {
        map.setView([ref.lat, ref.lon], 5);
    }

    requestAnimationFrame(() => {
        map.invalidateSize();
    });
}
