<?php
/*
admin@gmail.com
12345
*/

$servidor = "localhost";
$usuario = "root";
$senha = "";
$banco = "producao_vegetal";
$porta = 3306;

$conn = new mysqli($servidor, $usuario, $senha, $banco, $porta);

if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>