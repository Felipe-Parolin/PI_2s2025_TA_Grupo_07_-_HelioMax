// simulador.js - Versão com envio correto de nomes de paradas para o backend e Overlay de Carregamento

document.addEventListener('DOMContentLoaded', () => {

    // 1. Inicializar o Mapa (Leaflet)
    const map = L.map('map').setView([-22.3755861, -47.8825], 5);
    L.tileLayer('https://maps.geoapify.com/v1/tile/klokantech-basic/{z}/{x}/{y}.png?apiKey=a94d6d7cd9de4604aea43f8e8a1d0a36', {
        attribution: 'Powered by <a href="https://www.geoapify.com/" target="_blank">Geoapify</a> | &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 20
    }).addTo(map);

    // --- Variáveis Globais ---
    let startMarker = null;
    let endMarker = null;
    let routeLayer = null;
    let chargeStopMarkers = [];
    let currentReportData = null;
    let currentVehicleInfo = null;

    let startCoords = null;
    let endCoords = null;
    let geocoder;
    let cachedFavorites = [];

    // VARIÁVEIS PARA VEÍCULOS
    let userVehicles = [];
    let selectedVehicle = null;

    // VARIÁVEIS PARA MÚLTIPLAS PARADAS
    let stopovers = [];
    let stopoverCounter = 0;
    let stopoverAutocompletes = {};

    // CONSTANTE DO SEPARADOR
    const SEPARATOR = '|~|';

    // --- Elementos do DOM ---
    const startInput = document.getElementById('start-point');
    const endInput = document.getElementById('end-point');
    const simulateBtn = document.getElementById('simulate-button');
    const clearBtn = document.getElementById('clear-button');
    const loadingSpinner = document.getElementById('loading-spinner');
    const downloadBtn = document.getElementById('download-pdf-button');
    const optimisticModeCheck = document.getElementById('optimistic-mode');

    const startFavoriteControl = document.getElementById('start-favorite-control');
    const endFavoriteControl = document.getElementById('end-favorite-control');
    const startFavoriteSelect = document.getElementById('start-favorite-select');
    const endFavoriteSelect = document.getElementById('end-favorite-select');

    // ELEMENTOS DOM DE VEÍCULOS
    const vehicleSelect = document.getElementById('vehicle-select');
    const vehicleDisplayText = document.getElementById('vehicle-display-text');
    const vehicleInfo = document.getElementById('vehicle-info');

    // ELEMENTOS DOM DAS PARADAS
    const stopoversContainer = document.getElementById('stopovers-container');
    const addStopoverBtn = document.getElementById('add-stopover-btn');

    // ELEMENTOS DOM DO RELATÓRIO
    const reportSummary = document.getElementById('report-summary');
    const chargingStopsList = document.getElementById('charging-stops-list');
    const stopsTitle = document.getElementById('stops-title');

    // ELEMENTOS DO OVERLAY DE CARREGAMENTO (NOVO)
    const loadingOverlay = document.getElementById('loading-overlay');
    const loadingText = document.getElementById('loading-text');

    // Verificação de elementos críticos
    if (!reportSummary) console.error('❌ ERRO: #report-summary não encontrado!');
    if (!chargingStopsList) console.error('❌ ERRO: #charging-stops-list não encontrado!');
    if (!stopsTitle) console.error('❌ ERRO: #stops-title não encontrado!');

    // Inicializa o Geocoder e o Autocomplete 
    if (window.google && window.google.maps) {
        try {
            geocoder = new google.maps.Geocoder();
            initAutocomplete();
        } catch (e) {
            console.error("Erro ao inicializar a API do Google Maps.", e);
        }
    } else {
        console.warn("API do Google Maps não está carregada.");
    }

    // ==================== FUNÇÕES AUXILIARES ====================

    // Função para encontrar o endereço original de uma parada manual baseada nas coordenadas
    function findOriginalStopoverAddress(lat, lng) {
        if (!stopovers || stopovers.length === 0) return null;

        // Tolerância para comparação de float (coordenadas)
        const epsilon = 0.0001;

        const match = stopovers.find(s =>
            s.coords &&
            Math.abs(s.coords.lat - lat) < epsilon &&
            Math.abs(s.coords.lng - lng) < epsilon
        );

        return match ? match.address : null;
    }

    // ==================== FUNÇÕES DE MÚLTIPLAS PARADAS ====================

    function addStopover() {
        const stopoverId = `stopover-${stopoverCounter++}`;
        const stopoverNumber = stopovers.length + 1;

        const stopoverElement = document.createElement('div');
        stopoverElement.className = 'stopover-item bg-slate-900/50 p-3 rounded-lg border border-yellow-500/20';
        stopoverElement.dataset.stopoverId = stopoverId;

        stopoverElement.innerHTML = `
            <div class="flex items-center gap-2 mb-2">
                <span class="bg-yellow-500 text-slate-900 rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold flex-shrink-0">
                    ${stopoverNumber}
                </span>
                <input 
                    type="text" 
                    id="${stopoverId}-input"
                    placeholder="Parada ${stopoverNumber}"
                    class="flex-1 px-3 py-2 bg-slate-800 border border-yellow-500/30 rounded-lg text-white text-sm placeholder-gray-500 focus:ring-1 focus:ring-yellow-500 focus:border-yellow-500 transition-all"
                >
                <button class="remove-stopover-btn text-red-400 hover:text-red-300 transition-colors" data-stopover-id="${stopoverId}">
                    <i data-lucide="x-circle" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="text-xs text-gray-500 hidden ml-8" id="${stopoverId}-coords">
                <i data-lucide="check-circle" class="w-3 h-3 inline text-green-400"></i>
                <span id="${stopoverId}-coords-text"></span>
            </div>
        `;

        stopoversContainer.appendChild(stopoverElement);

        setupStopoverAutocomplete(stopoverId);

        const removeBtn = stopoverElement.querySelector('.remove-stopover-btn');
        removeBtn.addEventListener('click', () => removeStopover(stopoverId));

        stopovers.push({
            id: stopoverId,
            number: stopoverNumber,
            element: stopoverElement,
            coords: null,
            address: null,
            marker: null
        });

        lucide.createIcons();
        updateStopoverNumbers();
    }

    function removeStopover(stopoverId) {
        const index = stopovers.findIndex(s => s.id === stopoverId);
        if (index !== -1) {
            if (stopovers[index].marker) {
                map.removeLayer(stopovers[index].marker);
            }

            stopovers[index].element.remove();

            if (stopoverAutocompletes[stopoverId]) {
                delete stopoverAutocompletes[stopoverId];
            }

            stopovers.splice(index, 1);
            updateStopoverNumbers();
        }
    }

    function updateStopoverNumbers() {
        stopovers.forEach((stopover, index) => {
            const stopoverNumber = index + 1;
            stopover.number = stopoverNumber;

            const numberBadge = stopover.element.querySelector('.bg-yellow-500');
            if (numberBadge) numberBadge.textContent = stopoverNumber;

            const input = stopover.element.querySelector('input');
            if (input) input.placeholder = `Parada ${stopoverNumber}`;
        });
    }

    function setupStopoverAutocomplete(stopoverId) {
        const input = document.getElementById(`${stopoverId}-input`);
        if (!input) return;

        const autocomplete = new google.maps.places.Autocomplete(input, {
            componentRestrictions: { country: "br" },
            fields: ["formatted_address", "geometry.location", "name"]
        });

        stopoverAutocompletes[stopoverId] = autocomplete;

        autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();

            if (!place.geometry || !place.geometry.location) {
                console.warn('Local sem geometria:', place);
                return;
            }

            const lat = place.geometry.location.lat();
            const lng = place.geometry.location.lng();
            const latlng = { lat, lng };
            const address = place.formatted_address || place.name;

            const stopover = stopovers.find(s => s.id === stopoverId);
            if (stopover) {
                stopover.coords = latlng;
                stopover.address = address;

                if (stopover.marker) {
                    map.removeLayer(stopover.marker);
                }

                const starIcon = L.divIcon({
                    className: 'custom-stopover-icon',
                    html: `
                        <div style="background: white; border-radius: 50%; padding: 3px; border: 3px solid #eab308; box-shadow: 0 4px 8px rgba(0,0,0, 0.3);">
                            <div style="background: #eab308; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #1e293b; font-size: 12px;">
                                ${stopover.number}
                            </div>
                        </div>
                    `,
                    iconSize: [30, 30],
                    iconAnchor: [15, 15],
                    popupAnchor: [0, -15]
                });

                stopover.marker = L.marker(latlng, {
                    icon: starIcon,
                    draggable: true
                })
                    .addTo(map)
                    .bindPopup(`<strong>Parada ${stopover.number}</strong><br>${address}`);

                stopover.marker.on('dragend', (e) => {
                    const newLatLng = e.target.getLatLng();
                    stopover.coords = newLatLng;
                    reverseGeocode(newLatLng, input);
                });

                const coordsDiv = document.getElementById(`${stopoverId}-coords`);
                const coordsText = document.getElementById(`${stopoverId}-coords-text`);

                if (coordsDiv && coordsText) {
                    coordsDiv.classList.remove('hidden');
                    coordsText.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                }

                map.setView(latlng, 13);
                console.log(`✓ Parada ${stopover.number} configurada:`, address);
            }
        });
    }

    function getStopoversData() {
        return stopovers
            .filter(s => s.coords !== null)
            .map(s => ({
                lat: s.coords.lat,
                lng: s.coords.lng,
                name: s.address || `Parada Manual ${s.number}`
            }));
    }

    function clearAllStopovers() {
        stopovers.forEach(stopover => {
            if (stopover.marker) {
                map.removeLayer(stopover.marker);
            }
        });

        stopoversContainer.innerHTML = '';
        stopovers = [];
        stopoverAutocompletes = {};
        stopoverCounter = 0;
    }

    // ==================== FUNÇÕES BÁSICAS ====================

    function reverseGeocode(latlng, inputElement) {
        geocoder.geocode({ 'location': latlng }, (results, status) => {
            if (status === 'OK') {
                if (results[0]) {
                    inputElement.value = results[0].formatted_address;
                } else {
                    inputElement.value = "Endereço não encontrado";
                }
            } else {
                console.error('Geocoder falhou: ' + status);
                inputElement.value = `${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}`;
            }
        });
    }

    function handleMarkerDrag(event, type) {
        const latlng = event.target.getLatLng();
        if (type === 'start') {
            startCoords = latlng;
            reverseGeocode(latlng, startInput);
        } else {
            endCoords = latlng;
            reverseGeocode(latlng, endInput);
        }
        const selectElement = type === 'start' ? startFavoriteSelect : endFavoriteSelect;
        if (selectElement) selectElement.value = '';
    }

    map.on('click', (e) => {
        const latlng = e.latlng;
        if (!startMarker) {
            setRoutePoint(latlng, `${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}`, 'start', false);
            reverseGeocode(latlng, startInput);
        } else if (!endMarker) {
            setRoutePoint(latlng, `${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}`, 'end', false);
            reverseGeocode(latlng, endInput);
        }
    });

    function initAutocomplete() {
        const options = {
            componentRestrictions: { country: "br" },
            fields: ["formatted_address", "geometry.location"]
        };

        const startAutocomplete = new google.maps.places.Autocomplete(startInput, options);
        const endAutocomplete = new google.maps.places.Autocomplete(endInput, options);

        startAutocomplete.addListener('place_changed', () => {
            handlePlaceSelect(startAutocomplete, 'start');
        });

        endAutocomplete.addListener('place_changed', () => {
            handlePlaceSelect(endAutocomplete, 'end');
        });
    }

    function handlePlaceSelect(autocomplete, type) {
        const place = autocomplete.getPlace();
        if (!place.geometry || !place.geometry.location) return;

        const lat = place.geometry.location.lat();
        const lng = place.geometry.location.lng();
        const latlng = { lat: lat, lng: lng };
        const name = place.formatted_address;

        setRoutePoint(latlng, name, type, false);
        map.setView(latlng, 15);
    }

    function setRoutePoint(latlng, name, type, isFavorite = false) {
        const inputElement = type === 'start' ? startInput : endInput;
        let marker = type === 'start' ? startMarker : endMarker;
        const setCoords = type === 'start' ? (c) => startCoords = c : (c) => endCoords = c;
        const setMarker = type === 'start' ? (m) => startMarker = m : (m) => endMarker = m;
        const selectElement = type === 'start' ? startFavoriteSelect : endFavoriteSelect;

        setCoords(latlng);
        inputElement.value = name;

        if (marker) map.removeLayer(marker);

        marker = L.marker(latlng, { draggable: true }).addTo(map).bindPopup(type === 'start' ? 'Origem' : 'Destino');
        marker.on('dragend', (ev) => handleMarkerDrag(ev, type));
        setMarker(marker);

        map.setView(latlng, 15);

        if (selectElement) {
            if (isFavorite) {
                const selectValue = `${latlng.lat},${latlng.lng}|${name}`;
                selectElement.value = selectValue;
            } else {
                selectElement.value = '';
            }
        }
    }

    // ==================== BOTÃO DE SIMULAR (COM LOADING) ====================

    simulateBtn.addEventListener('click', () => {
        if (!startCoords || !endCoords) {
            alert("Por favor, defina um ponto de Origem e um de Destino.");
            return;
        }

        // 1. Mostrar o Overlay de Carregamento
        if (loadingOverlay) {
            loadingOverlay.classList.remove('hidden');
            // Pequena animação de texto opcional
            const texts = ["Analisando trajeto...", "Buscando estações...", "Otimizando paradas...", "Calculando consumo..."];
            let textIndex = 0;
            const textInterval = setInterval(() => {
                if (loadingText) loadingText.textContent = texts[textIndex++ % texts.length];
            }, 800);

            // Guardamos o intervalo no elemento para limpar depois
            loadingOverlay.dataset.intervalId = textInterval;
        }

        // Limpar mapa
        if (routeLayer) map.removeLayer(routeLayer);
        chargeStopMarkers.forEach(marker => map.removeLayer(marker));
        chargeStopMarkers = [];
        currentReportData = null;
        currentVehicleInfo = null;

        // Desabilitar botão
        simulateBtn.disabled = true;

        if (reportSummary) {
            reportSummary.innerHTML = '<p class="text-gray-400">Calculando...</p>';
        }
        if (chargingStopsList) {
            chargingStopsList.innerHTML = '';
        }
        if (stopsTitle) {
            stopsTitle.classList.add('hidden');
        }
        downloadBtn.style.display = 'none';

        const formData = new FormData();
        formData.append('start_coords', `${startCoords.lat}, ${startCoords.lng}`);
        formData.append('end_coords', `${endCoords.lat}, ${endCoords.lng}`);
        formData.append('optimistic_mode', optimisticModeCheck.checked);

        // ENVIO DO ID DO VEÍCULO
        const vehicleId = vehicleSelect ? vehicleSelect.value : 'default';
        formData.append('vehicle_id', vehicleId);

        // *** ADICIONAR PARADAS MÚLTIPLAS COM NOMES ***
        const stopoversData = getStopoversData();

        if (stopoversData.length > 0) {
            // Envia o JSON completo com nomes e coordenadas
            formData.append('stopovers_data', JSON.stringify(stopoversData));
            console.log(`🛑 ${stopoversData.length} parada(s) adicionada(s) à simulação com nomes.`);
        }

        fetch('../PHP/simulate_route.php', { method: 'POST', body: formData })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`Erro de Servidor: ${res.status} ${res.statusText}`);
                }
                const contentType = res.headers.get("content-type");

                if (!contentType || !contentType.includes("application/json")) {
                    return res.text().then(text => {
                        console.error('Resposta não é JSON:', text);
                        throw new Error('Servidor retornou uma resposta inválida (não-JSON).');
                    });
                }
                return res.json();
            })
            .then(data => {
                // Reabilitar botão
                simulateBtn.disabled = false;
                simulateBtn.innerHTML = '<i data-lucide="play" class="w-5 h-5"></i><span>Simular Rota</span>';

                if (data.success) {
                    currentReportData = data.report;
                    currentVehicleInfo = data.vehicle_info;

                    const decodedPath = polyline.decode(data.geometry_polyline);

                    routeLayer = L.polyline(decodedPath, {
                        color: '#22d3ee',
                        weight: 5,
                        opacity: 0.7
                    }).addTo(map);

                    const bounds = [
                        [data.bounds.southwest.lat, data.bounds.southwest.lng],
                        [data.bounds.northeast.lat, data.bounds.northeast.lng]
                    ];
                    map.fitBounds(bounds);

                    downloadBtn.style.display = 'flex';
                    displayReport(currentReportData, data.vehicle_info);

                    // Adicionar marcadores de parada
                    const allStops = currentReportData.charge_stops_details;
                    if (allStops && allStops.length > 0) {
                        allStops.forEach((stop, index) => {
                            // *** VERIFICAR SE É PARADA MANUAL ***
                            if (stop.is_manual_stop === true) {
                                // O backend já devolve o nome salvo, mas garantimos o fallback
                                const displayName = stop.name || findOriginalStopoverAddress(stop.latitude, stop.longitude) || 'Parada Manual';

                                // Marcador para parada manual (amarelo)
                                const manualIcon = L.divIcon({
                                    className: 'custom-stopover-icon',
                                    html: `
                                        <div style="background: white; border-radius: 50%; padding: 3px; border: 3px solid #eab308; box-shadow: 0 4px 8px rgba(0,0,0, 0.3); display: flex; align-items: center; justify-content: center;">
                                            <div style="background: #eab308; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                                <i data-lucide="coffee" style="width: 20px; height: 20px; color: #1e293b;"></i>
                                            </div>
                                        </div>
                                    `,
                                    iconSize: [40, 40],
                                    iconAnchor: [20, 20],
                                    popupAnchor: [0, -20]
                                });

                                const popupContent = `
                                    <div style="min-width: 200px;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                            <strong style="color: #eab308; font-size: 1.1em;">☕ Parada de Descanso</strong>
                                        </div>
                                        <div style="background: rgba(234, 179, 8, 0.1); padding: 8px; border-radius: 6px;">
                                            <strong style="color: #eab308;">${displayName}</strong>
                                        </div>
                                    </div>
                                `;

                                const marker = L.marker([stop.latitude, stop.longitude], { icon: manualIcon })
                                    .bindPopup(popupContent)
                                    .addTo(map);

                                chargeStopMarkers.push(marker);
                                return; // Pula para próxima iteração
                            }

                            // ... (resto do código de marcadores de recarga igual)
                            let lat, lng;
                            try {
                                lat = stop.latitude;
                                lng = stop.longitude;
                                if (typeof lat !== 'number' || typeof lng !== 'number' || isNaN(lat) || isNaN(lng)) {
                                    throw new Error(`Coordenadas inválidas: lat=${lat}, lng=${lng}`);
                                }
                            } catch (error) {
                                console.error(`Erro ao processar parada ${index + 1}:`, error.message);
                                return;
                            }

                            const isEstimated = stop.is_estimated === true;
                            const isFromDatabase = stop.is_from_database === true;

                            let iconColor, iconBg, popupTitle, badgeText;

                            if (isEstimated) {
                                iconColor = '#64748b';
                                iconBg = '#64748b';
                                popupTitle = '⚡ Parada Simulada (Planejamento)';
                                badgeText = 'Simulada';
                            } else if (isFromDatabase) {
                                iconColor = '#10b981';
                                iconBg = '#10b981';
                                popupTitle = `⚡ Parada ${stop.stop_number} (HelioMax)`;
                                badgeText = 'HelioMax';
                            } else {
                                iconColor = '#ef4444';
                                iconBg = '#ef4444';
                                popupTitle = `⚡ Parada ${stop.stop_number} (OCM)`;
                                badgeText = 'OpenChargeMap';
                            }

                            const iconSVG = isEstimated ?
                                `<i data-lucide="brain-circuit" style="width: 20px; height: 20px; color: white;"></i>` :
                                isFromDatabase ?
                                    `<i data-lucide="home" style="width: 20px; height: 20px; color: white;"></i>` :
                                    `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2v4"/><path d="m16 6-4 4-4-4"/><rect width="8" height="12" x="8" y="10" rx="2"/><path d="M8 22v-2c0-1.1.9-2 2-2h4a2 2 0 0 1 2 2v2H8Z"/>
                            </svg>`;

                            const chargeStopIcon = L.divIcon({
                                className: 'custom-charge-stop-icon',
                                html: `
                                <div style="background: white; border-radius: 50%; padding: 4px; border: 3px solid ${iconBg}; box-shadow: 0 4px 12px rgba(0,0,0, 0.4); display: flex; align-items: center; justify-content: center;">
                                    <div style="background: ${iconBg}; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                        ${iconSVG}
                                    </div>
                                </div>
                            `,
                                iconSize: [40, 40],
                                iconAnchor: [20, 20],
                                popupAnchor: [0, -20]
                            });

                            const ratingPopup = isFromDatabase && stop.station.rating
                                ? `<em style="color: #10b981;">⭐ Avaliação Média: ${parseFloat(stop.station.rating).toFixed(1)}/5.0</em>`
                                : '';

                            let popupContent = `
                            <div style="min-width: 280px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                    <strong style="color: ${iconColor}; font-size: 1.1em;">${popupTitle}</strong>
                                    <span style="background: ${iconBg}; color: white; padding: 4px 8px; border-radius: 6px; font-size: 0.75em; font-weight: bold;">
                                        ${badgeText}
                                    </span>
                                </div>
                                <div style="background: rgba(34, 211, 238, 0.1); padding: 10px; border-radius: 6px; margin-bottom: 10px;">
                                    <strong style="color: #22d3ee;">📍 ${stop.station.name}</strong><br>
                                    <span style="font-size: 0.85em; color: #94a3b8;">
                                        ${stop.station.address}<br>
                                        ${ratingPopup}
                                    </span>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 0.9em;">
                                    <div><strong>Conector:</strong><br>${stop.station.connector_type}</div>
                                    <div><strong>Potência:</strong><br>${stop.charging_power_kw} kW</div>
                                    <div><strong>Distância:</strong><br>${stop.distance_traveled_km} km</div>
                                    <div><strong>Tempo Carga:</strong><br>${Math.round(stop.charge_time / 60)} min</div>
                                    <div><strong>Chegada:</strong><br>${stop.charge_at_arrival}%</div>
                                    <div><strong>Saída:</strong><br>${stop.charge_at_departure}%</div>
                                </div>
                                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e2e8f0;">
                                    <strong>Energia Carregada:</strong> ${stop.energy_charged_kwh} kWh
                                </div>
                            </div>
                        `;

                            try {
                                const marker = L.marker([lat, lng], { icon: chargeStopIcon })
                                    .bindPopup(popupContent)
                                    .addTo(map);

                                chargeStopMarkers.push(marker);
                            } catch (error) {
                                console.error(`Erro ao criar marcador para parada ${index + 1}:`, error);
                            }
                        });
                        lucide.createIcons();
                    }

                } else {
                    if (reportSummary) {
                        reportSummary.innerHTML = `<p style="color: #ef4444;">Erro: ${data.message}</p>`;
                    }
                }
            })
            .catch(err => {
                // Reabilitar botão
                simulateBtn.disabled = false;
                simulateBtn.innerHTML = '<i data-lucide="play" class="w-5 h-5"></i><span>Simular Rota</span>';

                if (reportSummary) {
                    reportSummary.innerHTML = `<p style="color: #ef4444;">Erro de conexão: ${err.message}</p>`;
                }
                console.error('Erro na requisição:', err);
            })
            .finally(() => {
                // 2. Esconder o Overlay de Carregamento (SEMPRE executa)
                if (loadingOverlay) {
                    loadingOverlay.classList.add('hidden');

                    // Limpar intervalo de texto
                    const intervalId = loadingOverlay.dataset.intervalId;
                    if (intervalId) clearInterval(intervalId);
                    if (loadingText) loadingText.textContent = "Finalizando...";
                }
                lucide.createIcons();
            });
    });

    // ==================== BOTÃO DE LIMPAR ====================

    clearBtn.addEventListener('click', () => {
        if (startMarker) map.removeLayer(startMarker);
        if (endMarker) map.removeLayer(endMarker);
        if (routeLayer) map.removeLayer(routeLayer);
        chargeStopMarkers.forEach(marker => map.removeLayer(marker));

        startMarker = null;
        endMarker = null;
        routeLayer = null;
        chargeStopMarkers = [];
        currentReportData = null;

        startCoords = null;
        endCoords = null;
        startInput.value = '';
        endInput.value = '';

        if (startFavoriteSelect) startFavoriteSelect.value = '';
        if (endFavoriteSelect) endFavoriteSelect.value = '';
        if (vehicleSelect) vehicleSelect.value = 'default';
        updateSelectedVehicle(null);

        // LIMPAR PARADAS
        clearAllStopovers();

        if (reportSummary) {
            reportSummary.innerHTML = '<p class="text-gray-400">Aguardando simulação...</p>';
        }
        if (chargingStopsList) {
            chargingStopsList.innerHTML = '';
        }
        if (stopsTitle) {
            stopsTitle.classList.add('hidden');
        }
        downloadBtn.style.display = 'none';
    });

    // ==================== FAVORITOS ====================

    function loadFavoritesFromApi() {
        if (cachedFavorites.length > 0) {
            if (startFavoriteControl) startFavoriteControl.style.display = 'block';
            if (endFavoriteControl) endFavoriteControl.style.display = 'block';
            return;
        }

        fetch('../PHP/api_pontos_favoritos.php', { method: 'GET' })
            .then(res => {
                if (!res.ok) {
                    console.error(`Erro de Servidor ao carregar favoritos: ${res.status}`);
                    return { success: false, data: [] };
                }
                return res.json();
            })
            .then(data => {
                if (data.success && data.data && Array.isArray(data.data) && data.data.length > 0) {
                    cachedFavorites = data.data;

                    if (startFavoriteControl) startFavoriteControl.style.display = 'block';
                    if (endFavoriteControl) endFavoriteControl.style.display = 'block';

                    if (startFavoriteSelect) {
                        startFavoriteSelect.innerHTML = '<option value="">-- Selecione um Favorito --</option>';
                    }
                    if (endFavoriteSelect) {
                        endFavoriteSelect.innerHTML = '<option value="">-- Selecione um Favorito --</option>';
                    }

                    data.data.forEach(fav => {
                        const lat = parseFloat(fav.LATITUDE);
                        const lng = parseFloat(fav.LONGITUDE);
                        let name = fav.NOME;

                        // Remove o ícone do nome se existir o separador
                        if (name && name.includes(SEPARATOR)) {
                            const parts = name.split(SEPARATOR);
                            name = parts[0].trim();
                        }

                        if (!isNaN(lat) && !isNaN(lng) && name) {
                            const option = document.createElement('option');
                            option.value = `${lat},${lng}|${name}`;
                            option.textContent = name;

                            if (startFavoriteSelect) startFavoriteSelect.appendChild(option.cloneNode(true));
                            if (endFavoriteSelect) endFavoriteSelect.appendChild(option.cloneNode(true));
                        }
                    });
                } else {
                    if (startFavoriteControl) startFavoriteControl.style.display = 'none';
                    if (endFavoriteControl) endFavoriteControl.style.display = 'none';
                }
            })
            .catch(err => {
                console.error("Erro no fetch da API de favoritos:", err);
                if (startFavoriteControl) startFavoriteControl.style.display = 'none';
                if (endFavoriteControl) endFavoriteControl.style.display = 'none';
            });
    }

    // ==================== VEÍCULOS ====================

    async function loadUserVehicles() {
        if (!vehicleSelect) {
            console.error('❌ Elemento #vehicle-select não encontrado no DOM.');
            return;
        }

        try {
            const response = await fetch('../PHP/get_user_vehicles.php');

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success && data.veiculos && Array.isArray(data.veiculos)) {
                if (data.veiculos.length > 0) {
                    userVehicles = data.veiculos;
                    populateVehicleSelect();

                    if (userVehicles.length > 0) {
                        const firstVehicle = userVehicles[0];
                        vehicleSelect.value = firstVehicle.id;
                        updateSelectedVehicle(firstVehicle);
                    }
                } else {
                    showDefaultVehicleOnly();
                }
            } else {
                showDefaultVehicleOnly();
            }
        } catch (error) {
            console.error('Erro ao carregar veículos:', error);
            showDefaultVehicleOnly();
        }
    }

    function populateVehicleSelect() {
        if (!vehicleSelect) return;

        while (vehicleSelect.options.length > 1) {
            vehicleSelect.remove(1);
        }

        userVehicles.forEach((vehicle) => {
            const option = document.createElement('option');
            option.value = vehicle.id;
            option.textContent = `${vehicle.nome_completo} - ${vehicle.placa} (${vehicle.ano})`;
            option.dataset.vehicleData = JSON.stringify(vehicle);
            vehicleSelect.appendChild(option);
        });
    }

    function showDefaultVehicleOnly() {
        while (vehicleSelect.options.length > 1) {
            vehicleSelect.remove(1);
        }
        vehicleSelect.value = 'default';
        updateSelectedVehicle(null);
    }

    function updateSelectedVehicle(vehicle) {
        selectedVehicle = vehicle;
        const vehicleInfoDiv = document.getElementById('vehicle-info');

        if (vehicle) {
            const autonomiaAtual = (vehicle.nivel_bateria / 100) * vehicle.capacidade_bateria / vehicle.consumo_medio * 100;

            if (vehicleDisplayText) {
                vehicleDisplayText.innerHTML = `<strong class="text-cyan-300">${vehicle.nome_completo}</strong>`;
            }

            if (vehicleInfoDiv) {
                vehicleInfoDiv.classList.remove('hidden');
                document.getElementById('vehicle-battery').textContent = `${vehicle.capacidade_bateria} kWh`;
                document.getElementById('vehicle-consumption').textContent = `${vehicle.consumo_medio} kWh/100km`;
                document.getElementById('vehicle-charge').textContent = `${vehicle.nivel_bateria}%`;
                document.getElementById('vehicle-range').textContent = `~${Math.round(autonomiaAtual)} km`;
            }
        } else {
            if (vehicleDisplayText) {
                vehicleDisplayText.textContent = 'Tesla Model 3 (Padrão)';
            }
            if (vehicleInfoDiv) {
                vehicleInfoDiv.classList.add('hidden');
            }
        }
    }

    if (vehicleSelect) {
        vehicleSelect.addEventListener('change', function () {
            const value = this.value;

            if (value === 'default' || !value) {
                updateSelectedVehicle(null);
            } else {
                const selectedOption = this.options[this.selectedIndex];
                try {
                    const vehicleData = JSON.parse(selectedOption.dataset.vehicleData);
                    updateSelectedVehicle(vehicleData);
                } catch (e) {
                    const fallbackVehicle = userVehicles.find(v => String(v.id) === value);
                    updateSelectedVehicle(fallbackVehicle || null);
                }
            }
        });
    }

    // ==================== FAVORITOS EVENT LISTENERS ====================

    if (startFavoriteSelect) {
        startFavoriteSelect.addEventListener('change', (e) => {
            const value = e.target.value;
            if (value) {
                const [coordsStr, name] = value.split('|');
                const [lat, lng] = coordsStr.split(',');
                setRoutePoint({ lat: parseFloat(lat), lng: parseFloat(lng) }, name, 'start', true);
            } else {
                if (startInput.value) {
                    startInput.value = '';
                    if (startMarker) map.removeLayer(startMarker);
                    startMarker = null;
                    startCoords = null;
                }
            }
        });
    }

    if (endFavoriteSelect) {
        endFavoriteSelect.addEventListener('change', (e) => {
            const value = e.target.value;
            if (value) {
                const [coordsStr, name] = value.split('|');
                const [lat, lng] = coordsStr.split(',');
                setRoutePoint({ lat: parseFloat(lat), lng: parseFloat(lng) }, name, 'end', true);
            } else {
                if (endInput.value) {
                    endInput.value = '';
                    if (endMarker) map.removeLayer(endMarker);
                    endMarker = null;
                    endCoords = null;
                }
            }
        });
    }

    // ==================== EVENT LISTENER PARA ADICIONAR PARADA ====================

    if (addStopoverBtn) {
        addStopoverBtn.addEventListener('click', () => {
            addStopover();
        });
    }

    // ==================== DISPLAY REPORT & PDF ====================
    function displayReport(report, vehicleInfo = null) {
        if (!reportSummary) {
            console.error('Elemento report-summary não encontrado!');
            return;
        }

        let vehicleInfoHTML = '';
        if (vehicleInfo && vehicleInfo.name) {
            vehicleInfoHTML = `
                <div class="p-3 bg-cyan-900/30 rounded-lg border border-cyan-500/30 mb-3 text-sm">
                    <p class="text-xs font-bold text-cyan-300 mb-2 flex items-center gap-1">
                        <i data-lucide="car" class="w-4 h-4"></i> Veículo Utilizado:
                    </p>
                    <p class="font-semibold text-white">${vehicleInfo.name}</p>
                    <div class="grid grid-cols-2 gap-2 mt-2 text-xs">
                        <div>
                            <span class="text-gray-400">Bateria:</span>
                            <span class="text-cyan-400 font-semibold"> ${vehicleInfo.battery_capacity} kWh</span>
                        </div>
                        <div>
                            <span class="text-gray-400">Consumo:</span>
                            <span class="text-cyan-400 font-semibold"> ${vehicleInfo.consumption} kWh/100km</span>
                        </div>
                    </div>
                </div>
            `;
        }

        let summaryHTML = `
            ${vehicleInfoHTML}
            <div class="space-y-2 mb-4 text-sm">
                <div class="flex justify-between items-center">
                    <span class="text-gray-300">Distância Total:</span>
                    <strong class="text-cyan-400">${report.distancia_total_km} km</strong>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-300">Tempo de Condução:</span>
                    <strong class="text-cyan-400">${report.tempo_conducao_min} min</strong>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-300">Paradas para Recarga:</span>
                    <strong class="text-cyan-400">${report.paradas_totais}</strong>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-300">Tempo Total Carregando:</span>
                    <strong class="text-cyan-400">${report.tempo_carregamento_min} min</strong>
                </div>
            </div>
            
            <hr class="border-cyan-500/20 my-3">
            
            <div class="space-y-2 text-sm mb-4">
                <div class="flex justify-between items-center">
                    <span class="text-gray-300">Energia Consumida:</span>
                    <strong class="text-cyan-400">${report.energia_consumida_total_kwh} kWh</strong>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-300">Custo Estimado:</span>
                    <strong class="text-cyan-400">R$ ${report.custo_total_estimado}</strong>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-300">Carga na Chegada:</span>
                    <strong class="text-cyan-400">${report.carga_final_pct}%</strong>
                </div>
            </div>
        `;

        reportSummary.innerHTML = summaryHTML;

        // Renderizar lista de paradas
        if (chargingStopsList && stopsTitle) {
            chargingStopsList.innerHTML = '';
            const allStops = report.charge_stops_details;

            if (allStops && allStops.length > 0) {
                stopsTitle.classList.remove('hidden');

                allStops.forEach((stop) => {
                    // *** VERIFICAR SE É PARADA MANUAL ***
                    if (stop.is_manual_stop === true) {
                        // O nome já vem correto do backend agora
                        const addressText = stop.name || findOriginalStopoverAddress(stop.latitude, stop.longitude) || 'Localização personalizada';

                        const stopHTML = `
                            <div class="bg-slate-900/50 p-3 rounded-lg border border-yellow-500/30">
                                <div class="flex items-start gap-2 mb-2">
                                    <span class="bg-yellow-500 text-slate-900 rounded-full w-6 h-6 flex items-center justify-center flex-shrink-0 text-xs font-bold">
                                        <i data-lucide="coffee" class="w-3 h-3"></i>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="font-bold text-yellow-400 text-sm truncate">Parada Manual</div>
                                            <span class="bg-yellow-500 text-slate-900 text-xs px-2 py-0.5 rounded-full font-bold">Descanso</span>
                                        </div>
                                        <div class="text-gray-400 text-xs mt-1 font-medium text-yellow-100/80">${addressText}</div>
                                    </div>
                                </div>
                            </div>
                        `;
                        chargingStopsList.insertAdjacentHTML('beforeend', stopHTML);
                        return; // Pula para próxima iteração
                    }

                    // *** PARADA DE RECARGA (código original) ***
                    const isEstimated = stop.is_estimated === true;
                    const isFromDatabase = stop.is_from_database === true;

                    let borderColor, iconBg, icon, badgeColor, badgeText;

                    if (isEstimated) {
                        borderColor = 'border-slate-500/30';
                        iconBg = 'bg-slate-500';
                        icon = `<i data-lucide="brain-circuit" class="w-4 h-4 text-white"></i>`;
                        badgeColor = 'bg-slate-500';
                        badgeText = 'Simulada';
                    } else if (isFromDatabase) {
                        borderColor = 'border-green-500/30';
                        iconBg = 'bg-green-500';
                        icon = `${stop.stop_number}`;
                        badgeColor = 'bg-green-500';
                        badgeText = 'HelioMax';
                    } else {
                        borderColor = 'border-red-500/30';
                        iconBg = 'bg-red-500';
                        icon = `${stop.stop_number}`;
                        badgeColor = 'bg-red-500';
                        badgeText = 'OCM';
                    }

                    // Formatar a avaliação média (rating)
                    const ratingDisplay = isFromDatabase && stop.station.rating
                        ? `⭐ ${parseFloat(stop.station.rating).toFixed(1)}/5.0`
                        : '';

                    const ratingText = isEstimated ? 'Parada Simulada' :
                        isFromDatabase ? `${ratingDisplay} • HelioMax • ${stop.station.connector_type}` :
                            `${stop.station.connector_type}`;

                    const stopHTML = `
                        <div class="bg-slate-900/50 p-3 rounded-lg border ${borderColor}">
                            <div class="flex items-start gap-2 mb-2">
                                <span class="${iconBg} text-white rounded-full w-6 h-6 flex items-center justify-center flex-shrink-0 text-xs font-bold">
                                    ${icon}
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="font-bold text-cyan-400 text-sm truncate">${stop.station.name}</div>
                                        <span class="${badgeColor} text-white text-xs px-2 py-0.5 rounded-full font-bold">${badgeText}</span>
                                    </div>
                                    <div class="text-gray-400 text-xs line-clamp-2">${stop.station.address}</div>
                                </div>
                            </div>
                            
                            <div class="mt-2 pt-2 border-t border-slate-700 space-y-1.5 text-xs">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400">Distância Acumulada:</span>
                                    <strong class="text-white">${stop.distance_traveled_km} km</strong>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400">Tempo Carga:</span>
                                    <strong class="text-white">${Math.round(stop.charge_time / 60)} min</strong>
                                </div>

                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400">Chegada/Saída (%):</span>
                                    <strong class="text-white">${stop.charge_at_arrival}% / ${stop.charge_at_departure}%</strong>
                                </div>

                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400">Potência/Energia:</span>
                                    <strong class="text-white">${stop.charging_power_kw} kW / ${stop.energy_charged_kwh} kWh</strong>
                                </div>

                                <div class="text-gray-500 pt-1.5 border-t border-slate-700/50 text-xs">
                                    ${ratingText}
                                </div>
                            </div>
                        </div>
                    `;
                    chargingStopsList.insertAdjacentHTML('beforeend', stopHTML);
                });
            } else {
                stopsTitle.classList.add('hidden');
            }
        }

        lucide.createIcons();
    }

    // ==================== INICIALIZAÇÃO ====================

    loadFavoritesFromApi();
    loadUserVehicles();

    // ==================== DOWNLOAD PDF (VERSÃO NATIVA/ECONÔMICA) ====================

    if (downloadBtn) {
        downloadBtn.addEventListener('click', () => {
            if (!currentReportData) {
                alert('Nenhum relatório disponível para download.');
                return;
            }

            // Inicializa o PDF
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            const margin = 15;

            // --- CABEÇALHO ---
            // Adicionar Logo/Texto HelioMax (Barra Cyan)
            doc.setFillColor(6, 182, 212); // Cor #06b6d4 (Cyan)
            doc.rect(0, 0, 210, 20, 'F'); // Barra superior colorida

            doc.setTextColor(255, 255, 255);
            doc.setFontSize(22);
            doc.setFont("helvetica", "bold");
            doc.text("HelioMax", margin, 13);

            doc.setFontSize(12);
            doc.setFont("helvetica", "normal");
            doc.text("Relatório de Planejamento de Rota", 210 - margin, 13, { align: 'right' });

            // Data e Hora de Geração
            const now = new Date();
            const dataFormatada = now.toLocaleDateString('pt-BR') + ' às ' + now.toLocaleTimeString('pt-BR');

            doc.setTextColor(100, 100, 100);
            doc.setFontSize(10);
            doc.text(`Gerado em: ${dataFormatada}`, margin, 28);

            let currentY = 35;

            // --- INFORMAÇÕES DO VEÍCULO ---
            if (currentVehicleInfo) {
                doc.setDrawColor(200, 200, 200);
                doc.line(margin, currentY, 210 - margin, currentY);
                currentY += 7;

                doc.setTextColor(0, 0, 0);
                doc.setFontSize(14);
                doc.setFont("helvetica", "bold");
                doc.text("Veículo Utilizado", margin, currentY);
                currentY += 7;

                doc.setFontSize(11);
                doc.setFont("helvetica", "normal");
                doc.text(`Modelo: ${currentVehicleInfo.name}`, margin, currentY);
                currentY += 5;
                doc.text(`Bateria: ${currentVehicleInfo.battery_capacity} kWh`, margin, currentY);
                doc.text(`Consumo Médio: ${currentVehicleInfo.consumption} kWh/100km`, 110, currentY);

                currentY += 8;
            }

            // --- RESUMO DA VIAGEM ---
            doc.setDrawColor(200, 200, 200);
            doc.line(margin, currentY, 210 - margin, currentY);
            currentY += 7;

            doc.setFontSize(14);
            doc.setFont("helvetica", "bold");
            doc.text("Resumo da Viagem", margin, currentY);
            currentY += 7;

            // Criar uma tabela simples para o resumo
            doc.autoTable({
                startY: currentY,
                head: [['Distância Total', 'Tempo Estimado', 'Paradas', 'Custo Estimado']],
                body: [[
                    `${currentReportData.distancia_total_km} km`,
                    `${Math.floor(currentReportData.tempo_conducao_min / 60)}h ${currentReportData.tempo_conducao_min % 60}m`,
                    `${currentReportData.paradas_totais}`,
                    `R$ ${currentReportData.custo_total_estimado}`
                ]],
                theme: 'plain',
                headStyles: { fillColor: [240, 240, 240], textColor: [0, 0, 0], fontStyle: 'bold' },
                styles: { fontSize: 11, cellPadding: 3 },
                margin: { left: margin, right: margin }
            });

            currentY = doc.lastAutoTable.finalY + 10;

            // --- DETALHES DAS PARADAS ---
            doc.setFontSize(14);
            doc.setFont("helvetica", "bold");
            doc.text("Itinerário e Paradas de Recarga", margin, currentY);
            currentY += 4;

            // Preparar dados para a tabela de paradas
            const tableRows = [];

            // Adicionar Origem
            const startAddress = document.getElementById('start-point').value || "Origem";
            tableRows.push(['Início', 'Origem', startAddress, '-', '-', '-']);

            // Processar paradas
            if (currentReportData.charge_stops_details && currentReportData.charge_stops_details.length > 0) {
                currentReportData.charge_stops_details.forEach((stop, index) => {
                    if (stop.is_manual_stop) {
                        // Recuperar o endereço original para o PDF
                        const originalAddress = stop.name || findOriginalStopoverAddress(stop.latitude, stop.longitude);

                        tableRows.push([
                            `Parada #${index + 1}`,
                            'Descanso (Manual)',
                            originalAddress || 'Parada Manual',
                            '-',
                            '-',
                            '-'
                        ]);
                    } else {
                        const isSimulated = stop.is_estimated;
                        const typeStr = isSimulated ? 'Simulada' : (stop.is_from_database ? 'HelioMax' : 'Pública (OCM)');

                        tableRows.push([
                            `Parada #${stop.stop_number || index + 1}`,
                            typeStr,
                            stop.station.name,
                            `${stop.distance_traveled_km} km`,
                            `${stop.charge_at_arrival}% -> ${stop.charge_at_departure}%`,
                            `${Math.round(stop.charge_time / 60)} min`
                        ]);
                    }
                });
            }

            // Adicionar Destino
            const endAddress = document.getElementById('end-point').value || "Destino";
            tableRows.push(['Fim', 'Destino', endAddress, `${currentReportData.distancia_total_km} km`, `${currentReportData.carga_final_pct}%`, '-']);

            doc.autoTable({
                startY: currentY,
                head: [['Tipo', 'Fonte/Status', 'Local / Endereço', 'Dist. Acum.', 'Carga (E/S)', 'Tempo']],
                body: tableRows,
                theme: 'striped', // Listras cinzas claras e brancas (economiza tinta)
                headStyles: { fillColor: [6, 182, 212], textColor: [255, 255, 255], fontStyle: 'bold' },
                styles: { fontSize: 9, cellPadding: 3, valign: 'middle' },
                columnStyles: {
                    0: { cellWidth: 20, fontStyle: 'bold' }, // Tipo
                    2: { cellWidth: 'auto' }, // Endereço (estica)
                    3: { cellWidth: 20, halign: 'center' }, // Dist
                    4: { cellWidth: 25, halign: 'center' }, // Carga
                    5: { cellWidth: 15, halign: 'center' }  // Tempo
                },
                margin: { left: margin, right: margin },
                didParseCell: function (data) {
                    // Destacar linhas de Início e Fim
                    if (data.row.raw[0] === 'Início' || data.row.raw[0] === 'Fim') {
                        data.cell.styles.fontStyle = 'bold';
                    }
                    // Destacar paradas manuais
                    if (data.row.raw[1] === 'Descanso (Manual)') {
                        data.cell.styles.textColor = [202, 138, 4]; // Amarelo escuro
                    }
                }
            });

            // --- RODAPÉ ---
            const pageCount = doc.internal.getNumberOfPages();
            for (let i = 1; i <= pageCount; i++) {
                doc.setPage(i);
                doc.setFontSize(8);
                doc.setTextColor(150, 150, 150);
                doc.text(`Página ${i} de ${pageCount} - HelioMax EVSimulator`, 105, 290, { align: 'center' });
            }

            // Salvar o arquivo
            const fileName = `Rota_HelioMax_${new Date().toISOString().slice(0, 10)}.pdf`;
            doc.save(fileName);
        });
    }

    console.log('🚀 Simulador inicializado com Overlay de Carregamento');
});