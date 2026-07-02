<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';

// Garantir login
verificar_login();

$msg_erro = "";
$msg_sucesso = "";
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'Todos';

// Lógica de Ações (Irrigar e Colher)
if (isset($_GET['action'])) {
    if (e_visitante()) {
        $msg_erro = "Acesso negado! Visitantes não podem realizar manejos de plantio.";
    } else {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($_GET['action'] === 'irrigar' && $id > 0) {
            // Incrementa dias_irrigados e alterna estado de irrigado
            $q = mysqli_query($conn, "SELECT irrigado, dias_irrigados FROM plantios WHERE id_plantio = $id");
            if ($q && $row = mysqli_fetch_assoc($q)) {
                $novo_estado = $row['irrigado'] == 1 ? 0 : 1;
                // Se estava NÃO irrigado e está sendo marcado como irrigado, incrementa dias_irrigados
                $inc_dias = ($novo_estado == 1) ? ', dias_irrigados = COALESCE(dias_irrigados, 0) + 1' : '';
                $update = mysqli_query($conn, "UPDATE plantios SET irrigado = $novo_estado$inc_dias WHERE id_plantio = $id");
                if ($update) {
                    header("Location: plantios_ativos.php?filtro=$filtro");
                    exit;
                } else {
                    $msg_erro = "Erro ao atualizar irrigação: " . mysqli_error($conn);
                }
            }
        }
        
        if ($_GET['action'] === 'colher' && $id > 0 && isset($_GET['qtd'])) {
            $qtd_texto = $_GET['qtd'];
            // Extrai apenas o número do texto informado (ex: "20 Kg" ou "20" -> 20.00)
            preg_match('/[0-9]+(?:\.[0-9]+)?/', $qtd_texto, $matches);
            $qtd_colhida = isset($matches[0]) ? floatval($matches[0]) : 0;

            if ($qtd_colhida <= 0) {
                $msg_erro = "Por favor, insira uma quantidade de colheita válida maior que zero.";
            } else {
                // Iniciar transação para garantir integridade
                mysqli_begin_transaction($conn);
                
                // 1. Inserir na tabela colheitas
                $insert_colheita = "INSERT INTO colheitas (data_colheita, quantidade_colhida, id_plantio) VALUES (CURRENT_DATE(), $qtd_colhida, $id)";
                
                // 2. Marcar plantio como colhido = 1
                $update_plantio = "UPDATE plantios SET colhido = 1, progresso_colheita = '100' WHERE id_plantio = $id";
                
                if (mysqli_query($conn, $insert_colheita) && mysqli_query($conn, $update_plantio)) {
                    mysqli_commit($conn);
                    header("Location: historico.php?msg=colheita_sucesso");
                    exit;
                } else {
                    mysqli_rollback($conn);
                    $msg_erro = "Erro ao registrar colheita: " . mysqli_error($conn);
                }
            }
        }
    }
}

// Buscar plantios ativos (colhido = 0)
$query = "SELECT p.*, c.nome_cultura, c.tempo_medio_crescimento, cat.nome_categoria 
          FROM plantios p 
          JOIN culturas c ON p.id_cultura = c.id_cultura 
          JOIN categorias cat ON c.id_categoria = cat.id_categoria
          WHERE p.colhido = 0";

if ($filtro === 'Horta') {
    $query .= " AND cat.nome_categoria = 'Horta'";
} elseif ($filtro === 'Pomar') {
    $query .= " AND cat.nome_categoria = 'Pomar'";
}

$query .= " ORDER BY p.id_plantio DESC";
$result = mysqli_query($conn, $query);
$plantios = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Cálculo dinâmico do progresso baseado em dias irrigados
        $dias_ciclo = intval($row['tempo_medio_crescimento']);
        $dias_irrigados = intval($row['dias_irrigados']);
        if ($dias_ciclo > 0) {
            $progresso = round(($dias_irrigados / $dias_ciclo) * 100);
            if ($progresso > 100) $progresso = 100;
        } else {
            $progresso = 0;
        }
        
        $row['progresso_calculado'] = $progresso;
        $plantios[] = $row;
    }
}

$activePage = 'plantios';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão - Plantios Ativos</title>
    
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
                    <button class="menu-btn" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
                </div>
                <div class="topbar-title">Plantios Ativos</div>
            </header>

            <main class="main-content">
                <div class="content-wrapper">
                    
                    <?php if (!empty($msg_erro)): ?>
                        <div style="background-color: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 700;">
                            <?php echo htmlspecialchars($msg_erro); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'criado'): ?>
                        <div style="background-color: #d1fae5; color: #10b981; border: 1px solid #6ee7b7; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 700;">
                            Novo plantio registrado com sucesso!
                        </div>
                    <?php endif; ?>

                    <!-- FILTROS -->
                    <div class="filters-container">
                        <a href="plantios_ativos.php?filtro=Todos" class="filter-btn <?php echo $filtro === 'Todos' ? 'active' : 'inactive'; ?>">Todos</a>
                        <a href="plantios_ativos.php?filtro=Horta" class="filter-btn <?php echo $filtro === 'Horta' ? 'active' : 'inactive'; ?>">Horta</a>
                        <a href="plantios_ativos.php?filtro=Pomar" class="filter-btn <?php echo $filtro === 'Pomar' ? 'active' : 'inactive'; ?>">Pomar</a>
                        
                        <!-- BOTÃO ADICIONAR (Apenas Admin): -->
                        <?php if (!e_visitante()): ?>
                            <a href="cadastro_plantio.php" class="add-btn-filter"><i class="fa-solid fa-plus"></i></a>
                        <?php endif; ?>
                    </div>

                    <!-- LISTA DE PLANTIOS -->
                    <div class="plantio-list" id="plantios-container">
                        <?php if (count($plantios) === 0): ?>
                            <div class="empty-state">
                                Nenhum plantio ativo encontrado.<br>
                                <?php if (!e_visitante()): ?>
                                    Clique no botão <b>+</b> acima para registrar um novo plantio.
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($plantios as $p): 
                                // Ícone do card baseado na cultura
                                $icon = '🌱';
                                $nomeMin = strtolower($p['nome_cultura']);
                                if (strpos($nomeMin, 'tomate') !== false) $icon = '🍅';
                                elseif (strpos($nomeMin, 'alface') !== false) $icon = '🥬';
                                elseif (strpos($nomeMin, 'milho') !== false) $icon = '🌽';
                                elseif (strpos($nomeMin, 'cenoura') !== false) $icon = '🥕';
                                elseif (strpos($nomeMin, 'laranja') !== false) $icon = '🍊';
                                elseif (strpos($nomeMin, 'pimenta') !== false) $icon = '🌶️';

                                $progresso = $p['progresso_calculado'];
                                
                                // Quantidade de colunas dos botões de ação
                                $gridLayout = ($progresso >= 100 && !e_visitante()) ? 'repeat(3, 1fr)' : 'repeat(2, 1fr)';
                                if (e_visitante()) {
                                    $gridLayout = '1fr'; // Se for visitante, as ações de escrita não aparecem ou ficam escondidas
                                }
                            ?>
                                <div class="plantio-card">
                                    <div class="plantio-header">
                                        <div class="plantio-icon"><?php echo $icon; ?></div>
                                        <div class="plantio-info">
                                            <h4><?php echo htmlspecialchars($p['nome_cultura']); ?></h4>
                                            <p>
                                                <i class="fa-solid fa-location-dot" style="color: var(--primary-green);"></i> 
                                                <?php echo htmlspecialchars($p['local_canteiro']); ?> 
                                                <span style="font-size: 11px; color: var(--text-gray); margin-left: 8px;">
                                                    (Qtd: <?php echo htmlspecialchars($p['quantidade_plantada']); ?>)
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar-header">
                                            <span>Progresso da Colheita</span>
                                            <strong><?php echo $progresso; ?>%</strong>
                                        </div>
                                        <div class="progress-track">
                                            <div class="progress-fill" style="width: <?php echo $progresso; ?>%;"></div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!e_visitante()): ?>
                                        <div class="plantio-actions" style="grid-template-columns: <?php echo $gridLayout; ?>;">
                                            <!-- Botão Irrigar -->
                                            <?php if ($p['irrigado'] == 1): ?>
                                                <a href="plantios_ativos.php?action=irrigar&id=<?php echo $p['id_plantio']; ?>&filtro=<?php echo $filtro; ?>" class="action-btn btn-irrigar active" style="text-decoration: none; text-align: center; display: inline-block;">
                                                    <i class="fa-solid fa-check"></i> Irrigado
                                                </a>
                                            <?php else: ?>
                                                <a href="plantios_ativos.php?action=irrigar&id=<?php echo $p['id_plantio']; ?>&filtro=<?php echo $filtro; ?>" class="action-btn btn-irrigar" style="text-decoration: none; text-align: center; display: inline-block;">
                                                    <i class="fa-solid fa-droplet"></i> Irrigar
                                                </a>
                                            <?php endif; ?>

                                            <!-- Botão Adubar -->
                                            <a href="adubar_plantio.php?id=<?php echo $p['id_plantio']; ?>&nome=<?php echo urlencode($p['nome_cultura']); ?>" class="action-btn btn-adubar" style="text-decoration: none; text-align: center; display: inline-block;">
                                                <i class="fa-solid fa-leaf"></i> Adubar
                                            </a>

                                            <!-- Botão Colher (Apenas se progresso >= 100%) -->
                                            <?php if ($progresso >= 100): ?>
                                                <button class="action-btn btn-colher" onclick="colherPlantio(<?php echo $p['id_plantio']; ?>, '<?php echo addslashes($p['nome_cultura']); ?>')">
                                                    <i class="fa-solid fa-check"></i> Realizar Colheita
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-size: 11px; text-align: center; color: var(--text-gray); margin-top: 15px; border-top: 1px dashed var(--border-color); padding-top: 10px;">
                                            <i class="fa-solid fa-eye" style="margin-right: 4px;"></i> Modo de Leitura: Apenas administradores realizam ações de manejo.
                                        </div>
                                    <?php endif; ?>
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

        function colherPlantio(id, nome) {
            let qtd = prompt(`Parabéns! Qual a quantidade colhida de ${nome}? (ex: 20 Kg)`);
            if (qtd) {
                // Redireciona com os dados da colheita
                window.location.href = `plantios_ativos.php?action=colher&id=${id}&qtd=${encodeURIComponent(qtd)}&filtro=<?php echo $filtro; ?>`;
            }
        }
    </script>
</body>
</html>
