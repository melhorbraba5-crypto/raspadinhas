<?php
// public/admin/actions/manage_user.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php?page=dashboard");
    exit();
}

global $pdo;

$action = $_POST['action'] ?? '';
$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$redirect_to = $_POST['redirect_to'] ?? '../index.php?page=usuarios';

if (!$user_id) {
    header("Location: " . $redirect_to . "&status=error&message=" . urlencode("ID de usuário inválido."));
    exit();
}

try {
    if ($action === 'delete_user') {
        // INÍCIO DA TRANSAÇÃO: Crucial para garantir que todas as exclusões aconteçam ou nenhuma aconteça.
        $pdo->beginTransaction();

        // EXCLUIR PRIMEIRO OS DADOS RELACIONADOS NAS TABELAS FILHAS
        // Ordem importa: Exclua de tabelas que referenciam 'users' antes de excluir de 'users'.
        // Usei os nomes de tabelas que vi nos seus esquemas: transactions, historicplay, commission_transactions.
        // Se você tiver outras tabelas que referenciam 'users.id', adicione-as aqui.

        // Excluir registros da tabela 'transactions' relacionados a este usuário
        $stmt_transactions = $pdo->prepare("DELETE FROM transactions WHERE user_id = ?");
        $stmt_transactions->execute([$user_id]);

        // Excluir registros da tabela 'historicplay' relacionados a este usuário
        $stmt_historicplay = $pdo->prepare("DELETE FROM historicplay WHERE user_id = ?");
        $stmt_historicplay->execute([$user_id]);

        // Excluir registros da tabela 'commission_transactions' relacionados a este usuário
        $stmt_commissions = $pdo->prepare("DELETE FROM commission_transactions WHERE user_id = ?");
        $stmt_commissions->execute([$user_id]);

        // Se você tiver uma tabela 'withdrawals', também precisaria excluir dela:
        // $stmt_withdrawals = $pdo->prepare("DELETE FROM withdrawals WHERE user_id = ?");
        // $stmt_withdrawals->execute([$user_id]);

        // Agora que todos os registros dependentes foram excluídos, pode excluir o usuário.
        $stmt_user = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt_user->execute([$user_id]);

        $pdo->commit(); // Confirma todas as exclusões
        header("Location: ../index.php?page=usuarios&status=success&message=" . urlencode("Usuário excluído com sucesso."));
        exit();

    } elseif ($action === 'toggle_block_user') {
        $current_status_string = $_POST['current_status'] ?? 'false';
        $current_blocked_status = ($current_status_string === 'true');
        $new_blocked_status = !$current_blocked_status;

        $value_for_db = $new_blocked_status ? 1 : 0; // Converte true para 1, false para 0

        $stmt = $pdo->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
        $stmt->execute([$value_for_db, $user_id]);

        $status_message = $new_blocked_status ? "bloqueado" : "desbloqueado";
        header("Location: " . $redirect_to . "&status=success&message=" . urlencode("Usuário " . $status_message . " com sucesso."));
        exit();

    } else {
        header("Location: " . $redirect_to . "&status=error&message=" . urlencode("Ação inválida."));
        exit();
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); // Reverte a transação em caso de erro
    }
    error_log("Erro no manage_user.php: " . $e->getMessage());
    header("Location: " . $redirect_to . "&status=error&message=" . urlencode("Erro no banco de dados: " . $e->getMessage()));
    exit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro inesperado no manage_user.php: " . $e->getMessage());
    header("Location: " . $redirect_to . "&status=error&message=" . urlencode("Erro inesperado: " . $e->getMessage()));
    exit();
}