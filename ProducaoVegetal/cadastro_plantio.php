<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';

// Garantir login
verificar_login();

$msg_erro = "";
$msg_sucesso = "";

// Buscar culturas para preencher o select
$culturas_query = "SELECT id_cultura, nome_cultura FROM culturas ORDER BY nome_cultura ASC";
$culturas_result = mysqli_query($conn, $culturas_query);
$culturas = [];
if ($culturas_result) {
    while ($row = mysqli_fetch_assoc($culturas_result)) {
        $culturas[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (e_visitante()) {
        $msg_erro = "Acesso negado! Visitantes não podem registrar plantios.";
    } else {
        $id_cultura = intval($_POST['id_cultura']);
        $data_plantio = mysqli_real_escape_string($conn, $_POST['data_plantio']);
        $local_canteiro = mysqli_real_escape_string($conn, $_POST['local_canteiro']);
        $quantidade_plantada = intval($_POST['quantidade_plantada']);
        $notas_plantio = mysqli_real_escape_string($conn, $_POST['notas_plantio']);
        
        // Progresso padrão inicial 0%, colhido 0
        $progresso_colheita = "0";

        $insert_query = "INSERT INTO plantios 
            (id_cultura, data_plantio, local_canteiro, quantidade_plantada, progresso_colheita, notas_plantio, irrigado, colhido) 
            VALUES 
            ($id_cultura, '$data_plantio', '$local_canteiro', $quantidade_plantada, '$progresso_colheita', '$notas_plantio', 0, 0)";
            
        if (mysqli_query($conn, $insert_query)) {
            header("Location: plantios_ativos.php?msg=criado");
            exit;
        } else {
            $msg_erro = "Erro ao registrar plantio: " . mysqli_error($conn);
        }
    }
}

$activePage = 'plantios';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão - Plantio</title>
    
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
                <div class="topbar-title">Cadastro de Plantio</div>
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

                <?php if (count($culturas) === 0): ?>
                    <div style="background-color: #fef3c7; color: #d97706; border: 1px solid #fcd34d; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 700;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>Atenção: Você precisa cadastrar pelo menos uma cultura no catálogo antes de realizar um plantio. <a href="cadastro_culturas.php" style="color: #b45309; text-decoration: underline;">Cadastrar Cultura</a></span>
                    </div>
                <?php endif; ?>

                <form class="form-container" id="form-plantio" action="cadastro_plantio.php" method="POST">
                    <div class="field-card">
                        <label>Cultura Selecionada</label>
                        <select name="id_cultura" class="form-select" required <?php echo e_visitante() || count($culturas) === 0 ? 'disabled' : ''; ?>>
                            <option value="">Selecione do Catálogo...</option>
                            <?php foreach ($culturas as $cultura): ?>
                                <option value="<?php echo $cultura['id_cultura']; ?>"><?php echo htmlspecialchars($cultura['nome_cultura']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                
                    <div class="field-card">
                        <label>Data do Plantio</label>
                        <input name="data_plantio" type="date" class="form-input" required value="<?php echo date('Y-m-d'); ?>" <?php echo e_visitante() ? 'disabled' : ''; ?>>
                    </div>
                
                    <div class="field-card">
                        <label>Local ou Canteiro</label>
                        <input name="local_canteiro" type="text" class="form-input" placeholder="Ex: Canteiro 04, Estufa A" required <?php echo e_visitante() ? 'disabled' : ''; ?>>
                    </div>
                
                    <div class="field-card">
                        <label>Quantidade Plantada</label>
                        <div class="input-row">
                            <input name="quantidade_plantada" type="number" class="form-input" placeholder="Ex: 50" required <?php echo e_visitante() ? 'disabled' : ''; ?>>
                            <select class="form-select" style="max-width: 120px;" <?php echo e_visitante() ? 'disabled' : ''; ?>>
                                <option>Unid</option>
                            </select>
                        </div>
                    </div>
                
                    <div class="field-card">
                        <label>Notas do Plantio</label>
                        <textarea name="notas_plantio" class="form-textarea" placeholder="Ex: Solo adubado com húmus." <?php echo e_visitante() ? 'disabled' : ''; ?>></textarea>
                    </div>
                
                    <?php if (!e_visitante() && count($culturas) > 0): ?>
                        <button type="submit" class="btn-submit">Confirmar Plantio</button>
                    <?php endif; ?>
                </form>
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
