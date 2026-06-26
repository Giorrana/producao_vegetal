<?php
require_once '../Banco/conecao.php';
require_once 'auth.php';

// Garantir login e restringir a admin
verificar_login();
restringir_pagina_visitante();

$msg_erro = "";
$msg_sucesso = "";

// Lógica de Remoção de Visitante
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_del = intval($_GET['id']);
    
    // Garantir que não pode deletar a si mesmo ou outro admin por segurança
    $check_user = mysqli_query($conexao, "SELECT perfil FROM usuarios WHERE id_usuario = $id_del");
    if ($check_user && $user_data = mysqli_fetch_assoc($check_user)) {
        if ($user_data['perfil'] === 'admin') {
            $msg_erro = "Ação inválida! Não é possível remover uma conta administradora.";
        } else {
            $delete_query = "DELETE FROM usuarios WHERE id_usuario = $id_del AND perfil = 'visitante'";
            if (mysqli_query($conexao, $delete_query)) {
                $msg_sucesso = "Usuário visitante removido com sucesso!";
            } else {
                $msg_erro = "Erro ao remover usuário: " . mysqli_error($conexao);
            }
        }
    } else {
        $msg_erro = "Usuário não encontrado.";
    }
}

// Buscar todos os visitantes
$query = "SELECT * FROM usuarios WHERE perfil = 'visitante' ORDER BY nome ASC";
$result = mysqli_query($conexao, $query);
$visitantes = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $visitantes[] = $row;
    }
}

$activePage = 'usuarios';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão - Usuários</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">

    <script>
        if (localStorage.getItem('agro_theme') === 'dark') {
            document.documentElement.classList.add('dark-theme');
        }
    </script>
</head>
<body>
    <div class="app-layout">
        
        <!-- SIDEBAR -->
        <?php include 'sidebar.php'; ?>

        <div class="main-wrapper">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="menu-btn" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
                </div>
                <div class="topbar-title">Controle de Usuários</div>
            </header>

            <main class="main-content">
                <div class="content-wrapper">
                    
                    <div class="page-header">
                        <h1>Gerenciamento de Visitantes</h1>
                        <p>Visualize e remova as contas de visitantes cadastradas no sistema.</p>
                    </div>

                    <?php if (!empty($msg_erro)): ?>
                        <div style="background-color: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 700;">
                            <?php echo htmlspecialchars($msg_erro); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($msg_sucesso)): ?>
                        <div style="background-color: #d1fae5; color: #10b981; border: 1px solid #6ee7b7; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 700;">
                            <?php echo htmlspecialchars($msg_sucesso); ?>
                        </div>
                    <?php endif; ?>

                    <div class="estoque-list" id="usuarios-container">
                        <?php if (count($visitantes) === 0): ?>
                            <div class="empty-state">Nenhum usuário visitante cadastrado no sistema.</div>
                        <?php else: ?>
                            <?php foreach ($visitantes as $v): 
                                $iniciais = obter_iniciais($v['nome']);
                            ?>
                                <div class="estoque-card" style="align-items: center;">
                                    <div class="item-left" style="align-items: center;">
                                        <div class="user-avatar-sidebar" style="margin-bottom: 0; width: 45px; height: 45px; font-size: 16px; background-color: var(--primary-green); color: white; display: flex; justify-content: center; align-items: center; border-radius: 12px; font-weight: 800;">
                                            <?php echo htmlspecialchars($iniciais); ?>
                                        </div>
                                        <div class="item-info" style="margin-left: 15px;">
                                            <h4 style="margin: 0; font-size: 16px; color: var(--text-main);"><?php echo htmlspecialchars($v['nome']); ?></h4>
                                            <p style="margin: 3px 0 0 0; font-size: 13px; color: var(--text-gray);"><?php echo htmlspecialchars($v['email']); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="item-right">
                                        <div class="action-btns">
                                            <a href="usuarios.php?action=delete&id=<?php echo $v['id_usuario']; ?>" 
                                               class="btn-action btn-delete" 
                                               style="background-color: #fee2e2; color: #ef4444; border: 1px solid #fca5a5;"
                                               onclick="return confirm('Deseja realmente remover este usuário visitante? Esta ação não pode ser desfeita.')">
                                                <i class="fa-solid fa-user-minus"></i> Remover
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <script>
        function toggleMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
    </script>
</body>
</html>
