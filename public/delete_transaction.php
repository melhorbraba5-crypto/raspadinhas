<?php
// public/admin/actions/delete_transaction.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

// Redireciona para o dashboard se não for uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php?page=dashboard");
    exit();
}

// Garante que o objeto PDO está disponível
global $pdo;

$action = $_POST['action'] ?? '';
$user_id = $_POST['user_id'] ?? null;
$transaction_id = $_POST['transaction_id'] ?? null;
$redirect_to = $_POST['redirect_to'] ?? '../index.php?page=financeiro'; // URL para redirecionar após a ação

// Validação básica dos IDs
if (!is_numeric($user_id)) {
    // Redireciona com uma mensagem de erro, se necessário
    header("Location: " . $redirect_to . "&error=invalid_user_id");
    exit();
}

try {
    if ($action === 'delete_single_deposit' && is_numeric($transaction_id)) {
        // Excluir um único depósito (transação)
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ? AND amount > 0"); // Apenas depósitos
        $stmt->execute([$transaction_id, $user_id]);
        
        // Opcional: Atualizar o saldo do usuário aqui se você não tiver triggers/lógica separada para isso
        // Isso é CRÍTICO para a consistência dos dados financeiros.
        // Se `amount` for o valor do depósito, você precisaria subtraí-lo do `total_deposited`
        // e do `saldo` do usuário.
        // Exemplo (SIMPLIFICADO, você precisa da lógica completa de atualização de saldo):
        // $old_amount_stmt = $pdo->prepare("SELECT amount FROM transactions WHERE id = ?");
        // $old_amount_stmt->execute([$transaction_id]);
        // $old_amount = $old_amount_stmt->fetchColumn();
        // $update_user_stmt = $pdo->prepare("UPDATE users SET saldo = saldo - ?, total_deposited = total_deposited - ? WHERE id = ?");
        // $update_user_stmt->execute([$old_amount, $old_amount, $user_id]);

        header("Location: " . $redirect_to . "&success=deposit_deleted");
        exit();

    } elseif ($action === 'delete_all_deposits_from_user') {
        // Excluir TODOS os depósitos (transações) de um usuário
        // **ATENÇÃO: Isso é uma ação DESTRUTIVA!**
        // Considere o impacto financeiro e de relatórios antes de usar em produção.
        
        // Opcional: Obter o total dos depósitos para subtrair do saldo do usuário ANTES de deletar
        // $total_deposits_to_delete_stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND amount > 0 AND status = 'APPROVED'");
        // $total_deposits_to_delete_stmt->execute([$user_id]);
        // $total_to_subtract = $total_deposits_to_delete_stmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM transactions WHERE user_id = ? AND amount > 0"); // Apenas depósitos
        $stmt->execute([$user_id]);

        // Opcional: Atualizar saldo do usuário
        // $update_user_stmt = $pdo->prepare("UPDATE users SET saldo = saldo - ?, total_deposited = total_deposited - ? WHERE id = ?");
        // $update_user_stmt->execute([$total_to_subtract, $total_to_subtract, $user_id]);


        header("Location: " . $redirect_to . "&success=all_deposits_deleted");
        exit();

    } else {
        header("Location: " . $redirect_to . "&error=invalid_action");
        exit();
    }

} catch (PDOException $e) {
    // Em caso de erro do BD durante a exclusão
    error_log("Erro ao deletar transação: " . $e->getMessage()); // Registra o erro no log
    header("Location: " . $redirect_to . "&error=db_error");
    exit();
}
?>