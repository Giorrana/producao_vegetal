<?php
mysqli_report(MYSQLI_REPORT_OFF);

$servidor = "localhost";
$usuario = "root";
$senha = "";
$banco = "producao_vegetal";
$porta = 3306; 

// Conectar primeiro sem banco para certificar que o banco existe
$conexao = mysqli_connect($servidor, $usuario, $senha, "", $porta);

if (!$conexao) {
    die("Erro na conexão com o servidor MySQL: " . mysqli_connect_error());
}

// Criar o banco de dados se não existir
$sql_db = "CREATE DATABASE IF NOT EXISTS `$banco` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if (!mysqli_query($conexao, $sql_db)) {
    die("Erro ao criar banco de dados: " . mysqli_error($conexao));
}

// Selecionar o banco
if (!mysqli_select_db($conexao, $banco)) {
    die("Erro ao selecionar o banco de dados: " . mysqli_error($conexao));
}

// Verificar se a tabela 'usuarios' existe. Se não, rodar o esquema.sql
$table_check = mysqli_query($conexao, "SHOW TABLES LIKE 'usuarios'");
if (!$table_check || mysqli_num_rows($table_check) == 0) {
    $sql_file = dirname(__FILE__) . '/esquema.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        
        // Remover comentários
        $sql_content = preg_replace('/--.*\n/', '', $sql_content);
        $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
        
        // Dividir por ponto e vírgula
        $queries = explode(';', $sql_content);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query) && strpos($query, 'CREATE DATABASE') === false && strpos($query, 'USE ') === false) {
                if (!mysqli_query($conexao, $query)) {
                    $errno = mysqli_errno($conexao);
                    $error = mysqli_error($conexao);
                    
                    // Se o erro for Tablespace exists (1813), tentar auto-correção
                    if ($errno == 1813 && preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $query, $matches)) {
                        $table_name = $matches[1];
                        
                        // Tentar descartar tablespace via SQL primeiro
                        mysqli_query($conexao, "ALTER TABLE `$table_name` DISCARD TABLESPACE");
                        
                        // Tentar remover o arquivo físico .ibd no diretório de dados do MySQL
                        $datadir_res = mysqli_query($conexao, "SHOW VARIABLES LIKE 'datadir'");
                        if ($datadir_res && $datadir_row = mysqli_fetch_assoc($datadir_res)) {
                            $datadir = rtrim(str_replace('\\', '/', $datadir_row['Value']), '/');
                            $ibd_file = $datadir . '/' . $banco . '/' . $table_name . '.ibd';
                            if (file_exists($ibd_file)) {
                                @unlink($ibd_file);
                            }
                        }
                        
                        // Tentar rodar a criação da tabela novamente
                        if (mysqli_query($conexao, $query)) {
                            continue; // Sucesso na segunda tentativa!
                        }
                    }
                    
                    error_log("Erro na query de importação: " . mysqli_error($conexao));
                }
            }
        }
    }
}

// Garantir que as colunas adicionais existam (caso a tabela já existisse antes da atualização do SQL)
$colunas_culturas = [
    'estacao_primavera' => "ALTER TABLE culturas ADD COLUMN estacao_primavera VARCHAR(255) NULL AFTER estacao_ano_ideal",
    'estacao_verao' => "ALTER TABLE culturas ADD COLUMN estacao_verao VARCHAR(255) NULL AFTER estacao_primavera",
    'estacao_outono' => "ALTER TABLE culturas ADD COLUMN estacao_outono VARCHAR(255) NULL AFTER estacao_verao",
    'estacao_inverno' => "ALTER TABLE culturas ADD COLUMN estacao_inverno VARCHAR(255) NULL AFTER estacao_outono"
];

foreach ($colunas_culturas as $coluna => $alter_query) {
    $check_col = mysqli_query($conexao, "SHOW COLUMNS FROM culturas LIKE '$coluna'");
    if (mysqli_num_rows($check_col) == 0) {
        mysqli_query($conexao, $alter_query);
    }
}

$colunas_plantios = [
    'irrigado'       => "ALTER TABLE plantios ADD COLUMN irrigado TINYINT(1) DEFAULT 0",
    'colhido'        => "ALTER TABLE plantios ADD COLUMN colhido TINYINT(1) DEFAULT 0",
    'dias_irrigados' => "ALTER TABLE plantios ADD COLUMN dias_irrigados INT DEFAULT 0"
];

foreach ($colunas_plantios as $coluna => $alter_query) {
    $check_col = mysqli_query($conexao, "SHOW COLUMNS FROM plantios LIKE '$coluna'");
    if (mysqli_num_rows($check_col) == 0) {
        mysqli_query($conexao, $alter_query);
    }
}

// Alimentar tabela categorias (Horta e Pomar)
$cat_check = mysqli_query($conexao, "SELECT * FROM categorias");
if (mysqli_num_rows($cat_check) == 0) {
    mysqli_query($conexao, "INSERT INTO categorias (id_categoria, nome_categoria) VALUES (1, 'Horta'), (2, 'Pomar')");
}

// Alimentar administrador default
$admin_check = mysqli_query($conexao, "SELECT * FROM usuarios WHERE email = 'admin@gmail.com'");
if (mysqli_num_rows($admin_check) == 0) {
    $senha_hash = password_hash("12345", PASSWORD_DEFAULT);
    $insert_admin = "INSERT INTO usuarios (nome, email, senha, perfil) VALUES ('Administrador', 'admin@gmail.com', '$senha_hash', 'admin')";
    if (!mysqli_query($conexao, $insert_admin)) {
        error_log("Erro ao criar conta administrador default: " . mysqli_error($conexao));
    }
}
?>
