<?php
session_start();

// Verifica se há informações de bloqueio na sessão. Se não houver, ou se o bloqueio expirou, redireciona.
if (!isset($_SESSION['is_banned']) || !$_SESSION['is_banned']) {
    // Redireciona para o nome correto da página inicial
    header("Location: inicio.php"); 
    exit;
}

$ban_type = $_SESSION['ban_type'] ?? 'PERM'; 
$lockout_end_time = $_SESSION['lockout_end'] ?? 0;
$remaining_seconds = max(0, $lockout_end_time - time());

// Limpa o banimento da sessão se for temporário e tiver expirado
if ($ban_type === 'TEMP' && $remaining_seconds <= 0) {
    // Limpa a sessão para que o botão fique verde e o ícone de seta apareça novamente
    unset($_SESSION['is_banned']);
    unset($_SESSION['ban_type']);
    unset($_SESSION['lockout_end']);
}

$title = "Acesso Bloqueado";
$message = "Seu acesso foi restrito por motivos de segurança. Você foi bloqueado permanentemente.";
$show_countdown = false;

// Variáveis de controle de estilo
$button_class = 'btn-danger'; // Vermelho e Bloqueado (Padrão para banido)
$button_icon = 'bi-x-octagon-fill'; // Ícone de "X" de bloqueio (Padrão para banido)


if ($ban_type === 'TEMP' && $remaining_seconds > 0) {
    $title = "Você foi bloqueado temporariamente";
    $message = "Seu IP foi temporariamente bloqueado. O acesso será restabelecido em:";
    $show_countdown = true;
    // (Mantém o $button_class e $button_icon definidos como Vermelho/X)
} elseif ($ban_type === 'TEMP' && $remaining_seconds <= 0) {
    // Banimento temporário expirado
    $title = "Bloqueio Expirado!";
    $message = "O tempo de bloqueio expirou. Clique no botão abaixo para retornar à página inicial.";
    $show_countdown = false;
    $button_class = 'btn-success'; // Verde após expirar
    $button_icon = 'bi-arrow-left-circle-fill'; // Seta para voltar
} else {
    // Banimento Permanente (Mantém Vermelho/X)
}


// Funções de formatação de tempo
function formatTime($totalSeconds) {
    if ($totalSeconds < 0) $totalSeconds = 0;
    
    $hours = floor($totalSeconds / 3600);
    $minutes = floor(($totalSeconds % 3600) / 60);
    $seconds = $totalSeconds % 60;

    $parts = [];
    if ($hours > 0) $parts[] = $hours . " hora" . ($hours > 1 ? "s" : "");
    if ($minutes > 0) $parts[] = $minutes . " minuto" . ($minutes > 1 ? "s" : "");
    if (empty($parts) || $seconds > 0) {
         $parts[] = $seconds . " segundo" . ($seconds !== 1 ? "s" : "");
    }

    if (count($parts) > 1) {
        $last = array_pop($parts);
        return implode(", ", $parts) . " e " . $last;
    }
    return $parts[0] ?? "Expirado";
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        body {
            background-color: #1a1a1d; /* Fundo Principal: MUITO ESCURO */
            color: #f8f9fa; 
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .block-container {
            width: 100%;
            max-width: 500px; /* Largura para a mensagem de bloqueio */
            padding: 30px;
            background-color: #252529; /* Fundo do Card/Formulário */
            border: 1px solid #343a40; 
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5); 
        }
        .countdown {
            font-size: 1.8em;
            font-weight: bold;
            color: #0dcaf0; /* Cor Ciano/Info */
            margin-top: 15px;
        }
        /* Definição de cores para o botão */
        .btn-danger { background-color: #dc3545; border-color: #dc3545; }
        .btn-success { background-color: #198754; border-color: #198754; }
    </style>
</head>
<body>

    <div class="block-container">
        <i class="bi bi-lock-fill" style="font-size: 3em; color: <?php echo ($button_class == 'btn-success' ? '#198754' : '#dc3545'); ?>;"></i>
        <h1 class="mt-3"><?php echo $title; ?></h1>
        
        <p class="lead mt-4"><?php echo $message; ?></p>
        
        <?php if ($show_countdown): ?>
            <div id="countdown" class="countdown">
                <?php echo formatTime($remaining_seconds); ?>
            </div>
            <p class="text-light mt-2">A página será recarregada automaticamente quando o tempo terminar.</p>
        <?php endif; ?>

        <a href="inicio.php" class="btn <?php echo $button_class; ?> btn-lg mt-4">
            <i class="bi <?php echo $button_icon; ?>"></i> Voltar para a Página Inicial
        </a>

        <?php if ($ban_type === 'PERM'): ?>
            <p class="text-light mt-2">Entre em contato com o suporte para mais informações sobre o banimento permanente.</p>
        <?php endif; ?>

    </div>

    <?php if ($show_countdown): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let remainingSeconds = <?php echo $remaining_seconds; ?>;
            const countdownElement = document.getElementById('countdown');
            const backButton = document.querySelector('.btn');
            const backButtonIcon = backButton.querySelector('i');
            const lockIcon = document.querySelector('.bi-lock-fill');

            function formatTimeJs(totalSeconds) {
                if (totalSeconds < 0) totalSeconds = 0;
                const hours = Math.floor(totalSeconds / 3600);
                const minutes = Math.floor((totalSeconds % 3600) / 60);
                const seconds = totalSeconds % 60;

                let parts = [];
                if (hours > 0) parts.push(hours + " hora" + (hours > 1 ? "s" : ""));
                if (minutes > 0) parts.push(minutes + " minuto" + (minutes > 1 ? "s" : ""));
                if (hours === 0 && minutes === 0 || seconds > 0) {
                    parts.push(seconds + " segundo" + (seconds !== 1 ? "s" : ""));
                }
                
                if (parts.length > 1) {
                    const last = parts.pop();
                    return parts.join(", ") + " e " + last;
                } else {
                    return parts[0];
                }
            }

            function updateCountdown() {
                if (remainingSeconds <= 0) {
                    clearInterval(timerInterval);
                    countdownElement.textContent = "Acesso Liberado! Clique no botão abaixo.";
                    
                    // 1. Mudar o estilo do botão para verde
                    backButton.classList.remove('btn-danger');
                    backButton.classList.add('btn-success');
                    
                    // 2. Mudar o ícone do botão para a seta (bi-arrow-left-circle-fill)
                    backButtonIcon.classList.remove('bi-x-octagon-fill');
                    backButtonIcon.classList.add('bi-arrow-left-circle-fill');

                    // 3. Mudar o ícone de bloqueio do topo para verde
                    if(lockIcon) {
                         lockIcon.style.color = '#198754';
                    }
                    
                    // 4. Mudar o texto
                    document.querySelector('h1').textContent = "Bloqueio Expirado!";
                    document.querySelector('.lead').textContent = "O tempo de bloqueio expirou. Clique no botão abaixo para retornar à página inicial.";
                    
                    // Não precisa mudar a cor do texto de notificação via JS, pois o elemento 'p'
                    // com a mensagem "A página será recarregada..." some e é substituído pelas novas mensagens.
                    
                    return;
                }

                countdownElement.innerHTML = formatTimeJs(remainingSeconds);
                remainingSeconds--;
            }

            updateCountdown();
            const timerInterval = setInterval(updateCountdown, 1000);
        });
    </script>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>