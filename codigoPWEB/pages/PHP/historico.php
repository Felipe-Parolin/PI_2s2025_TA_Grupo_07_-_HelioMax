<?php
require_once 'protectuser.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Configuração do banco de dados
$host = '127.0.0.1';
$dbname = 'heliomax';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Busca dados do usuário logado
$user_id = $_SESSION['usuario_id'];
$stmt = $pdo->prepare("SELECT u.*, c.LOGRADOURO, b.NOME as bairro, ci.NOME as cidade, e.UF 
                       FROM usuario u
                       LEFT JOIN cep c ON u.FK_ID_CEP = c.ID_CEP
                       LEFT JOIN bairro b ON c.FK_BAIRRO = b.ID_BAIRRO
                       LEFT JOIN cidade ci ON b.FK_CIDADE = ci.ID_CIDADE
                       LEFT JOIN estado e ON ci.FK_ESTADO = e.ID_ESTADO
                       WHERE u.ID_USER = ?");
$stmt->execute([$user_id]);
$usuario_logado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario_logado) {
    $usuario_logado = ['NOME' => 'Visitante', 'EMAIL' => 'email@exemplo.com'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard - Histórico de Rotas</title>
    <link rel="icon" type="image/png" href="../../images/icon.png">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../../styles/style_historico.css">
</head>

<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white flex">

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 bg-slate-900/50 backdrop-blur-xl border-r border-cyan-500/20 p-4 
               flex flex-col flex-shrink-0 z-50 
               md:relative md:translate-x-0 
               transition-all duration-300 sidebar-mobile-hidden">

        <div class="flex items-center gap-3 mb-10">
            <div
                class="w-12 h-12 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
                <i data-lucide="zap" class="w-7 h-7 text-white"></i>
            </div>
            <div class="sidebar-text">
                <h1 class="text-xl font-bold text-white">HelioMax</h1>
                <p class="text-xs text-cyan-400">Histórico de Rotas</p>
            </div>
        </div>

        <nav class="flex-grow">
            <a href="../PHP/dashUSER.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item"
                id="sidebar-dashboard" title="Dashboard">
                <i data-lucide="layout-dashboard" class="flex-shrink-0"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <a href="historico.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item active mt-2"
                id="sidebar-historico" title="Histórico">
                <i data-lucide="history" class="flex-shrink-0"></i>
                <span class="sidebar-text">Histórico</span>
            </a>
            <a href="veiculos.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item mt-2"
                id="sidebar-veiculos" title="Meus Veículos">
                <i data-lucide="car" class="flex-shrink-0"></i>
                <span class="sidebar-text">Meus Veículos</span>
            </a>
            <a href="../PHP/avaliacoes.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item mt-2"
                id="sidebar-avaliacoes" title="Avaliações">
                <i data-lucide="star" class="flex-shrink-0"></i>
                <span class="sidebar-text">Avaliações</span>
            </a>
            <a href="pontos_favoritos.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item mt-2">
                <i data-lucide="map-pin"></i>
                <span class="sidebar-text">Pontos Favoritos</span>
            </a>
        </nav>

        <div class="mt-auto pt-4 border-t border-cyan-500/20">
            <a href="perfil.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item"
                title="Minha Conta">
                <i data-lucide="user-cog" class="flex-shrink-0"></i>
                <span class="sidebar-text">Minha Conta</span>
            </a>
            <a href="../PHP/dashUSER.php?logout=1"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-400 hover:bg-red-500/30 transition-colors sidebar-item mt-2"
                title="Sair">
                <i data-lucide="log-out" class="flex-shrink-0"></i>
                <span class="sidebar-text">Sair</span>
            </a>
        </div>
    </aside>

    <!-- Overlay para mobile -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="toggleSidebar()"></div>

    <!-- Conteúdo Principal -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">
        <header class="flex justify-between items-center mb-6 lg:mb-8 flex-wrap gap-4">
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl sm:text-3xl font-bold text-white">Histórico de Rotas</h1>
                <p class="text-gray-400 text-sm sm:text-base">Visualize todas as rotas que você simulou.</p>
            </div>

            <div class="flex items-center gap-3">
                <button id="toggle-sidebar-btn" class="p-2 md:hidden text-white" onclick="toggleSidebar()">
                    <i data-lucide="menu" class="w-7 h-7"></i>
                </button>

                <!-- Filtros -->
                <div class="hidden sm:flex items-center gap-2">
                    <select id="filter-period"
                        class="bg-slate-800 border border-cyan-500/20 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                        <option value="all">Todos os períodos</option>
                        <option value="today">Hoje</option>
                        <option value="week">Última semana</option>
                        <option value="month">Último mês</option>
                    </select>
                </div>
            </div>
        </header>

        <!-- Estatísticas Rápidas -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div
                class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-xl border border-cyan-500/20 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-xs sm:text-sm mb-1">Total de Rotas</p>
                        <p class="text-2xl sm:text-3xl font-bold text-white" id="stat-total-routes">0</p>
                    </div>
                    <div class="w-12 h-12 bg-cyan-500/20 rounded-lg flex items-center justify-center">
                        <i data-lucide="map" class="w-6 h-6 text-cyan-400"></i>
                    </div>
                </div>
            </div>

            <div
                class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-xl border border-cyan-500/20 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-xs sm:text-sm mb-1">Distância Total</p>
                        <p class="text-2xl sm:text-3xl font-bold text-white" id="stat-total-distance">0 km</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center">
                        <i data-lucide="route" class="w-6 h-6 text-blue-400"></i>
                    </div>
                </div>
            </div>

            <div
                class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-xl border border-cyan-500/20 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-xs sm:text-sm mb-1">Energia Consumida</p>
                        <p class="text-2xl sm:text-3xl font-bold text-white" id="stat-total-energy">0 kWh</p>
                    </div>
                    <div class="w-12 h-12 bg-green-500/20 rounded-lg flex items-center justify-center">
                        <i data-lucide="battery-charging" class="w-6 h-6 text-green-400"></i>
                    </div>
                </div>
            </div>

            <div
                class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-xl border border-cyan-500/20 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-xs sm:text-sm mb-1">Custo Total</p>
                        <p class="text-2xl sm:text-3xl font-bold text-white" id="stat-total-cost">R$ 0,00</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-500/20 rounded-lg flex items-center justify-center">
                        <i data-lucide="dollar-sign" class="w-6 h-6 text-yellow-400"></i>
                    </div>
                </div>
            </div>
        </section>

        <!-- Lista de Histórico -->
        <section
            class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl border border-cyan-500/20 p-4 sm:p-6">
            <div class="flex justify-between items-center mb-4 border-b border-cyan-500/10 pb-3">
                <h2 class="text-xl sm:text-2xl font-semibold">Rotas Simuladas</h2>

                <!-- Filtro mobile -->
                <select id="filter-period-mobile"
                    class="sm:hidden bg-slate-800 border border-cyan-500/20 rounded-lg px-2 py-1 text-xs text-white">
                    <option value="all">Todos</option>
                    <option value="today">Hoje</option>
                    <option value="week">Semana</option>
                    <option value="month">Mês</option>
                </select>
            </div>

            <!-- Loading Spinner -->
            <div id="loading-spinner" class="flex justify-center items-center py-12">
                <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-cyan-400"></div>
            </div>

            <!-- Lista de Rotas -->
            <div id="routes-list" class="space-y-4 hidden">
                <!-- Os cards serão inseridos aqui via JavaScript -->
            </div>

            <!-- Estado Vazio -->
            <div id="empty-state" class="text-center py-12 sm:py-20 hidden">
                <i data-lucide="map-pin-off" class="w-12 h-12 sm:w-16 sm:h-16 mx-auto text-gray-600"></i>
                <p class="mt-4 text-lg sm:text-xl text-gray-400">Você ainda não possui histórico de rotas.</p>
                <p class="text-sm sm:text-base text-gray-500 mb-4">Faça sua primeira simulação no Dashboard.</p>
                <a href="../PHP/dashUSER.php"
                    class="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-500 text-white font-bold py-2 px-6 rounded-xl transition-colors">
                    <i data-lucide="play" class="w-4 h-4"></i>
                    Simular Rota
                </a>
            </div>
        </section>
    </main>

    <!-- Modal de Detalhes da Rota -->
    <div id="modal-details"
        class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4 z-[99] hidden transition-opacity duration-300">
        <div id="modal-content"
            class="bg-slate-800 border border-cyan-500/30 rounded-2xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto transition-all duration-300 opacity-0 scale-95 transform">

            <header
                class="flex justify-between items-center p-4 sm:p-5 border-b border-slate-700 sticky top-0 bg-slate-800 z-10">
                <h2 class="text-lg sm:text-xl font-bold text-white">Detalhes da Rota</h2>
                <button id="btn-close-modal" class="text-gray-400 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </header>

            <div id="modal-body" class="p-4 sm:p-6">
                <!-- Conteúdo será inserido via JavaScript -->
            </div>

        </div>
    </div>

    <script src="../JS/historico.js"></script>
    <script>
        lucide.createIcons();

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('sidebar-mobile-hidden');
            overlay.classList.toggle('hidden');

            const toggleBtn = document.getElementById('toggle-sidebar-btn');
            if (toggleBtn) {
                toggleBtn.innerHTML = sidebar.classList.contains('sidebar-mobile-hidden')
                    ? '<i data-lucide="menu" class="w-7 h-7"></i>'
                    : '<i data-lucide="x" class="w-7 h-7"></i>';
                lucide.createIcons();
            }
        }
    </script>
</body>

</html>