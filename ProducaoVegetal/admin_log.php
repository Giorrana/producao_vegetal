<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';
verificar_login();
restringir_pagina_admin();

$id_usuario_logado = $_SESSION['user_id'];

// --- Filtros ---
$filtro_usuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
$filtro_operacao = isset($_GET['operacao']) ? trim($_GET['operacao']) : '';
$filtro_data_de = isset($_GET['data_de']) ? trim($_GET['data_de']) : '';
$filtro_data_ate = isset($_GET['data_ate']) ? trim($_GET['data_ate']) : '';

// --- Construção da Query ---
$where_clauses = ["1=1"];
if ($filtro_usuario > 0) {
    $where_clauses[] = "l.id_usuario = $filtro_usuario";
}
if (!empty($filtro_operacao)) {
    $escaped_op = $conn->real_escape_string($filtro_operacao);
    $where_clauses[] = "l.operacao LIKE '%$escaped_op%'";
}
if (!empty($filtro_data_de)) {
    $escaped_de = $conn->real_escape_string($filtro_data_de);
    $where_clauses[] = "l.data_operacao >= '$escaped_de 00:00:00'";
}
if (!empty($filtro_data_ate)) {
    $escaped_ate = $conn->real_escape_string($filtro_data_ate);
    $where_clauses[] = "l.data_operacao <= '$escaped_ate 23:59:59'";
}

$where_sql = implode(" AND ", $where_clauses);

// --- Paginação ---
$total_registros = 0;
$res_count = $conn->query("SELECT COUNT(*) c FROM log l WHERE $where_sql");
if ($res_count) {
    $total_registros = $res_count->fetch_assoc()['c'];
}

$registros_por_pagina = 20;
$total_paginas = ceil($total_registros / $registros_por_pagina);
if ($total_paginas < 1) $total_paginas = 1;

$pagina_atual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
if ($pagina_atual < 1) $pagina_atual = 1;
if ($pagina_atual > $total_paginas) $pagina_atual = $total_paginas;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// --- Buscar Logs ---
$q_logs = $conn->query("
    SELECT l.id_log, l.operacao, l.data_operacao, u.nome, u.email, u.perfil
    FROM log l
    LEFT JOIN usuarios u ON l.id_usuario = u.id_usuario
    WHERE $where_sql
    ORDER BY l.data_operacao DESC, l.id_log DESC
    LIMIT $registros_por_pagina OFFSET $offset
");
$logs = $q_logs ? $q_logs->fetch_all(MYSQLI_ASSOC) : [];

// --- Buscar Usuários para o Filtro ---
$q_users = $conn->query("SELECT id_usuario, nome, perfil FROM usuarios ORDER BY nome ASC");
$usuarios_filtro = $q_users ? $q_users->fetch_all(MYSQLI_ASSOC) : [];

$activePage = 'admin_log';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão — Log de Auditoria</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script>if(localStorage.getItem('agro_theme')==='dark')document.documentElement.classList.add('dark-theme');</script>
    <style>
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .filter-field { display: flex; flex-direction: column; gap: 6px; }
        .filter-field label { font-size: 11px; font-weight: 700; color: var(--text-gray); text-transform: uppercase; }
        .filter-input {
            padding: 10px 12px; border-radius: 10px; border: 1.5px solid var(--border-color);
            background: var(--card-bg); color: var(--text-main); font-family: inherit; font-size: 13px; transition: border-color .2s;
        }
        .filter-input:focus { border-color: var(--primary-green); outline: none; }
        .filter-actions { display: flex; align-items: flex-end; gap: 8px; }

        .adm-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .adm-table th { padding: 12px 14px; text-align: left; font-weight: 800; color: var(--text-gray); font-size: 11px; text-transform: uppercase; background: var(--form-bg,#f9fafb); border-bottom: 2px solid var(--border-color); }
        .adm-table td { padding: 12px 14px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .adm-table tbody tr:hover { background: var(--active-bg); }

        .perfil-badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 9px; font-weight: 800; text-transform: uppercase; color: white; }
        .badge-admin { background: #ef4444; }
        .badge-operador { background: #3b82f6; }
        .badge-visitante { background: #9ca3af; }

        .log-operacao { font-weight: 700; color: var(--text-main); }
        
        .pagination { display: flex; justify-content: center; gap: 6px; margin-top: 20px; }
        .page-link {
            padding: 8px 12px; border-radius: 8px; border: 1.5px solid var(--border-color);
            background: var(--card-bg); color: var(--text-gray); text-decoration: none; font-size: 13px; font-weight: 700;
            transition: all .2s;
        }
        .page-link:hover { border-color: var(--primary-green); color: var(--primary-green); }
        .page-link.active { background: var(--primary-green); color: white; border-color: var(--primary-green); }
        .page-link.disabled { opacity: 0.5; pointer-events: none; }

        .export-bar { display: flex; gap: 8px; margin-bottom: 14px; justify-content: flex-end; }
        .btn-export { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1.5px solid var(--border-color); background: var(--form-bg,#f9fafb); border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; font-family: inherit; color: var(--dark-green); }
        .btn-export:hover { border-color: #22c55e; background: rgba(34,197,94,.08); }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-btn" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
                <div class="topbar-title">Log de Auditoria</div>
            </div>
        </header>

        <main class="main-content">
            <div class="content-wrapper">

                <div class="page-header">
                    <h1>Log de Auditoria</h1>
                    <p>Histórico completo de operações críticas realizadas no AgroGestão.</p>
                </div>

                <!-- Filtros -->
                <div class="report-card" style="margin-bottom:20px;">
                    <form method="GET" action="admin_log.php">
                        <div class="filter-grid">
                            <div class="filter-field">
                                <label>Usuário</label>
                                <select name="usuario" class="filter-input">
                                    <option value="0">Todos os usuários</option>
                                    <?php foreach ($usuarios_filtro as $u): ?>
                                        <option value="<?php echo $u['id_usuario']; ?>" <?php echo $filtro_usuario === intval($u['id_usuario']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['nome']); ?> (<?php echo ucfirst($u['perfil']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-field">
                                <label>Operação</label>
                                <input type="text" name="operacao" class="filter-input" placeholder="Ex: login, exclusão" value="<?php echo htmlspecialchars($filtro_operacao); ?>">
                            </div>
                            <div class="filter-field">
                                <label>De (Data)</label>
                                <input type="date" name="data_de" class="filter-input" value="<?php echo htmlspecialchars($filtro_data_de); ?>">
                            </div>
                            <div class="filter-field">
                                <label>Até (Data)</label>
                                <input type="date" name="data_ate" class="filter-input" value="<?php echo htmlspecialchars($filtro_data_ate); ?>">
                            </div>
                            <div class="filter-actions">
                                <button type="submit" class="btn-export" style="background:var(--primary-green); color:white; border-color:var(--primary-green); padding:10px 20px; height:40px;">
                                    <i class="fa-solid fa-filter"></i> Filtrar
                                </button>
                                <a href="admin_log.php" class="btn-export" style="padding:10px 20px; height:40px; display:inline-flex; align-items:center;">
                                    Limpar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Tabela de Logs -->
                <div class="export-bar">
                    <button class="btn-export" onclick="exportTableToExcel('tbl-logs', 'log_auditoria')">
                        <i class="fa-solid fa-file-excel"></i> Exportar Excel
                    </button>
                    <button class="btn-export" onclick="exportToPDF()">
                        <i class="fa-solid fa-file-pdf"></i> Exportar PDF
                    </button>
                </div>

                <div class="report-card">
                    <div style="overflow-x:auto;">
                        <table class="adm-table" id="tbl-logs">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Data / Hora</th>
                                    <th>Usuário</th>
                                    <th>Perfil</th>
                                    <th>Operação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr><td colspan="5" style="text-align:center;color:var(--text-gray);padding:25px;">Nenhum registro encontrado para os filtros selecionados.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><span style="font-family:monospace;color:var(--text-gray);">#<?php echo $log['id_log']; ?></span></td>
                                            <td style="color:var(--text-gray);"><?php echo date('d/m/Y H:i:s', strtotime($log['data_operacao'])); ?></td>
                                            <td>
                                                <strong style="color:var(--text-main);"><?php echo htmlspecialchars($log['nome'] ?? 'Sistema/Excluído'); ?></strong>
                                                <?php if($log['email']): ?><div style="font-size:11px;color:var(--text-gray);"><?php echo htmlspecialchars($log['email']); ?></div><?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($log['perfil']): ?>
                                                    <span class="perfil-badge badge-<?php echo $log['perfil']; ?>"><?php echo ucfirst($log['perfil']); ?></span>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="log-operacao"><?php echo htmlspecialchars($log['operacao']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="pagination">
                            <a href="?pagina=1&usuario=<?php echo $filtro_usuario; ?>&operacao=<?php echo urlencode($filtro_operacao); ?>&data_de=<?php echo $filtro_data_de; ?>&data_ate=<?php echo $filtro_data_ate; ?>" class="page-link <?php echo $pagina_atual === 1 ? 'disabled' : ''; ?>">&laquo;</a>
                            
                            <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                                <a href="?pagina=<?php echo $i; ?>&usuario=<?php echo $filtro_usuario; ?>&operacao=<?php echo urlencode($filtro_operacao); ?>&data_de=<?php echo $filtro_data_de; ?>&data_ate=<?php echo $filtro_data_ate; ?>" class="page-link <?php echo $pagina_atual === $i ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <a href="?pagina=<?php echo $total_paginas; ?>&usuario=<?php echo $filtro_usuario; ?>&operacao=<?php echo urlencode($filtro_operacao); ?>&data_de=<?php echo $filtro_data_de; ?>&data_ate=<?php echo $filtro_data_ate; ?>" class="page-link <?php echo $pagina_atual === $total_paginas ? 'disabled' : ''; ?>">&raquo;</a>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
</div>
<script src="export.js"></script>
<script>
function toggleMenu() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('overlay').classList.toggle('active');
}
</script>
</body>
</html>
