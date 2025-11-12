// simulador.js - Versão Completa com Pontos do Banco de Dados + OCM + Seleção de Veículos

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

    let startCoords = null;
    let endCoords = null;
    let geocoder;
    let cachedFavorites = [];

    // NOVAS VARIÁVEIS PARA VEÍCULOS
    let userVehicles = []; // Array para armazenar veículos do usuário
    let selectedVehicle = null; // Veículo atualmente selecionado

    // --- Elementos do DOM ---
    const startInput = document.getElementById('start-point');
    const endInput = document.getElementById('end-point');
    const simulateBtn = document.getElementById('simulate-button');
    const clearBtn = document.getElementById('clear-button');
    const reportContent = document.getElementById('report-content');
    const loadingSpinner = document.getElementById('loading-spinner');
    const downloadBtn = document.getElementById('download-pdf-button');
    const optimisticModeCheck = document.getElementById('optimistic-mode');

    const startFavoriteControl = document.getElementById('start-favorite-control');
    const endFavoriteControl = document.getElementById('end-favorite-control');
    const startFavoriteSelect = document.getElementById('start-favorite-select');
    const endFavoriteSelect = document.getElementById('end-favorite-select');

    // NOVOS ELEMENTOS DOM DE VEÍCULOS
    const vehicleSelect = document.getElementById('vehicle-select');
    const vehicleDisplayText = document.getElementById('vehicle-display-text');
    const vehicleInfo = document.getElementById('vehicle-info');

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

    // 2. Geocoder Reverso (Lat/Lng -> Endereço)
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

    // 3. Lógica de arrastar o marcador
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

    // 4. Lidar com cliques no mapa
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

    // 5. Lógica do Autocomplete 
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

    // 6. Função chamada quando um local é selecionado no Autocomplete 
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

    // 7. Botão de Simular
    simulateBtn.addEventListener('click', () => {
        if (!startCoords || !endCoords) {
            alert("Por favor, defina um ponto de Origem e um de Destino.");
            return;
        }

        if (routeLayer) map.removeLayer(routeLayer);
        chargeStopMarkers.forEach(marker => map.removeLayer(marker));
        chargeStopMarkers = [];
        currentReportData = null;

        loadingSpinner.style.display = 'block';
        reportContent.innerHTML = '<p class="text-gray-400">Calculando...</p>';
        downloadBtn.style.display = 'none';

        const formData = new FormData();
        formData.append('start_coords', `${startCoords.lat}, ${startCoords.lng}`);
        formData.append('end_coords', `${endCoords.lat}, ${endCoords.lng}`);
        formData.append('optimistic_mode', optimisticModeCheck.checked);

        // ENVIO DO ID DO VEÍCULO
        const vehicleId = vehicleSelect ? vehicleSelect.value : 'default';
        formData.append('vehicle_id', vehicleId);

        fetch('../PHP/simulate_route.php', { method: 'POST', body: formData })
            .then(res => {
                if (!res.ok) { throw new Error(`Erro de Servidor: ${res.status} ${res.statusText}`); }
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
                loadingSpinner.style.display = 'none';

                if (data.success) {
                    currentReportData = data.report;
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

                    // Lógica de mapeamento de marcadores de parada
                    const chargeStops = currentReportData.charge_stops_details;
                    if (chargeStops && chargeStops.length > 0) {
                        chargeStops.forEach((stop, index) => {
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

                            let iconColor, iconBg, popupTitle, badgeColor, badgeText;

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
                                <div style="background: white; border-radius: 50%; padding: 4px; border: 3px solid ${iconBg}; box-shadow: 0 4px 12px rgba(0,0,0, 0.4);">
                                    <div style="background: ${iconBg}; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                        ${iconSVG}
                                    </div>
                                </div>
                            `,
                                iconSize: [40, 40],
                                iconAnchor: [20, 20],
                                popupAnchor: [0, -20]
                            });

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
                                        ${!isEstimated ? `<em>Avaliação: ${stop.station.rating}</em>` : ''}
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
                    reportContent.innerHTML = `<p style="color: #ef4444;">Erro: ${data.message}</p>`;
                }
            })
            .catch(err => {
                loadingSpinner.style.display = 'none';
                reportContent.innerHTML = `<p style="color: #ef4444;">Erro de conexão: ${err.message}</p>`;
                console.error('Erro na requisição:', err);
            });
    });

    // 8. Botão de Limpar
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

        reportContent.innerHTML = '<p class="text-gray-400">Aguardando simulação...</p>';
        downloadBtn.style.display = 'none';
    });

    // 9. Função de Carregar Favoritos da API
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
                        const name = fav.NOME;

                        if (!isNaN(lat) && !isNaN(lng) && name) {
                            const option = document.createElement('option');
                            option.value = `${lat},${lng}|${name}`;
                            option.textContent = name;

                            if (startFavoriteSelect) startFavoriteSelect.appendChild(option.cloneNode(true));
                            if (endFavoriteSelect) endFavoriteSelect.appendChild(option.cloneNode(true));
                        } else {
                            console.warn("Ponto favorito ignorado (dados incompletos ou inválidos):", fav);
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

    // ==================== SEÇÃO DE VEÍCULOS ====================

    // Carregar veículos do usuário
    async function loadUserVehicles() {
        if (!vehicleSelect) {
            console.error('❌ Elemento #vehicle-select não encontrado no DOM.');
            return;
        }

        try {
            console.log('🚗 Iniciando carregamento de veículos...');

            const response = await fetch('../PHP/get_user_vehicles.php');
            console.log('📡 Response status:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('📦 Dados recebidos:', data);

            if (data.success && data.veiculos && Array.isArray(data.veiculos)) {
                if (data.veiculos.length > 0) {
                    console.log(`✅ ${data.veiculos.length} veículo(s) encontrado(s)`);
                    userVehicles = data.veiculos;
                    populateVehicleSelect();

                    // Selecionar automaticamente o primeiro veículo
                    if (userVehicles.length > 0) {
                        const firstVehicle = userVehicles[0];
                        vehicleSelect.value = firstVehicle.id;
                        updateSelectedVehicle(firstVehicle);
                    }
                } else {
                    console.warn('⚠️ Nenhum veículo cadastrado para este usuário.');
                    showDefaultVehicleOnly();
                }
            } else {
                console.error('❌ Erro na resposta:', data.message || 'Formato inválido');
                showDefaultVehicleOnly();
            }
        } catch (error) {
            console.error('💥 Erro ao carregar veículos:', error);
            console.error('🔍 Stack trace:', error.stack);
            showDefaultVehicleOnly();
        }
    }

    // Popular Select de Veículos
    function populateVehicleSelect() {
        if (!vehicleSelect) {
            console.error('❌ vehicleSelect não está definido');
            return;
        }

        console.log('🔄 Populando select com', userVehicles.length, 'veículos');

        // Limpar todas as opções EXCETO a primeira (Padrão)
        while (vehicleSelect.options.length > 1) {
            vehicleSelect.remove(1);
        }

        // Adicionar veículos do usuário
        userVehicles.forEach((vehicle, index) => {
            const option = document.createElement('option');
            // Corrigido: o value precisa ser o ID para a seleção automática funcionar
            option.value = vehicle.id;
            option.textContent = `${vehicle.nome_completo} - ${vehicle.placa} (${vehicle.ano})`;
            option.dataset.vehicleData = JSON.stringify(vehicle);
            vehicleSelect.appendChild(option);

            console.log(`✅ Veículo ${index + 1} adicionado:`, vehicle.nome_completo);
        });

        console.log('✅ Select populado! Total de opções:', vehicleSelect.options.length);
    }

    // Mostrar apenas o veículo padrão
    function showDefaultVehicleOnly() {
        console.log('ℹ️ Mantendo apenas veículo padrão');
        while (vehicleSelect.options.length > 1) {
            vehicleSelect.remove(1);
        }
        vehicleSelect.value = 'default';
        updateSelectedVehicle(null);
    }

    // Atualizar informações do veículo selecionado na tela
    function updateSelectedVehicle(vehicle) {
        selectedVehicle = vehicle;

        const vehicleInfoDiv = document.getElementById('vehicle-info');

        if (vehicle) {
            // Calcular autonomia atual
            const autonomiaAtual = (vehicle.nivel_bateria / 100) * vehicle.capacidade_bateria / vehicle.consumo_medio * 100;

            // Atualizar texto principal
            if (vehicleDisplayText) {
                vehicleDisplayText.innerHTML = `
                    <strong class="text-cyan-300">${vehicle.nome_completo}</strong>
                `;
            }

            // Mostrar informações detalhadas
            if (vehicleInfoDiv) {
                vehicleInfoDiv.classList.remove('hidden');
                document.getElementById('vehicle-battery').textContent = `${vehicle.capacidade_bateria} kWh`;
                document.getElementById('vehicle-consumption').textContent = `${vehicle.consumo_medio} kWh/100km`;
                document.getElementById('vehicle-charge').textContent = `${vehicle.nivel_bateria}%`;
                document.getElementById('vehicle-range').textContent = `~${Math.round(autonomiaAtual)} km`;
            }

            console.log('✅ Veículo selecionado:', vehicle.nome_completo);
        } else {
            // Veículo padrão
            if (vehicleDisplayText) {
                vehicleDisplayText.textContent = 'Tesla Model 3 (Padrão)';
            }

            if (vehicleInfoDiv) {
                vehicleInfoDiv.classList.add('hidden');
            }

            console.log('ℹ️ Usando veículo padrão');
        }
    }

    // Listener para mudança de veículo
    if (vehicleSelect) {
        vehicleSelect.addEventListener('change', function () {
            const value = this.value;
            console.log('🔄 Veículo alterado para:', value);

            if (value === 'default' || !value) {
                updateSelectedVehicle(null);
            } else {
                const selectedOption = this.options[this.selectedIndex];
                try {
                    // O data-vehicle-data deve conter os dados completos do veículo
                    const vehicleData = JSON.parse(selectedOption.dataset.vehicleData);
                    updateSelectedVehicle(vehicleData);
                } catch (e) {
                    console.error("❌ Erro ao parsear dados do veículo:", e);
                    // Procura o veículo no array userVehicles como fallback
                    const fallbackVehicle = userVehicles.find(v => String(v.id) === value);
                    updateSelectedVehicle(fallbackVehicle || null);
                }
            }
        });

        console.log('✅ Event listener do select configurado');
    } else {
        console.error('❌ Elemento vehicleSelect não encontrado no carregamento');
    }

    // 10. Função para configurar Origem/Destino (unificada)
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

    // Event Listeners para os Dropdowns de Favoritos
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

    // 11. Função de Relatório
    function displayReport(report, vehicleInfo = null) {
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

        let html = `
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

        if (report.charge_stops_details && report.charge_stops_details.length > 0) {
            html += `
                <hr class="border-cyan-500/20 my-3">
                <h4 class="text-cyan-400 font-bold text-sm mb-3 flex items-center gap-2">
                    <i data-lucide="map-pin" class="w-4 h-4"></i>
                    Detalhes das Paradas
                </h4>
                <div class="space-y-3 max-h-60 overflow-y-auto pr-2">
            `;

            report.charge_stops_details.forEach((stop, index) => {
                const isEstimated = stop.is_estimated === true;
                const isFromDatabase = stop.is_from_database === true;

                let borderColor, iconBg, textColor, icon, badgeColor, badgeText;

                if (isEstimated) {
                    borderColor = 'border-slate-500/30';
                    iconBg = 'bg-slate-500';
                    textColor = 'text-slate-400';
                    icon = `<i data-lucide="brain-circuit" class="w-4 h-4 text-white"></i>`;
                    badgeColor = 'bg-slate-500';
                    badgeText = 'Simulada';
                } else if (isFromDatabase) {
                    borderColor = 'border-green-500/30';
                    iconBg = 'bg-green-500';
                    textColor = 'text-green-400';
                    icon = `${stop.stop_number}`;
                    badgeColor = 'bg-green-500';
                    badgeText = 'HelioMax';
                } else {
                    borderColor = 'border-cyan-500/30';
                    iconBg = 'bg-cyan-500';
                    textColor = 'text-cyan-400';
                    icon = `${stop.stop_number}`;
                    badgeColor = 'bg-red-500';
                    badgeText = 'OCM';
                }

                const ratingText = isEstimated ? 'Parada Simulada' :
                    isFromDatabase ? `HelioMax • ${stop.station.connector_type}` :
                        `⭐ ${stop.station.rating || 'N/A'} • ${stop.station.connector_type}`;

                html += `
                    <div class="bg-slate-900/50 p-3 rounded-lg border ${borderColor}">
                        <div class="flex items-start gap-2 mb-2">
                            <span class="${iconBg} text-white rounded-full w-6 h-6 flex items-center justify-center flex-shrink-0 text-xs font-bold">
                                ${icon}
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="font-bold ${textColor} text-xs truncate">${stop.station.name}</div>
                                    <span class="${badgeColor} text-white text-xs px-2 py-0.5 rounded-full font-bold">${badgeText}</span>
                                </div>
                                <div class="text-gray-400 text-xs line-clamp-2">${stop.station.address}</div>
                            </div>
                        </div>
                        
                        <div class="mt-2 pt-2 border-t border-slate-700 space-y-1.5 text-xs">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400">Distância:</span>
                                <strong class="text-white">${stop.distance_traveled_km} km</strong>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400">Tempo Carga:</span>
                                <strong class="text-white">${Math.round(stop.charge_time / 60)} min</strong>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-gray-400">Chegada/Saída:</span>
                                <strong class="text-white">${stop.charge_at_arrival}% / ${stop.charge_at_departure}%</strong>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-gray-400">Energia:</span>
                                <strong class="text-white">${stop.energy_charged_kwh} kWh</strong>
                            </div>

                            <div class="text-gray-500 pt-1.5 border-t border-slate-700/50 text-xs">
                                ${ratingText}
                            </div>
                        </div>
                    </div>
                `;
            });

            html += `</div>`;
        }

        reportContent.innerHTML = html;
        lucide.createIcons();
    }

    // 12. Função de Download PDF
    downloadBtn.addEventListener('click', () => {
        if (!currentReportData) {
            alert("Não há dados de relatório para baixar. Por favor, simule uma rota primeiro.");
            return;
        }

        loadingSpinner.style.display = 'block';

        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');
            const pageHeight = doc.internal.pageSize.getHeight();
            const pageWidth = doc.internal.pageSize.getWidth();
            const margin = 15;
            let y = 20;

            const checkPageBreak = (spaceNeeded = 20) => {
                if (y + spaceNeeded > pageHeight - margin) {
                    doc.addPage();
                    y = 20;
                }
            };

            doc.setFontSize(18);
            doc.setTextColor('#22d3ee');
            doc.setFont('helvetica', 'bold');
            doc.text('Relatório de Rota - Simulador EV', margin, y);
            y += 12;

            doc.setFontSize(11);
            doc.setTextColor(40, 40, 40);

            const summary = [
                [`Distância Total:`, `${currentReportData.distancia_total_km} km`],
                [`Tempo de Condução:`, `${currentReportData.tempo_conducao_min} min`],
                [`Paradas para Recarga:`, `${currentReportData.paradas_totais}`],
                [`Tempo Total Carregando:`, `${currentReportData.tempo_carregamento_min} min`],
                [`Energia Consumida:`, `${currentReportData.energia_consumida_total_kwh} kWh`],
                [`Custo Estimado:`, `R$ ${currentReportData.custo_total_estimado}`],
                [`Carga na Chegada:`, `${currentReportData.carga_final_pct}%`]
            ];

            doc.setFont('helvetica', 'bold');
            for (const [label, value] of summary) {
                doc.setTextColor(80, 80, 80);
                doc.text(label, margin, y);
                doc.setFont('helvetica', 'normal');
                doc.setTextColor(40, 40, 40);
                doc.text(value, margin + 60, y);
                doc.setFont('helvetica', 'bold');
                y += 7;
            }
            y += 5;

            if (currentReportData.charge_stops_details && currentReportData.charge_stops_details.length > 0) {
                checkPageBreak(30);
                doc.setDrawColor('#22d3ee');
                doc.line(margin, y, pageWidth - margin, y);
                y += 10;

                checkPageBreak();
                doc.setFontSize(14);
                doc.setTextColor('#22d3ee');
                doc.text('Paradas de Recarga', margin, y);
                y += 8;

                const lineSpacing = 6;
                const col2X = 100;
                const valueX1 = margin + 45;
                const valueX2 = col2X + 35;

                for (const stop of currentReportData.charge_stops_details) {
                    checkPageBreak(50);

                    const isEstimated = stop.is_estimated === true;
                    const isFromDatabase = stop.is_from_database === true;

                    let titleColor, title;
                    if (isEstimated) {
                        titleColor = '#64748b';
                        title = `Parada Simulada (Planejamento)`;
                    } else if (isFromDatabase) {
                        titleColor = '#10b981';
                        title = `Parada ${stop.stop_number} (HelioMax)`;
                    } else {
                        titleColor = '#000000';
                        title = `Parada ${stop.stop_number} (OCM)`;
                    }

                    doc.setFontSize(12);
                    doc.setTextColor(titleColor);
                    doc.setFont('helvetica', 'bold');
                    doc.text(title, margin, y);
                    y += 6;

                    doc.setFontSize(10);
                    doc.setTextColor(50, 50, 50);
                    doc.setFont('helvetica', 'bold');
                    doc.text(stop.station.name, margin + 5, y);
                    y += 5;

                    doc.setTextColor(120, 120, 120);
                    doc.setFont('helvetica', 'normal');
                    doc.text(stop.station.address, margin + 5, y);
                    y += 5;

                    let ratingText = isEstimated ? `Conector: Simulado • Potência: ${stop.charging_power_kw} kW (Estimada)`
                        : `Conector: ${stop.station.connector_type} • Potência: ${stop.charging_power_kw} kW`;
                    doc.text(ratingText, margin + 5, y);
                    y += 7;

                    doc.setTextColor(80, 80, 80);
                    let currentRowY = y;

                    doc.setFont('helvetica', 'bold');
                    doc.text('Distância percorrida:', margin + 5, currentRowY);
                    doc.setFont('helvetica', 'normal');
                    doc.text(`${stop.distance_traveled_km} km`, valueX1, currentRowY);

                    doc.setFont('helvetica', 'bold');
                    doc.text('Tempo de carga:', col2X, currentRowY);
                    doc.setFont('helvetica', 'normal');
                    doc.text(`${Math.round(stop.charge_time / 60)} min`, valueX2, currentRowY);

                    currentRowY += lineSpacing;

                    doc.setFont('helvetica', 'bold');
                    doc.text('Carga ao chegar:', margin + 5, currentRowY);
                    doc.setFont('helvetica', 'normal');
                    doc.text(`${stop.charge_at_arrival}%`, valueX1, currentRowY);

                    doc.setFont('helvetica', 'bold');
                    doc.text('Carga ao sair:', col2X, currentRowY);
                    doc.setFont('helvetica', 'normal');
                    doc.text(`${stop.charge_at_departure}%`, valueX2, currentRowY);

                    y = currentRowY + 10;

                    doc.setDrawColor(220, 220, 220);
                    doc.line(margin, y - 4, pageWidth - margin, y - 4);
                }
            }

            doc.save('relatorio-rota-ev.pdf');

        } catch (err) {
            alert("Erro ao gerar PDF:" + err.message);
            console.error(err);
        } finally {
            loadingSpinner.style.display = 'none';
        }
    });

    // ==================== INICIALIZAÇÃO ====================

    // Carregar dados ao iniciar
    loadFavoritesFromApi();
    loadUserVehicles(); // CARREGA OS VEÍCULOS

    console.log('🚀 Simulador inicializado');
});