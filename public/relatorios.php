<?php
// public/admin/relatorios.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

// Definir o fuso horário para São Paulo (GMT-3)
date_default_timezone_set('America/Sao_Paulo');

$admin_user = $_SESSION['admin_user'] ?? 'Admin';
$page = 'relatorios'; // Define a página atual para o menu de navegação lateral

// --- Funções Auxiliares para Filtros de Período ---
function get_period_query_parts($param_name, $default_period = 'all_time', $date_column = 'created_at') {
    $period = $_GET[$param_name] ?? $default_period;
    $start_date = $_GET[$param_name . '_start'] ?? null;
    $end_date = $_GET[$param_name . '_end'] ?? null;

    $params = [];
    $where_condition = "";
    $label = "Todo o Período";

    // Convertendo a data do banco de dados para o fuso horário de São Paulo antes de aplicar a comparação
    $date_column_local = "timezone('America/Sao_Paulo', {$date_column})";

    switch ($period) {
        case 'today':
            $where_condition = "{$date_column_local}::date = NOW()::date";
            $label = "Hoje";
            break;
        case 'week':
            $where_condition = "{$date_column_local} >= date_trunc('week', NOW()) AND {$date_column_local} < (date_trunc('week', NOW()) + INTERVAL '1 week')";
            $label = "Esta Semana";
            break;
        case 'month':
            $where_condition = "{$date_column_local} >= date_trunc('month', NOW()) AND {$date_column_local} < (date_trunc('month', NOW()) + INTERVAL '1 month')";
            $label = "Este Mês";
            break;
        case 'custom':
            if ($start_date && $end_date) {
                $where_condition = "DATE({$date_column}) BETWEEN ? AND ?";
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
        case 'until_today':
            $where_condition = "{$date_column_local} <= NOW()";
            $label = "Até Hoje";
            break;
        case 'all_including_future':
            $where_condition = "";
            $label = "Todas (Futuras incluídas)";
            break;
        default:
            $period = 'all_time';
            $where_condition = "";
            $params = [];
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

// --- Lógica de Carregamento de Dados para Relatórios ---
global $pdo;

// Filtros para Faturamento (Depósitos Aprovados)
$deposits_revenue_filter = get_period_query_parts('deposits_revenue_period', 'today', 'created_at');
$deposits_revenue_where = $deposits_revenue_filter['condition'] ? "AND ({$deposits_revenue_filter['condition']})" : "";

// Busca Faturamento Total (todos os tempos)
$total_revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE amount > 0 AND status = 'APPROVED'")->fetchColumn();

// Busca Faturamento por Período
$current_revenue_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE amount > 0 AND status = 'APPROVED' {$deposits_revenue_where}");
$current_revenue_stmt->execute($deposits_revenue_filter['params']);
$current_period_revenue = $current_revenue_stmt->fetchColumn();


// Filtros para Saques Aprovados (Usando a tabela withdrawals)
$approved_withdrawals_filter = get_period_query_parts('approved_withdrawals_period', 'today', 'created_at');
$approved_withdrawals_where = $approved_withdrawals_filter['condition'] ? "AND ({$approved_withdrawals_filter['condition']})" : "";

// Busca Saques Aprovados por Período (usando tabela 'withdrawals')
$current_approved_withdrawals_stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM withdrawals
    WHERE status = 'APPROVED' {$approved_withdrawals_where}
");
$current_approved_withdrawals_stmt->execute($approved_withdrawals_filter['params']);
$current_period_approved_withdrawals = $current_approved_withdrawals_stmt->fetchColumn();


// Lógica para o card de Detalhes de Depósitos (APROVADOS OU PENDENTES)
$selected_deposit_status_detail = $_GET['deposit_detail_status_filter'] ?? 'all';
$all_deposits_detail_filter = get_period_query_parts('all_deposits_detail_period', 'until_today', 't.created_at');

$deposit_detail_status_condition = "";
$deposit_detail_status_label = "Aprovados e Pendentes";

switch ($selected_deposit_status_detail) {
    case 'approved':
        $deposit_detail_status_condition = "AND t.status = 'APPROVED'";
        $deposit_detail_status_label = "Aprovados";
        break;
    case 'pending':
        $deposit_detail_status_condition = "AND t.status = 'PENDING'";
        $deposit_detail_status_label = "Pendentes";
        break;
    case 'all':
    default:
        $deposit_detail_status_condition = "AND (t.status = 'APPROVED' OR t.status = 'PENDING')";
        $deposit_detail_status_label = "Aprovados e Pendentes";
        break;
}

$all_deposits_detail_where = $all_deposits_detail_filter['condition'] ? "AND ({$all_deposits_detail_filter['condition']})" : "";

// Contagem total para paginação do card de detalhes de depósitos
$total_deposits_detail_count_stmt = $pdo->prepare("
    SELECT COUNT(t.id)
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.amount > 0 AND t.withdrawal_id IS NULL {$deposit_detail_status_condition} {$all_deposits_detail_where}
");
$total_deposits_detail_count_stmt->execute($all_deposits_detail_filter['params']);
$total_deposits_detail = $total_deposits_detail_count_stmt->fetchColumn();

// Paginação para o card de detalhes de depósitos
$limit_deposits_detail_results = 5;
$deposits_detail_pagination = get_pagination_params($total_deposits_detail, $limit_deposits_detail_results, 'deposits_detail_page');

// BUSCA AS TRANSAÇÕES PARA O CARD DE DETALHES DE DEPÓSITOS (AGORA COM EMAIL)
// Adicionada condição 't.withdrawal_id IS NULL' para garantir que sejam APENAS depósitos e não transações de saque.
$deposits_detail_stmt = $pdo->prepare("
    SELECT
        t.id, t.amount, t.status, t.created_at, u.email as user_email, u.id as user_id, u.name as user_name
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.amount > 0 AND t.withdrawal_id IS NULL {$deposit_detail_status_condition} {$all_deposits_detail_where}
    ORDER BY t.created_at DESC
    LIMIT {$limit_deposits_detail_results} OFFSET {$deposits_detail_pagination['offset']}
");
$deposits_detail_stmt->execute($all_deposits_detail_filter['params']);
$deposits_detail_results = $deposits_detail_stmt->fetchAll(PDO::FETCH_ASSOC);


// --- Lógica para o Card de Relatórios de Saques ---
$selected_withdrawal_status_detail = $_GET['withdrawal_detail_status_filter'] ?? 'all';
$withdrawals_detail_filter = get_period_query_parts('withdrawals_detail_period', 'until_today', 'w.created_at');

$withdrawal_detail_status_condition = "";
$withdrawal_detail_status_label = "Todos os Saques";

switch ($selected_withdrawal_status_detail) {
    case 'approved':
        $withdrawal_detail_status_condition = "AND w.status = 'APPROVED'";
        $withdrawal_detail_status_label = "Aprovados";
        break;
    case 'pending':
        $withdrawal_detail_status_condition = "AND w.status = 'PENDING'";
        $withdrawal_detail_status_label = "Pendentes";
        break;
    case 'rejected':
        $withdrawal_detail_status_condition = "AND w.status = 'REJECTED'";
        $withdrawal_detail_status_label = "Negados";
        break;
    case 'all':
    default:
        $withdrawal_detail_status_condition = ""; // Sem condição específica para status
        $withdrawal_detail_status_label = "Todos";
        break;
}

$withdrawals_detail_where = $withdrawals_detail_filter['condition'] ? "AND ({$withdrawals_detail_filter['condition']})" : "";

// Contagem total para paginação do card de detalhes de saques
$total_withdrawals_detail_count_stmt = $pdo->prepare("
    SELECT COUNT(w.id)
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    WHERE 1=1 {$withdrawal_detail_status_condition} {$withdrawals_detail_where}
");
$total_withdrawals_detail_count_stmt->execute($withdrawals_detail_filter['params']);
$total_withdrawals_detail = $total_withdrawals_detail_count_stmt->fetchColumn();

// Paginação para o card de detalhes de saques
$limit_withdrawals_detail_results = 5;
$withdrawals_detail_pagination = get_pagination_params($total_withdrawals_detail, $limit_withdrawals_detail_results, 'withdrawals_detail_page');

// BUSCA AS TRANSAÇÕES DE SAQUE PARA O NOVO CARD
$withdrawals_detail_stmt = $pdo->prepare("
    SELECT
        w.id, w.amount, w.status, w.created_at, u.email as user_email, u.name as user_name, u.id as user_id
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    WHERE 1=1 {$withdrawal_detail_status_condition} {$withdrawals_detail_where}
    ORDER BY w.created_at DESC
    LIMIT {$limit_withdrawals_detail_results} OFFSET {$withdrawals_detail_pagination['offset']}
");
$withdrawals_detail_stmt->execute($withdrawals_detail_filter['params']);
$withdrawals_detail_results = $withdrawals_detail_stmt->fetchAll(PDO::FETCH_ASSOC);


// --- Dados para o Card de Estatísticas de Usuários ---

// Filtros para Novos Usuários
$new_users_filter = get_period_query_parts('new_users_period', 'today', 'created_at'); // 'created_at' aqui é da tabela 'users'
$new_users_where = $new_users_filter['condition'] ? "AND ({$new_users_filter['condition']})" : "";
$new_users_params = $new_users_filter['params'];

// Contagem de Novos Usuários no Período
$new_users_count_stmt = $pdo->prepare("SELECT COUNT(id) FROM users WHERE 1=1 {$new_users_where}");
$new_users_count_stmt->execute($new_users_params);
$new_users_in_period = $new_users_count_stmt->fetchColumn();

// Total de Usuários Cadastrados (geral)
$total_registered_users = $pdo->query("SELECT COUNT(id) FROM users")->fetchColumn();

// Total de Depósitos Aprovados (contagem de transações para o período selecionado)
$approved_deposits_count_stmt = $pdo->prepare("SELECT COUNT(id) FROM transactions WHERE status = 'APPROVED' AND amount > 0 AND withdrawal_id IS NULL {$new_users_where}");
$approved_deposits_count_stmt->execute($new_users_params);
$users_with_approved_deposits_count = $approved_deposits_count_stmt->fetchColumn();

// Total de Depósitos Pendentes (contagem de transações para o período selecionado)
$pending_deposits_count_stmt = $pdo->prepare("SELECT COUNT(id) FROM transactions WHERE status = 'PENDING' AND amount > 0 AND withdrawal_id IS NULL {$new_users_where}");
$pending_deposits_count_stmt->execute($new_users_params);
$users_with_pending_deposits_count = $pending_deposits_count_stmt->fetchColumn();

// Usuários que se cadastraram mas não depositaram (usuários sem NENHUM depósito, de qualquer status ou tipo)
// Mantido sem filtro de período, pois normalmente é uma métrica global.
$users_no_deposits_count = $pdo->query("
    SELECT COUNT(u.id)
    FROM users u
    LEFT JOIN transactions t ON u.id = t.user_id AND t.amount > 0 AND t.withdrawal_id IS NULL
    WHERE t.id IS NULL
")->fetchColumn();

// Usuários Bloqueados (Pegar detalhes para o modal)
$blocked_users_details_stmt = $pdo->query("SELECT id, name, email FROM users WHERE is_blocked = TRUE");
$blocked_users_list = $blocked_users_details_stmt->fetchAll(PDO::FETCH_ASSOC);
$blocked_users_count = count($blocked_users_list);


// Usuários Administradores (ASSUMIDAS COMO DADOS FIXOS OU COLUNA 'is_admin' FALSE)
// Se você tem uma coluna 'is_admin' ou 'role', use-a aqui.
$admin_users_count = 0; // Por padrão, se não tiver 'is_admin' ou 'role' para identificar.
// Exemplo se tiver 'is_admin' como BOOLEAN:
// $admin_users_count = $pdo->query("SELECT COUNT(id) FROM users WHERE is_admin = TRUE")->fetchColumn();
// Exemplo se tiver 'role' como 'admin':
// $admin_users_count = $pdo->query("SELECT COUNT(id) FROM users WHERE role = 'admin'")->fetchColumn();


// --- Dados para o Novo Card de Relatório de Afiliados ---

// Contagem de usuários que indicaram alguém (ou seja, são referenciadores)
$users_who_referred_count = $pdo->query("SELECT COUNT(DISTINCT referrer_id) FROM users WHERE referrer_id IS NOT NULL")->fetchColumn();

// Total de afiliados "ativos" globalmente (com pelo menos 1 depósito aprovado e que são referenciados)
$total_active_affiliates_globally = $pdo->query("SELECT COUNT(DISTINCT a.id) FROM users a JOIN transactions t ON a.id = t.user_id WHERE t.status = 'APPROVED' AND t.amount > 0 AND t.withdrawal_id IS NULL AND a.referrer_id IS NOT NULL")->fetchColumn();

// Top 5 usuários com mais afiliados ATIVOS (com pelo menos 1 depósito aprovado)
// Nota: a.id é o id do afiliado, u.id é o id do referenciador
$top_referrers_stmt = $pdo->query("
    SELECT u.id, u.name, u.email, COUNT(DISTINCT a.id) AS active_affiliates_count
    FROM users u
    JOIN users a ON u.id = a.referrer_id
    JOIN transactions t ON a.id = t.user_id
    WHERE t.status = 'APPROVED' AND t.amount > 0 AND t.withdrawal_id IS NULL
    GROUP BY u.id, u.name, u.email
    ORDER BY active_affiliates_count DESC
    LIMIT 5
");
$top_referrers = $top_referrers_stmt->fetchAll(PDO::FETCH_ASSOC);


// --- Dados para Gráfico de Novos Usuários no Mês (Original) ---
$users_stats_stmt = $pdo->query("SELECT COUNT(CASE WHEN timezone('America/Sao_Paulo', u.created_at)::date = timezone('America/Sao_Paulo', NOW())::date THEN 1 END) AS users_today, COUNT(CASE WHEN timezone('America/Sao_Paulo', u.created_at)::date >= date_trunc('month', timezone('America/Sao_Paulo', NOW())) THEN 1 END) AS users_this_month FROM users u");
$stats_users = $users_stats_stmt->fetch(PDO::FETCH_ASSOC);
$users_month_other_days = $stats_users['users_this_month'] - $stats_users['users_today'];

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Relatórios</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* Variáveis CSS globais (copiadas do dashboard/user_details) */
        :root {
            --bg-dark: #0D1117; --bg-light: #161B22; --border-color: #30363D;
            --text-primary: #c9d1d9; --text-secondary: #8b949e;
            --accent-blue: #58A6FF; --accent-green: #3FB950;
            --accent-red: #E24C4C;
            --accent-yellow: #FFC107;
            --user-blue: #00BFFF; /* Um azul para stats de usuário */
            --user-green: #6EEB83; /* Um verde mais claro */
            --affiliate-orange: #FF8C00; /* Laranja para afiliados */
        }

        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; margin: 0; background-color: var(--bg-dark); color: var(--text-primary); }

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

        /* Estilos de Cards para Relatórios */
        .report-grid-area {
            display: grid;
            /* Definindo a grid e alocando as áreas dos cards */
            grid-template-areas:
                "total_revenue          faturamento_period  saques_aprovados"
                "deposits_details       users_stats         users_stats"
                "affiliate_report       withdrawals_details withdrawals_details"; /* Alterado: agora withdrawals_details ocupa 2 colunas */
            grid-template-columns: 1fr 1fr 1fr; /* 3 colunas */
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* Define as áreas dos cards na grade */
        .report-card.total-revenue { grid-area: total_revenue; }
        .report-card.faturamento-period { grid-area: faturamento_period; }
        .report-card.saques-aprovados { grid-area: saques_aprovados; }
        .report-card.deposits-details { grid-area: deposits_details; }
        .report-card.users-stats { grid-area: users_stats; }
        .chart-container.users-chart { grid-area: users_chart; } /* Esta área não está mais na grid principal, será dentro do users_stats */
        .report-card.affiliate-report { grid-area: affiliate_report; }
        .report-card.withdrawals-details { grid-area: withdrawals_details; } /* Nova área */


        /* Responsividade para a grid de relatórios */
        @media (max-width: 1200px) {
            .report-grid-area {
                grid-template-areas:
                    "total_revenue          faturamento_period"
                    "saques_aprovados       saques_aprovados"
                    "deposits_details       deposits_details"
                    "users_stats            users_stats"
                    "affiliate_report       affiliate_report"
                    "withdrawals_details    withdrawals_details"; /* Em telas menores, ocupa a largura total */
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 768px) {
            .report-grid-area {
                grid-template-areas:
                    "total_revenue"
                    "faturamento_period"
                    "saques_aprovados"
                    "deposits_details"
                    "users_stats"
                    "affiliate_report"
                    "withdrawals_details"; /* Em telas menores, ocupa a largura total */
                grid-template-columns: 1fr;
            }
        }

        .report-card {
            background-color: var(--bg-light);
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .report-card-title {
            font-size: 1.1rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            border-bottom: 1px dashed var(--border-color);
            padding-bottom: 0.8rem;
            width: 100%;
            justify-content: space-between;
        }
        .report-card-title.with-subfilter {
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }
        .report-card-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 0.5rem;
        }
        .report-card-value.red {
             color: var(--accent-red);
        }
        .report-card-details {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        /* Estilos dos filtros de período */
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
        /* Estilo para o seletor de status (reutiliza .card-filter) */
        .status-select-filter {
            display: flex;
            gap: 5px;
            background-color: var(--bg-dark);
            padding: 4px;
            border-radius: 6px;
            margin-top: 5px;
            width: fit-content;
        }
        .status-select-filter a {
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .status-select-filter a:hover {
            background-color: #30363d;
            color: var(--text-primary);
        }
        .status-select-filter a.active {
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

        /* Estilos para Gráficos */
        .chart-container {
            background-color: var(--bg-light);
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            text-align: center;
            max-width: 400px;
            width: 100%;
            margin-left: auto;
            margin-right: auto;
        }
        .chart-container.users-chart h2 {
            font-size: 1.3rem;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            border-bottom: 1px dashed var(--border-color);
            padding-bottom: 0.8rem;
        }
        .chart-canvas-wrapper {
            width: 100%;
            height: 0;
            padding-bottom: 100%;
            position: relative;
        }
        .chart-canvas-wrapper canvas {
            position: absolute;
            width: 100%;
            height: 100%;
        }

        /* Estilos para Tabela de Transações no Card de Detalhes de Depósitos */
        .deposits-detail-table-wrapper, .affiliate-table-wrapper, .withdrawals-detail-table-wrapper { /* Adicionado .withdrawals-detail-table-wrapper */
            margin-top: 1rem;
            flex-grow: 1;
            overflow-y: auto;
            max-height: 250px;
            padding-right: 5px;
        }
        .deposits-detail-table-wrapper::-webkit-scrollbar, .affiliate-table-wrapper::-webkit-scrollbar, .withdrawals-detail-table-wrapper::-webkit-scrollbar { width: 8px; }
        .deposits-detail-table-wrapper::-webkit-scrollbar-track, .affiliate-table-wrapper::-webkit-scrollbar-track, .withdrawals-detail-table-wrapper::-webkit-scrollbar-track { background: var(--bg-dark); border-radius: 10px; }
        .deposits-detail-table-wrapper::-webkit-scrollbar-thumb, .affiliate-table-wrapper::-webkit-scrollbar-thumb, .withdrawals-detail-table-wrapper::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 10px; }
        .deposits-detail-table-wrapper::-webkit-scrollbar-thumb:hover, .affiliate-table-wrapper::-webkit-scrollbar-thumb:hover, .withdrawals-detail-table-wrapper::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }

        .deposits-detail-table, .affiliate-table, .withdrawals-detail-table { /* Adicionado .withdrawals-detail-table */
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            text-align: left;
        }
        .deposits-detail-table th, .deposits-detail-table td,
        .affiliate-table th, .affiliate-table td,
        .withdrawals-detail-table th, .withdrawals-detail-table td { /* Adicionado .withdrawals-detail-table th/td */
            padding: 8px 10px;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }
        .deposits-detail-table thead tr, .affiliate-table thead tr, .withdrawals-detail-table thead tr { background-color: var(--bg-dark); } /* Adicionado .withdrawals-detail-table thead tr */
        .deposits-detail-table th, .affiliate-table th, .withdrawals-detail-table th { /* Adicionado .withdrawals-detail-table th */
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
        }
        .deposits-detail-table tbody tr:hover, .affiliate-table tbody tr:hover, .withdrawals-detail-table tbody tr:hover { background-color: #2a3038; } /* Adicionado .withdrawals-detail-table tbody tr */
        .deposits-detail-table tbody tr:last-child td, .affiliate-table tbody tr:last-child td, .withdrawals-detail-table tbody tr:last-child td { border-bottom: none; } /* Adicionado .withdrawals-detail-table tbody tr:last-child td */
        .deposits-detail-table .status-cell, .affiliate-table .status-cell, .withdrawals-detail-table .status-cell { font-size: 0.75rem; } /* Adicionado .withdrawals-detail-table .status-cell */

        /* Cores para status */
        .status-cell.status-approved { color: var(--accent-green); font-weight: 600; }
        .status-cell.status-pending { color: var(--accent-yellow); font-weight: 600; }
        .status-cell.status-rejected { color: var(--accent-red); font-weight: 600; }
        .status-cell.status-processing { color: var(--accent-blue); font-weight: 600; } /* Se tiver status 'processing' */


        .deposits-detail-table .user-link, .affiliate-table .user-link, .withdrawals-detail-table .user-link { /* Adicionado .withdrawals-detail-table .user-link */
            color: var(--accent-blue);
            text-decoration: none;
            transition: color 0.2s;
        }
        .deposits-detail-table .user-link:hover, .affiliate-table .user-link:hover, .withdrawals-detail-table .user-link:hover { text-decoration: underline; } /* Adicionado .withdrawals-detail-table .user-link */

        /* Paginação específica para as tabelas de detalhes */
        .deposits-detail-pagination, .withdrawals-detail-pagination { /* Agrupado, Adicionado .withdrawals-detail-pagination */
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed var(--border-color);
        }
        .deposits-detail-pagination a, .deposits-detail-pagination span,
        .withdrawals-detail-pagination a, .withdrawals-detail-pagination span { /* Agrupado */
            background-color: var(--bg-dark);
            color: var(--text-secondary);
            padding: 6px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            border: 1px solid var(--border-color);
        }
        .deposits-detail-pagination a:hover, .withdrawals-detail-pagination a:hover { background-color: #2a3038; color: var(--text-primary); } /* Agrupado */
        .deposits-detail-pagination span.current-page, .withdrawals-detail-pagination span.current-page { background-color: var(--accent-blue); color: #fff; border-color: var(--accent-blue); font-weight: 600; } /* Agrupado */
        .deposits-detail-pagination span.disabled, .withdrawals-detail-pagination span.disabled { opacity: 0.5; cursor: not-allowed; } /* Agrupado */
        .no-records-found { text-align: center; color: var(--text-secondary); padding: 20px; font-style: italic; } /* Renomeado para ser mais genérico */

        /* NOVO CARD: Estatísticas de Usuários */
        .user-stats-card, .affiliate-report-card { /* Adicionado affiliate-report-card aqui */
            background-color: var(--bg-light);
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .user-stats-card h3, .affiliate-report-card h3 { /* Adicionado affiliate-report-card h3 */
            font-size: 1.3rem;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            border-bottom: 1px dashed var(--border-color);
            padding-bottom: 0.8rem;
            width: 100%;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            justify-content: space-between;
        }
        .user-stats-card-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 0.8rem;
            width: 100%;
            margin-bottom: 1rem;
        }
        .user-stats-metric-item {
            background-color: var(--bg-dark);
            padding: 0.8rem 1rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }
        .user-stats-metric-item strong {
            display: block;
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 0.2rem;
        }
        .user-stats-metric-item span {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--user-blue);
        }
        .user-stats-metric-item.green span { color: var(--user-green); }
        .user-stats-metric-item.red span { color: var(--accent-red); }
        .user-stats-metric-item.yellow span { color: var(--accent-yellow); }
        .user-stats-metric-item.highlight span { color: var(--accent-green); }

        /* Contêiner para o gráfico de pizza dentro do card de estatísticas */
        .user-stats-card .chart-wrapper {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px dashed var(--border-color);
            width: 100%;
            text-align: center;
        }
        .user-stats-card .chart-wrapper .chart-title {
            font-size: 1rem;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        /* Estilos do Modal para Usuários Bloqueados */
        .blocked-users-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1001; /* Acima do modal de confirmação */
            visibility: hidden;
            opacity: 0;
            transition: visibility 0s, opacity 0.3s ease-in-out;
        }
        .blocked-users-modal.show {
            visibility: visible;
            opacity: 1;
        }
        .blocked-modal-content {
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 2rem;
            width: 90%;
            max-width: 500px; /* Um pouco maior para a lista de usuários */
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            text-align: left;
            position: relative;
        }
        .blocked-modal-content h3 {
            color: var(--accent-red);
            margin-top: 0;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .blocked-modal-list {
            max-height: 300px; /* Limita a altura para scroll */
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 10px;
            background-color: var(--bg-dark);
        }
        .blocked-modal-list p {
            margin: 0.5rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 1px dashed rgba(var(--border-color), 0.5);
            color: var(--text-primary);
        }
        .blocked-modal-list p:last-child {
            border-bottom: none;
        }
        .blocked-modal-list span.email {
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        .blocked-modal-close {
            background-color: var(--accent-blue);
            color: #fff;
            padding: 8px 15px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.2s;
            margin-top: 1.5rem;
            display: block; /* Ocupa toda a largura */
            margin-left: auto;
            margin-right: auto;
        }
        .blocked-modal-close:hover {
            background-color: #4a90e2;
        }
        /* Estilo para o novo card de afiliados */
        .affiliate-report-card .user-stats-metric-item span {
            color: var(--affiliate-orange); /* Cor para os números de afiliados */
        }
        .affiliate-report-card h4 {
            color: var(--text-primary);
        }
            /* ---------------------------------------------------- */
    /* RESPONSIVIDADE */
    /* ---------------------------------------------------- */

    /* Desktop/Tablet (de 1200px para baixo) */
    @media (max-width: 1200px) {
        .report-grid-area {
            grid-template-areas:
                "total_revenue faturamento_period"
                "saques_aprovados saques_aprovados"
                "deposits_details deposits_details"
                "users_stats users_stats"
                "affiliate_report affiliate_report"
                "withdrawals_details withdrawals_details";
            grid-template-columns: 1fr 1fr;
        }
        .report-card-title {
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }
        .card-filter {
            flex-wrap: wrap;
        }
        .user-stats-card .chart-wrapper {
             /* Remove o espaço do gráfico para que não empurre os cards para baixo */
            margin-top: 1rem;
            padding-top: 1rem;
        }
        .user-stats-card-metrics {
            grid-template-columns: 1fr; /* Em telas menores, as métricas ficam em uma coluna */
        }
    }

    /* Tablet (de 992px para baixo) */
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
        .report-grid-area {
            grid-template-areas:
                "total_revenue"
                "faturamento_period"
                "saques_aprovados"
                "deposits_details"
                "users_stats"
                "affiliate_report"
                "withdrawals_details";
            grid-template-columns: 1fr;
        }
        /* Ajusta o layout da tabela dentro dos cards para evitar quebras */
        .deposits-detail-table-wrapper, .affiliate-table-wrapper, .withdrawals-detail-table-wrapper {
            max-height: 200px;
        }
    }

    /* Mobile (de 768px para baixo) */
    @media (max-width: 768px) {
        .dashboard-grid {
            padding: 1rem;
        }
        .sidebar, .report-card {
            padding: 1rem;
        }
        h1, h2, .report-card-title {
            font-size: 1.2rem;
        }
        .report-card-title {
            padding-bottom: 0.5rem;
        }
        .report-card-value {
            font-size: 2rem;
        }
        .report-card-details {
            font-size: 0.8rem;
        }
        .deposits-detail-table th, .deposits-detail-table td,
        .affiliate-table th, .affiliate-table td,
        .withdrawals-detail-table th, .withdrawals-detail-table td {
            font-size: 0.75rem;
            padding: 6px 8px;
        }
        .status-cell {
            font-size: 0.7rem;
        }
    }

    /* Mobile (de 480px para baixo) */
    @media (max-width: 480px) {
        .dashboard-grid {
            padding: 0.5rem;
            gap: 0.5rem;
        }
        .sidebar, .report-card {
            padding: 0.75rem;
        }
        .report-card-title {
            font-size: 1rem;
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }
        .report-card-value {
            font-size: 1.8rem;
        }
        .card-filter, .status-select-filter {
            flex-wrap: wrap;
        }
        .user-stats-metric-item {
            font-size: 0.9rem;
            padding: 0.6rem;
        }
        .user-stats-metric-item strong {
            font-size: 0.8rem;
        }
        .user-stats-metric-item span {
            font-size: 1.2rem;
        }
        .blocked-modal-content {
            width: 95%;
            padding: 1rem;
        }
        .blocked-modal-content h3 {
            font-size: 1.2rem;
        }
        .blocked-modal-close {
            width: 100%;
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
                <h1>Relatórios Financeiros e de Usuários</h1>
            </div>

            <div class="report-grid-area">
                <div class="report-card total-revenue">
                    <div class="report-card-title">
                        <span><i class="bi bi-coin"></i> Faturamento Total</span>
                    </div>
                    <span class="report-card-value">R$ <?= number_format($total_revenue, 2, ',', '.') ?></span>
                    <span class="report-card-details">Total arrecadado em depósitos aprovados (todos os tempos).</span>
                </div>

                <div class="report-card faturamento-period">
                    <div class="report-card-title">
                        <span><i class="bi bi-currency-dollar"></i> Faturamento no Período</span>
                        <div class="card-filter">
                            <a href="?<?= http_build_query(array_merge($_GET, ['deposits_revenue_period' => 'today', 'deposits_revenue_period_start' => null, 'deposits_revenue_period_end' => null])) ?>"
                               class="<?= $deposits_revenue_filter['period'] === 'today' ? 'active' : '' ?>">Hoje</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['deposits_revenue_period' => 'week', 'deposits_revenue_period_start' => null, 'deposits_revenue_period_end' => null])) ?>"
                               class="<?= $deposits_revenue_filter['period'] === 'week' ? 'active' : '' ?>">Semana</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['deposits_revenue_period' => 'month', 'deposits_revenue_period_start' => null, 'deposits_revenue_period_end' => null])) ?>"
                               class="<?= $deposits_revenue_filter['period'] === 'month' ? 'active' : '' ?>">Mês</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['deposits_revenue_period' => 'all_time', 'deposits_revenue_period_start' => null, 'deposits_revenue_period_end' => null])) ?>"
                               class="<?= $deposits_revenue_filter['period'] === 'all_time' ? 'active' : '' ?>">Todo Período</a>
                            <a href="#" class="date-picker-trigger" data-param-prefix="deposits_revenue_period">Data</a>
                        </div>
                    </div>
                    <span class="report-card-value">R$ <?= number_format($current_period_revenue, 2, ',', '.') ?></span>
                    <span class="report-card-details">Faturamento de depósitos aprovados: <strong><?= $deposits_revenue_filter['label'] ?></strong></span>
                </div>

                <div class="report-card saques-aprovados">
                    <div class="report-card-title">
                        <span><i class="bi bi-cash-coin"></i> Saques Aprovados</span>
                        <div class="card-filter">
                            <a href="?<?= http_build_query(array_merge($_GET, ['approved_withdrawals_period' => 'today', 'approved_withdrawals_period_start' => null, 'approved_withdrawals_period_end' => null])) ?>"
                               class="<?= $approved_withdrawals_filter['period'] === 'today' ? 'active' : '' ?>">Hoje</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['approved_withdrawals_period' => 'week', 'approved_withdrawals_period_start' => null, 'approved_withdrawals_period_end' => null])) ?>"
                               class="<?= $approved_withdrawals_filter['period'] === 'week' ? 'active' : '' ?>">Semana</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['approved_withdrawals_period' => 'month', 'approved_withdrawals_period_start' => null, 'approved_withdrawals_period_end' => null])) ?>"
                               class="<?= $approved_withdrawals_filter['period'] === 'month' ? 'active' : '' ?>">Mês</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['approved_withdrawals_period' => 'all_time', 'approved_withdrawals_period_start' => null, 'approved_withdrawals_period_end' => null])) ?>"
                               class="<?= $approved_withdrawals_filter['period'] === 'all_time' ? 'active' : '' ?>">Todo Período</a>
                            <a href="#" class="date-picker-trigger" data-param-prefix="approved_withdrawals_period">Data</a>
                        </div>
                    </div>
                    <span class="report-card-value red">R$ <?= number_format($current_period_approved_withdrawals, 2, ',', '.') ?></span>
                    <span class="report-card-details">Saques aprovados: <strong><?= $approved_withdrawals_filter['label'] ?></strong></span>
                </div>

                <div class="report-card deposits-details">
                    <div class="report-card-title with-subfilter">
                        <div>
                            <span><i class="bi bi-receipt-cutoff"></i> Detalhes de Depósitos</span>
                        </div>
                        <div class="status-select-filter">
                            <a href="?<?= http_build_query(array_merge($_GET, ['deposits_detail_page' => 1, 'deposit_detail_status_filter' => 'all', 'all_deposits_detail_period_start' => null, 'all_deposits_detail_period_end' => null])) ?>"
                               class="<?= $selected_deposit_status_detail === 'all' ? 'active' : '' ?>">Todos</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['deposits_detail_page' => 1, 'deposit_detail_status_filter' => 'approved', 'all_deposits_detail_period_start' => null, 'all_deposits_detail_period_end' => null])) ?>"
                               class="<?= $selected_deposit_status_detail === 'approved' ? 'active' : '' ?>">Aprovados</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['deposits_detail_page' => 1, 'deposit_detail_status_filter' => 'pending', 'all_deposits_detail_period_start' => null, 'all_deposits_detail_period_end' => null])) ?>"
                               class="<?= $selected_deposit_status_detail === 'pending' ? 'active' : '' ?>">Pendentes</a>
                        </div>
                    </div>
                    <div class="card-filter" style="margin-bottom: 1rem; width: 100%;">
                        <a href="?<?= http_build_query(array_merge($_GET, ['deposits_detail_page' => 1, 'all_deposits_detail_period' => 'today', 'all_deposits_detail_period_start' => null, 'all_deposits_detail_period_end' => null])) ?>"
                           class="<?= $all_deposits_detail_filter['period'] === 'today' ? 'active' : '' ?>">Hoje</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['deposits_detail_page' => 1, 'all_deposits_detail_period' => 'week', 'all_deposits_detail_period_start' => null, 'all_deposits_detail_period_end' => null])) ?>"
                           class="<?= $all_deposits_detail_filter['period'] === 'week' ? 'active' : '' ?>">Semana</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['deposits_detail_page' => 1, 'all_deposits_detail_period' => 'month', 'all_deposits_detail_period_start' => null, 'all_deposits_detail_period_end' => null])) ?>"
                           class="<?= $all_deposits_detail_filter['period'] === 'month' ? 'active' : '' ?>">Mês</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['all_deposits_detail_page' => 1, 'all_deposits_detail_period' => 'until_today', 'all_deposits_detail_period_start' => null, 'all_deposits_detail_period_end' => null])) ?>"
                           class="<?= $all_deposits_detail_filter['period'] === 'until_today' ? 'active' : '' ?>">Até Hoje</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['all_deposits_detail_page' => 1, 'all_deposits_detail_period' => 'all_time', 'all_deposits_detail_period_start' => null, 'all_deposits_detail_period_end' => null])) ?>"
                           class="<?= $all_deposits_detail_filter['period'] === 'all_time' ? 'active' : '' ?>">Todo Período</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['all_deposits_detail_page' => 1, 'all_deposits_detail_period' => 'all_including_future', 'all_deposits_detail_period_start' => null, 'all_deposits_detail_period_end' => null])) ?>"
                           class="<?= $all_deposits_detail_filter['period'] === 'all_including_future' ? 'active' : '' ?>">Incl. Futuras</a>
                        <a href="#" class="date-picker-trigger" data-param-prefix="all_deposits_detail_period">Data</a>
                    </div>
                    <span class="report-card-details" style="margin-bottom: 1rem;">
                        Exibindo Depósitos <strong style="color: <?= $selected_deposit_status_detail === 'approved' ? 'var(--accent-green)' : ($selected_deposit_status_detail === 'pending' ? 'var(--accent-yellow)' : 'var(--text-primary)') ?>;"><?= $deposit_detail_status_label ?></strong> (período: <strong><?= $all_deposits_detail_filter['label'] ?></strong>)
                    </span>

                    <div class="deposits-detail-table-wrapper">
                        <table class="deposits-detail-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuário</th>
                                    <th>Valor (R$)</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($deposits_detail_results)): ?>
                                    <?php foreach ($deposits_detail_results as $transaction):
                                        $transaction_date = date('d/m/Y H:i', strtotime($transaction['created_at']));
                                        $status_class = 'status-' . strtolower(str_replace(' ', '-', $transaction['status']));
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($transaction['id']) ?></td>
                                            <td><a href="index.php?page=usuarios&id=<?= $transaction['user_id'] ?>" class="user-link"><?= htmlspecialchars($transaction['user_name'] ?: $transaction['user_email']) ?></a></td>
                                            <td>R$ <?= number_format($transaction['amount'], 2, ',', '.') ?></td>
                                            <td><span class="status-cell <?= $status_class ?>"><?= htmlspecialchars($transaction['status']) ?></span></td>
                                            <td><?= $transaction_date ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="no-records-found">Nenhum depósito encontrado para os filtros selecionados.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="deposits-detail-pagination">
                        <?php if ($deposits_detail_pagination['current_page'] > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['deposits_detail_page' => $deposits_detail_pagination['current_page'] - 1])) ?>">Anterior</a>
                        <?php else: ?>
                            <span class="disabled">Anterior</span>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $deposits_detail_pagination['current_page'] - 2);
                        $end_page = min($deposits_detail_pagination['total_pages'], $deposits_detail_pagination['current_page'] + 2);

                        if ($start_page > 1) { echo '<span>...</span>'; }

                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $deposits_detail_pagination['current_page']): ?>
                                <span class="current-page"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['deposits_detail_page' => $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($end_page < $deposits_detail_pagination['total_pages']): echo '<span>...</span>'; endif; ?>

                        <?php if ($deposits_detail_pagination['current_page'] < $deposits_detail_pagination['total_pages']): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['deposits_detail_page' => $deposits_detail_pagination['current_page'] + 1])) ?>">Próximo</a>
                        <?php else: ?>
                            <span class="disabled">Próximo</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="report-card users-stats">
                    <h3>
                        <div><i class="bi bi-people-fill"></i> Estatísticas de Usuários</div>
                        <div class="card-filter">
                            <a href="?<?= http_build_query(array_merge($_GET, ['new_users_period' => 'today', 'new_users_period_start' => null, 'new_users_period_end' => null])) ?>"
                               class="<?= $new_users_filter['period'] === 'today' ? 'active' : '' ?>">Hoje</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['new_users_period' => 'week', 'new_users_period_start' => null, 'new_users_period_end' => null])) ?>"
                               class="<?= $new_users_filter['period'] === 'week' ? 'active' : '' ?>">Semana</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['new_users_period' => 'month', 'new_users_period_start' => null, 'new_users_period_end' => null])) ?>"
                               class="<?= $new_users_filter['period'] === 'month' ? 'active' : '' ?>">Mês</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['new_users_period' => 'all_time', 'new_users_period_start' => null, 'new_users_period_end' => null])) ?>"
                               class="<?= $new_users_filter['period'] === 'all_time' ? 'active' : '' ?>">Todo Período</a>
                            <a href="#" class="date-picker-trigger" data-param-prefix="new_users_period">Data</a>
                        </div>
                    </h3>
                    <div class="user-stats-card-metrics">
                        <div class="user-stats-metric-item highlight">
                            <strong>Total Cadastrados:</strong>
                            <span><?= number_format($total_registered_users, 0, ',', '.') ?></span>
                        </div>
                        <div class="user-stats-metric-item green">
                            <strong>Novos (<?= $new_users_filter['label'] ?>):</strong>
                            <span><?= number_format($new_users_in_period, 0, ',', '.') ?></span>
                        </div>
                        <div class="user-stats-metric-item highlight">
                            <strong>Com Depósito (Aprov.):</strong>
                            <span><?= number_format($users_with_approved_deposits_count, 0, ',', '.') ?></span>
                        </div>
                        <div class="user-stats-metric-item yellow">
                            <strong>Depósitos Pendentes:</strong>
                            <span><?= number_format($users_with_pending_deposits_count, 0, ',', '.') ?></span>
                        </div>
                        <div class="user-stats-metric-item">
                            <strong>Sem Depósito (Ativos):</strong>
                            <span><?= number_format($users_no_deposits_count, 0, ',', '.') ?></span>
                        </div>
                        <div class="user-stats-metric-item red clickable-blocked-users" data-users='<?= json_encode($blocked_users_list) ?>'>
                            <strong>Bloqueados:</strong>
                            <span><?= number_format($blocked_users_count, 0, ',', '.') ?></span>
                        </div>
                        <div class="user-stats-metric-item blue">
                            <strong>Administradores:</strong>
                            <span><?= number_format($admin_users_count, 0, ',', '.') ?></span>
                        </div>
                    </div>

                    <div class="chart-wrapper">
                        <h4 class="chart-title">Distribuição de Novos Usuários (Este Mês)</h4>
                        <div style="width: 100%; max-width: 250px; margin: auto;">
                            <canvas id="usersPieChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="report-card affiliate-report-card">
                    <h3>
                        <div><i class="bi bi-share-fill"></i> Relatório de Afiliados</div>
                    </h3>
                    <div class="user-stats-card-metrics" style="grid-template-columns: 1fr; margin-bottom: 1rem;">
                        <div class="user-stats-metric-item highlight">
                            <strong>Usuários que Indicam:</strong>
                            <span><?= number_format($users_who_referred_count, 0, ',', '.') ?></span>
                            <span class="report-card-details">(Referenciadores Únicos)</span>
                        </div>
                        <div class="user-stats-metric-item green">
                            <strong>Afiliados Ativos (Total):</strong>
                            <span><?= number_format($total_active_affiliates_globally, 0, ',', '.') ?></span>
                            <span class="report-card-details">(com pelo menos 1 depósito aprovado)</span>
                        </div>
                    </div>

                    <h4 style="font-size: 1rem; color: var(--text-primary); margin-top: 1rem; margin-bottom: 1rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 0.5rem; width: 100%;">Top 5 Referenciadores (por Afiliados Ativos)</h4>
                    <div class="affiliate-table-wrapper">
                        <table class="affiliate-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Afiliados Ativos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($top_referrers)): ?>
                                    <?php foreach ($top_referrers as $referrer): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($referrer['id']) ?></td>
                                            <td><a href="index.php?page=usuarios&id=<?= $referrer['id'] ?>" class="user-link"><?= htmlspecialchars($referrer['name']) ?></a></td>
                                            <td><?= htmlspecialchars($referrer['email']) ?></td>
                                            <td><?= number_format($referrer['active_affiliates_count'], 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="no-records-found">Nenhum referenciador encontrado com afiliados ativos.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="report-card withdrawals-details">
                    <div class="report-card-title with-subfilter">
                        <div>
                            <span><i class="bi bi-wallet-minus"></i> Detalhes de Saques</span>
                        </div>
                        <div class="status-select-filter">
                            <a href="?<?= http_build_query(array_merge($_GET, ['withdrawals_detail_page' => 1, 'withdrawal_detail_status_filter' => 'all', 'withdrawals_detail_period_start' => null, 'withdrawals_detail_period_end' => null])) ?>"
                               class="<?= $selected_withdrawal_status_detail === 'all' ? 'active' : '' ?>">Todos</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['withdrawals_detail_page' => 1, 'withdrawal_detail_status_filter' => 'approved', 'withdrawals_detail_period_start' => null, 'withdrawals_detail_period_end' => null])) ?>"
                               class="<?= $selected_withdrawal_status_detail === 'approved' ? 'active' : '' ?>">Aprovados</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['withdrawals_detail_page' => 1, 'withdrawal_detail_status_filter' => 'pending', 'withdrawals_detail_period_start' => null, 'withdrawals_detail_period_end' => null])) ?>"
                               class="<?= $selected_withdrawal_status_detail === 'pending' ? 'active' : '' ?>">Pendentes</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['withdrawals_detail_page' => 1, 'withdrawal_detail_status_filter' => 'rejected', 'withdrawals_detail_period_start' => null, 'withdrawals_detail_period_end' => null])) ?>"
                               class="<?= $selected_withdrawal_status_detail === 'rejected' ? 'active' : '' ?>">Negados</a>
                        </div>
                    </div>
                    <div class="card-filter" style="margin-bottom: 1rem; width: 100%;">
                        <a href="?<?= http_build_query(array_merge($_GET, ['withdrawals_detail_page' => 1, 'withdrawals_detail_period' => 'today', 'withdrawals_detail_period_start' => null, 'withdrawals_detail_period_end' => null])) ?>"
                           class="<?= $withdrawals_detail_filter['period'] === 'today' ? 'active' : '' ?>">Hoje</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['withdrawals_detail_page' => 1, 'withdrawals_detail_period' => 'week', 'withdrawals_detail_period_start' => null, 'withdrawals_detail_period_end' => null])) ?>"
                           class="<?= $withdrawals_detail_filter['period'] === 'week' ? 'active' : '' ?>">Semana</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['withdrawals_detail_page' => 1, 'withdrawals_detail_period' => 'month', 'withdrawals_detail_period_start' => null, 'withdrawals_detail_period_end' => null])) ?>"
                           class="<?= $withdrawals_detail_filter['period'] === 'month' ? 'active' : '' ?>">Mês</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['withdrawals_detail_page' => 1, 'withdrawals_detail_period' => 'until_today', 'withdrawals_detail_period_start' => null, 'withdrawals_detail_period_end' => null])) ?>"
                           class="<?= $withdrawals_detail_filter['period'] === 'until_today' ? 'active' : '' ?>">Até Hoje</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['withdrawals_detail_page' => 1, 'withdrawals_detail_period' => 'all_time', 'withdrawals_detail_period_start' => null, 'withdrawals_detail_period_end' => null])) ?>"
                           class="<?= $withdrawals_detail_filter['period'] === 'all_time' ? 'active' : '' ?>">Todo Período</a>
                        <a href="#" class="date-picker-trigger" data-param-prefix="withdrawals_detail_period">Data</a>
                    </div>
                    <span class="report-card-details" style="margin-bottom: 1rem;">
                        Exibindo Saques <strong style="color: <?= $selected_withdrawal_status_detail === 'approved' ? 'var(--accent-green)' : ($selected_withdrawal_status_detail === 'pending' ? 'var(--accent-yellow)' : ($selected_withdrawal_status_detail === 'rejected' ? 'var(--accent-red)' : 'var(--text-primary)')) ?>;"><?= $withdrawal_detail_status_label ?></strong> (período: <strong><?= $withdrawals_detail_filter['label'] ?></strong>)
                    </span>

                    <div class="withdrawals-detail-table-wrapper">
                        <table class="withdrawals-detail-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuário</th>
                                    <th>Valor (R$)</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($withdrawals_detail_results)): ?>
                                    <?php foreach ($withdrawals_detail_results as $withdrawal):
                                        $withdrawal_date = date('d/m/Y H:i', strtotime($withdrawal['created_at']));
                                        $status_class = 'status-' . strtolower(str_replace(' ', '-', $withdrawal['status']));
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($withdrawal['id']) ?></td>
                                            <td><a href="index.php?page=usuarios&id=<?= $withdrawal['user_id'] ?>" class="user-link"><?= htmlspecialchars($withdrawal['user_name'] ?: $withdrawal['user_email']) ?></a></td>
                                            <td>R$ <?= number_format($withdrawal['amount'], 2, ',', '.') ?></td>
                                            <td><span class="status-cell <?= $status_class ?>"><?= htmlspecialchars($withdrawal['status']) ?></span></td>
                                            <td><?= $withdrawal_date ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="no-records-found">Nenhum saque encontrado para os filtros selecionados.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="withdrawals-detail-pagination">
                        <?php if ($withdrawals_detail_pagination['current_page'] > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['withdrawals_detail_page' => $withdrawals_detail_pagination['current_page'] - 1])) ?>">Anterior</a>
                        <?php else: ?>
                            <span class="disabled">Anterior</span>
                        <?php endif; ?>

                        <?php
                        $start_page_w = max(1, $withdrawals_detail_pagination['current_page'] - 2);
                        $end_page_w = min($withdrawals_detail_pagination['total_pages'], $withdrawals_detail_pagination['current_page'] + 2);

                        if ($start_page_w > 1) { echo '<span>...</span>'; }

                        for ($i = $start_page_w; $i <= $end_page_w; $i++): ?>
                            <?php if ($i == $withdrawals_detail_pagination['current_page']): ?>
                                <span class="current-page"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['withdrawals_detail_page' => $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($end_page_w < $withdrawals_detail_pagination['total_pages']): echo '<span>...</span>'; endif; ?>

                        <?php if ($withdrawals_detail_pagination['current_page'] < $withdrawals_detail_pagination['total_pages']): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['withdrawals_detail_page' => $withdrawals_detail_pagination['current_page'] + 1])) ?>">Próximo</a>
                        <?php else: ?>
                            <span class="disabled">Próximo</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="blockedUsersModal" class="blocked-users-modal">
        <div class="blocked-modal-content">
            <h3><i class="bi bi-lock-fill"></i> Usuários Bloqueados</h3>
            <div class="blocked-modal-list" id="blockedUsersList">
                </div>
            <button type="button" class="blocked-modal-close">Fechar</button>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- LÓGICA DO CALENDÁRIO (Flatpickr) para MÚLTIPLOS FILTROS ---
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

                        // Redireciona a página para aplicar o filtro
                        window.location.href = url.toString();
                    }
                }
            });

            // --- Script do gráfico de Novos Usuários ---
            const ctx = document.getElementById('usersPieChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Cadastrados Hoje', 'Outros Dias do Mês'],
                        datasets: [{
                            data: [<?= $stats_users['users_today'] ?>, <?= $users_month_other_days ?>],
                            backgroundColor: ['#3FB950', '#58A6FF'],
                            borderColor: 'var(--bg-light)',
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                labels: {
                                    color: 'var(--text-primary)'
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed !== null) {
                                            label += new Intl.NumberFormat('pt-BR', { style: 'decimal' }).format(context.parsed);
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // --- Lógica do Modal de Usuários Bloqueados ---
            const blockedUsersMetricItem = document.querySelector('.clickable-blocked-users');
            const blockedUsersModal = document.getElementById('blockedUsersModal');
            const blockedUsersList = document.getElementById('blockedUsersList');
            const blockedModalCloseBtn = document.querySelector('.blocked-modal-close');

            if (blockedUsersMetricItem) {
                blockedUsersMetricItem.style.cursor = 'pointer'; // Indica que é clicável
                blockedUsersMetricItem.addEventListener('click', function() {
                    const usersData = JSON.parse(this.dataset.users); // Pega os dados dos usuários bloqueados

                    blockedUsersList.innerHTML = ''; // Limpa a lista anterior
                    if (usersData.length > 0) {
                        usersData.forEach(user => {
                            const p = document.createElement('p');
                            p.innerHTML = `<strong>ID: ${user.id}</strong> - ${user.name}<br><span class="email">${user.email}</span>`;
                            blockedUsersList.appendChild(p);
                        });
                    } else {
                        blockedUsersList.innerHTML = '<p style="text-align: center;">Nenhum usuário bloqueado.</p>';
                    }
                    blockedUsersModal.classList.add('show');
                });
            }

            blockedModalCloseBtn.addEventListener('click', function() {
                blockedUsersModal.classList.remove('show');
            });

            blockedUsersModal.addEventListener('click', function(event) {
                if (event.target === blockedUsersModal) {
                    blockedUsersModal.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>