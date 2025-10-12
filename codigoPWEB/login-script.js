document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const emailInput = document.getElementById('email');
    const loginButton = document.querySelector('.login-button');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const socialButtons = document.querySelectorAll('.social-button');
    const logo = document.querySelector('.logo');

    let isLoading = false;

    init();

    function init() {
        setupEventListeners();
        setupAnimations();
        validateFormOnLoad();
    }

    function setupEventListeners() {
        loginForm.addEventListener('submit', handleFormSubmit);
        
        if (togglePassword) {
            togglePassword.addEventListener('click', handleTogglePassword);
        }
        
        emailInput.addEventListener('input', validateEmail);
        passwordInput.addEventListener('input', validatePassword);
        
        socialButtons.forEach(button => {
            button.addEventListener('click', handleSocialLogin);
        });

        const forgotPasswordLink = document.querySelector('.forgot-password');
        if (forgotPasswordLink) {
            forgotPasswordLink.addEventListener('click', handleForgotPassword);
        }

        const signupLink = document.querySelector('.signup-link a');
        if (signupLink) {
            signupLink.addEventListener('click', handleSignupRedirect);
        }

        if (logo) {
            logo.style.cursor = 'pointer';
            logo.addEventListener('click', handleLogoRedirect);
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isLoading) {
                hideLoading();
            }
        });
    }

    function setupAnimations() {
        const formElements = document.querySelectorAll('.form-group, .form-options, .login-button, .divider, .social-buttons, .signup-link');
        
        formElements.forEach((element, index) => {
            element.style.animationPlayState = 'running';
        });
    }

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

    async function handleFormSubmit(e) {
        e.preventDefault();
        
        if (isLoading) return;

        const email = emailInput.value.trim();
        const password = passwordInput.value;
        const remember = document.getElementById('remember').checked;

        if (!validateEmail() || !validatePassword()) {
            showNotification('Por favor, preencha os campos corretamente.', 'error');
            return;
        }

        try {
            showLoading();
            
            const loginData = await simulateLogin(email, password, remember);
            
            if (loginData.success) {
                showNotification('Login realizado com sucesso!', 'success');
                
                setTimeout(() => {
                    console.log('Redirecionando para dashboard...');
                    hideLoading();
                }, 1500);
                
            } else {
                throw new Error(loginData.message || 'Erro ao fazer login');
            }
            
        } catch (error) {
            hideLoading();
            showNotification(error.message || 'Erro interno do servidor', 'error');
            
            const loginCard = document.querySelector('.login-card');
            loginCard.style.animation = 'shake 0.5s ease-in-out';
            setTimeout(() => {
                loginCard.style.animation = '';
            }, 500);
        }
    }

    async function simulateLogin(email, password, remember) {
        const response = await fetch("login.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email, password, remember })
        });
    
        if (!response.ok) {
            throw new Error("Erro no servidor");
        }
    
        return await response.json();
    }
    
    function handleTogglePassword() {
        const currentType = passwordInput.getAttribute('type');
        const newType = currentType === 'password' ? 'text' : 'password';
        
        passwordInput.setAttribute('type', newType);
        
        if (newType === 'password') {
            togglePassword.textContent = '👁️';
        } else {
            togglePassword.textContent = '🙈';
        }
    }

    function handleSocialLogin(e) {
        e.preventDefault();
        const provider = e.currentTarget.classList.contains('google') ? 'Google' : 'GitHub';
        showProductionAlert(provider);
    }

    function showProductionAlert(provider) {
        showNotification(
            `Login por meio do ${provider} ainda está em produção. Use o formulário normal para acessar.`,
            'warning'
        );
    }

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

    function handleSignupRedirect(e) {
        e.preventDefault();
        showNotification('Redirecionando para página de cadastro...', 'info');
    
        setTimeout(() => {
            window.location.href = 'register.html';
        }, 700);
    }

    function handleLogoRedirect(e) {
        e.preventDefault();
        window.location.href = 'landpage.html';
    }

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

    function showNotification(message, type = 'info') {
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

        setTimeout(() => notification.classList.add('show'), 10);

        const autoRemove = setTimeout(() => {
            hideNotification(notification);
        }, 5000);

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

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && (e.target === emailInput || e.target === passwordInput)) {
            e.preventDefault();
            loginForm.dispatchEvent(new Event('submit'));
        }
        
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

    const rememberCheckbox = document.getElementById('remember');
    
    const savedEmail = localStorage.getItem('rememberedEmail');
    if (savedEmail) {
        emailInput.value = savedEmail;
        rememberCheckbox.checked = true;
        validateEmail();
    }

    rememberCheckbox.addEventListener('change', function() {
        if (this.checked && emailInput.value.trim()) {
            localStorage.setItem('rememberedEmail', emailInput.value.trim());
        } else {
            localStorage.removeItem('rememberedEmail');
        }
    });

    emailInput.addEventListener('input', function() {
        if (rememberCheckbox.checked && this.value.trim()) {
            localStorage.setItem('rememberedEmail', this.value.trim());
        }
    });

    const notificationStyles = document.createElement('style');
    notificationStyles.textContent = `
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
    
    document.head.appendChild(notificationStyles);
});