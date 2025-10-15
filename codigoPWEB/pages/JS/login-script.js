document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('loginForm');
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const emailInput = document.getElementById('email');
    const loginButton = document.querySelector('.login-button');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const forgotPasswordLink = document.querySelector('.forgot-password');
    const socialButtons = document.querySelectorAll('.social-button');

    let isLoading = false;

    // Adiciona o listener de evento ao formulário
    loginForm.addEventListener('submit', handleFormSubmit);

    // Adiciona o listener para o botão de mostrar/ocultar senha
    if (togglePassword) {
        togglePassword.addEventListener('click', handleTogglePassword);
    }

    // Adiciona o listener para o link "Esqueceu a senha"
    if (forgotPasswordLink) {
        forgotPasswordLink.addEventListener('click', handleForgotPassword);
    }

    // [NOVO] Adiciona listeners para os botões sociais (Google e GitHub)
    socialButtons.forEach(button => {
        button.addEventListener('click', handleSocialLogin);
    });

    // [NOVO] Função para lidar com login social
    function handleSocialLogin(e) {
        e.preventDefault();
        const platform = this.classList.contains('google') ? 'Google' : 'GitHub';
        showNotification(`Login com ${platform} está em produção. Em breve estará disponível!`, 'warning');
    }

    // Função para lidar com o clique em "Esqueceu a senha"
    function handleForgotPassword(e) {
        e.preventDefault();
        showNotification('Esta funcionalidade está em produção. Em breve estará disponível!', 'warning');
    }

    // Função principal que lida com o envio do formulário
    async function handleFormSubmit(e) {
        e.preventDefault();
        if (isLoading) return;

        const email = emailInput.value.trim();
        const password = passwordInput.value;

        if (!email || !password) {
            showNotification('Por favor, preencha todos os campos.', 'error');
            return;
        }

        try {
            showLoading();

            // 1. Faz a requisição para a nova API
            const response = await fetch("api_login.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ email: email, password: password })
            });

            // 2. Converte a resposta do servidor para JSON
            const resultData = await response.json();

            // 3. Verifica se a requisição foi bem-sucedida E se a API retornou sucesso
            if (response.ok && resultData.success) {
                showNotification('Login realizado com sucesso! Redirecionando...', 'success');

                // 4. Redireciona para a URL que o back-end enviou
                setTimeout(() => {
                    window.location.href = resultData.redirectUrl;
                }, 1200);

            } else {
                throw new Error(resultData.message || 'Erro ao fazer login');
            }

        } catch (error) {
            hideLoading();
            showNotification(error.message, 'error');

            // Animação de "shake" para feedback visual
            const loginCard = document.querySelector('.login-card');
            loginCard.style.animation = 'shake 0.5s ease-in-out';
            setTimeout(() => {
                loginCard.style.animation = '';
            }, 500);
        }
    }

    // --- Funções Auxiliares ---

    function handleTogglePassword() {
        const currentType = passwordInput.getAttribute('type');
        const newType = currentType === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', newType);
        togglePassword.textContent = newType === 'password' ? '👁️' : '🙈';
    }

    function showLoading() {
        isLoading = true;
        loadingOverlay.classList.add('active');
        loginButton.disabled = true;
        loginButton.innerHTML = '<span class="button-text">Entrando...</span>';
    }

    function hideLoading() {
        isLoading = false;
        loadingOverlay.classList.remove('active');
        loginButton.disabled = false;
        loginButton.innerHTML = '<span class="button-text">Entrar</span><i class="fas fa-arrow-right button-icon"></i>';
    }

    function showNotification(message, type = 'info') {
        const existingNotification = document.querySelector('.notification');
        if (existingNotification) existingNotification.remove();

        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `<div class="notification-content"><i class="fas ${getNotificationIcon(type)}"></i><span>${message}</span><button class="notification-close"><i class="fas fa-times"></i></button></div>`;

        document.body.appendChild(notification);
        setTimeout(() => notification.classList.add('show'), 10);

        const autoRemove = setTimeout(() => hideNotification(notification), 5000);

        notification.querySelector('.notification-close').addEventListener('click', () => {
            clearTimeout(autoRemove);
            hideNotification(notification);
        });
    }

    function getNotificationIcon(type) {
        const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
        return icons[type] || icons.info;
    }

    function hideNotification(notification) {
        if (!notification) return;
        notification.classList.add('hide');
        setTimeout(() => { if (notification.parentNode) notification.parentNode.removeChild(notification); }, 300);
    }

    // Adiciona os estilos da notificação
    const notificationStyles = document.createElement('style');
    notificationStyles.textContent = `@keyframes shake { 0%, 100% { transform: translateX(0); } 10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); } 20%, 40%, 60%, 80% { transform: translateX(5px); } } .notification { position: fixed; top: 20px; right: 20px; z-index: 10000; min-width: 300px; padding: 1rem; border-radius: 12px; color: white; font-weight: 500; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); transform: translateX(100%); transition: all 0.3s ease; border-left: 4px solid; } .notification.show { transform: translateX(0); } .notification.hide { transform: translateX(100%); opacity: 0; } .notification.success { background: rgba(40, 167, 69, 0.9); border-left-color: #28a745; } .notification.error { background: rgba(220, 53, 69, 0.9); border-left-color: #dc3545; } .notification.warning { background: rgba(255, 193, 7, 0.9); border-left-color: #ffc107; color: #333; } .notification-content { display: flex; align-items: center; gap: 0.5rem; } .notification-close { background: none; border: none; color: inherit; cursor: pointer; }`;
    document.head.appendChild(notificationStyles);
});