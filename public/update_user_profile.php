<?php
// public/admin/actions/update_user_profile.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php?page=dashboard");
    exit();
}

global $pdo;

$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$redirect_to = $_POST['redirect_to'] ?? '../index.php?page=usuarios';

if (!$user_id) {
    header("Location: " . $redirect_to . "&status=error&message=" . urlencode("ID de usuário inválido para atualização."));
    exit();
}

try {
    $update_fields = [];
    $update_values = [];

    // Flag para verificar se o nível foi alterado pelo admin
    $level_was_manually_updated = false;

    // --- Nome ---
    if (isset($_POST['name'])) {
        $name = trim($_POST['name']);
        if (!empty($name)) {
            $update_fields[] = 'name = ?';
            $update_values[] = $name;
        } else {
            header("Location: " . $redirect_to . "&status=error&message=" . urlencode("Nome não pode ser vazio."));
            exit();
        }
    }

    // --- Email ---
    if (isset($_POST['email'])) {
        $email = strtolower(trim($_POST['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("Location: " . $redirect_to . "&status=error&message=" . urlencode("Email inválido."));
            exit();
        }
        $stmt_check = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND id != ? LIMIT 1");
        $stmt_check->execute([$email, $user_id]);
        if ($existing_user = $stmt_check->fetch(PDO::FETCH_ASSOC)) {
            header("Location: " . $redirect_to . "&status=error&message=" . urlencode("Email já em uso pelo usuário ID " . $existing_user['id'] . " (" . $existing_user['name'] . ")."));
            exit();
        }
        $update_fields[] = 'email = ?';
        $update_values[] = $email;
    }

    // --- Telefone ---
    if (isset($_POST['phone'])) {
        $phone = trim($_POST['phone']);
        if (!empty($phone)) {
            $stmt_check = $pdo->prepare("SELECT id, name FROM users WHERE phone = ? AND id != ? LIMIT 1");
            $stmt_check->execute([$phone, $user_id]);
            if ($existing_user = $stmt_check->fetch(PDO::FETCH_ASSOC)) {
                header("Location: " . $redirect_to . "&status=error&message=" . urlencode("Telefone já em uso pelo usuário ID " . $existing_user['id'] . " (" . $existing_user['name'] . ")."));
                exit();
            }
        }
        $update_fields[] = 'phone = ?';
        $update_values[] = empty($phone) ? null : $phone;
    }

    // --- CPF/Documento ---
    if (isset($_POST['document'])) {
        $document = trim($_POST['document']);
        if (!empty($document)) {
            $stmt_check = $pdo->prepare("SELECT id, name FROM users WHERE document = ? AND id != ? LIMIT 1");
            $stmt_check->execute([$document, $user_id]);
            if ($existing_user = $stmt_check->fetch(PDO::FETCH_ASSOC)) {
                header("Location: " . $redirect_to . "&status=error&message=" . urlencode("Documento/CPF já em uso pelo usuário ID " . $existing_user['id'] . " (" . $existing_user['name'] . ")."));
                exit();
            }
        }
        $update_fields[] = 'document = ?';
        $update_values[] = empty($document) ? null : $document;
    }

    // --- XP ---
    if (isset($_POST['xp'])) {
        $xp = filter_input(INPUT_POST, 'xp', FILTER_VALIDATE_INT);
        if ($xp !== false && $xp >= 0) {
            $update_fields[] = 'xp = ?';
            $update_values[] = $xp;
        } else {
            header("Location: " . $redirect_to . "&status=error&message=" . urlencode("XP inválido: deve ser um número inteiro não negativo."));
            exit();
        }
    }

    // --- Nível (level_id) e Taxa de Comissão (commission_rate) ---
    if (isset($_POST['level_id'])) {
        $potential_new_level_id = filter_input(INPUT_POST, 'level_id', FILTER_VALIDATE_INT);
        if ($potential_new_level_id !== false && $potential_new_level_id > 0) {
            $stmt_commission_rate = $pdo->prepare("SELECT commission_rate FROM referral_levels WHERE id = ?");
            $stmt_commission_rate->execute([$potential_new_level_id]);
            $fetched_commission_rate = $stmt_commission_rate->fetchColumn();

            if ($fetched_commission_rate !== false) {
                $new_level_id = $potential_new_level_id;
                $new_commission_rate = $fetched_commission_rate;

                $update_fields[] = 'level_id = ?';
                $update_values[] = $new_level_id;

                $update_fields[] = 'commission_rate = ?';
                $update_values[] = $new_commission_rate;

                // NOVO: Define o campo de controle manual para TRUE
                $update_fields[] = 'is_level_manual_override = ?';
                $update_values[] = true; // Use true ou 1 para booleano
            } else {
                header("Location: " . $redirect_to . "&status=error&message=" . urlencode("Nível selecionado não encontrado na base de dados."));
                exit();
            }
        } else {
            header("Location: " . $redirect_to . "&status=error&message=" . urlencode("ID de nível inválido."));
            exit();
        }
    }

    // --- Saldo de Comissão ---
    if (isset($_POST['commission_balance'])) {
        $raw_commission_balance = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['commission_balance']);
        $newCommissionBalance = filter_var($raw_commission_balance, FILTER_VALIDATE_FLOAT);

        if ($newCommissionBalance !== false && $newCommissionBalance >= 0) {
            $update_fields[] = 'commission_balance = ?';
            $update_values[] = $newCommissionBalance;
        } else {
            header("Location: " . $redirect_to . "&status=error&message=" . urlencode("Saldo de comissão inválido."));
            exit();
        }
    }

    // --- Saldo Principal (Adicionado da sua lógica original) ---
    if (isset($_POST['update_balance'])) {
        $raw_saldo = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['saldo_masked']);
        $newBalance = filter_var($raw_saldo, FILTER_VALIDATE_FLOAT);

        if ($newBalance !== false && $newBalance >= 0) {
            $update_fields[] = 'saldo = ?';
            $update_values[] = $newBalance;
        } else {
            header("Location: " . $redirect_to . "&status=error&message=" . urlencode("Erro ao atualizar saldo: valor inválido."));
            exit;
        }
    }

    // --- Saldo de Comissão (Adicionar) ---
    if (isset($_POST['add_commission_balance'])) {
        $raw_commission_amount = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['commission_amount_masked']);
        $commissionAmount = filter_var($raw_commission_amount, FILTER_VALIDATE_FLOAT);

        if ($commissionAmount !== false && $commissionAmount > 0) {
            $update_fields[] = 'commission_balance = commission_balance + ?';
            $update_values[] = $commissionAmount;
        } else {
            header("Location: " . $redirect_to . "&status=error&message=" . urlencode("Erro ao adicionar saldo de comissão: valor inválido."));
            exit;
        }
    }


    if (empty($update_fields)) {
        header("Location: " . $redirect_to . "&status=error&message=" . urlencode("Nenhum campo para atualizar."));
        exit();
    }

    $update_fields[] = 'updated_at = NOW()';
    $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $update_values[] = $user_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($update_values);

    header("Location: " . $redirect_to . "&status=success&message=" . urlencode("Perfil do usuário atualizado com sucesso."));
    exit();

} catch (PDOException $e) {
    $error_message = "Erro no banco de dados: " . $e->getMessage();
    error_log("Erro ao atualizar perfil do usuário: " . $e->getMessage());
    header("Location: " . $redirect_to . "&status=error&message=" . urlencode($error_message));
    exit();
} catch (Exception $e) {
    error_log("Erro inesperado ao atualizar perfil do usuário: " . $e->getMessage());
    header("Location: " . $redirect_to . "&status=error&message=" . urlencode("Erro inesperado ao atualizar."));
    exit();
}