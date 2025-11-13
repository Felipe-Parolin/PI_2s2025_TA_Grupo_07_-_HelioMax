// historico.js - Gerenciamento do Histórico de Rotas

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

    // Event delegation para botões de visualizar
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

    // Função para carregar histórico
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

    // Função para filtrar rotas
    function filterRoutes() {
        const period = filterPeriod.value;
        const now = new Date();
        
        if (period === 'all') {
            filteredRoutes = allRoutes;
        } else {
            filteredRoutes = allRoutes.filter(route => {
                const routeDate = new Date(route.DATA_SIMULACAO);
                
                switch(period) {
                    case 'today':
                        return routeDate.toDateString() === now.toDateString();
                    case 'week':
                        const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                        return routeDate >= weekAgo;
                    case 'month':
                        const monthAgo = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
                        return routeDate >= monthAgo;
                    default:
                        return true;
                }
            });
        }

        updateStatistics(filteredRoutes);
        renderRoutes(filteredRoutes);
        
        // Sincronizar filtros
        filterPeriodMobile.value = period;
        
        lucide.createIcons();
    }

    // Função para atualizar estatísticas
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

    // Função para renderizar rotas
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

    // Função para criar card de rota
    function createRouteCard(route) {
        const date = new Date(route.DATA_SIMULACAO);
        const formattedDate = date.toLocaleDateString('pt-BR', { 
            day: '2-digit', 
            month: 'short', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        const vehicleName = route.VEICULO_NOME || 'Veículo Padrão';
        const modeClass = route.MODO_OTIMISTA ? 'bg-purple-500/20 text-purple-400' : 'bg-green-500/20 text-green-400';
        const modeText = route.MODO_OTIMISTA ? 'Planejamento' : 'Realista';

        return `
            <article class="route-card bg-slate-900/50 p-4 sm:p-5 rounded-xl border border-slate-700 hover:border-cyan-500/50 transition-all duration-300">
                <!-- Cabeçalho -->
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
                    <span class="${modeClass} text-xs px-3 py-1 rounded-full font-bold">
                        ${modeText}
                    </span>
                </div>

                <!-- Origem e Destino -->
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

                <!-- Informações da Rota -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 text-xs">
                    <div class="bg-slate-800/50 p-2 rounded-lg">
                        <p class="text-gray-400 mb-1">Distância</p>
                        <p class="text-white font-semibold">${parseFloat(route.DISTANCIA_TOTAL_KM).toFixed(1)} km</p>
                    </div>
                    <div class="bg-slate-800/50 p-2 rounded-lg">
                        <p class="text-gray-400 mb-1">Paradas</p>
                        <p class="text-white font-semibold">${route.PARADAS_TOTAIS}</p>
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

                <!-- Ações -->
                <div class="flex gap-2">
                    <button class="btn-view-details flex-1 bg-cyan-600/20 text-cyan-400 hover:bg-cyan-600 hover:text-white py-2 px-4 rounded-lg text-sm font-semibold transition-all flex items-center justify-center gap-2"
                        data-id="${route.ID_HISTORICO}">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                        <span class="hidden sm:inline">Ver Detalhes</span>
                        <span class="sm:hidden">Detalhes</span>
                    </button>
                    <button class="btn-delete-route bg-red-600/20 text-red-400 hover:bg-red-600 hover:text-white py-2 px-4 rounded-lg text-sm font-semibold transition-all"
                        data-id="${route.ID_HISTORICO}"
                        title="Excluir rota">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
            </article>
        `;
    }

    // Função para abrir detalhes da rota
    async function openRouteDetails(routeId) {
        try {
            const response = await fetch(`../PHP/api_historico.php?action=details&id=${routeId}`);
            const data = await response.json();

            if (data.success && data.route) {
                const route = data.route;
                const paradas = route.DADOS_PARADAS ? JSON.parse(route.DADOS_PARADAS) : [];

                const date = new Date(route.DATA_SIMULACAO);
                const formattedDate = date.toLocaleDateString('pt-BR', { 
                    day: '2-digit', 
                    month: 'long', 
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                let paradasHTML = '';
                if (paradas.length > 0) {
                    paradasHTML = `
                        <div class="mt-6">
                            <h3 class="text-lg font-bold text-cyan-400 mb-4 flex items-center gap-2">
                                <i data-lucide="map-pin" class="w-5 h-5"></i>
                                Paradas de Recarga (${paradas.length})
                            </h3>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                ${paradas.map((parada, index) => createParadaCard(parada, index)).join('')}
                            </div>
                        </div>
                    `;
                }

                modalBody.innerHTML = `
                    <div class="space-y-6">
                        <!-- Informações Gerais -->
                        <div>
                            <p class="text-sm text-gray-400 mb-2">${formattedDate}</p>
                            <div class="flex items-center gap-2 mb-4">
                                <i data-lucide="car" class="w-5 h-5 text-cyan-400"></i>
                                <span class="text-lg font-semibold text-white">${route.VEICULO_NOME || 'Veículo Padrão'}</span>
                                <span class="text-xs px-2 py-1 rounded-full ${route.MODO_OTIMISTA ? 'bg-purple-500/20 text-purple-400' : 'bg-green-500/20 text-green-400'}">
                                    ${route.MODO_OTIMISTA ? 'Planejamento' : 'Realista'}
                                </span>
                            </div>
                        </div>

                        <!-- Origem e Destino -->
                        <div class="bg-slate-900/50 p-4 rounded-xl space-y-3">
                            <div class="flex items-start gap-3">
                                <i data-lucide="map-pin" class="w-5 h-5 text-green-400 mt-0.5"></i>
                                <div>
                                    <p class="text-xs text-gray-400">Origem</p>
                                    <p class="text-white">${route.ORIGEM_ENDERECO}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i data-lucide="flag" class="w-5 h-5 text-red-400 mt-0.5"></i>
                                <div>
                                    <p class="text-xs text-gray-400">Destino</p>
                                    <p class="text-white">${route.DESTINO_ENDERECO}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Resumo da Viagem -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-slate-900/50 p-4 rounded-xl">
                                <p class="text-gray-400 text-sm mb-1">Distância Total</p>
                                <p class="text-2xl font-bold text-cyan-400">${parseFloat(route.DISTANCIA_TOTAL_KM).toFixed(1)} km</p>
                            </div>
                            <div class="bg-slate-900/50 p-4 rounded-xl">
                                <p class="text-gray-400 text-sm mb-1">Tempo de Condução</p>
                                <p class="text-2xl font-bold text-cyan-400">${route.TEMPO_CONDUCAO_MIN} min</p>
                            </div>
                            <div class="bg-slate-900/50 p-4 rounded-xl">
                                <p class="text-gray-400 text-sm mb-1">Paradas</p>
                                <p class="text-2xl font-bold text-cyan-400">${route.PARADAS_TOTAIS}</p>
                            </div>
                            <div class="bg-slate-900/50 p-4 rounded-xl">
                                <p class="text-gray-400 text-sm mb-1">Tempo Carregamento</p>
                                <p class="text-2xl font-bold text-cyan-400">${route.TEMPO_CARREGAMENTO_MIN} min</p>
                            </div>
                            <div class="bg-slate-900/50 p-4 rounded-xl">
                                <p class="text-gray-400 text-sm mb-1">Energia Consumida</p>
                                <p class="text-2xl font-bold text-green-400">${parseFloat(route.ENERGIA_CONSUMIDA_KWH).toFixed(2)} kWh</p>
                            </div>
                            <div class="bg-slate-900/50 p-4 rounded-xl">
                                <p class="text-gray-400 text-sm mb-1">Custo Estimado</p>
                                <p class="text-2xl font-bold text-yellow-400">R$ ${parseFloat(route.CUSTO_TOTAL).toFixed(2)}</p>
                            </div>
                            <div class="bg-slate-900/50 p-4 rounded-xl">
                                <p class="text-gray-400 text-sm mb-1">Carga na Chegada</p>
                                <p class="text-2xl font-bold text-blue-400">${parseFloat(route.CARGA_FINAL_PCT).toFixed(1)}%</p>
                            </div>
                        </div>

                        ${paradasHTML}
                    </div>
                `;

                modal.classList.remove('hidden');
                modal.classList.add('flex');
                setTimeout(() => {
                    modalContent.classList.remove('opacity-0', 'scale-95');
                }, 10);

                lucide.createIcons();
            }
        } catch (error) {
            console.error('Erro ao carregar detalhes:', error);
            alert('Erro ao carregar detalhes da rota.');
        }
    }

    // Função para criar card de parada
    function createParadaCard(parada, index) {
        const isEstimated = parada.is_estimated === true;
        const isFromDatabase = parada.is_from_database === true;

        let badgeClass, badgeText, iconBg;
        if (isEstimated) {
            badgeClass = 'bg-slate-500';
            badgeText = 'Simulada';
            iconBg = 'bg-slate-500';
        } else if (isFromDatabase) {
            badgeClass = 'bg-green-500';
            badgeText = 'HelioMax';
            iconBg = 'bg-green-500';
        } else {
            badgeClass = 'bg-red-500';
            badgeText = 'OCM';
            iconBg = 'bg-cyan-500';
        }

        return `
            <div class="bg-slate-800/50 p-3 rounded-lg border border-slate-700">
                <div class="flex items-start gap-2 mb-2">
                    <span class="${iconBg} text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold flex-shrink-0">
                        ${isEstimated ? '?' : index + 1}
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-bold text-cyan-400 text-sm truncate">${parada.station.name}</p>
                            <span class="${badgeClass} text-white text-xs px-2 py-0.5 rounded-full font-bold">
                                ${badgeText}
                            </span>
                        </div>
                        <p class="text-gray-400 text-xs truncate">${parada.station.address}</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <span class="text-gray-400">Distância:</span>
                        <span class="text-white font-semibold"> ${parada.distance_traveled_km} km</span>
                    </div>
                    <div>
                        <span class="text-gray-400">Tempo:</span>
                        <span class="text-white font-semibold"> ${Math.round(parada.charge_time / 60)} min</span>
                    </div>
                    <div>
                        <span class="text-gray-400">Chegada:</span>
                        <span class="text-white font-semibold"> ${parada.charge_at_arrival}%</span>
                    </div>
                    <div>
                        <span class="text-gray-400">Saída:</span>
                        <span class="text-white font-semibold"> ${parada.charge_at_departure}%</span>
                    </div>
                </div>
            </div>
        `;
    }

    // Função para fechar modal
    function closeModal() {
        modalContent.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }

    // Função para excluir rota
    async function deleteRoute(routeId) {
        if (!confirm('Tem certeza que deseja excluir esta rota do histórico? Esta ação não pode ser desfeita.')) {
            return;
        }

        try {
            const response = await fetch('../PHP/api_historico.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: routeId })
            });

            const data = await response.json();

            if (data.success) {
                // Remove a rota do array local
                allRoutes = allRoutes.filter(route => route.ID_HISTORICO !== parseInt(routeId));
                filterRoutes(); // Reaplica o filtro e atualiza a tela
            } else {
                alert('Erro ao excluir: ' + data.message);
            }
        } catch (error) {
            console.error('Erro ao excluir rota:', error);
            alert('Erro ao excluir rota.');
        }
    }
});