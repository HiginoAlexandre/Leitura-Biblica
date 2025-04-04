class Auth {
    constructor() {
        this.isAuthenticated = false;
        this.username = null;
        this.setupEventListeners();
        this.checkAuthStatus();
    }

    setupEventListeners() {
        document.getElementById('login-form').addEventListener('submit', (e) => this.handleLogin(e));
        document.getElementById('register-form').addEventListener('submit', (e) => this.handleRegister(e));
        document.getElementById('logout-btn')?.addEventListener('click', () => this.handleLogout());
    }

    async checkAuthStatus() {
        try {
            const response = await fetch('/api/auth.php?action=check_status');
            const data = await response.json();
            this.isAuthenticated = data.isLoggedIn;
            this.username = data.username || null;
            this.updateUI();
        } catch (error) {
            console.error('Erro ao verificar status de autenticação:', error);
        }
    }

    async handleLogin(e) {
        e.preventDefault();
        const formData = new FormData(e.target);

        try {
            const response = await fetch('/api/auth.php?action=login', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                this.isAuthenticated = true;
                this.username = formData.get('username');
                this.updateUI();
                bootstrap.Modal.getInstance(document.getElementById('loginModal')).hide();
                window.location.reload();
            } else {
                alert(data.message);
            }
        } catch (error) {
            console.error('Erro ao fazer login:', error);
            alert('Erro ao fazer login. Tente novamente.');
        }
    }

    async handleRegister(e) {
        e.preventDefault();
        const formData = new FormData(e.target);

        try {
            const response = await fetch('/api/auth.php?action=register', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                alert('Registro realizado com sucesso! Faça login para continuar.');
                bootstrap.Modal.getInstance(document.getElementById('registerModal')).hide();
                bootstrap.Modal.getInstance(document.getElementById('loginModal')).show();
            } else {
                alert(data.message);
            }
        } catch (error) {
            console.error('Erro ao registrar:', error);
            alert('Erro ao registrar. Tente novamente.');
        }
    }

    async handleLogout() {
        try {
            const response = await fetch('/api/auth.php?action=logout');
            const data = await response.json();

            if (data.success) {
                this.isAuthenticated = false;
                this.username = null;
                window.location.reload();
            }
        } catch (error) {
            console.error('Erro ao fazer logout:', error);
        }
    }

    updateUI() {
        const authButtons = document.querySelector('.auth-buttons');
        const userDropdown = document.querySelector('.user-dropdown');

        if (this.isAuthenticated) {
            authButtons?.classList.add('d-none');
            userDropdown?.classList.remove('d-none');
            if (userDropdown) {
                userDropdown.querySelector('.username').textContent = this.username;
            }
        } else {
            authButtons?.classList.remove('d-none');
            userDropdown?.classList.add('d-none');
        }
    }
}

// Inicializa a classe Auth quando o documento estiver carregado
document.addEventListener('DOMContentLoaded', () => {
    window.auth = new Auth();
});