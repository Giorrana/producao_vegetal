<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';
verificar_login();

$id_usuario = $_SESSION['user_id'];

// ── Financeiro: Colheitas por Categoria ──────────────────────────────────────
$q_horta = mysqli_query($conn, "SELECT SUM(c.quantidade_colhida) as total FROM colheitas c JOIN plantios p ON c.id_plantio=p.id_plantio JOIN culturas cult ON p.id_cultura=cult.id_cultura WHERE cult.id_categoria=1 AND cult.id_usuario = $id_usuario");
$total_horta = $q_horta ? (mysqli_fetch_assoc($q_horta)['total'] ?? 0) : 0;

$q_pomar = mysqli_query($conn, "SELECT SUM(c.quantidade_colhida) as total FROM colheitas c JOIN plantios p ON c.id_plantio=p.id_plantio JOIN culturas cult ON p.id_cultura=cult.id_cultura WHERE cult.id_categoria=2 AND cult.id_usuario = $id_usuario");
$total_pomar = $q_pomar ? (mysqli_fetch_assoc($q_pomar)['total'] ?? 0) : 0;

// Produtividade Mensal
$mensal = array_fill(1, 12, 0);
$q_mes = mysqli_query($conn, "SELECT MONTH(c.data_colheita) as mes, SUM(c.quantidade_colhida) as total FROM colheitas c JOIN plantios p ON c.id_plantio = p.id_plantio JOIN culturas cult ON p.id_cultura = cult.id_cultura WHERE YEAR(c.data_colheita)=YEAR(CURDATE()) AND cult.id_usuario = $id_usuario GROUP BY MONTH(c.data_colheita)");
if ($q_mes) { while ($r = mysqli_fetch_assoc($q_mes)) { $mensal[intval($r['mes'])] = floatval($r['total']); } }
$max_mensal = max(max($mensal), 10);

// Custo total insumos (Admin)
$custo_insumos  = 0;
$custo_manejo   = 0;
if (e_admin()) {
    $qci = $conn->query("SELECT SUM(quantidade * custo_aquisicao) AS t FROM estoque WHERE id_usuario = $id_usuario"); if ($qci) $custo_insumos  = $qci->fetch_assoc()['t'] ?? 0;
    $qcm = $conn->query("SELECT SUM(cp.custo_calculado) AS t FROM cuidados_plantio cp JOIN plantios p ON cp.id_plantio = p.id_plantio JOIN culturas cult ON p.id_cultura = cult.id_cultura WHERE cult.id_usuario = $id_usuario");     if ($qcm) $custo_manejo   = $qcm->fetch_assoc()['t'] ?? 0;
}

// ── Fitossanitário: Status de plantios ───────────────────────────────────────
$status_dist = ['Germinação'=>0,'Crescimento'=>0,'Floração'=>0,'Pronto'=>0];
$q_plant = $conn->query("SELECT p.data_plantio, c.tempo_medio_crescimento FROM plantios p JOIN culturas c ON p.id_cultura=c.id_cultura WHERE p.colhido=0 AND c.id_usuario = $id_usuario");
if ($q_plant) {
    while ($row = $q_plant->fetch_assoc()) {
        $dias_ciclo = intval($row['tempo_medio_crescimento']) ?: 90;
        $dap = max(0,(int)floor((time()-strtotime($row['data_plantio']))/86400));
        $pct = min(100, round(($dap/$dias_ciclo)*100));
        if ($pct < 25)      $status_dist['Germinação']++;
        elseif ($pct < 60)  $status_dist['Crescimento']++;
        elseif ($pct < 90)  $status_dist['Floração']++;
        else                $status_dist['Pronto']++;
    }
}
$total_plant = max(array_sum($status_dist), 1);

// ── Colheitas Recentes ────────────────────────────────────────────────────────
$q_recentes = $conn->query("SELECT c.*, cult.nome_cultura, p.codigo_lote FROM colheitas c JOIN plantios p ON c.id_plantio=p.id_plantio JOIN culturas cult ON p.id_cultura=cult.id_cultura WHERE cult.id_usuario = $id_usuario ORDER BY c.id_colheita DESC LIMIT 10");
$recentes = [];
if ($q_recentes) $recentes = $q_recentes->fetch_all(MYSQLI_ASSOC);

// Chart scales
$max_setor = max($total_horta, $total_pomar, 10);

$activePage = 'relatorios';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão - Relatórios & 5 Pilares</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="export.js"><!-- loaded as script below -->
    <script>if (localStorage.getItem('agro_theme') === 'dark') document.documentElement.classList.add('dark-theme');</script>
    <style>
        /* ── 5 Pillars Tabs ─────────────── */
        .pillars-tabs {
            display: flex;
            gap: 6px;
            overflow-x: auto;
            padding-bottom: 4px;
            margin-bottom: 20px;
            scrollbar-width: none;
        }
        .pillars-tabs::-webkit-scrollbar { display: none; }
        .pillar-tab {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 9px 16px;
            border-radius: 50px;
            border: 1.5px solid var(--border-color);
            background: var(--bg-white, #fff);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            color: var(--text-gray);
            font-family: inherit;
            transition: all .2s;
        }
        .pillar-tab:hover { border-color: #22c55e; color: #16a34a; }
        .pillar-tab.active { background: linear-gradient(135deg,#16a34a,#22c55e); color: white; border-color: transparent; box-shadow: 0 4px 15px rgba(34,197,94,.3); }

        .pillar-panel { display: none; }
        .pillar-panel.active { display: block; }

        /* ── Donut Chart ─────────────────── */
        .donut-wrap { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
        .donut-legend { display: flex; flex-direction: column; gap: 8px; }
        .dleg-item { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700; }
        .dleg-dot  { width: 12px; height: 12px; border-radius: 3px; flex-shrink: 0; }

        /* ── Rastreabilidade ─────────────── */
        .lot-card {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: white;
            border-radius: 16px;
            padding: 20px;
            font-family: monospace;
            margin-bottom: 12px;
        }
        .lot-card h3 { font-size: 22px; letter-spacing: 3px; font-weight: 900; margin: 0 0 8px; color: #22c55e; }
        .lot-meta { font-size: 11px; color: #94a3b8; line-height: 2; }
        .lot-badge-row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .lot-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; border: 1px solid; }

        /* ── Export buttons ──────────────── */
        .export-bar { display: flex; gap: 8px; margin-bottom: 14px; justify-content: flex-end; }
        .btn-export { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1.5px solid var(--border-color); background: var(--form-bg,#f9fafb); border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; font-family: inherit; color: var(--dark-green); }
        .btn-export:hover { border-color: #22c55e; background: rgba(34,197,94,.08); }

        /* ── Mini stat ───────────────────── */
        .mini-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 20px; }
        .mini-stat { background: var(--form-bg,#f9fafb); border: 1px solid var(--border-color); border-radius: 14px; padding: 14px; text-align: center; }
        .mini-stat .ms-val { font-size: 20px; font-weight: 900; color: var(--dark-green); }
        .mini-stat .ms-lbl { font-size: 10px; font-weight: 700; color: var(--text-gray); text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-btn" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
                <div class="topbar-title">Relatórios & 5 Pilares</div>
            </div>
        </header>

        <main class="main-content">
            <div class="content-wrapper">

                <div class="page-header">
                    <h1>Painel de Gestão Agrícola</h1>
                    <p>Monitore os 5 pilares fundamentais da produção profissional.</p>
                </div>

                <!-- 5 PILLARS TABS -->
                <div class="pillars-tabs">
                    <button class="pillar-tab active" onclick="showPillar('financeiro',this)" id="tab-financeiro">
                        <i class="fa-solid fa-chart-line"></i> Financeiro
                    </button>
                    <button class="pillar-tab" onclick="showPillar('fitossanitario',this)" id="tab-fitossanitario">
                        <i class="fa-solid fa-heart-pulse"></i> Fitossanitário
                    </button>
                    <button class="pillar-tab" onclick="showPillar('planejamento',this)" id="tab-planejamento">
                        <i class="fa-solid fa-calendar-check"></i> Planejamento
                    </button>
                    <button class="pillar-tab" onclick="showPillar('rastreabilidade',this)" id="tab-rastreabilidade">
                        <i class="fa-solid fa-barcode"></i> Rastreabilidade
                    </button>
                </div>

                <!-- ══ PILLAR 1: FINANCEIRO ══════════════════════════════════ -->
                <div class="pillar-panel active" id="pillar-financeiro">

                    <?php if (!e_admin()): ?>
                        <div style="background:#fef3c7;color:#d97706;border:1px solid #fcd34d;padding:14px;border-radius:10px;font-weight:700;margin-bottom:20px;">
                            <i class="fa-solid fa-lock"></i> Visão financeira disponível apenas para Administradores.
                        </div>
                    <?php else: ?>
                        <div class="mini-stats">
                            <div class="mini-stat">
                                <div class="ms-val">R$ <?php echo number_format($custo_insumos,2,',','.'); ?></div>
                                <div class="ms-lbl">Custo em Estoque</div>
                            </div>
                            <div class="mini-stat">
                                <div class="ms-val">R$ <?php echo number_format($custo_manejo,2,',','.'); ?></div>
                                <div class="ms-lbl">Custo em Manejos</div>
                            </div>
                            <div class="mini-stat">
                                <div class="ms-val"><?php echo number_format($total_horta+$total_pomar,1,',','.'); ?> kg</div>
                                <div class="ms-lbl">Total Colhido</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="export-bar">
                        <button class="btn-export" onclick="exportTableToExcel('tbl-recentes','relatorio_colheitas')">
                            <i class="fa-solid fa-file-excel"></i> Exportar Excel
                        </button>
                        <button class="btn-export" onclick="exportToPDF()">
                            <i class="fa-solid fa-file-pdf"></i> Exportar PDF
                        </button>
                    </div>

                    <div class="reports-grid">
                        <!-- Horta vs Pomar -->
                        <div class="report-card">
                            <div class="rc-subtitle">MÉTRICAS DE COLHEITA (KG)</div>
                            <div class="rc-header-row">
                                <h2 class="rc-title">Horta vs Pomar</h2>
                                <div style="display:flex;gap:10px;font-size:11px;font-weight:800;">
                                    <span style="color:var(--primary-green);"><i class="fa-solid fa-square"></i> HORTA</span>
                                    <span style="color:var(--orange-chart);"><i class="fa-solid fa-square"></i> POMAR</span>
                                </div>
                            </div>
                            <div class="svg-container">
                                <svg class="chart-svg" viewBox="0 0 400 220" preserveAspectRatio="none">
                                    <line x1="30" y1="20" x2="400" y2="20" stroke="#f3f4f6" stroke-width="1"/>
                                    <line x1="30" y1="110" x2="400" y2="110" stroke="#f3f4f6" stroke-width="1"/>
                                    <line x1="30" y1="200" x2="400" y2="200" stroke="#e5e7eb" stroke-width="2"/>
                                    <text x="20" y="24" font-size="10" font-weight="700" fill="#9ca3af" text-anchor="end"><?php echo round($max_setor); ?></text>
                                    <text x="20" y="114" font-size="10" font-weight="700" fill="#9ca3af" text-anchor="end"><?php echo round($max_setor/2); ?></text>
                                    <text x="20" y="204" font-size="10" font-weight="700" fill="#9ca3af" text-anchor="end">0</text>
                                    <text x="140" y="218" font-size="11" font-weight="800" fill="#1f2937" text-anchor="middle">HORTA</text>
                                    <text x="260" y="218" font-size="11" font-weight="800" fill="#1f2937" text-anchor="middle">POMAR</text>
                                    <?php
                                    $h_h = ($total_horta/$max_setor)*150; $y_h = 200-$h_h;
                                    $h_p = ($total_pomar/$max_setor)*150; $y_p = 200-$h_p;
                                    ?>
                                    <rect x="120" y="<?php echo $y_h; ?>" width="40" height="<?php echo $h_h; ?>" fill="var(--primary-green)" rx="4"/>
                                    <text x="140" y="<?php echo $y_h-6; ?>" font-size="11" font-weight="800" fill="#1f2937" text-anchor="middle"><?php echo number_format($total_horta,1,',','.'); ?> kg</text>
                                    <rect x="240" y="<?php echo $y_p; ?>" width="40" height="<?php echo $h_p; ?>" fill="var(--orange-chart)" rx="4"/>
                                    <text x="260" y="<?php echo $y_p-6; ?>" font-size="11" font-weight="800" fill="#1f2937" text-anchor="middle"><?php echo number_format($total_pomar,1,',','.'); ?> kg</text>
                                </svg>
                            </div>
                        </div>

                        <!-- Produtividade Mensal -->
                        <div class="report-card">
                            <div class="rc-header-row">
                                <h2 class="rc-title">Produtividade Mensal (<?php echo date('Y'); ?>)</h2>
                                <div class="rc-badge">KG</div>
                            </div>
                            <div class="svg-container">
                                <svg class="chart-svg" viewBox="0 0 400 220" preserveAspectRatio="none">
                                    <line x1="30" y1="20" x2="400" y2="20" stroke="#f3f4f6" stroke-width="1"/>
                                    <line x1="30" y1="110" x2="400" y2="110" stroke="#f3f4f6" stroke-width="1"/>
                                    <line x1="30" y1="200" x2="400" y2="200" stroke="#e5e7eb" stroke-width="2"/>
                                    <text x="20" y="24" font-size="10" font-weight="700" fill="#9ca3af" text-anchor="end"><?php echo round($max_mensal); ?></text>
                                    <text x="20" y="204" font-size="10" font-weight="700" fill="#9ca3af" text-anchor="end">0</text>
                                    <?php
                                    $labels_mes = ['JAN','FEV','MAR','ABR','MAI','JUN','JUL','AGO','SET','OUT','NOV','DEZ'];
                                    $xs = [46,82,118,154,190,226,262,298,334,370]; // x positions for 10 visible months
                                    $months_to_show = [1,2,3,4,5,6,7,8,9,10];
                                    foreach ($months_to_show as $idx => $m):
                                        $x = $xs[$idx];
                                        $h = ($mensal[$m]/$max_mensal)*150;
                                        $y = 200-$h;
                                    ?>
                                        <text x="<?php echo $x+10; ?>" y="218" font-size="9" font-weight="800" fill="#1f2937" text-anchor="middle"><?php echo $labels_mes[$m-1]; ?></text>
                                        <rect x="<?php echo $x; ?>" y="<?php echo $y; ?>" width="20" height="<?php echo $h; ?>" fill="var(--primary-green)" rx="3"/>
                                    <?php endforeach; ?>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Colheitas recentes table -->
                    <div class="activities-section">
                        <h3 class="chart-title">Colheitas Recentes</h3>
                        <div style="overflow-x:auto;">
                            <table id="tbl-recentes" style="width:100%;border-collapse:collapse;font-size:13px;">
                                <thead>
                                    <tr style="background:var(--form-bg,#f9fafb);">
                                        <th style="padding:10px 14px;text-align:left;font-weight:800;color:var(--text-gray);font-size:11px;text-transform:uppercase;">Cultura</th>
                                        <th style="padding:10px 14px;text-align:left;font-weight:800;color:var(--text-gray);font-size:11px;text-transform:uppercase;">Lote</th>
                                        <th style="padding:10px 14px;text-align:right;font-weight:800;color:var(--text-gray);font-size:11px;text-transform:uppercase;">Quantidade</th>
                                        <th style="padding:10px 14px;text-align:right;font-weight:800;color:var(--text-gray);font-size:11px;text-transform:uppercase;">Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentes)): ?>
                                        <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-gray);">Nenhuma colheita registrada.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recentes as $act): ?>
                                            <tr style="border-bottom:1px solid var(--border-color);">
                                                <td style="padding:10px 14px;font-weight:700;color:var(--dark-green);"><?php echo htmlspecialchars($act['nome_cultura']); ?></td>
                                                <td style="padding:10px 14px;font-family:monospace;color:#2563eb;font-size:11px;"><?php echo htmlspecialchars($act['codigo_lote'] ?? '—'); ?></td>
                                                <td style="padding:10px 14px;text-align:right;font-weight:700;"><?php echo number_format($act['quantidade_colhida'],1,',','.'); ?> kg</td>
                                                <td style="padding:10px 14px;text-align:right;color:var(--text-gray);"><?php echo date('d/m/Y', strtotime($act['data_colheita'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /financeiro -->

                <!-- ══ PILLAR 2: FITOSSANITÁRIO ══════════════════════════════ -->
                <div class="pillar-panel" id="pillar-fitossanitario">
                    <div class="report-card">
                        <div class="rc-subtitle">SAÚDE DAS CULTURAS</div>
                        <h2 class="rc-title" style="margin-bottom:20px;">Distribuição por Estágio de Crescimento</h2>
                        <div class="donut-wrap">
                            <?php
                            $colors = ['Germinação'=>'#22c55e','Crescimento'=>'#3b82f6','Floração'=>'#f59e0b','Pronto'=>'#16a34a'];
                            $cx=90; $cy=90; $r=65; $stroke=22;
                            $circumference = 2*M_PI*$r;
                            $offset = 0;
                            ?>
                            <svg width="180" height="180" viewBox="0 0 180 180">
                                <?php foreach ($status_dist as $label => $val):
                                    $pct = $total_plant > 0 ? $val/$total_plant : 0;
                                    $dash = $pct * $circumference;
                                    $color = $colors[$label] ?? '#9ca3af';
                                ?>
                                    <circle cx="<?php echo $cx; ?>" cy="<?php echo $cy; ?>" r="<?php echo $r; ?>"
                                        fill="none" stroke="<?php echo $color; ?>" stroke-width="<?php echo $stroke; ?>"
                                        stroke-dasharray="<?php echo $dash.' '.($circumference-$dash); ?>"
                                        stroke-dashoffset="-<?php echo $offset; ?>"
                                        transform="rotate(-90 <?php echo $cx.' '.$cy; ?>)"/>
                                    <?php $offset += $dash; ?>
                                <?php endforeach; ?>
                                <text x="<?php echo $cx; ?>" y="<?php echo $cy+5; ?>" text-anchor="middle" font-size="22" font-weight="900" fill="var(--dark-green)"><?php echo array_sum($status_dist); ?></text>
                                <text x="<?php echo $cx; ?>" y="<?php echo $cy+20; ?>" text-anchor="middle" font-size="9" font-weight="700" fill="var(--text-gray)">ATIVOS</text>
                            </svg>
                            <div class="donut-legend">
                                <?php foreach ($status_dist as $label => $val): ?>
                                    <div class="dleg-item">
                                        <div class="dleg-dot" style="background:<?php echo $colors[$label] ?? '#9ca3af'; ?>;"></div>
                                        <?php echo $label; ?>: <strong><?php echo $val; ?></strong>
                                        <span style="color:var(--text-gray);font-size:11px;">(<?php echo $total_plant>0?round($val/$total_plant*100):0; ?>%)</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Alertas de validade -->
                    <?php
                    $q_val = $conn->query("SELECT nome_item, data_validade, categoria FROM estoque WHERE data_validade IS NOT NULL AND data_validade <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) AND id_usuario = $id_usuario ORDER BY data_validade ASC LIMIT 10");
                    $validades = $q_val ? $q_val->fetch_all(MYSQLI_ASSOC) : [];
                    ?>
                    <div class="report-card" style="margin-top:16px;">
                        <div class="rc-subtitle">FITOSSANIDADE DO ESTOQUE</div>
                        <h2 class="rc-title" style="margin-bottom:16px;">Insumos com Validade Próxima (60 dias)</h2>
                        <?php if (empty($validades)): ?>
                            <p style="color:var(--text-gray);font-size:13px;"><i class="fa-solid fa-circle-check" style="color:#22c55e;"></i> Nenhum insumo com validade crítica.</p>
                        <?php else: ?>
                            <?php foreach ($validades as $v):
                                $dias_rest = (strtotime($v['data_validade'])-time())/86400;
                                $cor = $dias_rest <= 7 ? '#ef4444' : ($dias_rest <= 30 ? '#f59e0b' : '#3b82f6');
                            ?>
                                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border-color);font-size:13px;">
                                    <span style="font-weight:700;color:var(--dark-green);"><i class="fa-solid fa-flask" style="color:<?php echo $cor; ?>;margin-right:6px;"></i><?php echo htmlspecialchars($v['nome_item']); ?></span>
                                    <span style="font-size:12px;font-weight:800;color:<?php echo $cor; ?>;"><?php echo date('d/m/Y',strtotime($v['data_validade'])); ?> (<?php echo ceil($dias_rest); ?>d)</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div><!-- /fitossanitario -->

                <!-- ══ PILLAR 3: PLANEJAMENTO ════════════════════════════════ -->
                <div class="pillar-panel" id="pillar-planejamento">
                    <div class="report-card">
                        <div class="rc-subtitle">LINHA DO TEMPO</div>
                        <h2 class="rc-title" style="margin-bottom:20px;">Plantios em Progresso</h2>
                        <?php
                        $q_pl2 = $conn->query("SELECT p.*, c.nome_cultura, c.tempo_medio_crescimento FROM plantios p JOIN culturas c ON p.id_cultura=c.id_cultura WHERE p.colhido=0 AND c.id_usuario = $id_usuario ORDER BY p.data_plantio ASC LIMIT 10");
                        $pl2 = $q_pl2 ? $q_pl2->fetch_all(MYSQLI_ASSOC) : [];
                        if (empty($pl2)):
                        ?>
                            <p style="color:var(--text-gray);font-size:13px;">Nenhum plantio ativo.</p>
                        <?php else: ?>
                            <?php foreach ($pl2 as $p2):
                                $dias_ciclo = intval($p2['tempo_medio_crescimento']) ?: 90;
                                $dap = max(0,(int)floor((time()-strtotime($p2['data_plantio']))/86400));
                                $pct = min(100,round(($dap/$dias_ciclo)*100));
                                $dias_restantes = max(0, $dias_ciclo - $dap);
                                $col = $pct >= 90 ? '#16a34a' : ($pct >= 60 ? '#f59e0b' : '#3b82f6');
                            ?>
                                <div style="margin-bottom:16px;">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                        <span style="font-size:13px;font-weight:700;color:var(--dark-green);"><?php echo htmlspecialchars($p2['nome_cultura']); ?> — <?php echo htmlspecialchars($p2['local_canteiro']); ?></span>
                                        <span style="font-size:11px;font-weight:800;color:<?php echo $col; ?>;"><?php echo $pct; ?>% · <?php echo $dias_restantes; ?>d restantes</span>
                                    </div>
                                    <div style="background:var(--border-color);border-radius:50px;height:8px;">
                                        <div style="height:8px;border-radius:50px;width:<?php echo $pct; ?>%;background:<?php echo $col; ?>;transition:width .5s;"></div>
                                    </div>
                                    <div style="font-size:10px;color:var(--text-gray);margin-top:3px;">Plantio: <?php echo date('d/m/Y',strtotime($p2['data_plantio'])); ?> · DAP: <?php echo $dap; ?> dias</div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div><!-- /planejamento -->


                <!-- ══ PILLAR 5: RASTREABILIDADE ════════════════════════════ -->
                <div class="pillar-panel" id="pillar-rastreabilidade">
                    <div class="report-card">
                        <div class="rc-subtitle">RASTREABILIDADE DE LOTES</div>
                        <h2 class="rc-title" style="margin-bottom:16px;">Lotes Ativos por Plantio</h2>
                        <?php
                        $q_lotes = $conn->query("SELECT p.codigo_lote, p.data_plantio, p.local_canteiro, p.quantidade_plantada, c.nome_cultura, p.tamanho_area, p.unidade_area FROM plantios p JOIN culturas c ON p.id_cultura=c.id_cultura WHERE p.colhido=0 AND p.codigo_lote IS NOT NULL AND c.id_usuario = $id_usuario ORDER BY p.id_plantio DESC LIMIT 8");
                        $lotes = $q_lotes ? $q_lotes->fetch_all(MYSQLI_ASSOC) : [];
                        if (empty($lotes)): ?>
                            <p style="color:var(--text-gray);font-size:13px;"><i class="fa-solid fa-circle-info"></i> Nenhum lote ativo. Novos plantios gerarão códigos de lote automaticamente.</p>
                        <?php else: ?>
                            <?php foreach ($lotes as $lot):
                                $dap = max(0,(int)floor((time()-strtotime($lot['data_plantio']))/86400));
                            ?>
                                <div class="lot-card">
                                    <h3><?php echo htmlspecialchars($lot['codigo_lote'] ?? '—'); ?></h3>
                                    <div class="lot-meta">
                                        Cultura: <?php echo htmlspecialchars($lot['nome_cultura']); ?><br>
                                        Local: <?php echo htmlspecialchars($lot['local_canteiro']); ?><br>
                                        Data Plantio: <?php echo date('d/m/Y',strtotime($lot['data_plantio'])); ?> · DAP: <?php echo $dap; ?> dias<br>
                                        <?php if ($lot['tamanho_area']): ?>
                                            Área: <?php echo number_format($lot['tamanho_area'],2,',','.'); ?> <?php echo htmlspecialchars($lot['unidade_area'] ?? 'm²'); ?><br>
                                        <?php endif; ?>
                                        Qtd. Plantada: <?php echo htmlspecialchars($lot['quantidade_plantada']); ?> unidades
                                    </div>
                                    <div class="lot-badge-row">
                                        <span class="lot-badge" style="color:#22c55e;border-color:#22c55e30;background:rgba(34,197,94,.1);">
                                            <i class="fa-solid fa-circle-dot"></i> ATIVO
                                        </span>
                                        <span class="lot-badge" style="color:#94a3b8;border-color:#94a3b830;background:rgba(148,163,184,.1);">
                                            <?php echo $dap; ?> DAP
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div><!-- /rastreabilidade -->

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

    function showPillar(id, btn) {
        document.querySelectorAll('.pillar-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.pillar-tab').forEach(t => t.classList.remove('active'));
        document.getElementById('pillar-' + id).classList.add('active');
        btn.classList.add('active');
    }
</script>
</body>
</html>
