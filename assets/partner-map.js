// The partner institutions map, shared by the dashboard (index.html) and the
// embeddable map-only page (map.html). Both pages are built from templates in
// scripts/, and their markers come from build_inst_markers() in
// scripts/build_dashboard.py.
//
// Leaflet (window.L) is only touched inside init(), so the file can be loaded and
// popupHtml() tested without a browser: see scripts/test_partner_map.mjs.
(function (window) {
  'use strict';

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // One pin's popup: the city, then every institution in it. A name links to its
  // website, in a new tab, only when the build found an http(s) address for it.
  function popupHtml(marker) {
    const list = marker.institutions.map(function (inst) {
      const name = escapeHtml(inst.name);
      const label = /^https?:\/\//i.test(inst.url || '')
        ? '<a href="' + escapeHtml(inst.url) + '" target="_blank" rel="noopener noreferrer">' + name + '</a>'
        : name;
      return '<li>' + label + '</li>';
    }).join('');
    return '<h4>' + escapeHtml(marker.city) + ', ' + escapeHtml(marker.country) + '</h4><ul>' + list + '</ul>';
  }

  // Draws the map into `element` (an id or a node) and returns the Leaflet map.
  // With options.fitToMarkers the view fits the pins, so the same page works at
  // any embed size; otherwise it opens on the dashboard's fixed world view.
  function init(element, markers, options) {
    const L = window.L;
    const fitToMarkers = Boolean(options && options.fitToMarkers);

    // Page scrolling wins over the map: no wheel zoom, and on touch screens one
    // finger scrolls the page instead of panning the map (pinch still zooms).
    const map = L.map(element, {
      scrollWheelZoom: false,
      zoomControl: true,
      dragging: !L.Browser.mobile
    });

    // OpenStreetMap's standard tiles, which need no API key. CARTO's light_all
    // basemap used to serve anonymous tiles and now stamps "API KEY REQUIRED"
    // across every one of them, while still answering 200 with a valid PNG, so
    // nothing here failed loudly. Two details this URL must keep: no {s}, since
    // OSM serves from a single host now, and no {r}, since OSM has no @2x tiles
    // and a retina suffix would 404 on exactly the screens that asked for it.
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 19
    }).addTo(map);

    const wpIcon = L.divIcon({
      className: 'wp-marker',
      html: '<div style="background:#3858E9;width:28px;height:28px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;font-family:dashicons,sans-serif"></div>',
      iconSize: [28, 28],
      iconAnchor: [14, 14],
      popupAnchor: [0, -16]
    });

    const wpIconMulti = function (count) {
      return L.divIcon({
        className: 'wp-marker',
        html: '<div style="background:#3858E9;width:32px;height:32px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:14px">' + count + '</div>',
        iconSize: [32, 32],
        iconAnchor: [16, 16],
        popupAnchor: [0, -18]
      });
    };

    // Cluster nearby pins: partner cities are often only a few km apart (San José
    // and Cartago, or the Spanish cities), so at world zoom they would sit on top
    // of each other and hide one another. Clustering keeps every institution
    // reachable and shows partner density.
    const cluster = L.markerClusterGroup({ maxClusterRadius: 45, showCoverageOnHover: false });
    markers.forEach(function (m) {
      const icon = m.institutions.length > 1 ? wpIconMulti(m.institutions.length) : wpIcon;
      cluster.addLayer(L.marker([m.lat, m.lng], { icon: icon }).bindPopup(popupHtml(m), { maxWidth: 280 }));
    });
    map.addLayer(cluster);

    if (fitToMarkers && markers.length) {
      // 12px keeps the outermost pins clear of the frame while letting a 358px-wide
      // phone embed open one zoom level closer than 24px would.
      map.fitBounds(cluster.getBounds(), { padding: [12, 12], maxZoom: 4 });
    } else {
      map.setView([30, 10], 2);
    }
    return map;
  }

  window.WPCreditsPartnerMap = { init: init, popupHtml: popupHtml };
})(window);
