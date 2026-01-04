<?php
// 1. LÓGICA PHP PRINCIPAL E DO DASHBOARD (AGORA APENAS ROTEAMENTO)
// =================================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php'; // Inclua o PDO aqui para que as páginas incluídas possam usá-lo

// Obtém o nome de usuário da sessão
$admin_user = $_SESSION['admin_user'] ?? 'Admin';

// --- Roteamento Simples ---
$allowed_pages = ['dashboard', 'usuarios', 'financeiro', 'relatorios', 'contasdemo', 'gerenciarjogos', 'gerenciarpagamentos', 'aparencia'];
$page = $_GET['page'] ?? 'dashboard';

// Valida a página requisitada
if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard'; // Redireciona para o dashboard se a página for inválida
}

// Constrói o caminho para o arquivo da página
// Assumimos que as páginas estão no mesmo diretório de index.php
$page_to_include = __DIR__ . '/' . $page . '.php';

// Verifica se o arquivo da página existe antes de incluir
if (file_exists($page_to_include)) {
    // Inclui o arquivo da página.
    // O conteúdo da página (incluindo HTML, CSS, JS e lógica PHP) será renderizado aqui.
    include $page_to_include;
} else {
    // Caso o arquivo da página não seja encontrado (embora o in_array já previna isso para allowed_pages)
    // Você pode criar uma página de erro 404 dedicada se quiser.
    http_response_code(404);
    echo "<h1>404 - Página Não Encontrada</h1>";
    echo "<p>A página que você está procurando não existe ou não está disponível.</p>";
}
?>