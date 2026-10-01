/**
 * Fallback de endereço para o cadastro (mapa OpenStreetMap, sem chaves).
 *
 * 1. Quando o Nominatim não retorna nada, exibe uma barra:
 *    "Não encontrou o endereço?" + [Buscar em base alternativa] [Usar pin do mapa].
 * 2. Base alternativa = Photon (photon.komoot.io, gratuito, sem chave),
 *    com viés para a região do mapa; ao escolher, preenche endereço,
 *    latitude/longitude e os campos cidade/estado/CEP/bairro.
 * 3. Pin manual: orienta arrastar o alfinete; ao detectar mudança de
 *    latitude/longitude, faz reverse-geocode (Nominatim) e preenche os campos.
 * 4. Não altera nenhum arquivo do tema pai.
 */
(function ($) {
    'use strict';

    var RIO_LAT = -22.9083;
    var RIO_LON = -43.1964;
    var MIN_CHARS = 4;
    var photonTimer = null;
    var pollTimer = null;
    var lastLat = null;
    var lastLng = null;
    var manualMode = false;

    function isOsmSubmitPage() {
        return $('#geocomplete').length > 0
            && $('#map_canvas').length > 0
            && typeof L !== 'undefined'
            && $('#latitude').length > 0
            && $('#longitude').length > 0;
    }

    function biasLatLng() {
        var lat = parseFloat($('#latitude').val());
        var lng = parseFloat($('#longitude').val());
        if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
            return { lat: lat, lng: lng };
        }
        var $map = $('#map_canvas');
        var mlat = parseFloat($map.data('add-lat'));
        var mlng = parseFloat($map.data('add-long'));
        if (!isNaN(mlat) && !isNaN(mlng) && mlat !== 0 && mlng !== 0) {
            return { lat: mlat, lng: mlng };
        }
        return { lat: RIO_LAT, lng: RIO_LON };
    }

    function refreshSelects() {
        var $sels = $('#city, #countyState, #neighborhood, #country');
        if ($sels.length && $.fn.selectpicker) {
            try { $sels.selectpicker('refresh'); } catch (e) {}
        }
    }

    function setField(selector, value) {
        if (value === undefined || value === null || value === '') {
            return;
        }
        var $el = $(selector);
        if ($el.length && !$el.val()) {
            $el.val(value);
        }
    }

    function fillFromParts(parts, label) {
        if (label) {
            $('#geocomplete').val(label);
        }
        setField('#city', parts.city);
        setField('#countyState', parts.state);
        setField('#zip', parts.zip);
        setField('#neighborhood', parts.neighborhood);
        setField('#country', parts.country || 'Brasil');
        refreshSelects();
    }

    /* ---------- barra de fallback ---------- */

    function ensureBar() {
        var $bar = $('#ipc-osm-fallback-bar');
        if ($bar.length) {
            return $bar;
        }
        var html = '' +
            '<div id="ipc-osm-fallback-bar" class="alert alert-warning mt-2" style="display:none" role="alert">' +
                '<strong>Não encontrou o endereço?</strong><br>' +
                '<span class="d-block mb-2">Tente a busca alternativa ou posicione o alfinete manualmente no mapa.</span>' +
                '<button type="button" class="btn btn-sm btn-primary ipc-osm-try-photon">Buscar em base alternativa</button> ' +
                '<button type="button" class="btn btn-sm btn-secondary-outlined ipc-osm-manual-pin">Usar pin do mapa</button>' +
            '</div>' +
            '<div id="ipc-osm-photon-results" class="list-group mt-2" style="display:none"></div>' +
            '<div id="ipc-osm-manual-hint" class="alert alert-info mt-2" style="display:none" role="status">' +
                '<strong>Modo pin manual.</strong> Arraste o alfinete vermelho até o local exato ' +
                'e os campos de endereço serão preenchidos automaticamente.' +
            '</div>';
        $('#geocomplete').closest('.form-group').append(html);
        return $('#ipc-osm-fallback-bar');
    }

    function showBar() {
        ensureBar();
        $('#ipc-osm-fallback-bar').slideDown(150);
    }

    function hideBar() {
        $('#ipc-osm-fallback-bar').slideUp(150);
        $('#ipc-osm-photon-results').hide().empty();
    }

    /* ---------- Photon (base alternativa) ---------- */

    function photonSearch(term) {
        var bias = biasLatLng();
        var url = 'https://photon.komoot.io/api/?q=' + encodeURIComponent(term)
            + '&limit=8&lang=pt&lat=' + bias.lat + '&lon=' + bias.lng;
        var $box = $('#ipc-osm-photon-results');
        $box.show().html('<div class="list-group-item">Buscando na base alternativa…</div>');
        $.getJSON(url)
            .done(function (data) {
                var feats = (data && data.features) || [];
                if (!feats.length) {
                    $box.html('<div class="list-group-item">Nada encontrado. Use o pin do mapa.</div>');
                    return;
                }
                var html = '';
                feats.forEach(function (f, i) {
                    var p = f.properties || {};
                    var label = composePhotonLabel(p);
                    html += '<button type="button" class="list-group-item list-group-item-action ipc-osm-photon-item" data-i="' + i + '">' + escapeHtml(label) + '</button>';
                });
                $box.html(html);
                $box.data('features', feats);
            })
            .fail(function () {
                $box.html('<div class="list-group-item">Falha na busca alternativa. Use o pin do mapa.</div>');
            });
    }

    function composePhotonLabel(p) {
        var parts = [];
        if (p.name && p.name !== p.street) {
            parts.push(p.name);
        }
        var street = [p.street, p.housenumber].filter(Boolean).join(', ');
        if (street) {
            parts.push(street);
        }
        var city = p.city || p.town || p.village || p.suburb || p.district || '';
        if (city) {
            parts.push(city);
        }
        if (p.state) {
            parts.push(p.state);
        }
        return parts.join(' - ') || 'Local sem nome';
    }

    function escapeHtml(s) {
        return $('<div>').text(s === null || s === undefined ? '' : String(s)).html();
    }

    function photonParts(p) {
        return {
            city: p.city || p.town || p.village || '',
            state: p.state || '',
            zip: p.postcode || '',
            neighborhood: p.suburb || p.district || p.neighbourhood || p.city_district || '',
            country: p.country || ''
        };
    }

    /* ---------- reverse geocode (pin manual) ---------- */

    function reverseGeocode(lat, lng) {
        var url = 'https://nominatim.openstreetmap.org/reverse?lat=' + lat
            + '&lon=' + lng + '&format=json&addressdetails=1&accept-language=pt-BR';
        $.getJSON(url)
            .done(function (data) {
                if (!data) {
                    return;
                }
                var a = data.address || {};
                $('#geocomplete').val(data.display_name || '');
                setField('#city', a.city || a.town || a.village || a.municipality || '');
                setField('#countyState', a.state || '');
                setField('#zip', a.postcode || '');
                setField('#neighborhood', a.suburb || a.district || a.neighbourhood || a.city_district || '');
                setField('#country', a.country || '');
                refreshSelects();
            });
    }

    function startLatLngPoll() {
        if (pollTimer) {
            return;
        }
        lastLat = $('#latitude').val();
        lastLng = $('#longitude').val();
        pollTimer = setInterval(function () {
            var lat = $('#latitude').val();
            var lng = $('#longitude').val();
            if (lat && lng && (lat !== lastLat || lng !== lastLng)) {
                lastLat = lat;
                lastLng = lng;
                if (manualMode) {
                    reverseGeocode(lat, lng);
                }
            }
        }, 900);
    }

    /* ---------- wiring ---------- */

    function init() {
        if (!isOsmSubmitPage()) {
            return;
        }

        // Resposta vazia do Nominatim -> mostra a barra.
        $('#geocomplete').on('autocompleteresponse', function (event, ui) {
            var items = (ui && ui.content) || [];
            var empty = items.length === 0
                || (items.length === 1 && !items[0].value && !items[0].latitude);
            if (empty) {
                showBar();
            } else {
                hideBar();
            }
        });

        // Digitando de novo, esconde para não poluir.
        $('#geocomplete').on('input', function () {
            $('#ipc-osm-photon-results').hide().empty();
        });

        // Escolha válida esconde a barra.
        $('#geocomplete').on('autocompleteselect', function () {
            hideBar();
            $('#ipc-osm-manual-hint').hide();
            manualMode = false;
        });

        $(document).on('click', '.ipc-osm-try-photon', function (e) {
            e.preventDefault();
            var term = $.trim($('#geocomplete').val() || '');
            if (term.length < MIN_CHARS) {
                $('#ipc-osm-photon-results').show().html(
                    '<div class="list-group-item">Digite ao menos ' + MIN_CHARS + ' caracteres do endereço.</div>'
                );
                return;
            }
            clearTimeout(photonTimer);
            photonTimer = setTimeout(function () {
                photonSearch(term);
            }, 250);
        });

        $(document).on('click', '.ipc-osm-photon-item', function (e) {
            e.preventDefault();
            var feats = $('#ipc-osm-photon-results').data('features') || [];
            var f = feats[parseInt($(this).data('i'), 10) || 0];
            if (!f) {
                return;
            }
            var coords = (f.geometry && f.geometry.coordinates) || [];
            var lng = coords[0];
            var lat = coords[1];
            if (lat === undefined || lng === undefined) {
                return;
            }
            $('#latitude').val(lat);
            $('#longitude').val(lng);
            lastLat = String(lat);
            lastLng = String(lng);
            fillFromParts(photonParts(f.properties || {}), composePhotonLabel(f.properties || {}));
            $('#ipc-osm-photon-results').hide().empty();
            hideBar();
        });

        $(document).on('click', '.ipc-osm-manual-pin', function (e) {
            e.preventDefault();
            manualMode = true;
            $('#ipc-osm-manual-hint').slideDown(150);
            var $map = $('#map_canvas');
            if ($map.length && $map[0].scrollIntoView) {
                $map[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        startLatLngPoll();
    }

    $(init);
})(jQuery);
