<?php
require_once 'auth/auth.php';

// Configurações de conexão com o banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "leiturabiblica";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Erro ao conectar ao banco de dados: " . $conn->connect_error);
    }
    $auth = new Auth($conn);
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leitura da Bíblia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/custom.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="#">Leitura Bíblica</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="new-tab-btn">Nova Aba</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#searchModal">Buscar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#collectionsModal">Coleções</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <?php if ($auth->isLoggedIn()): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#preferencesModal">Preferências</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" id="logout-btn">Sair</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#loginModal">Entrar</button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal">Registrar</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Container Principal -->
    <div class="container mt-4">
        <div class="bible-tabs" id="bible-tabs"></div>
        <div class="bible-container" id="bible-content"></div>
    </div>

    <!-- Controles do Usuário -->
    <div class="user-controls">
        <div class="btn-group">
            <button class="btn btn-light" id="dark-mode-toggle" title="Modo Noturno">
                <i class="bi bi-moon"></i>
            </button>
            <input type="range" class="form-range font-size-control" id="font-size-control" min="12" max="24" value="16">
            <button class="btn btn-light" id="show-phonetics-toggle" title="Mostrar Fonética">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

    <!-- Modal de Login -->
    <div class="modal fade" id="loginModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Entrar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="login-form">
                        <div class="mb-3">
                            <label class="form-label">Usuário</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Senha</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Entrar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Registro -->
    <div class="modal fade" id="registerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="register-form">
                        <div class="mb-3">
                            <label class="form-label">Usuário</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Senha</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Registrar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Preferências -->
    <div class="modal fade" id="preferencesModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Preferências</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Modo Noturno</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="darkModeSwitch">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tamanho da Fonte</label>
                        <input type="range" class="form-range" id="fontSizeRange" min="12" max="24" value="16">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mostrar Fonética</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="phoneticsSwitch" checked>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Busca -->
    <div class="modal fade" id="searchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Buscar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="search-form" class="mb-3">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Digite sua busca..." name="query">
                            <button class="btn btn-primary" type="submit">Buscar</button>
                        </div>
                    </form>
                    <div id="search-results"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Coleções -->
    <div class="modal fade" id="collectionsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Minhas Coleções</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#newCollectionModal">
                        Nova Coleção
                    </button>
                    <div id="collections-list"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Nova Coleção -->
    <div class="modal fade" id="newCollectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nova Coleção</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="new-collection-form">
                        <div class="mb-3">
                            <label class="form-label">Nome da Coleção</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Criar Coleção</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css"></script>
    <script src="js/app.js"></script>
</body>
</html>