<?php
require_once '../Banco/conecao.php';
require_once 'auth.php';

// Garantir login
verificar_login();

// 1. Quilos colhidos por categoria (Horta vs Pomar)
$q_horta = mysqli_query($conexao, "SELECT SUM(c.quantidade_colhida) as total FROM colheitas c JOIN plantios p ON c.id_plantio = p.id_plantio JOIN culturas cult ON p.id_cultura = cult.id_cultura WHERE cult.id_categoria = 1");
$total_horta = $q_horta ? (mysqli_fetch_assoc($q_horta)['total'] ?? 0) : 0;

$q_pomar = mysqli_query($conexao, "SELECT SUM(c.quantidade_colhida) as total FROM colheitas c JOIN plantios p ON c.id_plantio = p.id_plantio JOIN culturas cult ON p.id_cultura = cult.id_cultura WHERE cult.id_categoria = 2");
$total_pomar = $q_pomar ? (mysqli_fetch_assoc($q_pomar)['total'] ?? 0) : 0;

// 2. Produtividade Mensal (últimos 6 meses)
$mensal = array_fill(1, 6, 0); // Jan a Jun
$q_mes = mysqli_query($conexao, "SELECT MONTH(data_colheita) as mes, SUM(quantidade_colhida) as total FROM colheitas WHERE data_colheita >= '2026-01-01' AND data_colheita <= '2026-06-30' GROUP BY MONTH(data_colheita)");
if ($q_mes) {
    while ($row = mysqli_fetch_assoc($q_mes)) {
        $m = intval($row['mes']);
        if ($m >= 1 && $m <= 6) {
            $mensal[$m] = floatval($row['total']);
        }
    }
}

// 3. Atividades Recentes (últimas 5 colheitas)
$q_recentes = mysqli_query($conexao, "SELECT c.*, cult.nome_cultura FROM colheitas c JOIN plantios p ON c.id_plantio = p.id_plantio JOIN culturas cult ON p.id_cultura = cult.id_cultura ORDER BY c.id_colheita DESC LIMIT 5");
$recentes = [];
if ($q_recentes) {
    while ($row = mysqli_fetch_assoc($q_recentes)) {
        $recentes[] = $row;
    }
}

// Escalar as barras dos gráficos dinamicamente
// Gráfico 1 (Horta vs Pomar) - Escala baseada no máximo entre Horta e Pomar
$max_setor = max($total_horta, $total_pomar, 10); // Evitar divisão por zero
$height_horta = ($total_horta / $max_setor) * 150;
$height_pomar = ($total_pomar / $max_setor) * 150;
$y_horta = 200 - $height_horta;
$y_pomar = 200 - $height_pomar;

// Gráfico 2 (Mensal) - Escala baseada no máximo mensal
$max_mensal = max(max($mensal), 10);
$heights_mensal = [];
$y_mensal = [];
foreach ($mensal as $m => $val) {
    $h = ($val / $max_mensal) * 150;
    $heights_mensal[$m] = $h;
    $y_mensal[$m] = 200 - $h;
}

$activePage = 'relatorios';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão - Relatórios</title>
    
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

        <!-- MAIN CONTENT -->
        <div class="main-wrapper">
            
            <header class="topbar">
                <div class="topbar-left">
                    <button class="menu-btn" onclick="toggleMenu()">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="topbar-title">Relatórios e Métricas</div>
                </div>
            </header>

            <main class="main-content">
                <div class="content-wrapper">
                    
                    <div class="page-header">
                        <h1>Desempenho Geral</h1>
                        <p>Acompanhe a produtividade e compare setores da sua fazenda.</p>
                    </div>

                    <!-- GRELHA DOS GRÁFICOS -->
                    <div class="reports-grid">
                        
                        <!-- GRÁFICO 1: HORTA VS POMAR -->
                        <div class="report-card">
                            <div class="rc-subtitle">MÉTRICAS DE COLHEITA (KG)</div>
                            <div class="rc-header-row">
                                <h2 class="rc-title">Horta vs Pomar (Acumulado)</h2>
                                <div style="display: flex; gap: 10px; font-size: 11px; font-weight: 800;">
                                    <span style="color: var(--primary-green);"><i class="fa-solid fa-square"></i> HORTA</span>
                                    <span style="color: var(--orange-chart);"><i class="fa-solid fa-square"></i> POMAR</span>
                                </div>
                            </div>

                            <div class="svg-container">
                                <svg class="chart-svg" viewBox="0 0 400 220" preserveAspectRatio="none">
                                    <line x1="30" y1="20" x2="400" y2="20" stroke="#f3f4f6" stroke-width="1"/>
                                    <line x1="30" y1="110" x2="400" y2="110" stroke="#f3f4f6" stroke-width="1"/>
                                    <line x1="30" y1="200" x2="400" y2="200" stroke="#e5e7eb" stroke-width="2"/>
                                    
                                    <text x="20" y="24" font-size="10" font-weight="700" fill="#9ca3af" text-anchor="end"><?php echo round($max_setor); ?></text>
                                    <text x="20" y="114" font-size="10" font-weight="700" fill="#9ca3af" text-anchor="end"><?php echo round($max_setor / 2); ?></text>
                                    <text x="20" y="204" font-size="10" font-weight="700" fill="#9ca3af" text-anchor="end">0</text>

                                    <text x="140" y="218" font-size="11" font-weight="800" fill="#1f2937" text-anchor="middle">HORTA</text>
                                    <text x="260" y="218" font-size="11" font-weight="800" fill="#1f2937" text-anchor="middle">POMAR</text>

                                    <!-- Barra Horta -->
                                    <rect x="120" y="<?php echo $y_horta; ?>" width="40" height="<?php echo $height_horta; ?>" fill="var(--primary-green)" />
                                    <text x="140" y="<?php echo $y_horta - 6; ?>" font-size="11" font-weight="800" fill="#1f2937" text-anchor="middle"><?php echo number_format($total_horta, 1, ',', '.'); ?> kg</text>

                                    <!-- Barra Pomar -->
                                    <rect x="240" y="<?php echo $y_pomar; ?>" width="40" height="<?php echo $height_pomar; ?>" fill="var(--orange-chart)" />
                                    <text x="260" y="<?php echo $y_pomar - 6; ?>" font-size="11" font-weight="800" fill="#1f2937" text-anchor="middle"><?php echo number_format($total_pomar, 1, ',', '.'); ?> kg</text>
                                </svg>
                            </div>
                        </div>

                        <!-- GRÁFICO 2: PRODUTIVIDADE MENSAL -->
                        <div class="report-card">
                            <div class="rc-header-row">
                                <h2 class="rc-title">Produtividade Mensal (2026)</h2>
                                <div class="rc-badge">QUILOS (KG)</div>
                            </div>

                            <div class="svg-container">
                                <svg class="chart-svg" viewBox="0 0 400 220" preserveAspectRatio="none">
                                    <line x1="30" y1="20" x2="400" y2="20" stroke="#f3f4f6" stroke-width="1"/>
                                    <line x1="30" y1="110" x2="400" y2="110" stroke="#f3f4f6" stroke-width="1"/>
                                    <line x1="30" y1="200" x2="400" y2="200" stroke="#e5e7eb" stroke-width="2"/>
                                    
                                    <text x="20" y="24" font-size="10" font-weight="700" fill="#9ca3af" text-anchor="end"><?php echo round($max_mensal); ?></text>
                                    <text x="20" y="114" font-size="10" font-weight="700" fill="#9ca3af" text-anchor="end"><?php echo round($max_mensal / 2); ?></text>
                                    <text x="20" y="204" font-size="10" font-weight="700" fill="#9ca3af" text-anchor="end">0</text>

                                    <text x="60" y="218" font-size="11" font-weight="800" fill="#1f2937" text-anchor="middle">JAN</text>
                                    <text x="120" y="218" font-size="11" font-weight="800" fill="#1f2937" text-anchor="middle">FEV</text>
                                    <text x="180" y="218" font-size="11" font-weight="800" fill="#1f2937" text-anchor="middle">MAR</text>
                                    <text x="240" y="218" font-size="11" font-weight="800" fill="#1f2937" text-anchor="middle">ABR</text>
                                    <text x="300" y="218" font-size="11" font-weight="800" fill="#1f2937" text-anchor="middle">MAI</text>
                                    <text x="360" y="218" font-size="11" font-weight="800" fill="#1f2937" text-anchor="middle">JUN</text>

                                    <!-- Barras Mensais -->
                                    <rect x="50" y="<?php echo $y_mensal[1]; ?>" width="20" height="<?php echo $heights_mensal[1]; ?>" fill="var(--primary-green)" rx="4" ry="4" />
                                    <rect x="110" y="<?php echo $y_mensal[2]; ?>" width="20" height="<?php echo $heights_mensal[2]; ?>" fill="var(--primary-green)" rx="4" ry="4" />
                                    <rect x="170" y="<?php echo $y_mensal[3]; ?>" width="20" height="<?php echo $heights_mensal[3]; ?>" fill="var(--primary-green)" rx="4" ry="4" />
                                    <rect x="230" y="<?php echo $y_mensal[4]; ?>" width="20" height="<?php echo $heights_mensal[4]; ?>" fill="var(--dark-green)" rx="4" ry="4" />
                                    <rect x="290" y="<?php echo $y_mensal[5]; ?>" width="20" height="<?php echo $heights_mensal[5]; ?>" fill="var(--primary-green)" rx="4" ry="4" />
                                    <rect x="350" y="<?php echo $y_mensal[6]; ?>" width="20" height="<?php echo $heights_mensal[6]; ?>" fill="var(--primary-green)" rx="4" ry="4" />
                                </svg>
                            </div>
                        </div>

                    </div>

                    <!-- ATIVIDADES RECENTES -->
                    <div class="activities-section">
                        <h3 class="chart-title">Colheitas Recentes</h3>
                        
                        <?php if (count($recentes) === 0): ?>
                            <div class="activity-card" style="justify-content: center; color: var(--text-gray);">
                                Nenhuma atividade de colheita recente registrada.
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentes as $act): 
                                $data_pt = date('d/m/Y', strtotime($act['data_colheita']));
                            ?>
                                <div class="activity-card">
                                    <div class="act-left">
                                        <div class="act-icon icon-green"><i class="fa-solid fa-check"></i></div>
                                        <div class="act-info">
                                            <h4>Colheita Registrada</h4>
                                            <p><?php echo htmlspecialchars($act['nome_cultura']); ?> • <?php echo htmlspecialchars(number_format($act['quantidade_colhida'], 1, ',', '.')); ?> kg</p>
                                        </div>
                                    </div>
                                    <div class="act-time"><?php echo $data_pt; ?></div>
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
