<?php
require_once 'protectuser.php';

// Início do código PHP (Conexão com o Banco de Dados)
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

// Verifica se a sessão do usuário está ativa
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['usuario_id'] = 1;
}

$user_id = $_SESSION['usuario_id'];
$stmt = $pdo->prepare("SELECT u.* FROM usuario u WHERE u.ID_USER = ?");
$stmt->execute([$user_id]);
$usuario_logado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario_logado) {
    $usuario_logado = ['NOME' => 'Usuário Desconhecido'];
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../images/icon.png">
    <title>Meus Pontos Favoritos - Heliomax</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .map-container {
            height: 400px;
            width: 100%;
        }

        .modal {
            transition: opacity 0.3s ease, visibility 0.3s ease;
            display: none;
        }

        .modal.active {
            opacity: 1;
            visibility: visible;
            display: flex;
        }

        .sidebar-item.active {
            background-color: #0284c7;
            color: white;
        }

        @keyframes slide-in {
            from {
                transform: translateX(400px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .animate-slide-in {
            animation: slide-in 0.4s ease-out;
        }

        /* Sidebar mobile */
        .sidebar-mobile-hidden {
            transform: translateX(-100%);
        }

        @media (min-width: 768px) {
            .sidebar-mobile-hidden {
                transform: translateX(0);
            }
        }

        /* Empty state styling */
        .empty-state {
            min-height: 400px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .empty-state-icon {
            width: 120px;
            height: 120px;
            margin-bottom: 1.5rem;
            opacity: 0.4;
        }

        /* Loading Spinner */
        .loading-container {
            display: none;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            color: #06b6d4;
        }

        .loading-container.show {
            display: flex;
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid #06b6d4;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Estilo dos botões de ícone */
        .icon-btn {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .icon-btn:hover {
            transform: translateY(-2px);
        }

        .icon-btn.active {
            background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
            border-color: #67e8f9;
            box-shadow: 0 0 15px rgba(6, 182, 212, 0.4);
            transform: scale(1.1);
        }

        .icon-btn.active i {
            stroke-width: 3px;
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white flex">

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
                <p class="text-xs text-cyan-400">Pontos Favoritos</p>
            </div>
        </div>

        <nav class="flex-grow">
            <a href="dashUSER.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item"
                title="Dashboard">
                <i data-lucide="layout-dashboard" class="flex-shrink-0"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <a href="historico.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item mt-2"
                title="Histórico">
                <i data-lucide="history" class="flex-shrink-0"></i>
                <span class="sidebar-text">Histórico</span>
            </a>
            <a href="veiculos.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item mt-2"
                title="Meus Veículos">
                <i data-lucide="car" class="flex-shrink-0"></i>
                <span class="sidebar-text">Meus Veículos</span>
            </a>
            <a href="avaliacoes.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item mt-2"
                title="Avaliações">
                <i data-lucide="star" class="flex-shrink-0"></i>
                <span class="sidebar-text">Avaliações</span>
            </a>
            <a href="pontos_favoritos.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item active mt-2"
                title="Pontos Favoritos">
                <i data-lucide="map-pin" class="flex-shrink-0"></i>
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
            <a href="dashUSER.php?logout=1"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-400 hover:bg-red-500/30 transition-colors sidebar-item mt-2"
                title="Sair">
                <i data-lucide="log-out" class="flex-shrink-0"></i>
                <span class="sidebar-text">Sair</span>
            </a>
        </div>
    </aside>

    <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="toggleSidebar()"></div>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">
        <header class="flex justify-between items-center mb-6 lg:mb-8 flex-wrap gap-4">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-white">Meus Pontos Favoritos</h1>
                    <p class="text-gray-400 text-sm sm:text-base">Gerencie seus locais de recarga preferidos</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="novoFavorito()"
                    class="bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold py-3 px-4 sm:px-6 rounded-xl flex items-center gap-2 shadow-lg shadow-cyan-500/30 hover:shadow-cyan-500/50 transition-all duration-300 hover:scale-105">
                    <i data-lucide="plus-circle" class="w-5 h-5"></i>
                    <span class="hidden sm:inline">Adicionar Novo Favorito</span>
                    <span class="sm:hidden">Adicionar</span>
                </button>
                <button id="toggle-sidebar-btn" class="p-2 md:hidden text-white" onclick="toggleSidebar()">
                    <i data-lucide="menu" class="w-7 h-7"></i>
                </button>
            </div>
        </header>

        <div id="empty-state"
            class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl border border-cyan-500/20 p-6 empty-state">
            <div class="empty-state-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    class="text-cyan-500/40">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-300 mb-2">Nenhum ponto favorito cadastrado</h3>
            <p class="text-gray-400 mb-6 max-w-md mx-auto text-sm sm:text-base">Comece adicionando seus locais de
                recarga preferidos para acessá-los rapidamente.</p>
            <button onclick="novoFavorito()"
                class="bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-semibold py-2.5 px-6 rounded-lg flex items-center gap-2 mx-auto shadow-lg shadow-cyan-500/20 hover:shadow-cyan-500/40 transition-all duration-300">
                <i data-lucide="plus-circle" class="w-5 h-5"></i>
                <span>Adicionar Primeiro Ponto</span>
            </button>
        </div>

        <div id="lista-favoritos" class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 hidden">
        </div>
    </main>

    <div id="modalCriarFavorito"
        class="modal fixed inset-0 z-50 bg-black/70 backdrop-blur-sm items-center justify-center p-4">
        <div
            class="bg-gradient-to-br from-slate-800/95 to-slate-900/95 backdrop-blur-xl rounded-2xl border border-cyan-500/20 p-4 sm:p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-cyan-500/20">
                <h2 class="text-xl sm:text-2xl font-semibold text-white flex items-center gap-3">
                    <div
                        class="w-10 h-10 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-lg flex items-center justify-center">
                        <i data-lucide="map-pin" class="w-5 h-5 text-white"></i>
                    </div>
                    <span class="hidden sm:inline">Salvar Ponto Favorito</span>
                    <span class="sm:hidden">Novo Ponto</span>
                </h2>
                <button type="button" onclick="fecharModal('modalCriarFavorito')"
                    class="p-2 hover:bg-slate-700/50 rounded-lg transition-colors">
                    <i data-lucide="x" class="w-6 h-6 text-gray-400"></i>
                </button>
            </div>

            <form id="form-favorito" method="POST">
                <input type="hidden" id="favorito-id" name="favorito_id">
                <input type="hidden" id="input-cep" name="cep">
                <input type="hidden" id="input-logradouro" name="logradouro">
                <input type="hidden" id="input-bairro" name="bairro_nome">
                <input type="hidden" id="input-cidade" name="cidade_nome">
                <input type="hidden" id="input-estado" name="estado_uf">

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-300 mb-3">Escolha um Ícone para o Local:</label>
                    <div class="flex flex-wrap gap-3" id="lista-icones">
                        <button type="button" onclick="selectIcon('map-pin')"
                            class="icon-btn active p-3 rounded-xl bg-slate-700/50 hover:bg-cyan-500/20 border border-slate-600 hover:border-cyan-500/50 transition-all"
                            data-icon="map-pin" title="Padrão">
                            <i data-lucide="map-pin" class="w-6 h-6 text-white"></i>
                        </button>
                        <button type="button" onclick="selectIcon('home')"
                            class="icon-btn p-3 rounded-xl bg-slate-700/50 hover:bg-cyan-500/20 border border-slate-600 hover:border-cyan-500/50 transition-all"
                            data-icon="home" title="Casa">
                            <i data-lucide="home" class="w-6 h-6 text-white"></i>
                        </button>
                        <button type="button" onclick="selectIcon('briefcase')"
                            class="icon-btn p-3 rounded-xl bg-slate-700/50 hover:bg-cyan-500/20 border border-slate-600 hover:border-cyan-500/50 transition-all"
                            data-icon="briefcase" title="Trabalho">
                            <i data-lucide="briefcase" class="w-6 h-6 text-white"></i>
                        </button>
                        <button type="button" onclick="selectIcon('shopping-cart')"
                            class="icon-btn p-3 rounded-xl bg-slate-700/50 hover:bg-cyan-500/20 border border-slate-600 hover:border-cyan-500/50 transition-all"
                            data-icon="shopping-cart" title="Mercado/Shopping">
                            <i data-lucide="shopping-cart" class="w-6 h-6 text-white"></i>
                        </button>
                        <button type="button" onclick="selectIcon('heart')"
                            class="icon-btn p-3 rounded-xl bg-slate-700/50 hover:bg-cyan-500/20 border border-slate-600 hover:border-cyan-500/50 transition-all"
                            data-icon="heart" title="Lazer/Namorado(a)">
                            <i data-lucide="heart" class="w-6 h-6 text-white"></i>
                        </button>
                        <button type="button" onclick="selectIcon('zap')"
                            class="icon-btn p-3 rounded-xl bg-slate-700/50 hover:bg-cyan-500/20 border border-slate-600 hover:border-cyan-500/50 transition-all"
                            data-icon="zap" title="Posto de Carga">
                            <i data-lucide="zap" class="w-6 h-6 text-white"></i>
                        </button>
                        <button type="button" onclick="selectIcon('star')"
                            class="icon-btn p-3 rounded-xl bg-slate-700/50 hover:bg-cyan-500/20 border border-slate-600 hover:border-cyan-500/50 transition-all"
                            data-icon="star" title="Favorito">
                            <i data-lucide="star" class="w-6 h-6 text-white"></i>
                        </button>
                    </div>
                    <input type="hidden" id="input-icone-selecionado" value="map-pin">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mb-6">
                    <div class="md:col-span-2">
                        <label for="nome" class="block text-sm font-semibold text-gray-300 mb-2">Nome do Local/Apelido
                            *</label>
                        <input type="text" id="nome" name="nome" required placeholder="Ex: Casa, Trabalho, Shopping..."
                            class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-white transition-all text-sm sm:text-base">
                    </div>

                    <div class="md:col-span-2">
                        <label for="endereco-busca" class="block text-sm font-semibold text-gray-300 mb-2">Busca de
                            Endereço Exato *</label>
                        <input type="text" id="endereco-busca" name="descricao"
                            placeholder="Digite para buscar (Rua, Bairro, Cidade...)" required
                            class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-white transition-all text-sm sm:text-base">

                        <div id="loadingGeocode" class="loading-container">
                            <div class="spinner"></div>
                            <span>Buscando localização exata...</span>
                        </div>
                        <p id="geocode-error" class="text-sm text-red-400 mt-2 hidden font-semibold"></p>
                        <p id="geocode-success" class="text-sm text-green-400 mt-2 hidden font-semibold"></p>
                    </div>

                    <div class="md:col-span-2">
                        <label for="input-numero" class="block text-sm font-semibold text-gray-300 mb-2">Número
                            *</label>
                        <input type="text" id="input-numero" name="numero_residencia" required placeholder="Ex: 123"
                            class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-white transition-all text-sm sm:text-base">
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-300 mb-2">Localização no Mapa (Arraste o
                        marcador para corrigir)</label>
                    <div id="map-modal"
                        class="map-container rounded-xl shadow-inner border border-cyan-500/20 overflow-hidden"></div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="latitude" class="block text-sm font-semibold text-gray-300 mb-2">Latitude</label>
                        <input type="text" id="latitude" name="latitude" readonly required
                            class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-gray-400 text-xs sm:text-sm cursor-not-allowed">
                    </div>
                    <div>
                        <label for="longitude" class="block text-sm font-semibold text-gray-300 mb-2">Longitude</label>
                        <input type="text" id="longitude" name="longitude" readonly required
                            class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-gray-400 text-xs sm:text-sm cursor-not-allowed">
                    </div>
                </div>

                <p id="coordenadas-info"
                    class="text-sm text-cyan-400 hidden mb-6 font-semibold p-3 bg-cyan-500/10 rounded-lg border border-cyan-500/20">
                </p>

                <div class="flex flex-col sm:flex-row justify-end gap-3 mt-6 pt-6 border-t border-cyan-500/20">
                    <button type="button" onclick="fecharModal('modalCriarFavorito')"
                        class="px-6 py-3 bg-slate-700/50 hover:bg-slate-700 text-white font-semibold rounded-xl transition-all duration-300 w-full sm:w-auto">
                        <i data-lucide="x" class="w-5 h-5 inline-block mr-2"></i>
                        Cancelar
                    </button>
                    <button type="submit"
                        class="px-6 py-3 bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold rounded-xl shadow-lg shadow-cyan-500/30 hover:shadow-cyan-500/50 transition-all duration-300 hover:scale-105 w-full sm:w-auto">
                        <i data-lucide="save" class="w-5 h-5 inline-block mr-2"></i>
                        Salvar Ponto
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyD8GxprFa1NCA_pfGzXQqC6Eiflx7BeEKY&libraries=places"></script>
    <script src="../JS/pontos_favoritos.js"></script>

    <script>
        lucide.createIcons();
    </script>
</body>

</html>