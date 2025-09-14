document.addEventListener('DOMContentLoaded', function() {
    // Elementos do DOM
    const loginForm = document.getElementById('loginForm');
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const emailInput = document.getElementById('email');
    const loginButton = document.querySelector('.login-button');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const socialButtons = document.querySelectorAll('.social-button');

    // Estado da aplicação
    let isLoading = false;

    // Inicialização
    init();

    function init() {
        setupEventListeners();
        setupAnimations();
        validateFormOnLoad();
    }

    // Event Listeners
    function setupEventListeners() {
        // Form submission
        loginForm.addEventListener('submit', handleFormSubmit);
        
        // Toggle password visibility
        togglePassword.addEventListener('click', handleTogglePassword);
        
        // Input validation em tempo real
        emailInput.addEventListener('input', validateEmail);
        passwordInput.addEventListener('input', validatePassword);
        
        // Social login buttons
        socialButtons.forEach(button => {
            button.addEventListener('click', handleSocialLogin);
        });

        // Forgot password link
        const forgotPasswordLink = document.querySelector('.forgot-password');
        forgotPasswordLink.addEventListener('click', handleForgotPassword);

        // Signup link
        const signupLink = document.querySelector('.signup-link a');
        signupLink.addEventListener('click', handleSignupRedirect);

        // Esc key para fechar loading se necessário
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isLoading) {
                hideLoading();
            }
        });
    }

    // Animações de entrada
    function setupAnimations() {
        // Animar elementos do formulário
        const formElements = document.querySelectorAll('.form-group, .form-options, .login-button, .divider, .social-buttons, .signup-link');
        
        formElements.forEach((element, index) => {
            element.style.animationPlayState = 'running';
        });

        // Hover effects nos cards flutuantes
        const infoCards = document.querySelectorAll('.info-card');
        infoCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.05)';
                this.style.borderColor = 'rgba(93, 173, 226, 0.6)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = '';
                this.style.borderColor = 'rgba(93, 173, 226, 0.3)';
            });
        });
    }

    // Validação do formulário
    function validateFormOnLoad() {
        validateEmail();
        validatePassword();
    }

    function validateEmail() {
        const email = emailInput.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email === '') {
            setInputState(emailInput, 'neutral');
            return false;
        } else if (emailRegex.test(email)) {
            setInputState(emailInput, 'valid');
            return true;
        } else {
            setInputState(emailInput, 'invalid');
            return false;
        }
    }

    function validatePassword() {
        const password = passwordInput.value;
        
        if (password === '') {
            setInputState(passwordInput, 'neutral');
            return false;
        } else if (password.length >= 6) {
            setInputState(passwordInput, 'valid');
            return true;
        } else {
            setInputState(passwordInput, 'invalid');
            return false;
        }
    }

    function setInputState(input, state) {
        const wrapper = input.closest('.input-wrapper');
        
        // Remove classes anteriores
        wrapper.classList.remove('valid', 'invalid');
        
        if (state === 'valid') {
            wrapper.classList.add('valid');
            wrapper.style.borderColor = '#28a745';
            wrapper.style.boxShadow = '0 0 0 3px rgba(40, 167, 69, 0.1)';
        } else if (state === 'invalid') {
            wrapper.classList.add('invalid');
            wrapper.style.borderColor = '#dc3545';
            wrapper.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
        } else {
            wrapper.style.borderColor = '';
            wrapper.style.boxShadow = '';
        }
    }

    // Manipulador do envio do formulário
    async function handleFormSubmit(e) {
        e.preventDefault();
        
        if (isLoading) return;

        const email = emailInput.value.trim();
        const password = passwordInput.value;
        const remember = document.getElementById('remember').checked;

        // Validação final
        if (!validateEmail() || !validatePassword()) {
            showNotification('Por favor, preencha os campos corretamente.', 'error');
            return;
        }

        try {
            showLoading();
            
            // Simular chamada para API (substitua pela sua lógica real)
            const loginData = await simulateLogin(email, password, remember);
            
            if (loginData.success) {
                showNotification('Login realizado com sucesso!', 'success');
                
                // Redirecionar após 1.5 segundos
                setTimeout(() => {
                    // window.location.href = '/dashboard';
                    console.log('Redirecionando para dashboard...');
                    hideLoading();
                }, 1500);
                
            } else {
                throw new Error(loginData.message || 'Erro ao fazer login');
            }
            
        } catch (error) {
            hideLoading();
            showNotification(error.message || 'Erro interno do servidor', 'error');
            
            // Shake animation no card
            const loginCard = document.querySelector('.login-card');
            loginCard.style.animation = 'shake 0.5s ease-in-out';
            setTimeout(() => {
                loginCard.style.animation = '';
            }, 500);
        }
    }

    // Simulação de login (substitua pela sua API real)
    async function simulateLogin(email, password, remember) {
        return new Promise((resolve, reject) => {
            setTimeout(() => {
                // Simular diferentes cenários de login
                if (email === 'admin@test.com' && password === '123456') {
                    resolve({
                        success: true,
                        token: 'fake-jwt-token',
                        user: { name: 'Admin User', email: email }
                    });
                } else if (email === 'user@test.com' && password === 'password') {
                    resolve({
                        success: true,
                        token: 'fake-jwt-token',
                        user: { name: 'Test User', email: email }
                    });
                } else {
                    reject(new Error('Email ou senha incorretos'));
                }
            }, 2000); // Simular delay da rede
        });
    }

    // Toggle password visibility
    function handleTogglePassword() {
        const currentType = passwordInput.getAttribute('type');
        const newType = currentType === 'password' ? 'text' : 'password';
        
        passwordInput.setAttribute('type', newType);
        
        // Usar emojis simples
        if (newType === 'password') {
            togglePassword.textContent = '👁️';
        } else {
            togglePassword.textContent = '🙈';
        }
        
        // Debug
        console.log('Password visibility toggled:', newType);
    }

    // Social login
    function handleSocialLogin(e) {
        e.preventDefault();
        const provider = e.currentTarget.classList.contains('google') ? 'Google' : 'GitHub';
        
        showNotification(`Redirecionando para login com ${provider}...`, 'info');
        
        // Simular redirecionamento para OAuth
        setTimeout(() => {
            console.log(`Iniciando login com ${provider}`);
            // window.location.href = `/auth/${provider.toLowerCase()}`;
        }, 1000);
    }

    // Forgot password
    function handleForgotPassword(e) {
        e.preventDefault();
        
        const email = emailInput.value.trim();
        if (email && validateEmail()) {
            showNotification(`Link de recuperação enviado para ${email}`, 'success');
        } else {
            showNotification('Por favor, insira um email válido primeiro.', 'warning');
            emailInput.focus();
        }
    }

    // Signup redirect
    function handleSignupRedirect(e) {
        e.preventDefault();
        showNotification('Redirecionando para página de cadastro...', 'info');
        setTimeout(() => {
            // window.location.href = '/signup';
            console.log('Redirecionando para signup...');
        }, 1000);
    }

    // Loading overlay
    function showLoading() {
        isLoading = true;
        loadingOverlay.classList.add('active');
        loginButton.disabled = true;
        loginButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Entrando...';
    }

    function hideLoading() {
        isLoading = false;
        loadingOverlay.classList.remove('active');
        loginButton.disabled = false;
        loginButton.innerHTML = '<span class="button-text">Entrar</span><i class="fas fa-arrow-right button-icon"></i>';
    }

    // Sistema de notificações
    function showNotification(message, type = 'info') {
        // Remove notificação anterior se existir
        const existingNotification = document.querySelector('.notification');
        if (existingNotification) {
            existingNotification.remove();
        }

        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas ${getNotificationIcon(type)}"></i>
                <span>${message}</span>
                <button class="notification-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        document.body.appendChild(notification);

        // Animar entrada
        setTimeout(() => notification.classList.add('show'), 10);

        // Auto remove após 5 segundos
        const autoRemove = setTimeout(() => {
            hideNotification(notification);
        }, 5000);

        // Close button
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            clearTimeout(autoRemove);
            hideNotification(notification);
        });
    }

    function getNotificationIcon(type) {
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        return icons[type] || icons.info;
    }

    function hideNotification(notification) {
        notification.classList.add('hide');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    // Atalhos de teclado
    document.addEventListener('keydown', function(e) {
        // Enter para submeter o form quando focado em qualquer input
        if (e.key === 'Enter' && (e.target === emailInput || e.target === passwordInput)) {
            e.preventDefault();
            loginForm.dispatchEvent(new Event('submit'));
        }
        
        // Tab navigation melhorado
        if (e.key === 'Tab') {
            const focusableElements = loginForm.querySelectorAll(
                'input:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            if (e.shiftKey && document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            } else if (!e.shiftKey && document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }
    });

    // Persistir email no localStorage se "Lembrar de mim" estiver marcado
    const rememberCheckbox = document.getElementById('remember');
    
    // Carregar email salvo
    const savedEmail = localStorage.getItem('rememberedEmail');
    if (savedEmail) {
        emailInput.value = savedEmail;
        rememberCheckbox.checked = true;
        validateEmail();
    }

    // Salvar/remover email baseado no checkbox
    rememberCheckbox.addEventListener('change', function() {
        if (this.checked && emailInput.value.trim()) {
            localStorage.setItem('rememberedEmail', emailInput.value.trim());
        } else {
            localStorage.removeItem('rememberedEmail');
        }
    });

    // Salvar email quando o usuário digita (se remember estiver marcado)
    emailInput.addEventListener('input', function() {
        if (rememberCheckbox.checked && this.value.trim()) {
            localStorage.setItem('rememberedEmail', this.value.trim());
        }
    });

    // Animação de partículas no background (opcional)
    function createParticles() {
        const particleContainer = document.createElement('div');
        particleContainer.className = 'particles';
        particleContainer.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        `;
        
        document.body.insertBefore(particleContainer, document.body.firstChild);

        for (let i = 0; i < 50; i++) {
            setTimeout(() => {
                createParticle(particleContainer);
            }, i * 100);
        }
    }

    function createParticle(container) {
        const particle = document.createElement('div');
        particle.style.cssText = `
            position: absolute;
            width: 2px;
            height: 2px;
            background: rgba(93, 173, 226, 0.3);
            border-radius: 50%;
            pointer-events: none;
            animation: particleFloat ${Math.random() * 10 + 10}s linear infinite;
        `;
        
        particle.style.left = Math.random() * 100 + '%';
        particle.style.top = '100%';
        
        container.appendChild(particle);
        
        // Remove particle after animation
        setTimeout(() => {
            if (particle.parentNode) {
                particle.parentNode.removeChild(particle);
            }
        }, 20000);
    }

    // Adicionar CSS das partículas
    const particleStyles = document.createElement('style');
    particleStyles.textContent = `
        @keyframes particleFloat {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            min-width: 300px;
            max-width: 500px;
            padding: 1rem;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transform: translateX(100%);
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.hide {
            transform: translateX(100%);
            opacity: 0;
        }
        
        .notification.success {
            background: rgba(40, 167, 69, 0.9);
            border-left-color: #28a745;
        }
        
        .notification.error {
            background: rgba(220, 53, 69, 0.9);
            border-left-color: #dc3545;
        }
        
        .notification.warning {
            background: rgba(255, 193, 7, 0.9);
            border-left-color: #ffc107;
            color: #212529;
        }
        
        .notification.info {
            background: rgba(93, 173, 226, 0.9);
            border-left-color: #5DADE2;
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .notification-content i:first-child {
            font-size: 1.2rem;
        }
        
        .notification-content span {
            flex: 1;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .notification-close:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        @media (max-width: 768px) {
            .notification {
                right: 10px;
                left: 10px;
                min-width: auto;
                transform: translateY(-100%);
            }
            
            .notification.show {
                transform: translateY(0);
            }
            
            .notification.hide {
                transform: translateY(-100%);
            }
        }
    `;
    
    document.head.appendChild(particleStyles);
    
    // Inicializar partículas (descomente se quiser o efeito)
    // createParticles();

    console.log('Sistema de login HelioMax inicializado com sucesso!');
    console.log('Credenciais de teste:');
    console.log('admin@heliomax.com / 123456');
    console.log('usuario@heliomax.com / solar123');
    console.log('demo@test.com / demo123');
});