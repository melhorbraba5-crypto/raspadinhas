<?php
// 1. LÓGICA PHP
// =================================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

// Apenas para fins de depuração. A conversão de fuso horário será feita no banco de dados.
date_default_timezone_set('America/Sao_Paulo');

$admin_user = $_SESSION['admin_user'] ?? 'Admin';
$page = 'usuarios'; // Define a página atual para o menu de navegação lateral (para destacar 'Usuários')

$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$userId) {
    header("Location: index.php?page=usuarios");
    exit;
}

// Lógica para atualizar o saldo principal (manter separada por ser uma ação mais direta)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_balance'])) {
    $raw_saldo = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['saldo_masked']);
    $newBalance = filter_var($raw_saldo, FILTER_VALIDATE_FLOAT);

    if ($newBalance !== false && $newBalance >= 0) {
        $updateStmt = $pdo->prepare("UPDATE users SET saldo = ?, updated_at = NOW() AT TIME ZONE 'America/Sao_Paulo' WHERE id = ?");
        $updateStmt->execute([$newBalance, $userId]);
        header("Location: user_details.php?id=$userId&status=success&message=" . urlencode("Saldo principal atualizado com sucesso."));
        exit;
    } else {
        header("Location: user_details.php?id=$userId&status=error&message=" . urlencode("Erro ao atualizar saldo: valor inválido."));
        exit;
    }
}

// NOVA LÓGICA: Adicionar saldo de comissão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_commission_balance'])) {
    $raw_commission_amount = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['commission_amount_masked']);
    $commissionAmount = filter_var($raw_commission_amount, FILTER_VALIDATE_FLOAT);

    if ($commissionAmount !== false && $commissionAmount > 0) {
        $updateStmt = $pdo->prepare("UPDATE users SET commission_balance = commission_balance + ?, updated_at = NOW() AT TIME ZONE 'America/Sao_Paulo' WHERE id = ?");
        $updateStmt->execute([$commissionAmount, $userId]);
        header("Location: user_details.php?id=$userId&status=success&message=" . urlencode("Saldo de comissão adicionado com sucesso."));
        exit;
    } else {
        header("Location: user_details.php?id=$userId&status=error&message=" . urlencode("Erro ao adicionar saldo de comissão: valor inválido."));
        exit;
    }
}

// Busca os dados completos do usuário E TODOS OS NÍVEIS DE REFERÊNCIA, convertendo o created_at
$stmt = $pdo->prepare("SELECT u.*, rl.name as level_name, u.created_at AT TIME ZONE 'America/Sao_Paulo' as created_at_sp FROM users u LEFT JOIN referral_levels rl ON u.level_id = rl.id WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: index.php?page=usuarios&status=error&message=" . urlencode("Usuário não encontrado."));
    exit;
}

// Busca o nome do usuário que indicou (se houver referrer_id)
$referrer_name = 'Nenhum';
if (!empty($user['referrer_id'])) {
    $referrer_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $referrer_stmt->execute([$user['referrer_id']]);
    $fetched_referrer_name = $referrer_stmt->fetchColumn();
    if ($fetched_referrer_name) {
        $referrer_name = htmlspecialchars($fetched_referrer_name) . " (ID: " . htmlspecialchars($user['referrer_id']) . ")";
    } else {
        $referrer_name = "ID: " . htmlspecialchars($user['referrer_id']) . " (Usuário não encontrado)";
    }
}

// =========================================================
// ✅ NOVO: CÁLCULOS AJUSTADOS CONFORME A REQUISIÇÃO
// =========================================================

// Total Depositado (do saldo principal)
$total_main_deposited = 0;
$stmt_main_deposited = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'deposit' AND status = 'APPROVED'");
$stmt_main_deposited->execute([$userId]);
$total_main_deposited = $stmt_main_deposited->fetchColumn();


// Total Sacado (do saldo principal)
// CORREÇÃO: Excluir explicitamente os saques de comissão para evitar duplicidade.
$total_main_withdrawn = 0;
$stmt_main_withdrawn = $pdo->prepare("SELECT COALESCE(SUM(ABS(amount)), 0) FROM transactions WHERE user_id = ? AND type = 'WITHDRAWAL' AND status = 'APPROVED' AND type != 'COMMISSION_WITHDRAWAL_PIX'");
$stmt_main_withdrawn->execute([$userId]);
$total_main_withdrawn = $stmt_main_withdrawn->fetchColumn();

// Comissão Ganhada
$total_commission_earned = 0;
$stmt_commission_earned = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM commission_transactions WHERE user_id = ? AND type = 'deposit_commission' AND status = 'completed'");
$stmt_commission_earned->execute([$userId]);
$total_commission_earned = $stmt_commission_earned->fetchColumn();

// Comissão Sacada
// CORREÇÃO: Buscar apenas por COMMISSION_WITHDRAWAL_PIX, que é o tipo de transação correto
$total_commission_withdrawn_approved = 0;
$stmt_commission_withdrawn_approved = $pdo->prepare("SELECT COALESCE(SUM(ABS(amount)), 0) FROM transactions WHERE user_id = ? AND type = 'COMMISSION_WITHDRAWAL_PIX' AND status IN ('COMPLETED', 'APPROVED')");
$stmt_commission_withdrawn_approved->execute([$userId]);
$total_commission_withdrawn_approved = $stmt_commission_withdrawn_approved->fetchColumn();

// Ganhos Cashback (assumindo que o cashback não tem uma tabela dedicada)
$total_cashback_earnings = (float)($user['cashback_earnings'] ?? 0);

// =========================================================
// ✅ FIM DOS CÁLCULOS AJUSTADOS
// =========================================================

// NOVO: Lógica para Paginação de Afiliados Diretos
$affiliates_per_page = 10;
$affiliates_current_page = filter_input(INPUT_GET, 'affiliates_page', FILTER_VALIDATE_INT) ?: 1;

// 1. Contar o total de afiliados
$affiliates_count_stmt = $pdo->prepare("SELECT COUNT(id) FROM users WHERE referrer_id = ?");
$affiliates_count_stmt->execute([$userId]);
$affiliates_count = $affiliates_count_stmt->fetchColumn();

// 2. Calcular o offset para a consulta
$affiliates_total_pages = ceil($affiliates_count / $affiliates_per_page);
$affiliates_offset = ($affiliates_current_page - 1) * $affiliates_per_page;
if ($affiliates_current_page < 1) $affiliates_current_page = 1;
if ($affiliates_current_page > $affiliates_total_pages) $affiliates_current_page = $affiliates_total_pages;

// 3. Buscar os afiliados para a página atual, convertendo a data no banco de dados
$affiliates_list_stmt = $pdo->prepare("SELECT name, email, created_at AT TIME ZONE 'America/Sao_Paulo' as created_at_sp FROM users WHERE referrer_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$affiliates_list_stmt->bindValue(1, $userId, PDO::PARAM_INT);
$affiliates_list_stmt->bindValue(2, $affiliates_per_page, PDO::PARAM_INT);
$affiliates_list_stmt->bindValue(3, $affiliates_offset, PDO::PARAM_INT);
$affiliates_list_stmt->execute();
$affiliates_list = $affiliates_list_stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca todos os níveis de referência para o dropdown (se existir a tabela referral_levels)
try {
    $referral_levels_stmt = $pdo->query("SELECT id, name FROM referral_levels ORDER BY id ASC");
    $referral_levels = $referral_levels_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $referral_levels = []; // Fallback se a tabela não existir ou houver erro
    error_log("Erro ao buscar referral_levels: " . $e->getMessage());
}

// --- Funções Auxiliares para Filtros de Período ---
// Esta função agora também usa a conversão de fuso horário na condição WHERE
function get_period_query_parts($param_name, $default_period = 'all_time', $date_column = 'created_at') {
    $period = $_GET[$param_name] ?? $default_period;
    $start_date = $_GET[$param_name . '_start'] ?? null;
    $end_date = $_GET[$param_name . '_end'] ?? null;

    $params = [];
    $where_condition = "";
    $label = "Todo o Período";

    switch ($period) {
        case 'today':
            $where_condition = "{$date_column} AT TIME ZONE 'America/Sao_Paulo' >= CURRENT_DATE AT TIME ZONE 'America/Sao_Paulo'";
            $label = "Hoje";
            break;
        case 'week':
            $where_condition = "{$date_column} AT TIME ZONE 'America/Sao_Paulo' >= date_trunc('week', CURRENT_TIMESTAMP AT TIME ZONE 'America/Sao_Paulo')";
            $label = "Esta Semana";
            break;
        case 'month':
            $where_condition = "{$date_column} AT TIME ZONE 'America/Sao_Paulo' >= date_trunc('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Sao_Paulo')";
            $label = "Este Mês";
            break;
        case 'custom':
            if ($start_date && $end_date) {
                $where_condition = "DATE({$date_column} AT TIME ZONE 'America/Sao_Paulo') BETWEEN ? AND ?";
                $params = [$start_date, $end_date];
                $label = date('d/m', strtotime($start_date)) . " a " . date('d/m', strtotime($end_date));
            } else {
                $period = 'all_time';
                $label = "Todo o Período";
            }
            break;
        case 'all_time':
            $where_condition = "";
            $params = [];
            $label = "Todo o Período";
            break;
        default:
            $period = 'all_time';
            $where_condition = "";
            $label = "Todo o Período";
            break;
    }
    return ['condition' => $where_condition, 'params' => $params, 'label' => $label, 'period' => $period];
}

// --- Paginação Helper ---
function get_pagination_params($total_records, $records_per_page, $current_page_param_name) {
    $current_page = filter_input(INPUT_GET, $current_page_param_name, FILTER_VALIDATE_INT) ?: 1;
    $total_pages = ceil($total_records / $records_per_page);
    if ($total_pages == 0) $total_pages = 1;

    if ($current_page > $total_pages) $current_page = $total_pages;
    if ($current_page < 1) $current_page = 1;

    $offset = ($current_page - 1) * $records_per_page;

    return ['current_page' => $current_page, 'total_pages' => $total_pages, 'offset' => $offset];
}


// --- Lógica de Carregamento de Dados com Filtros e Paginação ---
$limit_results = 10;

// Depósitos
$deposits_filter = get_period_query_parts('deposits_period', 'all_time', 'created_at');
$deposits_query_base = "SELECT *, created_at AT TIME ZONE 'America/Sao_Paulo' as created_at_sp FROM transactions WHERE user_id = ?";
$deposits_count_query_base = "SELECT COUNT(id) FROM transactions WHERE user_id = ?";
$deposits_params = [$userId];

if (!empty($deposits_filter['condition'])) {
    $deposits_query_base .= " AND {$deposits_filter['condition']}";
    $deposits_count_query_base .= " AND {$deposits_filter['condition']}";
    $deposits_params = array_merge($deposits_params, $deposits_filter['params']);
}
$deposits_query_base .= " AND type = 'deposit' AND status = 'APPROVED'";
$deposits_count_query_base .= " AND type = 'deposit' AND status = 'APPROVED'";

$deposits_count_stmt = $pdo->prepare($deposits_count_query_base);
$deposits_count_stmt->execute($deposits_params);
$total_deposits = $deposits_count_stmt->fetchColumn();

$deposits_pagination = get_pagination_params($total_deposits, $limit_results, 'deposits_page');

$deposits_query_final = "{$deposits_query_base} ORDER BY created_at DESC LIMIT {$limit_results} OFFSET {$deposits_pagination['offset']}";
$deposits_stmt = $pdo->prepare($deposits_query_final);
$deposits_stmt->execute($deposits_params);
$deposits_results = $deposits_stmt->fetchAll(PDO::FETCH_ASSOC);


// Jogadas
$plays_filter = get_period_query_parts('plays_period', 'all_time', 'played_at');
$plays_query_base = "SELECT *, played_at AT TIME ZONE 'America/Sao_Paulo' as played_at_sp FROM historicplay WHERE user_id = ?";
$plays_count_query_base = "SELECT COUNT(id) FROM historicplay WHERE user_id = ?";
$plays_params = [$userId];

if (!empty($plays_filter['condition'])) {
    $plays_query_base .= " AND {$plays_filter['condition']}";
    $plays_count_query_base .= " AND {$plays_filter['condition']}";
    $plays_params = array_merge($plays_params, $plays_filter['params']);
}

$plays_count_stmt = $pdo->prepare($plays_count_query_base);
$plays_count_stmt->execute($plays_params);
$total_plays = $plays_count_stmt->fetchColumn();

$plays_pagination = get_pagination_params($total_plays, $limit_results, 'plays_page');

$plays_query_final = "{$plays_query_base} ORDER BY played_at DESC LIMIT {$limit_results} OFFSET {$plays_pagination['offset']}";
$plays_stmt = $pdo->prepare($plays_query_final);
$plays_stmt->execute($plays_params);
$plays_results = $plays_stmt->fetchAll(PDO::FETCH_ASSOC);


// Comissões
$commissions_filter = get_period_query_parts('commissions_period', 'all_time', 'created_at');
$commissions_query_base = "SELECT *, created_at AT TIME ZONE 'America/Sao_Paulo' as created_at_sp FROM commission_transactions WHERE user_id = ?";
$commissions_count_query_base = "SELECT COUNT(id) FROM commission_transactions WHERE user_id = ?";
$commissions_params = [$userId];

if (!empty($commissions_filter['condition'])) {
    $commissions_query_base .= " AND {$commissions_filter['condition']}";
    $commissions_count_query_base .= " AND {$commissions_filter['condition']}";
    $commissions_params = array_merge($commissions_params, $commissions_filter['params']);
}
$commissions_query_base .= " AND type = 'deposit_commission'";
$commissions_count_query_base .= " AND type = 'deposit_commission'";

$commissions_count_stmt = $pdo->prepare($commissions_count_query_base);
$commissions_count_stmt->execute($commissions_params);
$total_commissions = $commissions_count_stmt->fetchColumn();

$commissions_pagination = get_pagination_params($total_commissions, $limit_results, 'commissions_page');

$commissions_query_final = "{$commissions_query_base} ORDER BY created_at DESC LIMIT {$limit_results} OFFSET {$commissions_pagination['offset']}";
$commissions_stmt = $pdo->prepare($commissions_query_final);
$commissions_stmt->execute($commissions_params);
$commissions_results = $commissions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Processamento de mensagens de sucesso/erro
$message = '';
$message_type = '';

if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success') {
        $message_type = 'success';
        $message = $_GET['message'] ?? 'Operação realizada com sucesso!';
    } elseif ($_GET['status'] == 'error') {
        $message_type = 'error';
        $message = $_GET['message'] ?? 'Ocorreu um erro na operação.';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Detalhes de <?= htmlspecialchars($user['name']) ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/vanilla-masker@1.2.0/lib/vanilla-masker.min.js"></script>
    <style>
        /* Variáveis CSS globais (copiadas das outras páginas) */
        :root {
            --bg-dark: #0D1117; --bg-light: #161B22; --border-color: #30363D;
            --text-primary: #c9d1d9; --text-secondary: #8b949e;
            --accent-blue: #58A6FF; --accent-green: #3FB950;
            --accent-red: #E24C4C; /* Para status de falha/cancelado */
            --accent-yellow: #FFC107; /* Para status pendente */
            --card-bg: #1e2a3a;
            --delete-btn-bg: #E24C4C;
            --delete-btn-hover: #c43c3c;
            --block-btn-bg: #FFC107; /* Amarelo para bloquear */
            --unblock-btn-bg: #3FB950; /* Verde para desbloquear */
        }

        /* Estilos base do corpo (copiados das outras páginas) */
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; margin: 0; background-color: var(--bg-dark); color: var(--text-primary); }

        /* Estrutura de Grid do Dashboard (copiada das outras páginas) */
        .dashboard-grid { display: grid; grid-template-columns: 280px 1fr; gap: 2rem; padding: 2rem; min-height: 100vh; }
        .sidebar { background-color: var(--bg-light); border: 1px solid var(--border-color); border-radius: 10px; padding: 1.5rem; display: flex; flex-direction: column; }
        .admin-profile { text-align: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem; }
        .admin-profile .icon { font-size: 4rem; color: var(--accent-blue); }
        .admin-profile h3 { margin: 0.5rem 0 0.2rem 0; }
        .admin-profile p { margin: 0; font-size: 0.9rem; color: var(--text-secondary); }
        .sidebar-nav { list-style: none; padding: 0; margin: 1.5rem 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; color: var(--text-secondary); text-decoration: none; padding: 0.75rem 1rem; border-radius: 6px; font-weight: 500; transition: all 0.2s; }
        .sidebar-nav a:hover { background-color: #2a3038; color: var(--text-primary); }
        .sidebar-nav a.active { background-color: var(--accent-blue); color: #fff; font-weight: 600; }
        .logout-btn { margin-top: auto; }
        .logout-btn a { display: flex; align-items: center; gap: 0.75rem; color: var(--text-secondary); text-decoration: none; padding: 0.75rem 1rem; border-radius: 6px; font-weight: 500; border: 1px solid var(--text-secondary); }
        .logout-btn a:hover { background-color: #2a3038; color: var(--text-primary); }
        .main-content { overflow-y: auto; }
        h1, h2 { font-weight: 600; }
        .page-header { border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 2rem; }

        /* Mensagens de Sucesso/Erro */
        .alert-message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        .alert-message.success {
            background-color: rgba(63, 185, 80, 0.2);
            color: var(--accent-green);
            border: 1px solid var(--accent-green);
        }
        .alert-message.error {
            background-color: rgba(226, 76, 76, 0.2);
            color: var(--accent-red);
            border: 1px solid var(--accent-red);
        }
        .alert-message i {
            font-size: 1.5rem;
        }

        /* Botão Voltar (para a lista de usuários) */
        .back-button {
            background-color: var(--accent-blue);
            color: #fff;
            padding: 10px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s, transform 0.1s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .back-button:hover {
            background-color: #4a90e2;
            transform: translateY(-1px);
        }

        /* Seção de Detalhes do Usuário (Informações Principais, Financeiro, Gerenciamento) */
        .details-card-section {
            background-color: var(--bg-light);
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }
        .details-card-section h3 {
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.8rem;
            margin-top: 0;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .details-item {
            background-color: var(--bg-dark);
            padding: 1rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            font-size: 0.95rem;
        }
        .details-item strong {
            color: var(--text-secondary);
            display: block;
            margin-bottom: 0.2rem;
            font-weight: 500;
        }
        .details-item span {
            color: var(--text-primary);
            font-weight: 600;
        }
        .details-item.balance span { color: var(--accent-green); }
        .details-item.commission span { color: var(--accent-blue); }
        .details-item.total-withdrawn span { color: var(--accent-red); }
        .details-item.commission-withdrawn span { color: var(--accent-red); }

        /* NOVO Card de Afiliados - Estilo */
        .affiliate-section {
            background-color: var(--bg-light);
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }
        /* Estilo existente para o título da seção de afiliados */
        .affiliate-section h3 {
            color: var(--text-primary);
            font-size: 1.3rem;
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.8rem;
            margin-top: 0;
            margin-bottom: 1.5rem;

            /* Propriedades para centralizar e adicionar espaçamento */
            display: flex;
            align-items: center;
            justify-content: center; /* Centraliza horizontalmente */
            gap: 1rem; /* Adiciona espaçamento entre os itens */
        }
        .affiliate-section h3 span {
            color: var(--accent-green);
            font-weight: bold;
        }
        .affiliate-section .no-data {
            text-align: center;
            color: var(--text-secondary);
            padding: 2rem 0;
        }
        .affiliate-section table {
            width: 100%;
            border-collapse: collapse;
            text-align: center; /* Centraliza todo o texto da tabela */
            font-size: 0.9rem;
        }
        .affiliate-section th, .affiliate-section td {
            padding: 12px 15px; /* Adiciona um bom espaçamento interno */
            border-bottom: 1px solid var(--border-color);
        }
        .affiliate-section thead tr {
            background-color: var(--bg-dark);
        }
        .affiliate-section th {
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-size: 0.8rem;
        }
        .affiliate-section tbody tr:last-child td {
            border-bottom: none;
        }
        .affiliate-section .no-data {
            text-align: center;
            color: var(--text-secondary);
            padding: 2rem 0;
        }

        /* Formulários de Edição */
        .edit-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .form-group label {
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .form-group input, .form-group select {
            padding: 10px;
            background-color: var(--bg-dark);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 1rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            width: 100%;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(88, 166, 255, 0.2);
        }
        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
        }
        .form-actions button {
            background-color: var(--accent-blue);
            color: #fff;
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.2s, transform 0.1s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-actions button:hover {
            background-color: #4a90e2;
            transform: translateY(-1px);
        }
        .form-inline {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .form-inline label {
            white-space: nowrap;
        }
        .form-inline input {
            max-width: 250px;
        }


        /* Contêiner de botões de gerenciamento de usuário */
        .user-management-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
            justify-content: flex-end;
            border-top: 1px dashed var(--border-color);
            padding-top: 1.5rem;
        }
        .user-management-actions button {
            padding: 10px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s, transform 0.1s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .user-management-actions .delete-user-btn {
            background-color: var(--delete-btn-bg);
            color: #fff;
        }
        .user-management-actions .delete-user-btn:hover {
            background-color: var(--delete-btn-hover);
            transform: translateY(-1px);
        }
        .user-management-actions .block-user-btn {
            background-color: var(--block-btn-bg);
            color: var(--bg-dark);
        }
        .user-management-actions .block-user-btn:hover {
            background-color: #d4a700;
            transform: translateY(-1px);
        }
        .user-management-actions .unblock-user-btn {
            background-color: var(--unblock-btn-bg);
            color: #fff;
        }
        .user-management-actions .unblock-user-btn:hover {
            background-color: #359f44;
            transform: translateY(-1px);
        }

        /* Seções de Histórico (Últimos Depósitos, Jogadas, Comissões) */
        .history-section {
            background-color: var(--bg-light);
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }
        .history-section h3 {
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.8rem;
            margin-top: 0;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: space-between;
        }
        .history-section h3 .title-icon {
            font-size: 1.5rem;
        }
        .history-section table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
        }
        .history-section th, .history-section td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
        }
        .history-section thead tr {
            background-color: var(--bg-dark);
        }
        .history-section th {
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-size: 0.8rem;
        }
        .history-section tbody tr:hover {
            background-color: #2c2c2c;
        }
        .history-section tbody tr:last-child td {
            border-bottom: none;
        }
        /* Estilos para células de status dentro das tabelas */
        .history-section .status-cell {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-approved { background-color: rgba(63, 185, 80, 0.2); color: var(--accent-green); border: 1px solid var(--accent-green); }
        .status-pending { background-color: rgba(255, 193, 7, 0.2); color: var(--accent-yellow); border: 1px solid var(--accent-yellow); }
        .status-failed, .status-cancelled, .status-rejected { background-color: rgba(226, 76, 76, 0.2); color: var(--accent-red); border: 1px solid var(--accent-red); }
        .status-processing { background-color: rgba(88, 166, 255, 0.2); color: var(--accent-blue); border: 1px solid var(--accent-blue); }

        /* Mensagens "Sem dados" nas tabelas */
        .history-section .no-data-row td {
            text-align: center;
            color: var(--text-secondary);
            font-style: italic;
            padding: 20px;
        }

        /* Estilos para os filtros de período */
        .card-filter {
            display: flex;
            gap: 5px;
            background-color: var(--bg-dark);
            padding: 4px;
            border-radius: 6px;
        }
        .card-filter a {
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .card-filter a:hover {
            background-color: #30363d;
            color: var(--text-primary);
        }
        .card-filter a.active {
            background-color: var(--accent-blue);
            color: #fff;
            font-weight: 600;
        }
        /* Estilos Flatpickr */
        .flatpickr-calendar { background: #161B22; border-color: var(--border-color); border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); color: var(--text-primary); }
        .flatpickr-months .flatpickr-month, .flatpickr-current-month { color: inherit; fill: var(--text-primary); }
        .flatpickr-weekday { color: var(--accent-blue); }
        .flatpickr-day { color: var(--text-primary); }
        .flatpickr-day:hover { background: #30363D; }
        .flatpickr-day.today { border-color: var(--accent-blue); }
        .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange { background: var(--accent-blue); border-color: var(--accent-blue); color: #fff; }
        .flatpickr-day.inRange { background: rgba(88, 166, 255, 0.2); border-color: transparent; box-shadow: -5px 0 0 rgba(88, 166, 255, 0.2), 5px 0 0 rgba(88, 166, 255, 0.2); }

        /* Estilos de Paginação */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px dashed var(--border-color);
        }
        .pagination a, .pagination span {
            background-color: var(--bg-dark);
            color: var(--text-secondary);
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background-color 0.2s, color 0.2s;
            border: 1px solid var(--border-color);
        }
        .pagination a:hover {
            background-color: #2a3038;
            color: var(--text-primary);
        }
        .pagination span.current-page {
            background-color: var(--accent-blue);
            color: #fff;
            border-color: var(--accent-blue);
            font-weight: 600;
        }
        .pagination span.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Estilos do Modal de Confirmação */
        .confirmation-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            visibility: hidden;
            opacity: 0;
            transition: visibility 0s, opacity 0.3s ease-in-out;
        }
        .confirmation-modal.show {
            visibility: visible;
            opacity: 1;
        }
        .modal-content {
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 2rem;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            text-align: center;
            position: relative;
        }
        .modal-content h3 {
            color: var(--accent-red);
            margin-top: 0;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        .modal-content h3 i {
            font-size: 2rem;
        }
        .modal-content p {
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        .modal-button {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
            border: none;
        }
        .modal-button.cancel {
            background-color: var(--text-secondary);
            color: var(--text-primary);
        }
        .modal-button.cancel:hover {
            background-color: #7a828b;
            transform: translateY(-1px);
        }
        .modal-button.confirm {
            background-color: var(--delete-btn-bg);
            color: #fff;
        }
        .modal-button.confirm:hover {
            background-color: var(--delete-btn-hover);
            transform: translateY(-1px);
        }

        /* Novas regras de estilo para os cards mais modernos */
        .card-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card-modern {
            background-color: var(--bg-light);
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            flex: 1 1 300px; /* Para cards de gerenciamento */
        }
        .card-modern h3 {
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.8rem;
            margin-top: 0;
            margin-bottom: 1.5rem;
        }

        .form-modern-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .form-modern-group input {
            padding: 10px;
            background-color: var(--bg-dark);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .form-modern-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
        }
        .form-modern-actions button {
            padding: 10px 18px;
            border-radius: 6px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
        }
        /* ---------------------------------------------------- */
        /* RESPONSIVIDADE */
        /* ---------------------------------------------------- */

        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 1.5rem;
            }
            .sidebar {
                order: 2;
            }
            .main-content {
                order: 1;
            }
            .details-grid {
                grid-template-columns: 1fr; /* Coloca os cards de detalhes em uma única coluna */
            }
            .edit-form-grid {
                grid-template-columns: 1fr; /* Empilha os campos do formulário */
            }
            .form-actions, .user-management-actions {
                justify-content: center; /* Centraliza os botões */
            }
            .form-inline {
                flex-direction: column; /* Empilha os campos inline */
                align-items: stretch; /* Estica os itens para ocupar a largura total */
            }
            .form-inline input {
                max-width: 100%; /* Garante que o input ocupe a largura total */
            }
            .affiliate-section h3 {
                flex-direction: column; /* Empilha o título e o valor */
                text-align: center;
                gap: 0.5rem;
            }
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                padding: 1rem;
            }
            .sidebar, .history-section, .details-card-section {
                padding: 1rem;
            }
            .history-section table, .affiliate-section table {
                font-size: 0.85rem;
            }
            .history-section th, .history-section td, .affiliate-section th, .affiliate-section td {
                padding: 8px 10px;
            }
            .card-filter {
                flex-wrap: wrap; /* Permite que os filtros quebrem para a próxima linha */
            }
        }

        @media (max-width: 480px) {
            .dashboard-grid {
                padding: 0.5rem;
                gap: 0.5rem;
            }
            .sidebar, .history-section, .details-card-section {
                padding: 0.75rem;
            }
            h1, h2, .details-card-section h3, .history-section h3 {
                font-size: 1.2rem;
                padding-bottom: 0.5rem;
                margin-bottom: 1rem;
            }
            .admin-profile h3 { font-size: 1.2rem; }
            .admin-profile p { font-size: 0.8rem; }
            .details-item, .form-modern-group input { font-size: 0.9rem; }
            .form-actions button, .user-management-actions button {
                width: 100%; /* Estica os botões de ação para a largura total */
                justify-content: center;
            }
            .modal-content {
                width: 95%; /* Ajusta a largura do modal para telas bem pequenas */
                padding: 1.5rem;
            }
            .modal-buttons {
                flex-direction: column; /* Empilha os botões do modal */
                gap: 0.75rem;
            }
            .modal-button {
                width: 100%;
            }
            .history-section th, .history-section td {
                /* Esconde algumas colunas da tabela para telas muito pequenas */
                /* Seções de Histórico (Últimos Depósitos, Jogadas, Comissões) */
                /* Para telas pequenas, pode ser necessário remover colunas da tabela ou usar tabelas responsivas */
                /* Por exemplo: esconder a coluna de ID ou data */
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-grid">
        <aside class="sidebar">
            <div class="admin-profile">
                <i class="bi bi-person-circle icon"></i>
                <h3><?= htmlspecialchars($admin_user) ?></h3>
                <p>Administrador</p>
            </div>
            <ul class="sidebar-nav">
                <li><a href="index.php?page=dashboard" class="<?= ($page === 'dashboard') ? 'active' : '' ?>"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
                <li><a href="index.php?page=usuarios" class="<?= ($page === 'usuarios') ? 'active' : '' ?>"><i class="bi bi-people-fill"></i> Usuários</a></li>
                <li><a href="index.php?page=financeiro" class="<?= ($page === 'financeiro') ? 'active' : '' ?>"><i class="bi bi-wallet2"></i> Financeiro</a></li>
                <li><a href="index.php?page=relatorios" class="<?= ($page === 'relatorios') ? 'active' : '' ?>"><i class="bi bi-file-earmark-bar-graph-fill"></i> Relatórios</a></li>
                <li><a href="index.php?page=contasdemo" class="<?= ($page === 'contasdemo') ? 'active' : '' ?>"><i class="bi bi-joystick"></i> Contas Demo</a></li>
                <li><a href="index.php?page=gerenciarjogos" class="<?= ($page === 'gerenciarjogos') ? 'active' : '' ?>"><i class="bi bi-gear-fill"></i> Gerenciar Jogos</a></li>
                <li><a href="index.php?page=gerenciarpagamentos" class="<?= ($page === 'gerenciarpagamentos') ? 'active' : '' ?>"><i class="bi bi-credit-card-fill"></i> Métodos de Pagamento</a></li>
            </ul>
            <div class="logout-btn">
                <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Sair da Conta</a>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1>Detalhes do Usuário: <?= htmlspecialchars($user['name']) ?></h1>
            </div>

            <a href="index.php?page=usuarios" class="back-button"><i class="bi bi-arrow-left"></i> Voltar para a Lista de Usuários</a>

            <?php if (isset($_GET['status'])): ?>
                <div class="alert-message <?= htmlspecialchars($message_type) ?>">
                    <i class="bi <?= $message_type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?>"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            <?php endif; ?>

            <div class="details-card-section">
                <h3><i class="bi bi-person-circle"></i> Informações Principais</h3>
                <form method="POST" action="/update_user_profile.php" class="edit-form-grid">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                    <input type="hidden" name="redirect_to" value="user_details.php?id=<?= htmlspecialchars($user['id']) ?>">

                    <div class="form-group">
                        <label for="name"><i class="bi bi-person"></i> Nome:</label>
                        <input type="text" name="name" id="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email"><i class="bi bi-envelope"></i> Email:</label>
                        <input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone"><i class="bi bi-phone"></i> Telefone:</label>
                        <input type="text" name="phone" id="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="document"><i class="bi bi-file-earmark-person"></i> CPF/Documento:</label>
                        <input type="text" name="document" id="document" value="<?= htmlspecialchars($user['document'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="xp"><i class="bi bi-star"></i> XP:</label>
                        <input type="number" name="xp" id="xp" value="<?= htmlspecialchars($user['xp']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="level_id"><i class="bi bi-arrow-up-right-square"></i> Nível:</label>
                        <select name="level_id" id="level_id">
                            <?php if (!empty($referral_levels)): ?>
                                <?php foreach ($referral_levels as $level): ?>
                                    <option value="<?= htmlspecialchars($level['id']) ?>"
                                        <?= ($level['id'] == $user['level_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($level['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="<?= htmlspecialchars($user['level_id']) ?>" selected><?= htmlspecialchars($user['level_name'] ?? 'N/A') ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="details-item">
                        <strong>Membro Desde:</strong>
                        <span><?= date('d/m/Y H:i', strtotime($user['created_at_sp'])) ?></span>
                    </div>
                    <div class="details-item">
                        <strong>Indicado Por:</strong>
                        <span><?= $referrer_name ?></span>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_profile"><i class="bi bi-save"></i> Salvar Alterações</button>
                    </div>
                </form>

                <h3><i class="bi bi-wallet2"></i> Dados Financeiros</h3>
                <div class="details-grid">
                    <div class="details-item balance"><strong>Saldo Principal:</strong> <span>R$ <?= number_format($user['saldo'], 2, ',', '.') ?></span></div>
                    <div class="details-item commission"><strong>Saldo de Comissão:</strong> <span>R$ <?= number_format($user['commission_balance'], 2, ',', '.') ?></span></div>
                    <div class="details-item"><strong>Total Depositado:</strong> <span>R$ <?= number_format($total_main_deposited, 2, ',', '.') ?></span></div>
                    <div class="details-item total-withdrawn"><strong>Total Sacado:</strong> <span>R$ <?= number_format($total_main_withdrawn, 2, ',', '.') ?></span></div>
                    <div class="details-item"><strong>Ganhos Cashback:</strong> <span>R$ <?= number_format($total_cashback_earnings, 2, ',', '.') ?></span></div>
                    <div class="details-item"><strong>Comissão Ganhada:</strong> <span>R$ <?= number_format($total_commission_earned, 2, ',', '.') ?></span></div>
                    <div class="details-item commission-withdrawn"><strong>Comissão Sacada:</strong> <span>R$ <?= number_format($total_commission_withdrawn_approved, 2, ',', '.') ?></span></div>
                    <div class="details-item"><strong>Taxa Comissão:</strong> <span><?= htmlspecialchars($user['commission_rate'] * 100) ?>%</span></div>
                </div>
            </div>

            <div class="card-group">
                <div class="card-modern">
                    <h3><i class="bi bi-gear-fill"></i> Gerenciar Saldo Principal</h3>
                    <form method="POST" action="user_details.php?id=<?= $userId ?>">
                        <div class="form-modern-group">
                            <label for="saldo_masked">Novo Saldo:</label>
                            <input type="text" name="saldo_masked" id="saldo_masked" value="R$ <?= number_format($user['saldo'], 2, ',', '.') ?>">
                        </div>
                        <div class="form-modern-actions">
                            <button type="submit" name="update_balance" class="btn-primary"><i class="bi bi-save"></i> Atualizar Saldo</button>
                        </div>
                    </form>
                </div>

                <div class="card-modern">
                    <h3><i class="bi bi-piggy-bank"></i> Adicionar Saldo de Comissão</h3>
                    <form method="POST" action="user_details.php?id=<?= $userId ?>">
                        <div class="form-modern-group">
                            <label for="commission_amount_masked">Valor para Adicionar:</label>
                            <input type="text" name="commission_amount_masked" id="commission_amount_masked" value="R$ 0,00">
                        </div>
                        <div class="form-modern-actions">
                            <button type="submit" name="add_commission_balance" class="btn-primary"><i class="bi bi-plus-circle"></i> Adicionar</button>
                        </div>
                    </form>
                </div>

                <div class="card-modern" style="flex-basis: 100%;">
                    <h3><i class="bi bi-person-x"></i> Ações Administrativas</h3>
                    <div class="user-management-actions">
                        <button type="button" class="action-button <?= $user['is_blocked'] ? 'unblock-user-btn' : 'block-user-btn' ?>"
                                data-user-id="<?= htmlspecialchars($userId) ?>"
                                data-user-name="<?= htmlspecialchars($user['name']) ?>"
                                data-current-status="<?= htmlspecialchars($user['is_blocked'] ? 'true' : 'false') ?>"
                                data-action-type="toggle_block">
                            <i class="bi <?= $user['is_blocked'] ? 'bi-unlock-fill' : 'bi-lock-fill' ?>"></i>
                            <?= $user['is_blocked'] ? 'Desbloquear Acesso' : 'Bloquear Acesso' ?>
                        </button>
                        <button type="button" class="action-button delete-user-btn"
                                data-user-id="<?= htmlspecialchars($userId) ?>"
                                data-user-name="<?= htmlspecialchars($user['name']) ?>"
                                data-action-type="delete_user">
                            <i class="bi bi-person-x-fill"></i> Excluir Usuário
                        </button>
                    </div>
                </div>
            </div>

            <div class="affiliate-section">
                <h3><i class="bi bi-people-fill"></i> Afiliados Diretos <span class="affiliate-count">(<?= $affiliates_count ?>)</span></h3>
                <?php if (!empty($affiliates_list)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Data de Cadastro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($affiliates_list as $affiliate): ?>
                                <tr>
                                    <td><?= htmlspecialchars($affiliate['name']) ?></td>
                                    <td><?= htmlspecialchars($affiliate['email']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($affiliate['created_at_sp'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="pagination">
                        <?php if ($affiliates_current_page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['affiliates_page' => $affiliates_current_page - 1])) ?>">Anterior</a>
                        <?php else: ?>
                            <span class="disabled">Anterior</span>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $affiliates_current_page - 2);
                        $end_page = min($affiliates_total_pages, $affiliates_current_page + 2);

                        if ($start_page > 1) { echo '<span>...</span>'; }

                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $affiliates_current_page): ?>
                                <span class="current-page"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['affiliates_page' => $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($end_page < $affiliates_total_pages): echo '<span>...</span>'; endif; ?>

                        <?php if ($affiliates_current_page < $affiliates_total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['affiliates_page' => $affiliates_current_page + 1])) ?>">Próximo</a>
                        <?php else: ?>
                            <span class="disabled">Próximo</span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="no-data">Este usuário não possui afiliados diretos.</p>
                <?php endif; ?>
            </div>

            <div class="history-section">
                <h3>
                    <div><i class="bi bi-cash-stack title-icon"></i> Últimos Depósitos</div>
                    <div class="card-filter">
                        <a href="?<?= http_build_query(array_merge($_GET, ['deposits_page' => 1, 'deposits_period' => 'today', 'deposits_period_start' => null, 'deposits_period_end' => null])) ?>"
                           class="<?= $deposits_filter['period'] === 'today' ? 'active' : '' ?>">Hoje</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['deposits_page' => 1, 'deposits_period' => 'week', 'deposits_period_start' => null, 'deposits_period_end' => null])) ?>"
                           class="<?= $deposits_filter['period'] === 'week' ? 'active' : '' ?>">Semana</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['deposits_page' => 1, 'deposits_period' => 'month', 'deposits_period_start' => null, 'deposits_period_end' => null])) ?>"
                           class="<?= $deposits_filter['period'] === 'month' ? 'active' : '' ?>">Mês</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['deposits_page' => 1, 'deposits_period' => 'all_time', 'deposits_period_start' => null, 'deposits_period_end' => null])) ?>"
                           class="<?= $deposits_filter['period'] === 'all_time' ? 'active' : '' ?>">Todo Período</a>
                        <a href="#" class="date-picker-trigger" data-param-prefix="deposits_period">Data</a>
                    </div>
                </h3>
                <table>
                    <thead>
                        <tr><th>ID Transação</th><th>Valor (R$)</th><th>Provedor</th><th>Status</th><th>Data</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($deposits_results)): ?>
                            <?php foreach ($deposits_results as $row):
                                $transaction_date = date('d/m/Y H:i', strtotime($row['created_at_sp']));
                                $status_class = 'status-' . strtolower(str_replace(' ', '-', $row['status']));
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['id']) ?></td>
                                    <td>R$ <?= number_format($row['amount'], 2, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($row['provider'] ?? 'N/A') ?></td>
                                    <td><span class="status-cell <?= $status_class ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                                    <td><?= $transaction_date ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="no-data-row"><td colspan="5">Nenhum depósito encontrado para o período selecionado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="pagination">
                    <?php if ($deposits_pagination['current_page'] > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['deposits_page' => $deposits_pagination['current_page'] - 1])) ?>">Anterior</a>
                    <?php else: ?>
                        <span class="disabled">Anterior</span>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $deposits_pagination['current_page'] - 2);
                    $end_page = min($deposits_pagination['total_pages'], $deposits_pagination['current_page'] + 2);

                    if ($start_page > 1) { echo '<span>...</span>'; }

                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <?php if ($i == $deposits_pagination['current_page']): ?>
                            <span class="current-page"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['deposits_page' => $i])) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end_page < $deposits_pagination['total_pages']): echo '<span>...</span>'; endif; ?>

                    <?php if ($deposits_pagination['current_page'] < $deposits_pagination['total_pages']): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['deposits_page' => $deposits_pagination['current_page'] + 1])) ?>">Próximo</a>
                    <?php else: ?>
                        <span class="disabled">Próximo</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="history-section">
                <h3>
                    <div><i class="bi bi-controller title-icon"></i> Últimas Jogadas</div>
                    <div class="card-filter">
                        <a href="?<?= http_build_query(array_merge($_GET, ['plays_page' => 1, 'plays_period' => 'today', 'plays_period_start' => null, 'plays_period_end' => null])) ?>"
                           class="<?= $plays_filter['period'] === 'today' ? 'active' : '' ?>">Hoje</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['plays_page' => 1, 'plays_period' => 'week', 'plays_period_start' => null, 'plays_period_end' => null])) ?>"
                           class="<?= $plays_filter['period'] === 'week' ? 'active' : '' ?>">Semana</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['plays_page' => 1, 'plays_period' => 'month', 'plays_period_start' => null, 'plays_period_end' => null])) ?>"
                           class="<?= $plays_filter['period'] === 'month' ? 'active' : '' ?>">Mês</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['plays_page' => 1, 'plays_period' => 'all_time', 'plays_period_start' => null, 'plays_period_end' => null])) ?>"
                           class="<?= $plays_filter['period'] === 'all_time' ? 'active' : '' ?>">Todo Período</a>
                        <a href="#" class="date-picker-trigger" data-param-prefix="plays_period">Data</a>
                    </div>
                </h3>
                <table>
                    <thead>
                        <tr><th>Jogo</th><th>Aposta (R$)</th><th>Prêmio (R$)</th><th>Data</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($plays_results)): ?>
                            <?php foreach ($plays_results as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['game_name'] ?? 'N/A') ?></td>
                                    <td>R$ <?= number_format($row['bet_amount'], 2, ',', '.') ?></td>
                                    <td>R$ <?= number_format($row['prize_amount'], 2, ',', '.') ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($row['played_at_sp'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="no-data-row"><td colspan="4">Nenhuma jogada encontrada para o período selecionado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="pagination">
                    <?php if ($plays_pagination['current_page'] > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['plays_page' => $plays_pagination['current_page'] - 1])) ?>">Anterior</a>
                    <?php else: ?>
                        <span class="disabled">Anterior</span>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $plays_pagination['current_page'] - 2);
                    $end_page = min($plays_pagination['total_pages'], $plays_pagination['current_page'] + 2);

                    if ($start_page > 1) { echo '<span>...</span>'; }

                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <?php if ($i == $plays_pagination['current_page']): ?>
                            <span class="current-page"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['plays_page' => $i])) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end_page < $plays_pagination['total_pages']): echo '<span>...</span>'; endif; ?>

                    <?php if ($plays_pagination['current_page'] < $plays_pagination['total_pages']): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['plays_page' => $plays_pagination['current_page'] + 1])) ?>">Próximo</a>
                    <?php else: ?>
                        <span class="disabled">Próximo</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="history-section">
                <h3>
                    <div><i class="bi bi-piggy-bank title-icon"></i> Últimas Transações de Comissão</div>
                    <div class="card-filter">
                        <a href="?<?= http_build_query(array_merge($_GET, ['commissions_page' => 1, 'commissions_period' => 'today', 'commissions_period_start' => null, 'commissions_period_end' => null])) ?>"
                           class="<?= $commissions_filter['period'] === 'today' ? 'active' : '' ?>">Hoje</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['commissions_page' => 1, 'commissions_period' => 'week', 'commissions_period_start' => null, 'commissions_period_end' => null])) ?>"
                           class="<?= $commissions_filter['period'] === 'week' ? 'active' : '' ?>">Semana</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['commissions_page' => 1, 'commissions_period' => 'month', 'commissions_period_start' => null, 'commissions_period_end' => null])) ?>"
                           class="<?= $commissions_filter['period'] === 'month' ? 'active' : '' ?>">Mês</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['commissions_page' => 1, 'commissions_period' => 'all_time', 'commissions_period_start' => null, 'commissions_period_end' => null])) ?>"
                           class="<?= $commissions_filter['period'] === 'all_time' ? 'active' : '' ?>">Todo Período</a>
                        <a href="#" class="date-picker-trigger" data-param-prefix="commissions_period">Data</a>
                    </div>
                </h3>
                <table>
                    <thead>
                        <tr><th>ID</th><th>Tipo</th><th>Valor (R$)</th><th>Descrição</th><th>Data</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($commissions_results)): ?>
                            <?php foreach ($commissions_results as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['id']) ?></td>
                                    <td><?= htmlspecialchars($row['type'] ?? 'N/A') ?></td>
                                    <td>R$ <?= number_format($row['amount'], 2, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($row['description'] ?? 'N/A') ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($row['created_at_sp'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="no-data-row"><td colspan="5">Nenhuma transação de comissão encontrada para o período selecionado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="pagination">
                    <?php if ($commissions_pagination['current_page'] > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['commissions_page' => $commissions_pagination['current_page'] - 1])) ?>">Anterior</a>
                    <?php else: ?>
                        <span class="disabled">Anterior</span>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $commissions_pagination['current_page'] - 2);
                    $end_page = min($commissions_pagination['total_pages'], $commissions_pagination['current_page'] + 2);

                    if ($start_page > 1) { echo '<span>...</span>'; }

                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <?php if ($i == $commissions_pagination['current_page']): ?>
                            <span class="current-page"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['commissions_page' => $i])) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end_page < $commissions_pagination['total_pages']): echo '<span>...</span>'; endif; ?>

                    <?php if ($commissions_pagination['current_page'] < $commissions_pagination['total_pages']): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['commissions_page' => $commissions_pagination['current_page'] + 1])) ?>">Próximo</a>
                    <?php else: ?>
                        <span class="disabled">Próximo</span>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>

    <div id="confirmationModal" class="confirmation-modal">
        <div class="modal-content">
            <h3><i class="bi bi-exclamation-triangle-fill"></i> Confirmação de Ação</h3>
            <p id="modalMessage"></p>
            <div class="modal-buttons">
                <button type="button" class="modal-button cancel" id="cancelAction">Cancelar</button>
                <button type="button" class="modal-button confirm" id="confirmAction">Confirmar</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/vanilla-masker@1.2.0/lib/vanilla-masker.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar a máscara de BRL no campo de saldo principal
            const saldoInput = document.getElementById('saldo_masked');
            if (saldoInput) {
                VMasker(saldoInput).maskMoney({
                    precision: 2,
                    separator: ',',
                    delimiter: '.',
                    unit: 'R$',
                    zeroCents: false
                });
            }

            // NOVA MÁSCARA para o campo de comissão
            const commissionInput = document.getElementById('commission_amount_masked');
            if (commissionInput) {
                    VMasker(commissionInput).maskMoney({
                    precision: 2,
                    separator: ',',
                    delimiter: '.',
                    unit: 'R$',
                    zeroCents: false
                });
            }

            // --- LÓGICA DO CALENDÁRIO (Flatpickr) ---
            flatpickr(".date-picker-trigger", {
                mode: 'range',
                dateFormat: 'Y-m-d',
                disableMobile: true,
                onClose: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        const startDate = instance.formatDate(selectedDates[0], "Y-m-d");
                        const endDate = instance.formatDate(selectedDates[1], "Y-m-d");
                        const paramPrefix = instance.element.dataset.paramPrefix;

                        const url = new URL(window.location.href);

                        url.searchParams.set(paramPrefix, 'custom');
                        url.searchParams.set(paramPrefix + '_start', startDate);
                        url.searchParams.set(paramPrefix + '_end', endDate);

                        // Reseta a paginação ao aplicar um filtro de data
                        url.searchParams.set(paramPrefix.replace('_period', '_page'), 1);

                        url.searchParams.set('id', <?= json_encode($userId) ?>);

                        window.location.href = url.toString();
                    }
                }
            });


            // --- LÓGICA DO MODAL DE CONFIRMAÇÃO PARA AÇÕES DE USUÁRIO ---
            const confirmationModal = document.getElementById('confirmationModal');
            const modalMessage = document.getElementById('modalMessage');
            const confirmActionBtn = document.getElementById('confirmAction');
            const cancelActionBtn = document.getElementById('cancelAction');

            let actionToExecute = null;

            function showConfirmationModal(message, onConfirmCallback) {
                modalMessage.innerHTML = message;
                confirmationModal.classList.add('show');
                actionToExecute = onConfirmCallback;
            }

            function hideConfirmationModal() {
                confirmationModal.classList.remove('show');
                actionToExecute = null;
            }

            confirmActionBtn.addEventListener('click', function() {
                if (actionToExecute) {
                    actionToExecute();
                }
                hideConfirmationModal();
            });

            cancelActionBtn.addEventListener('click', hideConfirmationModal);

            confirmationModal.addEventListener('click', function(event) {
                if (event.target === confirmationModal) {
                    hideConfirmationModal();
                }
            });

            // --- Ativar botões de gerenciamento de usuário com o novo modal ---

            // Botão de Excluir Usuário
            const deleteUserBtn = document.querySelector('.delete-user-btn');
            if (deleteUserBtn) {
                deleteUserBtn.addEventListener('click', function() {
                    const userId = this.dataset.userId;
                    const userName = this.dataset.userName;
                    const actionType = this.dataset.action-type;

                    const message = `Tem certeza que deseja **EXCLUIR PERMANENTEMENTE** o usuário **${userName}** e todos os seus dados?` +
                                     `<br><br>**ESTA AÇÃO É IRREVERSÍVEL!** Todos os depósitos, jogadas e comissões associadas serão deletados.`;

                    showConfirmationModal(message, function() {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '/manage_user.php';

                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = actionType;
                        form.appendChild(actionInput);

                        const userIdInput = document.createElement('input');
                        userIdInput.type = 'hidden';
                        userIdInput.name = 'user_id';
                        userIdInput.value = userId;
                        form.appendChild(userIdInput);

                        const redirectToInput = document.createElement('input');
                        redirectToInput.type = 'hidden';
                        redirectToInput.name = 'redirect_to';
                        redirectToInput.value = 'index.php?page=usuarios';
                        form.appendChild(redirectToInput);

                        document.body.appendChild(form);
                        form.submit();
                    });
                });
            }

            // Botão de Bloquear/Desbloquear Acesso
            const toggleBlockBtn = document.querySelector('.block-user-btn, .unblock-user-btn');
            if (toggleBlockBtn) {
                toggleBlockBtn.addEventListener('click', function() {
                    const userId = this.dataset.userId;
                    const userName = this.dataset.userName;
                    const currentStatus = this.dataset.currentStatus === 'true';
                    const actionText = currentStatus ? 'DESBLOQUEAR' : 'BLOQUEAR';
                    const message = `Tem certeza que deseja **${actionText}** o acesso do usuário **${userName}** ao sistema?` +
                                     `<br><br>Ele(a) ${currentStatus ? 'poderá fazer login novamente.' : 'NÃO poderá mais fazer login.'}`;

                    showConfirmationModal(message, function() {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '/manage_user.php';

                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'toggle_block_user';
                        form.appendChild(actionInput);

                        const userIdInput = document.createElement('input');
                        userIdInput.type = 'hidden';
                        userIdInput.name = 'user_id';
                        userIdInput.value = userId;
                        form.appendChild(userIdInput);

                        const currentStatusInput = document.createElement('input');
                        currentStatusInput.type = 'hidden';
                        currentStatusInput.name = 'current_status';
                        currentStatusInput.value = currentStatus ? 'true' : 'false';
                        form.appendChild(currentStatusInput);

                        const redirectToInput = document.createElement('input');
                        redirectToInput.type = 'hidden';
                        redirectToInput.name = 'redirect_to';
                        redirectToInput.value = window.location.href;
                        form.appendChild(redirectToInput);

                        document.body.appendChild(form);
                        form.submit();
                    });
                });
            }
        });
    </script>
</body>
</html>