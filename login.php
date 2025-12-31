<?php
session_start();

// Configurações de login
$usuario_correto = "admin";
$senha_correta = "admin"; 

// Configurações de segurança
$session_timeout = 60 * 10; // 10 minutos
$arquivo_ban_temp = __DIR__ . '/ip_ban_list.txt'; 
$ban_duration = 60; // 60 segundos (1 minuto) de banimento após 5 falhas
$max_attempts = 5; // Limite de tentativas de login
$arquivo_tentativas = __DIR__ . '/login_attempts.txt'; 

// --- FUNÇÕES DE SEGURANÇA ---

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

function ban_ip_temporariamente($ip, $duration_seconds) {
    global $arquivo_ban_temp;
    $expiration_time = time() + $duration_seconds;
    $ban_line = "{$ip}|{$expiration_time}";

    $content = @file_get_contents($arquivo_ban_temp);
    $new_content = [];

    if ($content !== false) {
        $lines = explode(PHP_EOL, $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;
            
            if (strpos($line, '|') !== false) {
                list($banned_ip, $timestamp) = explode('|', $line);
                
                if ($banned_ip !== $ip && (int)$timestamp > time()) {
                    $new_content[] = $line;
                }
            }
        }
    }
    
    $new_content[] = trim($ban_line);
    
    @file_put_contents($arquivo_ban_temp, implode(PHP_EOL, array_unique($new_content)) . PHP_EOL, LOCK_EX);
}

// Incrementa as tentativas de login e retorna o total
function increment_attempts($ip) {
    global $arquivo_tentativas;
    $current_attempts = 0;
    $new_content = [];
    $found = false;
    $current_time = time();
    $expiration_threshold = 3600; // Tentativas válidas por 1 hora

    if (file_exists($arquivo_tentativas)) {
        $content = @file_get_contents($arquivo_tentativas);
        $lines = explode(PHP_EOL, $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (strpos($line, '|') !== false) {
                list($attempt_ip, $count, $timestamp) = explode('|', $line);
                
                if ((int)$timestamp + $expiration_threshold > $current_time) {
                    if ($attempt_ip === $ip) {
                        $current_attempts = (int)$count + 1;
                        $new_content[] = "{$ip}|{$current_attempts}|{$current_time}"; 
                        $found = true;
                    } else {
                        $new_content[] = $line;
                    }
                }
            }
        }
    }
    
    if (!$found) {
        $current_attempts = 1;
        $new_content[] = "{$ip}|1|{$current_time}";
    }
    
    @file_put_contents($arquivo_tentativas, implode(PHP_EOL, array_unique($new_content)) . PHP_EOL, LOCK_EX);
    return $current_attempts;
}

// Limpa as tentativas após um login bem-sucedido OU após um banimento temporário
function clear_attempts($ip) {
    global $arquivo_tentativas;
    $new_content = [];
    $current_time = time();
    $expiration_threshold = 3600; 

    if (file_exists($arquivo_tentativas)) {
        $content = @file_get_contents($arquivo_tentativas);
        $lines = explode(PHP_EOL, $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (strpos($line, '|') !== false) {
                list($attempt_ip, $count, $timestamp) = explode('|', $line);
                
                // Mantém apenas IPs que não são o IP logado/banido E que não expiraram
                if ($attempt_ip !== $ip && (int)$timestamp + $expiration_threshold > $current_time) {
                    $new_content[] = $line;
                }
            }
        }
    }
    
    @file_put_contents($arquivo_tentativas, implode(PHP_EOL, array_unique($new_content)) . PHP_EOL, LOCK_EX);
}


// --- PROCESSAMENTO PRINCIPAL ---

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $ip = get_client_ip();

    if ($username === $usuario_correto && $password === $senha_correta) {
        // Login bem-sucedido
        clear_attempts($ip); // Zera o contador de tentativas após sucesso
        unset($_SESSION['login_error']); 
        
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['expire'] = time() + $session_timeout; 

        header("Location: dashboard.php");
        exit;
    } else {
        // Login falhou - RASTREIA TENTATIVAS
        $current_attempts = increment_attempts($ip); 
        
        if ($current_attempts >= $max_attempts) {
            // Bane o IP no 5º erro
            ban_ip_temporariamente($ip, $ban_duration); 
            clear_attempts($ip); // <--- A CHAVE DA CORREÇÃO: Reseta o contador APÓS o banimento
            
            $_SESSION['login_error'] = "Limite de {$max_attempts} tentativas atingido. Seu IP foi banido por 1 minuto.";
        } else {
            // Exibe tentativas restantes
            $remaining = $max_attempts - $current_attempts;
            $_SESSION['login_error'] = "Usuário ou senha incorretos. Você tem mais {$remaining} tentativa(s) antes de ser banido temporariamente.";
        }
        
        header("Location: inicio.php"); 
        exit;
    }
} else {
    header("Location: inicio.php");
    exit;
}
?>