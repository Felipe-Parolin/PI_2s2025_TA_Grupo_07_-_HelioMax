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
} catch (PDOException $e) {
  die("Erro na conexão: " . $e->getMessage());
}

// Variáveis de controle de estado e mensagens
$erro_login = '';
$sucesso_recuperacao = '';
$erro_recuperacao = '';

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {

  // --- LÓGICA DE RECUPERAÇÃO DE SENHA ---
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // AÇÃO 1: SOLICITAR TOKEN DE RECUPERAÇÃO
    if ($_POST['action'] === 'solicitar_token') {
      $email = trim($_POST['email']);

      $stmt = $pdo->prepare("SELECT ID_USER FROM usuario WHERE EMAIL = ?");
      $stmt->execute([$email]);
      $usuario = $stmt->fetch();

      if ($usuario) {
        $user_id = $usuario['ID_USER'];
        // Gera um token criptograficamente seguro (64 caracteres)
        $token = bin2hex(random_bytes(32));
        // Token expira em 1 hora
        $expiracao = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $pdo->prepare("UPDATE usuario SET token_recuperacao = ?, expiracao_token = ? WHERE ID_USER = ?");
        $stmt->execute([$token, $expiracao, $user_id]);

        // --- SIMULAÇÃO DE ENVIO DE EMAIL ---
        // ATENÇÃO: Em um ambiente real, você enviaria este link por e-mail.
        $link_recuperacao = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?action=resetar_senha&token=" . $token;

        $sucesso_recuperacao = "O link de recuperação foi gerado. <br>Por favor, clique no link abaixo para redefinir sua senha: <br><a href=\"$link_recuperacao\" class=\"text-cyan-400 font-semibold hover:underline break-all\">$link_recuperacao</a>";
        // ------------------------------------
      } else {
        $sucesso_recuperacao = "Se o email estiver cadastrado, um link de recuperação (simulado) foi gerado.";
      }
    }

    // AÇÃO 2: ATUALIZAR SENHA (APÓS SUBMISSÃO DO FORMULÁRIO DE RESET)
    if ($_POST['action'] === 'resetar_senha_final') {
      $token = $_POST['token'];
      $nova_senha = $_POST['nova_senha'];
      $confirmar = $_POST['confirmar_senha'];

      if ($nova_senha !== $confirmar) {
        $erro_recuperacao = 'As senhas não conferem.';
      } else {
        // Busca o usuário pelo token e verifica se não expirou
        $stmt = $pdo->prepare("SELECT ID_USER FROM usuario WHERE token_recuperacao = ? AND expiracao_token > NOW()");
        $stmt->execute([$token]);
        $usuario = $stmt->fetch();

        if ($usuario) {
          // Atualiza a senha e limpa o token
          $stmt = $pdo->prepare("UPDATE usuario SET SENHA = ?, token_recuperacao = NULL, expiracao_token = NULL WHERE ID_USER = ?");
          $stmt->execute([$nova_senha, $usuario['ID_USER']]);

          // Redireciona para a tela de login com mensagem de sucesso
          header('Location: dashADM.php?login_msg=Senha alterada com sucesso! Faça login.');
          exit;
        } else {
          $erro_recuperacao = 'Token inválido ou expirado. Por favor, solicite a recuperação novamente.';
        }
      }
    }
  }

  // --- LÓGICA DE LOGIN ORIGINAL ---
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    $stmt = $pdo->prepare("SELECT ID_USER, NOME, EMAIL, TIPO_USUARIO FROM usuario WHERE EMAIL = ? AND SENHA = ?");
    $stmt->execute([$email, $senha]);

    if ($usuario = $stmt->fetch()) {
      $_SESSION['usuario_id'] = $usuario['ID_USER'];
      $_SESSION['usuario_nome'] = $usuario['NOME'];
      $_SESSION['usuario_email'] = $usuario['EMAIL'];
      header('Location: dashADM.php');
      exit;
    } else {
      $erro_login = 'Email ou senha inválidos!';
    }
  }

  // Processa a mensagem de sucesso pós-reset
  if (isset($_GET['login_msg'])) {
    $sucesso_recuperacao = htmlspecialchars($_GET['login_msg']);
  }

  // --- RENDERIZAÇÃO DA TELA DE LOGIN, RECUPERAÇÃO OU RESET ---

  // 1. Variáveis de controle para renderização
  $modo_recuperacao = isset($_GET['action']) && $_GET['action'] === 'recuperar_senha';
  $modo_reset = isset($_GET['action']) && $_GET['action'] === 'resetar_senha' && isset($_GET['token']);
  $token_reset = $modo_reset ? $_GET['token'] : '';

  if ($modo_reset) {
    // Verifica a validade do token antes de mostrar o formulário de reset
    $stmt = $pdo->prepare("SELECT ID_USER FROM usuario WHERE token_recuperacao = ? AND expiracao_token > NOW()");
    $stmt->execute([$token_reset]);
    if (!$stmt->fetch()) {
      $erro_recuperacao = 'Link de recuperação inválido ou expirado. Por favor, solicite a recuperação novamente.';
      $modo_reset = false; // Volta para o modo de solicitação
      $modo_recuperacao = true;
    }
  }

  // Mostrar tela de login, solicitação ou reset
  ?>
  <!DOCTYPE html>
  <html lang="pt-BR">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
      <?php
      if ($modo_reset)
        echo 'Redefinir Senha';
      elseif ($modo_recuperacao)
        echo 'Recuperar Senha';
      else
        echo 'Login';
      ?>
      - HelioMax Admin
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
  </head>

  <body
    class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <div
        class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl p-8 border border-cyan-500/20">
        <div class="flex justify-center mb-8">
          <div class="w-16 h-16 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-2xl flex items-center justify-center">
            <i data-lucide="zap" class="w-10 h-10 text-white"></i>
          </div>
        </div>

        <h1 class="text-3xl font-bold text-white text-center mb-2">HelioMax Admin</h1>
        <p class="text-gray-400 text-center mb-8">
          <?php
          if ($modo_reset)
            echo 'Defina sua nova senha';
          elseif ($modo_recuperacao)
            echo 'Informe seu e-mail para recuperar a senha';
          else
            echo 'Faça login para continuar';
          ?>
        </p>

        <?php if ($erro_login): ?>
          <div class="mb-6 p-4 bg-red-500/20 border border-red-500/30 rounded-xl">
            <p class="text-red-400 text-sm"><?php echo $erro_login; ?></p>
          </div>
        <?php endif; ?>

        <?php if ($sucesso_recuperacao): ?>
          <div class="mb-6 p-4 bg-green-500/20 border border-green-500/30 rounded-xl">
            <p class="text-green-400 text-sm"><?php echo $sucesso_recuperacao; ?></p>
          </div>
        <?php endif; ?>

        <?php if ($erro_recuperacao): ?>
          <div class="mb-6 p-4 bg-red-500/20 border border-red-500/30 rounded-xl">
            <p class="text-red-400 text-sm"><?php echo $erro_recuperacao; ?></p>
          </div>
        <?php endif; ?>

        <?php if ($modo_reset): ?>
          <form method="POST" action="">
            <input type="hidden" name="action" value="resetar_senha_final">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token_reset); ?>">

            <div class="mb-6">
              <label class="block text-gray-400 text-sm font-semibold mb-2">Nova Senha</label>
              <input type="password" name="nova_senha" required
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div class="mb-6">
              <label class="block text-gray-400 text-sm font-semibold mb-2">Confirmar Nova Senha</label>
              <input type="password" name="confirmar_senha" required
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <button type="submit"
              class="w-full bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg shadow-cyan-500/30">
              Redefinir Senha
            </button>

            <p class="text-center mt-4">
              <a href="dashADM.php" class="text-gray-400 text-sm hover:text-cyan-400 transition-colors">Voltar para o
                Login</a>
            </p>
          </form>

        <?php elseif ($modo_recuperacao): ?>
          <form method="POST" action="">
            <input type="hidden" name="action" value="solicitar_token">

            <div class="mb-6">
              <label class="block text-gray-400 text-sm font-semibold mb-2">Email Cadastrado</label>
              <input type="email" name="email" required
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <button type="submit"
              class="w-full bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg shadow-cyan-500/30">
              Enviar Link de Recuperação
            </button>

            <p class="text-center mt-4">
              <a href="dashADM.php" class="text-gray-400 text-sm hover:text-cyan-400 transition-colors">Voltar para o
                Login</a>
            </p>
          </form>

        <?php else: ?>
          <form method="POST" action="">
            <input type="hidden" name="action" value="login">
            <div class="mb-6">
              <label class="block text-gray-400 text-sm font-semibold mb-2">Email</label>
              <input type="email" name="email" required value="matheus@email.com"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div class="mb-6">
              <label class="block text-gray-400 text-sm font-semibold mb-2">Senha</label>
              <input type="password" name="senha" required value="123456"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <button type="submit"
              class="w-full bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg shadow-cyan-500/30">
              Entrar
            </button>

            <p class="text-center mt-4">
              <a href="?action=recuperar_senha" class="text-gray-400 text-sm hover:text-cyan-400 transition-colors">Esqueceu
                a senha?</a>
            </p>
          </form>

          <p class="text-xs text-gray-500 text-center mt-6">Credenciais padrão já preenchidas</p>
        <?php endif; ?>
      </div>
    </div>

    <script>lucide.createIcons();</script>
  </body>

  </html>
  <?php
  exit;
}

// ID do usuário logado
$admin_id = $_SESSION['usuario_id'];

// Processar ações do CRUD
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // CRIAR/EDITAR PONTO (Ambos os formulários usam esta lógica)
  if ($action === 'salvar_ponto') {
    // Uso de intval(trim(...)) para garantir a leitura correta do ID
    $id_ponto_raw = $_POST['id_ponto'] ?? '0';
    $id_ponto = intval(trim($id_ponto_raw));

    $logradouro = trim($_POST['logradouro']);
    $numero = trim($_POST['numero']);
    $complemento = trim($_POST['complemento']);
    $bairro = trim($_POST['bairro']);
    $cidade = trim($_POST['cidade']);
    $uf = strtoupper(trim($_POST['uf']));
    $valor_kwh = str_replace(',', '.', $_POST['valor_kwh']);
    $status_ponto = $_POST['status_ponto'];

    try {
      $pdo->beginTransaction();

      if ($id_ponto > 0) {
        // LÓGICA DE EDIÇÃO

        // **AJUSTE: Buscar o ID do CEP existente E verificar se o ponto pertence ao admin logado**
        $stmt = $pdo->prepare("SELECT LOCALIZACAO FROM ponto_carregamento WHERE ID_PONTO = ? AND FK_ID_USUARIO_CADASTRO = ?");
        $stmt->execute([$id_ponto, $admin_id]);
        $resultado = $stmt->fetch();

        if (!$resultado) {
          throw new Exception("Ponto não encontrado ou você não tem permissão para editar!");
        }

        $cep_id = $resultado['LOCALIZACAO'];

        // Buscar IDs relacionados
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

        // Atualizar logradouro
        $stmt = $pdo->prepare("UPDATE cep SET LOGRADOURO = ? WHERE ID_CEP = ?");
        $stmt->execute([$logradouro, $cep_id]);

        // Atualizar ponto (já verificamos a permissão acima)
        $stmt = $pdo->prepare("UPDATE ponto_carregamento SET VALOR_KWH = ?, FK_STATUS_PONTO = ? WHERE ID_PONTO = ?");
        $stmt->execute([$valor_kwh, $status_ponto, $id_ponto]);

        $mensagem = 'Ponto atualizado com sucesso!';
      } else {
        // LÓGICA DE CRIAÇÃO

        // Verificar ou criar estado
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

        // Verificar ou criar cidade
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

        // Verificar ou criar bairro
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

        // Criar CEP/Logradouro
        $stmt = $pdo->prepare("INSERT INTO cep (LOGRADOURO, FK_BAIRRO) VALUES (?, ?)");
        $stmt->execute([$logradouro, $bairro_id]);
        $cep_id = $pdo->lastInsertId();

        // **AJUSTE: Criar ponto, incluindo o FK_ID_USUARIO_CADASTRO**
        $stmt = $pdo->prepare("INSERT INTO ponto_carregamento (LOCALIZACAO, VALOR_KWH, FK_STATUS_PONTO, FK_ID_USUARIO_CADASTRO) VALUES (?, ?, ?, ?)");
        $stmt->execute([$cep_id, $valor_kwh, $status_ponto, $admin_id]);

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

  // DELETAR PONTO (ATUALIZADO COM EXCLUSÃO EM CASCATA E PERMISSÃO)
  if ($action === 'deletar_ponto') {
    $id_ponto = $_POST['id_ponto'];

    try {
      $pdo->beginTransaction();

      // 1. Obter o CEP ID do ponto E verificar se o ponto pertence ao admin logado
      $stmt = $pdo->prepare("SELECT LOCALIZACAO, FK_ID_USUARIO_CADASTRO FROM ponto_carregamento WHERE ID_PONTO = ?");
      $stmt->execute([$id_ponto]);
      $ponto_info = $stmt->fetch();

      if (!$ponto_info || $ponto_info['FK_ID_USUARIO_CADASTRO'] != $admin_id) {
        throw new Exception("Ponto não encontrado ou você não tem permissão para excluir!");
      }

      $cep_id = $ponto_info['LOCALIZACAO'];


      // 2. Deletar o Ponto de Carregamento (apenas se pertencer ao admin)
      $stmt = $pdo->prepare("DELETE FROM ponto_carregamento WHERE ID_PONTO = ? AND FK_ID_USUARIO_CADASTRO = ?");
      $stmt->execute([$id_ponto, $admin_id]);

      if ($cep_id) {
        // 3. Verificar e deletar CEP (Logradouro)
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

          // 4. Verificar e deletar BAIRRO
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

              // 5. Verificar e deletar CIDADE
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

                  // 6. Verificar e deletar ESTADO
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
      $mensagem = 'Ponto e dados de endereço relacionados excluídos com sucesso!';
      $tipo_mensagem = 'sucesso';

    } catch (Exception $e) {
      $pdo->rollBack();
      $mensagem = 'Erro ao excluir ponto e dados relacionados: ' . $e->getMessage();
      $tipo_mensagem = 'erro';
    }
  }

  // ATUALIZAR PERFIL (ATUALIZADO COM LIMPEZA DE CPF E NÚMERO)
  if ($action === 'atualizar_perfil') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    // Limpar CPF e Número, permitindo apenas dígitos
    $cpf = preg_replace('/\D/', '', $_POST['cpf']);
    $numero = preg_replace('/\D/', '', $_POST['numero_residencia']);
    $complemento = $_POST['complemento'];
    $logradouro_perfil = $_POST['logradouro_perfil'];
    $bairro_perfil = $_POST['bairro_perfil'];
    $cidade_perfil = $_POST['cidade_perfil'];
    $uf_perfil = strtoupper($_POST['uf_perfil']);

    try {
      $pdo->beginTransaction();

      $cep_id = null;

      // Se preencheu endereço, criar/atualizar
      if ($logradouro_perfil && $bairro_perfil && $cidade_perfil && $uf_perfil) {
        // Verificar ou criar estado
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

        // Verificar ou criar cidade
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

        // Verificar ou criar bairro
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

        // Criar CEP/Logradouro
        $stmt = $pdo->prepare("INSERT INTO cep (LOGRADOURO, FK_BAIRRO) VALUES (?, ?)");
        $stmt->execute([$logradouro_perfil, $bairro_id]);
        $cep_id = $pdo->lastInsertId();
      }

      $stmt = $pdo->prepare("UPDATE usuario SET NOME = ?, EMAIL = ?, CPF = ?, NUMERO_RESIDENCIA = ?, COMPLEMENTO_ENDERECO = ?, FK_ID_CEP = ? WHERE ID_USER = ?");
      $stmt->execute([$nome, $email, $cpf, $numero, $complemento, $cep_id, $_SESSION['usuario_id']]);

      $_SESSION['usuario_nome'] = $nome;
      $_SESSION['usuario_email'] = $email;

      $pdo->commit();
      $mensagem = 'Perfil atualizado com sucesso!';
      $tipo_mensagem = 'sucesso';
    } catch (PDOException $e) {
      $pdo->rollBack();
      $mensagem = 'Erro ao atualizar: ' . $e->getMessage();
      $tipo_mensagem = 'erro';
    }
  }

  // ALTERAR SENHA
  if ($action === 'alterar_senha') {
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar = $_POST['confirmar_senha'];

    $stmt = $pdo->prepare("SELECT SENHA FROM usuario WHERE ID_USER = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $senha_bd = $stmt->fetch()['SENHA'];

    if ($senha_atual !== $senha_bd) {
      $mensagem = 'Senha atual incorreta!';
      $tipo_mensagem = 'erro';
    } elseif ($nova_senha !== $confirmar) {
      $mensagem = 'As senhas não conferem!';
      $tipo_mensagem = 'erro';
    } else {
      $stmt = $pdo->prepare("UPDATE usuario SET SENHA = ? WHERE ID_USER = ?");
      $stmt->execute([$nova_senha, $_SESSION['usuario_id']]);
      $mensagem = 'Senha alterada com sucesso!';
      $tipo_mensagem = 'sucesso';
    }
  }
}

// LOGOUT
if (isset($_GET['logout'])) {
  session_destroy();
  header('Location: dashADM.php');
  exit;
}

// Buscar dados
$busca = $_GET['busca'] ?? '';
$status_filtro = $_GET['status'] ?? '';

// Estatísticas
// **AJUSTE: Conta apenas os pontos do administrador logado**
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
        WHERE pc.FK_ID_USUARIO_CADASTRO = ?"; // **AJUSTE: FILTRAR PONTOS PELO ID DO ADMIN LOGADO**

$params = [$admin_id]; // Adiciona o ID do admin como primeiro parâmetro

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

// Buscar Status para formulários
$status_lista = $pdo->query("SELECT * FROM status_ponto ORDER BY DESCRICAO")->fetchAll();

// Buscar dados do usuário para perfil
$stmt = $pdo->prepare("SELECT u.*, c.LOGRADOURO, b.NOME as bairro, ci.NOME as cidade, e.UF 
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
  <title>HelioMax Admin Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    .modal {
      display: none;
    }

    .modal.active {
      display: flex;
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

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div
        class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl p-6 border border-cyan-500/20 hover:border-cyan-500/40 transition-all duration-300 hover:scale-105">
        <div class="flex items-center justify-between mb-4">
          <div
            class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center">
            <i data-lucide="map-pin" class="w-6 h-6 text-white"></i>
          </div>
          <span class="text-green-400 text-sm font-semibold">+12%</span>
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
          <span class="text-green-400 text-sm font-semibold">+8%</span>
        </div>
        <h3 class="text-gray-400 text-sm mb-1">Usuários na Plataforma</h3>
        <p class="text-3xl font-bold text-white"><?php echo $totalUsuarios; ?></p>
      </div>

      <div
        class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl p-6 border border-cyan-500/20 hover:border-cyan-500/40 transition-all duration-300 hover:scale-105">
        <div class="flex items-center justify-between mb-4">
          <div
            class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500 to-emerald-500 flex items-center justify-center">
            <i data-lucide="zap" class="w-6 h-6 text-white"></i>
          </div>
          <span class="text-green-400 text-sm font-semibold">+23%</span>
        </div>
        <h3 class="text-gray-400 text-sm mb-1">Carregamentos Hoje</h3>
        <p class="text-3xl font-bold text-white">342</p>
      </div>

      <div
        class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl p-6 border border-cyan-500/20 hover:border-cyan-500/40 transition-all duration-300 hover:scale-105">
        <div class="flex items-center justify-between mb-4">
          <div
            class="w-12 h-12 rounded-xl bg-gradient-to-br from-orange-500 to-yellow-500 flex items-center justify-center">
            <i data-lucide="trending-up" class="w-6 h-6 text-white"></i>
          </div>
          <span class="text-green-400 text-sm font-semibold">+5%</span>
        </div>
        <h3 class="text-gray-400 text-sm mb-1">Taxa de Ocupação</h3>
        <p class="text-3xl font-bold text-white">68%</p>
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
          <option value="Ativo" <?php echo $status_filtro === 'Ativo' ? 'selected' : ''; ?>>Ativos</option>
          <option value="Inativo" <?php echo $status_filtro === 'Inativo' ? 'selected' : ''; ?>>Inativos</option>
          <option value="Manutenção" <?php echo $status_filtro === 'Manutenção' ? 'selected' : ''; ?>>Em Manutenção
          </option>
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
          Pontos de Recarga Cadastrados (Meus Pontos)
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
                        </p>
                        <p class="text-sm text-gray-400">
                          <?php echo htmlspecialchars($ponto['bairro'] ?? ''); ?> -
                          <?php echo htmlspecialchars($ponto['cidade'] ?? ''); ?> -
                          <?php echo htmlspecialchars($ponto['UF'] ?? ''); ?>
                        </p>
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
                    } elseif ($ponto['status_desc'] === 'Manutenção') {
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
      class="bg-gradient-to-br from-slate-800/95 to-slate-900/95 backdrop-blur-xl rounded-2xl border border-cyan-500/20 max-w-3xl w-full max-h-[90vh] overflow-y-auto">
      <div class="p-6 border-b border-cyan-500/20 flex items-center justify-between sticky top-0 bg-slate-900/90">
        <h2 class="text-2xl font-bold text-white flex items-center gap-2">
          <i data-lucide="map-pin" class="w-6 h-6 text-cyan-400"></i>
          <span id="tituloModalCriar">Cadastrar Ponto de Recarga</span>
        </h2>
        <button onclick="fecharModal('modalCriarPonto')" class="p-2 hover:bg-slate-700/50 rounded-lg transition-colors">
          <i data-lucide="x" class="w-6 h-6 text-gray-400"></i>
        </button>
      </div>

      <form method="POST" action="">
        <input type="hidden" name="action" value="salvar_ponto">
        <input type="hidden" name="id_ponto" value="0">
        <div class="p-6">
          <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
            <i data-lucide="map" class="w-5 h-5 text-cyan-400"></i>
            Endereço do Ponto
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="md:col-span-2">
              <label class="block text-gray-400 text-sm font-semibold mb-2">Logradouro *</label>
              <input type="text" name="logradouro" id="logradouro_criar" required
                placeholder="Ex: Av. Brigadeiro Faria Lima"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Número</label>
              <input type="text" name="numero" id="numero_criar" placeholder="Ex: 2232"
                oninput="this.value = this.value.replace(/\D/g, '')" inputmode="numeric"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Complemento</label>
              <input type="text" name="complemento" id="complemento_criar" placeholder="Ex: Sala 301"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Bairro *</label>
              <input type="text" name="bairro" id="bairro_criar" required placeholder="Ex: Pinheiros"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Cidade *</label>
              <input type="text" name="cidade" id="cidade_criar" required placeholder="Ex: São Paulo"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">UF *</label>
              <input type="text" name="uf" id="uf_criar" required placeholder="Ex: SP" maxlength="2"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors uppercase">
            </div>
          </div>

          <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2 mt-6">
            <i data-lucide="zap" class="w-5 h-5 text-cyan-400"></i>
            Informações do Ponto
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Valor por kWh (R$) *</label>
              <input type="text" name="valor_kwh" id="valor_kwh_criar" required placeholder="Ex: 0,85"
                oninput="this.value = this.value.replace(/[^0-9,]/g, '').replace(/(\,.*?)\,/g, '$1');"
                inputmode="numeric"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Status do Ponto *</label>
              <select name="status_ponto" id="status_ponto_criar" required
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white focus:outline-none focus:border-cyan-500/50 transition-colors cursor-pointer">
                <option value="">Selecione um status</option>
                <?php foreach ($status_lista as $st): ?>
                  <option value="<?php echo $st['ID_STATUS_PONTO']; ?>">
                    <?php echo htmlspecialchars($st['DESCRICAO']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
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
      class="bg-gradient-to-br from-slate-800/95 to-slate-900/95 backdrop-blur-xl rounded-2xl border border-cyan-500/20 max-w-3xl w-full max-h-[90vh] overflow-y-auto">
      <div class="p-6 border-b border-cyan-500/20 flex items-center justify-between sticky top-0 bg-slate-900/90">
        <h2 class="text-2xl font-bold text-white flex items-center gap-2">
          <i data-lucide="map-pin" class="w-6 h-6 text-cyan-400"></i>
          <span id="tituloModalEditar">Editar Ponto de Recarga</span>
        </h2>
        <button onclick="fecharModal('modalEditarPonto')"
          class="p-2 hover:bg-slate-700/50 rounded-lg transition-colors">
          <i data-lucide="x" class="w-6 h-6 text-gray-400"></i>
        </button>
      </div>

      <form method="POST" action="">
        <input type="hidden" name="action" value="salvar_ponto">
        <input type="hidden" name="id_ponto" id="id_ponto_editar" value="">
        <div class="p-6">
          <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
            <i data-lucide="map" class="w-5 h-5 text-cyan-400"></i>
            Endereço do Ponto
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="md:col-span-2">
              <label class="block text-gray-400 text-sm font-semibold mb-2">Logradouro *</label>
              <input type="text" name="logradouro" id="logradouro_editar" required
                placeholder="Ex: Av. Brigadeiro Faria Lima"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Número</label>
              <input type="text" name="numero" id="numero_editar" placeholder="Ex: 2232"
                oninput="this.value = this.value.replace(/\D/g, '')" inputmode="numeric"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Complemento</label>
              <input type="text" name="complemento" id="complemento_editar" placeholder="Ex: Sala 301"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Bairro *</label>
              <input type="text" name="bairro" id="bairro_editar" required placeholder="Ex: Pinheiros"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Cidade *</label>
              <input type="text" name="cidade" id="cidade_editar" required placeholder="Ex: São Paulo"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">UF *</label>
              <input type="text" name="uf" id="uf_editar" required placeholder="Ex: SP" maxlength="2"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors uppercase">
            </div>
          </div>

          <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2 mt-6">
            <i data-lucide="zap" class="w-5 h-5 text-cyan-400"></i>
            Informações do Ponto
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Valor por kWh (R$) *</label>
              <input type="text" name="valor_kwh" id="valor_kwh_editar" required placeholder="Ex: 0,85"
                oninput="this.value = this.value.replace(/[^0-9,]/g, '').replace(/(\,.*?)\,/g, '$1');"
                inputmode="numeric"
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
          </div>

          <div class="flex flex-col sm:flex-row gap-4">
            <button type="submit"
              class="flex-1 bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg shadow-cyan-500/30">
              <i data-lucide="save" class="w-5 h-5 inline-block mr-2"></i>
              Atualizar Ponto
            </button>

            <button type="button" onclick="fecharModal('modalEditarPonto')"
              class="flex-1 bg-slate-700/50 hover:bg-slate-700 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 border border-slate-600">
              <i data-lucide="x" class="w-5 h-5 inline-block mr-2"></i>
              Cancelar
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>


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
            class="px-4 py-3 text-gray-400 font-semibold border-b-2 border-transparent hover:text-white transition-colors">
            Alterar Senha
          </button>
        </div>
      </div>

      <div id="contentDadosPessoais" class="p-6">
        <form method="POST" action="">
          <input type="hidden" name="action" value="atualizar_perfil">

          <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
            <i data-lucide="edit-3" class="w-5 h-5 text-cyan-400"></i>
            Dados Pessoais
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Nome Completo *</label>
              <input type="text" name="nome" required value="<?php echo htmlspecialchars($usuario['NOME']); ?>"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">CPF * (Apenas Números)</label>
              <input type="text" name="cpf" required value="<?php echo htmlspecialchars($usuario['CPF']); ?>"
                maxlength="11" oninput="this.value = this.value.replace(/\D/g, '')" inputmode="numeric"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div class="md:col-span-2">
              <label class="block text-gray-400 text-sm font-semibold mb-2">Email *</label>
              <input type="email" name="email" required value="<?php echo htmlspecialchars($usuario['EMAIL']); ?>"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>
          </div>

          <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2 mt-8">
            <i data-lucide="map-pin" class="w-5 h-5 text-cyan-400"></i>
            Endereço
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="md:col-span-2">
              <label class="block text-gray-400 text-sm font-semibold mb-2">Logradouro</label>
              <input type="text" name="logradouro_perfil"
                value="<?php echo htmlspecialchars($usuario['LOGRADOURO'] ?? ''); ?>" placeholder="Ex: Av. Paulista"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Número (Apenas Números)</label>
              <input type="text" name="numero_residencia"
                value="<?php echo htmlspecialchars($usuario['NUMERO_RESIDENCIA'] ?? ''); ?>"
                oninput="this.value = this.value.replace(/\D/g, '')" inputmode="numeric"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Complemento</label>
              <input type="text" name="complemento"
                value="<?php echo htmlspecialchars($usuario['COMPLEMENTO_ENDERENCO'] ?? ''); ?>"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Bairro</label>
              <input type="text" name="bairro_perfil" value="<?php echo htmlspecialchars($usuario['bairro'] ?? ''); ?>"
                placeholder="Ex: Bela Vista"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Cidade</label>
              <input type="text" name="cidade_perfil" value="<?php echo htmlspecialchars($usuario['cidade'] ?? ''); ?>"
                placeholder="Ex: São Paulo"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">UF</label>
              <input type="text" name="uf_perfil" value="<?php echo htmlspecialchars($usuario['UF'] ?? ''); ?>"
                placeholder="Ex: SP" maxlength="2"
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors uppercase">
            </div>
          </div>

          <button type="submit"
            class="w-full bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg shadow-cyan-500/30">
            <i data-lucide="save" class="w-5 h-5 inline-block mr-2"></i>
            Salvar Alterações
          </button>
        </form>
      </div>

      <div id="contentAlterarSenha" class="p-6 hidden">
        <form method="POST" action="">
          <input type="hidden" name="action" value="alterar_senha">

          <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
            <i data-lucide="lock" class="w-5 h-5 text-cyan-400"></i>
            Alterar Senha
          </h3>

          <div class="mb-6">
            <label class="block text-gray-400 text-sm font-semibold mb-2">Senha Atual *</label>
            <input type="password" name="senha_atual" required
              class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Nova Senha *</label>
              <input type="password" name="nova_senha" required
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>

            <div>
              <label class="block text-gray-400 text-sm font-semibold mb-2">Confirmar Nova Senha *</label>
              <input type="password" name="confirmar_senha" required
                class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
            </div>
          </div>

          <button type="submit"
            class="w-full bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg shadow-purple-500/30">
            <i data-lucide="key" class="w-5 h-5 inline-block mr-2"></i>
            Alterar Senha
          </button>
        </form>
      </div>
    </div>
  </div>

  <form id="formDeletar" method="POST" action="" style="display:none;">
    <input type="hidden" name="action" value="deletar_ponto">
    <input type="hidden" name="id_ponto" id="id_ponto_deletar">
  </form>

  <script>
    lucide.createIcons();

    function fecharModal(id) {
      document.getElementById(id).classList.remove('active');
    }

    function limparFormPontoCriar() {
      // Usado para garantir que o formulário de CRIAÇÃO esteja sempre limpo
      document.getElementById('logradouro_criar').value = '';
      document.getElementById('numero_criar').value = '';
      document.getElementById('complemento_criar').value = '';
      document.getElementById('bairro_criar').value = '';
      document.getElementById('cidade_criar').value = '';
      document.getElementById('uf_criar').value = '';
      document.getElementById('valor_kwh_criar').value = '';
      document.getElementById('status_ponto_criar').value = '';
    }

    function abrirModal(id) {
      document.getElementById(id).classList.add('active');
      if (id === 'modalCriarPonto') {
        limparFormPontoCriar();
      }
      lucide.createIcons();
    }

    function editarPonto(ponto) {
      console.log('Editando ponto:', ponto); // Debug

      // 1. CHAVE: Preencher o campo oculto ID_PONTO_EDITAR
      document.getElementById('id_ponto_editar').value = parseInt(ponto.ID_PONTO) || '';

      // 2. Preencher os campos do formulário de EDIÇÃO (IDs exclusivos)
      document.getElementById('logradouro_editar').value = ponto.LOGRADOURO || '';
      document.getElementById('bairro_editar').value = ponto.bairro || '';
      document.getElementById('cidade_editar').value = ponto.cidade || '';
      document.getElementById('uf_editar').value = ponto.UF || '';

      // Campos opcionais/não mapeados
      document.getElementById('numero_editar').value = ponto.NUMERO || '';
      document.getElementById('complemento_editar').value = ponto.COMPLEMENTO || '';

      // Preencher Valor kWh e Status
      let valorFormatado = '';
      if (ponto.VALOR_KWH) {
        valorFormatado = parseFloat(ponto.VALOR_KWH).toFixed(2).replace('.', ',');
      }
      document.getElementById('valor_kwh_editar').value = valorFormatado;
      document.getElementById('status_ponto_editar').value = ponto.FK_STATUS_PONTO || '';

      // 3. Abrir o novo modal de Edição
      abrirModal('modalEditarPonto');

      console.log('ID do ponto preenchido para edição:', document.getElementById('id_ponto_editar').value); // Debug
    }

    function confirmarExclusao(id) {
      if (confirm('ATENÇÃO: Você tem certeza que deseja excluir este ponto de carregamento? Todos os dados de endereço não utilizados por outros registros também serão removidos.')) {
        document.getElementById('id_ponto_deletar').value = id;
        document.getElementById('formDeletar').submit();
      }
    }

    function mudarTab(tab) {
      document.getElementById('contentDadosPessoais').classList.add('hidden');
      document.getElementById('contentAlterarSenha').classList.add('hidden');

      document.getElementById('tabDadosPessoais').classList.remove('border-cyan-500', 'text-white');
      document.getElementById('tabDadosPessoais').classList.add('border-transparent', 'text-gray-400');
      document.getElementById('tabAlterarSenha').classList.remove('border-cyan-500', 'text-white');
      document.getElementById('tabAlterarSenha').classList.add('border-transparent', 'text-gray-400');

      if (tab === 'dadosPessoais') {
        document.getElementById('contentDadosPessoais').classList.remove('hidden');
        document.getElementById('tabDadosPessoais').classList.add('border-cyan-500', 'text-white');
        document.getElementById('tabDadosPessoais').classList.remove('border-transparent', 'text-gray-400');
      } else {
        document.getElementById('contentAlterarSenha').classList.remove('hidden');
        document.getElementById('tabAlterarSenha').classList.add('border-cyan-500', 'text-white');
        document.getElementById('tabAlterarSenha').classList.remove('border-transparent', 'text-gray-400');
      }

      lucide.createIcons();
    }

    // Fechar modal ao clicar fora
    document.querySelectorAll('.modal').forEach(modal => {
      modal.addEventListener('click', function (e) {
        if (e.target === this) {
          this.classList.remove('active');
        }
      });
    });

    // Converter UF para maiúsculas automaticamente
    document.querySelectorAll('input[name="uf"], input[name="uf_perfil"]').forEach(input => {
      input.addEventListener('input', function (e) {
        this.value = this.value.toUpperCase();
      });
    });

    // Recriar ícones após carregamento
    document.addEventListener('DOMContentLoaded', function () {
      lucide.createIcons();
      // Garante que a primeira aba esteja ativa ao abrir o modalPerfil
      mudarTab('dadosPessoais');
    });
  </script>
</body>

</html>