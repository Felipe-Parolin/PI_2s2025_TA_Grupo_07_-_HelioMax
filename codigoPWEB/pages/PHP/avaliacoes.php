<?php
session_start();

// Configuração do banco de dados
$host = '127.0.0.1';
$dbname = 'heliomax';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar e adicionar coluna EDITADO se não existir
    try {
        $result = $pdo->query("SHOW COLUMNS FROM avaliacao LIKE 'EDITADO'");
        if ($result->rowCount() == 0) {
            $pdo->exec("ALTER TABLE avaliacao ADD COLUMN EDITADO TINYINT(1) NOT NULL DEFAULT 0 AFTER DATA_AVALIACAO");
            // Atualizar registros existentes
            $pdo->exec("UPDATE avaliacao SET EDITADO = 0 WHERE EDITADO IS NULL");
        }
    } catch (Exception $e) {
        // Silenciosamente ignora se houver erro
    }

} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../HTML/landpage.html');
    exit;
}

// Variáveis de controle
$mensagem = '';
$tipo_mensagem = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CRIAR AVALIAÇÃO
    if ($action === 'criar_avaliacao') {
        $ponto_id = $_POST['ponto_id'];
        $nota = $_POST['nota'];
        $comentario = trim($_POST['comentario']);

        try {
            // Verificar se o ponto existe
            $stmt = $pdo->prepare("SELECT ID_PONTO FROM ponto_carregamento WHERE ID_PONTO = ?");
            $stmt->execute([$ponto_id]);

            if ($stmt->fetch()) {
                // Verificar se o usuário já avaliou este ponto
                $stmt = $pdo->prepare("SELECT ID_AVALIACAO FROM avaliacao WHERE FK_ID_USUARIO = ? AND FK_PONTO_CARRRGAMENTO = ?");
                $stmt->execute([$usuario_id, $ponto_id]);

                if ($stmt->fetch()) {
                    $mensagem = 'Você já avaliou este ponto de carregamento!';
                    $tipo_mensagem = 'erro';
                } else {
                    // Inserir avaliação com EDITADO = 0 (não editado)
                    $stmt = $pdo->prepare("INSERT INTO avaliacao (COMENTARIO, NOTA, DATA_AVALIACAO, FK_ID_USUARIO, FK_PONTO_CARRRGAMENTO) VALUES (?, ?, NOW(), ?, ?)");
                    $stmt->execute([$comentario, $nota, $usuario_id, $ponto_id]);

                    $mensagem = 'Avaliação cadastrada com sucesso!';
                    $tipo_mensagem = 'sucesso';
                }
            } else {
                $mensagem = 'Ponto de carregamento não encontrado!';
                $tipo_mensagem = 'erro';
            }
        } catch (Exception $e) {
            $mensagem = 'Erro ao cadastrar avaliação: ' . $e->getMessage();
            $tipo_mensagem = 'erro';
        }
    }

    // EDITAR AVALIAÇÃO
    if ($action === 'editar_avaliacao') {
        $avaliacao_id = intval($_POST['avaliacao_id']);
        $nota = $_POST['nota'];
        $comentario = trim($_POST['comentario']);

        try {
            // IMPORTANTE: Primeiro verifica se a avaliação pertence ao usuário
            $stmt = $pdo->prepare("SELECT ID_AVALIACAO FROM avaliacao WHERE ID_AVALIACAO = ? AND FK_ID_USUARIO = ?");
            $stmt->execute([$avaliacao_id, $usuario_id]);

            if ($stmt->fetch()) {
                // Atualiza APENAS a avaliação específica do usuário e marca como editada
                $stmt = $pdo->prepare("UPDATE avaliacao SET NOTA = ?, COMENTARIO = ?, DATA_AVALIACAO = NOW(), EDITADO = 1 WHERE ID_AVALIACAO = ? AND FK_ID_USUARIO = ?");
                $result = $stmt->execute([$nota, $comentario, $avaliacao_id, $usuario_id]);

                if ($result) {
                    // Verificar se realmente atualizou
                    $stmt_verify = $pdo->prepare("SELECT EDITADO FROM avaliacao WHERE ID_AVALIACAO = ?");
                    $stmt_verify->execute([$avaliacao_id]);
                    $verify = $stmt_verify->fetch();

                    $mensagem = 'Avaliação atualizada com sucesso!';
                    $tipo_mensagem = 'sucesso';
                } else {
                    $mensagem = 'Erro ao atualizar avaliação!';
                    $tipo_mensagem = 'erro';
                }
            } else {
                $mensagem = 'Avaliação não encontrada ou você não tem permissão para editar!';
                $tipo_mensagem = 'erro';
            }
        } catch (Exception $e) {
            $mensagem = 'Erro ao editar avaliação: ' . $e->getMessage();
            $tipo_mensagem = 'erro';
        }
    }

    // DELETAR AVALIAÇÃO
    if ($action === 'deletar_avaliacao') {
        $avaliacao_id = $_POST['avaliacao_id'];

        try {
            // Verificar se a avaliação pertence ao usuário logado
            $stmt = $pdo->prepare("DELETE FROM avaliacao WHERE ID_AVALIACAO = ? AND FK_ID_USUARIO = ?");
            $stmt->execute([$avaliacao_id, $usuario_id]);

            if ($stmt->rowCount() > 0) {
                $mensagem = 'Avaliação excluída com sucesso!';
                $tipo_mensagem = 'sucesso';
            } else {
                $mensagem = 'Avaliação não encontrada ou você não tem permissão para excluir!';
                $tipo_mensagem = 'erro';
            }
        } catch (Exception $e) {
            $mensagem = 'Erro ao excluir avaliação: ' . $e->getMessage();
            $tipo_mensagem = 'erro';
        }
    }
}

// Buscar filtros
$busca = $_GET['busca'] ?? '';
$nota_filtro = $_GET['nota'] ?? '';

// Buscar avaliações
$sql = "SELECT a.ID_AVALIACAO, a.COMENTARIO, a.NOTA, a.DATA_AVALIACAO, 
        COALESCE(a.EDITADO, 0) as EDITADO,
        a.FK_ID_USUARIO, a.FK_PONTO_CARRRGAMENTO,
        u.NOME as usuario_nome, 
        c.LOGRADOURO, b.NOME as bairro, ci.NOME as cidade, e.UF,
        pc.ID_PONTO, pc.VALOR_KWH
        FROM avaliacao a
        INNER JOIN usuario u ON a.FK_ID_USUARIO = u.ID_USER
        INNER JOIN ponto_carregamento pc ON a.FK_PONTO_CARRRGAMENTO = pc.ID_PONTO
        LEFT JOIN cep c ON pc.LOCALIZACAO = c.ID_CEP
        LEFT JOIN bairro b ON c.FK_BAIRRO = b.ID_BAIRRO
        LEFT JOIN cidade ci ON b.FK_CIDADE = ci.ID_CIDADE
        LEFT JOIN estado e ON ci.FK_ESTADO = e.ID_ESTADO
        WHERE 1=1";

$params = [];

if ($busca) {
    $sql .= " AND (c.LOGRADOURO LIKE ? OR b.NOME LIKE ? OR ci.NOME LIKE ? OR a.COMENTARIO LIKE ?)";
    $params = array_merge($params, ["%$busca%", "%$busca%", "%$busca%", "%$busca%"]);
}

if ($nota_filtro) {
    $sql .= " AND a.NOTA = ?";
    $params[] = $nota_filtro;
}

$sql .= " ORDER BY a.DATA_AVALIACAO DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$avaliacoes = $stmt->fetchAll();

// Buscar pontos para o dropdown
$pontos_disponiveis = $pdo->query("
    SELECT pc.ID_PONTO, c.LOGRADOURO, b.NOME as bairro, ci.NOME as cidade, e.UF
    FROM ponto_carregamento pc
    LEFT JOIN cep c ON pc.LOCALIZACAO = c.ID_CEP
    LEFT JOIN bairro b ON c.FK_BAIRRO = b.ID_BAIRRO
    LEFT JOIN cidade ci ON b.FK_CIDADE = ci.ID_CIDADE
    LEFT JOIN estado e ON ci.FK_ESTADO = e.ID_ESTADO
    WHERE pc.FK_STATUS_PONTO = 1
    ORDER BY ci.NOME, c.LOGRADOURO
")->fetchAll();

// Calcular estatísticas
$total_avaliacoes = count($avaliacoes);
$soma_notas = array_sum(array_column($avaliacoes, 'NOTA'));
$media_geral = $total_avaliacoes > 0 ? round($soma_notas / $total_avaliacoes, 1) : 0;

// Contar avaliações por nota
$avaliacoes_por_nota = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($avaliacoes as $av) {
    $avaliacoes_por_nota[$av['NOTA']]++;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliações - HelioMax</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .modal {
            display: none;
        }

        .modal.active {
            display: flex;
        }

        .sidebar-item.active {
            background-color: #0284c7;
            color: white;
        }

        .star-rating {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .star-icon,
        .star-icon-editar {
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-block;
        }

        .star-icon svg,
        .star-icon-editar svg {
            width: 40px;
            height: 40px;
            transition: all 0.2s ease;
        }

        .star-icon.inactive svg,
        .star-icon-editar.inactive svg {
            fill: none;
            stroke: #64748b;
            stroke-width: 2;
        }

        .star-icon.active svg,
        .star-icon-editar.active svg {
            fill: #fbbf24;
            stroke: #fbbf24;
            stroke-width: 2;
        }

        .star-icon:hover svg,
        .star-icon-editar:hover svg {
            transform: scale(1.15);
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white flex">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-slate-900/50 backdrop-blur-xl border-r border-cyan-500/20 p-4 flex flex-col">
        <div class="flex items-center gap-3 mb-10">
            <div
                class="w-12 h-12 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-lg flex items-center justify-center">
                <i data-lucide="zap" class="w-7 h-7 text-white"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-white">HelioMax</h1>
                <p class="text-xs text-cyan-400">Avaliações</p>
            </div>
        </div>

        <nav class="flex-grow">
            <a href="dashUSER.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item">
                <i data-lucide="layout-dashboard"></i> <span>Dashboard</span>
            </a>
            <a href="avaliacoes.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-cyan-600/50 transition-colors sidebar-item active mt-2">
                <i data-lucide="star"></i> <span>Avaliações</span>
            </a>
        </nav>

        <div class="mt-auto">
            <a href="?logout=1"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-400 hover:bg-red-500/30 transition-colors sidebar-item mt-2">
                <i data-lucide="log-out"></i> <span>Sair</span>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-8 overflow-y-auto">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Avaliações dos Pontos</h1>
                <p class="text-gray-400">Veja o que os usuários estão dizendo sobre os pontos de recarga</p>
            </div>
            <button onclick="abrirModal('modalCriarAvaliacao')"
                class="bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 text-white font-bold py-3 px-6 rounded-xl flex items-center gap-2 shadow-lg shadow-yellow-500/30 hover:shadow-yellow-500/50 transition-all duration-300 hover:scale-105">
                <i data-lucide="star"></i>
                <span>Nova Avaliação</span>
            </button>
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

        <!-- CARDS DE ESTATÍSTICAS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div
                class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl p-6 border border-cyan-500/20 hover:border-cyan-500/40 transition-all duration-300 hover:scale-105">
                <div class="flex items-center justify-between mb-4">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-yellow-500 to-orange-500 flex items-center justify-center">
                        <i data-lucide="star" class="w-6 h-6 text-white"></i>
                    </div>
                </div>
                <h3 class="text-gray-400 text-sm mb-1">Total de Avaliações</h3>
                <p class="text-3xl font-bold text-white"><?php echo $total_avaliacoes; ?></p>
            </div>

            <div
                class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl p-6 border border-cyan-500/20 hover:border-cyan-500/40 transition-all duration-300 hover:scale-105">
                <div class="flex items-center justify-between mb-4">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500 to-emerald-500 flex items-center justify-center">
                        <i data-lucide="trending-up" class="w-6 h-6 text-white"></i>
                    </div>
                </div>
                <h3 class="text-gray-400 text-sm mb-1">Média Geral</h3>
                <div class="flex items-center gap-2">
                    <p class="text-3xl font-bold text-white"><?php echo number_format($media_geral, 1, ',', '.'); ?></p>
                    <i data-lucide="star" class="w-6 h-6 text-yellow-400 fill-yellow-400"></i>
                </div>
            </div>

            <div
                class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl p-6 border border-cyan-500/20 hover:border-cyan-500/40 transition-all duration-300 hover:scale-105">
                <div class="flex items-center justify-between mb-4">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center">
                        <i data-lucide="thumbs-up" class="w-6 h-6 text-white"></i>
                    </div>
                </div>
                <h3 class="text-gray-400 text-sm mb-1">Avaliações 5 Estrelas</h3>
                <p class="text-3xl font-bold text-white"><?php echo $avaliacoes_por_nota[5]; ?></p>
            </div>

            <div
                class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl p-6 border border-cyan-500/20 hover:border-cyan-500/40 transition-all duration-300 hover:scale-105">
                <div class="flex items-center justify-between mb-4">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
                        <i data-lucide="message-square" class="w-6 h-6 text-white"></i>
                    </div>
                </div>
                <h3 class="text-gray-400 text-sm mb-1">Com Comentários</h3>
                <p class="text-3xl font-bold text-white">
                    <?php echo count(array_filter($avaliacoes, fn($a) => !empty($a['COMENTARIO']))); ?>
                </p>
            </div>
        </div>

        <!-- FILTROS -->
        <div
            class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl p-6 border border-cyan-500/20 mb-6">
            <form method="GET" action="" class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1 relative">
                    <i data-lucide="search"
                        class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                    <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>"
                        placeholder="Buscar por endereço ou comentário..."
                        class="w-full pl-12 pr-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors" />
                </div>

                <select name="nota"
                    class="px-6 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white focus:outline-none focus:border-cyan-500/50 transition-colors cursor-pointer">
                    <option value="">Todas as Notas</option>
                    <option value="5" <?php echo $nota_filtro == '5' ? 'selected' : ''; ?>>⭐⭐⭐⭐⭐ (5)</option>
                    <option value="4" <?php echo $nota_filtro == '4' ? 'selected' : ''; ?>>⭐⭐⭐⭐ (4)</option>
                    <option value="3" <?php echo $nota_filtro == '3' ? 'selected' : ''; ?>>⭐⭐⭐ (3)</option>
                    <option value="2" <?php echo $nota_filtro == '2' ? 'selected' : ''; ?>>⭐⭐ (2)</option>
                    <option value="1" <?php echo $nota_filtro == '1' ? 'selected' : ''; ?>>⭐ (1)</option>
                </select>

                <button type="submit"
                    class="px-6 py-3 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl font-semibold transition-colors">
                    Filtrar
                </button>
            </form>
        </div>

        <!-- LISTA DE AVALIAÇÕES -->
        <div
            class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl border border-cyan-500/20 overflow-hidden">
            <div class="p-6 border-b border-cyan-500/20">
                <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                    <i data-lucide="star" class="w-6 h-6 text-yellow-400"></i>
                    Avaliações Recentes
                </h2>
                <p class="text-gray-400 mt-1"><?php echo count($avaliacoes); ?> avaliações encontradas</p>
            </div>

            <div class="p-6">
                <?php if (empty($avaliacoes)): ?>
                    <div class="text-center py-12">
                        <i data-lucide="message-square-off" class="w-16 h-16 text-gray-600 mx-auto mb-4"></i>
                        <p class="text-gray-400 text-lg">Nenhuma avaliação encontrada.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-4">
                        <?php foreach ($avaliacoes as $av): ?>
                            <div
                                class="bg-slate-900/50 border border-cyan-500/20 rounded-xl p-6 hover:border-cyan-500/40 transition-all">
                                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <div
                                                class="w-10 h-10 bg-gradient-to-br from-cyan-500/20 to-blue-500/20 rounded-full flex items-center justify-center border border-cyan-500/30">
                                                <i data-lucide="user" class="w-5 h-5 text-cyan-400"></i>
                                            </div>
                                            <div>
                                                <p class="text-white font-semibold">
                                                    <?php echo htmlspecialchars($av['usuario_nome']); ?>
                                                </p>
                                                <div class="flex items-center gap-2">
                                                    <p class="text-xs text-gray-400">
                                                        <?php echo date('d/m/Y H:i', strtotime($av['DATA_AVALIACAO'])); ?>
                                                    </p>
                                                    <?php
                                                    // Debug: verificar se campo existe e valor
                                                    $editado = isset($av['EDITADO']) ? $av['EDITADO'] : 0;
                                                    if ($editado == 1):
                                                        ?>
                                                        <span
                                                            class="text-xs text-yellow-400 font-semibold flex items-center gap-1 bg-yellow-500/10 px-2 py-1 rounded-md border border-yellow-500/30">
                                                            <i data-lucide="edit-3" class="w-3 h-3"></i>
                                                            Editado
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-1 mb-3">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i data-lucide="star"
                                                    class="w-5 h-5 <?php echo $i <= $av['NOTA'] ? 'text-yellow-400 fill-yellow-400' : 'text-gray-600'; ?>"></i>
                                            <?php endfor; ?>
                                            <span class="ml-2 text-white font-semibold"><?php echo $av['NOTA']; ?>/5</span>
                                        </div>

                                        <?php if (!empty($av['COMENTARIO'])): ?>
                                            <p class="text-gray-300 mb-3"><?php echo htmlspecialchars($av['COMENTARIO']); ?></p>
                                        <?php endif; ?>

                                        <div class="flex items-center gap-2 text-sm text-gray-400">
                                            <i data-lucide="map-pin" class="w-4 h-4 text-cyan-400"></i>
                                            <span>
                                                <?php echo htmlspecialchars($av['LOGRADOURO'] ?? 'Não informado'); ?> -
                                                <?php echo htmlspecialchars($av['bairro'] ?? ''); ?>,
                                                <?php echo htmlspecialchars($av['cidade'] ?? ''); ?> -
                                                <?php echo htmlspecialchars($av['UF'] ?? ''); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <?php if ($av['FK_ID_USUARIO'] == $usuario_id): ?>
                                        <div class="flex gap-2">
                                            <button onclick='editarAvaliacao(<?php echo json_encode($av); ?>)'
                                                class="p-2 bg-blue-500/20 hover:bg-blue-500/30 text-blue-400 rounded-lg border border-blue-500/30 hover:border-blue-500/50 transition-colors self-start">
                                                <i data-lucide="edit" class="w-4 h-4"></i>
                                            </button>
                                            <button onclick="confirmarExclusao(<?php echo $av['ID_AVALIACAO']; ?>)"
                                                class="p-2 bg-red-500/20 hover:bg-red-500/30 text-red-400 rounded-lg border border-red-500/30 hover:border-red-500/50 transition-colors self-start">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- MODAL CRIAR AVALIAÇÃO -->
    <div id="modalCriarAvaliacao"
        class="modal fixed inset-0 bg-black/70 backdrop-blur-sm items-center justify-center z-50 p-4">
        <div
            class="bg-gradient-to-br from-slate-800/95 to-slate-900/95 backdrop-blur-xl rounded-2xl border border-cyan-500/20 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-cyan-500/20 flex items-center justify-between sticky top-0 bg-slate-900/90">
                <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                    <i data-lucide="star" class="w-6 h-6 text-yellow-400"></i>
                    Nova Avaliação
                </h2>
                <button onclick="fecharModal('modalCriarAvaliacao')"
                    class="p-2 hover:bg-slate-700/50 rounded-lg transition-colors">
                    <i data-lucide="x" class="w-6 h-6 text-gray-400"></i>
                </button>
            </div>

            <form method="POST" action="" onsubmit="return validarFormulario()">
                <input type="hidden" name="action" value="criar_avaliacao">
                <input type="hidden" name="nota" id="nota_valor" value="0">

                <div class="p-6">
                    <div class="mb-6">
                        <label class="block text-gray-400 text-sm font-semibold mb-2">Ponto de Carregamento *</label>
                        <select name="ponto_id" id="ponto_id" required
                            class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white focus:outline-none focus:border-cyan-500/50 transition-colors cursor-pointer">
                            <option value="">Selecione um ponto</option>
                            <?php foreach ($pontos_disponiveis as $ponto): ?>
                                <option value="<?php echo $ponto['ID_PONTO']; ?>">
                                    #<?php echo $ponto['ID_PONTO']; ?> -
                                    <?php echo htmlspecialchars($ponto['LOGRADOURO']); ?> -
                                    <?php echo htmlspecialchars($ponto['cidade']); ?>,
                                    <?php echo htmlspecialchars($ponto['UF']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="block text-gray-400 text-sm font-semibold mb-3">Sua Avaliação *</label>
                        <div class="star-rating" id="star-rating">
                            <span class="star-icon inactive" data-rating="1">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                </svg>
                            </span>
                            <span class="star-icon inactive" data-rating="2">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                </svg>
                            </span>
                            <span class="star-icon inactive" data-rating="3">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                </svg>
                            </span>
                            <span class="star-icon inactive" data-rating="4">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                </svg>
                            </span>
                            <span class="star-icon inactive" data-rating="5">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                </svg>
                            </span>
                            <span id="nota_texto" class="ml-2 text-white font-semibold">Clique para avaliar</span>
                        </div>
                    </div>

                    <div class="mb-8">
                        <label class="block text-gray-400 text-sm font-semibold mb-2">Comentário (opcional)</label>
                        <textarea name="comentario" id="comentario" rows="4" maxlength="200"
                            placeholder="Compartilhe sua experiência..."
                            class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors resize-none"></textarea>
                        <p class="text-xs text-gray-500 mt-1">0/200 caracteres</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4">
                        <button type="submit"
                            class="flex-1 bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg shadow-yellow-500/30">
                            <i data-lucide="send" class="w-5 h-5 inline-block mr-2"></i>
                            Enviar Avaliação
                        </button>

                        <button type="button" onclick="fecharModal('modalCriarAvaliacao')"
                            class="flex-1 bg-slate-700/50 hover:bg-slate-700 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 border border-slate-600">
                            <i data-lucide="x" class="w-5 h-5 inline-block mr-2"></i>
                            Cancelar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDITAR AVALIAÇÃO -->
    <div id="modalEditarAvaliacao"
        class="modal fixed inset-0 bg-black/70 backdrop-blur-sm items-center justify-center z-50 p-4">
        <div
            class="bg-gradient-to-br from-slate-800/95 to-slate-900/95 backdrop-blur-xl rounded-2xl border border-cyan-500/20 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-cyan-500/20 flex items-center justify-between sticky top-0 bg-slate-900/90">
                <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                    <i data-lucide="edit" class="w-6 h-6 text-blue-400"></i>
                    Editar Avaliação
                </h2>
                <button onclick="fecharModal('modalEditarAvaliacao')"
                    class="p-2 hover:bg-slate-700/50 rounded-lg transition-colors">
                    <i data-lucide="x" class="w-6 h-6 text-gray-400"></i>
                </button>
            </div>

            <form method="POST" action="" onsubmit="return validarFormularioEdicao()">
                <input type="hidden" name="action" value="editar_avaliacao">
                <input type="hidden" name="avaliacao_id" id="avaliacao_id_editar">
                <input type="hidden" name="nota" id="nota_valor_editar" value="0">

                <div class="p-6">
                    <div class="mb-6">
                        <label class="block text-gray-400 text-sm font-semibold mb-2">Ponto de Carregamento</label>
                        <input type="text" id="ponto_nome_editar" readonly
                            class="w-full px-4 py-3 bg-slate-900/80 border border-cyan-500/20 rounded-xl text-gray-400 cursor-not-allowed">
                    </div>

                    <div class="mb-6">
                        <label class="block text-gray-400 text-sm font-semibold mb-3">Sua Avaliação *</label>
                        <div class="star-rating" id="star-rating-editar">
                            <span class="star-icon-editar inactive" data-rating="1">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                </svg>
                            </span>
                            <span class="star-icon-editar inactive" data-rating="2">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                </svg>
                            </span>
                            <span class="star-icon-editar inactive" data-rating="3">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                </svg>
                            </span>
                            <span class="star-icon-editar inactive" data-rating="4">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                </svg>
                            </span>
                            <span class="star-icon-editar inactive" data-rating="5">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                </svg>
                            </span>
                            <span id="nota_texto_editar" class="ml-2 text-white font-semibold">Clique para
                                avaliar</span>
                        </div>
                    </div>

                    <div class="mb-8">
                        <label class="block text-gray-400 text-sm font-semibold mb-2">Comentário (opcional)</label>
                        <textarea name="comentario" id="comentario_editar" rows="4" maxlength="200"
                            placeholder="Compartilhe sua experiência..."
                            class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors resize-none"></textarea>
                        <p class="text-xs text-gray-500 mt-1" id="contador_editar">0/200 caracteres</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4">
                        <button type="submit"
                            class="flex-1 bg-gradient-to-r from-blue-500 to-cyan-500 hover:from-blue-600 hover:to-cyan-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg shadow-blue-500/30">
                            <i data-lucide="save" class="w-5 h-5 inline-block mr-2"></i>
                            Salvar Alterações
                        </button>

                        <button type="button" onclick="fecharModal('modalEditarAvaliacao')"
                            class="flex-1 bg-slate-700/50 hover:bg-slate-700 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 border border-slate-600">
                            <i data-lucide="x" class="w-5 h-5 inline-block mr-2"></i>
                            Cancelar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- FORM DELETAR (OCULTO) -->
    <form id="formDeletar" method="POST" action="" style="display:none;">
        <input type="hidden" name="action" value="deletar_avaliacao">
        <input type="hidden" name="avaliacao_id" id="avaliacao_id_deletar">
    </form>

    <script>
        lucide.createIcons();

        let notaSelecionada = 0;
        let notaSelecionadaEdicao = 0;

        function abrirModal(id) {
            document.getElementById(id).classList.add('active');
            if (id === 'modalCriarAvaliacao') {
                resetarFormulario();
            }
            setTimeout(() => lucide.createIcons(), 100);
        }

        function fecharModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function resetarFormulario() {
            document.getElementById('ponto_id').value = '';
            document.getElementById('comentario').value = '';
            notaSelecionada = 0;
            document.getElementById('nota_valor').value = '0';
            document.getElementById('nota_texto').textContent = 'Clique para avaliar';
            document.getElementById('nota_texto').classList.remove('text-yellow-400');
            atualizarEstrelas();

            // Reset contador
            const counter = document.querySelector('#comentario + p');
            if (counter) counter.textContent = '0/200 caracteres';
        }

        function atualizarEstrelas() {
            const stars = document.querySelectorAll('.star-icon');
            stars.forEach((star, index) => {
                if (index < notaSelecionada) {
                    star.classList.remove('inactive');
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                    star.classList.add('inactive');
                }
            });
        }

        function atualizarEstrelasEdicao() {
            const stars = document.querySelectorAll('.star-icon-editar');
            stars.forEach((star, index) => {
                if (index < notaSelecionadaEdicao) {
                    star.classList.remove('inactive');
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                    star.classList.add('inactive');
                }
            });
        }

        function editarAvaliacao(avaliacao) {
            // Preencher o ID da avaliação
            document.getElementById('avaliacao_id_editar').value = avaliacao.ID_AVALIACAO;

            // Preencher nome do ponto (readonly)
            const nomePonto = `#${avaliacao.ID_PONTO} - ${avaliacao.LOGRADOURO} - ${avaliacao.cidade}, ${avaliacao.UF}`;
            document.getElementById('ponto_nome_editar').value = nomePonto;

            // Preencher comentário
            document.getElementById('comentario_editar').value = avaliacao.COMENTARIO || '';

            // Atualizar contador
            const contador = document.getElementById('contador_editar');
            const length = (avaliacao.COMENTARIO || '').length;
            contador.textContent = `${length}/200 caracteres`;

            // Preencher nota
            notaSelecionadaEdicao = parseInt(avaliacao.NOTA);
            document.getElementById('nota_valor_editar').value = notaSelecionadaEdicao;

            const textos = {
                1: '1/5 - Muito Ruim',
                2: '2/5 - Ruim',
                3: '3/5 - Regular',
                4: '4/5 - Bom',
                5: '5/5 - Excelente'
            };

            document.getElementById('nota_texto_editar').textContent = textos[notaSelecionadaEdicao];
            document.getElementById('nota_texto_editar').classList.add('text-yellow-400');

            atualizarEstrelasEdicao();
            abrirModal('modalEditarAvaliacao');
        }

        function validarFormulario() {
            if (notaSelecionada === 0) {
                alert('Por favor, selecione uma nota para a avaliação!');
                return false;
            }
            return true;
        }

        function validarFormularioEdicao() {
            if (notaSelecionadaEdicao === 0) {
                alert('Por favor, selecione uma nota para a avaliação!');
                return false;
            }
            return true;
        }

        function confirmarExclusao(id) {
            if (confirm('Tem certeza que deseja excluir esta avaliação?')) {
                document.getElementById('avaliacao_id_deletar').value = id;
                document.getElementById('formDeletar').submit();
            }
        }

        // Sistema de avaliação por estrelas (CRIAR)
        document.addEventListener('DOMContentLoaded', function () {
            const stars = document.querySelectorAll('.star-icon');
            const notaInput = document.getElementById('nota_valor');
            const notaTexto = document.getElementById('nota_texto');
            const starRating = document.getElementById('star-rating');

            stars.forEach(star => {
                star.addEventListener('mouseenter', function () {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.classList.remove('inactive');
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                            s.classList.add('inactive');
                        }
                    });
                });

                star.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    notaSelecionada = parseInt(this.getAttribute('data-rating'));
                    notaInput.value = notaSelecionada;

                    const textos = {
                        1: '1/5 - Muito Ruim',
                        2: '2/5 - Ruim',
                        3: '3/5 - Regular',
                        4: '4/5 - Bom',
                        5: '5/5 - Excelente'
                    };

                    notaTexto.textContent = textos[notaSelecionada];
                    notaTexto.classList.add('text-yellow-400');
                    atualizarEstrelas();
                });
            });

            if (starRating) {
                starRating.addEventListener('mouseleave', function () {
                    atualizarEstrelas();
                });
            }

            // Sistema de avaliação por estrelas (EDITAR)
            const starsEditar = document.querySelectorAll('.star-icon-editar');
            const notaInputEditar = document.getElementById('nota_valor_editar');
            const notaTextoEditar = document.getElementById('nota_texto_editar');
            const starRatingEditar = document.getElementById('star-rating-editar');

            starsEditar.forEach(star => {
                star.addEventListener('mouseenter', function () {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    starsEditar.forEach((s, index) => {
                        if (index < rating) {
                            s.classList.remove('inactive');
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                            s.classList.add('inactive');
                        }
                    });
                });

                star.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    notaSelecionadaEdicao = parseInt(this.getAttribute('data-rating'));
                    notaInputEditar.value = notaSelecionadaEdicao;

                    const textos = {
                        1: '1/5 - Muito Ruim',
                        2: '2/5 - Ruim',
                        3: '3/5 - Regular',
                        4: '4/5 - Bom',
                        5: '5/5 - Excelente'
                    };

                    notaTextoEditar.textContent = textos[notaSelecionadaEdicao];
                    notaTextoEditar.classList.add('text-yellow-400');
                    atualizarEstrelasEdicao();
                });
            });

            if (starRatingEditar) {
                starRatingEditar.addEventListener('mouseleave', function () {
                    atualizarEstrelasEdicao();
                });
            }

            // Contador de caracteres (CRIAR)
            const comentarioInput = document.getElementById('comentario');
            if (comentarioInput) {
                comentarioInput.addEventListener('input', function () {
                    const currentLength = this.value.length;
                    const counterElement = this.nextElementSibling;
                    if (counterElement) {
                        counterElement.textContent = `${currentLength}/200 caracteres`;
                        if (currentLength > 180) {
                            counterElement.classList.add('text-yellow-400');
                            counterElement.classList.remove('text-gray-500');
                        } else {
                            counterElement.classList.remove('text-yellow-400');
                            counterElement.classList.add('text-gray-500');
                        }
                    }
                });
            }

            // Contador de caracteres (EDITAR)
            const comentarioInputEditar = document.getElementById('comentario_editar');
            if (comentarioInputEditar) {
                comentarioInputEditar.addEventListener('input', function () {
                    const currentLength = this.value.length;
                    const counterElement = document.getElementById('contador_editar');
                    if (counterElement) {
                        counterElement.textContent = `${currentLength}/200 caracteres`;
                        if (currentLength > 180) {
                            counterElement.classList.add('text-yellow-400');
                            counterElement.classList.remove('text-gray-500');
                        } else {
                            counterElement.classList.remove('text-yellow-400');
                            counterElement.classList.add('text-gray-500');
                        }
                    }
                });
            }

            lucide.createIcons();
        });

        // Fechar modal ao clicar fora
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function (e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        window.addEventListener('load', function () {
            lucide.createIcons();
        });
    </script>
</body>

</html>
index