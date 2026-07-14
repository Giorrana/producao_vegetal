<?php
header("Content-Type: application/json; charset=UTF-8");
require_once '../Banco/conexao.php';
require_once 'auth.php';

// Apenas usuários logados podem ver
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["erro" => "Não autorizado. Por favor, faça login."]);
    exit;
}

$id_usuario = intval($_SESSION['user_id']);
$tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';

switch ($tipo) {
    case 'produtividade_mensal':
        // Produtividade Mensal por mês para o usuário atual no ano corrente
        $mensal = array_fill(1, 12, 0);
        $q_mes = $conn->query("
            SELECT MONTH(c.data_colheita) as mes, SUM(c.quantidade_colhida) as total 
            FROM colheitas c 
            JOIN plantios p ON c.id_plantio = p.id_plantio 
            JOIN culturas cult ON p.id_cultura = cult.id_cultura 
            WHERE YEAR(c.data_colheita) = YEAR(CURDATE()) 
              AND cult.id_usuario = $id_usuario 
            GROUP BY MONTH(c.data_colheita)
        ");
        if ($q_mes) {
            while ($r = $q_mes->fetch_assoc()) {
                $mensal[intval($r['mes'])] = floatval($r['total']);
            }
        }
        echo json_encode(["status" => "sucesso", "dados" => $mensal]);
        break;

    case 'custo_manejo':
        // Custo acumulado por tipo de manejo para o usuário atual
        $q_custo = $conn->query("
            SELECT cp.tipo_manejo, SUM(cp.custo_calculado) as total_custo 
            FROM cuidados_plantio cp
            JOIN plantios p ON cp.id_plantio = p.id_plantio
            JOIN culturas cult ON p.id_cultura = cult.id_cultura
            WHERE cult.id_usuario = $id_usuario
            GROUP BY cp.tipo_manejo
            ORDER BY total_custo DESC
        ");
        $dados = [];
        if ($q_custo) {
            while ($row = $q_custo->fetch_assoc()) {
                $dados[$row['tipo_manejo']] = floatval($row['total_custo'] ?? 0);
            }
        }
        echo json_encode(["status" => "sucesso", "dados" => $dados]);
        break;

    case 'status_plantios':
        // Status atual dos plantios ativos do usuário
        $status_dist = ['Germinação' => 0, 'Crescimento' => 0, 'Floração' => 0, 'Pronto' => 0];
        $q_plant = $conn->query("
            SELECT p.data_plantio, c.tempo_medio_crescimento 
            FROM plantios p 
            JOIN culturas c ON p.id_cultura = c.id_cultura 
            WHERE p.colhido = 0 
              AND c.id_usuario = $id_usuario
        ");
        if ($q_plant) {
            while ($row = $q_plant->fetch_assoc()) {
                $dias_ciclo = intval($row['tempo_medio_crescimento']) ?: 90;
                $dap = max(0, (int)floor((time() - strtotime($row['data_plantio'])) / 86400));
                $pct = min(100, round(($dap / $dias_ciclo) * 100));
                if ($pct < 25)      $status_dist['Germinação']++;
                elseif ($pct < 60)  $status_dist['Crescimento']++;
                elseif ($pct < 90)  $status_dist['Floração']++;
                else                $status_dist['Pronto']++;
            }
        }
        echo json_encode(["status" => "sucesso", "dados" => $status_dist]);
        break;

    case 'ranking_culturas':
        // Ranking de culturas com mais volume colhido para o usuário
        $q_rank = $conn->query("
            SELECT c.nome_cultura, SUM(col.quantidade_colhida) as total_kg
            FROM colheitas col
            JOIN plantios p ON col.id_plantio = p.id_plantio
            JOIN culturas c ON p.id_cultura = c.id_cultura
            WHERE c.id_usuario = $id_usuario
            GROUP BY c.id_cultura
            ORDER BY total_kg DESC
            LIMIT 5
        ");
        $dados = [];
        if ($q_rank) {
            while ($row = $q_rank->fetch_assoc()) {
                $dados[] = [
                    "cultura" => $row['nome_cultura'],
                    "total_kg" => floatval($row['total_kg'])
                ];
            }
        }
        echo json_encode(["status" => "sucesso", "dados" => $dados]);
        break;

    default:
        http_response_code(400);
        echo json_encode(["erro" => "Tipo de relatório inválido ou não especificado."]);
        break;
}
?>
