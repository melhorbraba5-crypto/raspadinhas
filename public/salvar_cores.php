<?php
// public/salvar_cores.php
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['admin_user'])) {
    header('Location: ../admin_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=aparencia');
    exit;
}

// Lista de todas as configurações para salvar
$configuracoes_para_salvar = [
    'cor-primary'   => $_POST['cor-primary'] ?? null,
    'cor-secondary' => $_POST['cor-secondary'] ?? null,
    'cor-tertiary'  => $_POST['cor-tertiary'] ?? null,
    'site-logo-url' => $_POST['site-logo-url'] ?? null,
];

// Removendo entradas nulas (opcional, mas boa prática)
$configuracoes_para_salvar = array_filter($configuracoes_para_salvar, fn($v) => $v !== null);

try {
    global $pdo;
    $pdo->beginTransaction();

    $sql = "INSERT INTO configuracoes_tema (nome_config, valor_config) VALUES (:nome, :valor)
            ON CONFLICT (nome_config)
            DO UPDATE SET valor_config = EXCLUDED.valor_config";

    $stmt = $pdo->prepare($sql);

    foreach ($configuracoes_para_salvar as $nome => $valor) {
        $stmt->execute([':nome' => $nome, ':valor' => $valor]);
    }

    $pdo->commit();
    $_SESSION['form_feedback'] = ['type' => 'success', 'message' => 'Configurações de aparência atualizadas com sucesso!'];

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'Erro ao salvar as configurações.'];
}

header('Location: index.php?page=aparencia');
exit;