<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';
verificar_login();

$msg_erro   = "";
$msg_sucesso = "";
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'Todos';

// ─── HANDLE AJAX: Registrar Manejo ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_manejo'])) {
    header('Content-Type: application/json');
    if (e_visitante()) { echo json_encode(['ok'=>false,'msg'=>'Acesso restrito.']); exit; }

    $id_plantio     = intval($_POST['id_plantio']);
    $tipo_manejo    = trim($_POST['tipo_manejo']);
    $id_item        = !empty($_POST['id_item']) ? intval($_POST['id_item']) : null;
    $qty_usada      = !empty($_POST['quantidade_usada']) ? floatval($_POST['quantidade_usada']) : null;
    $operador       = trim($_POST['operador'] ?? '');
    $observacoes    = trim($_POST['observacoes'] ?? '');
    $id_usuario     = $_SESSION['user_id'];

    // Verificar se o plantio pertence a este usuário
    $stmt_chk = $conn->prepare("SELECT p.id_plantio FROM plantios p JOIN culturas c ON p.id_cultura = c.id_cultura WHERE p.id_plantio = ? AND c.id_usuario = ?");
    $stmt_chk->bind_param("ii", $id_plantio, $id_usuario);
    $stmt_chk->execute();
    if ($stmt_chk->get_result()->num_rows === 0) {
        echo json_encode(['ok'=>false, 'msg'=>'Acesso negado ao plantio.']);
        exit;
    }

    // Calcular custo da aplicação e realizar baixa automática no estoque
    $custo_aplic = null;
    if ($id_item && $qty_usada) {
        $stmt_i = $conn->prepare("SELECT custo_aquisicao, quantidade, nome_item FROM estoque WHERE id_item = ? AND id_usuario = ?");
        $stmt_i->bind_param("ii", $id_item, $id_usuario);
        $stmt_i->execute();
        $res_i = $stmt_i->get_result()->fetch_assoc();

        if ($res_i) {
            $custo_aplic = $res_i['custo_aquisicao'] ? $res_i['custo_aquisicao'] * $qty_usada : null;

            // Baixa automática no estoque (não permite ficar negativo)
            $nova_qty = max(0, $res_i['quantidade'] - $qty_usada);
            $novo_status = ($nova_qty <= 0) ? 'Alerta' : 'Normal';
            $stmt_upd = $conn->prepare("UPDATE estoque SET quantidade = ?, status_estoque = ? WHERE id_item = ? AND id_usuario = ?");
            $stmt_upd->bind_param("dsii", $nova_qty, $novo_status, $id_item, $id_usuario);
            $stmt_upd->execute();
        }
    }

    // Inserir log no caderno de campo
    $stmt_ins = $conn->prepare("INSERT INTO cuidados_plantio(id_plantio,tipo_manejo,id_item,quantidade_usada,custo_calculado,responsavel)VALUES (?,?,?,?,?,?)"
);
    $stmt_ins->bind_param("isidds",$id_plantio,$tipo_manejo,$id_item,$qty_usada,$custo_aplic,$operador
);

    if ($stmt_ins->execute()) {
        // Auto-update irrigado flag se for Irrigacao
        if ($tipo_manejo === 'Irrigacao') {
            $conn->query("UPDATE plantios p JOIN culturas c ON p.id_cultura = c.id_cultura SET p.irrigado=1, p.dias_irrigados=COALESCE(p.dias_irrigados,0)+1 WHERE p.id_plantio=$id_plantio AND c.id_usuario=$id_usuario");
        }
        echo json_encode(['ok'=>true, 'custo'=>$custo_aplic]);
    } else {
        echo json_encode(['ok'=>false, 'msg'=>$stmt_ins->error]);
    }
    exit;
}

// ─── HANDLE GET: irrigar / colher ──────────────────────────────────────────
if (isset($_GET['action']) && !e_visitante()) {
    $id = intval($_GET['id'] ?? 0);
    $id_usuario = $_SESSION['user_id'];

    if ($_GET['action'] === 'irrigar' && $id > 0) {
        $q = $conn->query("SELECT p.irrigado FROM plantios p JOIN culturas c ON p.id_cultura = c.id_cultura WHERE p.id_plantio=$id AND c.id_usuario = $id_usuario");
        if ($q && $row = $q->fetch_assoc()) {
            $novo = $row['irrigado'] == 1 ? 0 : 1;
            $inc  = $novo == 1 ? ', p.dias_irrigados = COALESCE(p.dias_irrigados,0)+1' : '';
            $conn->query("UPDATE plantios p JOIN culturas c ON p.id_cultura = c.id_cultura SET p.irrigado=$novo$inc WHERE p.id_plantio=$id AND c.id_usuario = $id_usuario");
        }
        header("Location: plantios_ativos.php?filtro=$filtro"); exit;
    }

    if ($_GET['action'] === 'colher' && $id > 0 && isset($_GET['qtd'])) {
        $q_chk = $conn->query("SELECT p.id_plantio FROM plantios p JOIN culturas c ON p.id_cultura = c.id_cultura WHERE p.id_plantio=$id AND c.id_usuario = $id_usuario");
        if ($q_chk && $q_chk->num_rows > 0) {
            preg_match('/[0-9]+(?:\.[0-9]+)?/', $_GET['qtd'], $m);
            $qtd = isset($m[0]) ? floatval($m[0]) : 0;
            if ($qtd > 0) {
                mysqli_begin_transaction($conn);
                $s1 = $conn->prepare("INSERT INTO colheitas (data_colheita, quantidade_colhida, id_plantio) VALUES (CURRENT_DATE(), ?, ?)");
                $s1->bind_param("di", $qtd, $id);
                $s2 = $conn->prepare("UPDATE plantios SET colhido=1, progresso_colheita='100' WHERE id_plantio=?");
                $s2->bind_param("i", $id);
                if ($s1->execute() && $s2->execute()) { mysqli_commit($conn); header("Location: historico.php?msg=colheita_sucesso"); exit; }
                else { mysqli_rollback($conn); $msg_erro = "Erro ao registrar colheita."; }
            } else { $msg_erro = "Quantidade inválida."; }
        } else { $msg_erro = "Acesso negado ao plantio."; }
    }
}

// ─── BUSCAR PLANTIOS ATIVOS ─────────────────────────────────────────────────
$id_usuario = $_SESSION['user_id'];
$query = "SELECT p.*, c.nome_cultura, c.tempo_medio_crescimento, cat.nome_categoria
          FROM plantios p
          JOIN culturas c ON p.id_cultura = c.id_cultura
          JOIN categorias cat ON c.id_categoria = cat.id_categoria
          WHERE p.colhido = 0 AND c.id_usuario = $id_usuario";
if ($filtro === 'Horta')  $query .= " AND cat.nome_categoria = 'Horta'";
if ($filtro === 'Pomar')  $query .= " AND cat.nome_categoria = 'Pomar'";
$query .= " ORDER BY p.id_plantio DESC";

$result  = mysqli_query($conn, $query);
$plantios = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $dias_ciclo = intval($row['tempo_medio_crescimento']) ?: 90;
        $dap = (int) floor((time() - strtotime($row['data_plantio'])) / 86400);
        $dap = max(0, $dap);
        $progresso = min(100, round(($dap / $dias_ciclo) * 100));
        $row['dap'] = $dap;
        $row['progresso_calculado'] = $progresso;
        $row['dias_ciclo'] = $dias_ciclo;

        // Buscar logs do caderno de campo (últimos 5)
        $stmt_logs = $conn->prepare(
            "SELECT cp.*, e.nome_item FROM cuidados_plantio cp
             LEFT JOIN estoque e ON cp.id_item = e.id_item
             WHERE cp.id_plantio = ? ORDER BY cp.data_cuidado DESC LIMIT 5"
        );
        $stmt_logs->bind_param("i", $row['id_plantio']);
        $stmt_logs->execute();
        $row['logs'] = $stmt_logs->get_result()->fetch_all(MYSQLI_ASSOC);

        // Custo acumulado total
        $stmt_custo = $conn->prepare("SELECT SUM(custo_calculado) AS total FROM cuidados_plantio WHERE id_plantio = ?");
        $stmt_custo->bind_param("i", $row['id_plantio']);
        $stmt_custo->execute();
        $custo_row = $stmt_custo->get_result()->fetch_assoc();
        $row['custo_total'] = $custo_row['total'] ?? 0;

        $plantios[] = $row;
    }
}

// ─── BUSCAR INSUMOS PARA MODAL ──────────────────────────────────────────────
$insumos_modal = [];
$res_ins = $conn->query("SELECT id_item, nome_item, categoria, unidade_medida, quantidade FROM estoque WHERE quantidade > 0 AND id_usuario = $id_usuario ORDER BY nome_item ASC");
if ($res_ins) $insumos_modal = $res_ins->fetch_all(MYSQLI_ASSOC);

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
    <script>if (localStorage.getItem('agro_theme') === 'dark') document.documentElement.classList.add('dark-theme');</script>
    <style>
        /* ── Growth Stepper ─────────────── */
        .growth-stepper {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            position: relative;
            margin: 16px 0 8px;
            padding: 0 4px;
        }
        .growth-stepper::before {
            content: '';
            position: absolute;
            top: 13px;
            left: 14px;
            right: 14px;
            height: 3px;
            background: var(--border-color);
            z-index: 0;
        }
        .growth-stepper .step-progress-line {
            position: absolute;
            top: 13px;
            left: 14px;
            height: 3px;
            background: linear-gradient(90deg, #16a34a, #22c55e);
            z-index: 1;
            transition: width .5s ease;
        }
        .gs-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        .gs-dot {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 3px solid var(--border-color);
            background: var(--bg-white, #fff);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: var(--text-gray);
            transition: all .3s;
        }
        .gs-step.done .gs-dot {
            background: #16a34a;
            border-color: #16a34a;
            color: white;
        }
        .gs-step.active .gs-dot {
            background: #22c55e;
            border-color: #22c55e;
            color: white;
            box-shadow: 0 0 0 4px rgba(34,197,94,.25);
        }
        .gs-label {
            font-size: 9px;
            font-weight: 700;
            text-align: center;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: .3px;
            line-height: 1.2;
            max-width: 54px;
        }
        .gs-step.done .gs-label, .gs-step.active .gs-label { color: #16a34a; }

        /* ── DAP badge ───────────────────── */
        .dap-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(34,197,94,.12);
            color: #16a34a;
            font-size: 11px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 20px;
            margin-top: 6px;
            border: 1px solid rgba(34,197,94,.25);
        }

        /* ── Lot code badge ─────────────── */
        .lote-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(59,130,246,.1);
            color: #2563eb;
            font-size: 10px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 20px;
            margin-top: 4px;
            font-family: monospace;
            letter-spacing: .5px;
        }

        /* ── Caderno de Campo ────────────── */
        .caderno-toggle {
            width: 100%;
            background: none;
            border: none;
            border-top: 1px dashed var(--border-color);
            padding: 10px 0 0;
            margin-top: 14px;
            cursor: pointer;
            color: var(--text-gray);
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
        }
        .caderno-toggle:hover { color: var(--primary-green); }
        .caderno-toggle .ct-arrow { margin-left: auto; transition: transform .25s; }
        .caderno-toggle.open .ct-arrow { transform: rotate(180deg); }

        .caderno-body {
            display: none;
            padding: 10px 0 0;
        }
        .caderno-body.open { display: block; }

        .log-entry {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 12px;
        }
        .log-entry:last-child { border-bottom: none; }
        .log-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
        }
        .log-icon.irr { background: rgba(59,130,246,.15); color: #3b82f6; }
        .log-icon.adu { background: rgba(34,197,94,.15); color: #16a34a; }
        .log-icon.def { background: rgba(239,68,68,.12); color: #ef4444; }
        .log-icon.out { background: rgba(245,158,11,.12); color: #d97706; }
        .log-info { flex: 1; }
        .log-info b { display: block; font-weight: 700; color: var(--dark-green); margin-bottom: 2px; }
        .log-meta { color: var(--text-gray); font-size: 11px; }
        .log-cost { font-size: 11px; font-weight: 700; color: #16a34a; white-space: nowrap; }

        /* ── Custo badge ─────────────────── */
        .custo-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 800;
            color: #b45309;
            background: rgba(245,158,11,.1);
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: auto;
        }

        /* ── Modal ───────────────────────── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.55);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s;
        }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal-box {
            background: var(--bg-white, #fff);
            border-radius: 20px;
            width: 100%;
            max-width: 440px;
            padding: 28px 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,.3);
            transform: translateY(20px);
            transition: transform .25s;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-overlay.open .modal-box { transform: translateY(0); }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .modal-header h3 {
            font-size: 17px;
            font-weight: 800;
            color: var(--dark-green);
            margin: 0;
        }
        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: var(--border-color);
            color: var(--text-gray);
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-field { margin-bottom: 14px; }
        .modal-field label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 5px;
        }
        .modal-field select, .modal-field input, .modal-field textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            background: var(--form-bg, #f9fafb);
            color: var(--dark-green);
            font-family: inherit;
            box-sizing: border-box;
        }
        .modal-field select:focus, .modal-field input:focus, .modal-field textarea:focus {
            outline: none;
            border-color: #22c55e;
        }
        .modal-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg,#16a34a,#22c55e);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 6px;
        }
        .modal-submit:hover { filter: brightness(1.05); }
        .modal-submit:disabled { opacity: .6; cursor: not-allowed; }

        #insumo-group { transition: opacity .2s; }

        /* ── Culture code tag ────────────── */
        .cultura-tag {
            display: inline-block;
            background: rgba(34,197,94,.12);
            color: #16a34a;
            font-size: 10px;
            font-weight: 900;
            padding: 2px 8px;
            border-radius: 6px;
            font-family: monospace;
            letter-spacing: 1px;
            margin-left: 6px;
            border: 1px solid rgba(34,197,94,.25);
        }

        /* ── Action buttons ──────────────── */
        .btn-manejo {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 18px;
            border: none;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            background: linear-gradient(135deg,#2563eb,#3b82f6);
            color: white;
            width: 100%;
            justify-content: center;
        }
        .btn-manejo:hover { filter: brightness(1.08); }

        .toast-msg {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: #16a34a;
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 700;
            z-index: 9999;
            opacity: 0;
            transition: all .3s;
            pointer-events: none;
            white-space: nowrap;
        }
        .toast-msg.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>

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
                    <div style="background:#fee2e2;color:#ef4444;border:1px solid #fca5a5;padding:10px;border-radius:8px;margin-bottom:15px;font-weight:700;">
                        <?php echo htmlspecialchars($msg_erro); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'criado'): ?>
                    <div style="background:#d1fae5;color:#10b981;border:1px solid #6ee7b7;padding:10px;border-radius:8px;margin-bottom:15px;font-weight:700;">
                        <i class="fa-solid fa-seedling"></i> Novo plantio registrado com sucesso!
                    </div>
                <?php endif; ?>

                <!-- FILTROS + BOTÃO ADD -->
                <div class="filters-container">
                    <a href="plantios_ativos.php?filtro=Todos" class="filter-btn <?php echo $filtro==='Todos'?'active':'inactive'; ?>">Todos</a>
                    <a href="plantios_ativos.php?filtro=Horta" class="filter-btn <?php echo $filtro==='Horta'?'active':'inactive'; ?>">Horta</a>
                    <a href="plantios_ativos.php?filtro=Pomar" class="filter-btn <?php echo $filtro==='Pomar'?'active':'inactive'; ?>">Pomar</a>
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
                            $progresso  = $p['progresso_calculado'];
                            $dap        = $p['dap'];
                            $dias_ciclo = $p['dias_ciclo'];

                            // FontAwesome icon mapping
                            $nomeMin = strtolower($p['nome_cultura']);
                            $fa_icon = 'fa-seedling';
                            if (str_contains($nomeMin,'tomate'))   $fa_icon = 'fa-circle-dot';
                            elseif (str_contains($nomeMin,'alface')) $fa_icon = 'fa-leaf';
                            elseif (str_contains($nomeMin,'milho'))  $fa_icon = 'fa-wheat-awn';
                            elseif (str_contains($nomeMin,'cenoura'))$fa_icon = 'fa-carrot';
                            elseif (str_contains($nomeMin,'pimenta'))$fa_icon = 'fa-pepper-hot';

                            // Sigla 3 letras da cultura para tag
                            $sigla_tag = strtoupper(substr(preg_replace('/[^a-zA-Z]/','',$p['nome_cultura']),0,3));

                            // Growth stepper thresholds
                            $steps = [
                                ['label'=>'Germinação','pct'=>0,  'icon'=>'fa-seedling'],
                                ['label'=>'Cresc. Veg.','pct'=>25, 'icon'=>'fa-leaf'],
                                ['label'=>'Floração',  'pct'=>60, 'icon'=>'fa-sun'],
                                ['label'=>'Colheita',  'pct'=>90, 'icon'=>'fa-wheat-awn'],
                            ];
                            // Line width %
                            $line_pct = $progresso; // 0-100 mapped between first and last step
                            // active step index
                            $active_idx = 0;
                            foreach ($steps as $si => $s) {
                                if ($progresso >= $s['pct']) $active_idx = $si;
                            }
                        ?>
                            <div class="plantio-card" id="card-<?php echo $p['id_plantio']; ?>">
                                <!-- Header -->
                                <div class="plantio-header">
                                    <div class="plantio-icon"><i class="fa-solid <?php echo $fa_icon; ?>" style="font-size:22px;color:var(--primary-green);"></i></div>
                                    <div class="plantio-info">
                                        <h4>
                                            <?php echo htmlspecialchars($p['nome_cultura']); ?>
                                            <span class="cultura-tag"><?php echo $sigla_tag; ?></span>
                                        </h4>
                                        <p>
                                            <i class="fa-solid fa-location-dot" style="color:var(--primary-green);"></i>
                                            <?php echo htmlspecialchars($p['local_canteiro']); ?>
                                            <span style="font-size:11px;color:var(--text-gray);margin-left:8px;">(<?php echo htmlspecialchars($p['quantidade_plantada']); ?> un.)</span>
                                        </p>
                                        <?php if (!empty($p['codigo_lote'])): ?>
                                            <span class="lote-badge"><i class="fa-solid fa-barcode"></i> <?php echo htmlspecialchars($p['codigo_lote']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($p['custo_total'] > 0): ?>
                                        <div class="custo-badge"><i class="fa-solid fa-circle-dollar-to-slot"></i> R$ <?php echo number_format($p['custo_total'],2,',','.'); ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- DAP Badge -->
                                <div class="dap-badge">
                                    <i class="fa-regular fa-calendar"></i>
                                    DAP: <strong><?php echo $dap; ?></strong> / <?php echo $dias_ciclo; ?> dias &nbsp;&mdash;&nbsp; <?php echo $progresso; ?>% do ciclo
                                </div>

                                <!-- Growth Stepper -->
                                <div class="growth-stepper">
                                    <div class="step-progress-line" style="width:calc(<?php echo min(100,$progresso); ?>% - 14px);"></div>
                                    <?php foreach ($steps as $si => $s):
                                        $cls = '';
                                        if ($si < $active_idx)   $cls = 'done';
                                        elseif ($si === $active_idx) $cls = 'active';
                                    ?>
                                        <div class="gs-step <?php echo $cls; ?>">
                                            <div class="gs-dot"><i class="fa-solid <?php echo $s['icon']; ?>"></i></div>
                                            <div class="gs-label"><?php echo $s['label']; ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Actions -->
                                <?php if (!e_visitante()): ?>
                                    <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap;">
                                        <!-- Registrar Manejo -->
                                        <button class="btn-manejo" style="flex:2;"
                                            onclick="abrirManejo(<?php echo $p['id_plantio']; ?>, '<?php echo addslashes(htmlspecialchars($p['nome_cultura'])); ?>')">
                                            <i class="fa-solid fa-clipboard-list"></i> Registrar Manejo
                                        </button>

                                        <!-- Realizar Colheita -->
                                        <?php if ($progresso >= 90): ?>
                                            <button class="action-btn btn-colher" style="flex:1;"
                                                onclick="colherPlantio(<?php echo $p['id_plantio']; ?>, '<?php echo addslashes($p['nome_cultura']); ?>')">
                                                <i class="fa-solid fa-wheat-awn"></i> Colher
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size:11px;text-align:center;color:var(--text-gray);margin-top:14px;border-top:1px dashed var(--border-color);padding-top:10px;">
                                        <i class="fa-solid fa-eye"></i> Modo Leitura: apenas operadores realizam manejos.
                                    </div>
                                <?php endif; ?>

                                <!-- Caderno de Campo -->
                                <button class="caderno-toggle" onclick="toggleCaderno(this)">
                                    <i class="fa-solid fa-book-open"></i> Caderno de Campo
                                    <?php if (!empty($p['logs'])): ?>
                                        <span style="background:#22c55e;color:white;font-size:10px;font-weight:800;border-radius:50%;width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;">
                                            <?php echo count($p['logs']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <i class="fa-solid fa-chevron-down ct-arrow"></i>
                                </button>
                                <div class="caderno-body">
                                    <?php if (empty($p['logs'])): ?>
                                        <p style="font-size:12px;color:var(--text-gray);text-align:center;padding:12px 0;">
                                            <i class="fa-solid fa-inbox"></i> Nenhum registro ainda. Use "Registrar Manejo" para começar.
                                        </p>
                                    <?php else: ?>
                                        <?php foreach ($p['logs'] as $log):
                                            $li_cls = 'out';
                                            $li_icon = 'fa-pen-to-square';
                                            if ($log['tipo_manejo']==='Irrigacao')  { $li_cls='irr'; $li_icon='fa-droplet'; }
                                            elseif ($log['tipo_manejo']==='Adubacao')   { $li_cls='adu'; $li_icon='fa-leaf'; }
                                            elseif ($log['tipo_manejo']==='Defensivo')  { $li_cls='def'; $li_icon='fa-flask'; }
                                        ?>
                                            <div class="log-entry">
                                                <div class="log-icon <?php echo $li_cls; ?>"><i class="fa-solid <?php echo $li_icon; ?>"></i></div>
                                                <div class="log-info">
                                                    <b><?php echo htmlspecialchars($log['tipo_manejo']); ?></b>
                                                    <span class="log-meta">
                                                        <?php if (!empty($log['nome_item'])): ?>
                                                            <?php echo htmlspecialchars($log['nome_item']); ?>
                                                            <?php if ($log['quantidade_usada']): ?> — <?php echo number_format($log['quantidade_usada'],2,',','.'); ?> un.<?php endif; ?>
                                                        <?php endif; ?>
                                                        <?php if (!empty($log['operador'])): ?> · <?php echo htmlspecialchars($log['operador']); ?><?php endif; ?>
                                                        · <?php echo date('d/m/Y H:i', strtotime($log['data_cuidado'])); ?>
                                                    </span>
                                                </div>
                                                <?php if ($log['custo_calculado']): ?>
                                                    <div class="log-cost">R$ <?php echo number_format($log['custo_calculado'],2,',','.'); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
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

<!-- ── MODAL: Registrar Manejo ───────────────────────────────────────────── -->
<div class="modal-overlay" id="manejo-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-clipboard-list" style="color:#22c55e;margin-right:6px;"></i> Registrar Manejo</h3>
            <button class="modal-close" onclick="fecharManejo()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div id="manejo-plantio-info" style="background:rgba(34,197,94,.08);border-radius:10px;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:700;color:#16a34a;">
        </div>

        <form id="form-manejo" onsubmit="submitManejo(event)">
            <input type="hidden" id="m-id-plantio" name="id_plantio">
            <input type="hidden" name="ajax_manejo" value="1">

            <div class="modal-field">
                <label>Tipo de Manejo *</label>
                <select name="tipo_manejo" id="m-tipo" onchange="tipoManejoChanged(this.value)" required>
                    <option value="Irrigacao">💧 Irrigação</option>
                    <option value="Adubacao">🌿 Adubação / Fertilizante</option>
                    <option value="Defensivo">🧪 Aplicação de Defensivo</option>
                    <option value="Outro">📝 Outro</option>
                </select>
            </div>

            <div class="modal-field" id="insumo-group">
                <label>Insumo Utilizado</label>
                <select name="id_item" id="m-insumo">
                    <option value="">— Nenhum / Água —</option>
                    <?php foreach ($insumos_modal as $ins): ?>
                        <option value="<?php echo $ins['id_item']; ?>"
                            data-cat="<?php echo htmlspecialchars($ins['categoria']); ?>"
                            data-unit="<?php echo htmlspecialchars($ins['unidade_medida']); ?>">
                            <?php echo htmlspecialchars($ins['nome_item']); ?> (<?php echo number_format($ins['quantidade'],2,',','.'); ?> <?php echo htmlspecialchars($ins['unidade_medida']); ?> em estoque)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-field" id="qty-group">
                <label>Quantidade Utilizada</label>
                <input type="number" name="quantidade_usada" id="m-qty" step="0.001" min="0" placeholder="Ex: 2.5">
            </div>

            <div class="modal-field">
                <label>Operador Responsável</label>
                <input type="text" name="operador" id="m-operador" placeholder="Nome de quem realizou o manejo">
            </div>

            <div class="modal-field">
                <label>Observações</label>
                <textarea name="observacoes" rows="2" placeholder="Detalhes adicionais (opcional)"></textarea>
            </div>

            <button type="submit" class="modal-submit" id="btn-submit-manejo">
                <i class="fa-solid fa-floppy-disk"></i> Salvar Registro
            </button>
        </form>
    </div>
</div>

<!-- Toast notification -->
<div class="toast-msg" id="toast"></div>

<script>
    function toggleMenu() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('overlay').classList.toggle('active');
    }

    function toggleCaderno(btn) {
        btn.classList.toggle('open');
        const body = btn.nextElementSibling;
        body.classList.toggle('open');
    }

    // ── Modal ────────────────────────────────────────────────────────────────
    function abrirManejo(idPlantio, nomeCultura) {
        document.getElementById('m-id-plantio').value = idPlantio;
        document.getElementById('manejo-plantio-info').innerHTML =
            `<i class="fa-solid fa-seedling"></i> Plantio: <span style="font-weight:900;">${nomeCultura}</span>`;
        document.getElementById('manejo-overlay').classList.add('open');
        document.body.style.overflow = 'hidden';
        tipoManejoChanged(document.getElementById('m-tipo').value);
    }

    function fecharManejo() {
        document.getElementById('manejo-overlay').classList.remove('open');
        document.body.style.overflow = '';
        document.getElementById('form-manejo').reset();
    }

    // Close overlay on backdrop click
    document.getElementById('manejo-overlay').addEventListener('click', function(e) {
        if (e.target === this) fecharManejo();
    });

    function tipoManejoChanged(val) {
        const insumoGroup = document.getElementById('insumo-group');
        const qtyGroup    = document.getElementById('qty-group');
        // Show insumo selector for Adubacao and Defensivo
        if (val === 'Adubacao' || val === 'Defensivo') {
            insumoGroup.style.opacity = '1';
            qtyGroup.style.display = 'block';
        } else if (val === 'Irrigacao') {
            insumoGroup.style.opacity = '.4';
            qtyGroup.style.display = 'block';
        } else {
            insumoGroup.style.opacity = '.4';
            qtyGroup.style.display = 'none';
        }
    }

    async function submitManejo(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-submit-manejo');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';

        const form = document.getElementById('form-manejo');
        const data = new FormData(form);

        try {
            const res  = await fetch('plantios_ativos.php', { method: 'POST', body: data });
            const json = await res.json();

            if (json.ok) {
                fecharManejo();
                showToast('✅ Manejo registrado!' + (json.custo ? ' Custo: R$ ' + json.custo.toFixed(2).replace('.',',') : ''));
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('❌ Erro: ' + (json.msg || 'Tente novamente.'), true);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar Registro';
            }
        } catch(err) {
            showToast('❌ Erro de conexão.', true);
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar Registro';
        }
    }

    function colherPlantio(id, nome) {
        let qtd = prompt(`Parabéns! Qual a quantidade colhida de ${nome}? (ex: 20 Kg)`);
        if (qtd) {
            window.location.href = `plantios_ativos.php?action=colher&id=${id}&qtd=${encodeURIComponent(qtd)}&filtro=<?php echo $filtro; ?>`;
        }
    }

    function showToast(msg, isError = false) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.style.background = isError ? '#ef4444' : '#16a34a';
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 3500);
    }
</script>
</body>
</html>
