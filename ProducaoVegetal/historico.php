<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';

// Garantir login
verificar_login();

$id_usuario = $_SESSION['user_id'];

$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'Todos';

// Query das colheitas
$query = "SELECT c.*, cult.nome_cultura, cat.nome_categoria, u.nome AS nome_registrador 
          FROM colheitas c 
          JOIN plantios p ON c.id_plantio = p.id_plantio 
          JOIN culturas cult ON p.id_cultura = cult.id_cultura 
          JOIN categorias cat ON cult.id_categoria = cat.id_categoria
          JOIN usuarios u ON cult.id_usuario = u.id_usuario
          WHERE " . escopo_sql('cult.id_usuario');

if ($filtro === 'Horta') {
    $query .= " AND cat.nome_categoria = 'Horta'";
} elseif ($filtro === 'Pomar') {
    $query .= " AND cat.nome_categoria = 'Pomar'";
}

$query .= " ORDER BY c.id_colheita DESC";
$result = mysqli_query($conn, $query);
$historico = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $historico[] = $row;
    }
}

$activePage = 'historico';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão - Histórico</title>
    
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

        <div class="main-wrapper">
            
            <header class="topbar">
                <div class="topbar-left">
                    <button class="menu-btn" onclick="toggleMenu()">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="topbar-title">Histórico de Colheitas</div>
                </div>
            </header>

            <main class="main-content">
                <div class="content-wrapper">
                    
                    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'colheita_sucesso'): ?>
                        <div style="background-color: #d1fae5; color: #10b981; border: 1px solid #6ee7b7; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 700;">
                            Parabéns! Colheita registrada e arquivada com sucesso.
                        </div>
                    <?php endif; ?>

                    <div class="export-bar" style="display: flex; gap: 8px; margin-bottom: 14px; justify-content: flex-end;">
                        <button class="btn-export" onclick="exportTableToExcel('tbl-historico', 'historico_colheitas')" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1.5px solid var(--border-color); background: var(--form-bg,#f9fafb); border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; color: var(--dark-green);">
                            <i class="fa-solid fa-file-excel"></i> Exportar Excel
                        </button>
                        <button class="btn-export" onclick="exportToPDF()" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1.5px solid var(--border-color); background: var(--form-bg,#f9fafb); border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; color: var(--dark-green);">
                            <i class="fa-solid fa-file-pdf"></i> Exportar PDF
                        </button>
                    </div>

                    <div class="filters-container">
                        <a href="historico.php?filtro=Todos" class="filter-btn <?php echo $filtro === 'Todos' ? 'active' : 'inactive'; ?>">Todos</a>
                        <a href="historico.php?filtro=Horta" class="filter-btn <?php echo $filtro === 'Horta' ? 'active' : 'inactive'; ?>">Horta</a>
                        <a href="historico.php?filtro=Pomar" class="filter-btn <?php echo $filtro === 'Pomar' ? 'active' : 'inactive'; ?>">Pomar</a>
                    </div>

                    <div class="history-list" id="history-container">
                        <?php if (count($historico) === 0): ?>
                            <div class="empty-state">Nenhuma colheita registrada no histórico.</div>
                        <?php else: ?>
                            <?php foreach ($historico as $item): 
                                $is_horta = ($item['nome_categoria'] === 'Horta');
                                $iconClass = $is_horta ? 'icon-horta' : 'icon-pomar';
                                $iconTag = $is_horta ? '<i class="fa-solid fa-seedling"></i>' : '<i class="fa-solid fa-apple-whole"></i>';
                                $badgeClass = $is_horta ? 'badge-horta' : 'badge-pomar';

                                // Formatando a data do banco (YYYY-MM-DD) para (DD Mês YYYY)
                                $data_formatada = date('d M Y', strtotime($item['data_colheita']));
                                // Traduzir meses curtos para português se desejado, ou manter simples
                                $meses = ["Jan" => "Jan", "Feb" => "Fev", "Mar" => "Mar", "Apr" => "Abr", "May" => "Mai", "Jun" => "Jun", "Jul" => "Jul", "Aug" => "Ago", "Sep" => "Set", "Oct" => "Out", "Nov" => "Nov", "Dec" => "Dez"];
                                $mes_ingles = date('M', strtotime($item['data_colheita']));
                                $mes_pt = isset($meses[$mes_ingles]) ? $meses[$mes_ingles] : $mes_ingles;
                                $data_formatada = date('d', strtotime($item['data_colheita'])) . ' ' . $mes_pt . ' ' . date('Y', strtotime($item['data_colheita']));
                            ?>
                                <div class="history-card">
                                    <div class="card-icon <?php echo $iconClass; ?>">
                                        <?php echo $iconTag; ?>
                                    </div>
                                    <div class="card-content">
                                        <div class="card-title-row">
                                            <h4><?php echo htmlspecialchars($item['nome_cultura']); ?></h4>
                                        </div>
                                        <div class="badge <?php echo $badgeClass; ?>"><?php echo strtoupper(htmlspecialchars($item['nome_categoria'])); ?></div>
                                        <div class="card-meta">
                                            <div><i class="fa-regular fa-calendar-days"></i> <?php echo $data_formatada; ?></div>
                                            <div><i class="fa-solid fa-caret-up"></i> <?php echo htmlspecialchars(number_format($item['quantidade_colhida'], 1, ',', '.')); ?> kg</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <table id="tbl-historico" style="display: none;">
                        <thead>
                            <tr>
                                <th>Cultura</th>
                                <th>Categoria</th>
                                <th>Data da Colheita</th>
                                <th>Quantidade (kg)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historico as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['nome_cultura']); ?></td>
                                    <td><?php echo htmlspecialchars($item['nome_categoria']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($item['data_colheita'])); ?></td>
                                    <td><?php echo number_format($item['quantidade_colhida'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                </div>
            </main>
        </div>
    </div>

    <script src="export.js"></script>
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
