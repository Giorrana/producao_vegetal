<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';

// Garantir login
verificar_login();

$editId = isset($_GET['editId']) ? intval($_GET['editId']) : null;
$msg_erro = "";
$msg_sucesso = "";

$nome_item = "";
$categoria = "Semente";
$quantidade = "";
$unidade_medida = "Kg";
$nivel_alerta = "";

if ($editId) {
    $query = "SELECT * FROM estoque WHERE id_item = $editId";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $item = mysqli_fetch_assoc($result);
        $nome_item = $item['nome_item'];
        $categoria = $item['categoria'];
        $quantidade = $item['quantidade'];
        $unidade_medida = $item['unidade_medida'];
        $nivel_alerta = $item['nivel_alerta'];
    } else {
        $editId = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (e_visitante()) {
        $msg_erro = "Acesso negado! Visitantes não podem alterar o estoque.";
    } else {
        $nome_item = mysqli_real_escape_string($conn, $_POST['nome_item']);
        $categoria = mysqli_real_escape_string($conn, $_POST['categoria']);
        $quantidade = floatval($_POST['quantidade']);
        $unidade_medida = mysqli_real_escape_string($conn, $_POST['unidade_medida']);
        $nivel_alerta = !empty($_POST['nivel_alerta']) ? intval($_POST['nivel_alerta']) : 0;
        
        $status_estoque = ($quantidade <= $nivel_alerta) ? 'Alerta' : 'Normal';
        $id_usuario = $_SESSION['user_id'];

        if ($editId) {
            $update_query = "UPDATE estoque SET 
                nome_item = '$nome_item', 
                categoria = '$categoria', 
                quantidade = $quantidade, 
                unidade_medida = '$unidade_medida', 
                nivel_alerta = $nivel_alerta, 
                status_estoque = '$status_estoque' 
                WHERE id_item = $editId";
            if (mysqli_query($conn, $update_query)) {
                header("Location: estoque.php?msg=editado");
                exit;
            } else {
                $msg_erro = "Erro ao atualizar item do estoque: " . mysqli_error($conn);
            }
        } else {
            $insert_query = "INSERT INTO estoque 
                (nome_item, categoria, quantidade, unidade_medida, nivel_alerta, status_estoque, id_usuario) 
                VALUES 
                ('$nome_item', '$categoria', $quantidade, '$unidade_medida', $nivel_alerta, '$status_estoque', $id_usuario)";
            if (mysqli_query($conn, $insert_query)) {
                header("Location: estoque.php?msg=criado");
                exit;
            } else {
                $msg_erro = "Erro ao adicionar item ao estoque: " . mysqli_error($conn);
            }
        }
    }
}

$activePage = 'estoque';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão - Novo Estoque</title>
    
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
        
        <?php include 'sidebar.php'; ?>

        <div class="main-wrapper">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="menu-btn" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
                    <div class="topbar-title" id="page-action-title"><?php echo $editId ? "Editar Item" : "Cadastro de Insumos"; ?></div>
                </div>
            </header>

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

                <form class="form-container" id="form-estoque" action="cadastro_insumos.php<?php echo $editId ? '?editId=' . $editId : ''; ?>" method="POST">
                    <div class="field-card">
                        <label>Nome do Insumo</label>
                        <input name="nome_item" type="text" class="form-input" placeholder="Ex: Adubo NPK, Sementes de Milho" required value="<?php echo htmlspecialchars($nome_item); ?>" <?php echo e_visitante() ? 'disabled' : ''; ?>>
                    </div>
                
                    <div class="field-card">
                        <label>Categoria</label>
                        <select name="categoria" class="form-select" required <?php echo e_visitante() ? 'disabled' : ''; ?>>
                            <option value="Semente" <?php echo $categoria === 'Semente' ? 'selected' : ''; ?>>Semente</option>
                            <option value="Adubo" <?php echo $categoria === 'Adubo' ? 'selected' : ''; ?>>Adubo / Fertilizante</option>
                            <option value="Defensivo" <?php echo $categoria === 'Defensivo' ? 'selected' : ''; ?>>Defensivo Orgânico</option>
                            <option value="Outros" <?php echo $categoria === 'Outros' ? 'selected' : ''; ?>>Outros</option>
                        </select>
                    </div>
                
                    <div class="field-card">
                        <label>Quantidade em Estoque</label>
                        <div class="input-row">
                            <input name="quantidade" type="number" step="0.01" class="form-input" placeholder="Ex: 50" required value="<?php echo htmlspecialchars($quantidade); ?>" <?php echo e_visitante() ? 'disabled' : ''; ?>>
                            <select name="unidade_medida" class="form-select" style="max-width: 120px;" <?php echo e_visitante() ? 'disabled' : ''; ?>>
                                <option value="Kg" <?php echo $unidade_medida === 'Kg' ? 'selected' : ''; ?>>Kg</option>
                                <option value="Litros" <?php echo $unidade_medida === 'Litros' ? 'selected' : ''; ?>>Litros</option>
                                <option value="Unid" <?php echo $unidade_medida === 'Unid' ? 'selected' : ''; ?>>Unid</option>
                            </select>
                        </div>
                    </div>
                
                    <div class="field-card">
                        <label>Nível para Alerta Mínimo (Opcional)</label>
                        <input name="nivel_alerta" type="number" class="form-input" placeholder="Ex: 10" value="<?php echo htmlspecialchars($nivel_alerta); ?>" <?php echo e_visitante() ? 'disabled' : ''; ?>>
                    </div>
                
                    <?php if (!e_visitante()): ?>
                        <button type="submit" id="btn-save" class="btn-submit"><?php echo $editId ? "Atualizar Estoque" : "Salvar no Estoque"; ?></button>
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
