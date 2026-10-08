(function () {
    'use strict';

    var el = document.getElementById('tripMap');
    if (!el || !window.mapboxgl) return;

    var pins = [];
    try {
        pins = JSON.parse(el.getAttribute('data-pins') || '[]');
    } catch (e) {
        pins = [];
    }
    if (!pins.length) return;

    var focusId = parseInt(el.getAttribute('data-focus') || '0', 10);
    var mapboxToken = el.getAttribute('data-mapbox-token') || '';
    var mapillaryToken = el.getAttribute('data-mapillary-token') || '';

    if (!mapboxToken) {
        el.innerHTML = '<p class="hint" style="padding:1.5rem">Mapbox token missing. Add MAPBOX_ACCESS_TOKEN to your .env file.</p>';
        return;
    }

    mapboxgl.accessToken = mapboxToken;

    var listEl = document.getElementById('tripMapList');
    var actionsEl = document.getElementById('tripMapActions');
    var selectedLabel = document.getElementById('tripMapSelectedLabel');
    var addressLabel = document.getElementById('tripMapAddressLabel');
    var locStatus = document.getElementById('locStatus');
    var streetViewBtn = document.getElementById('tripStreetViewBtn');
    var splitEl = document.getElementById('tripMapSplit');
    var streetPanel = document.getElementById('tripStreetPanel');
    var streetClose = document.getElementById('tripStreetClose');
    var streetFrame = document.getElementById('tripStreetFrame');
    var mapillaryViewerEl = document.getElementById('tripMapillaryViewer');
    var fitRouteBtn = document.getElementById('tripMapFitRoute');

    var STYLES = {
        satellite: 'mapbox://styles/mapbox/satellite-streets-v12',
        streets: 'mapbox://styles/mapbox/streets-v12',
    };

    var ROUTE_SOURCE = 'trip-route';
    var USER_SOURCE = 'trip-user-accuracy';
    var activeLayer = 'satellite';
    var map;
    var bookingMarkers = {};
    var userMarker = null;
    var selectedId = null;
    var userPos = null;
    var streetOpen = false;
    var currentPin = null;
    var mlyViewer = null;
    var lastAccuracy = 40;
    var geocodeCache = {};

    map = new mapboxgl.Map({
        container: 'tripMap',
        style: STYLES.satellite,
        center: [123.8854, 10.3157],
        zoom: 11,
        maxZoom: 22,
        minZoom: 8,
        antialias: true,
        pitch: 0,
        bearing: 0,
    });

    map.addControl(new mapboxgl.NavigationControl({ visualizePitch: true }), 'bottom-right');

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function findPin(id) {
        for (var i = 0; i < pins.length; i++) {
            if (pins[i].id === id) return pins[i];
        }
        return null;
    }

    function bookingMarkerElement(upcoming, title) {
        var wrap = document.createElement('div');
        wrap.className = 'trip-pin-wrap';
        wrap.innerHTML = '<div class="trip-pin-label">' + escapeHtml(title) + '</div>'
            + '<div class="trip-pin' + (upcoming ? ' trip-pin-upcoming' : '') + '"><span></span></div>';
        return wrap;
    }

    function userMarkerElement() {
        var wrap = document.createElement('div');
        wrap.className = 'trip-user-label-wrap';
        wrap.innerHTML = '<span class="trip-user-label">You</span>'
            + '<span class="trip-user-dot"></span>';
        return wrap;
    }

    function addMapillaryLayers() {
        if (!mapillaryToken || map.getSource('mapillary')) return;

        map.addSource('mapillary', {
            type: 'vector',
            tiles: [
                'https://tiles.mapillary.com/maps/vtp/mly1_public/2/{z}/{x}/{y}?access_token='
                + encodeURIComponent(mapillaryToken),
            ],
            minzoom: 0,
            maxzoom: 14,
        });

        map.addLayer({
            id: 'mly-sequences',
            type: 'line',
            source: 'mapillary',
            'source-layer': 'sequence',
            minzoom: 10,
            paint: {
                'line-color': '#05cb63',
                'line-width': 3,
                'line-opacity': 0.9,
            },
        });

        map.addLayer({
            id: 'mly-images',
            type: 'circle',
            source: 'mapillary',
            'source-layer': 'image',
            minzoom: 10,
            paint: {
                'circle-radius': ['interpolate', ['linear'], ['zoom'], 10, 4, 16, 8, 20, 10],
                'circle-color': '#05cb63',
                'circle-stroke-color': '#ffffff',
                'circle-stroke-width': 2,
            },
        });

        map.on('click', 'mly-images', function (e) {
            if (!e.features || !e.features.length) return;
            var imageId = e.features[0].properties && e.features[0].properties.id;
            if (!imageId) return;
            openMapillaryViewer(String(imageId));
            setStreetOpen(true);
        });
        map.on('mouseenter', 'mly-images', function () { map.getCanvas().style.cursor = 'pointer'; });
        map.on('mouseleave', 'mly-images', function () { map.getCanvas().style.cursor = ''; });
    }

    function setupOverlaySources() {
        addMapillaryLayers();

        if (!map.getSource(USER_SOURCE)) {
            map.addSource(USER_SOURCE, {
                type: 'geojson',
                data: { type: 'FeatureCollection', features: [] },
            });
            map.addLayer({
                id: 'trip-user-accuracy-fill',
                type: 'fill',
                source: USER_SOURCE,
                paint: {
                    'fill-color': '#2563eb',
                    'fill-opacity': 0.15,
                },
            });
        }

        if (!map.getSource(ROUTE_SOURCE)) {
            map.addSource(ROUTE_SOURCE, {
                type: 'geojson',
                data: { type: 'FeatureCollection', features: [] },
            });
            map.addLayer({
                id: 'trip-route-line',
                type: 'line',
                source: ROUTE_SOURCE,
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': '#0d9488',
                    'line-width': 6,
                    'line-opacity': 0.92,
                },
            });
        }
    }

    function circlePolygon(lng, lat, radiusMeters) {
        var coords = [];
        var earth = 6378137;
        for (var i = 0; i <= 64; i++) {
            var angle = (i / 64) * Math.PI * 2;
            var dx = radiusMeters * Math.cos(angle);
            var dy = radiusMeters * Math.sin(angle);
            var dLng = (dx / (earth * Math.cos(lat * Math.PI / 180))) * (180 / Math.PI);
            var dLat = (dy / earth) * (180 / Math.PI);
            coords.push([lng + dLng, lat + dLat]);
        }
        return {
            type: 'Feature',
            geometry: { type: 'Polygon', coordinates: [coords] },
            properties: {},
        };
    }

    function renderBookingMarkers() {
        Object.keys(bookingMarkers).forEach(function (id) {
            bookingMarkers[id].remove();
        });
        bookingMarkers = {};

        pins.forEach(function (p) {
            var marker = new mapboxgl.Marker({ element: bookingMarkerElement(p.upcoming, p.title), anchor: 'bottom' })
                .setLngLat([p.lng, p.lat])
                .addTo(map);
            marker.getElement().addEventListener('click', function (ev) {
                ev.stopPropagation();
                selectBooking(p.id);
            });
            bookingMarkers[p.id] = marker;
        });
    }

    function updateBookingMarkerPosition(pin) {
        if (bookingMarkers[pin.id]) {
            bookingMarkers[pin.id].setLngLat([pin.lng, pin.lat]);
        }
    }

    function geocodeAddress(query) {
        if (geocodeCache[query]) {
            return Promise.resolve(geocodeCache[query]);
        }
        var url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/'
            + encodeURIComponent(query)
            + '.json?access_token=' + encodeURIComponent(mapboxToken)
            + '&country=ph&limit=1';

        return fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.features || !data.features.length) return null;
                var f = data.features[0];
                var result = {
                    lng: f.center[0],
                    lat: f.center[1],
                    place: f.place_name || query,
                };
                geocodeCache[query] = result;
                return result;
            })
            .catch(function () { return null; });
    }

    function resolvePinLocation(pin) {
        return geocodeAddress(pin.googleQuery || pin.title).then(function (result) {
            if (result) {
                pin.lat = result.lat;
                pin.lng = result.lng;
                pin.resolvedAddress = result.place;
                pin.approximate = false;
                updateBookingMarkerPosition(pin);
            }
            return pin;
        });
    }

    function googleStreetEmbed(lat, lng) {
        return 'https://www.google.com/maps?output=svembed&layer=c'
            + '&cbll=' + encodeURIComponent(lat + ',' + lng)
            + '&cbp=0,90,0,0,0';
    }

    function fetchMapillaryImage(pin) {
        if (!mapillaryToken || !pin) return Promise.resolve(null);
        var url = 'https://graph.mapillary.com/images?access_token='
            + encodeURIComponent(mapillaryToken)
            + '&fields=id&limit=1&radius=50&lat=' + encodeURIComponent(String(pin.lat))
            + '&lng=' + encodeURIComponent(String(pin.lng));
        return fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var images = data && data.data ? data.data : [];
                return images.length ? String(images[0].id) : null;
            })
            .catch(function () { return null; });
    }

    function ensureMapillaryViewer() {
        if (!mapillaryToken || !window.mapillary || !mapillaryViewerEl) return null;
        if (mlyViewer) return mlyViewer;
        mlyViewer = new mapillary.Viewer({
            accessToken: mapillaryToken,
            container: mapillaryViewerEl,
            component: { cover: false, direction: true, sequence: true, zoom: true },
        });
        return mlyViewer;
    }

    function openMapillaryViewer(imageId) {
        var viewer = ensureMapillaryViewer();
        if (!viewer) return false;
        if (streetFrame) streetFrame.hidden = true;
        if (mapillaryViewerEl) mapillaryViewerEl.hidden = false;
        viewer.moveTo(imageId).catch(function () {
            if (currentPin) loadGoogleStreetView(currentPin);
        });
        return true;
    }

    function loadGoogleStreetView(pin) {
        if (streetFrame) {
            streetFrame.src = googleStreetEmbed(pin.lat, pin.lng);
            streetFrame.hidden = false;
        }
        if (mapillaryViewerEl) mapillaryViewerEl.hidden = true;
    }

    function loadStreetView(pin) {
        if (!pin) return;
        fetchMapillaryImage(pin).then(function (imageId) {
            if (imageId && openMapillaryViewer(imageId)) return;
            loadGoogleStreetView(pin);
        });
    }

    function setStreetOpen(open) {
        streetOpen = open;
        if (splitEl) splitEl.classList.toggle('has-street', open);
        if (streetPanel) streetPanel.hidden = !open;
        if (streetViewBtn) streetViewBtn.classList.toggle('is-active', open);
        if (open && currentPin) loadStreetView(currentPin);
        setTimeout(function () {
            map.resize();
            if (mlyViewer && open) {
                try { mlyViewer.resize(); } catch (e) { /* ignore */ }
            }
        }, 200);
    }

    function fitMapToMarkers(pin) {
        if (!pin) return;
        if (userPos) {
            var bounds = new mapboxgl.LngLatBounds();
            bounds.extend([userPos.lng, userPos.lat]);
            bounds.extend([pin.lng, pin.lat]);
            map.fitBounds(bounds, { padding: 80, maxZoom: 17, duration: 900 });
        } else {
            map.flyTo({ center: [pin.lng, pin.lat], zoom: 16, duration: 900 });
        }
    }

    function drawRoute(pin) {
        var source = map.getSource(ROUTE_SOURCE);
        if (!source) return;

        if (!userPos || !pin) {
            source.setData({ type: 'FeatureCollection', features: [] });
            return;
        }

        var url = 'https://api.mapbox.com/directions/v5/mapbox/driving/'
            + userPos.lng + ',' + userPos.lat + ';' + pin.lng + ',' + pin.lat
            + '?geometries=geojson&overview=full&access_token=' + encodeURIComponent(mapboxToken);

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.routes || !data.routes[0]) {
                    source.setData({ type: 'FeatureCollection', features: [] });
                    return;
                }
                source.setData({
                    type: 'Feature',
                    geometry: data.routes[0].geometry,
                    properties: {},
                });
            })
            .catch(function () {
                source.setData({ type: 'FeatureCollection', features: [] });
            });
    }

    function setUserLocation(lat, lng, accuracy) {
        userPos = { lat: lat, lng: lng };
        lastAccuracy = accuracy || 40;

        if (!userMarker) {
            userMarker = new mapboxgl.Marker({ element: userMarkerElement(), anchor: 'center' })
                .setLngLat([lng, lat])
                .addTo(map);
        } else {
            userMarker.setLngLat([lng, lat]);
        }

        var accSource = map.getSource(USER_SOURCE);
        if (accSource) {
            accSource.setData({
                type: 'FeatureCollection',
                features: [circlePolygon(lng, lat, Math.min(Math.max(accuracy || 40, 25), 200))],
            });
        }

        if (locStatus) locStatus.textContent = 'Location on';

        if (selectedId) {
            var pin = findPin(selectedId);
            if (pin) {
                drawRoute(pin);
                fitMapToMarkers(pin);
            }
        }
    }

    function selectBooking(id) {
        selectedId = id;
        if (listEl) {
            listEl.querySelectorAll('.trip-map-item').forEach(function (item) {
                item.classList.toggle('is-active', parseInt(item.getAttribute('data-booking-id'), 10) === id);
            });
        }

        var pin = findPin(id);
        if (!pin) return;

        currentPin = pin;
        if (actionsEl) actionsEl.hidden = false;
        if (selectedLabel) selectedLabel.textContent = pin.title + ' — ' + pin.dateLabel;
        if (addressLabel) {
            addressLabel.textContent = pin.address || pin.area || '';
        }

        resolvePinLocation(pin).then(function (resolved) {
            currentPin = resolved;
            if (addressLabel && resolved.resolvedAddress) {
                addressLabel.textContent = resolved.resolvedAddress;
            } else if (addressLabel) {
                addressLabel.textContent = resolved.address || resolved.area || '';
            }
            fitMapToMarkers(resolved);
            drawRoute(resolved);
            if (streetOpen) loadStreetView(resolved);
        });
    }

    function startGeolocation() {
        if (!navigator.geolocation) {
            if (locStatus) locStatus.textContent = 'GPS not supported';
            return;
        }
        if (locStatus) locStatus.textContent = 'Locating…';

        navigator.geolocation.watchPosition(
            function (pos) {
                setUserLocation(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
            },
            function () {
                if (locStatus) locStatus.textContent = 'Location off — allow GPS in browser';
            },
            { enableHighAccuracy: true, maximumAge: 10000, timeout: 15000 }
        );
    }

    function refreshAfterStyleChange() {
        setupOverlaySources();
        renderBookingMarkers();
        if (userPos) setUserLocation(userPos.lat, userPos.lng, lastAccuracy);
        if (selectedId) {
            var pin = findPin(selectedId);
            if (pin) drawRoute(pin);
        }
    }

    var layerBtns = document.querySelectorAll('.map-layer-btn[data-layer]');
    layerBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var layer = btn.getAttribute('data-layer');
            if (layer === activeLayer) return;
            activeLayer = layer;
            layerBtns.forEach(function (b) { b.classList.toggle('is-active', b === btn); });
            map.setStyle(STYLES[layer] || STYLES.satellite);
            map.once('style.load', refreshAfterStyleChange);
        });
    });

    if (listEl) {
        listEl.addEventListener('click', function (e) {
            var item = e.target.closest('.trip-map-item');
            if (!item) return;
            selectBooking(parseInt(item.getAttribute('data-booking-id'), 10));
        });
    }

    if (streetViewBtn) {
        streetViewBtn.addEventListener('click', function () {
            if (!currentPin) return;
            setStreetOpen(!streetOpen);
        });
    }

    if (streetClose) {
        streetClose.addEventListener('click', function () { setStreetOpen(false); });
    }

    if (fitRouteBtn) {
        fitRouteBtn.addEventListener('click', function () {
            if (currentPin) fitMapToMarkers(currentPin);
            else if (userPos) map.flyTo({ center: [userPos.lng, userPos.lat], zoom: 14 });
            else startGeolocation();
        });
    }

    map.on('load', function () {
        setupOverlaySources();
        renderBookingMarkers();
        startGeolocation();

        if (focusId && findPin(focusId)) selectBooking(focusId);
        else {
            var firstUpcoming = pins.find(function (p) { return p.upcoming; });
            if (firstUpcoming) selectBooking(firstUpcoming.id);
            else if (pins.length) selectBooking(pins[0].id);
        }
    });
})();
