<?php
require_once '../Banco/conexao.php';
require_once 'auth.php';

verificar_login();

$msg_erro   = "";
$msg_sucesso = "";

// Parâmetros recebidos
$id_plantio = isset($_GET['id']) ? intval($_GET['id']) : 0;
$nome_cultura = isset($_GET['nome']) ? htmlspecialchars(urldecode($_GET['nome'])) : '';

// Se o plantio não for válido, redirecionar de volta
if ($id_plantio <= 0) {
    header("Location: plantios_ativos.php");
    exit;
}

// Verificar se o plantio existe
$plantio_res = mysqli_query($conn, "SELECT p.*, c.nome_cultura FROM plantios p JOIN culturas c ON p.id_cultura = c.id_cultura WHERE p.id_plantio = $id_plantio AND p.colhido = 0");
if (!$plantio_res || mysqli_num_rows($plantio_res) == 0) {
    header("Location: plantios_ativos.php?erro=plantio_invalido");
    exit;
}
$plantio = mysqli_fetch_assoc($plantio_res);
$nome_cultura = htmlspecialchars($plantio['nome_cultura']);

// Buscar adubos do estoque
$adubos_res = mysqli_query($conn, "SELECT * FROM estoque WHERE categoria = 'Adubo' ORDER BY nome_item ASC");
$adubos = [];
if ($adubos_res) {
    while ($row = mysqli_fetch_assoc($adubos_res)) {
        $adubos[] = $row;
    }
}

// Processar formulário de adubação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (e_visitante()) {
        $msg_erro = "Acesso negado! Visitantes não podem realizar adubação.";
    } else {
        $id_adubo   = intval($_POST['id_adubo'] ?? 0);
        $quantidade = floatval($_POST['quantidade'] ?? 0);
        $data_cuidado = mysqli_real_escape_string($conn, $_POST['data_cuidado'] ?? date('Y-m-d'));
        $obs = mysqli_real_escape_string($conn, $_POST['observacoes'] ?? '');

        if ($id_adubo <= 0) {
            $msg_erro = "Selecione um adubo válido.";
        } elseif ($quantidade <= 0) {
            $msg_erro = "Informe uma quantidade válida maior que zero.";
        } else {
            // Verificar estoque disponível
            $estoque_res = mysqli_query($conn, "SELECT quantidade, unidade_medida, nome_item, nivel_alerta FROM estoque WHERE id_item = $id_adubo");
            $item_estoque = mysqli_fetch_assoc($estoque_res);

            if (!$item_estoque) {
                $msg_erro = "Adubo não encontrado no estoque.";
            } elseif ($item_estoque['quantidade'] < $quantidade) {
                $msg_erro = "Quantidade insuficiente no estoque! Disponível: " . number_format($item_estoque['quantidade'], 2, ',', '.') . " " . $item_estoque['unidade_medida'];
            } else {
                mysqli_begin_transaction($conn);

                // 1. Registrar o cuidado na tabela cuidados_plantio
                $ins_cuidado = "INSERT INTO cuidados_plantio (irrigar, adubar, data_cuidado, id_plantio) 
                                VALUES ('não', '" . $item_estoque['nome_item'] . "', '$data_cuidado', $id_plantio)";

                // 2. Descontar do estoque
                $nova_qtd    = $item_estoque['quantidade'] - $quantidade;
                $novo_status = ($nova_qtd <= $item_estoque['nivel_alerta']) ? 'Alerta' : 'Normal';
                $upd_estoque = "UPDATE estoque SET quantidade = $nova_qtd, status_estoque = '$novo_status' WHERE id_item = $id_adubo";

                if (mysqli_query($conn, $ins_cuidado) && mysqli_query($conn, $upd_estoque)) {
                    mysqli_commit($conn);
                    $msg_sucesso = "Adubação registrada com sucesso! " . number_format($quantidade, 2, ',', '.') . " " . $item_estoque['unidade_medida'] . " de \"" . htmlspecialchars($item_estoque['nome_item']) . "\" aplicados.";
                    // Recarregar adubos para refletir nova quantidade
                    $adubos_res2 = mysqli_query($conn, "SELECT * FROM estoque WHERE categoria = 'Adubo' ORDER BY nome_item ASC");
                    $adubos = [];
                    if ($adubos_res2) {
                        while ($row = mysqli_fetch_assoc($adubos_res2)) {
                            $adubos[] = $row;
                        }
                    }
                } else {
                    mysqli_rollback($conn);
                    $msg_erro = "Erro ao registrar adubação: " . mysqli_error($conn);
                }
            }
        }
    }
}

// Histórico de adubações deste plantio
$historico_res = mysqli_query($conn, "SELECT * FROM cuidados_plantio WHERE id_plantio = $id_plantio AND adubar != 'não' ORDER BY data_cuidado DESC LIMIT 10");
$historico_adubo = [];
if ($historico_res) {
    while ($row = mysqli_fetch_assoc($historico_res)) {
        $historico_adubo[] = $row;
    }
}

$activePage = 'plantios'; // Mantém plantios ativo no menu
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroGestão - Adubação de Plantio</title>
    
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
                <div class="topbar-title">
                    <a href="plantios_ativos.php" style="color: var(--text-gray); text-decoration: none; font-size: 13px; font-weight: 500;">
                        <i class="fa-solid fa-arrow-left" style="margin-right: 5px;"></i>Plantios Ativos
                    </a>
                    <span style="color: var(--text-gray); margin: 0 8px;">/</span>
                    Adubação
                </div>
            </header>

            <main class="main-content">
                <div class="content-wrapper">

                    <!-- CABEÇALHO DA PÁGINA -->
                    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px; padding: 20px; background: linear-gradient(135deg, #064e3b 0%, #065f46 100%); border-radius: 16px; color: white;">
                        <div style="width: 56px; height: 56px; border-radius: 14px; background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; font-size: 26px;">🌿</div>
                        <div>
                            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.7; margin-bottom: 4px;">Aplicar Adubo</div>
                            <div style="font-size: 20px; font-weight: 900;"><?php echo $nome_cultura; ?></div>
                            <div style="font-size: 12px; opacity: 0.8; margin-top: 2px;">
                                <i class="fa-solid fa-location-dot" style="margin-right: 4px;"></i>
                                <?php echo htmlspecialchars($plantio['local_canteiro']); ?>
                                &nbsp;•&nbsp; Plantio #<?php echo $id_plantio; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (e_visitante()): ?>
                        <div style="background-color: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <span>Modo de Leitura: Como visitante, você não pode realizar adubações.</span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($msg_erro)): ?>
                        <div style="background-color: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <?php echo htmlspecialchars($msg_erro); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($msg_sucesso)): ?>
                        <div style="background-color: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                            <i class="fa-solid fa-circle-check"></i>
                            <?php echo $msg_sucesso; ?>
                        </div>
                    <?php endif; ?>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">

                        <!-- FORMULÁRIO DE ADUBAÇÃO -->
                        <div>
                            <div style="font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-gray); margin-bottom: 14px;">
                                <i class="fa-solid fa-flask-vial" style="margin-right: 6px; color: var(--primary-green);"></i>Registrar Adubação
                            </div>

                            <?php if (!e_visitante()): ?>
                            <form method="POST" action="adubar_plantio.php?id=<?php echo $id_plantio; ?>&nome=<?php echo urlencode($plantio['nome_cultura']); ?>">
                                <input type="hidden" name="id_plantio" value="<?php echo $id_plantio; ?>">
                                
                                <!-- Selecionar Adubo -->
                                <div class="field-card" style="margin-bottom: 16px;">
                                    <label>Adubo do Estoque</label>
                                    <?php if (count($adubos) === 0): ?>
                                        <div style="color: var(--text-gray); font-size: 13px; margin-top: 8px;">
                                            <i class="fa-solid fa-triangle-exclamation" style="color: #f59e0b;"></i>
                                            Nenhum adubo disponível no estoque.
                                            <a href="cadastro_insumos.php" style="color: var(--primary-green); font-weight: 700; margin-left: 4px;">Adicionar →</a>
                                        </div>
                                    <?php else: ?>
                                        <select name="id_adubo" class="form-select" required>
                                            <option value="">— Selecione o adubo —</option>
                                            <?php foreach ($adubos as $ad): ?>
                                                <option value="<?php echo $ad['id_item']; ?>">
                                                    <?php echo htmlspecialchars($ad['nome_item']); ?> 
                                                    (<?php echo number_format($ad['quantidade'], 2, ',', '.'); ?> <?php echo htmlspecialchars($ad['unidade_medida']); ?> disponíveis)
                                                    <?php echo $ad['status_estoque'] === 'Alerta' ? '⚠️' : ''; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>

                                <!-- Quantidade Utilizada -->
                                <div class="field-card" style="margin-bottom: 16px;">
                                    <label>Quantidade Utilizada</label>
                                    <input type="number" name="quantidade" class="form-input" placeholder="Ex: 2.5" step="0.01" min="0.01" required>
                                </div>

                                <!-- Data de Aplicação -->
                                <div class="field-card" style="margin-bottom: 16px;">
                                    <label>Data de Aplicação</label>
                                    <input type="date" name="data_cuidado" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>

                                <!-- Observações -->
                                <div class="field-card" style="margin-bottom: 20px;">
                                    <label>Observações (opcional)</label>
                                    <textarea name="observacoes" class="form-textarea" placeholder="Ex: Adubação foliar aplicada ao entardecer..."></textarea>
                                </div>

                                <?php if (count($adubos) > 0): ?>
                                    <button type="submit" class="btn-submit" style="background: linear-gradient(135deg, #064e3b, #059669);">
                                        <i class="fa-solid fa-leaf" style="margin-right: 8px;"></i>Registrar Adubação
                                    </button>
                                <?php endif; ?>
                            </form>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px 20px; color: var(--text-gray); background: var(--card-bg); border-radius: 12px; border: 1px dashed var(--border-color);">
                                    <i class="fa-solid fa-eye" style="font-size: 28px; margin-bottom: 10px; display: block;"></i>
                                    Apenas administradores podem registrar adubações.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- PAINEL LATERAL: ESTOQUE DE ADUBOS + HISTÓRICO -->
                        <div>
                            <!-- Estoque de Adubos -->
                            <div style="font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-gray); margin-bottom: 14px;">
                                <i class="fa-solid fa-box" style="margin-right: 6px; color: var(--primary-green);"></i>Adubos no Estoque
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 24px;">
                                <?php if (count($adubos) === 0): ?>
                                    <div class="empty-state" style="padding: 20px;">Nenhum adubo no estoque.</div>
                                <?php else: ?>
                                    <?php foreach ($adubos as $ad): 
                                        $pct = ($ad['nivel_alerta'] > 0) ? min(100, round(($ad['quantidade'] / max($ad['quantidade'], $ad['nivel_alerta'] * 3)) * 100)) : 100;
                                        $bar_color = $ad['status_estoque'] === 'Alerta' ? '#ef4444' : '#10b981';
                                    ?>
                                    <div style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 14px 16px; <?php echo $ad['status_estoque'] === 'Alerta' ? 'border-left: 3px solid #ef4444;' : ''; ?>">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <span style="font-weight: 700; font-size: 14px; color: var(--text-main);">
                                                🪨 <?php echo htmlspecialchars($ad['nome_item']); ?>
                                            </span>
                                            <?php if ($ad['status_estoque'] === 'Alerta'): ?>
                                                <span style="font-size: 10px; font-weight: 800; background: #fee2e2; color: #ef4444; padding: 2px 8px; border-radius: 20px;">ALERTA</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 13px; color: var(--text-gray); margin-bottom: 8px;">
                                            <?php echo number_format($ad['quantidade'], 2, ',', '.'); ?> <?php echo htmlspecialchars($ad['unidade_medida']); ?> disponíveis
                                        </div>
                                        <div style="background: var(--border-color); border-radius: 99px; height: 6px; overflow: hidden;">
                                            <div style="width: <?php echo $pct; ?>%; height: 100%; background: <?php echo $bar_color; ?>; border-radius: 99px; transition: width 0.4s;"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Histórico de Adubações deste Plantio -->
                            <div style="font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-gray); margin-bottom: 14px;">
                                <i class="fa-solid fa-clock-rotate-left" style="margin-right: 6px; color: var(--primary-green);"></i>Histórico de Adubação
                            </div>
                            <?php if (count($historico_adubo) === 0): ?>
                                <div class="empty-state" style="padding: 20px;">Nenhuma adubação registrada ainda para este plantio.</div>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <?php foreach ($historico_adubo as $h): ?>
                                    <div style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 10px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 36px; height: 36px; border-radius: 50%; background: #d1fae5; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;">🌿</div>
                                        <div>
                                            <div style="font-weight: 700; font-size: 13px; color: var(--text-main);"><?php echo htmlspecialchars($h['adubar']); ?></div>
                                            <div style="font-size: 11px; color: var(--text-gray); margin-top: 2px;">
                                                <i class="fa-solid fa-calendar" style="margin-right: 4px;"></i>
                                                <?php echo date('d/m/Y', strtotime($h['data_cuidado'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div><!-- /grid -->

                </div>
            </main>
        </div>
    </div>

    <style>
        @media (max-width: 768px) {
            .content-wrapper > div[style*="grid-template-columns: 1fr 1fr"] {
                grid-template-columns: 1fr !important;
            }
        }
        .topbar-title {
            display: flex;
            align-items: center;
            font-size: 16px;
            font-weight: 700;
        }
    </style>

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
