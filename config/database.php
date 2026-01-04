<?php
// config/database.php
date_default_timezone_set('America/Sao_Paulo');

// 1. Tenta obter a DATABASE_URL do ambiente (como na Heroku)
$dbUrl = getenv('DATABASE_URL');

// Se não encontrar (desenvolvimento local), usa um valor fixo de um arquivo .env ou define manualmente
if ($dbUrl === false) {
    // Para desenvolvimento local, você pode definir a URL aqui diretamente
    // Ex: $dbUrl = "postgres://user:password@host:port/dbname";
    // A melhor prática é usar um arquivo .env (ver explicação abaixo)
    die("A variável de ambiente DATABASE_URL não foi encontrada. Configure para desenvolvimento local.");
}

// 2. "Quebra" a URL nos seus componentes
$dbopts = parse_url($dbUrl);

// 3. Extrai cada parte da conexão
$host = $dbopts['host'];
$port = $parts['port'] ?? '5432'; // Usa a porta padrão 5432 se a URL não especificar
$username = $dbopts['user'];
$password = $dbopts['pass'];
// O nome do banco de dados vem no 'path', mas com uma '/' no início. Removemos ela.
$db_name = ltrim($dbopts['path'], '/');

try {
    // 4. Monta a string de conexão (DSN) para o PDO e cria a conexão
    $dsn = "pgsql:host=$host;port=$port;dbname=$db_name";
    $pdo = new PDO($dsn, $username, $password);

    // Configura o PDO para lançar exceções em caso de erro
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e) {
    // Encerra a execução e mostra uma mensagem de erro clara
    die("ERRO: Não foi possível conectar ao banco de dados. " . $e->getMessage());
}