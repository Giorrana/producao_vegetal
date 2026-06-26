<?php
require_once '../Banco/conecao.php';
require_once 'auth.php';

// Garantir login
verificar_login();

$msg_erro = "";
$msg_sucesso = "";
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'Todos';

// Lógica de ações (Exclusão e Ajuste Rápido de Quantidade)
if (isset($_GET['action'])) {
    if (e_visitante()) {
        $msg_erro = "Acesso negado! Visitantes não podem alterar o estoque.";
    } else {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($_GET['action'] === 'delete' && $id > 0) {
            $delete_query = "DELETE FROM estoque WHERE id_item = $id";
            if (mysqli_query($conexao, $delete_query)) {
                $msg_sucesso = "Insumo removido com sucesso!";
            } else {
                $msg_erro = "Erro ao remover insumo: " . mysqli_error($conexao);
            }
        }
        
        if ($_GET['action'] === 'adjust_qty' && $id > 0 && isset($_GET['val'])) {
            $val = floatval($_GET['val']);
            
            // Buscar quantidade atual
            $q = mysqli_query($conexao, "SELECT quantidade, nivel_alerta FROM estoque WHERE id_item = $id");
            if ($q && mysqli_fetch_assoc($q)) {
                mysqli_data_seek($q, 0);
                $item = mysqli_fetch_assoc($q);
                $new_qty = $item['quantidade'] + $val;
                
                if ($new_qty >= 0) {
                    $status_estoque = ($new_qty <= $item['nivel_alerta']) ? 'Alerta' : 'Normal';
                    $update_query = "UPDATE estoque SET quantidade = $new_qty, status_estoque = '$status_estoque' WHERE id_item = $id";
                    if (mysqli_query($conexao, $update_query)) {
                        header("Location: estoque.php?filtro=$filtro");
                        exit;
                    } else {
                        $msg_erro = "Erro ao atualizar quantidade: " . mysqli_error($conexao);
                    }
                } else {
                    $msg_erro = "A quantidade não pode ser menor que zero!";
                }
            }
        }
    }
}

// Buscar itens do estoque
$query = "SELECT * FROM estoque";
if ($filtro === 'Semente') {
    $query .= " WHERE categoria = 'Semente'";
} elseif ($filtro === 'Adubo') {
    $query .= " WHERE categoria = 'Adubo'";
} elseif ($filtro === 'Defensivo') {
    $query .= " WHERE categoria = 'Defensivo'";
}
$query .= " ORDER BY id_item DESC";

$result = mysqli_query($conexao, $query);
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

                                // Alerta de nível mínimo
                                $style_card = "";
                                if ($item['status_estoque'] === 'Alerta') {
                                    $style_card = "border-left: 4px solid var(--red); background-color: #fffafb;";
                                }
                            ?>
                                <div class="estoque-card" style="<?php echo $style_card; ?>">
                                    <div class="item-left">
                                        <div class="item-icon-box"><?php echo $icon; ?></div>
                                        <div class="item-info">
                                            <h4><?php echo htmlspecialchars($item['nome_item']); ?></h4>
                                            <p><?php echo htmlspecialchars($item['categoria']); ?> • <?php echo htmlspecialchars(number_format($item['quantidade'], 2, ',', '.')); ?> <?php echo htmlspecialchars($item['unidade_medida']); ?></p>
                                            <?php if ($item['status_estoque'] === 'Alerta'): ?>
                                                <span style="color: var(--red); font-size: 11px; font-weight: 700; display: inline-block; margin-top: 3px;">
                                                    <i class="fa-solid fa-circle-exclamation"></i> Nível de alerta atingido! (Mín: <?php echo htmlspecialchars($item['nivel_alerta']); ?>)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="item-right">
                                        <!-- Controles Rápidos de Quantidade (Apenas Admin) -->
                                        <?php if (!e_visitante()): ?>
                                            <div class="qty-controls">
                                                <a href="estoque.php?action=adjust_qty&id=<?php echo $item['id_item']; ?>&val=-1&filtro=<?php echo $filtro; ?><?php echo isset($_GET['action_type']) ? '&action_type=adubar' : ''; ?>" class="btn-qty btn-qty-minus">
                                                    <i class="fa-solid fa-minus"></i>
                                                </a>
                                                <a href="estoque.php?action=adjust_qty&id=<?php echo $item['id_item']; ?>&val=1&filtro=<?php echo $filtro; ?><?php echo isset($_GET['action_type']) ? '&action_type=adubar' : ''; ?>" class="btn-qty btn-qty-plus">
                                                    <i class="fa-solid fa-plus"></i>
                                                </a>
                                            </div>
                                            
                                            <!-- Ações de Editar e Excluir -->
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
