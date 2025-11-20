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
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        /* ESTILO CUSTOMIZADO DO BOTÃO GOOGLE - INSPIRADO NO GITHUB */
        .social-buttons {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 25px;
        }

        .custom-google-button {
            width: 100%;
            height: 48px;
            background-color: #1a1a1a;
            border: 1px solid #3d3d3d;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 14px;
            font-weight: 500;
            color: #ffffff;
            padding: 0 16px;
        }

        .custom-google-button:hover {
            background-color: #2a2a2a;
            border-color: #4d4d4d;
        }

        .custom-google-button:active {
            background-color: #0a0a0a;
            transform: scale(0.98);
        }

        .google-icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .google-icon svg {
            width: 100%;
            height: 100%;
        }

        /* Esconde o botão original do Google */
        #google-login-button {
            display: none !important;
        }
    </style>
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
                    <!-- Botão customizado visível -->
                    <button type="button" class="custom-google-button" id="customGoogleButton">
                        <span class="google-icon">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                            </svg>
                        </span>
                        <span>Google</span>
                    </button>
                    
                    <!-- Botão original do Google (escondido mas funcional) -->
                    <div id="google-login-button"></div>
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
        // Função de callback que será chamada pelo Google após o login
        function handleCredentialResponse(response) {
            const idToken = response.credential;
            
            // Redireciona o ID Token para o script PHP que irá processá-lo
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../PHP/login_google.php'; 
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'id_token';
            input.value = idToken;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }

        // Função para inicializar o Google Sign-In
        function initializeGoogleSignIn() {
            // Verifica se o objeto google está disponível
            if (typeof google === 'undefined' || !google.accounts) {
                console.log('Aguardando carregamento do Google Sign-In...');
                setTimeout(initializeGoogleSignIn, 100);
                return;
            }

            google.accounts.id.initialize({
                client_id: "915868671198-mipp6tk42ikmq945t7q48vnphejiet96.apps.googleusercontent.com",
                callback: handleCredentialResponse, 
                auto_select: false,
            });

            // Renderiza o botão original (escondido) para manter a funcionalidade
            google.accounts.id.renderButton(
                document.getElementById("google-login-button"),
                { 
                    theme: "filled_blue",
                    size: "large", 
                    text: "continue_with", 
                    locale: "pt_BR"
                }
            );

            // Conecta o botão customizado ao botão real do Google
            document.getElementById('customGoogleButton').addEventListener('click', function(e) {
                e.preventDefault();
                // Procura e clica no botão real do Google que está escondido
                const googleButton = document.querySelector('#google-login-button [role="button"]');
                if (googleButton) {
                    googleButton.click();
                } else {
                    // Fallback: usa o método prompt do Google
                    google.accounts.id.prompt();
                }
            });
        }

        // Inicializa quando a página carregar
        window.onload = function () {
            // Inicia o processo de inicialização do Google
            initializeGoogleSignIn();
            
            // Hash de senhas
            fetch('hash_senhas_auto.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            }).catch(error => {
                console.log('');
            });
        };
    </script>
</body>

</html>