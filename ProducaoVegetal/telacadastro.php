<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';

$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = mysqli_real_escape_string($conn, $_POST['usuario']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $senha = $_POST['senha'];
    $confirma_senha = $_POST['confirma-senha'];

    if ($senha !== $confirma_senha) {
        $erro = "As senhas não coincidem!";
    } else {
        // Verificar e-mail duplicado
        $check_query = "SELECT id_usuario FROM usuarios WHERE email = '$email'";
        $check_result = mysqli_query($conn, $check_query);

        if ($check_result && mysqli_num_rows($check_result) > 0) {
            $erro = "Este e-mail já está cadastrado!";
        } else {
            // Inserir usuário como visitante
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $insert_query = "INSERT INTO usuarios (nome, email, senha, perfil) VALUES ('$nome', '$email', '$senha_hash', 'visitante')";
            
            if (mysqli_query($conn, $insert_query)) {
                header("Location: index.php?cadastro=sucesso");
                exit;
            } else {
                $erro = "Erro ao cadastrar usuário: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão - Cadastro</title>
    
    <!-- Fonte do Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

    <script>
        if (localStorage.getItem('agro_theme') === 'dark') {
            document.documentElement.classList.add('dark-theme');
        }
    </script>
</head>
<body>

    <div class="app-container">
        <!-- Elementos decorativos -->
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

            <!-- Coluna da Direita (Textos e Formulário de Cadastro) -->
            <div class="login-right">
                <h1>Bem vindo ao AgroGestão!<br><strong>Cadastre-se</strong></h1>

                <?php if (!empty($erro)): ?>
                    <div style="background-color: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; font-weight: 700; text-align: center;">
                        <?php echo htmlspecialchars($erro); ?>
                    </div>
                <?php endif; ?>

                <!-- Formulário com 4 campos -->
                <form action="telacadastro.php" method="POST">
                    <div class="input-group">
                        <label for="usuario">Usuário</label>
                        <input type="text" id="usuario" name="usuario" placeholder="nome do usuário" required value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>">
                    </div>

                    <div class="input-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" placeholder="nome@gmail.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <div class="input-group">
                        <label for="senha">Senha</label>
                        <input type="password" id="senha" name="senha" placeholder="insira uma senha" required>
                    </div>

                    <div class="input-group">
                        <label for="confirma-senha">Confirme a Senha</label>
                        <input type="password" id="confirma-senha" name="confirma-senha" placeholder="insira novamente a senha" required>
                    </div>

                    <button type="submit" class="btn-entrar">Cadastre-se</button>
                </form>

                <!-- Link que "liga" de volta para a tela de Login -->
                <p class="register-link">
                    Já possui uma conta? <a href="index.php">Entrar</a>
                </p>
            </div>
            
        </div>
    </div>

</body>
</html>
