<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';
verificar_login();
restringir_pagina_admin();

// ─── KPIs Globais (todos os tenants) ────────────────────────────────────────
$kpi = [];
$kpi['usuarios']  = $conn->query("SELECT COUNT(*) c FROM usuarios")->fetch_assoc()['c'] ?? 0;
$kpi['plantios']  = $conn->query("SELECT COUNT(*) c FROM plantios WHERE colhido=0")->fetch_assoc()['c'] ?? 0;
$kpi['culturas']  = $conn->query("SELECT COUNT(*) c FROM culturas")->fetch_assoc()['c'] ?? 0;
$kpi['colheitas'] = $conn->query("SELECT COUNT(*) c FROM colheitas")->fetch_assoc()['c'] ?? 0;
$kpi['manejos']   = $conn->query("SELECT COUNT(*) c FROM cuidados_plantio")->fetch_assoc()['c'] ?? 0;
$kpi['kg_total']  = $conn->query("SELECT COALESCE(SUM(quantidade_colhida),0) c FROM colheitas")->fetch_assoc()['c'] ?? 0;

// ─── Atividade por Usuário ───────────────────────────────────────────────────
$q_users = $conn->query("
    SELECT
        u.id_usuario, u.nome, u.email, u.perfil,
        COUNT(DISTINCT p.id_plantio)   AS n_plantios,
        COUNT(DISTINCT cp.id_cuidado)  AS n_manejos,
        COUNT(DISTINCT col.id_colheita) AS n_colheitas,
        MAX(l.data_operacao)            AS ultima_atividade
    FROM usuarios u
    LEFT JOIN culturas c   ON c.id_usuario  = u.id_usuario
    LEFT JOIN plantios p   ON p.id_cultura  = c.id_cultura
    LEFT JOIN cuidados_plantio cp ON cp.id_plantio = p.id_plantio
    LEFT JOIN colheitas col       ON col.id_plantio = p.id_plantio
    LEFT JOIN log l               ON l.id_usuario   = u.id_usuario
    GROUP BY u.id_usuario
    ORDER BY ultima_atividade DESC, u.nome ASC
");
$users_atividade = $q_users ? $q_users->fetch_all(MYSQLI_ASSOC) : [];

// ─── Últimos 20 logs ─────────────────────────────────────────────────────────
$q_logs = $conn->query("
    SELECT l.operacao, l.data_operacao, u.nome, u.perfil
    FROM log l
    LEFT JOIN usuarios u ON l.id_usuario = u.id_usuario
    ORDER BY l.data_operacao DESC
    LIMIT 20
");
$logs_recentes = $q_logs ? $q_logs->fetch_all(MYSQLI_ASSOC) : [];

// ─── Produção por Mês (últimos 6 meses) ─────────────────────────────────────
$q_prod = $conn->query("
    SELECT DATE_FORMAT(data_colheita,'%Y-%m') as mes,
           SUM(quantidade_colhida) as total
    FROM colheitas
    WHERE data_colheita >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY mes
    ORDER BY mes ASC
");
$prod_mensal = $q_prod ? $q_prod->fetch_all(MYSQLI_ASSOC) : [];

$activePage = 'admin_painel';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão — Painel Admin</title>
    <meta name="description" content="Painel administrativo global com KPIs e atividade por usuário.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script>if(localStorage.getItem('agro_theme')==='dark')document.documentElement.classList.add('dark-theme');</script>
    <style>
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(160px,1fr)); gap: 14px; margin-bottom: 24px; }
        .kpi-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px 16px;
            text-align: center;
            transition: transform .2s;
        }
        .kpi-card:hover { transform: translateY(-3px); }
        .kpi-val { font-size: 30px; font-weight: 900; color: var(--primary-green); line-height: 1; }
        .kpi-lbl { font-size: 11px; font-weight: 700; color: var(--text-gray); text-transform: uppercase; letter-spacing: .5px; margin-top: 6px; }
        .kpi-icon { font-size: 18px; margin-bottom: 8px; }

        .adm-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .adm-table th { padding: 10px 14px; text-align: left; font-weight: 800; color: var(--text-gray); font-size: 11px; text-transform: uppercase; background: var(--form-bg,#f9fafb); }
        .adm-table td { padding: 10px 14px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .adm-table tbody tr:hover { background: var(--active-bg); }
        .perfil-badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; color: white; }
        .badge-admin { background: #ef4444; }
        .badge-operador { background: #3b82f6; }
        .badge-visitante { background: #9ca3af; }

        .log-pill { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; background: var(--active-bg); color: var(--primary-green); }
        .section-title { font-size: 16px; font-weight: 800; color: var(--text-main); margin: 0 0 16px; display: flex; align-items: center; gap: 8px; }
        .section-title i { color: var(--primary-green); }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-btn" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
                <div class="topbar-title">Painel Administrativo</div>
            </div>
        </header>

        <main class="main-content">
            <div class="content-wrapper">

                <div class="page-header">
                    <h1>Painel Administrativo</h1>
                    <p>Visão global de todos os usuários, plantios e operações do sistema.</p>
                </div>

                <!-- KPIs -->
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-icon" style="color:#6366f1;"><i class="fa-solid fa-users"></i></div>
                        <div class="kpi-val"><?php echo $kpi['usuarios']; ?></div>
                        <div class="kpi-lbl">Usuários</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon" style="color:#22c55e;"><i class="fa-solid fa-seedling"></i></div>
                        <div class="kpi-val"><?php echo $kpi['plantios']; ?></div>
                        <div class="kpi-lbl">Plantios Ativos</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon" style="color:#f59e0b;"><i class="fa-solid fa-leaf"></i></div>
                        <div class="kpi-val"><?php echo $kpi['culturas']; ?></div>
                        <div class="kpi-lbl">Culturas</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon" style="color:#10b981;"><i class="fa-solid fa-basket-shopping"></i></div>
                        <div class="kpi-val"><?php echo $kpi['colheitas']; ?></div>
                        <div class="kpi-lbl">Colheitas</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon" style="color:#3b82f6;"><i class="fa-solid fa-droplet"></i></div>
                        <div class="kpi-val"><?php echo $kpi['manejos']; ?></div>
                        <div class="kpi-lbl">Manejos</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon" style="color:#16a34a;"><i class="fa-solid fa-weight-scale"></i></div>
                        <div class="kpi-val"><?php echo number_format($kpi['kg_total'],1,',','.'); ?></div>
                        <div class="kpi-lbl">kg Colhidos</div>
                    </div>
                </div>

                <!-- Atividade por Usuário -->
                <div class="report-card" style="margin-bottom:20px;">
                    <h3 class="section-title"><i class="fa-solid fa-chart-bar"></i> Atividade por Usuário</h3>
                    <div style="overflow-x:auto;">
                        <table class="adm-table" id="tbl-usuarios">
                            <thead>
                                <tr>
                                    <th>Usuário</th>
                                    <th>Perfil</th>
                                    <th style="text-align:center;">Plantios</th>
                                    <th style="text-align:center;">Manejos</th>
                                    <th style="text-align:center;">Colheitas</th>
                                    <th>Última Atividade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users_atividade)): ?>
                                    <tr><td colspan="6" style="text-align:center;color:var(--text-gray);padding:20px;">Nenhum usuário encontrado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($users_atividade as $u): ?>
                                        <tr>
                                            <td>
                                                <strong style="color:var(--text-main);"><?php echo htmlspecialchars($u['nome']); ?></strong>
                                                <div style="font-size:11px;color:var(--text-gray);"><?php echo htmlspecialchars($u['email']); ?></div>
                                            </td>
                                            <td>
                                                <span class="perfil-badge badge-<?php echo $u['perfil']; ?>">
                                                    <?php echo $u['perfil'] === 'admin' ? 'Admin' : ucfirst($u['perfil']); ?>
                                                </span>
                                            </td>
                                            <td style="text-align:center;font-weight:800;color:var(--primary-green);"><?php echo $u['n_plantios']; ?></td>
                                            <td style="text-align:center;font-weight:800;color:#3b82f6;"><?php echo $u['n_manejos']; ?></td>
                                            <td style="text-align:center;font-weight:800;color:#10b981;"><?php echo $u['n_colheitas']; ?></td>
                                            <td style="color:var(--text-gray);font-size:12px;">
                                                <?php echo $u['ultima_atividade'] ? date('d/m/Y H:i', strtotime($u['ultima_atividade'])) : '—'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Últimos Logs -->
                <div class="report-card">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                        <h3 class="section-title" style="margin:0;"><i class="fa-solid fa-scroll"></i> Últimas Atividades (Log)</h3>
                        <a href="admin_log.php" style="font-size:12px;font-weight:700;color:var(--primary-green);">Ver todos →</a>
                    </div>
                    <?php if (empty($logs_recentes)): ?>
                        <p style="color:var(--text-gray);font-size:13px;"><i class="fa-solid fa-circle-info"></i> Nenhuma operação registrada ainda.</p>
                    <?php else: ?>
                        <?php foreach ($logs_recentes as $log): ?>
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border-color);gap:12px;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div style="width:34px;height:34px;border-radius:10px;background:var(--active-bg);display:flex;justify-content:center;align-items:center;color:var(--primary-green);flex-shrink:0;">
                                        <i class="fa-solid fa-clock-rotate-left" style="font-size:13px;"></i>
                                    </div>
                                    <div>
                                        <div class="log-pill"><?php echo htmlspecialchars($log['operacao'] ?? '—'); ?></div>
                                        <div style="font-size:11px;color:var(--text-gray);margin-top:2px;">
                                            <?php echo htmlspecialchars($log['nome'] ?? 'Sistema'); ?>
                                            <span class="perfil-badge badge-<?php echo $log['perfil'] ?? 'visitante'; ?>" style="padding:1px 6px;font-size:9px;margin-left:4px;"><?php echo $log['perfil'] ?? ''; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <span style="font-size:11px;color:var(--text-gray);white-space:nowrap;">
                                    <?php echo $log['data_operacao'] ? date('d/m/Y H:i', strtotime($log['data_operacao'])) : '—'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
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
