<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';

// Garantir login
verificar_login();

$user_nome = $_SESSION['user_nome'];
$user_email = $_SESSION['user_email'];
$user_perfil = $_SESSION['user_perfil'];
$iniciais = obter_iniciais($user_nome);

$activePage = 'configuracoes';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão - Configurações</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">

    <!-- SCRIPT DE TEMA (No topo para não piscar branco ao carregar a página) -->
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

        <!-- ================= MAIN CONTENT ================= -->
        <div class="main-wrapper">
            
            <!-- TOPBAR -->
            <header class="topbar">
                <div class="topbar-left">
                    <button class="menu-btn" onclick="toggleMenu()">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="topbar-title">Configurações</div>
                </div>
            </header>

            <main class="main-content">
                <div class="content-wrapper">
                    
                    <div class="page-header">
                        <h1>Ajustes da Conta</h1>
                        <p>Gerencie o seu perfil e as preferências do sistema.</p>
                    </div>

                    <!-- CARD DE PERFIL -->
                    <div class="settings-card">
                        <div class="profile-row">
                            <div class="profile-info">
                                <div class="profile-avatar" style="background-color: var(--primary-green); color: white; display: flex; justify-content: center; align-items: center; font-size: 24px; font-weight: 800; border-radius: 16px; width: 60px; height: 60px;">
                                    <?php echo htmlspecialchars($iniciais); ?>
                                </div>
                                <div class="profile-text">
                                    <h2><?php echo htmlspecialchars($user_nome); ?></h2>
                                    <p><?php echo htmlspecialchars($user_email); ?></p>
                                    <span class="user-role-badge" style="
                                        display: inline-block;
                                        padding: 2px 8px;
                                        border-radius: 20px;
                                        font-size: 10px;
                                        font-weight: 800;
                                        text-transform: uppercase;
                                        background-color: <?php echo $user_perfil === 'admin' ? '#ef4444' : '#3b82f6'; ?>;
                                        color: white;
                                        margin-top: 5px;
                                    ">
                                        <?php echo $user_perfil === 'admin' ? 'Administrador' : 'Visitante'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CARD DE PREFERÊNCIAS -->
                    <div class="settings-card">
                        <h3 class="section-title">Preferências</h3>
                        
                        <div class="settings-list">
                            <!-- Switch 1 -->
                            <div class="settings-item">
                                <div class="item-label">
                                    <i class="fa-regular fa-bell"></i> Notificações push
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <!-- Switch 2 -->
                            <div class="settings-item">
                                <div class="item-label">
                                    <i class="fa-solid fa-droplet"></i> Lembretes de rega/colheita
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <!-- Switch 3 (TEMA ESCURO) -->
                            <div class="settings-item">
                                <div class="item-label">
                                    <i class="fa-regular fa-moon"></i> Tema Escuro
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="theme-toggle">
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- CARD DE CONTA -->
                    <div class="settings-card">
                        <h3 class="section-title">Conta</h3>
                        
                        <div class="settings-list">
                            <!-- Ação 1 (Sair) -->
                            <a href="logout.php" class="settings-item text-red">
                                <div class="item-label">
                                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Sair da Conta
                                </div>
                                <i class="fa-solid fa-chevron-right action-chevron"></i>
                            </a>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <script>
        // Lógica do Menu
        function toggleMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        // Lógica do Tema Escuro
        document.addEventListener('DOMContentLoaded', () => {
            const themeToggle = document.getElementById('theme-toggle');
            
            // Marca o interruptor como ativado se o modo escuro estiver salvo
            if (localStorage.getItem('agro_theme') === 'dark') {
                themeToggle.checked = true;
            }

            // Ouve as mudanças no interruptor
            themeToggle.addEventListener('change', function() {
                if (this.checked) {
                    document.documentElement.classList.add('dark-theme');
                    localStorage.setItem('agro_theme', 'dark'); // Salva a preferência
                } else {
                    document.documentElement.classList.remove('dark-theme');
                    localStorage.setItem('agro_theme', 'light'); // Salva a preferência
                }
            });
        });
    </script>
</body>
</html>
