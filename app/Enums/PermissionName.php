<?php

declare(strict_types=1);

namespace App\Enums;

enum PermissionName: string
{
    case DashboardView = 'dashboard.view';

    case UsersView = 'users.view';
    case UsersEdit = 'users.edit';
    case UsersUpdate = 'users.update';
    case UsersSuspend = 'users.suspend';
    case UsersSessionsView = 'users.sessions.view';
    case UsersSessionsRevoke = 'users.sessions.revoke';

    case OrdersView = 'orders.view';
    case OrdersCancel = 'orders.cancel';
    case OrdersRetryFulfillment = 'orders.retry_fulfillment';
    case OrdersRefund = 'orders.refund';

    case PaymentsView = 'payments.view';
    case PaymentsRefund = 'payments.refund';
    case PaymentsRetry = 'payments.retry';
    case PaymentRefundsView = 'payment_refunds.view';

    case ProvidersView = 'providers.view';
    case ProvidersManage = 'providers.manage';
    case ProvidersEnable = 'providers.enable';
    case ProvidersDisable = 'providers.disable';
    case ProvidersCredentialsViewMasked = 'providers.credentials.view_masked';
    case ProvidersCredentialsManage = 'providers.credentials.manage';
    case ProvidersServicesManage = 'providers.services.manage';
    case ProvidersCoverageManage = 'providers.coverage.manage';
    case ProvidersProductsSync = 'providers.products.sync';
    case ProvidersHealthRun = 'providers.health.run';

    case EsimPlansView = 'esim.plans.view';
    case EsimPlansManage = 'esim.plans.manage';
    case EsimPricesView = 'esim.prices.view';
    case EsimPricesManage = 'esim.prices.manage';
    case EsimProfilesView = 'esim.profiles.view';
    case EsimUsageView = 'esim.usage.view';
    case EsimUsageSync = 'esim.usage.sync';

    case WalletsView = 'wallets.view';
    case WalletsTransactionsView = 'wallets.transactions.view';
    case WalletsHoldsView = 'wallets.holds.view';
    case WalletsLedgerView = 'wallets.ledger.view';
    case WalletsAdjust = 'wallets.adjust';

    case WebhooksView = 'webhooks.view';
    case WebhooksRetry = 'webhooks.retry';
    case WebhooksDeadLetterView = 'webhooks.dead_letter.view';

    case AuditView = 'audit.view';

    case ProductsView = 'products.view';
    case ProductsManage = 'products.manage';
    case WalletView = 'wallet.view';
    case WalletAdjust = 'wallet.adjust';
    case ReportsView = 'reports.view';
    case SettingsManage = 'settings.manage';
    case LogsView = 'logs.view';
    case SupportManage = 'support.manage';
    case SupportTicketsView = 'support.tickets.view';
    case SupportTicketsAssign = 'support.tickets.assign';
    case SupportTicketsReply = 'support.tickets.reply';
    case SupportTicketsClose = 'support.tickets.close';

    case UsersViewDetail = 'users.view_detail';
    case UsersCreate = 'users.create';

    case OrdersViewDetail = 'orders.view_detail';
    case OrdersUpdate = 'orders.update';
    case OrdersManage = 'orders.manage';

    case EsimsView = 'esims.view';
    case EsimsViewDetail = 'esims.view_detail';
    case EsimsManage = 'esims.manage';

    case EsimProductsView = 'esim_products.view';
    case EsimProductsCreate = 'esim_products.create';
    case EsimProductsUpdate = 'esim_products.update';
    case EsimProductsDelete = 'esim_products.delete';

    case SimInventoryView = 'sim_inventory.view';
    case SimInventoryManage = 'sim_inventory.manage';

    case NetworkOperatorsView = 'network_operators.view';
    case NetworkOperatorsManage = 'network_operators.manage';

    case PaymentsViewDetail = 'payments.view_detail';
    case PaymentsManage = 'payments.manage';

    case WalletsViewDetail = 'wallets.view_detail';
    case WalletsManage = 'wallets.manage';

    case TopupsView = 'topups.view';
    case TopupsViewDetail = 'topups.view_detail';
    case TopupsManage = 'topups.manage';

    case RefundsView = 'refunds.view';
    case RefundsCreate = 'refunds.create';
    case RefundsManage = 'refunds.manage';

    case FinanceDashboardView = 'finance.dashboard.view';
    case FinanceRevenueView = 'finance.revenue.view';
    case FinanceReportsView = 'finance.reports.view';

    case TicketsView = 'tickets.view';
    case TicketsViewDetail = 'tickets.view_detail';
    case TicketsCreate = 'tickets.create';
    case TicketsUpdate = 'tickets.update';
    case TicketsAssign = 'tickets.assign';
    case TicketsClose = 'tickets.close';

    case NotificationsView = 'notifications.view';
    case NotificationsCreate = 'notifications.create';
    case NotificationsManage = 'notifications.manage';

    case PromotionsView = 'promotions.view';
    case PromotionsCreate = 'promotions.create';
    case PromotionsUpdate = 'promotions.update';
    case PromotionsDelete = 'promotions.delete';

    case StaffView = 'staff.view';
    case StaffViewDetail = 'staff.view_detail';
    case StaffCreate = 'staff.create';
    case StaffUpdate = 'staff.update';
    case StaffSuspend = 'staff.suspend';
    case StaffActivate = 'staff.activate';
    case StaffRolesManage = 'staff.roles.manage';
    case StaffPermissionsManage = 'staff.permissions.manage';

    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';
    case RolesPermissionsManage = 'roles.permissions.manage';

    case SystemSettingsView = 'system.settings.view';
    case SystemSettingsManage = 'system.settings.manage';
}
