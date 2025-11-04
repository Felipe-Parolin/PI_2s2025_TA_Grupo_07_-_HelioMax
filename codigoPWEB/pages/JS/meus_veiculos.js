// Arquivo: meus_veiculos.js

document.addEventListener('DOMContentLoaded', () => {
    // --- SELETORES DE ELEMENTOS ---
    const modal = document.getElementById('modal-veiculo');
    const modalContent = document.getElementById('modal-content');
    const btnFecharModal = document.getElementById('btn-fechar-modal');
    const btnCancelarModal = document.getElementById('btn-cancelar-modal');
    const formVeiculo = document.getElementById('form-veiculo');
    const vehicleList = document.getElementById('vehicle-list');
    const errorMessage = document.getElementById('modal-error-message');
    const modalTitle = document.getElementById('modal-title');
    const selectMarca = document.getElementById('marca');
    const selectModelo = document.getElementById('modelo');
    const hiddenVeiculoId = document.getElementById('veiculo-id');
    const btnSalvar = document.getElementById('btn-salvar-veiculo');

    // --- FUNÇÕES DO MODAL ---
    
    // Abre o modal em modo "Criação"
    function abrirModalParaCriar() {
        modalTitle.textContent = 'Cadastrar Novo Veículo';
        btnSalvar.textContent = 'Salvar Veículo';
        formVeiculo.reset(); // Limpa o formulário
        hiddenVeiculoId.value = ''; // Garante que o ID oculto está vazio
        selectModelo.disabled = true;
        selectModelo.innerHTML = '<option value="">Selecione a marca primeiro</option>';
        errorMessage.classList.add('hidden');
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => modalContent.classList.remove('opacity-0', 'scale-95'), 10);
    }

    // Abre o modal em modo "Edição"
    async function abrirModalParaEditar(id) {
        modalTitle.textContent = 'Editar Veículo';
        btnSalvar.textContent = 'Salvar Alterações';
        formVeiculo.reset();
        errorMessage.classList.add('hidden');
        
        try {
            // 1. Busca os dados do veículo específico
            const response = await fetch(`../PHP/api_veiculos.php?id=${id}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            
            const veiculo = result.data;

            // 2. Preenche os campos do formulário
            hiddenVeiculoId.value = veiculo.ID_VEICULO;
            document.getElementById('placa').value = veiculo.PLACA;
            document.getElementById('ano_fab').value = parseInt(veiculo.ANO_FAB);
            document.getElementById('cor').value = veiculo.cor_id;
            document.getElementById('conector').value = veiculo.conector_id;
            document.getElementById('capacidade_bateria').value = veiculo.CAPACIDADE_BATERIA;
            document.getElementById('consumo_medio').value = veiculo.CONSUMO_MEDIO;
            document.getElementById('nivel_bateria').value = veiculo.NIVEL_BATERIA;
            
            // 3. Lógica complexa para preencher os <select> dependentes
            // Seta a marca
            selectMarca.value = veiculo.marca_id;
            
            // Força o carregamento dos modelos daquela marca
            await carregarOpcoes(selectModelo, `../PHP/api_opcoes_veiculo.php?acao=modelos&marca_id=${veiculo.marca_id}`, 'Selecione...');
            selectModelo.disabled = false;
            
            // Seta o modelo correto
            selectModelo.value = veiculo.modelo_id;

            // 4. Mostra o modal
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => modalContent.classList.remove('opacity-0', 'scale-95'), 10);

        } catch (error) {
            console.error('Erro ao buscar dados para edição:', error);
            alert('Não foi possível carregar os dados do veículo.');
        }
    }

    function fecharModal() {
        modalContent.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300); // Aguarda a transição de saída
    }

    // --- FUNÇÕES DE API E DADOS ---
    
    // Carrega opções para um <select>
    async function carregarOpcoes(selectElement, url, placeholder) {
        try {
            const response = await fetch(url);
            const result = await response.json();
            if (result.success) {
                selectElement.innerHTML = `<option value="">${placeholder}</option>`;
                result.data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = Object.values(item)[0]; // ID
                    option.textContent = Object.values(item)[1]; // NOME
                    selectElement.appendChild(option);
                });
            }
        } catch (error) {
            console.error(`Erro ao carregar ${url}:`, error);
        }
    }

    // Carrega todas as opções iniciais do modal
    function carregarOpcoesIniciais() {
        carregarOpcoes(selectMarca, '../PHP/api_opcoes_veiculo.php?acao=marcas', 'Selecione...');
        carregarOpcoes(document.getElementById('cor'), '../PHP/api_opcoes_veiculo.php?acao=cores', 'Selecione...');
        carregarOpcoes(document.getElementById('conector'), '../PHP/api_opcoes_veiculo.php?acao=conectores', 'Selecione...');
    }
    
    // Evento para carregar modelos quando uma marca é selecionada
    selectMarca.addEventListener('change', () => {
        const marcaId = selectMarca.value;
        selectModelo.disabled = true;
        selectModelo.innerHTML = '<option value="">Carregando...</option>';
        if (marcaId) {
            carregarOpcoes(selectModelo, `../PHP/api_opcoes_veiculo.php?acao=modelos&marca_id=${marcaId}`, 'Selecione...');
            selectModelo.disabled = false;
        } else {
            selectModelo.innerHTML = '<option value="">Selecione a marca primeiro</option>';
        }
    });

    // Submissão do formulário (Criação OU Edição)
    formVeiculo.addEventListener('submit', async (e) => {
        e.preventDefault();
        btnSalvar.disabled = true;
        errorMessage.classList.add('hidden');
        
        const formData = new FormData(formVeiculo);
        const dados = Object.fromEntries(formData.entries());
        
        const id = hiddenVeiculoId.value;
        const method = id ? 'PUT' : 'POST';
        const url = id ? `../PHP/api_veiculos.php?id=${id}` : '../PHP/api_veiculos.php';

        try {
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dados),
            });

            const result = await response.json();

            if (result.success) {
                fecharModal();
                carregarVeiculos(); // Recarrega a lista de veículos
            } else {
                errorMessage.textContent = result.message;
                errorMessage.classList.remove('hidden');
            }
        } catch (error) {
            errorMessage.textContent = 'Erro de comunicação com o servidor.';
            errorMessage.classList.remove('hidden');
        } finally {
            btnSalvar.disabled = false;
        }
    });

    // --- LÓGICA PRINCIPAL DA PÁGINA ---

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.toggle('sidebar-mobile-hidden');
        overlay.classList.toggle('hidden');
        const toggleBtn = document.getElementById('toggle-sidebar-btn');
        if(toggleBtn) {
            toggleBtn.innerHTML = sidebar.classList.contains('sidebar-mobile-hidden') ? '<i data-lucide="menu" class="w-7 h-7"></i>' : '<i data-lucide="x" class="w-7 h-7"></i>';
            lucide.createIcons();
        }
    }
    window.toggleSidebar = toggleSidebar;

    function adjustSidebarForTablet() { /* ...código original... */ }

    function checkEmptyState() {
        const remainingCards = vehicleList.querySelectorAll('.vehicle-card');
        const emptyState = document.getElementById('empty-state');
        emptyState.classList.toggle('hidden', remainingCards.length > 0);
    }

    function criarCardVeiculo(veiculo) {

        const capacidadeBateria = veiculo.CAPACIDADE_BATERIA || 'N/A';
        const consumoMedio = veiculo.CONSUMO_MEDIO || 'N/A';
        const nivelBateria = veiculo.NIVEL_BATERIA || 'N/A';

        return `
        <article class="vehicle-card bg-slate-700/50 p-4 sm:p-6 rounded-xl shadow-lg border border-slate-700 hover:shadow-cyan-500/20" data-veiculo-id="${veiculo.ID_VEICULO}">
            <div class="flex items-center mb-4 border-b border-slate-600 pb-3">
                <i data-lucide="car-front" class="w-7 h-7 sm:w-8 sm:h-8 text-cyan-400 mr-3 sm:mr-4 flex-shrink-0"></i>
                <div class="min-w-0">
                    <h3 class="text-lg sm:text-xl font-bold text-white truncate">${veiculo.MARCA_NOME} ${veiculo.MODELO_NOME}</h3>
                    <p class="text-xs sm:text-sm text-gray-400">Placa: ${veiculo.PLACA}</p>
                </div>
            </div>
            <div class="space-y-2 text-xs sm:text-sm text-gray-300">
                <p class="flex items-center gap-2"><i data-lucide="calendar" class="w-4 h-4 text-green-400"></i><span>Ano: <span class="font-semibold text-white">${parseInt(veiculo.ANO_FAB)}</span></span></p>
                <p class="flex items-center gap-2"><i data-lucide="palette" class="w-4 h-4 text-blue-400"></i><span>Cor: <span class="font-semibold text-white">${veiculo.COR_NOME}</span></span></p>
                <p class="flex items-center gap-2"><i data-lucide="bolt" class="w-4 h-4 text-yellow-400"></i><span>Plug: <span class="font-semibold text-white">${veiculo.CONECTOR_NOME}</span></span></p>
                <p class="flex items-center gap-2"><i data-lucide="battery" class="w-4 h-4 text-green-500"></i><span>Bateria: <span class="font-semibold text-white">${capacidadeBateria}${capacidadeBateria !== 'N/A' ? ' kWh' : ''}</span></span></p>
                <p class="flex items-center gap-2"><i data-lucide="gauge" class="w-4 h-4 text-blue-500"></i><span>Consumo: <span class="font-semibold text-white">${consumoMedio}${consumoMedio !== 'N/A' ? ' kWh/100km' : ''}</span></span></p>
                ${nivelBateria !== 'N/A' ? `<p class="flex items-center gap-2"><i data-lucide="percent" class="w-4 h-4 text-purple-400"></i><span>Nível: <span class="font-semibold text-white">${nivelBateria}%</span></span></p>` : ''}
        </div>
            <div class="flex justify-end gap-2 sm:gap-3 mt-4 sm:mt-6 pt-3 sm:pt-4 border-t border-slate-700">
                <button class="btn-editar bg-blue-600/20 text-blue-400 hover:bg-blue-600 hover:text-white py-1.5 sm:py-2 px-3 sm:px-4 rounded-lg text-xs sm:text-sm font-semibold transition flex items-center gap-1" data-id="${veiculo.ID_VEICULO}"><i data-lucide="edit" class="w-3.5 h-3.5 sm:w-4 sm:h-4"></i> Editar</button>
                <button class="btn-excluir bg-red-600/20 text-red-400 hover:bg-red-600 hover:text-white py-1.5 sm:py-2 px-3 sm:px-4 rounded-lg text-xs sm:text-sm font-semibold transition flex items-center gap-1" data-id="${veiculo.ID_VEICULO}"><i data-lucide="trash-2" class="w-3.5 h-3.5 sm:w-4 sm:h-4"></i> Excluir</button>
            </div>
        </article>`;
    }

        async function carregarVeiculos() {
            const placeholder = vehicleList.querySelector('article.xl\\:flex');
            try {
                const response = await fetch('../PHP/api_veiculos.php');
                if (response.status === 401) {
                    alert('Sessão expirada. Por favor, faça login novamente.');
                    window.location.href = 'login.php';
                    return;
                }
                const result = await response.json();
                
                // ✅ ADICIONE ESTE CONSOLE.LOG PARA VER OS DADOS
                console.log('Dados dos veículos:', result.data);
                
                vehicleList.querySelectorAll('.vehicle-card').forEach(card => card.remove());
                
                if (result.success && result.data.length > 0) {
                    result.data.forEach(veiculo => {
                        // ✅ ADICIONE ESTE CONSOLE.LOG TAMBÉM
                        console.log('Veículo individual:', veiculo);
                        const cardHTML = criarCardVeiculo(veiculo);
                        placeholder.insertAdjacentHTML('beforebegin', cardHTML);
                    });
                }
            } catch (error) {
                console.error('Erro ao carregar veículos:', error);
            } finally {
                checkEmptyState();
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        }

    // --- INICIALIZAÇÃO E EVENTOS GLOBAIS ---
    
    // Adiciona evento de clique para abrir o modal de CRIAÇÃO
    document.querySelectorAll('#btn-cadastrar-veiculo, #btn-cadastrar-veiculo-mobile, article[onclick]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault(); 
            e.stopPropagation(); // Impede que o clique propague
            abrirModalParaCriar();
        });
    });

    // Eventos para fechar o modal
    btnFecharModal.addEventListener('click', fecharModal);
    btnCancelarModal.addEventListener('click', fecharModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) fecharModal(); // Fecha se clicar no fundo escuro
    });

    // Listener de eventos na lista para EDIÇÃO e EXCLUSÃO
    vehicleList.addEventListener('click', async (event) => {
        const target = event.target;
        const btnEditar = target.closest('.btn-editar');
        const btnExcluir = target.closest('.btn-excluir');

        // Lógica de Edição
        if (btnEditar) {
            const id = btnEditar.dataset.id;
            abrirModalParaEditar(id);
            return; // Impede que o clique no card de "adicionar" seja acionado
        }

        // Lógica de Exclusão
        if (btnExcluir) {
            const id = btnExcluir.dataset.id;
            if (confirm('Tem certeza que deseja excluir este veículo? Esta ação não pode ser desfeita.')) {
                try {
                    const response = await fetch(`../PHP/api_veiculos.php?id=${id}`, { method: 'DELETE' });
                    const result = await response.json();
                    if (result.success) {
                        const card = btnExcluir.closest('.vehicle-card');
                        card.classList.add('opacity-0', 'scale-0', 'h-0', 'p-0', 'm-0', 'transition-all');
                        setTimeout(() => {
                            card.remove();
                            checkEmptyState();
                        }, 500);
                    } else {
                        alert('Erro ao excluir: ' + result.message);
                    }
                } catch (error) {
                    alert('Erro de comunicação ao tentar excluir.');
                }
            }
            return; // Impede que o clique no card de "adicionar" seja acionado
        }
    });

    // Carregamento inicial
    adjustSidebarForTablet();
    window.addEventListener('resize', adjustSidebarForTablet);
    carregarOpcoesIniciais();
    carregarVeiculos();
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});