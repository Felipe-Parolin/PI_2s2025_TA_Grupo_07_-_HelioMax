// Arquivo: meus_veiculos.js

// 1. Lógica do Menu Hamburger
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    // Toggle nas classes de visibilidade e posição
    sidebar.classList.toggle('sidebar-mobile-hidden');
    sidebar.classList.toggle('shadow-2xl'); // Adiciona sombra quando aberto
    overlay.classList.toggle('hidden');
    
    // Altera o ícone do botão hamburger
    const toggleBtn = document.getElementById('toggle-sidebar-btn');
    if (toggleBtn) {
        if (sidebar.classList.contains('sidebar-mobile-hidden')) {
            toggleBtn.innerHTML = '<i data-lucide="menu" class="w-7 h-7"></i>';
        } else {
            toggleBtn.innerHTML = '<i data-lucide="x" class="w-7 h-7"></i>';
        }
        // Re-inicializa os ícones após a alteração do HTML interno
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}

// 2. Ajusta sidebar para tablets (modo compacto)
function adjustSidebarForTablet() {
    const sidebar = document.getElementById('sidebar');
    const isTablet = window.innerWidth >= 640 && window.innerWidth < 1024;
    
    if (isTablet) {
        sidebar.classList.add('sidebar-tablet');
    } else {
        sidebar.classList.remove('sidebar-tablet');
    }
}

// 3. Função para verificar o estado da lista de veículos
function checkEmptyState() {
    const vehicleList = document.getElementById('vehicle-list');
    const remainingCards = vehicleList.querySelectorAll('.vehicle-card');
    const emptyState = document.getElementById('empty-state');
    const placeholderCard = vehicleList.querySelector('.xl\\:flex');

    let cardCount = remainingCards.length;

    if (cardCount === 0 || (cardCount === 1 && placeholderCard)) {
        emptyState.classList.remove('hidden');
    } else {
        emptyState.classList.add('hidden');
    }
}

// 4. Inicializa o ambiente
document.addEventListener('DOMContentLoaded', () => {
    // Inicializa todos os ícones Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Ajusta sidebar para tablets no carregamento
    adjustSidebarForTablet();
    
    // Adiciona listener para ajustar sidebar ao redimensionar a janela
    window.addEventListener('resize', adjustSidebarForTablet);

    // Listener para o botão de Cadastro (versão Desktop)
    const btnCadastrar = document.getElementById('btn-cadastrar-veiculo');
    if (btnCadastrar) {
        btnCadastrar.addEventListener('click', () => {
            alert('Ação: Abrir modal/página de Cadastro de Novo Veículo.');
        });
    }

    // Listener para o botão de Cadastro (versão Mobile na lista de veículos)
    const btnCadastrarMobile = document.getElementById('btn-cadastrar-veiculo-mobile');
    if (btnCadastrarMobile) {
        btnCadastrarMobile.addEventListener('click', () => {
            alert('Ação: Abrir modal/página de Cadastro de Novo Veículo.');
        });
    }

    // 5. Adiciona interatividade aos botões "Editar" e "Excluir"
    const vehicleList = document.getElementById('vehicle-list');

    if (vehicleList) {
        vehicleList.addEventListener('click', (event) => {
            const target = event.target;
            
            const btnEditar = target.closest('.btn-editar');
            const btnExcluir = target.closest('.btn-excluir');

            if (btnEditar) {
                const veiculoId = btnEditar.getAttribute('data-id');
                const card = btnEditar.closest('.vehicle-card');
                const marcaModelo = card.querySelector('h3').textContent;
                
                alert(`Ação: Editar o veículo ID ${veiculoId} (${marcaModelo}).`);
            } else if (btnExcluir) {
                const veiculoId = btnExcluir.getAttribute('data-id');
                const card = btnExcluir.closest('.vehicle-card');
                const marcaModelo = card.querySelector('h3').textContent;
                
                if (confirm(`Tem certeza que deseja EXCLUIR o veículo ${marcaModelo} (ID: ${veiculoId})?`)) {
                    // Animação de saída
                    card.classList.add('opacity-0', 'scale-0', 'h-0', 'p-0', 'm-0', 'transition-all', 'duration-500', 'ease-in-out');
                    
                    // Remove o card após a animação
                    setTimeout(() => {
                        card.remove();
                        checkEmptyState();
                    }, 500);
                }
            }
        });
    }

    // Adiciona a função toggleSidebar globalmente para uso no onclick do HTML
    window.toggleSidebar = toggleSidebar; 
    
    // Verifica o estado inicial da lista
    checkEmptyState();
});