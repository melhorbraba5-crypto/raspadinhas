<?php
// public/admin_process_withdrawal.php
// API para o administrador processar (aprovar/rejeitar) solicitações de saque

// Inicia a sessão (se já não estiver iniciada globalmente)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verifica se é um admin logado
if (!isset($_SESSION['admin_user'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado. Somente administradores.']);
    exit;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Considere restringir em produção
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ***** CORREÇÃO FINAL PARA A CONEXÃO DO DB DO ADMIN *****
// Conforme a estrutura do seu admin, o db do admin é acessado via:
require_once __DIR__ . '/../config/database.php';
// E auth_check.php não é necessário aqui, pois já verificamos $_SESSION['admin_user']
// require_once __DIR__ . '/../includes/auth_check.php'; // REMOVIDO: Não necessário aqui

date_default_timezone_set('America/Sao_Paulo');

$response = ['success' => false, 'message' => 'Erro desconhecido.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $withdrawal_id = filter_var($data['withdrawal_id'] ?? null, FILTER_VALIDATE_INT);
    $user_id = filter_var($data['user_id'] ?? null, FILTER_VALIDATE_INT);
    $amount = filter_var($data['amount'] ?? null, FILTER_VALIDATE_FLOAT);
    $action = $data['action'] ?? '';
    $rejection_reason = $data['rejection_reason'] ?? null;

    if (!$withdrawal_id || !$user_id || $amount === false || !in_array($action, ['approve_withdrawal', 'reject_withdrawal'])) {
        $response['message'] = 'Dados inválidos para processar o saque.';
        echo json_encode($response);
        exit;
    }

    // ***** AQUI ESTÁ A CHAVE: Obter a variável $pdo que config/database.php deve definir *****
    // Se config/database.php define $pdo diretamente (ex: $pdo = new PDO(...);) sem 'global' ou 'return',
    // precisamos declará-la como global aqui para acessá-la.
    // Se config/database.php já tem 'global $pdo;' ou retorna a conexão, esta linha está ok.
    global $pdo;
    // Se config/database.php RETORNA a conexão, você PRECISA mudar para:
    // $pdo = require_once __DIR__ . '/../config/database.php';
    // Mas, dado que outros scripts do admin funcionam com 'global $pdo;', vamos manter essa premissa.


    try {
        $pdo->beginTransaction();

        $current_withdrawal_stmt = $pdo->prepare("SELECT status FROM withdrawals WHERE id = ?");
        $current_withdrawal_stmt->execute([$withdrawal_id]);
        $current_status = $current_withdrawal_stmt->fetchColumn();

        if ($current_status !== 'PENDING') {
            $pdo->rollBack();
            $response['message'] = "Saque ID {$withdrawal_id} já foi processado (Status atual: {$current_status}).";
            echo json_encode($response);
            exit;
        }

        if ($action === 'approve_withdrawal') {
            $update_withdrawal_stmt = $pdo->prepare("UPDATE withdrawals SET status = 'APPROVED', processed_at = NOW() WHERE id = ?");
            $update_withdrawal_stmt->execute([$withdrawal_id]);

            $update_transaction_stmt = $pdo->prepare("UPDATE transactions SET status = 'APPROVED', updated_at = NOW() WHERE user_id = ? AND withdrawal_id = ?");
            $update_transaction_stmt->execute([$user_id, $withdrawal_id]);

            $response['success'] = true;
            $response['message'] = "Saque ID {$withdrawal_id} aprovado com sucesso.";

        } elseif ($action === 'reject_withdrawal') {
            $sanitized_rejection_reason = htmlspecialchars(trim($rejection_reason));

            if (empty($sanitized_rejection_reason)) {
                $pdo->rollBack();
                $response['message'] = 'Motivo da rejeição é obrigatório.';
                echo json_encode($response);
                exit;
            }

            $update_withdrawal_stmt = $pdo->prepare("UPDATE withdrawals SET status = 'REJECTED', processed_at = NOW(), rejection_reason = ? WHERE id = ?");
            $update_withdrawal_stmt->execute([$sanitized_rejection_reason, $withdrawal_id]);

            // Usar o operador de concatenação '||' do PostgreSQL e fazer um CAST explícito para TEXT no parâmetro.
            $update_transaction_stmt = $pdo->prepare("UPDATE transactions SET status = 'REJECTED', updated_at = NOW(), description = description || ' (Motivo da Rejeição: ' || CAST(? AS TEXT) || ')' WHERE user_id = ? AND withdrawal_id = ?");
            $update_transaction_stmt->execute([$sanitized_rejection_reason, $user_id, $withdrawal_id]);

            // Estornar o valor para o saldo do usuário
            $revert_balance_stmt = $pdo->prepare("UPDATE users SET saldo = saldo + ?, updated_at = NOW() WHERE id = ?");
            $revert_balance_stmt->execute([$amount, $user_id]);

            $response['success'] = true;
            $response['message'] = "Saque ID {$withdrawal_id} rejeitado com sucesso. Valor estornado.";
        }

        $pdo->commit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao processar saque admin (ID: {$withdrawal_id}, Ação: {$action}): " . $e->getMessage());
        $response['message'] = 'Erro interno do servidor ao processar o saque. Tente novamente mais tarde.';
    }
} else {
    http_response_code(405);
    $response['message'] = 'Método não permitido.';
}

echo json_encode($response);