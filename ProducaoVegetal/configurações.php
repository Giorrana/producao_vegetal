<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';
verificar_login();

$user_id    = $_SESSION['user_id'];
$user_nome  = $_SESSION['user_nome'];
$user_email = $_SESSION['user_email'];
$user_perfil= $_SESSION['user_perfil'];
$iniciais   = obter_iniciais($user_nome);

// Load current foto_perfil from DB
$stmt_fp = $conn->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario = ?");
$stmt_fp->bind_param("i", $user_id);
$stmt_fp->execute();
$row_fp = $stmt_fp->get_result()->fetch_assoc();
$foto_perfil_atual = $row_fp['foto_perfil'] ?? '';

$msg_ok  = '';
$msg_err = '';

// ── AJAX: Atualizar Perfil (nome/email) ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'update_profile') {
        $novo_nome  = trim($_POST['nome']  ?? '');
        $novo_email = trim($_POST['email'] ?? '');

        if (empty($novo_nome) || empty($novo_email) || !filter_var($novo_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok'=>false,'msg'=>'Nome e e-mail válidos são obrigatórios.']); exit;
        }

        // Check email uniqueness (excluding self)
        $stmt_c = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
        $stmt_c->bind_param("si", $novo_email, $user_id);
        $stmt_c->execute();
        if ($stmt_c->get_result()->num_rows > 0) {
            echo json_encode(['ok'=>false,'msg'=>'Este e-mail já está em uso por outro usuário.']); exit;
        }

        $stmt = $conn->prepare("UPDATE usuarios SET nome = ?, email = ? WHERE id_usuario = ?");
        $stmt->bind_param("ssi", $novo_nome, $novo_email, $user_id);
        if ($stmt->execute()) {
            $_SESSION['user_nome']  = $novo_nome;
            $_SESSION['user_email'] = $novo_email;
            echo json_encode(['ok'=>true,'nome'=>$novo_nome,'email'=>$novo_email]);
        } else {
            echo json_encode(['ok'=>false,'msg'=>'Erro ao salvar: '.$stmt->error]);
        }
        exit;
    }

    if ($_POST['action'] === 'update_password') {
        $atual  = $_POST['senha_atual']   ?? '';
        $nova   = $_POST['nova_senha']    ?? '';
        $conf   = $_POST['confirmar_senha']?? '';

        if (empty($atual) || empty($nova) || empty($conf)) {
            echo json_encode(['ok'=>false,'msg'=>'Preencha todos os campos de senha.']); exit;
        }
        if ($nova !== $conf) {
            echo json_encode(['ok'=>false,'msg'=>'A nova senha e a confirmação não conferem.']); exit;
        }
        if (strlen($nova) < 6) {
            echo json_encode(['ok'=>false,'msg'=>'A nova senha deve ter pelo menos 6 caracteres.']); exit;
        }

        // Verify current password
        $stmt_v = $conn->prepare("SELECT senha FROM usuarios WHERE id_usuario = ?");
        $stmt_v->bind_param("i", $user_id);
        $stmt_v->execute();
        $row_v = $stmt_v->get_result()->fetch_assoc();

        if (!$row_v || !password_verify($atual, $row_v['senha'])) {
            echo json_encode(['ok'=>false,'msg'=>'Senha atual incorreta.']); exit;
        }

        $hash = password_hash($nova, PASSWORD_BCRYPT);
        $stmt_u = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id_usuario = ?");
        $stmt_u->bind_param("si", $hash, $user_id);
        if ($stmt_u->execute()) {
            echo json_encode(['ok'=>true]);
        } else {
            echo json_encode(['ok'=>false,'msg'=>'Erro ao atualizar senha.']);
        }
        exit;
    }

    // ── Update Photo ──────────────────────────────────────────────────────────
    if ($_POST['action'] === 'update_photo') {
        $foto_tipo = trim($_POST['foto_tipo'] ?? 'url'); // 'url' or 'upload'
        $nova_foto = '';

        if ($foto_tipo === 'url') {
            $url = trim($_POST['foto_url'] ?? '');
            if (empty($url)) {
                // Allow clearing the photo
                $nova_foto = '';
            } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
                echo json_encode(['ok'=>false,'msg'=>'URL de foto inválida.']); exit;
            } else {
                $nova_foto = $url;
            }
        } elseif ($foto_tipo === 'upload') {
            if (!isset($_FILES['foto_arquivo']) || $_FILES['foto_arquivo']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['ok'=>false,'msg'=>'Erro ao receber o arquivo. Verifique o tamanho (máx. 2MB).']); exit;
            }
            $file = $_FILES['foto_arquivo'];
            $allowed_types = ['image/jpeg','image/png','image/gif','image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowed_types)) {
                echo json_encode(['ok'=>false,'msg'=>'Tipo de arquivo inválido. Use JPG, PNG, GIF ou WEBP.']); exit;
            }
            if ($file['size'] > 2 * 1024 * 1024) {
                echo json_encode(['ok'=>false,'msg'=>'Arquivo muito grande. Máximo 2MB.']); exit;
            }
            $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'][$mime];
            $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            $upload_dir = __DIR__ . '/uploads/avatars/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            // Delete old uploaded avatar if exists
            $stmt_old = $conn->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario = ?");
            $stmt_old->bind_param("i", $user_id);
            $stmt_old->execute();
            $old_row = $stmt_old->get_result()->fetch_assoc();
            if (!empty($old_row['foto_perfil']) && strpos($old_row['foto_perfil'], 'uploads/avatars/') !== false) {
                $old_file = __DIR__ . '/' . $old_row['foto_perfil'];
                if (file_exists($old_file)) @unlink($old_file);
            }
            if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                echo json_encode(['ok'=>false,'msg'=>'Falha ao salvar o arquivo no servidor.']); exit;
            }
            $nova_foto = 'uploads/avatars/' . $filename;
        }

        $stmt_photo = $conn->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id_usuario = ?");
        $stmt_photo->bind_param("si", $nova_foto, $user_id);
        if ($stmt_photo->execute()) {
            $_SESSION['user_foto'] = $nova_foto;
            echo json_encode(['ok'=>true,'foto'=>$nova_foto]);
        } else {
            echo json_encode(['ok'=>false,'msg'=>'Erro ao salvar foto: '.$stmt_photo->error]);
        }
        exit;
    }
}

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
    <script>if (localStorage.getItem('agro_theme') === 'dark') document.documentElement.classList.add('dark-theme');</script>
    <style>
        .cfg-section { margin-bottom: 20px; }
        .cfg-input { width:100%; padding:11px 14px; border:1.5px solid var(--border-color); border-radius:12px; font-size:14px; background:var(--form-bg,#f9fafb); color:var(--dark-green); font-family:inherit; box-sizing:border-box; }
        .cfg-input:focus { outline:none; border-color:#22c55e; }
        .cfg-label { display:block; font-size:11px; font-weight:800; color:var(--text-gray); text-transform:uppercase; letter-spacing:.5px; margin-bottom:5px; }
        .cfg-field { margin-bottom:12px; }
        .cfg-btn { display:inline-flex; align-items:center; gap:7px; padding:11px 22px; border:none; border-radius:12px; font-size:14px; font-weight:800; cursor:pointer; font-family:inherit; background:linear-gradient(135deg,#16a34a,#22c55e); color:white; width:100%; justify-content:center; }
        .cfg-btn:hover { filter:brightness(1.05); }
        .cfg-btn.secondary { background:var(--form-bg,#f9fafb); color:var(--dark-green); border:1.5px solid var(--border-color); }
        .cfg-msg { padding:10px 14px; border-radius:10px; font-size:13px; font-weight:700; margin-top:10px; display:none; }
        .cfg-msg.ok  { background:#d1fae5; color:#10b981; border:1px solid #6ee7b7; }
        .cfg-msg.err { background:#fee2e2; color:#ef4444; border:1px solid #fca5a5; }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-btn" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
                <div class="topbar-title">Configurações</div>
            </div>
        </header>
        <main class="main-content">
            <div class="content-wrapper">

                <div class="page-header">
                    <h1>Ajustes da Conta</h1>
                    <p>Gerencie seu perfil, segurança e preferências do sistema.</p>
                </div>

                <!-- CARD PERFIL: View -->
                <div class="settings-card">
                    <div class="profile-row">
                        <div class="profile-info">
                            <div class="profile-avatar" id="avatar-disp" style="background:var(--primary-green);color:white;display:flex;justify-content:center;align-items:center;font-size:24px;font-weight:800;border-radius:16px;width:60px;height:60px;flex-shrink:0;overflow:hidden;">
                                <?php if (!empty($foto_perfil_atual)): ?>
                                    <img id="avatar-img" src="<?php echo htmlspecialchars($foto_perfil_atual); ?>" alt="Foto de Perfil" style="width:100%;height:100%;object-fit:cover;border-radius:16px;">
                                <?php else: ?>
                                    <span id="avatar-iniciais"><?php echo htmlspecialchars($iniciais); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="profile-text">
                                <h2 id="disp-nome"><?php echo htmlspecialchars($user_nome); ?></h2>
                                <p id="disp-email"><?php echo htmlspecialchars($user_email); ?></p>
                                <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:10px;font-weight:800;text-transform:uppercase;background:<?php echo $user_perfil==='admin'?'#ef4444':($user_perfil==='operador'?'#3b82f6':'#9ca3af'); ?>;color:white;margin-top:5px;">
                                    <?php echo $user_perfil==='admin'?'Administrador':($user_perfil==='operador'?'Operador':'Visitante'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CARD: Editar Perfil -->
                <div class="settings-card cfg-section">
                    <h3 class="section-title"><i class="fa-solid fa-user-pen" style="color:var(--primary-green);margin-right:8px;"></i>Editar Perfil</h3>
                    <div class="cfg-field">
                        <label class="cfg-label" for="inp-nome">Nome Completo</label>
                        <input class="cfg-input" id="inp-nome" type="text" value="<?php echo htmlspecialchars($user_nome); ?>" placeholder="Seu nome">
                    </div>
                    <div class="cfg-field">
                        <label class="cfg-label" for="inp-email">E-mail</label>
                        <input class="cfg-input" id="inp-email" type="email" value="<?php echo htmlspecialchars($user_email); ?>" placeholder="Seu e-mail">
                    </div>
                    <button class="cfg-btn" id="btn-save-perfil" onclick="salvarPerfil()">
                        <i class="fa-solid fa-floppy-disk"></i> Salvar Alterações
                    </button>
                    <div class="cfg-msg" id="msg-perfil"></div>
                </div>

                <!-- CARD: Alterar Senha -->
                <div class="settings-card cfg-section">
                    <h3 class="section-title"><i class="fa-solid fa-lock" style="color:#3b82f6;margin-right:8px;"></i>Alterar Senha</h3>
                    <div class="cfg-field">
                        <label class="cfg-label" for="inp-senha-atual">Senha Atual</label>
                        <input class="cfg-input" id="inp-senha-atual" type="password" placeholder="••••••">
                    </div>
                    <div class="cfg-field">
                        <label class="cfg-label" for="inp-nova-senha">Nova Senha</label>
                        <input class="cfg-input" id="inp-nova-senha" type="password" placeholder="Mínimo 6 caracteres">
                    </div>
                    <div class="cfg-field">
                        <label class="cfg-label" for="inp-conf-senha">Confirmar Nova Senha</label>
                        <input class="cfg-input" id="inp-conf-senha" type="password" placeholder="Repita a nova senha">
                    </div>
                    <button class="cfg-btn" id="btn-save-senha" onclick="salvarSenha()">
                        <i class="fa-solid fa-key"></i> Atualizar Senha
                    </button>
                    <div class="cfg-msg" id="msg-senha"></div>
                </div>

                <!-- CARD: Preferências -->
                <div class="settings-card cfg-section">
                    <h3 class="section-title"><i class="fa-solid fa-sliders" style="color:#f59e0b;margin-right:8px;"></i>Preferências</h3>
                    <div class="settings-list">
                        <div class="settings-item">
                            <div class="item-label"><i class="fa-regular fa-moon"></i> Tema Escuro</div>
                            <label class="switch">
                                <input type="checkbox" id="theme-toggle">
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="settings-item">
                            <div class="item-label"><i class="fa-regular fa-bell"></i> Notificações push</div>
                            <label class="switch">
                                <input type="checkbox" id="notif-toggle" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="settings-item">
                            <div class="item-label"><i class="fa-solid fa-droplet"></i> Lembretes de irrigação</div>
                            <label class="switch">
                                <input type="checkbox" id="irrig-toggle" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- CARD: Foto de Perfil -->
                <div class="settings-card cfg-section">
                    <h3 class="section-title"><i class="fa-solid fa-camera" style="color:#8b5cf6;margin-right:8px;"></i>Foto de Perfil</h3>
                    <!-- Tab switcher -->
                    <div style="display:flex;gap:8px;margin-bottom:14px;">
                        <button id="tab-url" class="cfg-btn" style="flex:1;" onclick="switchFotoTab('url')">
                            <i class="fa-solid fa-link"></i> URL
                        </button>
                        <button id="tab-upload" class="cfg-btn secondary" style="flex:1;" onclick="switchFotoTab('upload')">
                            <i class="fa-solid fa-upload"></i> Enviar Arquivo
                        </button>
                    </div>
                    <!-- URL tab -->
                    <div id="painel-url">
                        <div class="cfg-field">
                            <label class="cfg-label">URL da Foto de Perfil</label>
                            <input class="cfg-input" id="inp-foto-url" type="url" placeholder="https://exemplo.com/foto.jpg" value="<?php echo (strpos($foto_perfil_atual,'http')===0)?htmlspecialchars($foto_perfil_atual):''; ?>">
                        </div>
                        <button class="cfg-btn" onclick="salvarFotoUrl()">
                            <i class="fa-solid fa-floppy-disk"></i> Salvar URL
                        </button>
                    </div>
                    <!-- Upload tab -->
                    <div id="painel-upload" style="display:none;">
                        <div class="cfg-field">
                            <label class="cfg-label">Arquivo de Imagem (JPG, PNG, GIF ou WEBP — máx. 2MB)</label>
                            <input class="cfg-input" id="inp-foto-arquivo" type="file" accept="image/jpeg,image/png,image/gif,image/webp" style="padding:8px;">
                        </div>
                        <button class="cfg-btn" onclick="salvarFotoUpload()">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Fazer Upload
                        </button>
                    </div>
                    <!-- Remove photo -->
                    <?php if (!empty($foto_perfil_atual)): ?>
                    <div style="margin-top:10px;">
                        <button class="cfg-btn secondary" onclick="removerFoto()">
                            <i class="fa-solid fa-trash-can" style="color:#ef4444;"></i> Remover Foto
                        </button>
                    </div>
                    <?php endif; ?>
                    <div class="cfg-msg" id="msg-foto"></div>
                </div>

                <!-- CARD: Conta -->
                <div class="settings-card cfg-section">
                    <h3 class="section-title">Conta</h3>
                    <div class="settings-list">
                        <a href="logout.php" class="settings-item text-red">
                            <div class="item-label"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair da Conta</div>
                            <i class="fa-solid fa-chevron-right action-chevron"></i>
                        </a>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>
<script>
    function toggleMenu() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('overlay').classList.toggle('active');
    }

    // ── Dark Mode ────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const themeToggle = document.getElementById('theme-toggle');
        if (localStorage.getItem('agro_theme') === 'dark') themeToggle.checked = true;
        themeToggle.addEventListener('change', function() {
            document.documentElement.classList.toggle('dark-theme', this.checked);
            localStorage.setItem('agro_theme', this.checked ? 'dark' : 'light');
        });

        // Persist notification prefs in localStorage
        ['notif-toggle','irrig-toggle'].forEach(id => {
            const el = document.getElementById(id);
            el.checked = localStorage.getItem(id) !== 'false';
            el.addEventListener('change', () => localStorage.setItem(id, el.checked));
        });
    });

    // ── Save Profile ─────────────────────────────────────────────────────────
    async function salvarPerfil() {
        const btn = document.getElementById('btn-save-perfil');
        const msg = document.getElementById('msg-perfil');
        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';

        const data = new FormData();
        data.append('action', 'update_profile');
        data.append('nome',   document.getElementById('inp-nome').value);
        data.append('email',  document.getElementById('inp-email').value);

        try {
            const res  = await fetch('configurações.php', { method:'POST', body:data });
            const json = await res.json();
            showMsg(msg, json.ok, json.ok ? 'Perfil atualizado com sucesso!' : json.msg);
            if (json.ok) {
                document.getElementById('disp-nome').textContent  = json.nome;
                document.getElementById('disp-email').textContent = json.email;
                // Update localStorage for other pages
                localStorage.setItem('agro_user_nome', json.nome);
                localStorage.setItem('agro_user_email', json.email);
            }
        } catch(e) { showMsg(msg, false, 'Erro de conexão.'); }
        btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar Alterações';
    }

    // ── Change Password ───────────────────────────────────────────────────────
    async function salvarSenha() {
        const btn = document.getElementById('btn-save-senha');
        const msg = document.getElementById('msg-senha');
        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Atualizando...';

        const data = new FormData();
        data.append('action',          'update_password');
        data.append('senha_atual',     document.getElementById('inp-senha-atual').value);
        data.append('nova_senha',      document.getElementById('inp-nova-senha').value);
        data.append('confirmar_senha', document.getElementById('inp-conf-senha').value);

        try {
            const res  = await fetch('configurações.php', { method:'POST', body:data });
            const json = await res.json();
            showMsg(msg, json.ok, json.ok ? '✅ Senha alterada com sucesso!' : json.msg);
            if (json.ok) {
                document.getElementById('inp-senha-atual').value = '';
                document.getElementById('inp-nova-senha').value = '';
                document.getElementById('inp-conf-senha').value = '';
            }
        } catch(e) { showMsg(msg, false, 'Erro de conexão.'); }
        btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-key"></i> Atualizar Senha';
    }

    function showMsg(el, isOk, text) {
        el.textContent = text;
        el.className   = 'cfg-msg ' + (isOk ? 'ok' : 'err');
        el.style.display = 'block';
        setTimeout(() => el.style.display='none', 4000);
    }

    // ── Photo Tab Switcher ────────────────────────────────────────────────────
    function switchFotoTab(tab) {
        document.getElementById('painel-url').style.display    = tab === 'url'    ? 'block' : 'none';
        document.getElementById('painel-upload').style.display = tab === 'upload' ? 'block' : 'none';
        document.getElementById('tab-url').className    = 'cfg-btn' + (tab === 'url'    ? '' : ' secondary');
        document.getElementById('tab-upload').className = 'cfg-btn' + (tab === 'upload' ? '' : ' secondary');
    }

    // ── Update Avatar preview helper ─────────────────────────────────────────
    function updateAvatarPreview(fotoSrc) {
        const disp = document.getElementById('avatar-disp');
        if (fotoSrc) {
            disp.innerHTML = `<img src="${fotoSrc}" alt="Foto de Perfil" style="width:100%;height:100%;object-fit:cover;border-radius:16px;">`;
        } else {
            disp.innerHTML = `<span id="avatar-iniciais"><?php echo htmlspecialchars($iniciais); ?></span>`;
        }
    }

    // ── Save Photo via URL ───────────────────────────────────────────────────
    async function salvarFotoUrl() {
        const msg  = document.getElementById('msg-foto');
        const url  = document.getElementById('inp-foto-url').value.trim();
        const data = new FormData();
        data.append('action', 'update_photo');
        data.append('foto_tipo', 'url');
        data.append('foto_url', url);
        try {
            const res  = await fetch('configurações.php', { method:'POST', body:data });
            const json = await res.json();
            showMsg(msg, json.ok, json.ok ? '✅ Foto atualizada com sucesso!' : json.msg);
            if (json.ok) updateAvatarPreview(json.foto);
        } catch(e) { showMsg(msg, false, 'Erro de conexão.'); }
    }

    // ── Save Photo via File Upload ────────────────────────────────────────────
    async function salvarFotoUpload() {
        const msg   = document.getElementById('msg-foto');
        const input = document.getElementById('inp-foto-arquivo');
        if (!input.files || !input.files[0]) {
            showMsg(msg, false, 'Selecione um arquivo primeiro.'); return;
        }
        const file = input.files[0];
        if (file.size > 2 * 1024 * 1024) {
            showMsg(msg, false, 'Arquivo muito grande. Máximo 2MB.'); return;
        }
        const data = new FormData();
        data.append('action', 'update_photo');
        data.append('foto_tipo', 'upload');
        data.append('foto_arquivo', file);
        try {
            const res  = await fetch('configurações.php', { method:'POST', body:data });
            const json = await res.json();
            showMsg(msg, json.ok, json.ok ? '✅ Foto enviada com sucesso!' : json.msg);
            if (json.ok) updateAvatarPreview(json.foto);
        } catch(e) { showMsg(msg, false, 'Erro de conexão.'); }
    }

    // ── Remove Photo ─────────────────────────────────────────────────────────
    async function removerFoto() {
        if (!confirm('Deseja remover sua foto de perfil?')) return;
        const msg  = document.getElementById('msg-foto');
        const data = new FormData();
        data.append('action', 'update_photo');
        data.append('foto_tipo', 'url');
        data.append('foto_url', '');
        try {
            const res  = await fetch('configurações.php', { method:'POST', body:data });
            const json = await res.json();
            showMsg(msg, json.ok, json.ok ? '✅ Foto removida.' : json.msg);
            if (json.ok) updateAvatarPreview('');
        } catch(e) { showMsg(msg, false, 'Erro de conexão.'); }
    }
</script>
</body>
</html>
