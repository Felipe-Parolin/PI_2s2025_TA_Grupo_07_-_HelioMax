<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
     header('Location: login.php');
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
$mensagem = '';
$tipo_mensagem = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $action = $_POST['action'] ?? '';

     // ATUALIZAR PERFIL
     if ($action === 'atualizar_perfil') {
          $nome = $_POST['nome'];
          $email = $_POST['email'];
          // CPF vem do hidden field, não pode ser alterado
          $cpf = $_POST['cpf'];
          $cep_valor = preg_replace('/\D/', '', $_POST['cep_perfil']);
          $numero = trim($_POST['numero_residencia']);
          $complemento = trim($_POST['complemento']);
          $logradouro_perfil = trim($_POST['logradouro_perfil']);
          $bairro_perfil = trim($_POST['bairro_perfil']);
          $cidade_perfil = trim($_POST['cidade_perfil']);
          $uf_perfil = strtoupper(trim($_POST['uf_perfil']));

          try {
               $pdo->beginTransaction();

               $stmt = $pdo->prepare("SELECT ID_USER FROM usuario WHERE EMAIL = ? AND ID_USER != ?");
               $stmt->execute([$email, $user_id]);
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

               $stmt = $pdo->prepare("UPDATE usuario SET NOME = ?, EMAIL = ?, CEP = ?, NUMERO_RESIDENCIA = ?, COMPLEMENTO_ENDERECO = ?, FK_ID_CEP = ? WHERE ID_USER = ?");
               $cep_formatado_usuario = strlen($cep_valor) === 8 ? substr($cep_valor, 0, 5) . '-' . substr($cep_valor, 5) : $cep_valor;
               $stmt->execute([$nome, $email, $cep_formatado_usuario, $numero, $complemento, $cep_id, $user_id]);

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

     // ALTERAR SENHA
     if ($action === 'alterar_senha') {
          $senha_atual = $_POST['senha_atual'];
          $nova_senha = $_POST['nova_senha'];
          $confirmar = $_POST['confirmar_senha'];

          try {
               $stmt = $pdo->prepare("SELECT SENHA FROM usuario WHERE ID_USER = ?");
               $stmt->execute([$user_id]);
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
                    $stmt->execute([$nova_senha_hash, $user_id]);

                    $mensagem = 'Senha alterada com sucesso!';
                    $tipo_mensagem = 'sucesso';
               }
          } catch (Exception $e) {
               $mensagem = 'Erro ao alterar senha: ' . $e->getMessage();
               $tipo_mensagem = 'erro';
          }
     }

     // TORNAR-SE ADMINISTRADOR
     if ($action === 'tornar_admin') {
          $captcha_resposta = $_POST['captcha_resposta'] ?? '';
          $captcha_correto = $_POST['captcha_correto'] ?? '';

          if ($captcha_resposta != $captcha_correto) {
               $mensagem = 'Resposta do desafio incorreta! Tente novamente.';
               $tipo_mensagem = 'erro';
          } else {
               try {
                    $stmt = $pdo->prepare("UPDATE usuario SET TIPO_USUARIO = 1 WHERE ID_USER = ?");
                    $stmt->execute([$user_id]);

                    // Destruir sessão e redirecionar para landpage
                    session_destroy();
                    header('Location: ../HTML/landpage.html');
                    exit;
               } catch (Exception $e) {
                    $mensagem = 'Erro ao processar solicitação: ' . $e->getMessage();
                    $tipo_mensagem = 'erro';
               }
          }
     }
}

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT u.*, 
                       u.CEP as CEP_USUARIO,
                       u.NUMERO_RESIDENCIA,
                       u.COMPLEMENTO_ENDERECO,
                       c.CEP as CEP_TABELA, 
                       c.LOGRADOURO, 
                       b.NOME as bairro, 
                       ci.NOME as cidade, 
                       e.UF 
                       FROM usuario u
                       LEFT JOIN cep c ON u.FK_ID_CEP = c.ID_CEP
                       LEFT JOIN bairro b ON c.FK_BAIRRO = b.ID_BAIRRO
                       LEFT JOIN cidade ci ON b.FK_CIDADE = ci.ID_CIDADE
                       LEFT JOIN estado e ON ci.FK_ESTADO = e.ID_ESTADO
                       WHERE u.ID_USER = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();

// Gerar desafio CAPTCHA
$num1 = rand(1, 10);
$num2 = rand(1, 10);
$captcha_resultado = $num1 + $num2;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
     <meta charset="UTF-8" />
     <meta name="viewport" content="width=device-width, initial-scale=1.0" />
     <link rel="icon" type="image/png" href="../../images/icon.png">
     <title>Minha Conta - HelioMax</title>
     <script src="https://cdn.tailwindcss.com"></script>
     <script src="https://unpkg.com/lucide@latest"></script>
     <style>
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

          .modal {
               display: none;
               opacity: 0;
               transition: opacity 0.3s ease-in-out;
          }

          .modal.active {
               display: flex;
               opacity: 1;
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
                              <h1 class="text-xl font-bold text-white">HelioMax</h1>
                              <p class="text-xs text-cyan-400">Minha Conta</p>
                         </div>
                    </div>

                    <div class="flex items-center gap-4">
                         <button onclick="window.history.back()"
                              class="p-2 bg-slate-800/50 hover:bg-slate-700/50 rounded-lg border border-cyan-500/20 hover:border-cyan-500/40 transition-all">
                              <i data-lucide="x" class="w-5 h-5 text-cyan-400"></i>
                         </button>
                    </div>
               </div>
          </div>
     </header>

     <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

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

          <div
               class="bg-gradient-to-br from-slate-800/95 to-slate-900/95 backdrop-blur-xl rounded-2xl border border-cyan-500/20">

               <div class="p-6 border-b border-cyan-500/20 flex items-center gap-4">
                    <div
                         class="w-16 h-16 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-2xl flex items-center justify-center">
                         <i data-lucide="user" class="w-8 h-8 text-white"></i>
                    </div>
                    <div>
                         <h2 class="text-2xl font-bold text-white"><?php echo htmlspecialchars($usuario['NOME']); ?>
                         </h2>
                         <p class="text-gray-400"><?php echo htmlspecialchars($usuario['EMAIL']); ?></p>
                         <span
                              class="inline-block mt-1 px-3 py-1 bg-<?php echo $usuario['TIPO_USUARIO'] == 1 ? 'purple' : 'blue'; ?>-500/20 text-<?php echo $usuario['TIPO_USUARIO'] == 1 ? 'purple' : 'blue'; ?>-400 rounded-full text-xs font-semibold border border-<?php echo $usuario['TIPO_USUARIO'] == 1 ? 'purple' : 'blue'; ?>-500/30">
                              <?php echo $usuario['TIPO_USUARIO'] == 1 ? 'Administrador' : 'Usuário Comum'; ?>
                         </span>
                    </div>
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
                         <?php if ($usuario['TIPO_USUARIO'] == 0): ?>
                              <button onclick="mudarTab('tornarAdmin')" id="tabTornarAdmin"
                                   class="px-4 py-3 text-gray-400 font-semibold border-b-2 border-transparent hover:text-white transition-colors">
                                   Tornar-se Administrador
                              </button>
                         <?php endif; ?>
                    </div>
               </div>

               <div id="contentDadosPessoais" class="p-6">
                    <form method="POST" action="">
                         <input type="hidden" name="action" value="atualizar_perfil">
                         <input type="hidden" name="cpf" value="<?php echo htmlspecialchars($usuario['CPF']); ?>">

                         <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                              <i data-lucide="edit-3" class="w-5 h-5 text-cyan-400"></i>
                              Dados Pessoais
                         </h3>

                         <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                              <div>
                                   <label class="block text-gray-400 text-sm font-semibold mb-2">Nome Completo *</label>
                                   <input type="text" name="nome" required
                                        value="<?php echo htmlspecialchars($usuario['NOME']); ?>"
                                        class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
                              </div>

                              <div>
                                   <label class="block text-gray-400 text-sm font-semibold mb-2">CPF *</label>
                                   <div class="relative">
                                        <input type="text" value="<?php echo htmlspecialchars($usuario['CPF']); ?>"
                                             readonly disabled
                                             class="w-full px-4 py-3 bg-slate-900/30 border border-cyan-500/10 rounded-xl text-gray-500 cursor-not-allowed focus:outline-none">
                                        <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                             <i data-lucide="lock" class="w-4 h-4 text-gray-600"></i>
                                        </div>
                                   </div>
                                   <p class="text-xs text-gray-500 mt-1">Não pode ser alterado por segurança</p>
                              </div>

                              <div class="md:col-span-2">
                                   <label class="block text-gray-400 text-sm font-semibold mb-2">Email *</label>
                                   <input type="email" name="email" required
                                        value="<?php echo htmlspecialchars($usuario['EMAIL']); ?>"
                                        class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
                              </div>
                         </div>

                         <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2 mt-8">
                              <i data-lucide="map-pin" class="w-5 h-5 text-cyan-400"></i>
                              Endereço
                         </h3>

                         <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                              <div class="md:col-span-2">
                                   <label class="block text-gray-400 text-sm font-semibold mb-2">CEP</label>
                                   <input type="text" name="cep_perfil" id="cep_perfil" placeholder="Ex: 13610-100"
                                        maxlength="9"
                                        value="<?php echo htmlspecialchars($usuario['CEP_USUARIO'] ?? $usuario['CEP_TABELA'] ?? ''); ?>"
                                        class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
                                   <div id="loadingCep_perfil" class="loading-container">
                                        <div class="spinner"></div>
                                        <span>Buscando endereço...</span>
                                   </div>
                                   <div id="cepError_perfil" class="cep-error">
                                        ⚠ Erro ao buscar CEP. Preencha manualmente.
                                   </div>
                              </div>

                              <div>
                                   <label class="block text-gray-400 text-sm font-semibold mb-2">UF</label>
                                   <div class="relative">
                                        <input type="text" name="uf_perfil" id="uf_perfil"
                                             value="<?php echo htmlspecialchars($usuario['UF'] ?? ''); ?>"
                                             placeholder="Ex: SP" maxlength="2" readonly
                                             class="w-full px-4 py-3 bg-slate-900/30 border border-cyan-500/10 rounded-xl text-gray-500 cursor-not-allowed focus:outline-none uppercase">
                                        <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                             <i data-lucide="lock" class="w-4 h-4 text-gray-600"></i>
                                        </div>
                                   </div>
                              </div>

                              <div class="md:col-span-2">
                                   <label class="block text-gray-400 text-sm font-semibold mb-2">Logradouro</label>
                                   <div class="relative">
                                        <input type="text" name="logradouro_perfil" id="logradouro_perfil"
                                             value="<?php echo htmlspecialchars($usuario['LOGRADOURO'] ?? ''); ?>"
                                             placeholder="Ex: Av. Paulista" readonly
                                             class="w-full px-4 py-3 bg-slate-900/30 border border-cyan-500/10 rounded-xl text-gray-500 cursor-not-allowed focus:outline-none">
                                        <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                             <i data-lucide="lock" class="w-4 h-4 text-gray-600"></i>
                                        </div>
                                   </div>
                              </div>

                              <div>
                                   <label class="block text-gray-400 text-sm font-semibold mb-2">Número</label>
                                   <input type="text" name="numero_residencia" id="numero_residencia_perfil"
                                        value="<?php echo htmlspecialchars($usuario['NUMERO_RESIDENCIA'] ?? ''); ?>"
                                        oninput="this.value = this.value.replace(/\D/g, '')" inputmode="numeric"
                                        placeholder="Ex: 123"
                                        class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
                              </div>

                              <div>
                                   <label class="block text-gray-400 text-sm font-semibold mb-2">Bairro</label>
                                   <div class="relative">
                                        <input type="text" name="bairro_perfil" id="bairro_perfil"
                                             value="<?php echo htmlspecialchars($usuario['bairro'] ?? ''); ?>"
                                             placeholder="Ex: Bela Vista" readonly
                                             class="w-full px-4 py-3 bg-slate-900/30 border border-cyan-500/10 rounded-xl text-gray-500 cursor-not-allowed focus:outline-none">
                                        <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                             <i data-lucide="lock" class="w-4 h-4 text-gray-600"></i>
                                        </div>
                                   </div>
                              </div>

                              <div>
                                   <label class="block text-gray-400 text-sm font-semibold mb-2">Cidade</label>
                                   <div class="relative">
                                        <input type="text" name="cidade_perfil" id="cidade_perfil"
                                             value="<?php echo htmlspecialchars($usuario['cidade'] ?? ''); ?>"
                                             placeholder="Ex: São Paulo" readonly
                                             class="w-full px-4 py-3 bg-slate-900/30 border border-cyan-500/10 rounded-xl text-gray-500 cursor-not-allowed focus:outline-none">
                                        <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                             <i data-lucide="lock" class="w-4 h-4 text-gray-600"></i>
                                        </div>
                                   </div>
                              </div>

                              <div>
                                   <label class="block text-gray-400 text-sm font-semibold mb-2">Complemento</label>
                                   <input type="text" name="complemento" id="complemento_perfil"
                                        value="<?php echo htmlspecialchars($usuario['COMPLEMENTO_ENDERECO'] ?? ''); ?>"
                                        placeholder="Ex: Apto 10"
                                        class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
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
                                   <input type="password" name="nova_senha" required minlength="6"
                                        class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
                              </div>

                              <div>
                                   <label class="block text-gray-400 text-sm font-semibold mb-2">Confirmar Nova Senha
                                        *</label>
                                   <input type="password" name="confirmar_senha" required minlength="6"
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

               <?php if ($usuario['TIPO_USUARIO'] == 0): ?>
                    <div id="contentTornarAdmin" class="p-6 hidden">
                         <div
                              class="bg-gradient-to-br from-purple-500/10 to-pink-500/10 border border-purple-500/30 rounded-xl p-6 mb-6">
                              <div class="flex items-start gap-4">
                                   <div
                                        class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center flex-shrink-0">
                                        <i data-lucide="alert-triangle" class="w-6 h-6 text-purple-400"></i>
                                   </div>
                                   <div>
                                        <h3 class="text-lg font-bold text-white mb-2">⚠️ Atenção: Ação Irreversível</h3>
                                        <p class="text-gray-300 mb-3">
                                             Ao se tornar um administrador, você terá acesso a funcionalidades avançadas de
                                             gerenciamento de pontos de recarga.
                                        </p>
                                        <p class="text-red-400 font-semibold">
                                             Esta ação NÃO PODE SER REVERTIDA. Você não poderá voltar a ser um usuário comum.
                                        </p>
                                   </div>
                              </div>
                         </div>

                         <form method="POST" action="" onsubmit="return confirmarAdmin()">
                              <input type="hidden" name="action" value="tornar_admin">
                              <input type="hidden" name="captcha_correto" value="<?php echo $captcha_resultado; ?>">

                              <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                                   <i data-lucide="shield-check" class="w-5 h-5 text-cyan-400"></i>
                                   Desafio de Verificação
                              </h3>

                              <div class="bg-slate-900/50 border border-cyan-500/20 rounded-xl p-6 mb-6">
                                   <label class="block text-gray-400 text-sm font-semibold mb-3">Resolva a operação
                                        matemática:</label>
                                   <div class="flex items-center gap-4 mb-4">
                                        <div
                                             class="text-3xl font-bold text-white bg-gradient-to-br from-cyan-500 to-blue-500 px-6 py-3 rounded-lg">
                                             <?php echo $num1; ?> + <?php echo $num2; ?> = ?
                                        </div>
                                   </div>
                                   <input type="number" name="captcha_resposta" required placeholder="Digite sua resposta"
                                        class="w-full px-4 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
                              </div>

                              <button type="submit"
                                   class="w-full bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg shadow-purple-500/30">
                                   <i data-lucide="shield-check" class="w-5 h-5 inline-block mr-2"></i>
                                   Tornar-me Administrador
                              </button>
                         </form>
                    </div>
               <?php endif; ?>

          </div>
     </main>

     <script>
          lucide.createIcons();

          function mudarTab(tab) {
               // Esconder todos os conteúdos
               document.getElementById('contentDadosPessoais').classList.add('hidden');
               document.getElementById('contentAlterarSenha').classList.add('hidden');
               <?php if ($usuario['TIPO_USUARIO'] == 0): ?>
                    document.getElementById('contentTornarAdmin').classList.add('hidden');
               <?php endif; ?>

               // Remover estilo ativo de todas as tabs
               document.getElementById('tabDadosPessoais').classList.remove('border-cyan-500', 'text-white');
               document.getElementById('tabDadosPessoais').classList.add('border-transparent', 'text-gray-400');
               document.getElementById('tabAlterarSenha').classList.remove('border-cyan-500', 'text-white');
               document.getElementById('tabAlterarSenha').classList.add('border-transparent', 'text-gray-400');
               <?php if ($usuario['TIPO_USUARIO'] == 0): ?>
                    document.getElementById('tabTornarAdmin').classList.remove('border-cyan-500', 'text-white');
                    document.getElementById('tabTornarAdmin').classList.add('border-transparent', 'text-gray-400');
               <?php endif; ?>

               // Mostrar conteúdo selecionado
               if (tab === 'dadosPessoais') {
                    document.getElementById('contentDadosPessoais').classList.remove('hidden');
                    document.getElementById('tabDadosPessoais').classList.add('border-cyan-500', 'text-white');
                    document.getElementById('tabDadosPessoais').classList.remove('border-transparent', 'text-gray-400');
               } else if (tab === 'alterarSenha') {
                    document.getElementById('contentAlterarSenha').classList.remove('hidden');
                    document.getElementById('tabAlterarSenha').classList.add('border-cyan-500', 'text-white');
                    document.getElementById('tabAlterarSenha').classList.remove('border-transparent', 'text-gray-400');
               } else if (tab === 'tornarAdmin') {
                    document.getElementById('contentTornarAdmin').classList.remove('hidden');
                    document.getElementById('tabTornarAdmin').classList.add('border-cyan-500', 'text-white');
                    document.getElementById('tabTornarAdmin').classList.remove('border-transparent', 'text-gray-400');
               }

               lucide.createIcons();
          }

          function confirmarAdmin() {
               return confirm('Você tem certeza de que deseja se tornar um administrador? Esta ação NÃO PODE ser revertida!');
          }

          // Função de busca de CEP
          async function buscarCEP(cep, contexto) {
               const cleanCep = cep.replace(/\D/g, '');

               if (cleanCep.length !== 8) {
                    mostrarErroCep(contexto, "CEP deve ter 8 dígitos");
                    return;
               }

               const loadingEl = document.getElementById(`loadingCep_${contexto}`);
               const errorEl = document.getElementById(`cepError_${contexto}`);

               loadingEl.classList.add('show');
               errorEl.classList.remove('show');

               try {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 10000);

                    const response = await fetch(`https://viacep.com.br/ws/${cleanCep}/json/`, {
                         signal: controller.signal
                    });

                    clearTimeout(timeoutId);

                    if (!response.ok) {
                         throw new Error("Erro na resposta da API");
                    }

                    const data = await response.json();
                    loadingEl.classList.remove('show');

                    if (data.erro) {
                         mostrarErroCep(contexto, "CEP não encontrado!");
                         limparCamposEndereco(contexto);
                         return;
                    }

                    const logradouro = data.logradouro || "";
                    const bairro = data.bairro || "";
                    const cidade = data.localidade || "";
                    const estado = data.uf || "";

                    if (contexto === 'perfil') {
                         document.getElementById('logradouro_perfil').value = logradouro;
                         document.getElementById('bairro_perfil').value = bairro;
                         document.getElementById('cidade_perfil').value = cidade;
                         document.getElementById('uf_perfil').value = estado;
                    }

                    if (!logradouro || !bairro) {
                         mostrarAvisoCep(contexto, "Alguns dados não foram encontrados. Complete manualmente.");
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

          function limparCamposEndereco(contexto) {
               if (contexto === 'perfil') {
                    document.getElementById('logradouro_perfil').value = "";
                    document.getElementById('bairro_perfil').value = "";
                    document.getElementById('cidade_perfil').value = "";
                    document.getElementById('uf_perfil').value = "";
               }
          }

          // Máscara de CEP e busca automática
          const cepPerfil = document.getElementById('cep_perfil');
          cepPerfil.addEventListener('input', () => {
               let value = cepPerfil.value.replace(/\D/g, '');
               if (value.length > 5) value = value.replace(/(\d{5})(\d)/, '$1-$2');
               cepPerfil.value = value;

               document.getElementById('cepError_perfil').classList.remove('show');

               if (value.length === 9) {
                    buscarCEP(value, 'perfil');
               }
          });

          // Inicializar - os dados já estão preenchidos pelo PHP
          document.addEventListener('DOMContentLoaded', function () {
               lucide.createIcons();
               mudarTab('dadosPessoais');
          });
     </script>
</body>

</html>