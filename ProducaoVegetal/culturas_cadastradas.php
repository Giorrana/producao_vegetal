<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';

// Garantir login
verificar_login();

$msg_erro = "";
$msg_sucesso = "";

$id_usuario = $_SESSION['user_id'];

// Lógica de Exclusão (Apenas Admin)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (e_visitante()) {
        $msg_erro = "Acesso negado! Visitantes não podem excluir culturas.";
    } else {
        $id_del = intval($_GET['id']);
        
        // Verificar se a cultura está em uso em algum plantio do usuário
        $check_plantio = mysqli_query($conn, "SELECT p.id_plantio FROM plantios p JOIN culturas c ON p.id_cultura = c.id_cultura WHERE p.id_cultura = $id_del AND c.id_usuario = $id_usuario");
        if (mysqli_num_rows($check_plantio) > 0) {
            $msg_erro = "Não é possível excluir esta cultura pois ela já está associada a um plantio ativo.";
        } else {
            $delete_query = "DELETE FROM culturas WHERE id_cultura = $id_del AND id_usuario = $id_usuario";
            if (mysqli_query($conn, $delete_query)) {
                registrar_log("Cultura excluída #$id_del");
                $msg_sucesso = "Cultura excluída com sucesso!";
            } else {
                $msg_erro = "Erro ao excluir cultura: " . mysqli_error($conn);
            }
        }
    }
}

// Filtro de categoria
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'Todos';

// Montar a query filtrando por id_usuario
$query = "SELECT c.*, cat.nome_categoria FROM culturas c 
          JOIN categorias cat ON c.id_categoria = cat.id_categoria
          WHERE c.id_usuario = $id_usuario";

if ($filtro === 'Horta') {
    $query .= " AND cat.nome_categoria = 'Horta'";
} elseif ($filtro === 'Pomar') {
    $query .= " AND cat.nome_categoria = 'Pomar'";
}
$query .= " ORDER BY c.id_cultura DESC";

$result = mysqli_query($conn, $query);
$culturas = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $culturas[] = $row;
    }
}

$activePage = 'culturas';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão - Culturas Cadastradas</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                <div class="topbar-title">Culturas Cadastradas</div>
            </header>

            <main class="main-content">
                <div class="content-wrapper">
                    
                    <?php if (!empty($msg_erro)): ?>
                        <div style="background-color: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 700;">
                            <?php echo htmlspecialchars($msg_erro); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($msg_sucesso) || (isset($_GET['msg']) && $_GET['msg'] === 'criado') || (isset($_GET['msg']) && $_GET['msg'] === 'editado')): ?>
                        <div style="background-color: #d1fae5; color: #10b981; border: 1px solid #6ee7b7; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 700;">
                            <?php 
                                if (!empty($msg_sucesso)) echo htmlspecialchars($msg_sucesso);
                                elseif (isset($_GET['msg']) && $_GET['msg'] === 'criado') echo "Cultura cadastrada com sucesso!";
                                elseif (isset($_GET['msg']) && $_GET['msg'] === 'editado') echo "Cultura atualizada com sucesso!";
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- FILTROS -->
                    <div class="filters-container">
                        <a href="culturas_cadastradas.php?filtro=Todos" class="filter-btn <?php echo $filtro === 'Todos' ? 'active' : 'inactive'; ?>">Todos</a>
                        <a href="culturas_cadastradas.php?filtro=Horta" class="filter-btn <?php echo $filtro === 'Horta' ? 'active' : 'inactive'; ?>">Horta</a>
                        <a href="culturas_cadastradas.php?filtro=Pomar" class="filter-btn <?php echo $filtro === 'Pomar' ? 'active' : 'inactive'; ?>">Pomar</a>
        
                        <!-- NOVO BOTÃO ADICIONADO AQUI (Apenas Admin): -->
                        <?php if (!e_visitante()): ?>
                            <a href="cadastro_culturas.php" class="add-btn-filter"><i class="fa-solid fa-plus"></i></a>
                        <?php endif; ?>
                    </div>

                    <div class="crop-list" id="crop-container">
                        <?php if (count($culturas) === 0): ?>
                            <div class="empty-state">Nenhuma cultura encontrada.</div>
                        <?php else: ?>
                            <?php foreach ($culturas as $cultura): 
                                // Ícones padrões baseados na cultura
                                $icon = '🌱';
                                $nomeMin = strtolower($cultura['nome_cultura']);
                                if (strpos($nomeMin, 'tomate') !== false) $icon = '🍅';
                                elseif (strpos($nomeMin, 'cenoura') !== false) $icon = '🥕';
                                elseif (strpos($nomeMin, 'morango') !== false) $icon = '🍓';
                                elseif (strpos($nomeMin, 'manjericão') !== false || strpos($nomeMin, 'alface') !== false) $icon = '🥬';
                                elseif (strpos($nomeMin, 'laranja') !== false) $icon = '🍊';
                                elseif (strpos($nomeMin, 'pimenta') !== false) $icon = '🌶️';

                                $color = $cultura['nome_categoria'] === 'Pomar' ? '#f97316' : '#22c55e';
                            ?>
                                <div class="crop-card">
                                    <div class="crop-left">
                                        <div class="crop-icon-box" style="color: <?php echo $color; ?>"><?php echo $icon; ?></div>
                                        <div class="crop-info">
                                            <h4><?php echo htmlspecialchars($cultura['nome_cultura']); ?></h4>
                                            <p><?php echo htmlspecialchars($cultura['nome_categoria']); ?> • <?php echo htmlspecialchars($cultura['tempo_medio_crescimento']); ?> dias</p>
                                            
                                            <!-- Estações recomendadas visualizadas de forma elegante -->
                                            <?php 
                                                $estacoes = [];
                                                if (!empty($cultura['estacao_primavera'])) $estacoes[] = "Primavera (" . htmlspecialchars($cultura['estacao_primavera']) . ")";
                                                if (!empty($cultura['estacao_verao'])) $estacoes[] = "Verão (" . htmlspecialchars($cultura['estacao_verao']) . ")";
                                                if (!empty($cultura['estacao_outono'])) $estacoes[] = "Outono (" . htmlspecialchars($cultura['estacao_outono']) . ")";
                                                if (!empty($cultura['estacao_inverno'])) $estacoes[] = "Inverno (" . htmlspecialchars($cultura['estacao_inverno']) . ")";
                                            ?>
                                            <?php if (count($estacoes) > 0): ?>
                                                <p style="font-size: 11px; color: var(--text-gray); margin-top: 3px;">
                                                    <i class="fa-regular fa-calendar" style="margin-right: 4px;"></i>
                                                    <?php echo implode(' | ', $estacoes); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="crop-actions">
                                        <?php if (!empty($cultura['observacoes'])): ?>
                                            <button
                                                class="btn-action btn-info"
                                                onclick="verObservacao(`<?php echo htmlspecialchars(addslashes($cultura['observacoes'])); ?>`)"
                                                title="Ver observações">
                                                <i class="fa-solid fa-comment-dots"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (!e_visitante()): ?>
                                            <a href="cadastro_culturas.php?editId=<?php echo $cultura['id_cultura']; ?>" class="btn-action btn-edit">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <a href="culturas_cadastradas.php?action=delete&id=<?php echo $cultura['id_cultura']; ?>&filtro=<?php echo $filtro; ?>" 
                                               class="btn-action btn-delete" 
                                               onclick="return confirm('Tem certeza que deseja excluir esta cultura do seu catálogo?')">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
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
        function verObservacao(obs){
        Swal.fire({
            title: "Observações",
            html: obs.replace(/\n/g,"<br>"),
            icon: "info",
            confirmButtonText: "Fechar"
        });
        }
    </script>
    
</body>
</html>
