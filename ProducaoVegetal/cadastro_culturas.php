<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';

// Garantir login
verificar_login();

$editId = isset($_GET['editId']) ? intval($_GET['editId']) : null;
$msg_erro = "";
$msg_sucesso = "";

$nome_cultura = "";
$tempo_medio_crescimento = "";
$rendimento_esperado = "";
$observacoes = "";
$id_categoria = 1; // Default Horta

$estacao_primavera = 0;
$estacao_verao = 0;
$estacao_outono = 0;
$estacao_inverno = 0;

// Se for edição, busca no banco
if ($editId) {
    $query = "SELECT * FROM culturas WHERE id_cultura = $editId AND " . escopo_sql('id_usuario');
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $cultura = mysqli_fetch_assoc($result);
        $nome_cultura = $cultura['nome_cultura'];
        $tempo_medio_crescimento = $cultura['tempo_medio_crescimento'];
        $rendimento_esperado = $cultura['rendimento_esperado'];
        $observacoes = $cultura['observacoes'];
        $id_categoria = $cultura['id_categoria'];
        
        $estacao_primavera = !empty($cultura['estacao_primavera']) ? 1 : 0;
        $estacao_verao = !empty($cultura['estacao_verao']) ? 1 : 0;
        $estacao_outono = !empty($cultura['estacao_outono']) ? 1 : 0;
        $estacao_inverno = !empty($cultura['estacao_inverno']) ? 1 : 0;
    } else {
        $editId = null; // ID inválido
    }
}

// Submissão do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (e_visitante()) {
        $msg_erro = "Acesso negado! Visitantes não podem adicionar ou editar culturas.";
    } else {
        $nome_cultura = mysqli_real_escape_string($conn, $_POST['nome_cultura']);
        $id_categoria = intval($_POST['id_categoria']);
        $tempo_medio_crescimento = mysqli_real_escape_string($conn, $_POST['tempo_medio_crescimento']);
        $rendimento_esperado = floatval($_POST['rendimento_esperado']);
        
        $estacao_primavera = isset($_POST['estacao_primavera']) ? 'Primavera' : '';
        $estacao_verao    = isset($_POST['estacao_verao'])    ? 'Verão'    : '';
        $estacao_outono   = isset($_POST['estacao_outono'])   ? 'Outono'   : '';
        $estacao_inverno  = isset($_POST['estacao_inverno'])  ? 'Inverno'  : '';
        
        // Combina em uma única string para compatibilidade retroativa
        $estacoes_ativas = [];
        if (!empty($estacao_primavera)) $estacoes_ativas[] = "Primavera";
        if (!empty($estacao_verao))    $estacoes_ativas[] = "Verão";
        if (!empty($estacao_outono))   $estacoes_ativas[] = "Outono";
        if (!empty($estacao_inverno))  $estacoes_ativas[] = "Inverno";
        $estacao_ano_ideal = implode('/', $estacoes_ativas);

        $observacoes = mysqli_real_escape_string($conn, $_POST['observacoes']);
        $id_usuario_atual = $_SESSION['user_id'];

        if ($editId) {
            $update_query = "UPDATE culturas SET 
                nome_cultura = '$nome_cultura', 
                id_categoria = $id_categoria, 
                tempo_medio_crescimento = '$tempo_medio_crescimento', 
                rendimento_esperado = $rendimento_esperado, 
                estacao_ano_ideal = '$estacao_ano_ideal', 
                estacao_primavera = '$estacao_primavera', 
                estacao_verao = '$estacao_verao', 
                estacao_outono = '$estacao_outono', 
                estacao_inverno = '$estacao_inverno', 
                observacoes = '$observacoes' 
                WHERE id_cultura = $editId AND " . escopo_sql('id_usuario');
            if (mysqli_query($conn, $update_query)) {
                header("Location: culturas_cadastradas.php?msg=editado");
                exit;
            } else {
                $msg_erro = "Erro ao atualizar cultura: " . mysqli_error($conn);
            }
        } else {
            $insert_query = "INSERT INTO culturas 
                (nome_cultura, id_categoria, tempo_medio_crescimento, rendimento_esperado, estacao_ano_ideal, estacao_primavera, estacao_verao, estacao_outono, estacao_inverno, observacoes, id_usuario) 
                VALUES 
                ('$nome_cultura', $id_categoria, '$tempo_medio_crescimento', $rendimento_esperado, '$estacao_ano_ideal', '$estacao_primavera', '$estacao_verao', '$estacao_outono', '$estacao_inverno', '$observacoes', $id_usuario_atual)";
            if (mysqli_query($conn, $insert_query)) {
                header("Location: culturas_cadastradas.php?msg=criado");
                exit;
            } else {
                $msg_erro = "Erro ao cadastrar cultura: " . mysqli_error($conn);
            }
        }
    }
}

$activePage = 'culturas';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão - Culturas</title>
    
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
        
        <!-- MENU LATERAL -->
        <?php include 'sidebar.php'; ?>
        
        <!-- ÁREA DIREITA -->
        <div class="main-wrapper">
            <header class="topbar">
                <button class="menu-btn" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
                <div class="topbar-title" id="page-action-title"><?php echo $editId ? "Editar Cultura" : "Cadastro de Culturas"; ?></div>
            </header>

            <!-- CONTEÚDO DO FORMULÁRIO -->
            <main class="main-content">
                
                <?php if (e_visitante()): ?>
                    <div style="background-color: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>Modo de Leitura: Como visitante, você não pode realizar alterações nesta página.</span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($msg_erro)): ?>
                    <div style="background-color: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 700;">
                        <?php echo htmlspecialchars($msg_erro); ?>
                    </div>
                <?php endif; ?>

                <form class="form-container" id="form-cultura" action="cadastro_culturas.php<?php echo $editId ? '?editId=' . $editId : ''; ?>" method="POST">
                    <div class="field-card">
                        <label>Nome da Cultura</label>
                        <input name="nome_cultura" type="text" class="form-input" placeholder="Ex: Tomate Cereja" required value="<?php echo htmlspecialchars($nome_cultura); ?>" <?php echo e_visitante() ? 'disabled' : ''; ?>>
                    </div>
                
                    <div class="field-card">
                        <label>Setor / Categoria</label>
                        <select name="id_categoria" class="form-select" required <?php echo e_visitante() ? 'disabled' : ''; ?>>
                            <option value="1" <?php echo $id_categoria == 1 ? 'selected' : ''; ?>>Horta</option>
                            <option value="2" <?php echo $id_categoria == 2 ? 'selected' : ''; ?>>Pomar</option>
                        </select>
                    </div>
                
                    <div class="field-card">
                        <label>Ciclo Estimado (Dias até Colheita)</label>
                        <input name="tempo_medio_crescimento" type="number" class="form-input" placeholder="Ex: 90" required value="<?php echo htmlspecialchars($tempo_medio_crescimento); ?>" <?php echo e_visitante() ? 'disabled' : ''; ?>>
                    </div>
                
                    <div class="field-card">
                        <label>Rendimento Esperado (Kg por unidade plantada) *</label>
                        <input name="rendimento_esperado" type="number" step="0.01" min="0" class="form-input" placeholder="Ex: 2.50" required value="<?php echo htmlspecialchars($rendimento_esperado); ?>" <?php echo e_visitante() ? 'disabled' : ''; ?>>
                    </div>
                
                    <!-- 4 OPÇÕES DE CHECKBOX PARA ESTAÇÃO RECOMENDADA -->
                    <div class="field-card">
                        <label>Estações Recomendadas</label>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 12px;">

                            <!-- Primavera -->
                            <label class="season-toggle <?php echo $estacao_primavera ? 'selected' : ''; ?>" style="--season-color: #22c55e;">
                                <input type="checkbox" name="estacao_primavera" value="1" <?php echo $estacao_primavera ? 'checked' : ''; ?> <?php echo e_visitante() ? 'disabled' : ''; ?> style="display:none;" onchange="toggleSeason(this)">
                                <span class="season-icon">🌸</span>
                                <span class="season-name">Primavera</span>
                                <span class="season-check"><i class="fa-solid fa-check"></i></span>
                            </label>

                            <!-- Verão -->
                            <label class="season-toggle <?php echo $estacao_verao ? 'selected' : ''; ?>" style="--season-color: #f59e0b;">
                                <input type="checkbox" name="estacao_verao" value="1" <?php echo $estacao_verao ? 'checked' : ''; ?> <?php echo e_visitante() ? 'disabled' : ''; ?> style="display:none;" onchange="toggleSeason(this)">
                                <span class="season-icon">☀️</span>
                                <span class="season-name">Verão</span>
                                <span class="season-check"><i class="fa-solid fa-check"></i></span>
                            </label>

                            <!-- Outono -->
                            <label class="season-toggle <?php echo $estacao_outono ? 'selected' : ''; ?>" style="--season-color: #f97316;">
                                <input type="checkbox" name="estacao_outono" value="1" <?php echo $estacao_outono ? 'checked' : ''; ?> <?php echo e_visitante() ? 'disabled' : ''; ?> style="display:none;" onchange="toggleSeason(this)">
                                <span class="season-icon">🍂</span>
                                <span class="season-name">Outono</span>
                                <span class="season-check"><i class="fa-solid fa-check"></i></span>
                            </label>

                            <!-- Inverno -->
                            <label class="season-toggle <?php echo $estacao_inverno ? 'selected' : ''; ?>" style="--season-color: #3b82f6;">
                                <input type="checkbox" name="estacao_inverno" value="1" <?php echo $estacao_inverno ? 'checked' : ''; ?> <?php echo e_visitante() ? 'disabled' : ''; ?> style="display:none;" onchange="toggleSeason(this)">
                                <span class="season-icon">❄️</span>
                                <span class="season-name">Inverno</span>
                                <span class="season-check"><i class="fa-solid fa-check"></i></span>
                            </label>

                        </div>
                    </div>
                
                    <div class="field-card">
                        <label>Observações de Manejo</label>
                        <textarea name="observacoes" class="form-textarea" placeholder="Ex: Solo adubado." <?php echo e_visitante() ? 'disabled' : ''; ?>><?php echo htmlspecialchars($observacoes); ?></textarea>
                    </div>
                
                    <?php if (!e_visitante()): ?>
                        <button type="submit" id="btn-save" class="btn-submit"><?php echo $editId ? "Atualizar Cultura" : "Salvar Cultura"; ?></button>
                    <?php endif; ?>
                </form>
            </main>
        </div>
    </div>

    <style>
        .season-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 12px;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--card-bg);
            user-select: none;
            position: relative;
        }
        .season-toggle:hover {
            border-color: var(--season-color);
            transform: translateY(-1px);
        }
        .season-toggle.selected {
            border-color: var(--season-color);
            background: color-mix(in srgb, var(--season-color) 10%, var(--card-bg));
        }
        .season-icon { font-size: 22px; line-height: 1; }
        .season-name { font-weight: 700; font-size: 14px; flex: 1; color: var(--text-main); }
        .season-check {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: white;
            transition: all 0.2s ease;
        }
        .season-toggle.selected .season-check {
            background: var(--season-color);
            border-color: var(--season-color);
        }
        .season-toggle input[disabled] + .season-icon { opacity: 0.5; }
    </style>
    <script>
        function toggleMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
        function toggleSeason(checkbox) {
            const label = checkbox.closest('.season-toggle');
            if (checkbox.checked) {
                label.classList.add('selected');
            } else {
                label.classList.remove('selected');
            }
        }
    </script>
</body>
</html>
