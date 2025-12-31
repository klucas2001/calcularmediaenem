<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['access_error'] = "Acesso negado. Faça login como administrador.";
    header("Location: inicio.php"); 
    exit;
}

// --- ARQUIVOS E VARIÁVEIS DO BAN MANAGER (Ajuste conforme necessário) ---
$arquivo_ban_temp = __DIR__ . '/ip_ban_list.txt'; 
$arquivo_ban_perm = __DIR__ . '/ip_ban_perm.txt'; 
$log_message = $_SESSION['log_message'] ?? null;
unset($_SESSION['log_message']);

// --- COLOQUE A SUA LÓGICA PHP DE GERENCIAMENTO DE BANIMENTOS AQUI ---

// Exemplo de função para ler bans
function get_banned_ips($arquivo_ban_temp, $arquivo_ban_perm) {
    $bans = [];
    
    // Leitura dos bans permanentes
    if (file_exists($arquivo_ban_perm)) {
        $lines = array_filter(explode(PHP_EOL, file_get_contents($arquivo_ban_perm)), 'trim');
        foreach ($lines as $ip) {
            $bans[] = ['ip' => $ip, 'status' => 'Permanente', 'badge' => 'bg-danger', 'expires' => '∞'];
        }
    }
    
    // Leitura dos bans temporários
    if (file_exists($arquivo_ban_temp)) {
        $lines = array_filter(explode(PHP_EOL, file_get_contents($arquivo_ban_temp)), 'trim');
        foreach ($lines as $line) {
            list($ip, $timestamp) = explode('|', $line);
            if ((int)$timestamp > time()) {
                 $expiration_date = date('d/m H:i', (int)$timestamp);
                 $bans[] = ['ip' => $ip, 'status' => 'Temporário', 'badge' => 'bg-warning text-dark', 'expires' => $expiration_date];
            }
            // Se o ban temporário expirou, ele será ignorado aqui. 
            // Uma função de limpeza deve ser executada para removê-lo do arquivo.
        }
    }

    return $bans;
}

$banned_entries = get_banned_ips($arquivo_ban_temp, $arquivo_ban_perm);

// --- FIM DA LÓGICA PHP DE GERENCIAMENTO ---

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Banimentos</title>

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
        /* Estilo da Tabela/Lista */
        .log-table { /* Reutiliza a classe 'log-table' para consistência */
            background-color: #252529; /* Fundo da Tabela Escuro */
            color: #d1d5db; 
            border-radius: 6px;
            overflow: hidden; 
            border: 1px solid #343a40; 
        }
        
        .log-table tbody tr, 
        .log-table tbody tr td {
            background-color: #252529 !important; /* Força o fundo escuro */
            color: #d1d5db !important; /* Garante que o texto seja claro */
        }

        .log-table th {
            font-size: 0.75rem; 
            text-transform: uppercase;
            color: #ccc;
            border-bottom: 2px solid #343a40;
            background-color: #252529 !important; /* Fundo do cabeçalho */
        }
        .log-table th, .log-table td {
            vertical-align: middle;
            border-top: 1px solid #343a40; 
            padding: 0.4rem 0.6rem; 
            font-size: 0.85rem; 
        }
        .log-table tbody tr:hover {
            background-color: #343a40 !important; /* Hover escuro */
        }
        
        .table-container {
            max-height: 75vh; 
            overflow-y: auto;
        }
        /* Cor dos Ícones */
        .icon-sm {
            font-size: 1.1em;
            vertical-align: middle;
            margin-right: 5px;
            color: #0dcaf0; /* Ciano/Info para Ícones de Ação/Status */
        }
        .sticky-top {
             background-color: #252529 !important; /* Fundo do cabeçalho da tabela fixo */
        }
    </style>
</head>
<body>
    <header class="text-center py-4 bg-dark text-white shadow-sm">
        <h1><i class="bi bi-shield-fill-exclamation me-2"></i> Gerenciador de Banimentos</h1>
        <p class="lead">Lista de IPs bloqueados: <?php echo date('d/m/Y H:i:s'); ?></p>
    </header>

    <main class="container-fluid my-5 flex-grow-1">
        
        <?php if ($log_message): ?>
            <div class="alert alert-success"><?php echo $log_message; ?></div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
             <a href="dashboard.php" class="btn btn-outline-info">
                 <i class="bi bi-arrow-left-circle"></i> Voltar ao Dashboard
             </a>
             <div class="text-end">
                <span class="text-white fw-bold me-3" style="font-size: 0.95rem;">
                    <i class="bi bi-list-ol me-1"></i> IPs Banidos: <?php echo count($banned_entries); ?>
                </span>
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#banModal">
                    <i class="bi bi-plus-circle"></i> Banir Novo IP
                </button>
             </div>
        </div>
        
        <?php if (empty($banned_entries)): ?>
            <div class="alert alert-info" role="alert">
                Nenhum IP está atualmente banido.
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table log-table table-borderless mb-0">
                    <thead class="sticky-top">
                        <tr>
                            <th style="width: 25%;">IP Bloqueado</th>
                            <th style="width: 20%;">Status</th>
                            <th style="width: 20%;">Expiração</th>
                            <th style="width: 35%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($banned_entries as $ban): ?>
                        <tr>
                            <td><i class="bi bi-globe icon-sm text-danger"></i><strong><?php echo htmlspecialchars($ban['ip']); ?></strong></td>
                            <td>
                                <span class="badge <?php echo $ban['badge']; ?> text-uppercase">
                                    <i class="bi bi-lock-fill"></i> <?php echo htmlspecialchars($ban['status']); ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-warning"><?php echo htmlspecialchars($ban['expires']); ?></small>
                            </td>
                            <td>
                                <form action="ban_manager.php" method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="unban">
                                    <input type="hidden" name="ip" value="<?php echo htmlspecialchars($ban['ip']); ?>">
                                    <button type="submit" class="btn btn-outline-success btn-sm"
                                        onclick="return confirm('Tem certeza que deseja desbanir este IP?');">
                                        <i class="bi bi-unlock-fill"></i> Desbanir
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
    </main>
    
    <div class="modal fade" id="banModal" tabindex="-1" aria-labelledby="banModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content bg-dark text-white">
          <div class="modal-header">
            <h5 class="modal-title" id="banModalLabel">Banir Novo Endereço IP</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action="ban_manager.php" method="POST">
              <input type="hidden" name="action" value="ban_ip">
              <div class="modal-body">
                <div class="mb-3">
                  <label for="ip_to_ban" class="form-label">Endereço IP</label>
                  <input type="text" class="form-control" id="ip_to_ban" name="ip" required placeholder="Ex: 192.168.1.1">
                </div>
                <div class="mb-3">
                  <label for="ban_type" class="form-label">Tipo de Banimento</label>
                  <select class="form-select" id="ban_type" name="type" required>
                    <option value="perm">Permanente (∞)</option>
                    <option value="temp_1h">Temporário (1 Hora)</option>
                    <option value="temp_24h">Temporário (24 Horas)</option>
                  </select>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-lock-fill"></i> Banir IP</button>
              </div>
          </form>
        </div>
      </div>
    </div>
    <footer class="text-center py-3 bg-dark text-muted mt-auto">
        <p>© 2025 | Sistema de Administração de Segurança</p>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>