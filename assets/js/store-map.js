/*
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Store & service map.
// Enhances store-locations.php with an interactive Leaflet/OpenStreetMap map. Progressive
// enhancement: if JavaScript, Leaflet, the container, or the tiles are unavailable, the always-
// visible text address beside the map remains fully usable. Location data is read from data-*
// attributes on #customcore-map so no inline script or server HTML injection is needed.

(function () {
  'use strict';

  /**
   * Build the marker popup from location fields using DOM nodes (values are set
   * via textContent so they are treated as text, never HTML).
   *
   * @param {{name?: string, street?: string, locality?: string, phone?: string
   * phoneHref?: string, email?: string}} data Location details from data-* attrs.
   * @returns {HTMLDivElement} The popup content element.
   */
  function buildPopup(data) {
    // Build popup content with DOM nodes so values are inserted as text, never HTML.
    var wrap = document.createElement('div');
    wrap.className = 'location-popup';

    if (data.name) {
      var name = document.createElement('strong');
      name.textContent = data.name;
      wrap.appendChild(name);
      wrap.appendChild(document.createElement('br'));
    }

    if (data.street) {
      wrap.appendChild(document.createTextNode(data.street));
      wrap.appendChild(document.createElement('br'));
    }

    if (data.locality) {
      wrap.appendChild(document.createTextNode(data.locality));
      wrap.appendChild(document.createElement('br'));
    }

    if (data.phone) {
      var phone;
      if (data.phoneHref) {
        phone = document.createElement('a');
        phone.href = 'tel:' + data.phoneHref;
        phone.textContent = data.phone;
      } else {
        phone = document.createTextNode(data.phone);
      }
      wrap.appendChild(phone);
      wrap.appendChild(document.createElement('br'));
    }

    if (data.email) {
      var email = document.createElement('a');
      email.href = 'mailto:' + data.email;
      email.textContent = data.email;
      wrap.appendChild(email);
    }

    return wrap;
  }

  /**
   * Initialise the Leaflet map from #customcore-map data-* attributes and drop a
   * marker with the store popup. No-op if the container, Leaflet, or valid
   * coordinates are missing, leaving the text address as the fallback.
   *
   * @returns {void}
   */
  function initMap() {
    var el = document.getElementById('customcore-map');
    if (!el || typeof L === 'undefined') {
      return;
    }

    var lat = parseFloat(el.getAttribute('data-lat'));
    var lng = parseFloat(el.getAttribute('data-lng'));
    if (isNaN(lat) || isNaN(lng)) {
      return;
    }

    var zoom = parseInt(el.getAttribute('data-zoom'), 10);
    if (isNaN(zoom)) {
      zoom = 14;
    }

    var coords = [lat, lng];
    var map = L.map(el, {
      scrollWheelZoom: false
    }).setView(coords, zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var popup = buildPopup({
      name: el.getAttribute('data-name'),
      street: el.getAttribute('data-street'),
      locality: el.getAttribute('data-locality'),
      phone: el.getAttribute('data-phone'),
      phoneHref: el.getAttribute('data-phone-href'),
      email: el.getAttribute('data-email')
    });

    L.marker(coords).addTo(map)
      .bindPopup(popup)
      .openPopup();

    // Keep keyboard users from being trapped: allow wheel zoom only once focused.
    map.on('focus', function () {
      map.scrollWheelZoom.enable();
    });
    map.on('blur', function () {
      map.scrollWheelZoom.disable();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMap);
  } else {
    initMap();
  }
})();
