<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';

// Se já estiver logado, vai direto para o dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $senha = $_POST['senha'];

    $query = "SELECT * FROM usuarios WHERE email = '$email'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $usuario_db = mysqli_fetch_assoc($result);
        
        // Verificar senha (suporta password_hash)
        if (password_verify($senha, $usuario_db['senha'])) {
            $_SESSION['user_id']     = $usuario_db['id_usuario'];
            $_SESSION['user_nome']   = $usuario_db['nome'];
            $_SESSION['user_email']  = $usuario_db['email'];
            $_SESSION['user_perfil'] = $usuario_db['perfil'];
            $_SESSION['user_foto']   = $usuario_db['foto_perfil'] ?? '';
            registrar_log('Login realizado');
            
            header("Location: dashboard.php");
            exit;
        } else {
            $erro = "E-mail ou senha incorretos!";
        }
    } else {
        $erro = "E-mail ou senha incorretos!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão - Login</title>
    
    <!-- Fonte do Google Fonts para ficar parecido com o design -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="app-container">
        <!-- Elementos decorativos (Curvas do fundo simétricas) -->
        <div class="shape-top"></div>
        <div class="shape-bottom"></div>

        <!-- Conteúdo principal -->
        <div class="content">
            
            <!-- Coluna da Esquerda (Logo) -->
            <div class="login-left">
                <div class="logo-circle">
                    <img src="fotologo.png" alt="Logo AgroGestão" class="logo-img">
                </div>
            </div>

            <!-- Coluna da Direita (Textos e Formulário) -->
            <div class="login-right">
                <h1>Bem vindo ao AgroGestão!<br><strong>Faça seu Login</strong></h1>

                <?php if (!empty($erro)): ?>
                    <div style="background-color: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; font-weight: 700; text-align: center;">
                        <?php echo htmlspecialchars($erro); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['cadastro']) && $_GET['cadastro'] === 'sucesso'): ?>
                    <div style="background-color: #d1fae5; color: #10b981; border: 1px solid #6ee7b7; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; font-weight: 700; text-align: center;">
                        Cadastro realizado com sucesso! Faça login.
                    </div>
                <?php endif; ?>

                <!-- Formulário de Login -->
                <form action="index.php" method="POST">
                    <div class="input-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" placeholder="nome@gmail.com" required>
                    </div>

                    <div class="input-group">
                        <label for="senha">Senha</label>
                        <input type="password" id="senha" name="senha" placeholder="insira sua senha" required>
                    </div>

                    <button type="submit" class="btn-entrar">Entrar</button>
                </form>

                <p class="register-link">
                    Ainda não possui uma conta? <a href="telacadastro.php">Cadastre-se</a>
                </p>
            </div>
            
        </div>
    </div>

</body>
</html>
