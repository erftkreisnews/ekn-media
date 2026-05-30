{{-- OSM/Nominatim: Füllt Land, Bundesland, Stadt, Straße (Alpine x-model auf #section-publication) --}}
@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <style>
        /* Leaflet: verhindert schmale „Streifen“-Darstellung bei Grid/Flex */
        #publication-osm-map.leaflet-container {
            width: 100% !important;
            min-height: 280px;
            height: 360px;
        }
        @media (min-width: 1024px) {
            #publication-osm-map.leaflet-container {
                height: min(400px, 50vh);
                min-height: 300px;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
        (function () {
            function applyFields(fields) {
                var root = document.getElementById('section-publication');
                if (!root || !window.Alpine) return;
                var d = Alpine.$data(root);
                if (!d) return;
                if (fields.country) d.country = fields.country;
                if (fields.federal_state) d.federal_state = fields.federal_state;
                if (fields.city) d.city = fields.city;
                if (fields.street) d.street = fields.street;
                if (fields.region) d.region = fields.region;
            }

            function debounce(fn, ms) {
                var t;
                return function () {
                    var a = arguments;
                    clearTimeout(t);
                    t = setTimeout(function () { fn.apply(null, a); }, ms);
                };
            }

            function syncCoords(lat, lon) {
                var latIn = document.getElementById('publication-latitude');
                var lonIn = document.getElementById('publication-longitude');
                if (latIn) latIn.value = String(lat);
                if (lonIn) lonIn.value = String(lon);
            }

            function init() {
                if (typeof L === 'undefined') return;
                var mapEl = document.getElementById('publication-osm-map');
                var searchInput = document.getElementById('publication-osm-search');
                var suggestEl = document.getElementById('publication-osm-suggest');
                var hintEl = document.getElementById('publication-osm-hint');
                if (!mapEl || !searchInput) return;

                // Doppel-Init (z. B. durch erneutes Skript) zerstört die Karte → Marker „springt“ zum Standard
                if (mapEl._leaflet_id) {
                    return;
                }

                var searchUrl = @json(route('admin.geocoding.search'));
                var reverseUrl = @json(route('admin.geocoding.reverse'));

                var latIn = document.getElementById('publication-latitude');
                var lonIn = document.getElementById('publication-longitude');
                var rawLat = latIn && latIn.value ? parseFloat(latIn.value) : NaN;
                var rawLon = lonIn && lonIn.value ? parseFloat(lonIn.value) : NaN;
                var hasSaved = !isNaN(rawLat) && !isNaN(rawLon) && rawLat >= -90 && rawLat <= 90 && rawLon >= -180 && rawLon <= 180;

                var map = L.map(mapEl, { scrollWheelZoom: false });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                }).addTo(map);

                var startLat = hasSaved ? rawLat : 50.8;
                var startLon = hasSaved ? rawLon : 6.8;
                var startZoom = hasSaved ? 15 : 10;
                map.setView([startLat, startLon], startZoom);

                var marker = L.marker([startLat, startLon], { draggable: true }).addTo(map);
                if (hasSaved) {
                    syncCoords(startLat, startLon);
                }

                function showHint(text, isError) {
                    if (!hintEl) return;
                    hintEl.textContent = text || '';
                    hintEl.classList.toggle('text-red-600', !!isError);
                    hintEl.classList.toggle('text-gray-500', !isError);
                }

                function placeAt(lat, lon, fields) {
                    syncCoords(lat, lon);
                    var ll = [lat, lon];
                    marker.setLatLng(ll);
                    map.setView(ll, Math.max(map.getZoom(), 15));
                    if (fields) applyFields(fields);
                }

                function fetchReverse(lat, lon) {
                    showHint('Lade Adresse …', false);
                    var u = reverseUrl + '?lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lon);
                    fetch(u, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.error) {
                                showHint(data.error, true);
                                return;
                            }
                            if (data.fields) {
                                applyFields(data.fields);
                                showHint(data.display_name ? String(data.display_name).slice(0, 120) : 'Übernommen.', false);
                            }
                        })
                        .catch(function () {
                            showHint('Adresse konnte nicht geladen werden.', true);
                        });
                }

                marker.on('dragend', function () {
                    var p = marker.getLatLng();
                    syncCoords(p.lat, p.lng);
                    fetchReverse(p.lat, p.lng);
                });

                map.on('click', function (e) {
                    syncCoords(e.latlng.lat, e.latlng.lng);
                    marker.setLatLng(e.latlng);
                    fetchReverse(e.latlng.lat, e.latlng.lng);
                });

                var invalidateTimer = null;
                function invalidateMap() {
                    if (invalidateTimer) {
                        clearTimeout(invalidateTimer);
                    }
                    invalidateTimer = setTimeout(function () {
                        invalidateTimer = null;
                        try {
                            map.invalidateSize({ animate: false, pan: false });
                        } catch (e) {}
                    }, 150);
                }

                setTimeout(invalidateMap, 0);
                setTimeout(invalidateMap, 400);
                window.addEventListener('resize', invalidateMap);
                if (typeof ResizeObserver !== 'undefined') {
                    new ResizeObserver(invalidateMap).observe(mapEl);
                }
                if ('IntersectionObserver' in window) {
                    new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting) {
                                invalidateMap();
                            }
                        });
                    }, { threshold: 0.05 }).observe(mapEl);
                }

                function renderSuggest(items) {
                    if (!suggestEl) return;
                    suggestEl.innerHTML = '';
                    if (!items || !items.length) {
                        suggestEl.classList.add('hidden');
                        return;
                    }
                    suggestEl.classList.remove('hidden');
                    items.forEach(function (item) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'w-full text-left px-3 py-2 text-sm hover:bg-gray-50 border-b border-gray-100 last:border-0';
                        btn.textContent = item.display_name || (item.lat + ',' + item.lon);
                        btn.addEventListener('click', function () {
                            var lat = parseFloat(item.lat);
                            var lon = parseFloat(item.lon);
                            if (item.fields) applyFields(item.fields);
                            placeAt(lat, lon, null);
                            suggestEl.classList.add('hidden');
                            searchInput.value = '';
                            showHint('Auswahl übernommen. Marker bei Bedarf verschieben.', false);
                        });
                        suggestEl.appendChild(btn);
                    });
                }

                var runSearch = debounce(function () {
                    var q = (searchInput.value || '').trim();
                    if (q.length < 2) {
                        renderSuggest([]);
                        return;
                    }
                    showHint('Suche …', false);
                    fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.error) {
                                showHint(data.error, true);
                                renderSuggest([]);
                                return;
                            }
                            renderSuggest(data.results || []);
                            showHint((data.results && data.results.length) ? 'Treffer antippen oder Karte anklicken.' : 'Keine Treffer.', false);
                        })
                        .catch(function () {
                            showHint('Suche fehlgeschlagen.', true);
                            renderSuggest([]);
                        });
                }, 450);

                searchInput.addEventListener('input', runSearch);
                searchInput.addEventListener('focus', function () {
                    if ((searchInput.value || '').trim().length >= 2) runSearch();
                });
                document.addEventListener('click', function (e) {
                    if (suggestEl && !suggestEl.contains(e.target) && e.target !== searchInput) {
                        suggestEl.classList.add('hidden');
                    }
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>
@endpush

<div class="space-y-4 w-full min-w-0 max-w-none rounded-xl border border-gray-200 bg-gradient-to-b from-slate-50/90 to-white p-4 sm:p-5 shadow-sm ring-1 ring-gray-100/80">
    <div>
        <h3 class="text-sm font-semibold text-gray-900">Karte &amp; OSM-Suche</h3>
        <p class="text-xs text-gray-500 mt-1 leading-relaxed">
            Ort suchen oder in die Karte klicken / Marker ziehen – Land, Bundesland, Stadt und Straße werden aus OpenStreetMap übernommen (manuell anpassbar). Die Marker-Position wird als Koordinaten gespeichert; der Link in der Medienangebots-Mail öffnet Google Maps genau an diesem Punkt.
        </p>
    </div>
    <input type="hidden" name="latitude" id="publication-latitude" value="{{ old('latitude', $initialLatitude ?? null) ?? '' }}">
    <input type="hidden" name="longitude" id="publication-longitude" value="{{ old('longitude', $initialLongitude ?? null) ?? '' }}">
    @error('latitude')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
    @error('longitude')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
    <div class="relative z-30">
        <label for="publication-osm-search" class="block text-xs font-medium text-gray-600 mb-1">Ort suchen</label>
        <input
            type="search"
            id="publication-osm-search"
            autocomplete="off"
            placeholder="z. B. Bergheim Erft, Bahnhofstraße Köln"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48] text-sm"
        />
        <div
            id="publication-osm-suggest"
            class="hidden absolute left-0 right-0 mt-1 max-h-48 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg"
            role="listbox"
        ></div>
    </div>
    <div
        id="publication-osm-map"
        class="w-full rounded-lg border border-gray-200 z-10 overflow-hidden bg-gray-100"
        style="width: 100%; min-width: 200px;"
    ></div>
    <p id="publication-osm-hint" class="text-xs text-gray-500"></p>
    <p class="text-[10px] text-gray-400">
        © <a href="https://www.openstreetmap.org/copyright" class="underline hover:text-gray-600" target="_blank" rel="noopener">OpenStreetMap</a>-Mitwirkende · Daten via Nominatim (Nutzung nur im üblichen Rahmen, siehe Server-Konfiguration).
    </p>
</div>
