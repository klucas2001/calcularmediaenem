<?php
// O session_start é necessário para usar as variáveis $_SESSION e redirecionar
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Desativa o limite de tempo para evitar que o script seja interrompido
set_time_limit(0); 

// Define o nome do arquivo de log e banimento
$arquivo_log = __DIR__ . '/logs.txt';
$arquivo_ban_temp = __DIR__ . '/ip_ban_list.txt'; // Banimento temporário: IP|TIMESTAMP
$arquivo_ban_perm = __DIR__ . '/ip_ban_perm.txt'; // Banimento permanente: IP

// --- FUNÇÕES DE COLETA DE DADOS (Não mudaram) ---

// 1. Obtém o IP real do cliente
function get_client_ip() {
    $ipaddress = 'UNKNOWN';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    
    if (strpos($ipaddress, ',') !== false) {
        $ipaddress = trim(explode(',', $ipaddress)[0]);
    }
    return $ipaddress;
}

// 2. Detecta se é um dispositivo móvel
function is_mobile($user_agent) {
    $regex = '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|l)\d|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino|tablet|ipad|playbook|silk/i';
    return preg_match($regex, $user_agent) ? 'SIM' : 'NAO';
}

// 3. Extrai o nome do navegador
function get_browser_name($user_agent) {
    if (preg_match('/opr/i', $user_agent) && !preg_match('/chrome/i', $user_agent)) {
        return 'Opera';
    } elseif (preg_match('/firefox/i', $user_agent)) {
        return 'Firefox';
    } elseif (preg_match('/msie/i', $user_agent) || preg_match('/trident/i', $user_agent)) {
        return 'Internet Explorer';
    } elseif (preg_match('/edge/i', $user_agent)) {
        return 'Edge';
    } elseif (preg_match('/chrome/i', $user_agent)) {
        return 'Chrome';
    } elseif (preg_match('/safari/i', $user_agent)) {
        return 'Safari';
    } elseif (preg_match('/ucbrowser/i', $user_agent)) {
        return 'UC Browser';
    } else {
        return 'Outro';
    }
}

// 4. Obtém a geolocalização (simplificada)
function get_geolocation_data($ip) {
    if ($ip == '::1' || $ip == '127.0.0.1') {
        return ['log' => 'IP Local (XAMPP)', 'countryCode' => 'BR']; 
    }
    return ['log' => 'Localização N/A', 'countryCode' => 'XX']; 
}

// 5. Checa se o IP está na lista negra e limpa IPs expirados
function check_ban_status($ip, $ban_file_temp, $ban_file_perm) {
    $current_time = time();

    // 1. Verificar Banimento Permanente
    if (file_exists($ban_file_perm)) {
        $perm_bans = @file_get_contents($ban_file_perm);
        $ip_list = array_map('trim', explode(PHP_EOL, $perm_bans));
        if (in_array($ip, $ip_list)) {
            return ['type' => 'PERM', 'time' => 0];
        }
    }

    // 2. Verificar Banimento Temporário
    if (file_exists($ban_file_temp)) {
        $content = @file_get_contents($ban_file_temp);
        $active_bans = [];
        $lockout_end = 0;

        if ($content !== false) {
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, '|') !== false) {
                    list($banned_ip, $timestamp) = explode('|', $line);
                    
                    if ((int)$timestamp > $current_time) {
                        $active_bans[] = $line;
                        if ($banned_ip === $ip) {
                            $lockout_end = (int)$timestamp;
                        }
                    }
                }
            }
        }
        
        // Reescreve o arquivo limpando os IPs expirados
        @file_put_contents($ban_file_temp, implode(PHP_EOL, array_unique($active_bans)) . PHP_EOL, LOCK_EX);
        
        if ($lockout_end > $current_time) {
            return ['type' => 'TEMP', 'time' => $lockout_end];
        }
    }
    
    return ['type' => 'NONE', 'time' => 0];
}


// --- EXECUÇÃO PRINCIPAL: VERIFICAÇÃO DE BANIMENTO ---
$ip = get_client_ip();
$ban_status = check_ban_status($ip, $arquivo_ban_temp, $arquivo_ban_perm);

if ($ban_status['type'] !== 'NONE' && basename($_SERVER['PHP_SELF']) !== 'bloqueado.php') {
    // Redireciona para a página de bloqueio se estiver banido e não estiver já na página de bloqueio
    $_SESSION['is_banned'] = true;
    $_SESSION['ban_type'] = $ban_status['type'];
    $_SESSION['lockout_end'] = $ban_status['time'];

    header("Location: bloqueado.php");
    exit;
} else if ($ban_status['type'] === 'NONE') {
    // Se não estiver banido, limpa qualquer resquício de banimento da sessão
    unset($_SESSION['is_banned']);
    unset($_SESSION['ban_type']);
    unset($_SESSION['lockout_end']);
}
// --- FIM DA VERIFICAÇÃO DE BANIMENTO ---


// --- LOGGING DE ACESSO (Opcional, mas mantido) ---
$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'N/A';
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'DIRETO/N/A';
$data_hora = date('Y-m-d H:i:s');
$pagina_completa = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

$geo_data = get_geolocation_data($ip);
$localizacao_detalhada = $geo_data['log'];
$country_code = $geo_data['countryCode'] ?? 'XX';
$browser_name = get_browser_name($user_agent);
$is_mobile = is_mobile($user_agent); 

$linha_log = 
    "[{$data_hora}] " . 
    "| PAGINA: {$pagina_completa} " . 
    "| IP: {$ip} " . 
    "| PAIS: {$country_code} " . 
    "| BROWSER: {$browser_name} " . 
    "| LOCAL/DETALHES: {$localizacao_detalhada} " . 
    "| REFERER: {$referer} " .
    "| MOBILE: {$is_mobile} " .
    "| USER-AGENT: {$user_agent}" . PHP_EOL;

@file_put_contents($arquivo_log, $linha_log, FILE_APPEND | LOCK_EX);
?>