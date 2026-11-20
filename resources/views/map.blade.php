<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPS Tracking & Route Visualization Demo</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --border: #334155; --text: #f8fafc; --muted: #94a3b8; --accent: #38bdf8; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, sans-serif; background: var(--bg); color: var(--text); padding: 20px 16px; }
        .wrap { max-width: 1180px; margin: 0 auto; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        .topbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; }
        .kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .kpi-val { font-size: 1.6rem; font-weight: 700; color: var(--accent); }
        .kpi-lbl { font-size: 0.82rem; color: var(--muted); }
        .maps-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; }
        @media (max-width: 900px) { .maps-grid { grid-template-columns: 1fr; } }
        #leaflet-map { height: 420px; border-radius: 8px; border: 1px solid var(--border); background: #0b1220; }
        #vector-canvas { width: 100%; height: 420px; border-radius: 8px; border: 1px solid var(--border); background: #0b1220; }
        .controls { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 12px; }
        button { padding: 8px 14px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; background: var(--accent); color: #0f172a; }
        button.sec { background: #334155; color: var(--text); }
        .legend { display: flex; flex-wrap: wrap; gap: 14px; font-size: 0.85rem; color: var(--muted); margin-top: 10px; }
        .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid var(--border); font-size: 0.85rem; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card topbar">
        <div>
            <h1 style="margin:0;">GPS Tracking & Route Visualization Demo</h1>
            <div style="color:var(--muted); font-size:0.9rem;">Unit: <strong>{{ $session->unit_code }}</strong> &bull; Route: <strong>{{ $session->route->name }}</strong> (Limit {{ $session->route->speed_limit_kmh }} km/h)</div>
        </div>
        <form method="POST" action="{{ route('map.simulate') }}">
            @csrf
            <button type="submit">Generate New Synthetic Route</button>
        </form>
    </div>

    <div class="kpis">
        <div class="card"><div class="kpi-lbl">Distance (Haversine)</div><div class="kpi-val" id="kpi-distance">{{ number_format($session->total_distance_km, 3) }} km</div></div>
        <div class="card"><div class="kpi-lbl">Valid Pings</div><div class="kpi-val" id="kpi-pings">{{ $session->pings->count() }}</div></div>
        <div class="card"><div class="kpi-lbl">Detected Stops</div><div class="kpi-val" id="kpi-stops">{{ $session->stops_count }}</div></div>
        <div class="card"><div class="kpi-lbl">Speed Alerts</div><div class="kpi-val" id="kpi-alerts" style="color:#f87171;">{{ $session->speed_alerts_count }}</div></div>
        <div class="card"><div class="kpi-lbl">Filtered GPS Noise</div><div class="kpi-val" id="kpi-noise" style="color:#fbbf24;">{{ $session->filtered_noise_count }}</div></div>
    </div>

    <div class="card">
        <div class="maps-grid">
            <div>
                <div style="margin-bottom:6px; font-weight:600;">Interactive Map (OpenStreetMap / Leaflet)</div>
                <div id="leaflet-map"></div>
            </div>
            <div>
                <div style="margin-bottom:6px; font-weight:600;">Telemetry Vector Telemetry Overlay (Always Available)</div>
                <svg id="vector-canvas" viewBox="0 0 400 360"></svg>
            </div>
        </div>

        <div class="controls">
            <button type="button" id="btn-play">Play Replay</button>
            <button type="button" id="btn-step" class="sec">Next Point</button>
            <button type="button" id="btn-reset" class="sec">Reset Replay</button>
            <button type="button" id="btn-live-ping">Send Live GPS Ping</button>
            <span id="replay-status" style="color:var(--muted); font-size:0.9rem;"></span>
        </div>

        <div class="legend">
            <span><span class="dot" style="background:#38bdf8;"></span>Planned Route</span>
            <span><span class="dot" style="background:#10b981;"></span>Actual Trace</span>
            <span><span class="dot" style="background:#f59e0b;"></span>Detected Stop</span>
            <span><span class="dot" style="background:#ef4444;"></span>Speed Alert</span>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Processed Telemetry Points</h2>
        <table>
            <thead>
                <tr><th>#</th><th>Lat / Lng</th><th>Speed</th><th>Accuracy</th><th>Deviation</th><th>Flags</th></tr>
            </thead>
            <tbody id="pings-tbody"></tbody>
        </table>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
const SESSION_ID = @json($session->id);
const PLANNED = @json($session->route->planned_polyline_json);
let pings = @json($session->pings);
let replayIndex = pings.length;
let timer = null;
let map = null;
let actualLayer = null;
let unitMarker = null;

function initLeaflet() {
  if (typeof L === 'undefined') return;
  const center = PLANNED.length ? [PLANNED[0].lat, PLANNED[0].lng] : [13.6929, -89.2182];
  map = L.map('leaflet-map').setView(center, 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  if (PLANNED.length) {
    L.polyline(PLANNED.map(p => [p.lat, p.lng]), {
      color: '#38bdf8', weight: 4, dashArray: '8, 6'
    }).addTo(map);
  }
  actualLayer = L.layerGroup().addTo(map);
}

function renderVectorSvg(visiblePings) {
  const svg = document.getElementById('vector-canvas');
  svg.textContent = '';

  const allCoords = [
    ...PLANNED.map(p => ({ lat: p.lat, lng: p.lng })),
    ...pings.map(p => ({ lat: p.latitude, lng: p.longitude }))
  ];
  if (!allCoords.length) return;

  const lats = allCoords.map(c => c.lat);
  const lngs = allCoords.map(c => c.lng);
  const minLat = Math.min(...lats), maxLat = Math.max(...lats);
  const minLng = Math.min(...lngs), maxLng = Math.max(...lngs);
  const pad = 32;
  const w = 400 - pad * 2, h = 360 - pad * 2;
  const spanLat = Math.max(0.0005, maxLat - minLat);
  const spanLng = Math.max(0.0005, maxLng - minLng);

  const project = (lat, lng) => ({
    x: pad + ((lng - minLng) / spanLng) * w,
    y: pad + (1 - (lat - minLat) / spanLat) * h
  });

  const ns = 'http://www.w3.org/2000/svg';
  if (PLANNED.length > 1) {
    const poly = document.createElementNS(ns, 'polyline');
    poly.setAttribute('fill', 'none');
    poly.setAttribute('stroke', '#38bdf8');
    poly.setAttribute('stroke-width', '3');
    poly.setAttribute('stroke-dasharray', '6 5');
    poly.setAttribute('points', PLANNED.map(p => {
      const pt = project(p.lat, p.lng);
      return `${pt.x.toFixed(1)},${pt.y.toFixed(1)}`;
    }).join(' '));
    svg.appendChild(poly);
  }

  if (visiblePings.length > 1) {
    const trace = document.createElementNS(ns, 'polyline');
    trace.setAttribute('fill', 'none');
    trace.setAttribute('stroke', '#10b981');
    trace.setAttribute('stroke-width', '4');
    trace.setAttribute('points', visiblePings.map(p => {
      const pt = project(p.latitude, p.longitude);
      return `${pt.x.toFixed(1)},${pt.y.toFixed(1)}`;
    }).join(' '));
    svg.appendChild(trace);
  }

  visiblePings.forEach((p, idx) => {
    const pt = project(p.latitude, p.longitude);
    const circle = document.createElementNS(ns, 'circle');
    circle.setAttribute('cx', pt.x.toFixed(1));
    circle.setAttribute('cy', pt.y.toFixed(1));
    circle.setAttribute('r', idx === visiblePings.length - 1 ? '7' : '5');
    const color = p.is_speed_alert ? '#ef4444' : (p.is_stop ? '#f59e0b' : '#10b981');
    circle.setAttribute('fill', color);
    circle.setAttribute('stroke', '#0f172a');
    circle.setAttribute('stroke-width', '1.5');
    svg.appendChild(circle);
  });
}

function renderAll() {
  const visible = pings.slice(0, replayIndex);
  renderVectorSvg(visible);

  if (map && actualLayer) {
    actualLayer.clearLayers();
    if (visible.length > 1) {
      L.polyline(visible.map(p => [p.latitude, p.longitude]), { color: '#10b981', weight: 4 }).addTo(actualLayer);
    }
    visible.forEach((p) => {
      const color = p.is_speed_alert ? '#ef4444' : (p.is_stop ? '#f59e0b' : '#10b981');
      L.circleMarker([p.latitude, p.longitude], { radius: 6, color, fillColor: color, fillOpacity: 0.9 })
        .bindPopup(`Speed: ${p.speed_kmh} km/h | Acc: ${p.accuracy_meters}m`)
        .addTo(actualLayer);
    });
    if (visible.length) {
      const last = visible[visible.length - 1];
      if (!unitMarker) {
        unitMarker = L.circleMarker([last.latitude, last.longitude], { radius: 9, color: '#fff', fillColor: '#38bdf8', fillOpacity: 1 }).addTo(map);
      } else {
        unitMarker.setLatLng([last.latitude, last.longitude]);
      }
    }
  }

  const tbody = document.getElementById('pings-tbody');
  tbody.textContent = '';
  visible.forEach((p, i) => {
    const tr = document.createElement('tr');
    const flags = [p.is_stop ? 'STOP' : null, p.is_speed_alert ? 'SPEED_ALERT' : null].filter(Boolean).join(', ') || 'OK';
    tr.innerHTML = `<td>${i + 1}</td><td>${p.latitude.toFixed(4)}, ${p.longitude.toFixed(4)}</td><td>${p.speed_kmh} km/h</td><td>${p.accuracy_meters} m</td><td>${p.route_deviation_meters} m</td><td>${flags}</td>`;
    tbody.appendChild(tr);
  });

  document.getElementById('replay-status').textContent = `Showing ${visible.length} / ${pings.length} processed points`;
}

document.getElementById('btn-play').addEventListener('click', () => {
  if (timer) {
    clearInterval(timer);
    timer = null;
    document.getElementById('btn-play').textContent = 'Play Replay';
    return;
  }
  if (replayIndex >= pings.length) replayIndex = 1;
  document.getElementById('btn-play').textContent = 'Pause Replay';
  timer = setInterval(() => {
    if (replayIndex < pings.length) {
      replayIndex++;
      renderAll();
    } else {
      clearInterval(timer);
      timer = null;
      document.getElementById('btn-play').textContent = 'Play Replay';
    }
  }, 500);
});

document.getElementById('btn-step').addEventListener('click', () => {
  if (replayIndex < pings.length) {
    replayIndex++;
    renderAll();
  }
});

document.getElementById('btn-reset').addEventListener('click', () => {
  if (timer) { clearInterval(timer); timer = null; document.getElementById('btn-play').textContent = 'Play Replay'; }
  replayIndex = pings.length ? 1 : 0;
  renderAll();
});

document.getElementById('btn-live-ping').addEventListener('click', async () => {
  const last = pings[pings.length - 1] || { latitude: 13.7060, longitude: -89.1985 };
  const nextLat = Number((last.latitude + 0.0022).toFixed(6));
  const nextLng = Number((last.longitude + 0.0028).toFixed(6));
  const res = await fetch(`/api/v1/sessions/${SESSION_ID}/pings`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      pings: [{
        latitude: nextLat,
        longitude: nextLng,
        speed_kmh: 64,
        accuracy_meters: 7.5,
        heading: 52,
        recorded_at: new Date().toISOString()
      }]
    })
  });
  const body = await res.json();
  pings = body.session.pings;
  replayIndex = pings.length;
  document.getElementById('kpi-distance').textContent = `${Number(body.summary.total_distance_km).toFixed(3)} km`;
  document.getElementById('kpi-pings').textContent = String(pings.length);
  document.getElementById('kpi-stops').textContent = String(body.summary.stops_count);
  document.getElementById('kpi-alerts').textContent = String(body.summary.speed_alerts_count);
  renderAll();
});

initLeaflet();
renderAll();
</script>
</body>
</html>
