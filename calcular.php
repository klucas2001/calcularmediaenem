<?php
session_start();

// ARQUIVOS DO SISTEMA DE SEGURANÇA (Geralmente localizados no mesmo diretório)
$arquivo_ban_temp = __DIR__ . '/ip_ban_list.txt'; 
$arquivo_ban_perm = __DIR__ . '/ip_ban_perm.txt'; 

// --- FUNÇÕES DE SEGURANÇA ---

function get_client_ip() {
    $ipaddress = 'UNKNOWN';
    if (isset($_SERVER['HTTP_CLIENT_IP'])) $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['REMOTE_ADDR'])) $ipaddress = $_SERVER['REMOTE_ADDR'];
    if (strpos($ipaddress, ',') !== false) {
        $ipaddress = trim(explode(',', $ipaddress)[0]);
    }
    return $ipaddress;
}

// Retorna o status de banimento (TEMP, PERM, NONE) e o timestamp de expiração.
function get_ban_status($ip, $arquivo_ban_temp, $arquivo_ban_perm) {
    $status = ['status' => 'NONE', 'timestamp' => 0]; 
    
    // 1. Verifica Banimento Permanente
    if (file_exists($arquivo_ban_perm) && strpos(file_get_contents($arquivo_ban_perm), $ip) !== false) {
        $status['status'] = 'PERM';
        return $status;
    }

    // 2. Verifica Banimento Temporário
    if (file_exists($arquivo_ban_temp)) {
        $lines = explode(PHP_EOL, file_get_contents($arquivo_ban_temp));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '|') === false) continue;
            
            list($banned_ip, $timestamp) = explode('|', $line);
            if ($banned_ip === $ip) {
                if ((int)$timestamp > time()) {
                    $status['status'] = 'TEMP';
                    $status['timestamp'] = (int)$timestamp;
                    return $status;
                }
            }
        }
    }
    
    return $status;
}

// --- VERIFICAÇÃO DE BANIMENTO (BLOCO DE BLOQUEIO) ---
$ip_atual = get_client_ip();
$ban_check = get_ban_status($ip_atual, $arquivo_ban_temp, $arquivo_ban_perm);

if ($ban_check['status'] !== 'NONE') {
    // Prepara as variáveis de sessão para a página bloqueado.php
    $_SESSION['is_banned'] = true;
    $_SESSION['ban_type'] = $ban_check['status']; 
    $_SESSION['lockout_end'] = $ban_check['timestamp']; 

    // Redireciona o usuário banido
    header("Location: bloqueado.php"); 
    exit;
}
// --- FIM DA VERIFICAÇÃO DE BANIMENTO ---
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado da Média ENEM</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

    <header class="d-flex flex-column align-items-center justify-content-center text-center">
        <img src="img/Enem_logo.png" alt="Logo ENEM" class="logo-enem mb-3">
        <h1 class="titulo">Calculadora de Média ENEM</h1>
        <p class="subtitulo">Confira seu resultado abaixo</p>
    </header>

    <main class="container my-5 d-flex justify-content-center">
        <div class="card p-4 col-md-6 col-lg-4 text-center">
            <?php
                if ($_SERVER["REQUEST_METHOD"] == "POST") {
                    // Captura de notas e informações
                    $linguagens = $_POST['linguagens'];
                    $matematica = $_POST['matematica'];
                    $humanas = $_POST['humanas'];
                    $natureza = $_POST['natureza'];
                    $redacao = $_POST['redacao'];
                    $curso_escolhido = $_POST['curso_escolhido']; 
                    $modalidade_concorrencia = $_POST['modalidade_concorrencia']; // COTA_ESCOLA, COTA_RENDA, COTA_PPI, COTA_PCD ou AC
                    $genero = $_POST['genero']; 

                    // 1. Notas de corte de Ampla Concorrência (AC) representativas (2025)
                    $notas_de_corte_ac = [
                        'Medicina' => ['SISU' => 800.0, 'PROUNI' => 740.0, 'FIES' => 720.0],
                        'Direito' => ['SISU' => 730.0, 'PROUNI' => 670.0, 'FIES' => 600.0],
                        'Engenharia Civil' => ['SISU' => 700.0, 'PROUNI' => 650.0, 'FIES' => 580.0],
                        'Psicologia' => ['SISU' => 680.0, 'PROUNI' => 630.0, 'FIES' => 560.0],
                        'Administração' => ['SISU' => 650.0, 'PROUNI' => 600.0, 'FIES' => 500.0],
                        'Pedagogia' => ['SISU' => 600.0, 'PROUNI' => 550.0, 'FIES' => 455.0],
                        'Enfermagem' => ['SISU' => 680.0, 'PROUNI' => 610.0, 'FIES' => 500.0],
                        'Arquitetura' => ['SISU' => 705.0, 'PROUNI' => 660.0, 'FIES' => 590.0],
                        'Odontologia' => ['SISU' => 715.0, 'PROUNI' => 655.0, 'FIES' => 550.0],
                        'Jornalismo' => ['SISU' => 685.0, 'PROUNI' => 600.0, 'FIES' => 520.0],
                        'Farmácia' => ['SISU' => 670.0, 'PROUNI' => 620.0, 'FIES' => 510.0],
                        'Sistemas de Informação' => ['SISU' => 690.0, 'PROUNI' => 640.0, 'FIES' => 530.0]
                    ];

                    // 2. Fatores de Redução para Cotas (simulação)
                    $reducao_cota = [
                        'SISU' => 80.0,  
                        'PROUNI' => 50.0, 
                        'FIES' => 40.0   
                    ];
                    $reducao_cota_med_dir_sisu = 60.0;
                    
                    // 3. Calcula a média e formata
                    $media = ($linguagens + $matematica + $humanas + $natureza + $redacao) / 5;
                    $media_formatada = number_format($media, 2, ',', '.');
                    
                    // 4. Determina as notas de corte efetivas
                    $cortes_efetivos = [];
                    if (array_key_exists($curso_escolhido, $notas_de_corte_ac)) {
                        $cortes_base = $notas_de_corte_ac[$curso_escolhido];
                        
                        // Verifica se o usuário selecionou QUALQUER opção de cota (diferente de AC)
                        if ($modalidade_concorrencia != 'AC') {
                            $cortes_efetivos['SISU'] = $cortes_base['SISU'] - (in_array($curso_escolhido, ['Medicina', 'Direito']) ? $reducao_cota_med_dir_sisu : $reducao_cota['SISU']);
                            $cortes_efetivos['PROUNI'] = $cortes_base['PROUNI'] - $reducao_cota['PROUNI'];
                            $cortes_efetivos['FIES'] = $cortes_base['FIES'] - $reducao_cota['FIES'];
                            
                            // Garante que a nota de corte não caia abaixo do mínimo do programa (450)
                            $cortes_efetivos['PROUNI'] = max($cortes_efetivos['PROUNI'], 450.0);
                            $cortes_efetivos['FIES'] = max($cortes_efetivos['FIES'], 450.0);

                        } else {
                            $cortes_efetivos = $cortes_base; // Usa as notas AC
                        }
                    }

                    // 5. Exibe a média do usuário e análise
                    echo "<h2 class='mb-4 text-success'>Sua média é: <strong>$media_formatada</strong></h2>";

                    // Formatação do texto da modalidade para exibição
                    $modalidade_map = [
                        'AC' => 'Ampla Concorrência',
                        'COTA_ESCOLA' => 'Cotista (Escola Pública)',
                        'COTA_RENDA' => 'Cotista (Renda/Escola Pública)',
                        'COTA_PPI' => 'Cotista (Pretos, Pardos e Indígenas)',
                        'COTA_PCD' => 'Cotista (Pessoa com Deficiência)'
                    ];
                    $modalidade_texto = $modalidade_map[$modalidade_concorrencia] ?? 'Não Informado';

                    echo "<p class='mb-3 text-info'>Perfil: Gênero ($genero) | Modalidade ($modalidade_texto)</p>";

                    if ($media >= 600) {
                        echo "<p class='text-success'>Parabéns! Você mandou muito bem 🎉</p>";
                    } elseif ($media >= 500) {
                        echo "<p class='text-warning'>Tá na média, dá pra melhorar! 💪</p>";
                    } else {
                        echo "<p class='text-danger'>Fica tranquilo, o importante é continuar tentando 💜</p>";
                    }

                    // Lógica de Aprovação por Curso
                    if (!empty($cortes_efetivos)) {
                        $nome_exibicao = $curso_escolhido == 'Arquitetura' ? 'Arquitetura e Urbanismo' : $curso_escolhido;
                        
                        echo "<h3 class='mt-4 mb-3 text-info'>Análise para $nome_exibicao</h3>";
                        echo "<ul class='list-group mb-4 text-start'>";
                        
                        // SISU
                        $status_sisu = $media >= $cortes_efetivos['SISU'] ? 'Aprovado' : 'Não Atingido';
                        $class_sisu = $media >= $cortes_efetivos['SISU'] ? 'list-group-item-success' : 'list-group-item-danger';
                        echo "<li class='list-group-item $class_sisu d-flex justify-content-between align-items-center'>
                                SISU (Corte: {$cortes_efetivos['SISU']})
                                <span class='badge bg-primary rounded-pill'>$status_sisu</span>
                              </li>";

                        // PROUNI
                        $status_prouni = $media >= $cortes_efetivos['PROUNI'] ? 'Aprovado' : 'Não Atingido';
                        $class_prouni = $media >= $cortes_efetivos['PROUNI'] ? 'list-group-item-success' : 'list-group-item-danger';
                        echo "<li class='list-group-item $class_prouni d-flex justify-content-between align-items-center'>
                                ProUni (Corte: {$cortes_efetivos['PROUNI']})
                                <span class='badge bg-primary rounded-pill'>$status_prouni</span>
                              </li>";

                        // FIES
                        $status_fies = $media >= $cortes_efetivos['FIES'] ? 'Aprovado' : 'Não Atingido';
                        $class_fies = $media >= $cortes_efetivos['FIES'] ? 'list-group-item-success' : 'list-group-item-danger';
                        echo "<li class='list-group-item $class_fies d-flex justify-content-between align-items-center'>
                                FIES (Corte: {$cortes_efetivos['FIES']})
                                <span class='badge bg-primary rounded-pill'>$status_fies</span>
                              </li>";
                        
                        echo "</ul>";

                        // NOTA DE RODAPÉ ATUALIZADA PARA text-success
                        echo "<small class='text-success'>*As notas de corte são **estimativas** baseadas em médias de 2025 para Ampla Concorrência, com uma redução simulada para a modalidade Cotista. Os valores reais variam amplamente.</small>";
                    } else {
                        echo "<p class='text-warning'>Curso escolhido não encontrado na base de dados de corte. Apenas sua média foi calculada.</p>";
                    }
                }
            ?>
            <a href="inicio.php" class="btn btn-custom mt-4 w-100">Voltar</a>
        </div>
    </main>

    <footer class="text-center mt-auto py-3">
        <p>© 2025 Calculadora ENEM | Desenvolvido por klucas2001</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>