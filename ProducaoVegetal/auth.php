<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado. Se não, redireciona para a página de login
function verificar_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }
}

// Retorna as iniciais do usuário logado (ex: "João Silva" -> "JS")
function obter_iniciais($nome) {
    $nomes = explode(' ', trim($nome));
    $iniciais = 'U';
    if (count($nomes) > 1) {
        $iniciais = strtoupper($nomes[0][0] . $nomes[count($nomes) - 1][0]);
    } else if (!empty($nomes[0])) {
        $iniciais = strtoupper(substr($nomes[0], 0, 2));
    }
    return $iniciais;
}

// Verifica se o usuário logado é administrador
function e_admin() {
    return isset($_SESSION['user_perfil']) && $_SESSION['user_perfil'] === 'admin';
}

// Verifica se o usuário logado é visitante
function e_visitante() {
    return isset($_SESSION['user_perfil']) && $_SESSION['user_perfil'] === 'visitante';
}

// Bloqueia o acesso se for visitante (ex: para ações de edição/exclusão)
function restringir_visitante() {
    if (e_visitante()) {
        header("Content-Type: application/json");
        echo json_encode(["status" => "error", "message" => "Acesso restrito para visitantes!"]);
        exit;
    }
}

// Bloqueia acesso de página inteira se for visitante (redireciona para index/dashboard)
function restringir_pagina_visitante() {
    if (e_visitante()) {
        header("Location: dashboard.php?erro=restrito");
        exit;
    }
}

// Restringe acesso a apenas admins (redireciona dashboard se não for admin)
function restringir_pagina_admin() {
    if (!e_admin()) {
        header("Location: dashboard.php?erro=restrito");
        exit;
    }
}

// Verifica se o usuário logado é operador
function e_operador() {
    return isset($_SESSION['user_perfil']) && $_SESSION['user_perfil'] === 'operador';
}

// Registra uma operação na tabela de auditoria (log)
function registrar_log(string $operacao): void {
    global $conn;
    if (!isset($conn) || !$conn || !isset($_SESSION['user_id'])) return;
    $id_usuario = intval($_SESSION['user_id']);
    $operacao   = substr(trim($operacao), 0, 100);
    $stmt = $conn->prepare("INSERT INTO log (operacao, id_usuario) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param("si", $operacao, $id_usuario);
        $stmt->execute();
    }
}
?>
