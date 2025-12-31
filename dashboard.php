<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['access_error'] = "Acesso negado. Faça login como administrador.";
    header("Location: inicio.php"); 
    exit;
}

// --- COLOQUE A SUA LÓGICA PHP DO DASHBOARD AQUI ---
// Por exemplo, contagem de logs, contagem de bans, etc.
// Exemplo (adapte conforme a sua lógica):
$total_logs = 0;
if (file_exists('logs.txt')) {
    $total_logs = count(array_filter(explode(PHP_EOL, file_get_contents('logs.txt')), 'trim'));
}

$total_bans = 0;
if (file_exists('ip_ban_perm.txt')) {
    $total_bans += count(array_filter(explode(PHP_EOL, file_get_contents('ip_ban_perm.txt')), 'trim'));
}
// Adicione a contagem temporária aqui se desejar.
// --- FIM DA LÓGICA PHP ---
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body {
            background-color: #1a1a1d; /* Fundo Principal: MUITO ESCURO */
            color: #f8f9fa; 
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        /* Estilo dos Cards (para harmonizar com a tabela de logs) */
        .dashboard-card {
            background-color: #252529; 
            border: 1px solid #343a40; 
            color: #f8f9fa;
            border-radius: 6px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .dashboard-card-header {
            border-bottom: 1px solid #343a40;
            font-size: 0.9rem;
            text-transform: uppercase;
            color: #ccc;
            padding: 0.75rem 1.25rem;
        }
        .dashboard-card-body {
            padding: 1.25rem;
        }
        .text-info-custom {
            color: #0dcaf0 !important; /* Cor Ciano/Info do ícone */
        }
        /* Cor dos Ícones */
        .icon-lg {
            font-size: 2.5em; /* Ícones maiores para o dashboard */
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <header class="text-center py-4 bg-dark text-white shadow-sm">
        <h1><i class="bi bi-speedometer2 me-2"></i> Painel de Controle (Dashboard)</h1>
        <p class="lead">Bem-vindo, Administrador! | Último acesso: <?php echo date('d/m/Y H:i:s'); ?></p>
    </header>

    <main class="container my-5 flex-grow-1">
        <?php if (isset($_SESSION['log_message'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['log_message']; ?></div>
            <?php unset($_SESSION['log_message']); ?>
        <?php endif; ?>

        <div class="row mb-5">
            <div class="col-md-4 mb-4">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        Logs de Acesso
                    </div>
                    <div class="dashboard-card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-0 fw-bold"><?php echo $total_logs; ?></h2>
                            <p class="text-muted mb-0">Total de Entradas</p>
                        </div>
                        <i class="bi bi-activity icon-lg text-info-custom"></i>
                    </div>
                    <div class="dashboard-card-body pt-0">
                        <a href="show_logs.php" class="btn btn-outline-info btn-sm w-100">
                            <i class="bi bi-eye"></i> Visualizar Logs
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        Segurança
                    </div>
                    <div class="dashboard-card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-0 fw-bold text-warning"><?php echo $total_bans; ?></h2>
                            <p class="text-muted mb-0">IPs Banidos</p>
                        </div>
                        <i class="bi bi-shield-fill-exclamation icon-lg text-warning"></i>
                    </div>
                    <div class="dashboard-card-body pt-0">
                        <a href="ban_manager.php" class="btn btn-outline-warning btn-sm w-100">
                            <i class="bi bi-lock-fill"></i> Gerenciar Banimentos
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        Configurações
                    </div>
                    <div class="dashboard-card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-0 fw-bold">Logout</h2>
                            <p class="text-muted mb-0">Encerrar Sessão</p>
                        </div>
                        <i class="bi bi-box-arrow-right icon-lg text-secondary"></i>
                    </div>
                    <div class="dashboard-card-body pt-0">
                        <a href="logout.php" class="btn btn-outline-danger btn-sm w-100">
                            <i class="bi bi-door-open"></i> Sair do Painel
                        </a>
                    </div>
                </div>
            </div>
            
        </div>
        
    </main>

    <footer class="text-center py-3 bg-dark text-muted mt-auto">
        <p>© 2025 | Sistema de Administração de Segurança</p>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>