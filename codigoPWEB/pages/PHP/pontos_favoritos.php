<?php
session_start();

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
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900/50 backdrop-blur-xl border-r border-cyan-500/20 p-4 flex flex-col">
        <div class="flex items-center gap-3 mb-10">
            <div
                class="w-12 h-12 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-lg flex items-center justify-center">
                <i data-lucide="zap" class="w-7 h-7 text-white"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-white">HelioMax</h1>
                <p class="text-xs text-cyan-400">Pontos Favoritos</p>
            </div>
        </div>

        <nav class="flex-grow">
            <a href="dashUSER.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item">
                <i data-lucide="layout-dashboard"></i> <span>Dashboard</span>
            </a>
            <a href="historico.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item mt-2"
                title="Histórico">
                <i data-lucide="history" class="flex-shrink-0"></i>
                <span class="sidebar-text">Histórico</span>
            </a>
            <a href="../PHP/veiculos.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item mt-2"
                title="Meus Veículos">
                <i data-lucide="car" class="flex-shrink-0"></i>
                <span class="sidebar-text">Meus Veículos</span>
            </a>
            <a href="#"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item mt-"
                title="Avaliações">
                <i data-lucide="star" class="flex-shrink-0"></i>
                <span class="sidebar-text">Avaliações</span>
            </a>
            <a href="pontos_favoritos.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item active mt-2">
                <i data-lucide="map-pin"></i> <span>Pontos Favoritos</span>
            </a>
        </nav>

        <div class="mt-auto">
            <a href="dashUSER.php?logout=1"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-400 hover:bg-red-500/30 transition-colors sidebar-item mt-2">
                <i data-lucide="log-out"></i> <span>Sair</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8 overflow-y-auto">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Meus Pontos Favoritos</h1>
                <p class="text-gray-400">Gerencie seus locais de recarga preferidos</p>
            </div>
            <button onclick="abrirModal('modalCriarFavorito')"
                class="bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold py-3 px-6 rounded-xl flex items-center gap-2 shadow-lg shadow-cyan-500/30 hover:shadow-cyan-500/50 transition-all duration-300 hover:scale-105">
                <i data-lucide="plus-circle" class="w-5 h-5"></i>
                <span>Adicionar Novo Favorito</span>
            </button>
        </header>

        <!-- Lista de Favoritos -->
        <div id="lista-favoritos" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div
                class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl border border-cyan-500/20 p-6 flex items-center justify-center min-h-[200px]">
                <p class="text-gray-400">Carregando pontos favoritos...</p>
            </div>
        </div>
    </main>

    <!-- Modal Criar/Editar Favorito -->
    <div id="modalCriarFavorito"
        class="modal fixed inset-0 z-50 bg-black/70 backdrop-blur-sm items-center justify-center p-4">
        <div
            class="bg-gradient-to-br from-slate-800/95 to-slate-900/95 backdrop-blur-xl rounded-2xl border border-cyan-500/20 p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-cyan-500/20">
                <h2 class="text-2xl font-semibold text-white flex items-center gap-3">
                    <div
                        class="w-10 h-10 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-lg flex items-center justify-center">
                        <i data-lucide="map-pin" class="w-5 h-5 text-white"></i>
                    </div>
                    Salvar Ponto Favorito
                </h2>
                <button type="button" onclick="fecharModal('modalCriarFavorito')"
                    class="p-2 hover:bg-slate-700/50 rounded-lg transition-colors">
                    <i data-lucide="x" class="w-6 h-6 text-gray-400"></i>
                </button>
            </div>

            <form id="form-favorito" method="POST">
                <input type="hidden" id="favorito-id" name="favorito_id">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="md:col-span-2">
                        <label for="nome" class="block text-sm font-semibold text-gray-300 mb-2">Nome do
                            Local/Apelido</label>
                        <input type="text" id="nome" name="nome" required
                            class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-white transition-all">
                    </div>

                    <div class="md:col-span-2">
                        <label for="endereco-busca" class="block text-sm font-semibold text-gray-300 mb-2">Endereço
                            (Pesquise e Confirme)</label>
                        <input type="text" id="endereco-busca" name="descricao"
                            placeholder="Av. Maximiliano Baruto, 500" required
                            class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-white transition-all">
                    </div>

                    <div>
                        <label for="input-cep" class="block text-sm font-semibold text-gray-300 mb-2">CEP</label>
                        <input type="text" id="input-cep" name="cep" required placeholder="Ex: 13611-100"
                            class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-white transition-all">
                    </div>

                    <div>
                        <label for="input-numero" class="block text-sm font-semibold text-gray-300 mb-2">Número</label>
                        <input type="text" id="input-numero" name="numero_residencia"
                            class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-white transition-all">
                    </div>

                    <div class="md:col-span-2">
                        <label for="input-logradouro"
                            class="block text-sm font-semibold text-gray-300 mb-2">Logradouro</label>
                        <input type="text" id="input-logradouro" name="logradouro" required
                            class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-white transition-all">
                    </div>
                </div>

                <input type="hidden" id="input-bairro" name="bairro_nome">
                <input type="hidden" id="input-cidade" name="cidade_nome">
                <input type="hidden" id="input-estado" name="estado_uf">

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-300 mb-2">Localização no Mapa (Clique ou Arraste
                        o Marcador)</label>
                    <div id="map-modal"
                        class="map-container rounded-xl shadow-inner border border-cyan-500/20 overflow-hidden"></div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="latitude" class="block text-sm font-semibold text-gray-300 mb-2">Latitude</label>
                        <input type="text" id="latitude" name="latitude" readonly required
                            class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-gray-400">
                    </div>
                    <div>
                        <label for="longitude" class="block text-sm font-semibold text-gray-300 mb-2">Longitude</label>
                        <input type="text" id="longitude" name="longitude" readonly required
                            class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-gray-400">
                    </div>
                </div>

                <p id="coordenadas-info"
                    class="text-sm text-cyan-400 hidden mb-6 font-semibold p-3 bg-cyan-500/10 rounded-lg border border-cyan-500/20">
                </p>

                <div class="flex justify-end gap-3 mt-6 pt-6 border-t border-cyan-500/20">
                    <button type="button" onclick="fecharModal('modalCriarFavorito')"
                        class="px-6 py-3 bg-slate-700/50 hover:bg-slate-700 text-white font-semibold rounded-xl transition-all duration-300">
                        <i data-lucide="x" class="w-5 h-5 inline-block mr-2"></i>
                        Cancelar
                    </button>
                    <button type="submit"
                        class="px-6 py-3 bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold rounded-xl shadow-lg shadow-cyan-500/30 hover:shadow-cyan-500/50 transition-all duration-300 hover:scale-105">
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

        function abrirModal(id) {
            document.getElementById(id).classList.add('active');
            document.getElementById('form-favorito').reset();
            document.getElementById('favorito-id').value = '';

            setTimeout(() => {
                lucide.createIcons();
                if (window.initMap) window.initMap();
            }, 100);
        }

        function fecharModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        // Fechar modal ao clicar fora
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function (e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>

</html>