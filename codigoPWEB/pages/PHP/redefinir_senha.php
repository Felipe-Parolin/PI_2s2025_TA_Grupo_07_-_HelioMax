<?php
require_once 'conexao.php';

$token = $_GET['token'] ?? '';
$mensagem = '';
$tipo_mensagem = '';
$token_valido = false;

// Verifica se o token é válido
if ($token) {
    $stmt = $pdo->prepare("
        SELECT r.*, u.NOME, u.EMAIL 
        FROM recuperacao_senha r
        JOIN usuario u ON r.FK_ID_USUARIO = u.ID_USER
        WHERE r.TOKEN = ? 
        AND r.UTILIZADO = 0 
        AND r.DATA_EXPIRACAO > NOW()
    ");
    $stmt->execute([$token]);
    $recuperacao = $stmt->fetch();

    if ($recuperacao) {
        $token_valido = true;
    } else {
        $mensagem = 'Link de recuperação inválido ou expirado.';
        $tipo_mensagem = 'erro';
    }
} else {
    $mensagem = 'Token não fornecido.';
    $tipo_mensagem = 'erro';
}

// Processa a redefinição de senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    if (empty($nova_senha) || empty($confirmar_senha)) {
        $mensagem = 'Preencha todos os campos.';
        $tipo_mensagem = 'erro';
    } elseif (strlen($nova_senha) < 6) {
        $mensagem = 'A senha deve ter no mínimo 6 caracteres.';
        $tipo_mensagem = 'erro';
    } elseif ($nova_senha !== $confirmar_senha) {
        $mensagem = 'As senhas não conferem.';
        $tipo_mensagem = 'erro';
    } else {
        try {
            $pdo->beginTransaction();

            // Hash da nova senha
            $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

            // Atualiza a senha do usuário
            $stmt = $pdo->prepare("UPDATE usuario SET SENHA = ? WHERE ID_USER = ?");
            $stmt->execute([$senha_hash, $recuperacao['FK_ID_USUARIO']]);

            // Marca o token como utilizado
            $stmt = $pdo->prepare("UPDATE recuperacao_senha SET UTILIZADO = 1 WHERE TOKEN = ?");
            $stmt->execute([$token]);

            $pdo->commit();

            $mensagem = 'Senha redefinida com sucesso! Você será redirecionado para o login...';
            $tipo_mensagem = 'sucesso';
            $token_valido = false; // Impede novo envio do formulário

            // Redireciona após 3 segundos
            header("Refresh: 3; url=login.php");

        } catch (Exception $e) {
            $pdo->rollBack();
            $mensagem = 'Erro ao redefinir senha. Tente novamente.';
            $tipo_mensagem = 'erro';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../images/icon.png">
    <title>Redefinir Senha - HelioMax</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body
    class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 flex items-center justify-center p-4">

    <div class="max-w-md w-full">
        <div
            class="bg-gradient-to-br from-slate-800/95 to-slate-900/95 backdrop-blur-xl rounded-2xl border border-cyan-500/20 p-8 shadow-2xl">

            <div class="text-center mb-8">
                <div
                    class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-2xl mb-4">
                    <i data-lucide="key" class="w-8 h-8 text-white"></i>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">Redefinir Senha</h1>
                <p class="text-gray-400">Crie uma nova senha para sua conta</p>
            </div>

            <?php if ($mensagem): ?>
                <div
                    class="mb-6 p-4 <?php echo $tipo_mensagem === 'sucesso' ? 'bg-green-500/20 border-green-500/30' : 'bg-red-500/20 border-red-500/30'; ?> border rounded-xl flex items-center gap-3">
                    <i data-lucide="<?php echo $tipo_mensagem === 'sucesso' ? 'check-circle' : 'alert-circle'; ?>"
                        class="w-5 h-5 <?php echo $tipo_mensagem === 'sucesso' ? 'text-green-400' : 'text-red-400'; ?>"></i>
                    <p class="<?php echo $tipo_mensagem === 'sucesso' ? 'text-green-400' : 'text-red-400'; ?> text-sm">
                        <?php echo $mensagem; ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($token_valido): ?>
                <form method="POST" action="">
                    <div class="mb-6">
                        <p class="text-gray-300 text-sm mb-4">
                            Olá, <strong><?php echo htmlspecialchars($recuperacao['NOME']); ?></strong>!<br>
                            Digite sua nova senha abaixo.
                        </p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-400 text-sm font-semibold mb-2">Nova Senha</label>
                        <div class="relative">
                            <i data-lucide="lock"
                                class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                            <input type="password" name="nova_senha" id="nova_senha" required minlength="6"
                                class="w-full pl-12 pr-12 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
                            <button type="button" onclick="togglePassword('nova_senha')"
                                class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-cyan-400 transition-colors">
                                <i data-lucide="eye" class="w-5 h-5"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-gray-400 text-sm font-semibold mb-2">Confirmar Nova Senha</label>
                        <div class="relative">
                            <i data-lucide="lock"
                                class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                            <input type="password" name="confirmar_senha" id="confirmar_senha" required minlength="6"
                                class="w-full pl-12 pr-12 py-3 bg-slate-900/50 border border-cyan-500/20 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 transition-colors">
                            <button type="button" onclick="togglePassword('confirmar_senha')"
                                class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-cyan-400 transition-colors">
                                <i data-lucide="eye" class="w-5 h-5"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg shadow-cyan-500/30 flex items-center justify-center gap-2">
                        <i data-lucide="check" class="w-5 h-5"></i>
                        <span>Redefinir Senha</span>
                    </button>
                </form>
            <?php else: ?>
                <div class="text-center">
                    <a href="login.php"
                        class="inline-flex items-center gap-2 text-cyan-400 hover:text-cyan-300 transition-colors">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Voltar para o login</span>
                    </a>
                </div>
            <?php endif; ?>

        </div>

        <div class="text-center mt-6">
            <p class="text-gray-400 text-sm">
                Precisa de ajuda? <a href="#" class="text-cyan-400 hover:text-cyan-300 transition-colors">Entre em
                    contato</a>
            </p>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = event.currentTarget.querySelector('i');

            if (field.type === 'password') {
                field.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                field.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }

            lucide.createIcons();
        }

        // Validação em tempo real
        const novaSenha = document.getElementById('nova_senha');
        const confirmarSenha = document.getElementById('confirmar_senha');

        confirmarSenha?.addEventListener('input', function () {
            if (this.value && novaSenha.value !== this.value) {
                this.setCustomValidity('As senhas não conferem');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>

</html>