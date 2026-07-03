<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';

verificar_login();

$msg_erro = "";

// Buscar culturas do USUÁRIO atual
$id_usuario = $_SESSION['user_id'];
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
        $id_cultura         = intval($_POST['id_cultura']);
        $data_plantio       = $_POST['data_plantio'];
        $local_canteiro     = trim($_POST['local_canteiro']);
        $quantidade_plantada= intval($_POST['quantidade_plantada']);
        $notas_plantio      = trim($_POST['notas_plantio'] ?? '');
        $tamanho_area       = !empty($_POST['tamanho_area']) ? floatval($_POST['tamanho_area']) : null;
        $unidade_area       = trim($_POST['unidade_area'] ?? 'm²');

        // Validações básicas
        if ($id_cultura <= 0 || empty($data_plantio) || empty($local_canteiro) || $quantidade_plantada <= 0) {
            $msg_erro = "Preencha todos os campos obrigatórios.";
        } else {
            // Buscar a sigla da cultura para o código do lote
            $stmt_c = $conn->prepare("SELECT nome_cultura FROM culturas WHERE id_cultura = ?");
            $stmt_c->bind_param("i", $id_cultura);
            $stmt_c->execute();
            $res_c = $stmt_c->get_result();
            $cultura_row = $res_c->fetch_assoc();
            $nome_cultura = $cultura_row ? $cultura_row['nome_cultura'] : 'CUL';

            // Gerar sigla de 3 letras da cultura
            $sigla = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $nome_cultura), 0, 3));
            if (strlen($sigla) < 3) $sigla = str_pad($sigla, 3, 'X');

            // Extrair número do canteiro para o código
            preg_match('/\d+/', $local_canteiro, $canteiro_matches);
            $canteiro_num = !empty($canteiro_matches) ? 'C' . $canteiro_matches[0] : 'C1';

            // Gerar sufixo aleatório de 6 dígitos
            $sufixo = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $codigo_lote = "LT-{$sigla}-{$canteiro_num}-{$sufixo}";

            // Inserir com prepared statement
            $stmt = $conn->prepare(
                "INSERT INTO plantios (id_cultura, data_plantio, local_canteiro, quantidade_plantada, progresso_colheita, notas_plantio, irrigado, colhido, codigo_lote, tamanho_area, unidade_area)
                 VALUES (?, ?, ?, ?, '0', ?, 0, 0, ?, ?, ?)"
            );
            $stmt->bind_param("issiisds",
                $id_cultura, $data_plantio, $local_canteiro, $quantidade_plantada,
                $notas_plantio, $codigo_lote, $tamanho_area, $unidade_area
            );

            if ($stmt->execute()) {
                header("Location: plantios_ativos.php?msg=criado");
                exit;
            } else {
                $msg_erro = "Erro ao registrar plantio: " . $stmt->error;
            }
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
    <title>AgroGestão - Novo Plantio</title>
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
            <button class="menu-btn" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
            <div class="topbar-title">Cadastro de Plantio</div>
        </header>
        <main class="main-content">

            <?php if (e_visitante()): ?>
                <div style="background:#fee2e2;color:#ef4444;border:1px solid #fca5a5;padding:15px;border-radius:8px;margin-bottom:20px;font-weight:700;display:flex;align-items:center;gap:10px;">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>Modo de Leitura: Como visitante, você não pode realizar alterações nesta página.</span>
                </div>
            <?php endif; ?>

            <?php if (!empty($msg_erro)): ?>
                <div style="background:#fee2e2;color:#ef4444;border:1px solid #fca5a5;padding:12px;border-radius:8px;margin-bottom:16px;font-weight:700;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($msg_erro); ?>
                </div>
            <?php endif; ?>

            <?php if (count($culturas) === 0): ?>
                <div style="background:#fef3c7;color:#d97706;border:1px solid #fcd34d;padding:15px;border-radius:8px;margin-bottom:20px;font-weight:700;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Atenção: Cadastre pelo menos uma cultura antes de criar um plantio. <a href="cadastro_culturas.php" style="color:#b45309;text-decoration:underline;">Cadastrar Cultura</a></span>
                </div>
            <?php endif; ?>

            <!-- Preview do código de lote -->
            <div id="lote-preview" style="display:none;background:linear-gradient(135deg,#16a34a,#15803d);color:white;border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:14px;">
                <div style="font-size:10px;font-weight:800;opacity:.7;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;"><i class="fa-solid fa-barcode"></i> Código de Lote (gerado automaticamente)</div>
                <div id="lote-preview-code" style="font-size:20px;font-weight:900;font-family:monospace;letter-spacing:2px;">LT-???-C?-??????</div>
            </div>

            <form class="form-container" id="form-plantio" action="cadastro_plantio.php" method="POST">

                <div class="field-card">
                    <label>Cultura Selecionada *</label>
                    <select name="id_cultura" id="sel-cultura" class="form-select" required <?php echo e_visitante() || count($culturas)===0 ? 'disabled':''; ?>>
                        <option value="">Selecione do Catálogo...</option>
                        <?php foreach ($culturas as $c): ?>
                            <option value="<?php echo $c['id_cultura']; ?>" data-nome="<?php echo htmlspecialchars($c['nome_cultura']); ?>">
                                <?php echo htmlspecialchars($c['nome_cultura']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field-card">
                    <label>Data do Plantio *</label>
                    <input name="data_plantio" type="date" class="form-input" required value="<?php echo date('Y-m-d'); ?>" <?php echo e_visitante() ? 'disabled':''; ?>>
                </div>

                <div class="field-card">
                    <label>Local ou Canteiro *</label>
                    <input name="local_canteiro" id="inp-canteiro" type="text" class="form-input" placeholder="Ex: Canteiro 04, Estufa A" required <?php echo e_visitante() ? 'disabled':''; ?>>
                </div>

                <div class="field-card">
                    <label>Quantidade Plantada *</label>
                    <div class="input-row">
                        <input name="quantidade_plantada" type="number" min="1" class="form-input" placeholder="Ex: 50" required <?php echo e_visitante() ? 'disabled':''; ?>>
                        <select class="form-select" style="max-width:120px;" <?php echo e_visitante() ? 'disabled':''; ?>>
                            <option>Unid</option>
                        </select>
                    </div>
                </div>

                <div class="field-card">
                    <label>Área do Plantio (Opcional)</label>
                    <div class="input-row">
                        <input name="tamanho_area" type="number" step="0.01" min="0" class="form-input" placeholder="Ex: 200" <?php echo e_visitante() ? 'disabled':''; ?>>
                        <select name="unidade_area" class="form-select" style="max-width:120px;" <?php echo e_visitante() ? 'disabled':''; ?>>
                            <option value="m²">m²</option>
                            <option value="ha">Hectares</option>
                            <option value="alq">Alqueire</option>
                        </select>
                    </div>
                </div>

                <div class="field-card">
                    <label>Notas do Plantio</label>
                    <textarea name="notas_plantio" class="form-textarea" placeholder="Ex: Solo adubado com húmus, irrigação por gotejamento." <?php echo e_visitante() ? 'disabled':''; ?>></textarea>
                </div>

                <?php if (!e_visitante() && count($culturas) > 0): ?>
                    <button type="submit" class="btn-submit"><i class="fa-solid fa-seedling"></i> Confirmar Plantio</button>
                <?php endif; ?>
            </form>
        </main>
    </div>
</div>
<script>
    function toggleMenu() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('overlay').classList.toggle('active');
    }

    // Pré-visualização do código de lote em tempo real
    function updateLotePreview() {
        const sel = document.getElementById('sel-cultura');
        const inp = document.getElementById('inp-canteiro');
        const preview = document.getElementById('lote-preview');
        const code = document.getElementById('lote-preview-code');

        if (!sel || !inp) return;

        const nomeCultura = sel.options[sel.selectedIndex]?.dataset?.nome || '';
        const canteiro = inp.value;

        if (!nomeCultura || !canteiro) { preview.style.display='none'; return; }

        const sigla = nomeCultura.replace(/[^a-zA-Z]/g,'').substring(0,3).toUpperCase().padEnd(3,'X');
        const numMatch = canteiro.match(/\d+/);
        const cNum = numMatch ? 'C' + numMatch[0] : 'C1';
        const sufixo = String(Math.floor(Math.random()*999999)).padStart(6,'0');
        code.textContent = `LT-${sigla}-${cNum}-${sufixo}`;
        preview.style.display='block';
    }

    document.getElementById('sel-cultura')?.addEventListener('change', updateLotePreview);
    document.getElementById('inp-canteiro')?.addEventListener('input', updateLotePreview);
</script>
</body>
</html>
