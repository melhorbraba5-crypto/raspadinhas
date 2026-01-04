<?php
// public/manage_demo_account.php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

// Redireciona se a requisição não for POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /index.php?page=contasdemo");
    exit();
}

global $pdo;

// Captura e valida os dados do formulário
$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

// O redirect_to é sempre a página de contas demo
$redirect_to = '/index.php?page=contasdemo';

if ($user_id === false || empty($action)) {
    $_SESSION['form_feedback'] = [
        'type' => 'error',
        'message' => 'Ação ou ID de usuário inválidos.'
    ];
    header("Location: " . $redirect_to);
    exit();
}

try {
    $message = '';
    $success = false;

    switch ($action) {
        case 'update_win_rate':
            $new_win_rate = filter_input(INPUT_POST, 'new_win_rate', FILTER_VALIDATE_FLOAT);
            if ($new_win_rate === false || $new_win_rate < 0 || $new_win_rate > 100) {
                $message = 'Taxa de ganho inválida. Deve ser um número entre 0 e 100.';
                break;
            }
            $stmt = $pdo->prepare("UPDATE users SET demo_win_rate = ?, updated_at = NOW() WHERE id = ? AND is_demo = TRUE");
            $stmt->execute([$new_win_rate, $user_id]);
            $message = 'Taxa de ganho da conta demo atualizada com sucesso.';
            $success = $stmt->rowCount() > 0;
            break;

        case 'change_password':
            $new_password = $_POST['new_password'] ?? '';
            if (empty($new_password)) {
                $message = 'A nova senha não pode ser vazia.';
                break;
            }
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ? AND is_demo = TRUE");
            $stmt->execute([$hashed_password, $user_id]);
            $message = 'Senha da conta demo alterada com sucesso.';
            $success = $stmt->rowCount() > 0;
            break;

        case 'delete_account':
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_demo = TRUE");
            $stmt->execute([$user_id]);
            $message = 'Conta demo excluída com sucesso.';
            $success = $stmt->rowCount() > 0;
            break;

        case 'toggle_block':
            $is_blocked = filter_input(INPUT_POST, 'is_blocked', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($is_blocked === null) {
                 $message = 'Status de bloqueio inválido.';
                 break;
            }
            $value_for_db = $is_blocked ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE users SET is_blocked = ?, updated_at = NOW() WHERE id = ? AND is_demo = TRUE");
            $stmt->execute([$value_for_db, $user_id]);
            $message = $is_blocked ? 'Conta demo bloqueada com sucesso.' : 'Conta demo desbloqueada com sucesso.';
            $success = $stmt->rowCount() > 0;
            break;

        default:
            $message = 'Ação desconhecida.';
            break;
    }

    if ($success) {
        $_SESSION['form_feedback'] = ['type' => 'success', 'message' => $message];
    } else {
        $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'Nenhuma alteração foi realizada. ' . $message];
    }

} catch (PDOException $e) {
    error_log("Erro ao gerenciar conta demo: " . $e->getMessage());
    $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'Erro no banco de dados: ' . $e->getMessage()];
}

header("Location: " . $redirect_to);
exit();
?>