import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
}

export function initEarthquakeMap() {
    const el = document.getElementById('zc-earthquake-map');
    const dataEl = document.getElementById('zc-earthquake-data');
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
    const events = Array.isArray(data.events) ? data.events : [];
    const radiusKm = typeof data.radius_km === 'number' ? data.radius_km : 650;

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
        opacity: 0.35,
        fillOpacity: 0.03,
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

    for (const ev of events) {
        if (typeof ev.lat !== 'number' || typeof ev.lon !== 'number') {
            continue;
        }
        const mag = typeof ev.mag === 'number' ? ev.mag : 0;
        const r = Math.max(5, Math.min(20, 4 + mag * 2.5));
        const place = esc(ev.place || '');
        const when = esc(ev.time_label || '');
        const dist =
            typeof ev.distance_km === 'number' ? `${esc(String(ev.distance_km))} km` : '—';
        const depth =
            typeof ev.depth_km === 'number' ? `${esc(String(ev.depth_km))} km` : '—';
        const magStr = esc(mag.toFixed(1));
        const safeUrl =
            typeof ev.url === 'string' && ev.url.startsWith('http') ? ev.url : '';

        const body = `<strong>M${magStr}</strong><br>${place}<br><small>${when}</small><br><small>${dist} · ${depth} deep</small>${
            safeUrl
                ? `<br><a href="${safeUrl.replace(/&/g, '&amp;').replace(/"/g, '&quot;')}" target="_blank" rel="noopener noreferrer">USGS</a>`
                : ''
        }`;

        L.circleMarker([ev.lat, ev.lon], {
            radius: r,
            color: '#7f1d1d',
            weight: 1,
            fillColor: '#ef4444',
            fillOpacity: 0.88,
        })
            .bindPopup(body)
            .addTo(group);
    }

    group.addTo(map);

    try {
        if (events.length) {
            map.fitBounds(group.getBounds().pad(0.12));
        } else {
            map.setView([ref.lat, ref.lon], 6);
        }
    } catch {
        map.setView([ref.lat, ref.lon], 6);
    }

    requestAnimationFrame(() => {
        map.invalidateSize();
    });
}
