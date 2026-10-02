/* Híbrido OSM + Google (cadastro de imóvel):
 * - O mapa e o pin continuam OpenStreetMap (map_system_submit = osm).
 * - O Google entra SÓ com sugestões no campo de endereço (#geocomplete),
 *   preenchendo rua/cidade/bairro/estado/CEP/país. Latitude/longitude NÃO
 *   são tocadas: o pin do OSM continua sendo a fonte das coordenadas.
 * Requer a chave em Opções do Tema → Maps → Google Maps API Key.
 */
(function () {
    'use strict';

    function norm(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    function setText(input, value) {
        if (!input || !value) {
            return false;
        }
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    function setSelect(select, value) {
        if (!select || !value) {
            return false;
        }
        var target = norm(value);
        var options = select.options;
        for (var i = 0; i < options.length; i++) {
            if (norm(options[i].text) === target || norm(options[i].value) === target) {
                select.selectedIndex = i;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                refreshPicker(select);
                return true;
            }
        }
        return false;
    }

    function refreshPicker(select) {
        try {
            if (window.jQuery && window.jQuery(select).data('selectpicker')) {
                window.jQuery(select).selectpicker('refresh');
            }
        } catch (e) {
            /* noop */
        }
    }

    function setField(names, value) {
        if (!value) {
            return;
        }
        for (var i = 0; i < names.length; i++) {
            var el = document.getElementById(names[i]);
            if (!el) {
                continue;
            }
            if (el.tagName === 'SELECT') {
                if (setSelect(el, value)) {
                    return;
                }
            } else if (setText(el, value)) {
                return;
            }
        }
    }

    function component(place, type) {
        var comps = place.address_components || [];
        for (var i = 0; i < comps.length; i++) {
            if (comps[i].types.indexOf(type) !== -1) {
                return comps[i];
            }
        }
        return null;
    }

    function pick(place, types) {
        for (var i = 0; i < types.length; i++) {
            var found = component(place, types[i]);
            if (found) {
                return found;
            }
        }
        return null;
    }

    function fillFromPlace(place) {
        if (!place || !place.address_components) {
            return;
        }

        var route = pick(place, ['route']);
        var number = pick(place, ['street_number']);
        var street = [route && route.long_name, number && number.long_name].filter(Boolean).join(', ');
        if (street) {
            var addressInput = document.getElementById('geocomplete');
            setText(addressInput, street);
        }

        var city = pick(place, ['administrative_area_level_2', 'locality']);
        var neighborhood = pick(place, ['sublocality_level_1', 'sublocality', 'neighborhood']);
        var state = pick(place, ['administrative_area_level_1']);
        var zip = pick(place, ['postal_code']);
        var country = pick(place, ['country']);

        if (city) {
            setField(['city'], city.long_name);
        }
        if (neighborhood) {
            setField(['neighborhood'], neighborhood.long_name);
        }
        if (state) {
            var stateEl = document.getElementById('countyState') || document.querySelector('select[name="administrative_area_level_1"]');
            if (stateEl && stateEl.tagName === 'SELECT') {
                if (!setSelect(stateEl, state.long_name)) {
                    setSelect(stateEl, state.short_name);
                }
            } else {
                setText(stateEl, state.long_name);
            }
        }
        if (zip) {
            setField(['zip'], zip.long_name);
        }
        if (country) {
            var countryEl = document.getElementById('country');
            if (countryEl && countryEl.tagName === 'SELECT') {
                if (!setSelect(countryEl, country.long_name)) {
                    setSelect(countryEl, country.short_name);
                }
            } else {
                setText(countryEl, country.long_name);
            }
        }
    }

    function initAutocomplete() {
        var input = document.getElementById('geocomplete');
        if (!input || !window.google || !google.maps || !google.maps.places) {
            return;
        }
        // Evita duplo vínculo (tema pode já ter anexado o próprio autocomplete).
        if (input.dataset.ipcSuggestBound === '1') {
            return;
        }
        input.dataset.ipcSuggestBound = '1';

        var autocomplete = new google.maps.places.Autocomplete(input, {
            types: ['address'],
            componentRestrictions: { country: 'br' },
            fields: ['address_components', 'formatted_address', 'geometry']
        });

        autocomplete.addListener('place_changed', function () {
            fillFromPlace(autocomplete.getPlace());
        });
    }

    window.ipcAddressSuggestInit = initAutocomplete;

    /* Falha de autenticação do Google (chave/billing/restrição): esconde o
     * diálogo assustador do Google e avisa de forma amigável. Os campos
     * continuam preenchíveis manualmente; o mapa OSM não é afetado. */
    function dismissGoogleDialog() {
        var texts = ['não carregou o Google Maps corretamente', "didn't load Google Maps correctly"];
        var observer;
        try {
            observer = new MutationObserver(function () {
                var divs = document.querySelectorAll('body div');
                for (var i = 0; i < divs.length; i++) {
                    var text = divs[i].innerText || '';
                    var matches = text.length < 400 && texts.some(function (needle) {
                        return text.indexOf(needle) !== -1;
                    });
                    if (matches && divs[i].querySelector('button')) {
                        divs[i].style.display = 'none';
                        observer.disconnect();
                        return;
                    }
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
            window.setTimeout(function () {
                observer.disconnect();
            }, 10000);
        } catch (e) {
            /* noop */
        }
    }

    window.gm_authFailure = function () {
        dismissGoogleDialog();
        var input = document.getElementById('geocomplete');
        if (input && !document.getElementById('ipc-suggest-warn') && input.closest('.form-group')) {
            var warn = document.createElement('p');
            warn.id = 'ipc-suggest-warn';
            warn.style.cssText = 'margin:6px 0 0;font-size:12px;color:#92400e;';
            warn.textContent = 'Sugestões de endereço indisponíveis no momento — preencha os campos manualmente.';
            input.closest('.form-group').appendChild(warn);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAutocomplete);
    } else {
        initAutocomplete();
    }
})();
