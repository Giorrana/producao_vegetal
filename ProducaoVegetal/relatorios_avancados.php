<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';
verificar_login();
restringir_pagina_admin();

$id_usuario = $_SESSION['user_id'];

// ─── ABA 1: Produtividade por Cultura ───────────────────────────────────────
$q1 = $conn->query("
    SELECT c.nome_cultura,
           COUNT(DISTINCT p.id_plantio)    AS n_plantios,
           COUNT(col.id_colheita)          AS n_colheitas,
           COALESCE(SUM(col.quantidade_colhida),0) AS total_kg
    FROM culturas c
    LEFT JOIN plantios p   ON p.id_cultura  = c.id_cultura
    LEFT JOIN colheitas col ON col.id_plantio = p.id_plantio
    WHERE c.id_usuario = $id_usuario
    GROUP BY c.id_cultura
    ORDER BY total_kg DESC
    LIMIT 20
");
$aba1 = $q1 ? $q1->fetch_all(MYSQLI_ASSOC) : [];

// ─── ABA 2: Custo vs Rendimento ──────────────────────────────────────────────
$q2 = $conn->query("
    SELECT c.nome_cultura,
           COALESCE(SUM(cp.custo_calculado),0)  AS custo_total,
           COALESCE(SUM(col.quantidade_colhida),0) AS kg_total
    FROM culturas c
    LEFT JOIN plantios p    ON p.id_cultura   = c.id_cultura
    LEFT JOIN cuidados_plantio cp ON cp.id_plantio = p.id_plantio
    LEFT JOIN colheitas col  ON col.id_plantio = p.id_plantio
    WHERE c.id_usuario = $id_usuario
    GROUP BY c.id_cultura
    HAVING custo_total > 0 OR kg_total > 0
    ORDER BY custo_total DESC
    LIMIT 20
");
$aba2 = $q2 ? $q2->fetch_all(MYSQLI_ASSOC) : [];

// ─── ABA 3: Manejos por Tipo ─────────────────────────────────────────────────
$q3 = $conn->query("
    SELECT cp.tipo_manejo,
           COUNT(*)                         AS total_ops,
           COALESCE(SUM(cp.custo_calculado),0) AS custo_total,
           COALESCE(SUM(cp.quantidade_usada),0) AS qty_total
    FROM cuidados_plantio cp
    JOIN plantios p  ON cp.id_plantio = p.id_plantio
    JOIN culturas c  ON p.id_cultura  = c.id_cultura
    WHERE c.id_usuario = $id_usuario
    GROUP BY cp.tipo_manejo
    ORDER BY total_ops DESC
");
$aba3 = $q3 ? $q3->fetch_all(MYSQLI_ASSOC) : [];

// ─── ABA 4: Estoque x Uso ────────────────────────────────────────────────────
$q4 = $conn->query("
    SELECT e.nome_item,
           e.categoria,
           e.unidade_medida,
           e.quantidade                     AS estoque_atual,
           COALESCE(SUM(cp.quantidade_usada),0) AS total_usado,
           e.custo_aquisicao
    FROM estoque e
    LEFT JOIN cuidados_plantio cp ON cp.id_item = e.id_item
    WHERE e.id_usuario = $id_usuario
    GROUP BY e.id_item
    ORDER BY total_usado DESC
    LIMIT 25
");
$aba4 = $q4 ? $q4->fetch_all(MYSQLI_ASSOC) : [];

// ─── ABA 5: Ranking de Culturas ──────────────────────────────────────────────
$q5 = $conn->query("
    SELECT c.nome_cultura,
           cat.nome_categoria,
           COUNT(DISTINCT p.id_plantio)          AS total_plantios,
           COALESCE(SUM(col.quantidade_colhida),0) AS kg_total,
           c.tempo_medio_crescimento
    FROM culturas c
    JOIN categorias cat ON c.id_categoria = cat.id_categoria
    LEFT JOIN plantios p   ON p.id_cultura  = c.id_cultura
    LEFT JOIN colheitas col ON col.id_plantio = p.id_plantio
    WHERE c.id_usuario = $id_usuario
    GROUP BY c.id_cultura
    ORDER BY kg_total DESC
    LIMIT 15
");
$aba5 = $q5 ? $q5->fetch_all(MYSQLI_ASSOC) : [];

$max_kg = count($aba1) > 0 ? max(array_column($aba1, 'total_kg')) : 1;
if ($max_kg < 1) $max_kg = 1;

$activePage = 'relatorios_avancados';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão — Relatórios Avançados</title>
    <meta name="description" content="Relatórios avançados com análises cruzadas entre culturas, plantios, manejos e colheitas.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script>if(localStorage.getItem('agro_theme')==='dark')document.documentElement.classList.add('dark-theme');</script>
    <style>
        .adv-tabs { display:flex; gap:6px; overflow-x:auto; padding-bottom:4px; margin-bottom:22px; scrollbar-width:none; }
        .adv-tabs::-webkit-scrollbar { display:none; }
        .adv-tab {
            flex-shrink:0; display:flex; align-items:center; gap:7px;
            padding:9px 16px; border-radius:50px; border:1.5px solid var(--border-color);
            background:var(--card-bg); font-size:13px; font-weight:700; cursor:pointer;
            color:var(--text-gray); font-family:inherit; transition:all .2s;
        }
        .adv-tab:hover { border-color:#22c55e; color:#16a34a; }
        .adv-tab.active { background:linear-gradient(135deg,#16a34a,#22c55e); color:white; border-color:transparent; box-shadow:0 4px 15px rgba(34,197,94,.3); }
        .adv-panel { display:none; }
        .adv-panel.active { display:block; }

        .adv-table { width:100%; border-collapse:collapse; font-size:13px; }
        .adv-table th { padding:10px 14px; text-align:left; font-weight:800; color:var(--text-gray); font-size:11px; text-transform:uppercase; background:var(--form-bg,#f9fafb); }
        .adv-table td { padding:10px 14px; border-bottom:1px solid var(--border-color); }
        .adv-table tbody tr:hover { background:var(--active-bg); }

        .bar-wrap { display:flex; align-items:center; gap:8px; }
        .bar-bg { flex:1; background:var(--border-color); border-radius:50px; height:8px; overflow:hidden; }
        .bar-fill { height:100%; border-radius:50px; background:var(--primary-green); transition:width .5s; }
        .bar-val { font-size:11px; font-weight:800; color:var(--primary-green); min-width:50px; text-align:right; }

        .tipo-icon { width:32px; height:32px; border-radius:8px; display:flex; justify-content:center; align-items:center; font-size:14px; flex-shrink:0; }
        .rank-num { font-size:20px; font-weight:900; color:var(--border-color); width:28px; flex-shrink:0; }
        .rank-gold   { color:#f59e0b; }
        .rank-silver { color:#9ca3af; }
        .rank-bronze { color:#b45309; }

        .export-bar { display:flex; gap:8px; margin-bottom:14px; justify-content:flex-end; }
        .btn-export { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border:1.5px solid var(--border-color); background:var(--form-bg,#f9fafb); border-radius:10px; font-size:12px; font-weight:700; cursor:pointer; font-family:inherit; color:var(--dark-green); }
        .btn-export:hover { border-color:#22c55e; background:rgba(34,197,94,.08); }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-btn" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
                <div class="topbar-title">Relatórios Avançados</div>
            </div>
        </header>

        <main class="main-content">
            <div class="content-wrapper">

                <div class="page-header">
                    <h1>Relatórios Avançados</h1>
                    <p>Análises cruzadas entre culturas, plantios, manejos, estoque e colheitas.</p>
                </div>

                <!-- Tabs -->
                <div class="adv-tabs">
                    <button class="adv-tab active" onclick="showTab('prod',this)"><i class="fa-solid fa-seedling"></i> Produtividade</button>
                    <button class="adv-tab" onclick="showTab('custo',this)"><i class="fa-solid fa-coins"></i> Custo vs Rendimento</button>
                    <button class="adv-tab" onclick="showTab('manejos',this)"><i class="fa-solid fa-droplet"></i> Manejos</button>
                    <button class="adv-tab" onclick="showTab('estoque',this)"><i class="fa-solid fa-box"></i> Estoque x Uso</button>
                    <button class="adv-tab" onclick="showTab('ranking',this)"><i class="fa-solid fa-trophy"></i> Ranking</button>
                </div>

                <!-- ═══ ABA 1: PRODUTIVIDADE ══════════════════════════════════ -->
                <div class="adv-panel active" id="panel-prod">
                    <div class="export-bar">
                        <button class="btn-export" onclick="exportTableToExcel('tbl-prod','produtividade_culturas')"><i class="fa-solid fa-file-excel"></i> Excel</button>
                        <button class="btn-export" onclick="exportToPDF()"><i class="fa-solid fa-file-pdf"></i> PDF</button>
                    </div>
                    <div class="report-card">
                        <h3 style="font-size:15px;font-weight:800;color:var(--text-main);margin:0 0 16px;">Produtividade por Cultura (kg colhido)</h3>
                        <?php if (empty($aba1)): ?>
                            <p style="color:var(--text-gray);font-size:13px;">Nenhuma colheita registrada ainda.</p>
                        <?php else: ?>
                            <?php foreach ($aba1 as $r):
                                $pct = $max_kg > 0 ? round($r['total_kg'] / $max_kg * 100) : 0;
                            ?>
                                <div style="margin-bottom:14px;">
                                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                                        <span style="font-size:13px;font-weight:700;color:var(--text-main);"><?php echo htmlspecialchars($r['nome_cultura']); ?></span>
                                        <span style="font-size:11px;color:var(--text-gray);"><?php echo $r['n_colheitas']; ?> colheitas · <?php echo $r['n_plantios']; ?> plantios</span>
                                    </div>
                                    <div class="bar-wrap">
                                        <div class="bar-bg"><div class="bar-fill" style="width:<?php echo $pct; ?>%;"></div></div>
                                        <span class="bar-val"><?php echo number_format($r['total_kg'],1,',','.'); ?> kg</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <table id="tbl-prod" style="display:none;">
                            <thead><tr><th>Cultura</th><th>Plantios</th><th>Colheitas</th><th>Total kg</th></tr></thead>
                            <tbody>
                                <?php foreach ($aba1 as $r): ?>
                                    <tr><td><?php echo htmlspecialchars($r['nome_cultura']); ?></td><td><?php echo $r['n_plantios']; ?></td><td><?php echo $r['n_colheitas']; ?></td><td><?php echo number_format($r['total_kg'],2,',','.'); ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ═══ ABA 2: CUSTO VS RENDIMENTO ══════════════════════════════ -->
                <div class="adv-panel" id="panel-custo">
                    <div class="export-bar">
                        <button class="btn-export" onclick="exportTableToExcel('tbl-custo','custo_rendimento')"><i class="fa-solid fa-file-excel"></i> Excel</button>
                        <button class="btn-export" onclick="exportToPDF()"><i class="fa-solid fa-file-pdf"></i> PDF</button>
                    </div>
                    <div class="report-card">
                        <h3 style="font-size:15px;font-weight:800;color:var(--text-main);margin:0 0 16px;">Custo de Manejo vs Kg Colhido por Cultura</h3>
                        <div style="overflow-x:auto;">
                            <table class="adv-table" id="tbl-custo">
                                <thead>
                                    <tr>
                                        <th>Cultura</th>
                                        <th style="text-align:right;">Custo Total (R$)</th>
                                        <th style="text-align:right;">Kg Colhidos</th>
                                        <th style="text-align:right;">Custo/kg (R$)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($aba2)): ?>
                                        <tr><td colspan="4" style="text-align:center;color:var(--text-gray);padding:20px;">Sem dados suficientes.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($aba2 as $r):
                                            $ckg = ($r['kg_total'] > 0) ? $r['custo_total'] / $r['kg_total'] : 0;
                                        ?>
                                            <tr>
                                                <td style="font-weight:700;color:var(--dark-green);"><?php echo htmlspecialchars($r['nome_cultura']); ?></td>
                                                <td style="text-align:right;font-weight:700;color:#ef4444;">R$ <?php echo number_format($r['custo_total'],2,',','.'); ?></td>
                                                <td style="text-align:right;font-weight:700;color:#22c55e;"><?php echo number_format($r['kg_total'],1,',','.'); ?> kg</td>
                                                <td style="text-align:right;color:var(--text-gray);">
                                                    <?php echo $ckg > 0 ? 'R$ '.number_format($ckg,2,',','.') : '—'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ═══ ABA 3: MANEJOS POR TIPO ═════════════════════════════════ -->
                <div class="adv-panel" id="panel-manejos">
                    <div class="export-bar">
                        <button class="btn-export" onclick="exportTableToExcel('tbl-manejos','manejos_por_tipo')"><i class="fa-solid fa-file-excel"></i> Excel</button>
                        <button class="btn-export" onclick="exportToPDF()"><i class="fa-solid fa-file-pdf"></i> PDF</button>
                    </div>
                    <div class="report-card">
                        <h3 style="font-size:15px;font-weight:800;color:var(--text-main);margin:0 0 16px;">Manejos Realizados por Tipo</h3>
                        <?php
                        $tipo_colors = ['Irrigacao'=>'#3b82f6','Adubacao'=>'#f59e0b','Defensivo'=>'#ef4444','Outro'=>'#9ca3af'];
                        $tipo_icons  = ['Irrigacao'=>'fa-droplet','Adubacao'=>'fa-leaf','Defensivo'=>'fa-flask','Outro'=>'fa-pen'];
                        $tipo_labels = ['Irrigacao'=>'Irrigação','Adubacao'=>'Adubação','Defensivo'=>'Defensivo','Outro'=>'Outro'];
                        ?>
                        <?php if (empty($aba3)): ?>
                            <p style="color:var(--text-gray);font-size:13px;">Nenhum manejo registrado.</p>
                        <?php else: ?>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:20px;">
                                <?php foreach ($aba3 as $r):
                                    $col = $tipo_colors[$r['tipo_manejo']] ?? '#9ca3af';
                                    $ic  = $tipo_icons[$r['tipo_manejo']]  ?? 'fa-pen';
                                    $lbl = $tipo_labels[$r['tipo_manejo']] ?? $r['tipo_manejo'];
                                ?>
                                    <div style="background:var(--form-bg,#f9fafb);border:1px solid var(--border-color);border-radius:14px;padding:16px;text-align:center;">
                                        <div style="width:48px;height:48px;border-radius:12px;background:<?php echo $col; ?>22;display:flex;justify-content:center;align-items:center;margin:0 auto 10px;font-size:20px;color:<?php echo $col; ?>;">
                                            <i class="fa-solid <?php echo $ic; ?>"></i>
                                        </div>
                                        <div style="font-size:24px;font-weight:900;color:<?php echo $col; ?>;"><?php echo $r['total_ops']; ?></div>
                                        <div style="font-size:12px;font-weight:700;color:var(--text-gray);margin-top:2px;"><?php echo $lbl; ?></div>
                                        <div style="font-size:11px;color:var(--text-gray);margin-top:4px;">R$ <?php echo number_format($r['custo_total'],2,',','.'); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <table class="adv-table" id="tbl-manejos">
                                <thead><tr><th>Tipo</th><th style="text-align:center;">Operações</th><th style="text-align:right;">Qty Total</th><th style="text-align:right;">Custo Total</th></tr></thead>
                                <tbody>
                                    <?php foreach ($aba3 as $r): ?>
                                        <tr>
                                            <td style="font-weight:700;"><?php echo $tipo_labels[$r['tipo_manejo']] ?? htmlspecialchars($r['tipo_manejo']); ?></td>
                                            <td style="text-align:center;font-weight:800;color:var(--primary-green);"><?php echo $r['total_ops']; ?></td>
                                            <td style="text-align:right;"><?php echo number_format($r['qty_total'],2,',','.'); ?></td>
                                            <td style="text-align:right;font-weight:700;">R$ <?php echo number_format($r['custo_total'],2,',','.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ═══ ABA 4: ESTOQUE X USO ═════════════════════════════════════ -->
                <div class="adv-panel" id="panel-estoque">
                    <div class="export-bar">
                        <button class="btn-export" onclick="exportTableToExcel('tbl-estoque','estoque_vs_uso')"><i class="fa-solid fa-file-excel"></i> Excel</button>
                        <button class="btn-export" onclick="exportToPDF()"><i class="fa-solid fa-file-pdf"></i> PDF</button>
                    </div>
                    <div class="report-card">
                        <h3 style="font-size:15px;font-weight:800;color:var(--text-main);margin:0 0 16px;">Estoque Atual vs Total Consumido em Manejos</h3>
                        <div style="overflow-x:auto;">
                            <table class="adv-table" id="tbl-estoque">
                                <thead>
                                    <tr>
                                        <th>Insumo</th>
                                        <th>Categoria</th>
                                        <th style="text-align:right;">Em Estoque</th>
                                        <th style="text-align:right;">Já Consumido</th>
                                        <th style="text-align:right;">Custo Unit.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($aba4)): ?>
                                        <tr><td colspan="5" style="text-align:center;color:var(--text-gray);padding:20px;">Nenhum item em estoque.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($aba4 as $r): ?>
                                            <tr>
                                                <td style="font-weight:700;color:var(--dark-green);"><?php echo htmlspecialchars($r['nome_item']); ?></td>
                                                <td><span style="font-size:11px;color:var(--text-gray);"><?php echo htmlspecialchars($r['categoria']); ?></span></td>
                                                <td style="text-align:right;font-weight:700;color:<?php echo $r['estoque_atual'] <= 0 ? '#ef4444' : '#22c55e'; ?>;">
                                                    <?php echo number_format($r['estoque_atual'],2,',','.'); ?> <?php echo htmlspecialchars($r['unidade_medida']); ?>
                                                </td>
                                                <td style="text-align:right;font-weight:700;color:#f59e0b;">
                                                    <?php echo number_format($r['total_usado'],2,',','.'); ?> <?php echo htmlspecialchars($r['unidade_medida']); ?>
                                                </td>
                                                <td style="text-align:right;color:var(--text-gray);">
                                                    <?php echo $r['custo_aquisicao'] ? 'R$ '.number_format($r['custo_aquisicao'],2,',','.') : '—'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ═══ ABA 5: RANKING DE CULTURAS ══════════════════════════════ -->
                <div class="adv-panel" id="panel-ranking">
                    <div class="export-bar">
                        <button class="btn-export" onclick="exportTableToExcel('tbl-ranking','ranking_culturas')"><i class="fa-solid fa-file-excel"></i> Excel</button>
                        <button class="btn-export" onclick="exportToPDF()"><i class="fa-solid fa-file-pdf"></i> PDF</button>
                    </div>
                    <div class="report-card">
                        <h3 style="font-size:15px;font-weight:800;color:var(--text-main);margin:0 0 16px;">Ranking de Produção — Top Culturas</h3>
                        <?php if (empty($aba5)): ?>
                            <p style="color:var(--text-gray);font-size:13px;">Nenhuma colheita registrada ainda.</p>
                        <?php else: ?>
                            <?php foreach ($aba5 as $i => $r):
                                $rank_class = $i === 0 ? 'rank-gold' : ($i === 1 ? 'rank-silver' : ($i === 2 ? 'rank-bronze' : ''));
                            ?>
                                <div style="display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--border-color);">
                                    <span class="rank-num <?php echo $rank_class; ?>">#<?php echo $i+1; ?></span>
                                    <div style="flex:1;">
                                        <div style="font-size:14px;font-weight:800;color:var(--text-main);"><?php echo htmlspecialchars($r['nome_cultura']); ?></div>
                                        <div style="font-size:11px;color:var(--text-gray);"><?php echo htmlspecialchars($r['nome_categoria']); ?> · <?php echo $r['total_plantios']; ?> plantio(s) · ciclo: <?php echo $r['tempo_medio_crescimento'] ?? '—'; ?> dias</div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-size:18px;font-weight:900;color:var(--primary-green);"><?php echo number_format($r['kg_total'],1,',','.'); ?> kg</div>
                                        <div style="font-size:10px;color:var(--text-gray);font-weight:700;">TOTAL COLHIDO</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <table id="tbl-ranking" style="display:none;">
                                <thead><tr><th>Posição</th><th>Cultura</th><th>Categoria</th><th>Plantios</th><th>Kg Colhido</th></tr></thead>
                                <tbody>
                                    <?php foreach ($aba5 as $i => $r): ?>
                                        <tr><td><?php echo $i+1; ?></td><td><?php echo htmlspecialchars($r['nome_cultura']); ?></td><td><?php echo htmlspecialchars($r['nome_categoria']); ?></td><td><?php echo $r['total_plantios']; ?></td><td><?php echo number_format($r['kg_total'],2,',','.'); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
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
function showTab(id, btn) {
    document.querySelectorAll('.adv-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.adv-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('panel-' + id).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>
