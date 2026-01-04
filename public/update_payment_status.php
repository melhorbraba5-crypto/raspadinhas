<?php
// api/admin/update_payment_status.php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não permitido.']);
    exit;
}

global $pdo;

$raw_input = file_get_contents('php://input');

// Log de depuração para ver a entrada bruta (opcional, pode ser removido depois)
error_log("RAW INPUT BODY em update_payment_status.php: " . $raw_input);

$input = json_decode($raw_input, true);

// ### CORREÇÃO DEFINITIVA: VALIDAÇÃO EXPLÍCITA E ROBUSTA ###
// A principal mudança está aqui.
// Verificamos se as chaves existem e se o tipo de dado é o esperado.

// 1. Valida o ID do método
if (!isset($input['method_id']) || !is_numeric($input['method_id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['ok' => false, 'error' => 'ID do método é inválido ou está ausente.']);
    exit;
}

// 2. Valida o status de ativação
// is_bool() é a verificação mais segura para um campo booleano JSON
if (!isset($input['is_active']) || !is_bool($input['is_active'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['ok' => false, 'error' => 'Status de ativação é inválido ou está ausente.']);
    exit;
}

// Se a validação passou, atribuímos os valores de forma segura
$method_id = (int)$input['method_id'];
$is_active_bool = $input['is_active'];
// ### FIM DA CORREÇÃO ###

try {
    // A consulta é a mesma, mas agora com dados validados e seguros
    $stmt = $pdo->prepare("UPDATE payment_methods SET is_active = ? WHERE id = ?");
    $stmt->execute([$is_active_bool, $method_id]);

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'Status atualizado com sucesso.']);
    } else {
        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'Nenhuma alteração necessária.']);
    }

} catch (Exception $e) {
    error_log("Erro em update_payment_status.php: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['ok' => false, 'error' => 'Erro interno do servidor.']);
}