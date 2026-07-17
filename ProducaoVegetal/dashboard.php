<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';

verificar_login();

$id_usuario = $_SESSION['user_id'];

// --- Dados para os cards do topo ---
$q_culturas  = mysqli_query($conn, "SELECT id_cultura FROM culturas WHERE " . escopo_sql('id_usuario'));
$total_culturas = $q_culturas ? mysqli_num_rows($q_culturas) : 0;

$q_plantios  = mysqli_query($conn, "SELECT p.id_plantio FROM plantios p JOIN culturas c ON p.id_cultura = c.id_cultura WHERE p.colhido = 0 AND " . escopo_sql('c.id_usuario'));
$total_plantios = $q_plantios ? mysqli_num_rows($q_plantios) : 0;

$q_insumos   = mysqli_query($conn, "SELECT id_item FROM estoque WHERE 1=1");
$total_insumos = $q_insumos ? mysqli_num_rows($q_insumos) : 0;

$q_colheita  = mysqli_query($conn, "SELECT SUM(col.quantidade_colhida) AS total FROM colheitas col JOIN plantios p ON col.id_plantio = p.id_plantio JOIN culturas c ON p.id_cultura = c.id_cultura WHERE " . escopo_sql('c.id_usuario'));
$total_colhido = 0;
if ($q_colheita) { $row = mysqli_fetch_assoc($q_colheita); $total_colhido = $row['total'] ?? 0; }

// --- Alertas Críticos: Estoque abaixo do nível mínimo OU a vencer em 30 dias ---
$alertas = [];

$q_alerta_min = mysqli_query($conn, "SELECT nome_item, quantidade, nivel_alerta, unidade_medida FROM estoque WHERE status_estoque = 'Alerta'");
if ($q_alerta_min) {
    while ($row = mysqli_fetch_assoc($q_alerta_min)) {
        $alertas[] = ['tipo' => 'estoque', 'msg' => "Estoque crítico: <b>" . htmlspecialchars($row['nome_item']) . "</b> — " . number_format($row['quantidade'],2,',','.') . " " . htmlspecialchars($row['unidade_medida']) . " (mín: {$row['nivel_alerta']})"];
    }
}

$q_alerta_validade = mysqli_query($conn, "SELECT nome_item, data_validade FROM estoque WHERE data_validade IS NOT NULL AND data_validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND data_validade >= CURDATE()");
if ($q_alerta_validade) {
    while ($row = mysqli_fetch_assoc($q_alerta_validade)) {
        $data_fmt = date('d/m/Y', strtotime($row['data_validade']));
        $alertas[] = ['tipo' => 'validade', 'msg' => "Próximo ao vencimento: <b>" . htmlspecialchars($row['nome_item']) . "</b> — vence em <b>{$data_fmt}</b>"];
    }
}

// --- Custo total de insumos (Admin only) ---
$custo_total_insumos = 0;
if (e_admin()) {
    $q_custo = mysqli_query($conn, "SELECT SUM(quantidade * custo_aquisicao) AS total FROM estoque WHERE 1=1");
    if ($q_custo) { $r = mysqli_fetch_assoc($q_custo); $custo_total_insumos = $r['total'] ?? 0; }
}

// --- Últimos 5 meses de dados dinâmicos para estatísticas ---
$meses_nomes = [1=>'Jan', 2=>'Fev', 3=>'Mar', 4=>'Abr', 5=>'Mai', 6=>'Jun', 7=>'Jul', 8=>'Ago', 9=>'Set', 10=>'Out', 11=>'Nov', 12=>'Dez'];
$ultimos_meses = [];
for ($i = 4; $i >= 0; $i--) {
    $time = strtotime("-$i months");
    $m = intval(date('n', $time));
    $y = intval(date('Y', $time));
    $ultimos_meses[] = [
        'num' => $m,
        'ano' => $y,
        'label' => $meses_nomes[$m],
        'plantios' => 0,
        'colheido' => 0.0
    ];
}

// Buscar plantios por mês
$q_stats_plantios = mysqli_query($conn, "
    SELECT MONTH(p.data_plantio) as mes, YEAR(p.data_plantio) as ano, COUNT(*) as total
    FROM plantios p
    JOIN culturas c ON p.id_cultura = c.id_cultura
    WHERE p.data_plantio >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
      AND " . escopo_sql('c.id_usuario') . "
    GROUP BY YEAR(p.data_plantio), MONTH(p.data_plantio)
");
if ($q_stats_plantios) {
    while ($row = mysqli_fetch_assoc($q_stats_plantios)) {
        foreach ($ultimos_meses as &$um) {
            if ($um['num'] == intval($row['mes']) && $um['ano'] == intval($row['ano'])) {
                $um['plantios'] = intval($row['total']);
            }
        }
    }
    unset($um);
}

// Buscar colheitas por mês
$q_stats_colheitas = mysqli_query($conn, "
    SELECT MONTH(col.data_colheita) as mes, YEAR(col.data_colheita) as ano, SUM(col.quantidade_colhida) as total
    FROM colheitas col
    JOIN plantios p ON col.id_plantio = p.id_plantio
    JOIN culturas c ON p.id_cultura = c.id_cultura
    WHERE col.data_colheita >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
      AND " . escopo_sql('c.id_usuario') . "
    GROUP BY YEAR(col.data_colheita), MONTH(col.data_colheita)
");
if ($q_stats_colheitas) {
    while ($row = mysqli_fetch_assoc($q_stats_colheitas)) {
        foreach ($ultimos_meses as &$um) {
            if ($um['num'] == intval($row['mes']) && $um['ano'] == intval($row['ano'])) {
                $um['colheido'] = floatval($row['total']);
            }
        }
    }
    unset($um);
}

// Valores máximos para escala dos gráficos
$max_plantios = 1;
$max_colheito = 1.0;
foreach ($ultimos_meses as $um) {
    if ($um['plantios'] > $max_plantios) $max_plantios = $um['plantios'];
    if ($um['colheido'] > $max_colheito) $max_colheito = $um['colheido'];
}

$iniciais_top = obter_iniciais($_SESSION['user_nome']);
$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão - Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script>if (localStorage.getItem('agro_theme') === 'dark') document.documentElement.classList.add('dark-theme');</script>
</head>
<body>
    <div class="app-layout">
        <?php include 'sidebar.php'; ?>

        <div class="main-wrapper">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="menu-btn" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
                    <div class="topbar-title">AgroGestão</div>
                </div>
                <div class="user-avatar-top" style="<?php echo !empty($_SESSION['user_foto']) ? 'padding:0;overflow:hidden;' : ''; ?>">
                    <?php if (!empty($_SESSION['user_foto'])): ?>
                        <img src="<?php echo htmlspecialchars($_SESSION['user_foto']); ?>" alt="Foto"
                             style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: ?>
                        <?php echo htmlspecialchars($iniciais_top); ?>
                    <?php endif; ?>
                </div>
            </header>

            <main class="main-content">
                <div class="content-wrapper">

                    <h1 class="greeting">Olá, <?php echo htmlspecialchars(explode(' ', trim($_SESSION['user_nome']))[0]); ?>!</h1>

                    <?php if (isset($_GET['erro']) && $_GET['erro'] === 'restrito'): ?>
                        <div style="background:#fee2e2;color:#ef4444;border:1px solid #fca5a5;padding:12px;border-radius:8px;margin-bottom:20px;font-weight:700;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Acesso Negado: Essa página é exclusiva para administradores.
                        </div>
                    <?php endif; ?>

                    <!-- ALERTAS CRÍTICOS -->
                    <?php if (!empty($alertas)): ?>
                        <div style="margin-bottom:20px;">
                            <?php foreach ($alertas as $alerta): ?>
                                <div style="display:flex;align-items:center;gap:10px;background:<?php echo $alerta['tipo']==='validade'?'#fef3c7':'#fee2e2'; ?>;color:<?php echo $alerta['tipo']==='validade'?'#b45309':'#ef4444'; ?>;border:1px solid <?php echo $alerta['tipo']==='validade'?'#fcd34d':'#fca5a5'; ?>;padding:10px 14px;border-radius:10px;margin-bottom:8px;font-size:13px;font-weight:600;">
                                    <i class="fa-solid <?php echo $alerta['tipo']==='validade'?'fa-clock':'fa-circle-exclamation'; ?>" style="font-size:16px;flex-shrink:0;"></i>
                                    <span><?php echo $alerta['msg']; ?></span>
                                    <a href="estoque.php" style="margin-left:auto;white-space:nowrap;font-size:12px;text-decoration:underline;color:inherit;">Ver Estoque →</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- CARDS SUPERIORES -->
                    <div class="top-cards-grid">
                        <a href="culturas_cadastradas.php" class="main-card mc-green">
                            <div class="mc-header">
                                <div class="mc-icon bg-icon-green"><i class="fa-solid fa-leaf"></i></div>
                                <div class="mc-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                            </div>
                            <div class="mc-info">
                                <h2>Catálogo</h2>
                                <p><?php echo $total_culturas; ?> culturas / <?php echo $total_plantios; ?> ativos</p>
                            </div>
                        </a>

                        <a href="estoque.php" class="main-card mc-orange">
                            <div class="mc-header">
                                <div class="mc-icon bg-icon-orange"><i class="fa-solid fa-box"></i></div>
                                <div class="mc-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                            </div>
                            <div class="mc-info">
                                <h2>Estoque</h2>
                                <p><?php echo $total_insumos; ?> Insumos cadastrados</p>
                                <?php if (!empty($alertas)): ?>
                                    <span style="font-size:11px;color:#ef4444;font-weight:700;"><i class="fa-solid fa-circle-exclamation"></i> <?php echo count($alertas); ?> alerta(s)</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>

                    <!-- CARD FINANCEIRO (Admin Only) -->
                    <?php if (e_admin()): ?>
                        <div style="background:linear-gradient(135deg,#16a34a,#15803d);border-radius:16px;padding:20px;margin-bottom:24px;color:white;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                            <div>
                                <div style="font-size:11px;font-weight:800;opacity:.8;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;"><i class="fa-solid fa-chart-line"></i> Visão Financeira (Admin)</div>
                                <div style="font-size:22px;font-weight:900;">R$ <?php echo number_format($custo_total_insumos, 2, ',', '.'); ?></div>
                                <div style="font-size:12px;opacity:.8;margin-top:2px;">Custo total em estoque</div>
                            </div>
                            <a href="relatorios.php" style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:white;text-decoration:none;padding:10px 18px;border-radius:10px;font-size:13px;font-weight:700;">
                                Ver Relatórios <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- MANEJO RÁPIDO -->
                    <div>
                        <div class="section-header"><h2>Manejo Rápido</h2></div>
                        <div class="action-cards-grid">
                            <?php if (!e_visitante()): ?>
                                <a href="cadastro_culturas.php" class="action-card">
                                    <div class="ac-icon ac-green"><i class="fa-solid fa-leaf"></i></div>
                                    <div class="ac-info"><h4>Nova Cultura</h4><p>Adicionar ao catálogo</p></div>
                                    <i class="fa-solid fa-chevron-right ac-arrow"></i>
                                </a>
                                <a href="cadastro_plantio.php" class="action-card">
                                    <div class="ac-icon ac-orange"><i class="fa-solid fa-seedling"></i></div>
                                    <div class="ac-info"><h4>Novo Plantio</h4><p>Registrar plantio ativo</p></div>
                                    <i class="fa-solid fa-chevron-right ac-arrow"></i>
                                </a>
                                <a href="plantios_ativos.php" class="action-card">
                                    <div class="ac-icon" style="background:rgba(59,130,246,.15);color:#3b82f6;"><i class="fa-solid fa-clipboard-list"></i></div>
                                    <div class="ac-info"><h4>Registrar Manejo</h4><p>Irrigar, adubar ou aplicar</p></div>
                                    <i class="fa-solid fa-chevron-right ac-arrow"></i>
                                </a>
                            <?php else: ?>
                                <div style="padding:20px;background:var(--form-bg);border-radius:16px;border:1px solid var(--border-color);color:var(--dark-green);text-align:center;width:100%;">
                                    <i class="fa-solid fa-circle-info" style="margin-right:8px;"></i>
                                    Você está logado como <b>Visitante</b>. Navegue pelo menu lateral para visualizar as listagens.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ESTATÍSTICAS -->
                    <div>
                        <div class="section-header"><h2>Estatísticas</h2></div>
                        <div class="stats-grid">
                            <div class="stat-box">
                                <div class="stat-header">
                                    <div class="stat-titles">
                                        <h3>Plantios Ativos</h3>
                                        <div class="stat-val"><?php echo $total_plantios; ?> Un.</div>
                                    </div>
                                    <div class="stat-legend"><span class="legend-dot dot-green"></span> Em Andamento</div>
                                </div>
                                <div class="stat-chart">
                                    <svg viewBox="0 0 300 150" preserveAspectRatio="none">
                                        <text x="25" y="25" font-size="11" font-weight="600" fill="var(--text-gray)" text-anchor="end"><?php echo $max_plantios; ?></text>
                                        <text x="25" y="75" font-size="11" font-weight="600" fill="var(--text-gray)" text-anchor="end"><?php echo ceil($max_plantios / 2); ?></text>
                                        <text x="25" y="125" font-size="11" font-weight="600" fill="var(--text-gray)" text-anchor="end">0</text>
                                        <line x1="35" y1="20" x2="300" y2="20" stroke="var(--border-color)" stroke-width="1" stroke-dasharray="4"/>
                                        <line x1="35" y1="70" x2="300" y2="70" stroke="var(--border-color)" stroke-width="1" stroke-dasharray="4"/>
                                        <line x1="35" y1="120" x2="300" y2="120" stroke="var(--border-color)" stroke-width="2"/>
                                        <?php 
                                        $label_xs = [60, 115, 170, 225, 280];
                                        foreach ($ultimos_meses as $idx => $um):
                                        ?>
                                            <text x="<?php echo $label_xs[$idx]; ?>" y="145" font-size="11" font-weight="600" fill="var(--text-gray)" text-anchor="middle"><?php echo $um['label']; ?></text>
                                        <?php endforeach; ?>
                                        <?php
                                        $bar_xs = [50, 105, 160, 215, 270];
                                        foreach ($ultimos_meses as $idx => $um):
                                            $h = ($um['plantios'] / $max_plantios) * 100;
                                            $y = 120 - $h;
                                        ?>
                                            <rect x="<?php echo $bar_xs[$idx]; ?>" y="<?php echo $y; ?>" width="20" height="<?php echo $h; ?>" fill="var(--primary-green)" rx="4"/>
                                        <?php endforeach; ?>
                                    </svg>
                                </div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-header">
                                    <div class="stat-titles">
                                        <h3>Volume Colhido</h3>
                                        <div class="stat-val"><?php echo number_format($total_colhido, 1, ',', '.'); ?> Kg</div>
                                    </div>
                                    <div class="stat-legend"><span class="legend-dot dot-orange"></span> Total Geral (Kg)</div>
                                </div>
                                <div class="stat-chart">
                                    <svg viewBox="0 0 300 150" preserveAspectRatio="none">
                                        <text x="30" y="25" font-size="11" font-weight="600" fill="var(--text-gray)" text-anchor="end"><?php echo round($max_colheito); ?></text>
                                        <text x="30" y="75" font-size="11" font-weight="600" fill="var(--text-gray)" text-anchor="end"><?php echo round($max_colheito / 2); ?></text>
                                        <text x="30" y="125" font-size="11" font-weight="600" fill="var(--text-gray)" text-anchor="end">0</text>
                                        <line x1="40" y1="20" x2="300" y2="20" stroke="var(--border-color)" stroke-width="1" stroke-dasharray="4"/>
                                        <line x1="40" y1="70" x2="300" y2="70" stroke="var(--border-color)" stroke-width="1" stroke-dasharray="4"/>
                                        <line x1="40" y1="120" x2="300" y2="120" stroke="var(--border-color)" stroke-width="2"/>
                                        <?php 
                                        $label_xs = [65, 120, 175, 230, 285];
                                        foreach ($ultimos_meses as $idx => $um):
                                        ?>
                                            <text x="<?php echo $label_xs[$idx]; ?>" y="145" font-size="11" font-weight="600" fill="var(--text-gray)" text-anchor="middle"><?php echo $um['label']; ?></text>
                                        <?php endforeach; ?>
                                        <?php
                                        $bar_xs = [55, 110, 165, 220, 275];
                                        foreach ($ultimos_meses as $idx => $um):
                                            $h = ($um['colheido'] / $max_colheito) * 100;
                                            $y = 120 - $h;
                                        ?>
                                            <rect x="<?php echo $bar_xs[$idx]; ?>" y="<?php echo $y; ?>" width="20" height="<?php echo $h; ?>" fill="var(--orange)" rx="4"/>
                                        <?php endforeach; ?>
                                    </svg>
                                </div>
                            </div>
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
    </script>
</body>
</html>
