// main.js (Versão Híbrida com Autocomplete do Google e PDF Nativo)

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

    // NOVO: Variáveis para armazenar coordenadas e o Geocoder
    let startCoords = null;
    let endCoords = null;
    let geocoder; // Será inicializado após a API do Google carregar

    // --- Elementos do DOM ---
    const startInput = document.getElementById('start-point');
    const endInput = document.getElementById('end-point');
    const simulateBtn = document.getElementById('simulate-button');
    const clearBtn = document.getElementById('clear-button');
    const reportContent = document.getElementById('report-content');
    const loadingSpinner = document.getElementById('loading-spinner');
    const downloadBtn = document.getElementById('download-pdf-button');
    const optimisticModeCheck = document.getElementById('optimistic-mode');

    // NOVO: Inicializa o Geocoder e o Autocomplete
    try {
        geocoder = new google.maps.Geocoder();
        initAutocomplete();
    } catch (e) {
        console.error("Erro ao inicializar a API do Google Maps. Verifique sua chave de API e se a API foi carregada.", e);
        alert("Não foi possível carregar a funcionalidade de busca de endereços. Verifique sua chave de API do Google.");
    }

    // 2. NOVO: Lógica do Geocoder Reverso (Lat/Lng -> Endereço)
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
                // Fallback: mostra coordenadas se o geocoder falhar
                inputElement.value = `${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}`;
            }
        });
    }

    // 3. NOVO: Lógica de arrastar o marcador
    function handleMarkerDrag(event, type) {
        const latlng = event.target.getLatLng();
        if (type === 'start') {
            startCoords = latlng;
            reverseGeocode(latlng, startInput);
        } else {
            endCoords = latlng;
            reverseGeocode(latlng, endInput);
        }
    }

    // 4. ATUALIZADO: Lidar com cliques no mapa
    map.on('click', (e) => {
        const latlng = e.latlng;

        if (!startMarker) {
            startCoords = latlng; // Armazena coords
            startMarker = L.marker(latlng, { draggable: true }).addTo(map).bindPopup('Origem');
            reverseGeocode(latlng, startInput); // Converte para endereço
            startMarker.on('dragend', (ev) => handleMarkerDrag(ev, 'start'));
        } else if (!endMarker) {
            endCoords = latlng; // Armazena coords
            endMarker = L.marker(latlng, { draggable: true }).addTo(map).bindPopup('Destino');
            reverseGeocode(latlng, endInput); // Converte para endereço
            endMarker.on('dragend', (ev) => handleMarkerDrag(ev, 'end'));
        }
    });

    // 5. NOVO: Lógica do Autocomplete (Endereço -> Lat/Lng)
    function initAutocomplete() {
        const options = {
            componentRestrictions: { country: "br" }, // Restringe ao Brasil
            fields: ["formatted_address", "geometry.location"] // Campos que queremos
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

    // 6. NOVO: Função chamada quando um local é selecionado no Autocomplete
    function handlePlaceSelect(autocomplete, type) {
        const place = autocomplete.getPlace();

        if (!place.geometry || !place.geometry.location) {
            console.error("Local selecionado não possui geometria.");
            return;
        }

        const lat = place.geometry.location.lat();
        const lng = place.geometry.location.lng();
        const latlng = { lat: lat, lng: lng };

        if (type === 'start') {
            startCoords = latlng; // Armazena coords
            if (startMarker) {
                startMarker.setLatLng(latlng);
            } else {
                startMarker = L.marker(latlng, { draggable: true }).addTo(map).bindPopup('Origem');
                startMarker.on('dragend', (ev) => handleMarkerDrag(ev, 'start'));
            }
            startInput.value = place.formatted_address; // Garante que o input tenha o endereço
        } else {
            endCoords = latlng; // Armazena coords
            if (endMarker) {
                endMarker.setLatLng(latlng);
            } else {
                endMarker = L.marker(latlng, { draggable: true }).addTo(map).bindPopup('Destino');
                endMarker.on('dragend', (ev) => handleMarkerDrag(ev, 'end'));
            }
            endInput.value = place.formatted_address;
        }
        
        // Foca o mapa no local selecionado
        map.setView(latlng, 15);
    }


    // 7. ATUALIZADO: Botão de Simular
    simulateBtn.addEventListener('click', () => {
        // ATUALIZADO: Verifica as variáveis de coordenadas, não os marcadores/inputs
        if (!startCoords || !endCoords) {
            alert("Por favor, defina um ponto de Origem e um de Destino.");
            return;
        }
        
        if (routeLayer) map.removeLayer(routeLayer);
        chargeStopMarkers.forEach(marker => map.removeLayer(marker));
        chargeStopMarkers = [];
        currentReportData = null;

        loadingSpinner.style.display = 'block';
        reportContent.innerHTML = '<p>Calculando...</p>';
        downloadBtn.style.display = 'none';
        
        const formData = new FormData();
        // ATUALIZADO: Envia as coordenadas armazenadas
        formData.append('start_coords', `${startCoords.lat}, ${startCoords.lng}`);
        formData.append('end_coords', `${endCoords.lat}, ${endCoords.lng}`);
        formData.append('optimistic_mode', optimisticModeCheck.checked);

        fetch('../PHP/simulate_route.php', { method: 'POST', body: formData })
        .then(res => {
            // ... (resto do fetch permanece o mesmo) ...
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
            console.log('=== RESPOSTA COMPLETA DA API ===');
            console.log(JSON.stringify(data, null, 2));
            
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
                displayReport(currentReportData); 

                const chargeStops = currentReportData.charge_stops_details;
                
                if (chargeStops && chargeStops.length > 0) {
                    // ... (lógica para desenhar os marcadores de parada permanece a MESMA) ...
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

                        const iconColor = isEstimated ? '#64748b' : '#ef4444';
                        const iconBg = isEstimated ? '#64748b' : '#ef4444';
                        const popupTitle = isEstimated ? '⚡ Parada Simulada (Planejamento)' : `⚡ Parada ${stop.stop_number}`;
                        const iconSVG = isEstimated ? 
                            `<i data-lucide="brain-circuit" style="width: 20px; height: 20px; color: ${iconColor};"></i>` :
                            `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="${iconColor}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2v4"/><path d="m16 6-4 4-4-4"/><rect width="8" height="12" x="8" y="10" rx="2"/><path d="M8 22v-2c0-1.1.9-2 2-2h4a2 2 0 0 1 2 2v2H8Z"/>
                            </svg>`;

                        const chargeStopIcon = L.divIcon({
                            className: 'custom-charge-stop-icon',
                            html: `
                                <div style="background: white; border-radius: 50%; padding: 4px; border: 3px solid ${iconBg}; box-shadow: 0 4px 12px rgba(0,0,0, 0.4);">
                                    ${iconSVG}
                                </div>
                            `,
                            iconSize: [32, 32],
                            iconAnchor: [16, 16],
                            popupAnchor: [0, -16]
                        });
                        
                        let popupContent = `
                            <div style="min-width: 250px;">
                                <strong style="color: ${iconColor}; font-size: 1.1em;">${popupTitle}</strong><br><br>
                                <div style="background: rgba(34, 211, 238, 0.1); padding: 8px; border-radius: 6px; margin-bottom: 8px;">
                                    <strong style="color: #22d3ee;">📍 ${stop.station.name}</strong><br>
                                    <span style="font-size: 0.85em; color: #94a3b8;">
                                        ${stop.station.address}<br>
                                        ${!isEstimated ? `<em>Avaliação OCM: ${stop.station.rating || 'N/A'}</em>` : ''}
                                    </span>
                                </div>
                                <strong>Conector:</strong> ${stop.station.connector_type}<br>
                                <strong>Potência:</strong> ${stop.charging_power_kw} kW ${isEstimated ? '(Estimada)' : ''}<br>
                                <strong>Distância Percorrida:</strong> ${stop.distance_traveled_km} km<br>
                                <strong>Carga ao Chegar:</strong> ${stop.charge_at_arrival}%<br>
                                <strong>Carga ao Sair:</strong> ${stop.charge_at_departure}%<br>
                                <strong>Tempo de Carga:</strong> ${Math.round(stop.charge_time / 60)} min<br>
                                <strong>Energia Carregada:</strong> ${stop.energy_charged_kwh} kWh
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

    // 8. ATUALIZADO: Botão de Limpar
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
        
        // NOVO: Limpa as coordenadas e os inputs
        startCoords = null;
        endCoords = null;
        startInput.value = '';
        endInput.value = '';
        
        reportContent.innerHTML = '<p>Aguardando simulação...</p>';
        downloadBtn.style.display = 'none';
    });

    // 9. Função para exibir o relatório (permanece a MESMA)
    function displayReport(report) {
        // ... (toda a sua função displayReport original vai aqui, sem alterações) ...
        let html = `
            <p>Distância Total: <strong class="text-cyan-400">${report.distancia_total_km} km</strong></p>
            <p>Tempo de Condução: <strong class="text-cyan-400">${report.tempo_conducao_min} min</strong></p>
            <p>Paradas para Recarga: <strong class="text-cyan-400">${report.paradas_totais}</strong></p>
            <p>Tempo Total Carregando: <strong class="text-cyan-400">${report.tempo_carregamento_min} min</strong></p>
            <hr class="border-cyan-500/20 my-2">
            <p>Energia Consumida: <strong class="text-cyan-400">${report.energia_consumida_total_kwh} kWh</strong></p>
            <p>Custo Estimado: <strong class="text-cyan-400">R$ ${report.custo_total_estimado}</strong></p>
            <p>Carga na Chegada: <strong class="text-cyan-400">${report.carga_final_pct}%</strong></p>
        `;
        
        if (report.charge_stops_details && report.charge_stops_details.length > 0) {
            html += `
                <hr class="border-cyan-500/20 my-3">
                <h4 class="text-cyan-400 font-bold text-sm mb-2 flex items-center gap-2">
                    <i data-lucide="map-pin" class="w-4 h-4"></i>
                    Paradas de Recarga
                </h4>
                <div class="space-y-3 max-h-60 overflow-y-auto">
            `;
            
            report.charge_stops_details.forEach((stop, index) => {
                const isEstimated = stop.is_estimated === true;
                
                const borderColor = isEstimated ? 'border-slate-500/30' : 'border-cyan-500/30';
                const iconBg = isEstimated ? 'bg-slate-500' : 'bg-cyan-500';
                const textColor = isEstimated ? 'text-slate-400' : 'text-cyan-400';
                const icon = isEstimated ? `<i data-lucide="brain-circuit" class="w-4 h-4 text-white"></i>` : `${stop.stop_number}`;
                const ratingText = isEstimated ? 'Parada Simulada' : `⭐ ${stop.station.rating || 'N/A'} • ${stop.station.connector_type}`;

                html += `
                    <div class="bg-slate-900/50 p-3 rounded-lg border ${borderColor} text-xs">
                        <div class="flex items-start gap-2 mb-2">
                            <span class="${iconBg} text-white rounded-full w-5 h-5 flex items-center justify-center flex-shrink-0 text-xs font-bold">${icon}</span>
                            <div class="flex-1">
                                <div class="font-bold ${textColor}">${stop.station.name}</div>
                                <div class="text-gray-400 text-xs">
                                    ${stop.station.address}
                                </div>
                                <div class="text-gray-500 text-xs mt-1">
                                    ${ratingText}
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs mt-2 pt-2 border-t border-slate-700">
                            <div>
                                <span class="text-gray-400">Distância percorrida:</span>
                                <div class="text-white font-semibold">${stop.distance_traveled_km} km</div>
                            </div>
                            <div>
                                <span class="text-gray-400">Tempo de carga:</span>
                                <div class="text-white font-semibold">${Math.round(stop.charge_time / 60)} min</div>
                            </div>
                            <div>
                                <span class="text-gray-400">Carga ao chegar:</span>
                                <div class="text-white font-semibold">${stop.charge_at_arrival}%</div>
                            </div>
                            <div>
                                <span class="text-gray-400">Carga ao sair:</span>
                                <div class="text-white font-semibold">${stop.charge_at_departure}%</div>
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

    // 10. Lógica do Botão de Download (permanece a MESMA)
    downloadBtn.addEventListener('click', () => {
        // ... (toda a sua função de download de PDF original vai aqui, sem alterações) ...
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
            let y = 20; // Posição Y atual

            // Função helper para checar e adicionar nova página
            const checkPageBreak = (spaceNeeded = 20) => {
                if (y + spaceNeeded > pageHeight - margin) { // 15mm de margem inferior
                    doc.addPage();
                    y = 20; // Resetar Y para o topo
                }
            };

            // --- TÍTULO ---
            doc.setFontSize(18);
            doc.setTextColor('#22d3ee'); // Cor Ciano
            doc.setFont('helvetica', 'bold');
            doc.text('Relatório de Rota - Simulador EV', margin, y);
            y += 12;

            // --- RESUMO DA VIAGEM ---
            doc.setFontSize(11);
            doc.setTextColor(40, 40, 40); // Cinza escuro
            
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
                doc.setTextColor(80, 80, 80); // Cinza médio
                doc.text(label, margin, y);
                doc.setFont('helvetica', 'normal');
                doc.setTextColor(40, 40, 40); // Cinza escuro
                doc.text(value, margin + 60, y); // Alinha os valores
                doc.setFont('helvetica', 'bold');
                y += 7;
            }
            y += 5; // Espaço extra

            // --- PARADAS DE RECARGA ---
            if (currentReportData.charge_stops_details && currentReportData.charge_stops_details.length > 0) {
                checkPageBreak(30); // Checa se há espaço para a seção
                doc.setDrawColor('#22d3ee'); // Linha Ciano
                doc.line(margin, y, pageWidth - margin, y); // Linha horizontal
                y += 10;

                checkPageBreak();
                doc.setFontSize(14);
                doc.setTextColor('#22d3ee');
                doc.text('Paradas de Recarga', margin, y);
                y += 8;

                // Loop por cada parada
                for (const stop of currentReportData.charge_stops_details) {
                    checkPageBreak(50); // Checa se há espaço para um bloco de parada
                    
                    const isEstimated = stop.is_estimated === true;
                    const titleColor = isEstimated ? '#64748b' : '#000000'; // Cinza ou Preto
                    const title = isEstimated ? `Parada Simulada (Planejamento)` : `Parada ${stop.stop_number}`;
                    
                    // Título da Parada
                    doc.setFontSize(12);
                    doc.setTextColor(titleColor);
                    doc.setFont('helvetica', 'bold');
                    doc.text(title, margin, y);
                    y += 6;

                    // Nome da Estação
                    doc.setFontSize(10);
                    doc.setTextColor(50, 50, 50);
                    doc.setFont('helvetica', 'bold');
                    doc.text(stop.station.name, margin + 5, y);
                    y += 5;
                    
                    // Endereço
                    doc.setTextColor(120, 120, 120);
                    doc.setFont('helvetica', 'normal');
                    doc.text(stop.station.address, margin + 5, y);
                    y += 5;

                    // Conector/Rating
                    let ratingText = isEstimated ? `Conector: Simulado • Potência: ${stop.charging_power_kw} kW (Estimada)`
                                               : `Conector: ${stop.station.connector_type} • Potência: ${stop.charging_power_kw} kW`;
                    doc.text(ratingText, margin + 5, y);
                    y += 7;

                    // Detalhes (em colunas)
                    doc.setTextColor(80, 80, 80);
                    let col1Y = y;
                    let col2Y = y;
                    
                    doc.setFont('helvetica', 'bold');
                    doc.text('Distância percorrida:', margin + 5, col1Y);
                    doc.setFont('helvetica', 'normal');
                    doc.text(`${stop.distance_traveled_km} km`, margin + 45, col1Y);
                    
                    doc.setFont('helvetica', 'bold');
                    doc.text('Tempo de carga:', margin + 90, col2Y);
                    doc.setFont('helvetica', 'normal');
                    doc.text(`${Math.round(stop.charge_time / 60)} min`, margin + 125, col2Y);
                    
                    col1Y += 6;
                    col2Y += 6;

                    doc.setFont('helvetica', 'bold');
                    doc.text('Carga ao chegar:', margin + 5, col1Y);
                    doc.setFont('helvetica', 'normal');
                    doc.text(`${stop.charge_at_arrival}%`, margin + 45, col1Y);

                    doc.setFont('helvetica', 'bold');
                    doc.text('Carga ao sair:', margin + 90, col2Y);
                    doc.setFont('helvetica', 'normal');
                    doc.text(`${stop.charge_at_departure}%`, margin + 125, col2Y);

                    y = col1Y + 10; // Move Y para baixo do bloco
                    
                    // Linha separadora
                    doc.setDrawColor(220, 220, 220); // Cinza claro
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

});