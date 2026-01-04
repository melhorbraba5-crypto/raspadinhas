<?php
// public/manage_demo_account.php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /index.php?page=contasdemo");
    exit();
}

global $pdo;

// Captura os dados essenciais
$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? null;
$new_win_rate = filter_input(INPUT_POST, 'new_win_rate', FILTER_VALIDATE_FLOAT);

// Verifica se os dados básicos são válidos
if ($user_id === false) {
    $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'ID de usuário inválido.'];
    header("Location: /index.php?page=contasdemo");
    exit();
}

try {
    // Inicia a transação
    $pdo->beginTransaction();

    $message = '';
    $type = 'success';

    // Lógica para cada tipo de ação
    switch ($action) {
        case 'update_win_rate':
            if ($new_win_rate === false || $new_win_rate < 0 || $new_win_rate > 100) {
                throw new Exception('Valor da taxa de ganho é inválido.');
            }
            $stmt = $pdo->prepare("UPDATE users SET demo_win_rate = ?, updated_at = NOW() WHERE id = ? AND is_demo = TRUE");
            $stmt->execute([$new_win_rate, $user_id]);
            $message = 'Taxa de ganho da conta demo atualizada com sucesso.';
            break;

        case 'reset_password':
            $new_password = substr(bin2hex(random_bytes(6)), 0, 8);
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ? AND is_demo = TRUE");
            $stmt->execute([$password_hash, $user_id]);
            $message = "Nova senha gerada com sucesso! Senha: <span style='font-weight: bold;'>{$new_password}</span>";
            break;

        case 'toggle_block':
            $stmt = $pdo->prepare("SELECT is_blocked FROM users WHERE id = ? AND is_demo = TRUE");
            $stmt->execute([$user_id]);
            $current_status = $stmt->fetchColumn();

            if ($current_status === null) {
                throw new Exception('Conta demo não encontrada.');
            }
            $new_status = !$current_status;
            $status_text = $new_status ? 'bloqueada' : 'desbloqueada';

            $stmt_update = $pdo->prepare("UPDATE users SET is_blocked = ?, updated_at = NOW() WHERE id = ? AND is_demo = TRUE");
            $stmt_update->execute([$new_status, $user_id]);
            $message = "Conta demo foi $status_text com sucesso.";
            break;

        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_demo = TRUE");
            $stmt->execute([$user_id]);
            $message = 'Conta demo excluída com sucesso.';
            break;

        default:
            throw new Exception('Ação inválida.');
    }

    $pdo->commit();
    $_SESSION['form_feedback'] = ['type' => $type, 'message' => $message];

    header("Location: /index.php?page=contasdemo");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao gerenciar conta demo: " . $e->getMessage());
    $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'Erro: ' . $e->getMessage()];
    header("Location: /index.php?page=contasdemo");
    exit();
}