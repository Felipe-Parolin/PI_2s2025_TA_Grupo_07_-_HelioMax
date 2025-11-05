<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../images/icon.png">
    <title>Login</title>
    <link rel="stylesheet" href="../../styles/login-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <a href="../HTML/landpage.html" class="logo-link">
                    <h1 class="logo">⚡HelioMax</h1>
                </a>
                <h2>Bem-vindo de volta</h2>
                <p>Faça login para continuar sua jornada</p>
            </div>

            <form class="login-form" id="loginForm">
                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder=" " required>
                        <label for="email">E-mail</label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-wrapper password-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder=" " required>
                        <label for="password">Senha</label>
                        <button type="button" class="toggle-password" id="togglePassword">
                            👁️
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" id="remember">
                        <span class="checkmark"></span>
                        Guardar Senha
                    </label>
                    <a href="#" class="forgot-password" onclick="abrirModalRecuperacao(event)">Esqueceu a senha?</a>
                </div>

                <button type="submit" class="login-button">
                    <span class="button-text">Entrar</span>
                    <i class="fas fa-arrow-right button-icon"></i>
                </button>

                <div class="divider">
                    <span>ou continue com</span>
                </div>

                <div class="social-buttons">
                    <button type="button" class="social-button google">
                        <i class="fab fa-google"></i>
                        Google
                    </button>
                    <button type="button" class="social-button github">
                        <i class="fab fa-github"></i>
                        GitHub
                    </button>
                </div>

                <div class="signup-link">
                    <p>Não tem uma conta? <a href="../HTML/register.html">Cadastre-se aqui</a></p>
                </div>
            </form>
        </div>

        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p>Fazendo login...</p>
            </div>
        </div>
    </div>

    <script src="../JS/login-script.js"></script>
    <script>
        // Executa o hash de senhas automaticamente ao carregar a página
        // Sem mostrar nenhuma mensagem ao usuário
        document.addEventListener('DOMContentLoaded', function () {
            fetch('hash_senhas_auto.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            }).catch(error => {
                // Silencia qualquer erro para não incomodar o usuário
                console.log('');
            });
        });
    </script>
</body>

</html>