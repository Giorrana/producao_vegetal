<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';
verificar_login();
restringir_pagina_admin();

$id_usuario_logado = $_SESSION['user_id'];
$msg_erro = "";
$msg_sucesso = "";

// --- Lógica de Ação ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id_target = intval($_GET['id']);
    $action = $_GET['action'];

    // Buscar usuário alvo
    $q_target = $conn->query("SELECT nome, perfil FROM usuarios WHERE id_usuario = $id_target");
    $target = $q_target ? $q_target->fetch_assoc() : null;

    if (!$target) {
        $msg_erro = "Usuário não encontrado.";
    } elseif ($id_target === $id_usuario_logado) {
        $msg_erro = "Você não pode alterar seu próprio perfil ou se remover.";
    } else {
        if ($action === 'delete') {
            // Remover logs(logins) associados por segurança
            $conn->query("DELETE FROM log WHERE id_usuario = $id_target");
            
            // Remover usuário
            if ($conn->query("DELETE FROM usuarios WHERE id_usuario = $id_target")) {
                registrar_log("Usuário excluído: " . $target['nome'] . " ($id_target)");
                $msg_sucesso = "Usuário removido com sucesso!";
            } else {
                $msg_erro = "Erro ao remover usuário: " . $conn->error;
            }
        } elseif ($action === 'tornar_operador') {
            if ($conn->query("UPDATE usuarios SET perfil = 'operador' WHERE id_usuario = $id_target")) {
                registrar_log("Perfil alterado: " . $target['nome'] . " para Operador");
                $msg_sucesso = "Usuário alterado para Operador!";
            } else {
                $msg_erro = "Erro ao atualizar perfil: " . $conn->error;
            }
        } elseif ($action === 'tornar_admin') {
            if ($conn->query("UPDATE usuarios SET perfil = 'admin' WHERE id_usuario = $id_target")) {
                registrar_log("Perfil alterado: " . $target['nome'] . " para Administrador");
                $msg_sucesso = "Usuário promovido a Administrador!";
            } else {
                $msg_erro = "Erro ao atualizar perfil: " . $conn->error;
            }
        }
    }
}

// --- Lógica de Criação de Usuário ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_form']) && $_POST['acao_form'] === 'criar_usuario') {
    $nome             = trim($_POST['nome'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $senha            = $_POST['senha'] ?? '';
    $confirmar_senha  = $_POST['confirmar_senha'] ?? '';
    $perfil           = ($_POST['perfil'] ?? '') === 'admin' ? 'admin' : 'operador';

    if (empty($nome) || empty($email) || strlen($senha) < 6) {
        $msg_erro = "Preencha nome, e-mail e uma senha com pelo menos 6 caracteres.";
    } elseif ($senha !== $confirmar_senha) {
        $msg_erro = "A senha e a confirmação de senha não coincidem.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg_erro = "E-mail inválido.";
    } else {
        $stmt_chk = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
        $stmt_chk->bind_param("s", $email);
        $stmt_chk->execute();
        if ($stmt_chk->get_result()->num_rows > 0) {
            $msg_erro = "Este e-mail já está cadastrado.";
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt_ins = $conn->prepare("INSERT INTO usuarios (nome, email, senha, perfil) VALUES (?, ?, ?, ?)");
            $stmt_ins->bind_param("ssss", $nome, $email, $hash, $perfil);
            if ($stmt_ins->execute()) {
                registrar_log("Usuário criado: $nome ($perfil)");
                $msg_sucesso = "Usuário \"$nome\" criado com sucesso como " . ($perfil === 'admin' ? 'Administrador' : 'Operador') . "!";
            } else {
                $msg_erro = "Erro ao criar usuário: " . $stmt_ins->error;
            }
        }
    }
}

// --- Filtro de Busca ---
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$where_sql = "1=1";
if (!empty($busca)) {
    $escaped_busca = $conn->real_escape_string($busca);
    $where_sql = "(nome LIKE '%$escaped_busca%' OR email LIKE '%$escaped_busca%')";
}

// --- Buscar todos os usuários ---
$q_users = $conn->query("
    SELECT u.id_usuario, u.nome, u.email, u.perfil, u.foto_perfil,
           (SELECT COUNT(*) FROM log l WHERE l.id_usuario = u.id_usuario) AS n_logs,
           (SELECT MAX(l.data_operacao) FROM log l WHERE l.id_usuario = u.id_usuario) AS ultima_atividade
    FROM usuarios u
    WHERE $where_sql
    ORDER BY u.perfil ASC, u.nome ASC
");
$usuarios = $q_users ? $q_users->fetch_all(MYSQLI_ASSOC) : [];

$activePage = 'admin_usuarios';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão — Gerenciamento de Usuários</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script>if(localStorage.getItem('agro_theme')==='dark')document.documentElement.classList.add('dark-theme');</script>
    <style>
        .user-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-top: 16px; }
        .user-card {
            background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px;
            padding: 20px; display: flex; flex-direction: column; gap: 15px; position: relative;
            transition: transform .2s, box-shadow .2s;
        }
        .user-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.05); }
        .user-header { display: flex; gap: 12px; align-items: center; }
        .user-avatar { width: 50px; height: 50px; border-radius: 12px; overflow: hidden; background: var(--primary-green); color: white; display: flex; justify-content: center; align-items: center; font-size: 18px; font-weight: 800; }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-meta { flex: 1; min-width: 0; }
        .user-meta h4 { font-size: 15px; color: var(--text-main); font-weight: 700; margin: 0 0 2px; }
        .user-meta p { font-size: 12px; color: var(--text-gray); margin: 0; }

        .perfil-badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 9px; font-weight: 800; text-transform: uppercase; color: white; }
        .badge-admin { background: #ef4444; }
        .badge-operador { background: #3b82f6; }
        .badge-visitante { background: #9ca3af; }

        .user-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 11px; color: var(--text-gray); border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); padding: 8px 0; }
        .user-actions { display: flex; gap: 6px; justify-content: flex-end; }
        .btn-act { padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; border: 1.5px solid; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: all .2s; }
        .btn-act-role { border-color: var(--border-color); color: var(--text-main); background: transparent; }
        .btn-act-role:hover { border-color: var(--primary-green); color: var(--primary-green); background: var(--active-bg); }
        .btn-act-del { border-color: #fee2e2; color: #ef4444; background: #fee2e220; }
        .btn-act-del:hover { background: #ef4444; color: white; border-color: #ef4444; }

        /* ── Modal ───────────────────────── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.55);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s;
        }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal-box {
            background: var(--card-bg, #fff);
            border-radius: 20px;
            width: 100%;
            max-width: 440px;
            padding: 28px 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,.3);
            transform: translateY(20px);
            transition: transform .25s;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-overlay.open .modal-box { transform: translateY(0); }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .modal-header h3 {
            font-size: 17px;
            font-weight: 800;
            color: var(--dark-green);
            margin: 0;
        }
        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: var(--border-color);
            color: var(--text-gray);
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-field { margin-bottom: 14px; }
        .modal-field label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 5px;
        }
        .modal-field select, .modal-field input, .modal-field textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            background: var(--bg-color, #f9fafb);
            color: var(--text-main);
            font-family: inherit;
            box-sizing: border-box;
        }
        .modal-field select:focus, .modal-field input:focus, .modal-field textarea:focus {
            outline: none;
            border-color: #22c55e;
        }
        .modal-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg,#16a34a,#22c55e);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 6px;
        }
        .modal-submit:hover { filter: brightness(1.05); }
        .modal-submit:disabled { opacity: .6; cursor: not-allowed; }

        /* ── Botão + Verde Redondo ────────── */
        .btn-novo-usuario {
            background-color: var(--primary-green);
            color: white;
            border: none;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(97, 187, 104, 0.3);
            transition: all 0.2s ease;
        }
        .btn-novo-usuario:hover {
            background-color: var(--sidebar-header);
            transform: scale(1.05);
        }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-btn" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
                <div class="topbar-title">Controle de Usuários</div>
            </div>
        </header>

        <main class="main-content">
            <div class="content-wrapper">

                <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h1>Gerenciamento de Usuários</h1>
                        <p>Gerencie todos os perfis cadastrados no AgroGestão.</p>
                    </div>
                    <button class="btn-novo-usuario" onclick="abrirNovoUsuario()" title="Novo Usuário"><i class="fa-solid fa-plus"></i></button>
                </div>

                <?php if (!empty($msg_erro)): ?>
                    <div style="background-color: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 700;">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($msg_erro); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($msg_sucesso)): ?>
                    <div style="background-color: #d1fae5; color: #10b981; border: 1px solid #6ee7b7; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 700;">
                        <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($msg_sucesso); ?>
                    </div>
                <?php endif; ?>

                <!-- Busca -->
                <div class="report-card" style="margin-bottom: 20px;">
                    <form method="GET" action="admin_usuarios.php" style="display:flex; gap:8px;">
                        <input type="text" name="busca" class="filter-input" style="flex:1;" placeholder="Buscar por nome ou e-mail..." value="<?php echo htmlspecialchars($busca); ?>">
                        <button type="submit" class="btn-export" style="background:var(--primary-green); color:white; border-color:var(--primary-green); padding:10px 20px;">
                            Buscar
                        </button>
                        <a href="admin_usuarios.php" class="btn-export" style="padding:10px 20px; display:inline-flex; align-items:center;">
                            Limpar
                        </a>
                    </form>
                </div>

                <!-- Lista de Usuários -->
                <div class="user-grid">
                    <?php if (empty($usuarios)): ?>
                        <div class="empty-state" style="grid-column: 1 / -1;">Nenhum usuário encontrado.</div>
                    <?php else: ?>
                        <?php foreach ($usuarios as $u):
                            $iniciais = obter_iniciais($u['nome']);
                        ?>
                            <div class="user-card">
                                <div class="user-header">
                                    <div class="user-avatar">
                                        <?php if (!empty($u['foto_perfil'])): ?>
                                            <img src="<?php echo htmlspecialchars($u['foto_perfil']); ?>" alt="Foto">
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($iniciais); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-meta">
                                        <h4><?php echo htmlspecialchars($u['nome']); ?></h4>
                                        <p><?php echo htmlspecialchars($u['email']); ?></p>
                                        <div style="margin-top: 4px;">
                                            <span class="perfil-badge badge-<?php echo $u['perfil']; ?>"><?php echo $u['perfil'] === 'admin' ? 'Admin' : ucfirst($u['perfil']); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="user-stats">
                                    <div>Atividades: <strong><?php echo $u['n_logs']; ?> logs</strong></div>
                                    <div>Último acesso: <strong><?php echo $u['ultima_atividade'] ? date('d/m H:i', strtotime($u['ultima_atividade'])) : 'Nunca'; ?></strong></div>
                                </div>

                                <div class="user-actions">
                                    <?php if ($u['id_usuario'] !== $id_usuario_logado): ?>
                                        <?php if ($u['perfil'] === 'operador'): ?>
                                            <a href="admin_usuarios.php?action=tornar_admin&id=<?php echo $u['id_usuario']; ?>&busca=<?php echo urlencode($busca); ?>" class="btn-act btn-act-role">
                                                <i class="fa-solid fa-user-shield"></i> Tornar Admin
                                            </a>
                                        <?php elseif ($u['perfil'] === 'admin'): ?>
                                            <a href="admin_usuarios.php?action=tornar_operador&id=<?php echo $u['id_usuario']; ?>&busca=<?php echo urlencode($busca); ?>" class="btn-act btn-act-role">
                                                <i class="fa-solid fa-user-tie"></i> Tornar Operador
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="admin_usuarios.php?action=delete&id=<?php echo $u['id_usuario']; ?>&busca=<?php echo urlencode($busca); ?>" class="btn-act btn-act-del" onclick="return confirm('Deseja realmente remover este usuário?')">
                                            <i class="fa-solid fa-user-minus"></i> Remover
                                        </a>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: var(--text-gray); font-style: italic;">Sem ações disponíveis</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
</div>

<!-- ── MODAL: Criar Usuário ───────────────────────────────────────────── -->
<div class="modal-overlay" id="usuario-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-plus" style="color:#22c55e;margin-right:6px;"></i> Criar Novo Usuário</h3>
            <button class="modal-close" onclick="fecharNovoUsuario()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="POST" action="admin_usuarios.php">
            <input type="hidden" name="acao_form" value="criar_usuario">
            
            <div class="modal-field">
                <label>Nome *</label>
                <input type="text" name="nome" required>
            </div>
            
            <div class="modal-field">
                <label>E-mail *</label>
                <input type="email" name="email" required>
            </div>
            
            <div class="modal-field">
                <label>Senha *</label>
                <input type="password" name="senha" minlength="6" required>
            </div>

            <div class="modal-field">
                <label>Confirmar senha *</label>
                <input type="password" name="confirmar_senha" minlength="6" required>
            </div>
            
            <div class="modal-field">
                <label>Tipo de usuário *</label>
                <select name="perfil" required>
                    <option value="operador">Operador</option>
                    <option value="admin">Administrador</option>
                </select>
            </div>

            <button type="submit" class="modal-submit">
                <i class="fa-solid fa-user-plus"></i> Criar Usuário
            </button>
        </form>
    </div>
</div>

<script>
function toggleMenu() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('overlay').classList.toggle('active');
}
function abrirNovoUsuario() {
    document.getElementById('usuario-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function fecharNovoUsuario() {
    document.getElementById('usuario-overlay').classList.remove('open');
    document.body.style.overflow = '';
}
// Close overlay on backdrop click
document.getElementById('usuario-overlay').addEventListener('click', function(e) {
    if (e.target === this) fecharNovoUsuario();
});
</script>
</body>
</html>
