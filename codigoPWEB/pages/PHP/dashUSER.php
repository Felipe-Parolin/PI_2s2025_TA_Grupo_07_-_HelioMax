<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../HTML/landpage.html');
    exit;
}

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

$mensagem = '';
$tipo_mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'atualizar_perfil') {
        $mensagem = 'Perfil atualizado com sucesso!';
        $tipo_mensagem = 'sucesso';
    }

    if ($action === 'alterar_senha') {
        $mensagem = 'Senha alterada com sucesso!';
        $tipo_mensagem = 'sucesso';
    }

    if ($action === 'salvar_veiculo') {
        $marca = $_POST['marca'];
        $modelo = $_POST['modelo'];
        $mensagem = 'Veículo "' . htmlspecialchars($marca) . ' ' . htmlspecialchars($modelo) . '" salvo com sucesso!';
        $tipo_mensagem = 'sucesso';
    }
}

$stmt_pontos = $pdo->prepare("
    SELECT pc.*, sp.DESCRICAO as status_desc, c.LOGRADOURO, b.NOME as bairro, ci.NOME as cidade, e.UF
    FROM ponto_carregamento pc
    JOIN status_ponto sp ON pc.FK_STATUS_PONTO = sp.ID_STATUS_PONTO
    JOIN cep c ON pc.LOCALIZACAO = c.ID_CEP
    JOIN bairro b ON c.FK_BAIRRO = b.ID_BAIRRO
    JOIN cidade ci ON b.FK_CIDADE = ci.ID_CIDADE
    JOIN estado e ON ci.FK_ESTADO = e.ID_ESTADO
    WHERE sp.DESCRICAO = 'Ativo'
    ORDER BY ci.NOME, b.NOME
");
$stmt_pontos->execute();
$pontos_disponiveis = $stmt_pontos->fetchAll(PDO::FETCH_ASSOC);

$meus_veiculos = [
    ['ID' => 1, 'MARCA' => 'Renault', 'MODELO' => 'Zoe', 'BATERIA_KWH' => 52],
    ['ID' => 2, 'MARCA' => 'Chevrolet', 'MODELO' => 'Bolt', 'BATERIA_KWH' => 65]
];
$historico_recargas = [
    ['DATA' => '2025-10-10', 'LOCAL' => 'Shopping Central', 'KWH' => 35.5, 'VALOR' => 65.67],
    ['DATA' => '2025-10-05', 'LOCAL' => 'Posto Petrobras', 'KWH' => 42.0, 'VALOR' => 79.80],
    ['DATA' => '2025-09-28', 'LOCAL' => 'Mercado Municipal', 'KWH' => 28.8, 'VALOR' => 51.84]
];

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="../../images/icon.png">
    <title>Dashboard do Usuário</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .modal {
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }

        .modal.active {
            display: flex;
            opacity: 1;
        }

        .sidebar-item.active {
            background-color: #0284c7;
            color: white;
        }

        .sidebar-mobile-hidden {
            transform: translateX(-100%);
        }

        @keyframes slideInFromRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
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

        @media (max-width: 768px) {
            .fixed-notification {
                bottom: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
                padding: 0.9rem 1.2rem;
            }

            .fixed-notification-text {
                font-size: 0.85rem;
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
                <p class="text-xs text-cyan-400">Bem-vindo(a)!</p>
            </div>
        </div>

        <nav class="flex-grow">
            <a href="#"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item active"
                title="Dashboard">
                <i data-lucide="layout-dashboard" class="flex-shrink-0"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <a href="javascript:abrirModal('modalHistorico')"
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
            <a href="../PHP/avaliacoes.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item mt-2"
                title="Avaliação">
                <i data-lucide="star" class="flex-shrink-0"></i>
                <span class="sidebar-text">Avaliação</span>
            </a>
        </nav>

        <div class="mt-auto pt-4 border-t border-cyan-500/20">
            <a href="perfil.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item"
                title="Minha Conta">
                <i data-lucide="user-cog" class="flex-shrink-0"></i>
                <span class="sidebar-text">Minha Conta</span>
            </a>
            <a href="?logout=1"
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
                <h1 class="text-2xl sm:text-3xl font-bold text-white">Olá,
                    <?php echo htmlspecialchars(explode(' ', $_SESSION['usuario_nome'])[0]); ?>!
                </h1>
                <p class="text-gray-400 text-sm sm:text-base">Pronto para encontrar o melhor ponto de recarga para sua
                    viagem?</p>
            </div>

            <div class="flex items-center gap-3">
                <button id="toggle-sidebar-btn" class="p-2 md:hidden text-white" onclick="toggleSidebar()">
                    <i data-lucide="menu" class="w-7 h-7"></i>
                </button>

                
            </div>
        </header>

        <?php if ($mensagem): ?>
            <div
                class="mb-6 p-4 <?php echo $tipo_mensagem === 'sucesso' ? 'bg-green-500/20 border-green-500/30' : 'bg-red-500/20 border-red-500/30'; ?> border rounded-xl flex items-center gap-3">
                <i data-lucide="<?php echo $tipo_mensagem === 'sucesso' ? 'check-circle' : 'alert-circle'; ?>"
                    class="w-5 h-5 <?php echo $tipo_mensagem === 'sucesso' ? 'text-green-400' : 'text-red-400'; ?>"></i>
                <p class="<?php echo $tipo_mensagem === 'sucesso' ? 'text-green-400' : 'text-red-400'; ?>">
                    <?php echo $mensagem; ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
    <div class="lg:col-span-2 bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl border border-cyan-500/20 p-4 flex flex-col"
        style="min-height: 400px; height: calc(100vh - 250px);">

        <div class="w-full h-full rounded-lg relative overflow-hidden">

            <img src="https://maps.geoapify.com/v1/staticmap?style=klokantech-basic&width=800&height=600&center=lonlat:-47.8825,-14.2350&zoom=4&apiKey=a94d6d7cd9de4604aea43f8e8a1d0a36"
                alt="Mapa estático do Brasil"
                class="absolute inset-0 w-full h-full object-cover">

            <div class="absolute inset-0 bg-slate-900/70"></div>

            <div class="absolute inset-0 flex items-center justify-center p-4">
                <a href="simulador.php"
                    class="whitespace-nowrap bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold py-3 px-6 rounded-xl flex items-center gap-2 shadow-lg shadow-cyan-500/30 hover:shadow-cyan-500/50 transition-all duration-300 hover:scale-[1.03] text-base">
                    <i data-lucide="route" class="w-5 h-5"></i>
                    <span>Planejar Rota</span>
                </a>
            </div>
        </div>
        </div>

            <div class="flex flex-col bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl border border-cyan-500/20 p-4 sm:p-6"
                style="min-height: 400px; max-height: calc(100vh - 250px);">
                <h2 class="text-xl sm:text-2xl font-bold mb-4">Pontos Disponíveis</h2>
                <div class="overflow-y-auto flex-1">
                    <?php if (empty($pontos_disponiveis)): ?>
                        <p class="text-gray-400 text-center py-10 text-sm sm:text-base">Nenhum ponto ativo encontrado no
                            momento.</p>
                    <?php else: ?>
                        <?php foreach ($pontos_disponiveis as $ponto): ?>
                            <div class="p-3 sm:p-4 rounded-lg hover:bg-slate-700/50 transition-colors mb-2">
                                <div class="flex justify-between items-center">
                                    <p class="font-semibold text-sm sm:text-base">
                                        <?php echo htmlspecialchars($ponto['LOGRADOURO']); ?>
                                    </p>
                                    <span class="text-xs font-bold text-green-400">Ativo</span>
                                </div>
                                <p class="text-xs sm:text-sm text-gray-400"><?php echo htmlspecialchars($ponto['cidade']); ?> -
                                    <?php echo htmlspecialchars($ponto['UF']); ?>
                                </p>
                                <p class="text-xs sm:text-sm font-semibold text-cyan-400 mt-1">R$
                                    <?php echo number_format($ponto['VALOR_KWH'], 2, ',', '.'); ?> / kWh
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de Veículos -->
    <div id="modalVeiculos"
        class="modal fixed inset-0 bg-black/70 backdrop-blur-sm items-center justify-center z-50 p-4">
        <div
            class="bg-gradient-to-br from-slate-800/95 to-slate-900/95 backdrop-blur-xl rounded-2xl border border-cyan-500/20 max-w-2xl w-full">
            <div class="p-6 border-b border-cyan-500/20 flex items-center justify-between">
                <h2 class="text-2xl font-bold text-white flex items-center gap-2"><i data-lucide="car"
                        class="w-6 h-6 text-cyan-400"></i> Meus Veículos</h2>
                <button onclick="fecharModal('modalVeiculos')" class="p-2 hover:bg-slate-700/50 rounded-lg"><i
                        data-lucide="x" class="w-6 h-6 text-gray-400"></i></button>
            </div>
            <div class="p-6">
                <form method="POST" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end mb-6">
                    <input type="hidden" name="action" value="salvar_veiculo">
                    <div class="md:col-span-1"><label class="text-sm font-semibold">Marca</label><input type="text"
                            name="marca" required
                            class="w-full mt-1 px-4 py-2 bg-slate-900/50 border border-cyan-500/20 rounded-xl"></div>
                    <div class="md:col-span-1"><label class="text-sm font-semibold">Modelo</label><input type="text"
                            name="modelo" required
                            class="w-full mt-1 px-4 py-2 bg-slate-900/50 border border-cyan-500/20 rounded-xl"></div>
                    <button type="submit"
                        class="w-full bg-cyan-500 hover:bg-cyan-600 text-white font-bold py-2 px-4 rounded-xl">Adicionar</button>
                </form>
                <div class="border-t border-cyan-500/20 pt-4">
                    <?php foreach ($meus_veiculos as $veiculo): ?>
                        <div class="flex justify-between items-center p-2 rounded-lg hover:bg-slate-800/50">
                            <p><?php echo htmlspecialchars($veiculo['MARCA']); ?>
                                <?php echo htmlspecialchars($veiculo['MODELO']); ?>
                            </p>
                            <span class="text-sm text-gray-400"><?php echo $veiculo['BATERIA_KWH']; ?> kWh</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Histórico -->
    <div id="modalHistorico"
        class="modal fixed inset-0 bg-black/70 backdrop-blur-sm items-center justify-center z-50 p-4">
        <div
            class="bg-gradient-to-br from-slate-800/95 to-slate-900/95 backdrop-blur-xl rounded-2xl border border-cyan-500/20 max-w-3xl w-full">
            <div class="p-6 border-b border-cyan-500/20 flex items-center justify-between">
                <h2 class="text-2xl font-bold text-white flex items-center gap-2"><i data-lucide="history"
                        class="w-6 h-6 text-cyan-400"></i> Histórico de Recargas</h2>
                <button onclick="fecharModal('modalHistorico')" class="p-2 hover:bg-slate-700/50 rounded-lg"><i
                        data-lucide="x" class="w-6 h-6 text-gray-400"></i></button>
            </div>
            <div class="p-6 max-h-[60vh] overflow-y-auto">
                <table class="w-full text-left text-sm sm:text-base">
                    <thead class="border-b border-cyan-500/20">
                        <tr>
                            <th class="p-2">Data</th>
                            <th class="p-2">Local</th>
                            <th class="p-2 text-right">kWh</th>
                            <th class="p-2 text-right">Valor Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico_recargas as $recarga): ?>
                            <tr class="border-b border-cyan-500/10">
                                <td class="p-2"><?php echo $recarga['DATA']; ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($recarga['LOCAL']); ?></td>
                                <td class="p-2 text-right"><?php echo number_format($recarga['KWH'], 2, ','); ?></td>
                                <td class="p-2 text-right text-cyan-400">R$
                                    <?php echo number_format($recarga['VALOR'], 2, ',', '.'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

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

        // Inicializa ícones após carregar
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>

</html>