<?php
require_once 'auth.php';
$user_nome    = isset($_SESSION['user_nome'])    ? $_SESSION['user_nome']    : 'Convidado';
$user_email   = isset($_SESSION['user_email'])   ? $_SESSION['user_email']   : 'convidado@email.com';
$user_perfil  = isset($_SESSION['user_perfil'])  ? $_SESSION['user_perfil']  : 'visitante';
$user_foto    = isset($_SESSION['user_foto'])    ? $_SESSION['user_foto']    : '';
$user_iniciais = obter_iniciais($user_nome);
// Load foto_perfil from DB if not in session
if (empty($user_foto) && isset($_SESSION['user_id'])) {
    require_once '../Banco/conexao.php';
    $uid = (int)$_SESSION['user_id'];
    $r = $conn->query("SELECT foto_perfil FROM usuarios WHERE id_usuario = $uid");
    if ($r && $row_foto = $r->fetch_assoc()) {
        $user_foto = $row_foto['foto_perfil'] ?? '';
        $_SESSION['user_foto'] = $user_foto;
    }
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <button class="close-btn" onclick="toggleMenu()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="user-avatar-sidebar" id="user-initials-sidebar" style="<?php if(!empty($user_foto)): ?>padding:0;background:transparent;border:none;<?php endif; ?>">
            <?php if (!empty($user_foto)): ?>
                <img src="<?php echo htmlspecialchars($user_foto); ?>" alt="Foto" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">
            <?php else: ?>
                <?php echo htmlspecialchars($user_iniciais); ?>
            <?php endif; ?>
        </div>
        <div class="user-info">
            <h3 id="user-name-sidebar" style="color: white; font-weight: 700;"><?php echo htmlspecialchars($user_nome); ?></h3>
            <p id="user-email-sidebar" style="color: rgba(255,255,255,0.8); font-size: 12px; margin-bottom: 5px;"><?php echo htmlspecialchars($user_email); ?></p>
            <span class="user-role-badge" style="
                display: inline-block;
                padding: 2px 8px;
                border-radius: 20px;
                font-size: 10px;
                font-weight: 800;
                text-transform: uppercase;
                background-color: <?php echo $user_perfil === 'admin' ? '#ef4444' : ($user_perfil === 'operador' ? '#3b82f6' : '#9ca3af'); ?>;
                color: white;
            ">
                <?php echo $user_perfil === 'admin' ? 'Administrador' : ($user_perfil === 'operador' ? 'Operador' : 'Visitante'); ?>
            </span>
        </div>
    </div>

    <div class="menu-title">MENU PRINCIPAL</div>

    <nav class="menu-list">
        <a href="dashboard.php" class="menu-item <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
            <i class="fa-solid fa-house"></i> Início
        </a>
        <a href="culturas_cadastradas.php" class="menu-item <?php echo $activePage === 'culturas' ? 'active' : ''; ?>">
            <i class="fa-solid fa-leaf"></i> Culturas
        </a>
        <a href="plantios_ativos.php" class="menu-item <?php echo $activePage === 'plantios' ? 'active' : ''; ?>">
            <i class="fa-solid fa-seedling"></i> Plantios
        </a>
        <a href="estoque.php" class="menu-item <?php echo $activePage === 'estoque' ? 'active' : ''; ?>">
            <i class="fa-solid fa-box"></i> Estoque
        </a>
        <a href="historico.php" class="menu-item <?php echo $activePage === 'historico' ? 'active' : ''; ?>">
            <i class="fa-solid fa-clock-rotate-left"></i> Histórico
        </a>
        <a href="relatorios.php" class="menu-item <?php echo $activePage === 'relatorios' ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-pie"></i> Relatórios
        </a>
        <a href="configurações.php" class="menu-item <?php echo $activePage === 'configuracoes' ? 'active' : ''; ?>">
            <i class="fa-solid fa-gear"></i> Configurações
        </a>
    </nav>

    <?php if ($user_perfil === 'admin'): ?>
    <div class="menu-title" style="margin-top:12px;">ADMINISTRAÇÃO</div>
    <nav class="menu-list">
        <a href="admin_painel.php" class="menu-item <?php echo $activePage === 'admin_painel' ? 'active' : ''; ?>">
            <i class="fa-solid fa-gauge-high"></i> Painel Admin
        </a>
        <a href="relatorios_avancados.php" class="menu-item <?php echo $activePage === 'relatorios_avancados' ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-bar"></i> Rel. Avançados
        </a>
        <a href="admin_log.php" class="menu-item <?php echo $activePage === 'admin_log' ? 'active' : ''; ?>">
            <i class="fa-solid fa-scroll"></i> Log de Auditoria
        </a>
        <a href="admin_usuarios.php" class="menu-item <?php echo $activePage === 'admin_usuarios' ? 'active' : ''; ?>">
            <i class="fa-solid fa-users-gear"></i> Usuários
        </a>
    </nav>
    <?php endif; ?>

    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout">
            <div><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</div>
        </a>
    </div>
</aside>
<div class="overlay" id="overlay" onclick="toggleMenu()"></div>

<script>
if ('serviceWorker' in navigator) {
    const link = document.createElement('link');
    link.rel = 'manifest';
    link.href = 'manifest.json';
    document.head.appendChild(link);

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js')
            .then(reg => console.log('Service Worker registered!', reg))
            .catch(err => console.log('Service Worker registration failed:', err));
    });
}
</script>

