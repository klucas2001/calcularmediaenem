<?php
    session_start();
    // Inclui o logger invasivo. Esta é a primeira coisa a ser executada na página.
    include 'iplogger.php';

    // Captura e limpa o erro de login (para o modal)
    $login_error = $_SESSION['login_error'] ?? null;
    unset($_SESSION['login_error']); 

    // Captura e limpa o erro de ACESSO (para o topo da página)
    $access_error = $_SESSION['access_error'] ?? null;
    unset($_SESSION['access_error']); // Limpa a mensagem após exibir
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculadora de Média ENEM</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">

    <style>
        /* Estilo para o botão de login no canto superior direito */
        .login-btn-container {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 1050; 
        }
        /* Estilização básica do Modal para seguir o tema escuro */
        .modal-content {
            background-color: #343a40; 
            color: #f8f9fa; 
        }
        .modal-header {
            border-bottom: 1px solid #495057; 
        }
    </style>
</head>
<body>
    
    <div class="login-btn-container">
        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#loginModal">
            <i class="bi bi-person-lock"></i> Área Restrita
        </button>
    </div>

    <?php if ($access_error): ?>
        <div class="container my-3">
            <div class="alert alert-danger text-center" role="alert">
                <?php echo htmlspecialchars($access_error); ?>
            </div>
        </div>
    <?php endif; ?>

    <header class="d-flex flex-column align-items-center justify-content-center text-center">
        <h1 class="titulo">Calculadora de Média ENEM</h1>
        <p class="subtitulo">Descubra a sua média do ENEM de forma facil!</p>
    </header>

    <main class="container my-5 d-flex justify-content-center">
        <div class="card p-4 col-md-6 col-lg-4">
            <h2 class="text-center mb-4 text-white">Insira suas notas</h2>
            
            <form method="POST" action="calcular.php">
                
                <div class="mb-3">
                    <label class="form-label">Qual é o seu Gênero?</label>
                    <select class="form-select form-control" name="genero" required>
                        <option value="Não Informar">Prefiro não informar</option>
                        <option value="Feminino">Feminino</option>
                        <option value="Masculino">Masculino</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Você faz parte de alguma cota?</label>
                    <select class="form-select form-control" name="modalidade_concorrencia" required>
                        <option value="AC">Ampla Concorrência (Não sou cotista)</option>
                        <option value="COTA_ESCOLA">Sim, cota de Escola Pública (Geral)</option>
                        <option value="COTA_RENDA">Sim, cota de Renda (L1 e L9)</option>
                        <option value="COTA_PPI">Sim, cota PPI (Pretos, Pardos e Indígenas - L2, L6, L10, L14)</option>
                        <option value="COTA_PCD">Sim, cota PCD (Pessoa com Deficiência - L5, L6, L13, L14)</option>
                    </select>
                    <small class="form-text text-white-50">Selecione a opção que melhor se aplica.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Escolha o Curso Desejado</label>
                    <select class="form-select form-control" name="curso_escolhido" required>
                        <option value="">Selecione um curso</option>
                        <option value="Medicina">Medicina</option>
                        <option value="Direito">Direito</option>
                        <option value="Engenharia Civil">Engenharia Civil</option>
                        <option value="Psicologia">Psicologia</option>
                        <option value="Administração">Administração</option>
                        <option value="Pedagogia">Pedagogia</option>
                        <option value="Enfermagem">Enfermagem</option>
                        <option value="Arquitetura">Arquitetura e Urbanismo</option>
                        <option value="Odontologia">Odontologia</option>
                        <option value="Jornalismo">Jornalismo</option>
                        <option value="Farmácia">Farmácia</option>
                        <option value="Sistemas de Informação">Sistemas de Informação</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Linguagens</label>
                    <input type="number" step="0.1" class="form-control" name="linguagens" required onkeydown="limitarApenasNumeros(event)" min="0" max="1000">
                </div>
                <div class="mb-3">
                    <label class="form-label">Matemática</label>
                    <input type="number" step="0.1" class="form-control" name="matematica" required onkeydown="limitarApenasNumeros(event)" min="0" max="1000">
                </div>
                <div class="mb-3">
                    <label class="form-label">Ciências Humanas</label>
                    <input type="number" step="0.1" class="form-control" name="humanas" required onkeydown="limitarApenasNumeros(event)" min="0" max="1000">
                </div>
                <div class="mb-3">
                    <label class="form-label">Ciências da Natureza</label>
                    <input type="number" step="0.1" class="form-control" name="natureza" required onkeydown="limitarApenasNumeros(event)" min="0" max="1000">
                </div>
                <div class="mb-3">
                    <label class="form-label">Redação</label>
                    <input type="number" step="0.1" class="form-control" name="redacao" required onkeydown="limitarApenasNumeros(event)" min="0" max="1000">
                </div>

                <button type="submit" class="btn btn-custom w-100 mt-3">Calcular</button>
            </form>
        </div>
    </main>

    <footer class="text-center mt-auto py-3">
        <p>© 2025 Calculadora ENEM | Desenvolvido por klucas2001</p>
    </footer>

    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loginModalLabel">Acesso Restrito</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="login.php"> 
                    <div class="modal-body">
                        <?php if ($login_error): ?>
                        <div class="alert alert-danger" role="alert" id="login-error-alert"><?php echo htmlspecialchars($login_error); ?></div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Usuário (admin)</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Senha (admin)</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        <button type="submit" class="btn btn-info" id="loginModalButton">Entrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Script para limitar a entrada de caracteres nos campos de nota
        function limitarApenasNumeros(event) {
            const key = event.key;
            
            // Permite teclas de controle
            if (event.keyCode === 8 || event.keyCode === 46 || event.keyCode === 37 || event.keyCode === 39 || event.keyCode === 9 || event.keyCode === 13) {
                return;
            }

            // Impede a entrada da letra 'e', '+', e '-'
            if (key === 'e' || key === 'E' || key === '+' || key === '-') {
                event.preventDefault();
            }
        }
        
        // --- LÓGICA DE EXIBIÇÃO AUTOMÁTICA DO MODAL ---
        document.addEventListener('DOMContentLoaded', function() {
            // Abre o modal automaticamente se houver erro de login
            <?php if ($login_error): ?>
                var loginModalElement = document.getElementById('loginModal');
                var loginModal = new bootstrap.Modal(loginModalElement);
                loginModal.show();
            <?php endif; ?>
        });
    </script>
</body>
</html>