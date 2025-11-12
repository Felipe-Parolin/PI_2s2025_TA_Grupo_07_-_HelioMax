<?php
session_start();

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
    <title>Dashboard - Meus Veículos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Estilos para o modal de perfil (da resposta anterior) */
        .modal {
            display: none;
            /* Escondido por padrão */
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }

        .modal.active {
            display: flex;
            /* Visível quando 'active' (flex para centralizar) */
            opacity: 1;
        }

        /* --- NOVOS ESTILOS PARA AS ABAS --- */
        .tab-link {
            padding: 0.75rem 1.25rem;
            border-bottom: 3px solid transparent;
            color: #94a3b8;
            /* slate-400 */
            transition: all 0.2s;
        }

        .tab-link:hover {
            color: #e2e8f0;
            /* slate-200 */
        }

        .tab-link.active {
            color: #22d3ee;
            /* cyan-400 */
            border-bottom-color: #22d3ee;
            /* cyan-400 */
        }

        .tab-content {
            display: none;
            /* Esconde todas as abas */
        }

        .tab-content.active {
            display: block;
            /* Mostra a aba ativa */
        }

        /* Estilos customizados */
        .sidebar-item.active {
            background-color: #0284c7;
            color: white;
        }

        .sidebar-mobile-hidden {
            transform: translateX(-100%);
        }

        /* Efeito de Destaque no Card ao Passar o Mouse */
        .vehicle-card {
            transition: all 0.3s ease-in-out;
            cursor: pointer;
            position: relative;
        }

        .vehicle-card:hover {
            transform: translateY(-5px);
            border-color: #06b6d4;
        }

        /* Breakpoints customizados para tablets */
        @media (min-width: 768px) and (max-width: 1279px) {
            aside#sidebar {
                width: 5rem !important;
            }

            aside#sidebar .sidebar-text {
                display: none !important;
            }

            aside#sidebar .sidebar-item {
                justify-content: center !important;
                padding: 0.75rem !important;
            }

            aside#sidebar h1,
            aside#sidebar p {
                display: none !important;
            }

            aside#sidebar .flex.items-center.gap-3.mb-10 {
                justify-content: center;
                margin-bottom: 2rem;
            }

            aside#sidebar .w-12 {
                width: 2.5rem !important;
                height: 2.5rem !important;
            }

            aside#sidebar .mt-auto {
                border-top: 1px solid rgba(6, 182, 212, 0.2);
            }
        }

        @media (min-width: 1280px) {
            aside#sidebar {
                width: 16rem !important;
            }
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
                <p class="text-xs text-cyan-400">Meus veículos</p>
            </div>
        </div>

        <nav class="flex-grow">
            <a href="../PHP/dashUSER.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item"
                id="sidebar-dashboard" title="Dashboard">
                <i data-lucide="layout-dashboard" class="flex-shrink-0"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <a href="#"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item mt-2"
                id="sidebar-historico" title="Histórico">
                <i data-lucide="history" class="flex-shrink-0"></i>
                <span class="sidebar-text">Histórico</span>
            </a>
            <a href="#"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item active mt-2"
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
                <i data-lucide="map-pin"></i> <span>Pontos Favoritos</span>
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

    <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="toggleSidebar()"></div>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">
        <header class="flex justify-between items-center mb-6 lg:mb-8 flex-wrap gap-4">
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl sm:text-3xl font-bold text-white">Meus Veículos</h1>
                <p class="text-gray-400 text-sm sm:text-base">Gerencie seus veículos elétricos cadastrados.</p>
            </div>

            <div class="flex items-center gap-3">
                <button id="toggle-sidebar-btn" class="p-2 md:hidden text-white" onclick="toggleSidebar()">
                    <i data-lucide="menu" class="w-7 h-7"></i>
                </button>

                <button id="btn-cadastrar-veiculo"
                    class="hidden sm:flex bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold py-2.5 sm:py-3 px-4 sm:px-6 rounded-xl items-center gap-2 shadow-lg shadow-cyan-500/30 hover:shadow-cyan-500/50 transition-all duration-300 hover:scale-[1.02] text-sm sm:text-base">
                    <i data-lucide="plus-circle" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                    <span class="hidden lg:inline">Cadastrar Veículo</span>
                    <span class="lg:hidden">Cadastrar</span>
                </button>
            </div>
        </header>

        <section
            class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl border border-cyan-500/20 p-4 sm:p-6">
            <div class="flex justify-between items-center mb-4 border-b border-cyan-500/10 pb-3 flex-wrap gap-3">
                <h2 class="text-xl sm:text-2xl font-semibold">Lista de Veículos</h2>
                <button id="btn-cadastrar-veiculo-mobile"
                    class="sm:hidden bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-xl text-sm flex items-center gap-1">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Cadastrar
                </button>
            </div>

            <div id="vehicle-list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6">
                <article onclick="document.getElementById('btn-cadastrar-veiculo').click()"
                    class="hidden xl:flex bg-slate-800/20 p-6 rounded-xl border-2 border-dashed border-cyan-500/50 text-center text-gray-500 flex-col items-center justify-center cursor-pointer hover:border-cyan-400/80 hover:bg-slate-800/30 transition-all duration-300">
                    <i data-lucide="plus-circle" class="w-12 h-12 mb-2 text-cyan-500"></i>
                    <p class="text-lg font-medium text-cyan-400">Adicionar Novo Veículo</p>
                    <p class="text-sm text-gray-500">Clique para cadastrar um novo veículo elétrico.</p>
                </article>
            </div>

            <div id="empty-state" class="text-center py-12 sm:py-20 hidden">
                <i data-lucide="circle-slash" class="w-12 h-12 sm:w-16 sm:h-16 mx-auto text-gray-600"></i>
                <p class="mt-4 text-lg sm:text-xl text-gray-400">Você ainda não possui veículos cadastrados.</p>
                <p class="text-sm sm:text-base text-gray-500">Clique em "Cadastrar Veículo" para começar.</p>
            </div>
        </section>
    </main>

    <!-- Modal de Cadastro/Edição de Veículo -->
    <div id="modal-veiculo"
        class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4 z-[99] hidden transition-opacity duration-300">
        <div id="modal-content"
            class="bg-slate-800 border border-cyan-500/30 rounded-2xl shadow-xl w-full max-w-lg transition-all duration-300 opacity-0 scale-95 transform">

            <header class="flex justify-between items-center p-4 sm:p-5 border-b border-slate-700">
                <h2 id="modal-title" class="text-lg sm:text-xl font-bold text-white">Cadastrar Novo Veículo</h2>
                <button id="btn-fechar-modal" class="text-gray-400 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </header>

            <form id="form-veiculo" class="p-4 sm:p-6 space-y-4">
                <input type="hidden" id="veiculo-id" name="veiculo_id">
                <div id="modal-error-message"
                    class="hidden p-3 bg-red-500/20 border border-red-500 text-red-300 text-sm rounded-lg"></div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="marca" class="block text-sm font-medium text-cyan-300 mb-1">Marca</label>
                        <select id="marca" name="marca_id" required
                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                    <div>
                        <label for="modelo" class="block text-sm font-medium text-cyan-300 mb-1">Modelo</label>
                        <select id="modelo" name="modelo_id" required
                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-cyan-500"
                            disabled>
                            <option value="">Selecione a marca primeiro</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="placa" class="block text-sm font-medium text-cyan-300 mb-1">Placa</label>
                        <input type="text" id="placa" name="placa" required maxlength="10" placeholder="ABC1D23"
                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                    </div>
                    <div>
                        <label for="ano_fab" class="block text-sm font-medium text-cyan-300 mb-1">Ano Fabricação</label>
                        <input type="number" id="ano_fab" name="ano_fab" required min="1990" max="2099"
                            placeholder="2024"
                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="cor" class="block text-sm font-medium text-cyan-300 mb-1">Cor</label>
                        <select id="cor" name="cor_id" required
                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                    <div>
                        <label for="conector" class="block text-sm font-medium text-cyan-300 mb-1">Tipo de
                            Conector</label>
                        <select id="conector" name="conector_id" required
                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="nivel_bateria" class="block text-sm font-medium text-cyan-300 mb-1">Nível Bateria
                            (%)</label>
                        <input type="number" id="nivel_bateria" name="nivel_bateria" required min="0" max="100"
                            placeholder="100"
                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                    </div>
                </div>

                <footer class="flex justify-end gap-3 pt-4 mt-2">
                    <button type="button" id="btn-cancelar-modal"
                        class="py-2 px-5 rounded-lg bg-slate-600 hover:bg-slate-500 text-white font-semibold transition-colors">
                        Cancelar
                    </button>
                    <button type="submit" id="btn-salvar-veiculo" class="py-2 px-5 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white font-semibold transition-colors flex items-center gap-2
                               disabled:bg-slate-500 disabled:cursor-not-allowed">
                        Salvar Veículo
                    </button>
                </footer>
            </form>
        </div>
    </div>

    <!-- Modal de Perfil -->
    <div id="modalPerfil" class="modal fixed inset-0 bg-black/70 backdrop-blur-sm items-center justify-center z-50 p-4">
        <div
            class="bg-gradient-to-br from-slate-800/95 to-slate-900/95 backdrop-blur-xl rounded-2xl border border-cyan-500/20 max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-cyan-500/20 flex items-center justify-between sticky top-0 bg-slate-900/90">
                <div class="flex items-center gap-4">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-2xl flex items-center justify-center">
                        <i data-lucide="user" class="w-8 h-8 text-white"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-white">
                            <?php echo htmlspecialchars($usuario_logado['NOME']); ?>
                        </h2>
                        <p class="text-gray-400"><?php echo htmlspecialchars($usuario_logado['EMAIL']); ?></p>
                    </div>
                </div>
                <button onclick="fecharModal('modalPerfil')"
                    class="p-2 hover:bg-slate-700/50 rounded-lg transition-colors">
                    <i data-lucide="x" class="w-6 h-6 text-gray-400"></i>
                </button>
            </div>

            <!-- Conteúdo adicional do modal pode ser adicionado aqui -->
        </div>
    </div>

    <script src="../JS/meus_veiculos.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            sidebar.classList.toggle('sidebar-mobile-hidden');
            overlay.classList.toggle('hidden');
        }

        function abrirModal(id) {
            document.getElementById(id).classList.add('active');
            setTimeout(() => lucide.createIcons(), 100);
        }

        function fecharModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        // Fecha modal ao clicar fora
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