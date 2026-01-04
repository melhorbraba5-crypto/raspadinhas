<?php
// public/toggle_payment_method.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

// Redireciona se a requisição não for POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /index.php?page=gerenciarpagamentos");
    exit();
}

global $pdo;

// Captura e valida os dados
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$is_active_int = filter_input(INPUT_POST, 'is_active', FILTER_VALIDATE_INT);

// A validação agora checa se os valores são inteiros válidos
if ($id === false || ($is_active_int !== 0 && $is_active_int !== 1)) {
    $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'ID ou status de método de pagamento inválido.'];
    header("Location: /index.php?page=gerenciarpagamentos");
    exit();
}

try {
    // Prepara a consulta para atualizar o status. O PDO converte 0 e 1 para false e true.
    $stmt = $pdo->prepare("UPDATE payment_methods SET is_active = ? WHERE id = ?");
    $stmt->execute([$is_active_int, $id]);

    if ($stmt->rowCount() > 0) {
        $status_text = ($is_active_int === 1) ? 'ativado' : 'desativado';
        $_SESSION['form_feedback'] = ['type' => 'success', 'message' => "Método de pagamento {$status_text} com sucesso."];
    } else {
        $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'Nenhuma alteração foi realizada.'];
    }

} catch (PDOException $e) {
    error_log("Erro ao alterar status de pagamento: " . $e->getMessage());
    $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'Erro no banco de dados ao tentar atualizar o status.'];
}

// Redireciona de volta para a página de gerenciamento
header("Location: /index.php?page=gerenciarpagamentos");
exit();