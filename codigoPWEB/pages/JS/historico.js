// historico.js - Com PDF idêntico ao Simulador e nomes de paradas corretos

document.addEventListener('DOMContentLoaded', () => {
    // Elementos DOM
    const routesList = document.getElementById('routes-list');
    const loadingSpinner = document.getElementById('loading-spinner');
    const emptyState = document.getElementById('empty-state');
    const filterPeriod = document.getElementById('filter-period');
    const filterPeriodMobile = document.getElementById('filter-period-mobile');
    const modal = document.getElementById('modal-details');
    const modalContent = document.getElementById('modal-content');
    const modalBody = document.getElementById('modal-body');
    const btnCloseModal = document.getElementById('btn-close-modal');

    // Estatísticas
    const statTotalRoutes = document.getElementById('stat-total-routes');
    const statTotalDistance = document.getElementById('stat-total-distance');
    const statTotalEnergy = document.getElementById('stat-total-energy');
    const statTotalCost = document.getElementById('stat-total-cost');

    let allRoutes = [];
    let filteredRoutes = [];

    // Carregar histórico ao iniciar
    loadHistory();

    // Event Listeners
    filterPeriod.addEventListener('change', filterRoutes);
    filterPeriodMobile.addEventListener('change', (e) => {
        filterPeriod.value = e.target.value;
        filterRoutes();
    });

    btnCloseModal.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    // Event delegation
    routesList.addEventListener('click', (e) => {
        const viewBtn = e.target.closest('.btn-view-details');
        const deleteBtn = e.target.closest('.btn-delete-route');

        if (viewBtn) {
            const routeId = viewBtn.dataset.id;
            openRouteDetails(routeId);
        }

        if (deleteBtn) {
            const routeId = deleteBtn.dataset.id;
            deleteRoute(routeId);
        }
    });

    // Delegar evento do botão de PDF dentro do modal (pois é criado dinamicamente)
    modalBody.addEventListener('click', (e) => {
        const pdfBtn = e.target.closest('#btn-download-history-pdf');
        if (pdfBtn) {
            const routeId = pdfBtn.dataset.id;
            const route = allRoutes.find(r => r.ID_HISTORICO == routeId);
            if (route) {
                generateHistoryPDF(route);
            }
        }
    });

    // --- FUNÇÕES DE CARREGAMENTO E DADOS ---

    async function loadHistory() {
        try {
            loadingSpinner.classList.remove('hidden');
            routesList.classList.add('hidden');
            emptyState.classList.add('hidden');

            const response = await fetch('../PHP/api_historico.php?action=list');
            const data = await response.json();

            if (data.success && data.routes && data.routes.length > 0) {
                allRoutes = data.routes;
                filteredRoutes = allRoutes;
                updateStatistics(allRoutes);
                renderRoutes(filteredRoutes);

                loadingSpinner.classList.add('hidden');
                routesList.classList.remove('hidden');
            } else {
                loadingSpinner.classList.add('hidden');
                emptyState.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Erro ao carregar histórico:', error);
            loadingSpinner.classList.add('hidden');
            emptyState.classList.remove('hidden');
        }
        lucide.createIcons();
    }

    function filterRoutes() {
        const period = filterPeriod.value;
        const now = new Date();

        if (period === 'all') {
            filteredRoutes = allRoutes;
        } else {
            filteredRoutes = allRoutes.filter(route => {
                const routeDate = new Date(route.DATA_SIMULACAO);
                switch (period) {
                    case 'today': return routeDate.toDateString() === now.toDateString();
                    case 'week': return routeDate >= new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                    case 'month': return routeDate >= new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
                    default: return true;
                }
            });
        }
        updateStatistics(filteredRoutes);
        renderRoutes(filteredRoutes);
        filterPeriodMobile.value = period;
        lucide.createIcons();
    }

    function updateStatistics(routes) {
        const totalRoutes = routes.length;
        const totalDistance = routes.reduce((sum, route) => sum + parseFloat(route.DISTANCIA_TOTAL_KM), 0);
        const totalEnergy = routes.reduce((sum, route) => sum + parseFloat(route.ENERGIA_CONSUMIDA_KWH), 0);
        const totalCost = routes.reduce((sum, route) => sum + parseFloat(route.CUSTO_TOTAL), 0);

        statTotalRoutes.textContent = totalRoutes;
        statTotalDistance.textContent = `${totalDistance.toFixed(1)} km`;
        statTotalEnergy.textContent = `${totalEnergy.toFixed(1)} kWh`;
        statTotalCost.textContent = `R$ ${totalCost.toFixed(2)}`;
    }

    // --- FUNÇÕES DE PARSING DE DADOS ---

    function parseStopsData(jsonString) {
        if (!jsonString) return { charge: [], manual: [] };
        try {
            const data = JSON.parse(jsonString);
            if (Array.isArray(data)) {
                // Formato antigo (apenas array de recargas)
                return { charge: data, manual: [] };
            } else {
                // Novo formato (objeto com arrays)
                return {
                    charge: data.charge_stops || [],
                    manual: data.manual_stops || []
                };
            }
        } catch (e) {
            console.error("Erro ao fazer parse das paradas", e);
            return { charge: [], manual: [] };
        }
    }

    // --- RENDERIZAÇÃO ---

    function renderRoutes(routes) {
        if (routes.length === 0) {
            routesList.classList.add('hidden');
            emptyState.classList.remove('hidden');
            return;
        }
        routesList.classList.remove('hidden');
        emptyState.classList.add('hidden');
        routesList.innerHTML = routes.map(route => createRouteCard(route)).join('');
    }

    function createRouteCard(route) {
        const date = new Date(route.DATA_SIMULACAO);
        const formattedDate = date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

        const vehicleName = route.VEICULO_NOME || 'Veículo Padrão';
        const modeClass = route.MODO_OTIMISTA ? 'bg-purple-500/20 text-purple-400' : 'bg-green-500/20 text-green-400';
        const modeText = route.MODO_OTIMISTA ? 'Planejamento' : 'Realista';

        // Extrair contagens
        const stopsData = parseStopsData(route.DADOS_PARADAS);
        const chargeCount = stopsData.charge.length;
        const manualCount = stopsData.manual.length;

        let paradasText = `${chargeCount}`;
        if (manualCount > 0) {
            paradasText += ` (+${manualCount} man.)`;
        }

        return `
            <article class="route-card bg-slate-900/50 p-4 sm:p-5 rounded-xl border border-slate-700 hover:border-cyan-500/50 transition-all duration-300">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-cyan-500 to-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i data-lucide="map" class="w-6 h-6 text-white"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">${formattedDate}</p>
                            <p class="text-sm font-semibold text-cyan-400">${vehicleName}</p>
                        </div>
                    </div>
                    <span class="${modeClass} text-xs px-3 py-1 rounded-full font-bold">${modeText}</span>
                </div>

                <div class="space-y-2 mb-4">
                    <div class="flex items-start gap-2">
                        <i data-lucide="map-pin" class="w-4 h-4 text-green-400 mt-0.5 flex-shrink-0"></i>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-gray-500">Origem</p>
                            <p class="text-sm text-white truncate">${route.ORIGEM_ENDERECO}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <i data-lucide="flag" class="w-4 h-4 text-red-400 mt-0.5 flex-shrink-0"></i>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-gray-500">Destino</p>
                            <p class="text-sm text-white truncate">${route.DESTINO_ENDERECO}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 text-xs">
                    <div class="bg-slate-800/50 p-2 rounded-lg">
                        <p class="text-gray-400 mb-1">Distância</p>
                        <p class="text-white font-semibold">${parseFloat(route.DISTANCIA_TOTAL_KM).toFixed(1)} km</p>
                    </div>
                    <div class="bg-slate-800/50 p-2 rounded-lg">
                        <p class="text-gray-400 mb-1">Paradas</p>
                        <p class="text-white font-semibold">${paradasText}</p>
                    </div>
                    <div class="bg-slate-800/50 p-2 rounded-lg">
                        <p class="text-gray-400 mb-1">Energia</p>
                        <p class="text-white font-semibold">${parseFloat(route.ENERGIA_CONSUMIDA_KWH).toFixed(1)} kWh</p>
                    </div>
                    <div class="bg-slate-800/50 p-2 rounded-lg">
                        <p class="text-gray-400 mb-1">Custo</p>
                        <p class="text-white font-semibold">R$ ${parseFloat(route.CUSTO_TOTAL).toFixed(2)}</p>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button class="btn-view-details flex-1 bg-cyan-600/20 text-cyan-400 hover:bg-cyan-600 hover:text-white py-2 px-4 rounded-lg text-sm font-semibold transition-all flex items-center justify-center gap-2"
                        data-id="${route.ID_HISTORICO}">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                        <span class="hidden sm:inline">Ver Detalhes</span>
                        <span class="sm:hidden">Detalhes</span>
                    </button>
                    <button class="btn-delete-route bg-red-600/20 text-red-400 hover:bg-red-600 hover:text-white py-2 px-4 rounded-lg text-sm font-semibold transition-all"
                        data-id="${route.ID_HISTORICO}" title="Excluir rota">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
            </article>
        `;
    }

    // --- MODAL DETALHES ---

    async function openRouteDetails(routeId) {
        try {
            const response = await fetch(`../PHP/api_historico.php?action=details&id=${routeId}`);
            const data = await response.json();

            if (data.success && data.route) {
                const route = data.route;
                const stopsData = parseStopsData(route.DADOS_PARADAS);

                const date = new Date(route.DATA_SIMULACAO);
                const formattedDate = date.toLocaleDateString('pt-BR', {
                    day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit'
                });

                // HTML das Paradas
                let paradasHTML = '';

                // 1. Renderizar Paradas Manuais (se houver)
                if (stopsData.manual.length > 0) {
                    paradasHTML += `
                        <div class="mt-6 mb-4">
                            <h3 class="text-md font-bold text-yellow-400 mb-3 flex items-center gap-2">
                                <i data-lucide="coffee" class="w-4 h-4"></i>
                                Paradas Manuais / Descanso (${stopsData.manual.length})
                            </h3>
                            <div class="space-y-3">
                                ${stopsData.manual.map((parada, idx) => `
                                    <div class="bg-slate-800/50 p-3 rounded-lg border border-yellow-500/30">
                                        <div class="flex items-start gap-2">
                                            <span class="bg-yellow-500 text-slate-900 rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold flex-shrink-0">
                                                ${idx + 1}
                                            </span>
                                            <div class="flex-1">
                                                <p class="font-bold text-yellow-400 text-sm">Parada Manual</p>
                                                <p class="text-gray-400 text-xs mt-1 font-medium text-yellow-100/80">${parada.name || 'Localização Personalizada'}</p>
                                            </div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                }

                // 2. Renderizar Paradas de Recarga
                if (stopsData.charge.length > 0) {
                    paradasHTML += `
                        <div class="mt-6">
                            <h3 class="text-md font-bold text-cyan-400 mb-3 flex items-center gap-2">
                                <i data-lucide="zap" class="w-4 h-4"></i>
                                Paradas de Recarga (${stopsData.charge.length})
                            </h3>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                ${stopsData.charge.map((parada, index) => createParadaCard(parada, index)).join('')}
                            </div>
                        </div>
                    `;
                }

                modalBody.innerHTML = `
                    <div class="space-y-6">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm text-gray-400 mb-1">${formattedDate}</p>
                                <div class="flex items-center gap-2">
                                    <i data-lucide="car" class="w-5 h-5 text-cyan-400"></i>
                                    <span class="text-lg font-semibold text-white">${route.VEICULO_NOME || 'Veículo Padrão'}</span>
                                </div>
                            </div>
                            <button id="btn-download-history-pdf" data-id="${route.ID_HISTORICO}" 
                                class="flex items-center gap-2 bg-cyan-600 hover:bg-cyan-500 text-white px-4 py-2 rounded-lg text-sm font-bold transition-colors">
                                <i data-lucide="download" class="w-4 h-4"></i>
                                Baixar PDF
                            </button>
                        </div>

                        <div class="bg-slate-900/50 p-4 rounded-xl space-y-3 border border-slate-700">
                            <div class="flex items-start gap-3">
                                <i data-lucide="map-pin" class="w-5 h-5 text-green-400 mt-0.5"></i>
                                <div>
                                    <p class="text-xs text-gray-400">Origem</p>
                                    <p class="text-white text-sm">${route.ORIGEM_ENDERECO}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i data-lucide="flag" class="w-5 h-5 text-red-400 mt-0.5"></i>
                                <div>
                                    <p class="text-xs text-gray-400">Destino</p>
                                    <p class="text-white text-sm">${route.DESTINO_ENDERECO}</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div class="bg-slate-900/50 p-3 rounded-xl border border-slate-700/50">
                                <p class="text-gray-400 text-xs mb-1">Distância</p>
                                <p class="text-lg font-bold text-white">${parseFloat(route.DISTANCIA_TOTAL_KM).toFixed(1)} km</p>
                            </div>
                            <div class="bg-slate-900/50 p-3 rounded-xl border border-slate-700/50">
                                <p class="text-gray-400 text-xs mb-1">Tempo Total</p>
                                <p class="text-lg font-bold text-white">${Math.floor(route.TEMPO_CONDUCAO_MIN / 60)}h ${route.TEMPO_CONDUCAO_MIN % 60}m</p>
                            </div>
                            <div class="bg-slate-900/50 p-3 rounded-xl border border-slate-700/50">
                                <p class="text-gray-400 text-xs mb-1">Custo</p>
                                <p class="text-lg font-bold text-yellow-400">R$ ${parseFloat(route.CUSTO_TOTAL).toFixed(2)}</p>
                            </div>
                        </div>

                        ${paradasHTML}
                    </div>
                `;

                modal.classList.remove('hidden');
                modal.classList.add('flex');
                setTimeout(() => { modalContent.classList.remove('opacity-0', 'scale-95'); }, 10);
                lucide.createIcons();
            }
        } catch (error) {
            console.error('Erro ao carregar detalhes:', error);
            alert('Erro ao carregar detalhes da rota.');
        }
    }

    function createParadaCard(parada, index) {
        const isEstimated = parada.is_estimated === true;
        const isFromDatabase = parada.is_from_database === true;

        let badgeClass = isEstimated ? 'bg-slate-500' : (isFromDatabase ? 'bg-green-500' : 'bg-red-500');
        let badgeText = isEstimated ? 'Simulada' : (isFromDatabase ? 'HelioMax' : 'OCM');
        let iconBg = isEstimated ? 'bg-slate-500' : (isFromDatabase ? 'bg-green-500' : 'bg-cyan-500');

        return `
            <div class="bg-slate-800/50 p-3 rounded-lg border border-slate-700">
                <div class="flex items-start gap-2 mb-2">
                    <span class="${iconBg} text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold flex-shrink-0">
                        ${isEstimated ? '?' : index + 1}
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-bold text-cyan-400 text-sm truncate">${parada.station.name}</p>
                            <span class="${badgeClass} text-white text-xs px-2 py-0.5 rounded-full font-bold">${badgeText}</span>
                        </div>
                        <p class="text-gray-400 text-xs truncate">${parada.station.address}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs border-t border-slate-700/50 pt-2 mt-2">
                    <div><span class="text-gray-400">Chegada:</span> <span class="text-white">${parada.charge_at_arrival}%</span></div>
                    <div><span class="text-gray-400">Tempo:</span> <span class="text-white">${Math.round(parada.charge_time / 60)} min</span></div>
                </div>
            </div>
        `;
    }

    function closeModal() {
        modalContent.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }

    async function deleteRoute(routeId) {
        if (!confirm('Tem certeza que deseja excluir esta rota?')) return;
        try {
            const response = await fetch('../PHP/api_historico.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: routeId })
            });
            const data = await response.json();
            if (data.success) {
                allRoutes = allRoutes.filter(route => route.ID_HISTORICO !== parseInt(routeId));
                filterRoutes();
            } else {
                alert('Erro ao excluir: ' + data.message);
            }
        } catch (error) {
            console.error('Erro ao excluir:', error);
        }
    }

    // --- GERAÇÃO DE PDF DO HISTÓRICO (AGORA IDÊNTICO AO SIMULADOR) ---

    function generateHistoryPDF(route) {
        if (!window.jspdf) {
            alert('Biblioteca PDF não carregada.');
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const margin = 15;
        const stopsData = parseStopsData(route.DADOS_PARADAS);

        // --- CABEÇALHO (Estilo Cyan) ---
        doc.setFillColor(6, 182, 212); // Cor #06b6d4 (Cyan)
        doc.rect(0, 0, 210, 20, 'F');

        doc.setTextColor(255, 255, 255);
        doc.setFontSize(22);
        doc.setFont("helvetica", "bold");
        doc.text("HelioMax", margin, 13);

        doc.setFontSize(12);
        doc.setFont("helvetica", "normal");
        doc.text("Relatório de Rota (Histórico)", 210 - margin, 13, { align: 'right' });

        // Data da Simulação
        const date = new Date(route.DATA_SIMULACAO);
        const dataStr = date.toLocaleDateString('pt-BR') + ' às ' + date.toLocaleTimeString('pt-BR');

        doc.setTextColor(100, 100, 100);
        doc.setFontSize(10);
        doc.text(`Simulação realizada em: ${dataStr}`, margin, 28);

        let currentY = 35;

        // --- INFORMAÇÕES DO VEÍCULO ---
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
        doc.text(`Modelo: ${route.VEICULO_NOME || 'Veículo Padrão'}`, margin, currentY);
        // Nota: A API de listagem do histórico não retorna detalhes técnicos do veículo (capacidade, consumo) por padrão,
        // apenas o nome. Para manter a consistência visual, exibimos o nome.
        currentY += 8;

        // --- RESUMO DA VIAGEM ---
        doc.setDrawColor(200, 200, 200);
        doc.line(margin, currentY, 210 - margin, currentY);
        currentY += 7;

        doc.setFontSize(14);
        doc.setFont("helvetica", "bold");
        doc.text("Resumo da Viagem", margin, currentY);
        currentY += 7;

        // Tabela Resumo (Estilo Simulador)
        doc.autoTable({
            startY: currentY,
            head: [['Distância Total', 'Tempo Estimado', 'Paradas', 'Custo Estimado']],
            body: [[
                `${parseFloat(route.DISTANCIA_TOTAL_KM).toFixed(2)} km`,
                `${Math.floor(route.TEMPO_CONDUCAO_MIN / 60)}h ${route.TEMPO_CONDUCAO_MIN % 60}m`,
                `${stopsData.charge.length + stopsData.manual.length}`,
                `R$ ${parseFloat(route.CUSTO_TOTAL).toFixed(2)}`
            ]],
            theme: 'plain',
            headStyles: { fillColor: [240, 240, 240], textColor: [0, 0, 0], fontStyle: 'bold' },
            styles: { fontSize: 11, cellPadding: 3 },
            margin: { left: margin, right: margin }
        });

        currentY = doc.lastAutoTable.finalY + 10;

        // --- LISTA DE PARADAS ---
        doc.setFontSize(14);
        doc.setFont("helvetica", "bold");
        doc.text("Itinerário e Paradas de Recarga", margin, currentY);
        currentY += 4;

        const tableRows = [];

        // 1. Origem
        tableRows.push(['Início', 'Origem', route.ORIGEM_ENDERECO || "Endereço de Origem", '-', '-', '-']);

        // 2. Paradas Manuais
        // Nota: No histórico, as paradas manuais e de recarga vêm separadas em arrays.
        // O ideal seria mesclar por distância, mas como não temos a distância relativa da manual salva no JSON antigo,
        // vamos listar manuais primeiro (como paradas de planejamento) e depois as de recarga, similar ao PDF do simulador.
        stopsData.manual.forEach((stop, idx) => {
            tableRows.push([
                `Parada #${idx + 1}`,
                'Descanso (Manual)',
                stop.name || 'Parada Manual', // Aqui usará o nome salvo
                '-',
                '-',
                '-'
            ]);
        });

        // 3. Paradas de Recarga
        stopsData.charge.forEach((stop, index) => {
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
        });

        // 4. Destino
        tableRows.push(['Fim', 'Destino', route.DESTINO_ENDERECO || "Endereço de Destino", `${parseFloat(route.DISTANCIA_TOTAL_KM).toFixed(2)} km`, `${parseFloat(route.CARGA_FINAL_PCT).toFixed(1)}%`, '-']);

        doc.autoTable({
            startY: currentY,
            head: [['Tipo', 'Fonte/Status', 'Local / Endereço', 'Dist. Acum.', 'Carga (E/S)', 'Tempo']],
            body: tableRows,
            theme: 'striped', // Estilo listrado igual ao simulador
            headStyles: { fillColor: [6, 182, 212], textColor: [255, 255, 255], fontStyle: 'bold' },
            styles: { fontSize: 9, cellPadding: 3, valign: 'middle' },
            columnStyles: {
                0: { cellWidth: 20, fontStyle: 'bold' },
                2: { cellWidth: 'auto' },
                3: { cellWidth: 20, halign: 'center' },
                4: { cellWidth: 25, halign: 'center' },
                5: { cellWidth: 15, halign: 'center' }
            },
            margin: { left: margin, right: margin },
            didParseCell: function (data) {
                // Negrito para Início/Fim
                if (data.row.raw[0] === 'Início' || data.row.raw[0] === 'Fim') {
                    data.cell.styles.fontStyle = 'bold';
                }
                // Destaque Amarelo para Manual
                if (data.row.raw[1] === 'Descanso (Manual)') {
                    data.cell.styles.textColor = [202, 138, 4]; // Amarelo
                }
            }
        });

        // Rodapé
        const pageCount = doc.internal.getNumberOfPages();
        for (let i = 1; i <= pageCount; i++) {
            doc.setPage(i);
            doc.setFontSize(8);
            doc.setTextColor(150, 150, 150);
            doc.text(`Página ${i} de ${pageCount} - HelioMax Histórico`, 105, 290, { align: 'center' });
        }

        doc.save(`Historico_Rota_${route.ID_HISTORICO}.pdf`);
    }
});