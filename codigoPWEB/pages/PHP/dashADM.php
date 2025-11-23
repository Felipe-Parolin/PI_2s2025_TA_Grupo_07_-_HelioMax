<?php
require_once 'protectadmin.php';

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

// Define o ID do admin logado
$admin_id = $_SESSION['usuario_id'];

// Processar ações do CRUD
$mensagem = '';
$tipo_mensagem = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // CRIAR/EDITAR PONTO
  if ($action === 'salvar_ponto') {
    $id_ponto_raw = $_POST['id_ponto'] ?? '0';
    $id_ponto = intval(trim($id_ponto_raw));

    // Sanitização e formatação dos dados
    $cep = preg_replace('/\D/', '', $_POST['cep_ponto']); // O CEP agora vem do Autocomplete/Geocoding
    $logradouro = trim($_POST['logradouro']);
    $numero = trim($_POST['numero']);
    $complemento = trim($_POST['complemento']);
    $bairro = trim($_POST['bairro']);
    $cidade = trim($_POST['cidade']);
    $uf = strtoupper(trim($_POST['uf']));
    $valor_kwh = str_replace(',', '.', $_POST['valor_kwh']);
    $status_ponto = $_POST['status_ponto'];
    $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    // Adiciona uma validação mínima para campos essenciais preenchidos via Geocoding
    if (empty($cep) || empty($logradouro) || empty($bairro) || empty($cidade) || empty($uf) || empty($valor_kwh) || empty($status_ponto)) {
      $mensagem = 'Erro: Por favor, use a Busca por Localização para preencher o endereço e todos os detalhes do ponto.';
      $tipo_mensagem = 'erro';
      // Interrompe o processamento se faltarem dados cruciais
    } else {

      // Formatar CEP para o padrão do banco de dados (ex: 13610-100)
      $cep_formatado = strlen($cep) === 8 ? substr($cep, 0, 5) . '-' . substr($cep, 5) : $cep;

      try {
        $pdo->beginTransaction();

        if ($id_ponto > 0) {
          // LÓGICA DE EDIÇÃO
          $stmt = $pdo->prepare("SELECT LOCALIZACAO FROM ponto_carregamento WHERE ID_PONTO = ? AND FK_ID_USUARIO_CADASTRO = ?");
          $stmt->execute([$id_ponto, $admin_id]);
          $resultado = $stmt->fetch();

          if (!$resultado) {
            throw new Exception("Ponto não encontrado ou você não tem permissão para editar!");
          }

          $cep_id = $resultado['LOCALIZACAO'];

          // Buscar IDs relacionados (necessário para atualizar a hierarquia de endereço)
          $stmt = $pdo->prepare("SELECT FK_BAIRRO FROM cep WHERE ID_CEP = ?");
          $stmt->execute([$cep_id]);
          $bairro_id = $stmt->fetch()['FK_BAIRRO'];

          $stmt = $pdo->prepare("SELECT FK_CIDADE FROM bairro WHERE ID_BAIRRO = ?");
          $stmt->execute([$bairro_id]);
          $cidade_id = $stmt->fetch()['FK_CIDADE'];

          $stmt = $pdo->prepare("SELECT FK_ESTADO FROM cidade WHERE ID_CIDADE = ?");
          $stmt->execute([$cidade_id]);
          $estado_id = $stmt->fetch()['FK_ESTADO'];

          // Atualizar estado
          $stmt = $pdo->prepare("UPDATE estado SET UF = ? WHERE ID_ESTADO = ?");
          $stmt->execute([$uf, $estado_id]);

          // Atualizar cidade
          $stmt = $pdo->prepare("UPDATE cidade SET NOME = ? WHERE ID_CIDADE = ?");
          $stmt->execute([$cidade, $cidade_id]);

          // Atualizar bairro
          $stmt = $pdo->prepare("UPDATE bairro SET NOME = ? WHERE ID_BAIRRO = ?");
          $stmt->execute([$bairro, $bairro_id]);

          // Atualizar CEP na tabela cep
          $stmt = $pdo->prepare("UPDATE cep SET LOGRADOURO = ?, CEP = ? WHERE ID_CEP = ?");
          $stmt->execute([$logradouro, $cep_formatado, $cep_id]);

          // Atualizar ponto COM número, CEP, complemento E COORDENADAS
          $stmt = $pdo->prepare("UPDATE ponto_carregamento SET CEP = ?, NUMERO = ?, COMPLEMENTO = ?, VALOR_KWH = ?, FK_STATUS_PONTO = ?, LATITUDE = ?, LONGITUDE = ? WHERE ID_PONTO = ?");
          $stmt->execute([$cep_formatado, $numero, $complemento, $valor_kwh, $status_ponto, $latitude, $longitude, $id_ponto]);

          $mensagem = 'Ponto atualizado com sucesso!';
        } else {
          // LÓGICA DE CRIAÇÃO

          // ----------------------------------------------------------------------
          // 1. VALIDAÇÃO DE DUPLICIDADE 
          // Checa se já existe um ponto com o mesmo CEP formatado e o mesmo Número.
          $stmt_check = $pdo->prepare("SELECT ID_PONTO FROM ponto_carregamento WHERE CEP = ? AND NUMERO = ?");
          $stmt_check->execute([$cep_formatado, $numero]);

          if ($stmt_check->fetch()) {
            throw new Exception("Já existe um ponto de carregamento cadastrado neste CEP e Número ($cep_formatado, $numero). Não é permitido cadastrar duplicatas no mesmo local.");
          }
          // ----------------------------------------------------------------------

          // 2. Continuação da Lógica de Inserção (Criação)
          $stmt = $pdo->prepare("SELECT ID_ESTADO FROM estado WHERE UF = ?");
          $stmt->execute([$uf]);
          $estado = $stmt->fetch();

          if ($estado) {
            $estado_id = $estado['ID_ESTADO'];
          } else {
            $stmt = $pdo->prepare("INSERT INTO estado (UF) VALUES (?)");
            $stmt->execute([$uf]);
            $estado_id = $pdo->lastInsertId();
          }

          $stmt = $pdo->prepare("SELECT ID_CIDADE FROM cidade WHERE NOME = ? AND FK_ESTADO = ?");
          $stmt->execute([$cidade, $estado_id]);
          $cidade_row = $stmt->fetch();

          if ($cidade_row) {
            $cidade_id = $cidade_row['ID_CIDADE'];
          } else {
            $stmt = $pdo->prepare("INSERT INTO cidade (NOME, FK_ESTADO) VALUES (?, ?)");
            $stmt->execute([$cidade, $estado_id]);
            $cidade_id = $pdo->lastInsertId();
          }

          $stmt = $pdo->prepare("SELECT ID_BAIRRO FROM bairro WHERE NOME = ? AND FK_CIDADE = ?");
          $stmt->execute([$bairro, $cidade_id]);
          $bairro_row = $stmt->fetch();

          if ($bairro_row) {
            $bairro_id = $bairro_row['ID_BAIRRO'];
          } else {
            $stmt = $pdo->prepare("INSERT INTO bairro (NOME, FK_CIDADE) VALUES (?, ?)");
            $stmt->execute([$bairro, $cidade_id]);
            $bairro_id = $pdo->lastInsertId();
          }

          // Verificar se o CEP já existe na tabela cep
          $stmt = $pdo->prepare("SELECT ID_CEP FROM cep WHERE CEP = ? AND LOGRADOURO = ? AND FK_BAIRRO = ?");
          $stmt->execute([$cep_formatado, $logradouro, $bairro_id]);
          $cep_existente = $stmt->fetch();

          if ($cep_existente) {
            $cep_id = $cep_existente['ID_CEP'];
          } else {
            $stmt = $pdo->prepare("INSERT INTO cep (CEP, LOGRADOURO, FK_BAIRRO) VALUES (?, ?, ?)");
            $stmt->execute([$cep_formatado, $logradouro, $bairro_id]);
            $cep_id = $pdo->lastInsertId();
          }

          // Inserir ponto COM CEP, número, complemento E COORDENADAS
          $stmt = $pdo->prepare("INSERT INTO ponto_carregamento (CEP, NUMERO, COMPLEMENTO, LOCALIZACAO, VALOR_KWH, FK_STATUS_PONTO, FK_ID_USUARIO_CADASTRO, LATITUDE, LONGITUDE) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
          $stmt->execute([$cep_formatado, $numero, $complemento, $cep_id, $valor_kwh, $status_ponto, $admin_id, $latitude, $longitude]);

          $mensagem = 'Ponto cadastrado com sucesso!';
        }

        $pdo->commit();
        $tipo_mensagem = 'sucesso';
      } catch (Exception $e) {
        $pdo->rollBack();
        $mensagem = 'Erro: ' . $e->getMessage();
        $tipo_mensagem = 'erro';
      }
    }
  }

  // DELETAR PONTO (permanece igual)
  if ($action === 'deletar_ponto') {
    $id_ponto = $_POST['id_ponto'];

    try {
      $pdo->beginTransaction();

      $stmt = $pdo->prepare("SELECT LOCALIZACAO, FK_ID_USUARIO_CADASTRO FROM ponto_carregamento WHERE ID_PONTO = ?");
      $stmt->execute([$id_ponto]);
      $ponto_info = $stmt->fetch();

      if (!$ponto_info || $ponto_info['FK_ID_USUARIO_CADASTRO'] != $admin_id) {
        throw new Exception("Ponto não encontrado ou você não tem permissão para excluir!");
      }

      $cep_id = $ponto_info['LOCALIZACAO'];

      $stmt = $pdo->prepare("DELETE FROM ponto_carregamento WHERE ID_PONTO = ? AND FK_ID_USUARIO_CADASTRO = ?");
      $stmt->execute([$id_ponto, $admin_id]);

      if ($cep_id) {
        $stmt_check_cep = $pdo->prepare("
          SELECT 1 FROM ponto_carregamento WHERE LOCALIZACAO = ?
          UNION
          SELECT 1 FROM usuario WHERE FK_ID_CEP = ?
        ");
        $stmt_check_cep->execute([$cep_id, $cep_id]);
        $cep_is_used = $stmt_check_cep->fetch();

        if (!$cep_is_used) {
          $stmt = $pdo->prepare("SELECT FK_BAIRRO FROM cep WHERE ID_CEP = ?");
          $stmt->execute([$cep_id]);
          $bairro_id = $stmt->fetchColumn();

          $stmt = $pdo->prepare("DELETE FROM cep WHERE ID_CEP = ?");
          $stmt->execute([$cep_id]);

          if ($bairro_id) {
            $stmt_check_bairro = $pdo->prepare("SELECT 1 FROM cep WHERE FK_BAIRRO = ?");
            $stmt_check_bairro->execute([$bairro_id]);
            $bairro_is_used = $stmt_check_bairro->fetch();

            if (!$bairro_is_used) {
              $stmt = $pdo->prepare("SELECT FK_CIDADE FROM bairro WHERE ID_BAIRRO = ?");
              $stmt->execute([$bairro_id]);
              $cidade_id = $stmt->fetchColumn();

              $stmt = $pdo->prepare("DELETE FROM bairro WHERE ID_BAIRRO = ?");
              $stmt->execute([$bairro_id]);

              if ($cidade_id) {
                $stmt_check_cidade = $pdo->prepare("SELECT 1 FROM bairro WHERE FK_CIDADE = ?");
                $stmt_check_cidade->execute([$cidade_id]);
                $cidade_is_used = $stmt_check_cidade->fetch();

                if (!$cidade_is_used) {
                  $stmt = $pdo->prepare("SELECT FK_ESTADO FROM cidade WHERE ID_CIDADE = ?");
                  $stmt->execute([$cidade_id]);
                  $estado_id = $stmt->fetchColumn();

                  $stmt = $pdo->prepare("DELETE FROM cidade WHERE ID_CIDADE = ?");
                  $stmt->execute([$cidade_id]);

                  if ($estado_id) {
                    $stmt_check_estado = $pdo->prepare("SELECT 1 FROM cidade WHERE FK_ESTADO = ?");
                    $stmt_check_estado->execute([$estado_id]);
                    $estado_is_used = $stmt_check_estado->fetch();

                    if (!$estado_is_used) {
                      $stmt = $pdo->prepare("DELETE FROM estado WHERE ID_ESTADO = ?");
                      $stmt->execute([$estado_id]);
                    }
                  }
                }
              }
            }
          }
        }
      }

      $pdo->commit();
      $mensagem = 'Ponto excluído com sucesso!';
      $tipo_mensagem = 'sucesso';

    } catch (Exception $e) {
      $pdo->rollBack();
      $mensagem = 'Erro ao excluir: ' . $e->getMessage();
      $tipo_mensagem = 'erro';
    }
  }

  // ATUALIZAR PERFIL (mantém a lógica de CEP/ViaCEP, pois não envolve Geocoding)
  if ($action === 'atualizar_perfil') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $cpf = preg_replace('/\D/', '', $_POST['cpf']);
    $cep_valor = preg_replace('/\D/', '', $_POST['cep_perfil']);
    $numero = trim($_POST['numero_residencia']);
    $complemento = trim($_POST['complemento']);
    $logradouro_perfil = trim($_POST['logradouro_perfil']);
    $bairro_perfil = trim($_POST['bairro_perfil']);
    $cidade_perfil = trim($_POST['cidade_perfil']);
    $uf_perfil = strtoupper(trim($_POST['uf_perfil']));

    try {
      $pdo->beginTransaction();

      $stmt = $pdo->prepare("SELECT ID_USER FROM usuario WHERE CPF = ? AND ID_USER != ?");
      $stmt->execute([$cpf, $_SESSION['usuario_id']]);
      $cpf_existente = $stmt->fetch();

      if ($cpf_existente) {
        throw new Exception("Este CPF já está cadastrado para outro usuário!");
      }

      $stmt = $pdo->prepare("SELECT ID_USER FROM usuario WHERE EMAIL = ? AND ID_USER != ?");
      $stmt->execute([$email, $_SESSION['usuario_id']]);
      $email_existente = $stmt->fetch();

      if ($email_existente) {
        throw new Exception("Este e-mail já está cadastrado para outro usuário!");
      }

      $cep_id = null;

      if ($logradouro_perfil && $bairro_perfil && $cidade_perfil && $uf_perfil) {
        $stmt = $pdo->prepare("SELECT ID_ESTADO FROM estado WHERE UF = ?");
        $stmt->execute([$uf_perfil]);
        $estado = $stmt->fetch();

        if ($estado) {
          $estado_id = $estado['ID_ESTADO'];
        } else {
          $stmt = $pdo->prepare("INSERT INTO estado (UF) VALUES (?)");
          $stmt->execute([$uf_perfil]);
          $estado_id = $pdo->lastInsertId();
        }

        $stmt = $pdo->prepare("SELECT ID_CIDADE FROM cidade WHERE NOME = ? AND FK_ESTADO = ?");
        $stmt->execute([$cidade_perfil, $estado_id]);
        $cidade_row = $stmt->fetch();

        if ($cidade_row) {
          $cidade_id = $cidade_row['ID_CIDADE'];
        } else {
          $stmt = $pdo->prepare("INSERT INTO cidade (NOME, FK_ESTADO) VALUES (?, ?)");
          $stmt->execute([$cidade_perfil, $estado_id]);
          $cidade_id = $pdo->lastInsertId();
        }

        $stmt = $pdo->prepare("SELECT ID_BAIRRO FROM bairro WHERE NOME = ? AND FK_CIDADE = ?");
        $stmt->execute([$bairro_perfil, $cidade_id]);
        $bairro_row = $stmt->fetch();

        if ($bairro_row) {
          $bairro_id = $bairro_row['ID_BAIRRO'];
        } else {
          $stmt = $pdo->prepare("INSERT INTO bairro (NOME, FK_CIDADE) VALUES (?, ?)");
          $stmt->execute([$bairro_perfil, $cidade_id]);
          $bairro_id = $pdo->lastInsertId();
        }

        // Formatar CEP
        $cep_formatado = strlen($cep_valor) === 8 ? substr($cep_valor, 0, 5) . '-' . substr($cep_valor, 5) : $cep_valor;

        $stmt = $pdo->prepare("SELECT ID_CEP FROM cep WHERE CEP = ? AND LOGRADOURO = ? AND FK_BAIRRO = ?");
        $stmt->execute([$cep_formatado, $logradouro_perfil, $bairro_id]);
        $cep_row = $stmt->fetch();

        if ($cep_row) {
          $cep_id = $cep_row['ID_CEP'];
        } else {
          $stmt = $pdo->prepare("INSERT INTO cep (CEP, LOGRADOURO, FK_BAIRRO) VALUES (?, ?, ?)");
          $stmt->execute([$cep_formatado, $logradouro_perfil, $bairro_id]);
          $cep_id = $pdo->lastInsertId();
        }
      }

      // Correção do erro "Unknown column 'CEP'"
      $stmt = $pdo->prepare("UPDATE usuario SET NOME = ?, EMAIL = ?, CPF = ?, NUMERO_RESIDENCIA = ?, COMPLEMENTO_ENDERECO = ?, FK_ID_CEP = ? WHERE ID_USER = ?");
      $stmt->execute([$nome, $email, $cpf, $numero, $complemento, $cep_id, $_SESSION['usuario_id']]);

      $_SESSION['usuario_nome'] = $nome;

      $pdo->commit();
      $mensagem = 'Perfil atualizado com sucesso!';
      $tipo_mensagem = 'sucesso';
    } catch (Exception $e) {
      $pdo->rollBack();
      $mensagem = 'Erro ao atualizar: ' . $e->getMessage();
      $tipo_mensagem = 'erro';
    }
  }

  // ALTERAR SENHA (permanece igual)
  if ($action === 'alterar_senha') {
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar = $_POST['confirmar_senha'];

    try {
      $stmt = $pdo->prepare("SELECT SENHA FROM usuario WHERE ID_USER = ?");
      $stmt->execute([$_SESSION['usuario_id']]);
      $senha_hash_bd = $stmt->fetch()['SENHA'];

      if (!password_verify($senha_atual, $senha_hash_bd)) {
        $mensagem = 'Senha atual incorreta!';
        $tipo_mensagem = 'erro';
      } elseif ($nova_senha !== $confirmar) {
        $mensagem = 'As senhas não conferem!';
        $tipo_mensagem = 'erro';
      } elseif (strlen($nova_senha) < 6) {
        $mensagem = 'A nova senha deve ter pelo menos 6 caracteres!';
        $tipo_mensagem = 'erro';
      } else {
        $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE usuario SET SENHA = ? WHERE ID_USER = ?");
        $stmt->execute([$nova_senha_hash, $_SESSION['usuario_id']]);

        $mensagem = 'Senha alterada com sucesso!';
        $tipo_mensagem = 'sucesso';
      }
    } catch (Exception $e) {
      $mensagem = 'Erro ao alterar senha: ' . $e->getMessage();
      $tipo_mensagem = 'erro';
    }
  }
}

// LOGOUT
if (isset($_GET['logout'])) {
  session_destroy();
  header('Location: ../HTML/landpage.html');
  exit;
}

// Buscar dados
$busca = $_GET['busca'] ?? '';
$status_filtro = $_GET['status'] ?? '';

// Estatísticas
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ponto_carregamento WHERE FK_ID_USUARIO_CADASTRO = ?");
$stmt->execute([$admin_id]);
$totalPontos = $stmt->fetch()['total'];

$totalUsuarios = $pdo->query("SELECT COUNT(*) as total FROM usuario")->fetch()['total'];

// Buscar pontos 
$sql = "SELECT pc.*, sp.DESCRICAO as status_desc, c.LOGRADOURO, b.NOME as bairro, ci.NOME as cidade, e.UF
        FROM ponto_carregamento pc
        LEFT JOIN status_ponto sp ON pc.FK_STATUS_PONTO = sp.ID_STATUS_PONTO
        LEFT JOIN cep c ON pc.LOCALIZACAO = c.ID_CEP
        LEFT JOIN bairro b ON c.FK_BAIRRO = b.ID_BAIRRO
        LEFT JOIN cidade ci ON b.FK_CIDADE = ci.ID_CIDADE
        LEFT JOIN estado e ON ci.FK_ESTADO = e.ID_ESTADO
        WHERE pc.FK_ID_USUARIO_CADASTRO = ?";

$params = [$admin_id];

if ($busca) {
  $sql .= " AND (c.LOGRADOURO LIKE ? OR b.NOME LIKE ? OR ci.NOME LIKE ?)";
  $params = array_merge($params, ["%$busca%", "%$busca%", "%$busca%"]);
}

if ($status_filtro) {
  $sql .= " AND sp.DESCRICAO = ?";
  $params[] = $status_filtro;
}

$sql .= " ORDER BY pc.ID_PONTO DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pontos = $stmt->fetchAll();

// Buscar Status
$status_lista = $pdo->query("SELECT * FROM status_ponto ORDER BY DESCRICAO")->fetchAll();

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT u.*, c.CEP as CEP_TABELA, c.LOGRADOURO, b.NOME as bairro, ci.NOME as cidade, e.UF 
                       FROM usuario u
                       LEFT JOIN cep c ON u.FK_ID_CEP = c.ID_CEP
                       LEFT JOIN bairro b ON c.FK_BAIRRO = b.ID_BAIRRO
                       LEFT JOIN cidade ci ON b.FK_CIDADE = ci.ID_CIDADE
                       LEFT JOIN estado e ON ci.FK_ESTADO = e.ID_ESTADO
                       WHERE u.ID_USER = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="../../images/icon.png">
  <title>Dashboard do Administrador</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyD8GxprFa1NCA_pfGzXQqC6Eiflx7BeEKY&libraries=places"></script>
  <style>
    .modal {
      display: none;
    }

    .modal.active {
      display: flex;
    }

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

    .cep-error {
      display: none;
      margin-top: 8px;
      color: #dc3545;
      font-size: 0.875rem;
    }

    .cep-error.show {
      display: block;
    }

    .geocode-success {
      display: none;
      margin-top: 8px;
      color: #10b981;
      font-size: 0.875rem;
    }

    .geocode-success.show {
      display: block;
    }
  </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">

  <header class="bg-slate-900/50 backdrop-blur-xl border-b border-cyan-500/20 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-lg flex items-center justify-center">
            <i data-lucide="zap" class="w-6 h-6 text-white"></i>
          </div>
          <div>
            <h1 class="text-xl font-bold text-white">HelioMax Admin</h1>
            <p class="text-xs text-cyan-400">Painel de Gerenciamento</p>
          </div>
        </div>

        <div class="flex items-center gap-4">
          <div class="hidden sm:flex items-center gap-2 px-4 py-2 bg-slate-800/50 rounded-lg border border-cyan-500/20">
            <i data-lucide="user" class="w-4 h-4 text-cyan-400"></i>
            <span class="text-sm text-white"><?php echo $_SESSION['usuario_nome']; ?></span>
          </div>

          <button onclick="abrirModal('modalPerfil')"
            class="p-2 bg-slate-800/50 hover:bg-slate-700/50 rounded-lg border border-cyan-500/20 hover:border-cyan-500/40 transition-all">
            <i data-lucide="settings" class="w-5 h-5 text-cyan-400"></i>
          </button>

          <a href="?logout=1"
            class="p-2 bg-red-500/20 hover:bg-red-500/30 rounded-lg border border-red-500/30 hover:border-red-500/50 transition-all">
            <i data-lucide="log-out" class="w-5 h-5 text-red-400"></i>
          </a>
        </div>
      </div>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

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

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
      <div
        class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl p-6 border border-cyan-500/20 hover:border-cyan-500/40 transition-all duration-300 hover:scale-105">
        <div class="flex items-center justify-between mb-4">
          <div
            class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center">
            <i data-lucide="map-pin" class="w-6 h-6 text-white"></i>
          </div>
        </div>
        <h3 class="text-gray-400 text-sm mb-1">Meus Pontos</h3>
        <p class="text-3xl font-bold text-white"><?php echo $totalPontos; ?></p>
      </div>

      <div
        class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl p-6 border border-cyan-500/20 hover:border-cyan-500/40 transition-all duration-300 hover:scale-105">
        <div class="flex items-center justify-between mb-4">
          <div
            class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
            <i data-lucide="users" class="w-6 h-6 text-white"></i>
          </div>
        </div>
        <h3 class="text-gray-400 text-sm mb-1">Usuários na Plataforma</h3>
        <p class="text-3xl font-bold text-white"><?php echo $totalUsuarios; ?></p>
      </div>
    </div>

    <div class="mb-8">
      <button onclick="abrirModal('modalCriarPonto')"
        class="w-full sm:w-auto bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold py-4 px-8 rounded-2xl flex items-center justify-center gap-3 shadow-lg shadow-cyan-500/30 hover:shadow-cyan-500/50 transition-all duration-300 hover:scale-105">
        <i data-lucide="plus" class="w-6 h-6"></i>
        <span class="text-lg">Cadastrar Novo Ponto de Recarga</span>
      </button>
    </div>

    <div
      class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl p-6 border border-cyan-500/20 mb-6">
      <form method="GET" action="" class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1 relative">
          <i data-lucide="search" class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5"></i>
          <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>"
            placeholder="Buscar por endereço..."
            class="w-full pl-12 pr-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors" />
        </div>
        <select name="status"
          class="px-6 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white focus:outline-none focus:border-cyan-500/50 transition-colors cursor-pointer">
          <option value="">Todos os Status</option>
          <?php foreach ($status_lista as $st): ?>
            <option value="<?php echo htmlspecialchars($st['DESCRICAO']); ?>" <?php echo $status_filtro === $st['DESCRICAO'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($st['DESCRICAO']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit"
          class="px-6 py-3 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl font-semibold transition-colors">
          Filtrar
        </button>
      </form>
    </div>

    <div
      class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl border border-cyan-500/20 overflow-hidden">
      <div class="p-6 border-b border-cyan-500/20">
        <h2 class="text-2xl font-bold text-white flex items-center gap-2">
          <i data-lucide="map-pin" class="w-6 h-6 text-cyan-400"></i>
          Pontos de Recarga Cadastrados
        </h2>
        <p class="text-gray-400 mt-1"><?php echo count($pontos); ?> pontos encontrados</p>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-slate-900/50">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider">ID</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-cyan-400 uppercase tracking-wider">Endereço</th>
              <th
                class="px-6 py-4 text-center text-xs font-semibold text-cyan-400 uppercase tracking-wider hidden sm:table-cell">
                Valor kWh</th>
              <th class="px-6 py-4 text-center text-xs font-semibold text-cyan-400 uppercase tracking-wider">Status</th>
              <th class="px-6 py-4 text-center text-xs font-semibold text-cyan-400 uppercase tracking-wider">Ações</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-cyan-500/10 text-white">
            <?php if (empty($pontos)): ?>
              <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-400">
                  Nenhum ponto de carregamento encontrado.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($pontos as $ponto): ?>
                <tr class="hover:bg-slate-800/30 transition-colors">
                  <td class="px-6 py-4 font-semibold">#<?php echo $ponto['ID_PONTO']; ?></td>
                  <td class="px-6 py-4">
                    <div class="flex items-start gap-3">
                      <div
                        class="w-10 h-10 bg-gradient-to-br from-cyan-500/20 to-blue-500/20 rounded-lg flex items-center justify-center flex-shrink-0 border border-cyan-500/30">
                        <i data-lucide="map-pin" class="w-5 h-5 text-cyan-400"></i>
                      </div>
                      <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($ponto['LOGRADOURO'] ?? 'Não informado'); ?>
                          <?php if (!empty($ponto['NUMERO'])): ?>,
                            <?php echo htmlspecialchars($ponto['NUMERO']); ?>
                          <?php endif; ?>
                        </p>
                        <p class="text-sm text-gray-400">
                          <?php echo htmlspecialchars($ponto['bairro'] ?? ''); ?> -
                          <?php echo htmlspecialchars($ponto['cidade'] ?? ''); ?> -
                          <?php echo htmlspecialchars($ponto['UF'] ?? ''); ?>
                          <?php if (!empty($ponto['CEP'])): ?> - CEP:
                            <?php echo htmlspecialchars($ponto['CEP']); ?>
                          <?php endif; ?>
                        </p>
                        <?php if (!empty($ponto['LATITUDE']) && !empty($ponto['LONGITUDE'])): ?>
                          <p class="text-xs text-cyan-400 mt-1">
                            <i data-lucide="navigation" class="w-3 h-3 inline-block"></i>
                            <?php echo number_format($ponto['LATITUDE'], 6); ?>,
                            <?php echo number_format($ponto['LONGITUDE'], 6); ?>
                          </p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-center hidden sm:table-cell">
                    <span
                      class="inline-flex items-center gap-1 px-3 py-1 bg-blue-500/20 text-blue-400 rounded-full text-sm font-semibold border border-blue-500/30">
                      <i data-lucide="zap" class="w-3 h-3"></i>
                      R$ <?php echo number_format($ponto['VALOR_KWH'] ?? 0, 2, ',', '.'); ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 text-center">
                    <?php
                    $status_class = 'bg-gray-500/20 text-gray-400 border-gray-500/30';
                    if ($ponto['status_desc'] === 'Ativo') {
                      $status_class = 'bg-green-500/20 text-green-400 border-green-500/30';
                    } elseif ($ponto['status_desc'] === 'Inativo') {
                      $status_class = 'bg-red-500/20 text-red-400 border-red-500/30';
                    } elseif ($ponto['status_desc'] === 'Em Manutenção') {
                      $status_class = 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30';
                    }
                    ?>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold border <?php echo $status_class; ?>">
                      <?php echo htmlspecialchars($ponto['status_desc'] ?? 'N/A'); ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 text-center">
                    <div class="flex items-center justify-center gap-2">
                      <button onclick='editarPonto(<?php echo json_encode($ponto); ?>)'
                        class="p-2 bg-blue-500/20 hover:bg-blue-500/30 text-blue-400 rounded-lg border border-blue-500/30 hover:border-blue-500/50 transition-colors">
                        <i data-lucide="edit" class="w-4 h-4"></i>
                      </button>
                      <button onclick="confirmarExclusao(<?php echo $ponto['ID_PONTO']; ?>)"
                        class="p-2 bg-red-500/20 hover:bg-red-500/30 text-red-400 rounded-lg border border-red-500/30 hover:border-red-500/50 transition-colors">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <div id="modalCriarPonto"
    class="modal fixed inset-0 bg-black/70 backdrop-blur-sm items-center justify-center z-50 p-4">
    <div
      class="bg-gradient-to-br from-slate-800/95 to-slate-900/95 backdrop-blur-xl rounded-2xl border border-cyan-500/20 max-w-4xl w-full max-h-[90vh] overflow-y-auto">
      <div class="p-6 border-b border-cyan-500/20 flex items-center justify-between sticky top-0 bg-slate-900/90">
        <h2 class="text-2xl font-bold text-white flex items-center gap-2">
          <i data-lucide="map-pin" class="w-6 h-6 text-cyan-400"></i>
          <span>Cadastrar Ponto de Recarga</span>
        </h2>
        <button onclick="fecharModal('modalCriarPonto')" class="p-2 hover:bg-slate-700/50 rounded-lg transition-colors">
          <i data-lucide="x" class="w-6 h-6 text-gray-400"></i>
        </button>
      </div>

      <form method="POST" action="">
        <input type="hidden" name="action" value="salvar_ponto">
        <input type="hidden" name="id_ponto" value="0">
        <input type="hidden" name="cep_ponto" id="cep_criar">
        <input type="hidden" name="logradouro" id="logradouro_criar">
        <input type="hidden" name="bairro" id="bairro_criar">
        <input type="hidden" name="cidade" id="cidade_criar">
        <input type="hidden" name="uf" id="uf_criar">

        <input type="hidden" name="latitude" id="latitude_criar">
        <input type="hidden" name="longitude" id="longitude_criar">

        <div class="p-6">
          <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
            <i data-lucide="map" class="w-5 h-5 text-cyan-400"></i>
            Busca de Endereço e Coordenadas *
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="md:col-span-2">
              <label class="block text-gray-400 text-sm font-semibold mb-2">Busca por Localização Exata (Obrigatório)
              </label>
              <input type="text" name="endereco_busca" id="endereco_busca_criar" required
                placeholder="Ex: Shopping Iguatemi, São Paulo, SP"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
              <div id="loadingGeocode_criar" class="loading-container">
                <div class="spinner"></div>
                <span>Buscando localização exata...</span>
              </div>
              <div id="geocodeError_criar" class="cep-error"></div>
              <div id="geocodeSuccess_criar" class="geocode-success"></div>
            </div>
          </div>

          <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
            <i data-lucide="pencil-line" class="w-5 h-5 text-cyan-400"></i>
            Detalhes Manuais do Endereço
          </h3>

          <div class="mb-6 p-4 bg-slate-700/20 border border-slate-600/50 rounded-xl">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="md:col-span-2">
                <label class="block text-gray-400 text-sm font-semibold mb-2">Endereço Encontrado
                  <span class="text-sm text-gray-500">(CEP, Logradouro, Bairro, Cidade, UF)</span></label>
                <div class="relative">
                  <p id="endereco_display_criar"
                    class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-gray-400 overflow-hidden text-ellipsis whitespace-nowrap">
                    Aguardando Busca por Localização...
                  </p>
                </div>
              </div>
              <div>
                <label class="block text-gray-400 text-sm font-semibold mb-2">Número</label>
                <input type="text" name="numero" id="numero_criar" placeholder="Ex: 2232"
                  oninput="this.value = this.value.replace(/\D/g, '')" inputmode="numeric"
                  class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
              </div>

              <div class="md:col-span-3">
                <label class="block text-gray-400 text-sm font-semibold mb-2">Complemento</label>
                <input type="text" name="complemento" id="complemento_criar" placeholder="Ex: Sala 301, Térreo"
                  class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
              </div>
            </div>
          </div>

          <div class="mb-6 p-4 bg-cyan-500/10 border border-cyan-500/30 rounded-xl">
            <h3 class="text-lg font-bold text-white mb-2 flex items-center gap-2">
              <i data-lucide="zap" class="w-5 h-5 text-cyan-400"></i>
              Detalhes do Ponto
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-gray-400 text-sm font-semibold mb-2">Valor por kWh (R$) *</label>
                <input type="text" name="valor_kwh" id="valor_kwh_criar" required placeholder="Ex: 0,95"
                  class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
              </div>

              <div>
                <label class="block text-gray-400 text-sm font-semibold mb-2">Status do Ponto *</label>
                <select name="status_ponto" required
                  class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white focus:outline-none focus:border-cyan-500/50 transition-colors cursor-pointer">
                  <option value="">Selecione um status</option>
                  <?php foreach ($status_lista as $st): ?>
                    <option value="<?php echo $st['ID_STATUS_PONTO']; ?>">
                      <?php echo htmlspecialchars($st['DESCRICAO']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="md:col-span-2">
                <p class="text-sm text-gray-400 mt-2">
                  Coordenadas (Latitude, Longitude):
                  <span id="coordenadas_display_criar" class="font-mono text-cyan-300">Aguardando busca...</span>
                </p>
              </div>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row gap-4">
            <button type="submit"
              class="flex-1 bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg shadow-cyan-500/30">
              <i data-lucide="save" class="w-5 h-5 inline-block mr-2"></i>
              Cadastrar Ponto
            </button>
            <button type="button" onclick="fecharModal('modalCriarPonto')"
              class="flex-1 bg-slate-700/50 hover:bg-slate-700 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 border border-slate-600">
              <i data-lucide="x" class="w-5 h-5 inline-block mr-2"></i>
              Cancelar
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div id="modalEditarPonto"
    class="modal fixed inset-0 bg-black/70 backdrop-blur-sm items-center justify-center z-50 p-4">
    <div
      class="bg-gradient-to-br from-slate-800/95 to-slate-900/95 backdrop-blur-xl rounded-2xl border border-cyan-500/20 max-w-4xl w-full max-h-[90vh] overflow-y-auto">
      <div class="p-6 border-b border-cyan-500/20 flex items-center justify-between sticky top-0 bg-slate-900/90">
        <h2 class="text-2xl font-bold text-white flex items-center gap-2">
          <i data-lucide="map-pin" class="w-6 h-6 text-cyan-400"></i>
          <span>Editar Ponto de Recarga</span>
        </h2>
        <button onclick="fecharModal('modalEditarPonto')"
          class="p-2 hover:bg-slate-700/50 rounded-lg transition-colors">
          <i data-lucide="x" class="w-6 h-6 text-gray-400"></i>
        </button>
      </div>

      <form method="POST" action="">
        <input type="hidden" name="action" value="salvar_ponto">
        <input type="hidden" name="id_ponto" id="id_ponto_editar" value="">
        <input type="hidden" name="cep_ponto" id="cep_editar">
        <input type="hidden" name="logradouro" id="logradouro_editar">
        <input type="hidden" name="bairro" id="bairro_editar">
        <input type="hidden" name="cidade" id="cidade_editar">
        <input type="hidden" name="uf" id="uf_editar">

        <input type="hidden" name="latitude" id="latitude_editar">
        <input type="hidden" name="longitude" id="longitude_editar">

        <div class="p-6">
          <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
            <i data-lucide="map" class="w-5 h-5 text-cyan-400"></i>
            Busca de Endereço e Coordenadas *
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="md:col-span-2">
              <label class="block text-gray-400 text-sm font-semibold mb-2">Busca por Localização Exata (Obrigatório)
              </label>
              <input type="text" name="endereco_busca" id="endereco_busca_editar"
                placeholder="Ex: Shopping Iguatemi, São Paulo, SP"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
              <div id="loadingGeocode_editar" class="loading-container">
                <div class="spinner"></div>
                <span>Buscando localização exata...</span>
              </div>
              <div id="geocodeError_editar" class="cep-error"></div>
              <div id="geocodeSuccess_editar" class="geocode-success"></div>
            </div>
          </div>

          <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
            <i data-lucide="pencil-line" class="w-5 h-5 text-cyan-400"></i>
            Detalhes Manuais do Endereço
          </h3>

          <div class="mb-6 p-4 bg-slate-700/20 border border-slate-600/50 rounded-xl">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="md:col-span-2">
                <label class="block text-gray-400 text-sm font-semibold mb-2">Endereço Encontrado
                  <span class="text-sm text-gray-500">(CEP, Logradouro, Bairro, Cidade, UF)</span></label>
                <div class="relative">
                  <p id="endereco_display_editar"
                    class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-gray-400 overflow-hidden text-ellipsis whitespace-nowrap">
                    Aguardando Busca por Localização...
                  </p>
                </div>
              </div>
              <div>
                <label class="block text-gray-400 text-sm font-semibold mb-2">Número</label>
                <input type="text" name="numero" id="numero_editar" placeholder="Ex: 2232"
                  oninput="this.value = this.value.replace(/\D/g, '')" inputmode="numeric"
                  class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
              </div>

              <div class="md:col-span-3">
                <label class="block text-gray-400 text-sm font-semibold mb-2">Complemento</label>
                <input type="text" name="complemento" id="complemento_editar" placeholder="Ex: Sala 301, Térreo"
                  class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
              </div>
            </div>
          </div>

          <div class="mb-6 p-4 bg-cyan-500/10 border border-cyan-500/30 rounded-xl">
            <h3 class="text-lg font-bold text-white mb-2 flex items-center gap-2">
              <i data-lucide="zap" class="w-5 h-5 text-cyan-400"></i>
              Detalhes do Ponto
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-gray-400 text-sm font-semibold mb-2">Valor por kWh (R$) *</label>
                <input type="text" name="valor_kwh" id="valor_kwh_editar" required placeholder="Ex: 0,95"
                  class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
              </div>

              <div>
                <label class="block text-gray-400 text-sm font-semibold mb-2">Status do Ponto *</label>
                <select name="status_ponto" id="status_ponto_editar" required
                  class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white focus:outline-none focus:border-cyan-500/50 transition-colors cursor-pointer">
                  <option value="">Selecione um status</option>
                  <?php foreach ($status_lista as $st): ?>
                    <option value="<?php echo $st['ID_STATUS_PONTO']; ?>">
                      <?php echo htmlspecialchars($st['DESCRICAO']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="md:col-span-2">
                <p class="text-sm text-gray-400 mt-2">
                  Coordenadas (Latitude, Longitude):
                  <span id="coordenadas_display_editar" class="font-mono text-cyan-300">Não cadastradas</span>
                </p>
              </div>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row gap-4">
            <button type="submit"
              class="flex-1 bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg shadow-cyan-500/30">
              <i data-lucide="save" class="w-5 h-5 inline-block mr-2"></i>
              Atualizar Ponto
            </button>

            <button type="button" onclick="fecharModal('modalEditarPonto')"
              class="flex-1 bg-slate-700/50 hover:bg-slate-700 text-white font-bold py-3 px-6 rounded-xl transition-colors">
              <i data-lucide="x" class="w-5 h-5 inline-block mr-2"></i>
              Cancelar
            </button>
          </div>
      </form>
    </div>
  </div>
  </div>

  <div id="modalConfirmarExclusao"
    class="modal fixed inset-0 bg-black/70 backdrop-blur-sm items-center justify-center z-50 p-4">
    <div
      class="bg-gradient-to-br from-slate-800/95 to-slate-900/95 backdrop-blur-xl rounded-2xl border border-red-500/20 max-w-lg w-full">
      <div class="p-6">
        <div class="text-center">
          <i data-lucide="alert-triangle" class="w-12 h-12 text-red-500 mx-auto mb-4"></i>
          <h3 class="text-xl font-bold text-white mb-2">Confirmar Exclusão</h3>
          <p class="text-gray-400">Tem certeza que deseja excluir este ponto de carregamento? Esta ação é irreversível.
          </p>
        </div>

        <form method="POST" id="formDeletar" action="" class="mt-6 flex gap-4">
          <input type="hidden" name="action" value="deletar_ponto">
          <input type="hidden" name="id_ponto" id="id_ponto_deletar">

          <button type="button" onclick="fecharModal('modalConfirmarExclusao')"
            class="flex-1 bg-slate-700/50 hover:bg-slate-700 text-white font-bold py-3 px-6 rounded-xl transition-colors border border-slate-600">
            <i data-lucide="x" class="w-5 h-5 inline-block mr-2"></i>
            Cancelar
          </button>
          <button type="submit"
            class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-xl transition-colors">
            <i data-lucide="trash-2" class="w-5 h-5 inline-block mr-2"></i>
            Excluir Ponto
          </button>
        </form>
      </div>
    </div>
  </div>

  <div id="modalPerfil" class="modal fixed inset-0 bg-black/70 backdrop-blur-sm items-center justify-center z-50 p-4">
    <div
      class="bg-gradient-to-br from-slate-800/95 to-slate-900/95 backdrop-blur-xl rounded-2xl border border-cyan-500/20 max-w-3xl w-full max-h-[90vh] overflow-y-auto">
      <div class="p-6 border-b border-cyan-500/20 flex items-center justify-between sticky top-0 bg-slate-900/90">
        <div class="flex items-center gap-4">
          <div
            class="w-16 h-16 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-2xl flex items-center justify-center">
            <i data-lucide="user" class="w-8 h-8 text-white"></i>
          </div>
          <div>
            <h2 class="text-2xl font-bold text-white"><?php echo htmlspecialchars($usuario['NOME']); ?></h2>
            <p class="text-gray-400"><?php echo htmlspecialchars($usuario['EMAIL']); ?></p>
          </div>
        </div>
        <button onclick="fecharModal('modalPerfil')" class="p-2 hover:bg-slate-700/50 rounded-lg transition-colors">
          <i data-lucide="x" class="w-6 h-6 text-gray-400"></i>
        </button>
      </div>

      <div class="border-b border-cyan-500/20 px-6">
        <div class="flex gap-4">
          <button onclick="mudarTab('dadosPessoais')" id="tabDadosPessoais"
            class="px-4 py-3 text-white font-semibold border-b-2 border-cyan-500 transition-colors">
            Dados Pessoais
          </button>
          <button onclick="mudarTab('alterarSenha')" id="tabAlterarSenha"
            class="px-4 py-3 text-gray-400 font-semibold border-b-2 border-transparent hover:border-cyan-500/50 transition-colors">
            Alterar Senha
          </button>
        </div>
      </div>

      <div id="contentDadosPessoais" class="p-6">
        <form method="POST" action="">
          <input type="hidden" name="action" value="atualizar_perfil">

          <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
            <i data-lucide="info" class="w-5 h-5 text-cyan-400"></i>
            Informações Pessoais
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Nome Completo *</label>
              <input type="text" name="nome" value="<?php echo htmlspecialchars($usuario['NOME']); ?>" required
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>
            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Email *</label>
              <input type="email" name="email" value="<?php echo htmlspecialchars($usuario['EMAIL']); ?>" required
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>
            <div class="md:col-span-2">
              <label class="block text-gray-400 text-sm font-semibold mb-2">CPF *</label>
              <div class="relative">
                <input type="text" value="<?php echo htmlspecialchars($usuario['CPF']); ?>" readonly disabled
                  maxlength="14"
                  class="w-full px-4 py-3 bg-slate-900/30 border border-cyan-500/10 rounded-xl text-gray-500 cursor-not-allowed focus:outline-none">
                <input type="hidden" name="cpf" value="<?php echo htmlspecialchars($usuario['CPF']); ?>">
                <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                  <i data-lucide="lock" class="w-4 h-4 text-gray-600"></i>
                </div>
              </div>
              <p class="text-xs text-gray-500 mt-1">Não pode ser alterado por segurança</p>
            </div>
          </div>

          <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
            <i data-lucide="home" class="w-5 h-5 text-cyan-400"></i>
            Endereço Residencial
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="md:col-span-2">
              <label class="block text-gray-400 text-sm font-semibold mb-2">CEP *</label>
              <input type="text" name="cep_perfil" id="cep_perfil"
                value="<?php echo htmlspecialchars(preg_replace('/\D/', '', $usuario['CEP_TABELA'] ?? '')); ?>" required
                placeholder="Ex: 13610-100" maxlength="9"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
              <div id="loadingCep_perfil" class="loading-container">
                <div class="spinner"></div>
                <span>Buscando endereço...</span>
              </div>
              <div id="cepError_perfil" class="cep-error"></div>
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">UF *</label>
              <div class="relative">
                <input type="text" name="uf_perfil" id="uf_perfil"
                  value="<?php echo htmlspecialchars($usuario['UF'] ?? ''); ?>" required placeholder="Ex: SP"
                  maxlength="2" readonly
                  class="w-full px-4 py-3 bg-slate-900/30 border border-cyan-500/10 rounded-xl text-gray-500 cursor-not-allowed focus:outline-none uppercase">
                <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                  <i data-lucide="lock" class="w-4 h-4 text-gray-600"></i>
                </div>
              </div>
            </div>

            <div class="md:col-span-2">
              <label class="block text-gray-400 text-sm font-semibold mb-2">Logradouro *</label>
              <div class="relative">
                <input type="text" name="logradouro_perfil" id="logradouro_perfil"
                  value="<?php echo htmlspecialchars($usuario['LOGRADOURO'] ?? ''); ?>" required
                  placeholder="Ex: Av. Brigadeiro Faria Lima" readonly
                  class="w-full px-4 py-3 bg-slate-900/30 border border-cyan-500/10 rounded-xl text-gray-500 cursor-not-allowed focus:outline-none">
                <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                  <i data-lucide="lock" class="w-4 h-4 text-gray-600"></i>
                </div>
              </div>
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Número</label>
              <input type="text" name="numero_residencia" id="numero_residencia"
                value="<?php echo htmlspecialchars($usuario['NUMERO_RESIDENCIA']); ?>" placeholder="Ex: 2232"
                oninput="this.value = this.value.replace(/\D/g, '')" inputmode="numeric"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Bairro *</label>
              <div class="relative">
                <input type="text" name="bairro_perfil" id="bairro_perfil"
                  value="<?php echo htmlspecialchars($usuario['bairro'] ?? ''); ?>" required placeholder="Ex: Pinheiros"
                  readonly
                  class="w-full px-4 py-3 bg-slate-900/30 border border-cyan-500/10 rounded-xl text-gray-500 cursor-not-allowed focus:outline-none">
                <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                  <i data-lucide="lock" class="w-4 h-4 text-gray-600"></i>
                </div>
              </div>
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Cidade *</label>
              <div class="relative">
                <input type="text" name="cidade_perfil" id="cidade_perfil"
                  value="<?php echo htmlspecialchars($usuario['cidade'] ?? ''); ?>" required placeholder="Ex: São Paulo"
                  readonly
                  class="w-full px-4 py-3 bg-slate-900/30 border border-cyan-500/10 rounded-xl text-gray-500 cursor-not-allowed focus:outline-none">
                <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                  <i data-lucide="lock" class="w-4 h-4 text-gray-600"></i>
                </div>
              </div>
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Complemento</label>
              <input type="text" name="complemento" id="complemento_perfil"
                value="<?php echo htmlspecialchars($usuario['COMPLEMENTO_ENDERECO']); ?>" placeholder="Ex: Sala 301"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>
          </div>

          <button type="submit"
            class="w-full bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 hover:scale-[1.01] shadow-lg shadow-cyan-500/30">
            <i data-lucide="save" class="w-5 h-5 inline-block mr-2"></i>
            Salvar Alterações
          </button>
        </form>
      </div>

      <div id="contentAlterarSenha" class="p-6 hidden">
        <form method="POST" action="">
          <input type="hidden" name="action" value="alterar_senha">
          <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
            <i data-lucide="lock" class="w-5 h-5 text-cyan-400"></i>
            Alteração de Senha
          </h3>
          <div class="space-y-4 mb-6">
            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Senha Atual *</label>
              <input type="password" name="senha_atual" required placeholder="Digite sua senha atual"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>
            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Nova Senha *</label>
              <input type="password" name="nova_senha" required placeholder="Mínimo 6 caracteres" minlength="6"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>
            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Confirmar Nova Senha *</label>
              <input type="password" name="confirmar_senha" required placeholder="Confirme a nova senha"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>
          </div>
          <button type="submit"
            class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 hover:scale-[1.01] shadow-lg shadow-red-500/30">
            <i data-lucide="key" class="w-5 h-5 inline-block mr-2"></i>
            Alterar Senha
          </button>
        </form>
      </div>
    </div>
  </div>


  <script>
    function abrirModal(id) {
      document.getElementById(id).classList.add('active');
    }

    function fecharModal(id) {
      document.getElementById(id).classList.remove('active');
      document.querySelectorAll('.cep-error').forEach(el => el.classList.remove('show'));
      document.querySelectorAll('.geocode-success').forEach(el => el.classList.remove('show'));
    }

    let geocoder;
    let autocompleteCriar;
    let autocompleteEditar;

    window.initMap = function () {
      if (typeof google === 'undefined' || !google.maps || !google.maps.Geocoder) {
        console.error("Google Maps API não carregou corretamente.");
        return;
      }
      geocoder = new google.maps.Geocoder();

      const inputCriar = document.getElementById('endereco_busca_criar');
      autocompleteCriar = new google.maps.places.Autocomplete(inputCriar, {
        types: ['geocode', 'establishment'],
        componentRestrictions: { country: 'br' }
      });
      autocompleteCriar.addListener('place_changed', () => handlePlaceSelect(autocompleteCriar, 'criar'));

      const inputEditar = document.getElementById('endereco_busca_editar');
      autocompleteEditar = new google.maps.places.Autocomplete(inputEditar, {
        types: ['geocode', 'establishment'],
        componentRestrictions: { country: 'br' }
      });
      autocompleteEditar.addListener('place_changed', () => handlePlaceSelect(autocompleteEditar, 'editar'));
    };

    // ===========================================
    // LÓGICA DE AUTOCOMPLETE E GEOCODING (PARA PONTOS)
    // ===========================================

    function handlePlaceSelect(autocomplete, contexto) {
      const place = autocomplete.getPlace();
      const prefixo = contexto === 'perfil' ? '_perfil' : `_${contexto}`;

      document.getElementById(`loadingGeocode_${contexto}`).classList.remove('show');
      document.getElementById(`geocodeError_${contexto}`).classList.remove('show');
      document.getElementById(`geocodeSuccess_${contexto}`).classList.remove('show');

      if (!place.geometry || !place.address_components) {
        mostrarErroGeocode(contexto, 'Nenhum detalhe de endereço encontrado. Digite novamente.');
        // Limpar campos ocultos
        limparCamposEndereco(contexto, true);
        return;
      }

      // 1. Capturar Coordenadas
      const lat = place.geometry.location.lat();
      const lng = place.geometry.location.lng();

      document.getElementById(`latitude${prefixo}`).value = lat;
      document.getElementById(`longitude${prefixo}`).value = lng;
      document.getElementById(`coordenadas_display${prefixo}`).textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;

      // 2. Preencher Campos de Endereço (usando components)
      preencherCamposPorPlace(place, contexto);
      mostrarSucessoGeocode(contexto, 'Localização e coordenadas encontradas e preenchidas!');
    }

    function preencherCamposPorPlace(place, contexto) {
      let street = '';
      let number = '';
      let neighborhood = '';
      let city = '';
      let state = '';
      let postalCode = '';
      let hasFullAddress = true;

      place.address_components.forEach(component => {
        const types = component.types;
        if (types.includes('route')) {
          street = component.long_name;
        } else if (types.includes('street_number')) {
          number = component.long_name;
        } else if (types.includes('sublocality_level_1') || types.includes('sublocality') || types.includes('neighborhood')) {
          neighborhood = component.long_name;
        } else if (types.includes('locality') || types.includes('administrative_area_level_2')) {
          if (!city) {
            city = component.long_name;
          }
        } else if (types.includes('administrative_area_level_1')) {
          state = component.short_name;
        } else if (types.includes('postal_code')) {
          postalCode = component.long_name;
        }
      });

      // O sufixo é diferente para perfil (modalPerfil) e pontos (modalCriarPonto/modalEditarPonto)
      const prefixo = contexto === 'perfil' ? '_perfil' : `_${contexto}`;

      // Os campos de endereço dos modais de Ponto agora são HIDDEN INPUTS
      const cepEl = document.getElementById(`cep${prefixo}`);
      const logradouroEl = document.getElementById(`logradouro${prefixo}`);
      const bairroEl = document.getElementById(`bairro${prefixo}`);
      const cidadeEl = document.getElementById(`cidade${prefixo}`);
      const ufEl = document.getElementById(`uf${prefixo}`);

      // Campos visíveis
      const numeroEl = document.getElementById(`numero${prefixo}`);
      const displayEl = document.getElementById(`endereco_display${prefixo}`); // Novo elemento para exibir o endereço

      // Validação de endereço
      if (!street || !neighborhood || !city || !state || !postalCode) {
        hasFullAddress = false;
      }

      // 1. Preenchimento dos campos HIDDEN (ou normais para o Perfil)
      if (cepEl) cepEl.value = postalCode ? postalCode.replace(/\D/g, '') : '';
      if (logradouroEl) logradouroEl.value = street || '';
      if (bairroEl) bairroEl.value = neighborhood || '';
      if (cidadeEl) cidadeEl.value = city || '';
      if (ufEl) ufEl.value = state || '';

      // 2. Preenchimento dos campos VISÍVEIS
      // O número é preenchido automaticamente, mas pode ser editado
      if (numeroEl && number) {
        numeroEl.value = number;
      }

      // 3. Atualizar o display visual
      if (displayEl) {
        let displayAddress = [
          postalCode ? `CEP: ${postalCode}` : '',
          street,
          neighborhood,
          city,
          state
        ].filter(n => n && n.trim() !== '').join(', ');

        displayEl.textContent = displayAddress || 'Endereço encontrado, mas alguns detalhes estão faltando. Ajuste o número e complemento.';
      }

      // 4. Se algum campo principal (incluindo CEP) estiver faltando, mostra erro
      if (contexto !== 'perfil' && !hasFullAddress) {
        mostrarErroGeocode(contexto, 'O endereço retornado está incompleto (falta CEP, Logradouro, Bairro ou Cidade/UF). Tente refinar a busca.');
      }
    }

    function mostrarErroGeocode(contexto, mensagem) {
      document.getElementById(`geocodeError_${contexto}`).textContent = "⚠ " + mensagem;
      document.getElementById(`geocodeError_${contexto}`).classList.add('show');
      document.getElementById(`geocodeSuccess_${contexto}`).classList.remove('show');
    }

    function mostrarSucessoGeocode(contexto, mensagem) {
      document.getElementById(`geocodeSuccess_${contexto}`).textContent = "✓ " + mensagem;
      document.getElementById(`geocodeSuccess_${contexto}`).classList.add('show');
      document.getElementById(`geocodeError_${contexto}`).classList.remove('show');
      setTimeout(() => {
        document.getElementById(`geocodeSuccess_${contexto}`).classList.remove('show');
      }, 5000);
    }

    // ===========================================
    // LÓGICA DE BUSCA DE CEP (ViaCEP) - APENAS PARA O PERFIL
    // ===========================================
    // Mantém a busca por CEP para o Perfil, pois o perfil não usa Geocoding/Coordenadas

    async function buscarCep(cep, contexto) {
      // Esta função só é relevante para o Perfil
      if (contexto !== 'perfil') return;

      const loadingEl = document.getElementById(`loadingCep_${contexto}`);
      loadingEl.classList.add('show');

      document.getElementById(`cepError_${contexto}`).classList.remove('show');

      // 1. Limpar campos de endereço preenchidos
      limparCamposEndereco(contexto);


      try {
        const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`, { signal: AbortSignal.timeout(5000) });
        const data = await response.json();

        loadingEl.classList.remove('show');

        if (data.erro) {
          mostrarErroCep(contexto, "CEP não encontrado. Preencha manualmente.");
        } else {
          const { logradouro, bairro, localidade, uf } = data;

          const prefixo = '_perfil';

          // Preenche os campos de endereço do Perfil
          const logradouroEl = document.getElementById(`logradouro${prefixo}`);
          const bairroEl = document.getElementById(`bairro${prefixo}`);
          const cidadeEl = document.getElementById(`cidade${prefixo}`);
          const ufEl = document.getElementById(`uf${prefixo}`);

          if (logradouroEl) logradouroEl.value = logradouro || '';
          if (bairroEl) bairroEl.value = bairro || '';
          if (cidadeEl) cidadeEl.value = localidade || '';
          if (ufEl) ufEl.value = uf || '';

          // O perfil não tem o "display" de endereço (era só para pontos)

          if (!logradouro || !bairro) {
            mostrarAvisoCep(contexto, "Alguns dados não foram encontrados. Complete manualmente.");
          }
        }
      } catch (error) {
        loadingEl.classList.remove('show');
        console.error("Erro ao buscar CEP:", error);
        if (error.name === 'AbortError') {
          mostrarErroCep(contexto, "Tempo esgotado. Tente novamente ou preencha manualmente.");
        } else {
          mostrarErroCep(contexto, "Não foi possível buscar o CEP. Preencha manualmente.");
        }
      }
    }

    function mostrarErroCep(contexto, mensagem) {
      const errorEl = document.getElementById(`cepError_${contexto}`);
      errorEl.textContent = "⚠ " + mensagem;
      errorEl.style.color = "#dc3545";
      errorEl.classList.add('show');
    }

    function mostrarAvisoCep(contexto, mensagem) {
      const errorEl = document.getElementById(`cepError_${contexto}`);
      errorEl.textContent = "ℹ️ " + mensagem;
      errorEl.style.color = "#ffc107";
      errorEl.classList.add('show');
      setTimeout(() => {
        errorEl.classList.remove('show');
        errorEl.style.color = "#dc3545";
      }, 5000);
    }

    function limparCamposEndereco(contexto, excetoNumeroComplemento = false) {
      const suffix = contexto === 'perfil' ? '_perfil' : `_${contexto}`;

      // Campos estruturais
      document.getElementById(`logradouro${suffix}`).value = "";
      document.getElementById(`bairro${suffix}`).value = "";
      document.getElementById(`cidade${suffix}`).value = "";
      document.getElementById(`uf${suffix}`).value = "";

      // Display Visual (apenas para pontos)
      const displayEl = document.getElementById(`endereco_display${suffix}`);
      if (displayEl) {
        displayEl.textContent = "Aguardando Busca por Localização...";
      }

      // Limpa CEP nos pontos, mas mantém no Perfil para o usuário digitar
      const cepEl = document.getElementById(`cep${suffix}`);
      if (cepEl && contexto !== 'perfil') {
        cepEl.value = "";
      }

      // Campos opcionais (limpar se for perfil ou se for forçado)
      if (!excetoNumeroComplemento) {
        if (document.getElementById(`numero${suffix}`)) {
          document.getElementById(`numero${suffix}`).value = "";
        }
        if (document.getElementById(`complemento${suffix}`)) {
          document.getElementById(`complemento${suffix}`).value = "";
        }
      }
    }

    function formatarCEP(input) {
      let value = input.value.replace(/\D/g, '');
      if (value.length > 5) {
        value = value.substring(0, 5) + '-' + value.substring(5, 8);
      }
      input.value = value;
    }

    // Event listeners para CEP (APENAS PERFIL)
    document.getElementById('cep_perfil').addEventListener('input', function (e) {
      formatarCEP(e.target);
      const cepLimpo = e.target.value.replace(/\D/g, '');
      if (cepLimpo.length === 8) {
        buscarCep(cepLimpo, 'perfil');
      } else if (cepLimpo.length < 8) {
        // Limpar campos estruturais se o CEP for incompleto
        limparCamposEndereco('perfil');
        document.getElementById(`cepError_perfil`).classList.remove('show');
      }
    });

    // ===========================================
    // LÓGICA DE MODAIS E AÇÕES DA TABELA
    // ===========================================

    document.addEventListener('DOMContentLoaded', () => {
      // Inicializar Lucide Icons
      lucide.createIcons();

      if (typeof google !== 'undefined' && google.maps) {
        initMap();
      } else {
        console.error("Google Maps API não está disponível ao carregar o DOM.");
      }

      mudarTab('dadosPessoais');
      // Força o formato do CEP do usuário ao carregar
      const cepPerfilEl = document.getElementById('cep_perfil');
      if (cepPerfilEl && cepPerfilEl.value) {
        formatarCEP(cepPerfilEl);
      }
    });

    function editarPonto(ponto) {
      document.getElementById('id_ponto_editar').value = ponto.ID_PONTO;

      // O CEP agora é hidden (preenchemos o input hidden com o valor do banco)
      document.getElementById('cep_editar').value = ponto.CEP ? ponto.CEP.replace('-', '') : '';

      document.getElementById('numero_editar').value = ponto.NUMERO || '';
      document.getElementById('complemento_editar').value = ponto.COMPLEMENTO || '';

      // Campos HIDDEN: preenchemos para o POST
      document.getElementById('logradouro_editar').value = ponto.LOGRADOURO || '';
      document.getElementById('bairro_editar').value = ponto.bairro || '';
      document.getElementById('cidade_editar').value = ponto.cidade || '';
      document.getElementById('uf_editar').value = ponto.UF || '';

      // Display Visual: preenchemos para feedback ao usuário
      const cepFormatado = ponto.CEP ? `CEP: ${ponto.CEP}` : '';
      const enderecoDisplay = [cepFormatado, ponto.LOGRADOURO, ponto.bairro, ponto.cidade, ponto.UF].filter(n => n).join(', ');
      document.getElementById('endereco_display_editar').textContent = enderecoDisplay || 'Aguardando Busca por Localização...';

      if (ponto.LATITUDE && ponto.LONGITUDE) {
        document.getElementById('latitude_editar').value = ponto.LATITUDE;
        document.getElementById('longitude_editar').value = ponto.LONGITUDE;
        document.getElementById('coordenadas_display_editar').textContent = `${parseFloat(ponto.LATITUDE).toFixed(6)}, ${parseFloat(ponto.LONGITUDE).toFixed(6)}`;
      } else {
        document.getElementById('latitude_editar').value = '';
        document.getElementById('longitude_editar').value = '';
        document.getElementById('coordenadas_display_editar').textContent = 'Não cadastradas';
      }

      let valorFormatado = '';
      if (ponto.VALOR_KWH) {
        valorFormatado = parseFloat(ponto.VALOR_KWH).toFixed(2).replace('.', ',');
      }
      document.getElementById('valor_kwh_editar').value = valorFormatado;
      document.getElementById('status_ponto_editar').value = ponto.FK_STATUS_PONTO || '';

      // Limpa os campos de busca/erro ao abrir o modal
      document.getElementById('endereco_busca_editar').value = '';
      document.getElementById('geocodeError_editar').classList.remove('show');
      document.getElementById('geocodeSuccess_editar').classList.remove('show');

      abrirModal('modalEditarPonto');
    }

    function confirmarExclusao(id) {
      document.getElementById('id_ponto_deletar').value = id;
      abrirModal('modalConfirmarExclusao');
    }

    function mudarTab(tab) {
      document.getElementById('contentDadosPessoais').classList.add('hidden');
      document.getElementById('contentAlterarSenha').classList.add('hidden');
      document.getElementById('tabDadosPessoais').classList.remove('border-cyan-500', 'text-white');
      document.getElementById('tabAlterarSenha').classList.remove('border-cyan-500', 'text-white');
      document.getElementById('tabDadosPessoais').classList.add('text-gray-400', 'border-transparent');
      document.getElementById('tabAlterarSenha').classList.add('text-gray-400', 'border-transparent');

      document.getElementById(`content${tab.charAt(0).toUpperCase() + tab.slice(1)}`).classList.remove('hidden');
      document.getElementById(`tab${tab.charAt(0).toUpperCase() + tab.slice(1)}`).classList.add('border-cyan-500', 'text-white');
      document.getElementById(`tab${tab.charAt(0).toUpperCase() + tab.slice(1)}`).classList.remove('text-gray-400', 'border-transparent');
    }
  </script>

</body>

</html>