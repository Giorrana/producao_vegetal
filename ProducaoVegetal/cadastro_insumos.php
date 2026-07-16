<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';

verificar_login();

// Editado para permitir operadores e administradores (menu principal)

$editId = isset($_GET['editId']) ? intval($_GET['editId']) : null;
$msg_erro = "";

// Valores padrão
$nome_item = $categoria = $unidade_medida = $lote_fabricante = "";
$quantidade = $nivel_alerta = $custo_aquisicao = "";
$data_validade = "";
$categoria = "Semente";
$unidade_medida = "Kg";

if ($editId) {
    $sql = "SELECT * FROM estoque WHERE id_item = ? AND " . escopo_sql('id_usuario');
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $item = $result->fetch_assoc();
        $nome_item      = $item['nome_item'];
        $categoria      = $item['categoria'];
        $quantidade     = $item['quantidade'];
        $unidade_medida = $item['unidade_medida'];
        $nivel_alerta   = $item['nivel_alerta'];
        $data_validade  = $item['data_validade'] ?? '';
        $lote_fabricante= $item['lote_fabricante'] ?? '';
        $custo_aquisicao= $item['custo_aquisicao'] ?? '';
    } else {
        $editId = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_item       = trim($_POST['nome_item']);
    $categoria       = trim($_POST['categoria']);
    $quantidade      = floatval($_POST['quantidade']);
    $unidade_medida  = trim($_POST['unidade_medida']);
    $nivel_alerta    = !empty($_POST['nivel_alerta']) ? intval($_POST['nivel_alerta']) : 0;
    $data_validade   = !empty($_POST['data_validade']) ? $_POST['data_validade'] : null;
    $lote_fabricante = trim($_POST['lote_fabricante'] ?? '');
    $custo_aquisicao = !empty($_POST['custo_aquisicao']) ? floatval($_POST['custo_aquisicao']) : null;
    $status_estoque  = ($quantidade <= $nivel_alerta) ? 'Alerta' : 'Normal';
    $id_usuario      = $_SESSION['user_id'];

    if (empty($nome_item) || empty($categoria)) {
        $msg_erro = "Preencha todos os campos obrigatórios.";
    } else {
        if ($editId) {
            $sql = "UPDATE estoque SET nome_item=?, categoria=?, quantidade=?, unidade_medida=?, nivel_alerta=?, status_estoque=?, data_validade=?, lote_fabricante=?, custo_aquisicao=? WHERE id_item=? AND " . escopo_sql('id_usuario');
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdsssssdi", $nome_item, $categoria, $quantidade, $unidade_medida, $nivel_alerta, $status_estoque, $data_validade, $lote_fabricante, $custo_aquisicao, $editId);
            if ($stmt->execute()) {
                header("Location: estoque.php?msg=editado"); exit;
            } else { $msg_erro = "Erro ao atualizar: " . $stmt->error; }
        } else {
            $stmt = $conn->prepare("INSERT INTO estoque (nome_item, categoria, quantidade, unidade_medida, nivel_alerta, status_estoque, id_usuario, data_validade, lote_fabricante, custo_aquisicao) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssdsssissd", $nome_item, $categoria, $quantidade, $unidade_medida, $nivel_alerta, $status_estoque, $id_usuario, $data_validade, $lote_fabricante, $custo_aquisicao);
            if ($stmt->execute()) {
                header("Location: estoque.php?msg=criado"); exit;
            } else { $msg_erro = "Erro ao inserir: " . $stmt->error; }
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
    <title>AgroGestão - <?php echo $editId ? 'Editar Insumo' : 'Novo Insumo'; ?></title>
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
                <div class="topbar-title"><?php echo $editId ? 'Editar Insumo' : 'Cadastro de Insumos'; ?></div>
            </div>
        </header>
        <main class="main-content">

            <?php if (!empty($msg_erro)): ?>
                <div style="background:#fee2e2;color:#ef4444;border:1px solid #fca5a5;padding:12px;border-radius:8px;margin-bottom:16px;font-weight:700;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($msg_erro); ?>
                </div>
            <?php endif; ?>

            <form class="form-container" id="form-estoque" action="cadastro_insumos.php<?php echo $editId ? '?editId='.$editId : ''; ?>" method="POST">

                <div class="field-card">
                    <label>Nome do Insumo *</label>
                    <input name="nome_item" type="text" class="form-input" placeholder="Ex: Adubo NPK, Sementes de Milho" required value="<?php echo htmlspecialchars($nome_item); ?>">
                </div>

                <div class="field-card">
                    <label>Categoria *</label>
                    <select name="categoria" class="form-select" required>
                        <option value="Semente"   <?php echo $categoria==='Semente'   ? 'selected':''; ?>>Semente</option>
                        <option value="Adubo"     <?php echo $categoria==='Adubo'     ? 'selected':''; ?>>Adubo / Fertilizante</option>
                        <option value="Defensivo" <?php echo $categoria==='Defensivo' ? 'selected':''; ?>>Defensivo Orgânico</option>
                        <option value="Outros"    <?php echo $categoria==='Outros'    ? 'selected':''; ?>>Outros</option>
                    </select>
                </div>

                <div class="field-card">
                    <label>Quantidade em Estoque *</label>
                    <div class="input-row">
                        <input name="quantidade" type="number" step="0.01" min="0" class="form-input" placeholder="Ex: 50" required value="<?php echo htmlspecialchars($quantidade); ?>">
                        <select name="unidade_medida" class="form-select" style="max-width:120px;">
                            <option value="Kg"     <?php echo $unidade_medida==='Kg'     ? 'selected':''; ?>>Kg</option>
                            <option value="Litros" <?php echo $unidade_medida==='Litros' ? 'selected':''; ?>>Litros</option>
                            <option value="Unid"   <?php echo $unidade_medida==='Unid'   ? 'selected':''; ?>>Unid</option>
                            <option value="g"      <?php echo $unidade_medida==='g'      ? 'selected':''; ?>>g</option>
                            <option value="mL"     <?php echo $unidade_medida==='mL'     ? 'selected':''; ?>>mL</option>
                            <option value="Sacos"  <?php echo $unidade_medida==='Sacos'  ? 'selected':''; ?>>Sacos</option>
                        </select>
                    </div>
                </div>

                <div class="field-card">
                    <label>Custo de Aquisição (R$ / unidade)</label>
                    <input name="custo_aquisicao" type="number" step="0.01" min="0" class="form-input" placeholder="Ex: 12.50" value="<?php echo htmlspecialchars($custo_aquisicao); ?>">
                </div>

                <div class="field-card">
                    <label>Lote do Fabricante</label>
                    <input name="lote_fabricante" type="text" class="form-input" placeholder="Ex: LT-2024-0045A" value="<?php echo htmlspecialchars($lote_fabricante); ?>">
                </div>

                <div class="field-card">
                    <label>Data de Validade</label>
                    <input name="data_validade" type="date" class="form-input" value="<?php echo htmlspecialchars($data_validade); ?>">
                </div>

                <div class="field-card">
                    <label>Nível para Alerta Mínimo (Opcional)</label>
                    <input name="nivel_alerta" type="number" class="form-input" placeholder="Ex: 10" value="<?php echo htmlspecialchars($nivel_alerta); ?>">
                </div>

                <button type="submit" class="btn-submit"><?php echo $editId ? 'Atualizar Insumo' : 'Salvar no Estoque'; ?></button>
            </form>
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
