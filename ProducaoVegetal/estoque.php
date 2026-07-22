<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';

// Garantir login
verificar_login();

$msg_erro = "";
$msg_sucesso = "";
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'Todos';

$id_usuario = $_SESSION['user_id'];

// Lógica de ações (Exclusão e Ajuste Rápido de Quantidade)
if (isset($_GET['action'])) {
    if (e_visitante()) {
        $msg_erro = "Acesso negado! Visitantes não podem alterar o estoque.";
    } else {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($_GET['action'] === 'delete' && $id > 0) {
            $check_cond = e_admin() ? "1=1" : "id_usuario = " . intval($_SESSION['user_id']);
            $stmt_del = $conn->prepare("DELETE FROM estoque WHERE id_item = ? AND ($check_cond)");
            $stmt_del->bind_param("i", $id);
            if ($stmt_del->execute()) {
                if ($stmt_del->affected_rows > 0) {
                    $msg_sucesso = "Insumo removido com sucesso!";
                } else {
                    $msg_erro = "Insumo não encontrado ou sem permissão para excluí-lo.";
                }
            } else {
                $msg_erro = tratar_erro_sql("remover insumo", $stmt_del->error);
            }
        }
        
        if ($_GET['action'] === 'adjust_qty' && $id > 0 && isset($_GET['val'])) {
            $val = floatval($_GET['val']);
            $check_cond = e_admin() ? "1=1" : "id_usuario = " . intval($_SESSION['user_id']);
            $stmt_sel = $conn->prepare("SELECT quantidade, nivel_alerta FROM estoque WHERE id_item = ? AND ($check_cond)");
            $stmt_sel->bind_param("i", $id);
            $stmt_sel->execute();
            $res_sel = $stmt_sel->get_result();
            if ($res_sel && $item = $res_sel->fetch_assoc()) {
                $new_qty = $item['quantidade'] + $val;
                
                if ($new_qty >= 0) {
                    $status_estoque = ($new_qty <= $item['nivel_alerta']) ? 'Alerta' : 'Normal';
                    $stmt_upd = $conn->prepare("UPDATE estoque SET quantidade = ?, status_estoque = ? WHERE id_item = ?");
                    $stmt_upd->bind_param("dsi", $new_qty, $status_estoque, $id);
                    if ($stmt_upd->execute()) {
                        header("Location: estoque.php?filtro=$filtro");
                        exit;
                    } else {
                        $msg_erro = tratar_erro_sql("atualizar quantidade", $stmt_upd->error);
                    }
                } else {
                    $msg_erro = "A quantidade não pode ser menor que zero!";
                }
            } else {
                $msg_erro = "Insumo não encontrado ou sem permissão para alterá-lo.";
            }
        }
    }
}

// Buscar itens do estoque
$query = "SELECT e.*, u.nome AS nome_registrador FROM estoque e JOIN usuarios u ON e.id_usuario = u.id_usuario WHERE 1=1";
if ($filtro === 'Semente') {
    $query .= " AND categoria = 'Semente'";
} elseif ($filtro === 'Adubo') {
    $query .= " AND categoria = 'Adubo'";
} elseif ($filtro === 'Defensivo') {
    $query .= " AND categoria = 'Defensivo'";
}
$query .= " ORDER BY id_item DESC";

$result = mysqli_query($conn, $query);
$estoque = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $estoque[] = $row;
    }
}

$activePage = 'estoque';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão - Estoque</title>
    
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
                    <button class="menu-btn" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
                </div>
                <div class="topbar-title">Controle de Estoque</div>
            </header>

            <main class="main-content">
                <div class="content-wrapper">
                    
                    <?php if (isset($_GET['action_type']) && $_GET['action_type'] === 'adubar'): ?>
                        <div id="alert-adubo" class="alert-adubo" style="display: block;">
                            <i class="fa-solid fa-circle-info" style="margin-right: 8px;"></i>
                            Veio de um Plantio Ativo. Reduza a quantidade do adubo utilizado clicando em "-".
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($msg_erro)): ?>
                        <div style="background-color: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 700;">
                            <?php echo htmlspecialchars($msg_erro); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($msg_sucesso) || (isset($_GET['msg']) && $_GET['msg'] === 'criado') || (isset($_GET['msg']) && $_GET['msg'] === 'editado')): ?>
                        <div style="background-color: #d1fae5; color: #10b981; border: 1px solid #6ee7b7; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 700;">
                            <?php 
                                if (!empty($msg_sucesso)) echo htmlspecialchars($msg_sucesso);
                                elseif (isset($_GET['msg']) && $_GET['msg'] === 'criado') echo "Insumo adicionado com sucesso!";
                                elseif (isset($_GET['msg']) && $_GET['msg'] === 'editado') echo "Insumo atualizado com sucesso!";
                            ?>
                        </div>
                    <?php endif; ?>

                    <div class="export-bar" style="display: flex; gap: 8px; margin-bottom: 14px; justify-content: flex-end;">
                        <button class="btn-export" onclick="exportTableToExcel('tbl-estoque', 'estoque_insumos')" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1.5px solid var(--border-color); background: var(--form-bg,#f9fafb); border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; color: var(--dark-green);">
                            <i class="fa-solid fa-file-excel"></i> Exportar Excel
                        </button>
                        <button class="btn-export" onclick="exportToPDF()" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1.5px solid var(--border-color); background: var(--form-bg,#f9fafb); border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; color: var(--dark-green);">
                            <i class="fa-solid fa-file-pdf"></i> Exportar PDF
                        </button>
                    </div>

                    <!-- FILTROS -->
                    <div class="filters-container">
                        <a href="estoque.php?filtro=Todos<?php echo isset($_GET['action_type']) ? '&action_type=adubar' : ''; ?>" class="filter-btn <?php echo $filtro === 'Todos' ? 'active' : 'inactive'; ?>">Todos</a>
                        <a href="estoque.php?filtro=Semente<?php echo isset($_GET['action_type']) ? '&action_type=adubar' : ''; ?>" class="filter-btn <?php echo $filtro === 'Semente' ? 'active' : 'inactive'; ?>">Sementes</a>
                        <a href="estoque.php?filtro=Adubo<?php echo isset($_GET['action_type']) ? '&action_type=adubar' : ''; ?>" class="filter-btn <?php echo $filtro === 'Adubo' ? 'active' : 'inactive'; ?>">Adubos</a>
                        <a href="estoque.php?filtro=Defensivo<?php echo isset($_GET['action_type']) ? '&action_type=adubar' : ''; ?>" class="filter-btn <?php echo $filtro === 'Defensivo' ? 'active' : 'inactive'; ?>">Defensivos</a>
                        
                        <!-- BOTÃO DE ADICIONAR NOVO ITEM DO ESTOQUE (Apenas Admin) -->
                        <?php if (!e_visitante()): ?>
                            <a href="cadastro_insumos.php" class="add-btn-filter"><i class="fa-solid fa-plus"></i></a>
                        <?php endif; ?>
                    </div>

                    <div class="estoque-list" id="estoque-container">
                        <?php if (count($estoque) === 0): ?>
                            <div class="empty-state">Nenhum insumo encontrado nesta categoria.</div>
                        <?php else: ?>
                            <?php foreach ($estoque as $item): 
                                $icon = '📦';
                                if ($item['categoria'] === 'Semente') $icon = '🌱';
                                elseif ($item['categoria'] === 'Adubo') $icon = '🪨';
                                elseif ($item['categoria'] === 'Defensivo') $icon = '🧪';

                                $vencido = !empty($item['data_validade']) && strtotime($item['data_validade']) < strtotime('today');

                                // Alerta de nível mínimo ou vencimento
                                $style_card = "";
                                if ($item['status_estoque'] === 'Alerta' || $vencido) {
                                    $style_card = "border-left: 4px solid var(--red); background-color: #fffafb;";
                                }
                            ?>
                                <div class="estoque-card" style="<?php echo $style_card; ?>">
                                    <div class="item-left">
                                        <div class="item-icon-box"><?php echo $icon; ?></div>
                                        <div class="item-info">
                                            <h4>
                                                <?php echo htmlspecialchars($item['nome_item']); ?>
                                                <?php if ($vencido): ?>
                                                    <span style="background-color: var(--red); color: white; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; margin-left: 6px; display: inline-block; vertical-align: middle;">
                                                        <i class="fa-solid fa-triangle-exclamation"></i> Vencido
                                                    </span>
                                                <?php endif; ?>
                                            </h4>
                                            <p><?php echo htmlspecialchars($item['categoria']); ?> • <?php echo htmlspecialchars(number_format($item['quantidade'], 2, ',', '.')); ?> <?php echo htmlspecialchars($item['unidade_medida']); ?></p>
                                            <p style="font-size: 11px; color: var(--text-gray); margin-top: 2px;">
                                                <i class="fa-solid fa-user" style="font-size: 10px;"></i> Registrado por: <?php echo htmlspecialchars($item['nome_registrador']); ?>
                                            </p>
                                            <?php if ($item['status_estoque'] === 'Alerta'): ?>
                                                <span style="color: var(--red); font-size: 11px; font-weight: 700; display: inline-block; margin-top: 3px;">
                                                    <i class="fa-solid fa-circle-exclamation"></i> Nível de alerta atingido! (Mín: <?php echo htmlspecialchars($item['nivel_alerta']); ?>)
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($vencido): ?>
                                                <span style="color: var(--red); font-size: 11px; font-weight: 700; display: inline-block; margin-top: 3px; <?php echo $item['status_estoque'] === 'Alerta' ? 'margin-left: 8px;' : ''; ?>">
                                                    <i class="fa-solid fa-calendar-xmark"></i> Vencido em <?php echo date('d/m/Y', strtotime($item['data_validade'])); ?>!
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="item-right">
                                        <!-- Ações de Editar e Excluir: admin vê tudo, operador só vê os seus -->
                                        <?php if (!e_visitante() && (e_admin() || $item['id_usuario'] == $id_usuario)): ?>
                                            <div class="action-btns">
                                                <a href="cadastro_insumos.php?editId=<?php echo $item['id_item']; ?>" class="btn-action btn-edit">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <a href="estoque.php?action=delete&id=<?php echo $item['id_item']; ?>&filtro=<?php echo $filtro; ?>" 
                                                   class="btn-action btn-delete" 
                                                   onclick="return confirm('Deseja realmente remover este insumo do seu estoque?')">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <table id="tbl-estoque" style="display: none;">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Categoria</th>
                                <th>Quantidade</th>
                                <th>Unidade</th>
                                <th>Registrado por</th>
                                <th>Lote Fabricante</th>
                                <th>Validade</th>
                                <th>Status</th>
                                <th>Custo Unitário</th>
                                <th>Custo Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estoque as $item): 
                                $custo_u = floatval($item['custo_aquisicao'] ?? 0);
                                $custo_t = $item['quantidade'] * $custo_u;
                                $vencido = !empty($item['data_validade']) && strtotime($item['data_validade']) < strtotime('today');
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['nome_item']); ?></td>
                                    <td><?php echo htmlspecialchars($item['categoria']); ?></td>
                                    <td><?php echo number_format($item['quantidade'], 2, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($item['unidade_medida']); ?></td>
                                    <td><?php echo htmlspecialchars($item['nome_registrador']); ?></td>
                                    <td><?php echo htmlspecialchars($item['lote_fabricante'] ?? '—'); ?></td>
                                    <td><?php echo $item['data_validade'] ? date('d/m/Y', strtotime($item['data_validade'])) : '—'; ?></td>
                                    <td><?php echo $vencido ? 'Vencido' : ($item['status_estoque'] === 'Alerta' ? 'Alerta' : 'Normal'); ?></td>
                                    <td>R$ <?php echo number_format($custo_u, 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($custo_t, 2, ',', '.'); ?></td>
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
