// Toggle password visibility
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');

if (togglePassword) {
    togglePassword.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.textContent = type === 'password' ? '👁️' : '🙈';
    });
}

// Login form submission
const loginForm = document.getElementById('loginForm');
const loadingOverlay = document.getElementById('loadingOverlay');

if (loginForm) {
    loginForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;

        // Mostra loading
        loadingOverlay.style.display = 'flex';

        try {
            const response = await fetch('../PHP/api_login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    password: password
                })
            });

            const data = await response.json();

            if (data.success) {
                // Login bem-sucedido. Usamos a mensagem do PHP ('Login bem-sucedido!')
                showNotification(data.message, 'success');
                setTimeout(() => {
                    window.location.href = '../PHP/' + data.redirectUrl;
                }, 1000);
            } else {
                // Login falhou. Usamos a mensagem do PHP ('E-mail ou senha inválidos!')
                loadingOverlay.style.display = 'none';
                showNotification(data.message || 'Erro ao fazer login. Tente novamente.', 'error');
            }

        } catch (error) {
            console.error('Erro:', error);
            loadingOverlay.style.display = 'none';
            showNotification('Erro de conexão. Tente novamente.', 'error');
        }
    });
}

// Função para mostrar notificações
function showNotification(message, type) {
    // Remove notificação anterior se existir
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }

    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
    `;

    document.body.appendChild(notification);

    // Remove após 5 segundos
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// ==================== RECUPERAÇÃO DE SENHA ====================

function abrirModalRecuperacao(e) {
    e.preventDefault();

    // Cria o modal se não existir
    if (!document.getElementById('modalRecuperacao')) {
        const modal = document.createElement('div');
        modal.id = 'modalRecuperacao';
        modal.className = 'modal-recuperacao';
        modal.innerHTML = `
            <div class="modal-recuperacao-content">
                <div class="modal-recuperacao-header">
                    <h2>
                        <i class="fas fa-key"></i>
                        Recuperar Senha
                    </h2>
                    <button class="modal-close" onclick="fecharModalRecuperacao()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="modal-recuperacao-body">
                    <p class="modal-description">
                        Digite seu e-mail cadastrado e enviaremos um link para redefinir sua senha.
                    </p>
                    
                    <form id="formRecuperacao" onsubmit="enviarRecuperacao(event)">
                        <div class="form-group">
                            <div class="input-wrapper">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email_recuperacao" name="email" placeholder=" " required>
                                <label for="email_recuperacao">E-mail</label>
                            </div>
                        </div>
                        
                        <div id="mensagemRecuperacao" class="mensagem-recuperacao" style="display: none;"></div>
                        
                        <button type="submit" class="btn-recuperacao">
                            <i class="fas fa-paper-plane"></i>
                            <span>Enviar Link de Recuperação</span>
                        </button>
                    </form>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Adiciona estilos do modal. (NOTA: Esta função também carrega os estilos de notificação)
        adicionarEstilosModal();
    }

    // Mostra o modal
    document.getElementById('modalRecuperacao').style.display = 'flex';

    // Limpa o formulário e mensagens
    document.getElementById('formRecuperacao').reset();
    const mensagem = document.getElementById('mensagemRecuperacao');
    mensagem.style.display = 'none';
}

function fecharModalRecuperacao() {
    const modal = document.getElementById('modalRecuperacao');
    if (modal) {
        modal.style.display = 'none';
    }
}

async function enviarRecuperacao(e) {
    e.preventDefault();

    const email = document.getElementById('email_recuperacao').value;
    const btnSubmit = e.target.querySelector('button[type="submit"]');
    const mensagemDiv = document.getElementById('mensagemRecuperacao');

    // Desabilita botão
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

    try {
        const response = await fetch('../PHP/api_solicitar_recuperacao.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email: email })
        });

        const data = await response.json();

        // Mostra mensagem
        mensagemDiv.style.display = 'block';
        mensagemDiv.className = `mensagem-recuperacao ${data.success ? 'success' : 'error'}`;
        mensagemDiv.innerHTML = `
            <i class="fas fa-${data.success ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${data.message}</span>
        `;

        if (data.success) {
            // Limpa o formulário
            document.getElementById('formRecuperacao').reset();

            // Fecha o modal após 5 segundos
            setTimeout(() => {
                fecharModalRecuperacao();
            }, 5000);
        }

    } catch (error) {
        console.error('Erro:', error);
        mensagemDiv.style.display = 'block';
        mensagemDiv.className = 'mensagem-recuperacao error';
        mensagemDiv.innerHTML = `
            <i class="fas fa-exclamation-circle"></i>
            <span>Erro de conexão. Tente novamente.</span>
        `;
    } finally {
        // Reabilita botão
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Link de Recuperação';
    }
}

function adicionarEstilosModal() {
    if (document.getElementById('estilos-modal-recuperacao')) return;

    const style = document.createElement('style');
    style.id = 'estilos-modal-recuperacao';
    style.textContent = `
        .modal-recuperacao {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-recuperacao-content {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.95));
            border: 1px solid rgba(6, 182, 212, 0.2);
            border-radius: 24px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.3s ease;
        }
        
        .modal-recuperacao-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px;
            border-bottom: 1px solid rgba(6, 182, 212, 0.2);
        }
        
        .modal-recuperacao-header h2 {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 24px;
            font-weight: 700;
            color: white;
            margin: 0;
        }
        
        .modal-recuperacao-header i {
            color: #06b6d4;
        }
        
        .modal-close {
            background: rgba(100, 116, 139, 0.2);
            border: 1px solid rgba(100, 116, 139, 0.3);
            color: #94a3b8;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .modal-close:hover:not(:disabled) {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }
        
        .modal-recuperacao-body {
            padding: 24px;
        }
        
        .modal-description {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
            text-align: center;
        }
        
        /* ADICIONADA MARGEM PARA SEPARAR DO BOTÃO */
        .form-group {
            margin-bottom: 24px; 
        }

        .mensagem-recuperacao {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px; /* AJUSTADO PARA DAR MAIS ESPAÇO */
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            animation: slideDown 0.3s ease;
        }
        
        .mensagem-recuperacao.success {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #22c55e;
        }
        
        .mensagem-recuperacao.error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }
        
        .mensagem-recuperacao i {
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .btn-recuperacao {
            width: 100%;
            background: linear-gradient(135deg, #06b6d4, #3b82f6);
            color: white;
            font-weight: 700;
            padding: 16px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px -5px rgba(6, 182, 212, 0.3);
        }
        
        .btn-recuperacao:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(6, 182, 212, 0.4);
        }
        
        .btn-recuperacao:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* Estilo para notificações */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10001;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease;
            max-width: 400px;
        }
        
        .notification.success {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.95), rgba(22, 163, 74, 0.95));
            border: 1px solid rgba(34, 197, 94, 0.5);
        }
        
        .notification.error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.95), rgba(220, 38, 38, 0.95));
            border: 1px solid rgba(239, 68, 68, 0.5);
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            font-weight: 600;
        }
        
        .notification-content i {
            font-size: 20px;
        }
        
        .notification.fade-out {
            animation: fadeOut 0.3s ease forwards;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }
        
        /* Fecha modal ao clicar fora */
        @media (max-width: 640px) {
            .modal-recuperacao-content {
                margin: 20px;
            }
            
            .modal-recuperacao-header h2 {
                font-size: 20px;
            }
        }
    `;

    document.head.appendChild(style);
}

// *** NOVA LINHA: Carrega os estilos assim que o script é executado ***
adicionarEstilosModal();

// Fecha modal ao clicar fora
document.addEventListener('click', function (e) {
    const modal = document.getElementById('modalRecuperacao');
    if (modal && e.target === modal) {
        fecharModalRecuperacao();
    }
});

// Fecha modal com tecla ESC
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        fecharModalRecuperacao();
    }
});