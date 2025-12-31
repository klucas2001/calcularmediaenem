<?php
session_start();
include 'iplogger.php'; 

// Verifica se o usuário está logado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['access_error'] = "Acesso negado. Faça login como administrador.";
    header("Location: inicio.php"); 
    exit;
}

// ARQUIVOS DO SISTEMA
$arquivo_log = __DIR__ . '/logs.txt';
$arquivo_ban_temp = __DIR__ . '/ip_ban_list.txt'; 
$arquivo_ban_perm = __DIR__ . '/ip_ban_perm.txt'; 
$arquivo_cache = __DIR__ . '/ip_cache.json';

// --- FUNÇÕES DE GERENCIAMENTO ---

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'clear_logs') {
    if (file_put_contents($arquivo_log, "") !== false) {
        $_SESSION['log_message'] = "Logs de acesso apagados com sucesso!";
    } else {
        $_SESSION['log_message'] = "ERRO: Falha ao apagar o arquivo logs.txt. Verifique as permissões.";
    }
    header("Location: show_logs.php");
    exit;
}

// --- FUNÇÕES DE UTILIDADE ---

function get_browser_icon($browser_name) {
    $browser_name = strtolower($browser_name);
    if (strpos($browser_name, 'chrome') !== false) return 'bi-google-chrome';
    if (strpos($browser_name, 'operagx') !== false || strpos($browser_name, 'opr') !== false || strpos($browser_name, 'opera') !== false) return 'bi-browser-opera';
    if (strpos($browser_name, 'firefox') !== false) return 'bi-browser-firefox';
    if (strpos($browser_name, 'safari') !== false) return 'bi-browser-safari';
    if (strpos($browser_name, 'edge') !== false) return 'bi-browser-edge';
    return 'bi-question-square';
}

function get_ip_info($ip) {
    global $arquivo_cache;
    $cache_data = [];
    if (file_exists($arquivo_cache)) {
        $cache_data = json_decode(file_get_contents($arquivo_cache), true) ?? [];
    }

    if (isset($cache_data[$ip]) && (time() - $cache_data[$ip]['timestamp'] < 86400)) { 
        return $cache_data[$ip];
    }
    
    if ($ip === '::1' || $ip === '127.0.0.1') {
        $info = ['status' => 'success', 'country' => 'Localhost', 'countryCode' => 'LOC', 'city' => 'Local', 'timestamp' => time()];
        $cache_data[$ip] = $info;
        file_put_contents($arquivo_cache, json_encode($cache_data, JSON_PRETTY_PRINT), LOCK_EX);
        return $info;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://ip-api.com/json/{$ip}?fields=countryCode,country,city,status");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    
    $info = ['status' => 'fail', 'country' => 'Indefinido', 'countryCode' => 'XX', 'city' => 'N/A', 'timestamp' => time()];

    if ($data && $data['status'] === 'success') {
        $info['status'] = 'success';
        $info['country'] = $data['country'];
        $info['countryCode'] = strtolower($data['countryCode'] ?? 'XX'); 
        $info['city'] = $data['city'] ?? 'N/A';
    }

    $cache_data[$ip] = $info;
    file_put_contents($arquivo_cache, json_encode($cache_data, JSON_PRETTY_PRINT), LOCK_EX);
    
    return $info;
}

function get_ban_status($ip, $arquivo_ban_temp, $arquivo_ban_perm) {
    $status = ['status' => 'NONE', 'message' => 'Desbanido', 'badge_class' => 'bg-success', 'ban_date' => 'N/A'];
    
    if (file_exists($arquivo_ban_perm) && strpos(file_get_contents($arquivo_ban_perm), $ip) !== false) {
        $status = ['status' => 'PERM', 'message' => 'Permanente', 'badge_class' => 'bg-danger', 'ban_date' => '∞'];
        return $status;
    }

    if (file_exists($arquivo_ban_temp)) {
        $lines = explode(PHP_EOL, file_get_contents($arquivo_ban_temp));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            list($banned_ip, $timestamp) = explode('|', $line);
            if ($banned_ip === $ip) {
                if ((int)$timestamp > time()) {
                    $expiration_date = date('d/m H:i', (int)$timestamp);
                    $status = ['status' => 'TEMP', 'message' => 'Temporário', 'badge_class' => 'bg-warning text-dark', 'ban_date' => $expiration_date];
                    return $status;
                }
            }
        }
    }
    
    return $status;
}

// --- PROCESSAMENTO PRINCIPAL ---

$log_entries = [];
$error_message = null;

if (file_exists($arquivo_log)) {
    $content = @file_get_contents($arquivo_log);
    
    if ($content === false) {
        $error_message = "ERRO: O arquivo logs.txt existe, mas o servidor não tem permissão de leitura.";
    } else {
        $lines = explode(PHP_EOL, $content);
        $lines = array_filter($lines, 'trim'); 
        $total_entries = count($lines);
        
        foreach (array_reverse($lines) as $line) {
            
            if (!preg_match('/^\[(.*?)\] (.*)$/', $line, $matches)) {
                 $log_entries[] = ['time' => 'N/A', 'ip' => 'N/A', 'site' => htmlspecialchars($line), 'browser' => 'N/A', 'mobile' => 'N/A', 'geo' => null, 'ban' => null];
                 continue;
            }

            $timestamp = $matches[1];
            $data_string = $matches[2];

            $data = [
                'PAGINA' => 'N/A', 'IP' => 'N/A', 'PAIS' => 'N/A', 'BROWSER' => 'N/A', 
                'LOCAL/DETALHES' => 'N/A', 'REFERER' => 'N/A', 'MOBILE' => 'N/A'
            ];
            
            $parts = explode(' | ', $data_string);
            foreach ($parts as $part) {
                if (preg_match('/^(.+?):\s*(.*)$/', trim($part), $kv_matches)) {
                    $key = trim($kv_matches[1]);
                    $value = trim($kv_matches[2]);
                    if (isset($data[$key])) {
                        $data[$key] = $value;
                    }
                }
            }
            
            $ip = $data['IP'];
            $geo_info = get_ip_info($ip);
            $ban_info = get_ban_status($ip, $arquivo_ban_temp, $arquivo_ban_perm);
            
            $log_entries[] = [
                'time' => $timestamp,
                'ip' => $ip,
                'site' => $data['PAGINA'],
                'browser' => $data['BROWSER'],
                'mobile' => (strtoupper($data['MOBILE']) == 'SIM' || strtoupper($data['MOBILE']) == 'TRUE' ? 'Sim' : 'Não'),
                'geo' => $geo_info,
                'ban' => $ban_info
            ];
        }
    }
} else {
    $error_message = "ERRO: O arquivo logs.txt não foi encontrado no diretório do sistema.";
}

$log_message = $_SESSION['log_message'] ?? null;
unset($_SESSION['log_message']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor de Logs</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/3.5.0/css/flag-icon.min.css">
    
    <style>
        body {
            background-color: #1a1a1d; /* Fundo Principal: MUITO ESCURO */
            color: #f8f9fa; 
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        /* Estilo da Tabela/Lista */
        .log-table {
            background-color: #252529; /* Fundo da Tabela Escuro */
            color: #d1d5db; 
            border-radius: 6px;
            overflow: hidden; 
            border: 1px solid #343a40; 
        }
        
        /* *** CORREÇÕES CHAVE PARA O FUNDO ESCURO (LINHAS E CÉLULAS) *** */
        .log-table tbody tr, 
        .log-table tbody tr td {
            background-color: #252529 !important; /* Força o fundo das linhas e células */
            color: #d1d5db !important; /* Garante que o texto nas linhas seja claro */
        }
        /* ******************************************************* */

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
        .flag-icon {
            margin-right: 5px;
            font-size: 1em;
            vertical-align: middle;
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
            color: #0dcaf0; 
        }
        .sticky-top {
             background-color: #252529 !important; /* Fundo do cabeçalho da tabela fixo */
        }
    </style>
</head>
<body>
    <header class="text-center py-4 bg-dark text-white shadow-sm">
        <h1><i class="bi bi-activity me-2"></i> Monitor de Acessos</h1>
        <p class="lead">Visualização de eventos do sistema: 
        <?php echo date('d/m/Y H:i:s'); ?></p>
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
                    <i class="bi bi-list-ol me-1"></i> Entradas: <?php echo count($log_entries); ?>
                </span>
                <a href="show_logs.php?action=clear_logs" class="btn btn-danger btn-sm" 
                   onclick="return confirm('Tem certeza que deseja apagar TODOS os logs? Esta ação é irreversível.');">
                    <i class="bi bi-trash"></i> Apagar Logs
                </a>
             </div>
        </div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php elseif (empty($log_entries)): ?>
            <div class="alert alert-info" role="alert">
                Não há entradas de log para exibir.
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table log-table table-borderless mb-0">
                    <thead class="sticky-top">
                        <tr>
                            <th style="width: 8%;">Horário</th>
                            <th style="width: 10%;">IP</th>
                            <th style="width: 10%;">País</th>
                            <th style="width: 25%;">Site Acessado</th>
                            <th style="width: 15%;">Navegador</th>
                            <th style="width: 7%;">Mobile</th>
                            <th style="width: 12%;">Status</th>
                            <th style="width: 10%;">Expiração</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($log_entries as $entry): 
                            $browser_icon = get_browser_icon($entry['browser']);
                            // Extrai o valor da hora para evitar problemas de sintaxe no HTML
                            $display_time = htmlspecialchars(date('H:i:s', strtotime($entry['time'])));
                        ?>
                        <tr>
                            <td><small class="text-light"><i class="bi bi-clock icon-sm"></i><?php echo $display_time; ?></small></td>
                            <td><i class="bi bi-globe icon-sm"></i><strong><?php echo htmlspecialchars($entry['ip']); ?></strong></td>
                            <td>
                                <?php 
                                    $country_code = $entry['geo']['countryCode'] ?? 'XX';
                                    $country_name = $entry['geo']['country'] ?? 'N/A';
                                    
                                    if ($country_code === 'LOC'): 
                                ?>
                                    <i class="bi bi-house-door icon-sm text-info" title="Localhost"></i> 
                                    <small class="text-info">Localhost</small>
                                <?php elseif ($country_code !== 'XX'): 
                                ?>
                                    <span class="flag-icon flag-icon-<?php echo $country_code; ?>" 
                                          title="<?php echo htmlspecialchars($country_name); ?>">
                                    </span> 
                                    <small><?php echo htmlspecialchars($country_name); ?></small>
                                <?php else: 
                                ?>
                                    <i class="bi bi-patch-question icon-sm text-secondary" title="País Indefinido"></i>
                                    <small class="text-secondary">Indefinido</small>
                                <?php endif; ?>
                            </td>
                            <td><small><i class="bi bi-link-45deg icon-sm"></i><?php echo htmlspecialchars($entry['site']); ?></small></td>
                            <td>
                                <i class="bi <?php echo $browser_icon; ?> icon-sm"></i>
                                <small class="text-secondary"><?php echo htmlspecialchars($entry['browser']); ?></small>
                            </td>
                            <td>
                                <?php $mobile_icon = ($entry['mobile'] == 'Sim' ? 'bi-phone-fill' : 'bi-pc-display-horizontal'); ?>
                                <span class="badge rounded-pill <?php echo ($entry['mobile'] == 'Sim' ? 'bg-info' : 'bg-dark'); ?> text-white" style="font-size: 0.7em;">
                                    <i class="bi <?php echo $mobile_icon; ?>"></i> <?php echo $entry['mobile']; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                    $ban_info = $entry['ban'] ?? ['badge_class' => 'bg-secondary', 'message' => 'N/A'];
                                    $lock_icon = ($ban_info['status'] == 'PERM' || $ban_info['status'] == 'TEMP') ? 'bi-lock-fill' : 'bi-unlock-fill';
                                ?>
                                <span class="badge <?php echo $ban_info['badge_class']; ?> text-uppercase">
                                    <i class="bi <?php echo $lock_icon; ?>"></i> <?php echo htmlspecialchars($ban_info['message']); ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-warning"><?php echo htmlspecialchars($ban_info['ban_date'] ?? 'N/A'); ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
    </main>

    <footer class="text-center py-3 bg-dark text-muted mt-auto">
        <p>© 2025 | Sistema de Administração de Segurança</p>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>