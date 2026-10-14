<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\AdminSearchController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\EsimProfileController as AdminEsimProfileController;
use App\Http\Controllers\Api\V1\Admin\OperationalIndexController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\V1\Admin\ProviderController as AdminProviderController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\StaffController;
use App\Http\Controllers\Api\V1\Admin\SupportController as AdminSupportController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Admin\WalletController as AdminWalletController;
use App\Http\Controllers\Api\V1\Admin\WebhookController as AdminWebhookController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CountryController;
use App\Http\Controllers\Api\V1\EsimController;
use App\Http\Controllers\Api\V1\EsimPlanController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\SupportController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\Webhooks\ProviderWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
        Route::post('otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:5,1');
        Route::post('reset-password', [AuthController::class, 'resetPassword']);

        Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::get('preferences', [AuthController::class, 'preferences']);
            Route::patch('preferences', [AuthController::class, 'updatePreferences']);
            Route::get('sessions', [AuthController::class, 'sessions']);
            Route::delete('sessions/{token}', [AuthController::class, 'revokeSession']);
        });
    });

    Route::get('countries', [CountryController::class, 'index']);
    Route::get('countries/{country}', [CountryController::class, 'show']);
    Route::get('esim-plans', [EsimPlanController::class, 'index']);
    Route::get('esim-plans/{esimPlan}', [EsimPlanController::class, 'show']);

    Route::post('webhooks/providers/{provider}', ProviderWebhookController::class)
        ->middleware('throttle:120,1');

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'store'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware('throttle:sensitive');

        Route::get('esims', [EsimController::class, 'index']);
        Route::get('esims/{profile}', [EsimController::class, 'show']);
        Route::get('esims/{profile}/usage', [EsimController::class, 'usage']);
        Route::post('esims/{profile}/usage/refresh', [EsimController::class, 'refreshUsage'])->middleware('throttle:sensitive');
        Route::get('esims/{profile}/installation', [EsimController::class, 'installation']);
        Route::post('esims/{profile}/topups', [EsimController::class, 'topup'])->middleware(['throttle:sensitive', 'idempotency']);
        Route::post('esims/{profile}/renewals', [EsimController::class, 'renew'])->middleware(['throttle:sensitive', 'idempotency']);

        Route::get('wallet', [WalletController::class, 'show']);
        Route::get('wallet/transactions', [WalletController::class, 'transactions']);
        Route::post('wallet/topup', [WalletController::class, 'topup'])->middleware(['throttle:sensitive', 'idempotency']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);

        Route::get('support/tickets', [SupportController::class, 'index']);
        Route::post('support/tickets', [SupportController::class, 'store']);
        Route::get('support/tickets/{ticket}', [SupportController::class, 'show']);
        Route::post('support/tickets/{ticket}/messages', [SupportController::class, 'message']);

        Route::prefix('admin')
            ->middleware(['role:admin|super_admin|operator|finance|support,sanctum', 'ability:admin'])
            ->group(function (): void {
                Route::get('dashboard', DashboardController::class)
                    ->middleware('permission:dashboard.view,sanctum');
                Route::get('search', AdminSearchController::class)
                    ->middleware('permission:dashboard.view|users.view,sanctum');

                Route::get('users', [AdminUserController::class, 'index'])->middleware('permission:users.view,sanctum');
                Route::get('users/{user}', [AdminUserController::class, 'show'])->middleware('permission:users.view|users.view_detail,sanctum');
                Route::patch('users/{user}', [AdminUserController::class, 'update'])->middleware('permission:users.update,sanctum');
                Route::post('users/{user}/suspend', [AdminUserController::class, 'suspend'])->middleware(['permission:users.suspend,sanctum', 'throttle:sensitive']);
                Route::get('users/{user}/sessions', [AdminUserController::class, 'sessions'])->middleware('permission:users.sessions.view,sanctum');
                Route::delete('users/{user}/sessions/{token}', [AdminUserController::class, 'revokeSession'])->middleware('permission:users.sessions.revoke,sanctum');

                Route::get('providers', [AdminProviderController::class, 'index'])->middleware('permission:providers.view,sanctum');
                Route::post('providers', [AdminProviderController::class, 'store'])->middleware('permission:providers.manage,sanctum');
                Route::get('providers/{provider}', [AdminProviderController::class, 'show'])->middleware('permission:providers.view,sanctum');
                Route::patch('providers/{provider}', [AdminProviderController::class, 'update'])->middleware('permission:providers.manage,sanctum');
                Route::post('providers/{provider}/enable', [AdminProviderController::class, 'enable'])->middleware('permission:providers.enable|providers.manage,sanctum');
                Route::post('providers/{provider}/disable', [AdminProviderController::class, 'disable'])->middleware('permission:providers.disable|providers.manage,sanctum');
                Route::post('providers/{provider}/sync', [AdminProviderController::class, 'sync'])->middleware(['permission:providers.products.sync|providers.manage,sanctum', 'throttle:sensitive']);
                Route::post('providers/{provider}/health-check', [AdminProviderController::class, 'healthCheck'])->middleware(['permission:providers.health.run|providers.manage,sanctum', 'throttle:sensitive']);
                Route::get('providers/{provider}/credentials', [AdminProviderController::class, 'credentials'])->middleware('permission:providers.credentials.view_masked,sanctum');
                Route::put('providers/{provider}/credentials', [AdminProviderController::class, 'storeCredentials'])->middleware('permission:providers.credentials.manage,sanctum');
                Route::get('providers/{provider}/services', [AdminProviderController::class, 'services'])->middleware('permission:providers.view,sanctum');
                Route::put('providers/{provider}/services', [AdminProviderController::class, 'syncServices'])->middleware('permission:providers.services.manage,sanctum');
                Route::get('providers/{provider}/products', [AdminProviderController::class, 'products'])->middleware('permission:providers.view,sanctum');
                Route::get('providers/{provider}/packages', [AdminProviderController::class, 'packages'])->middleware('permission:providers.view|esim.plans.view|esim_products.view,sanctum');
                Route::get('providers/{provider}/countries', [AdminProviderController::class, 'countries'])->middleware('permission:providers.view,sanctum');
                Route::put('providers/{provider}/countries', [AdminProviderController::class, 'syncCountries'])->middleware('permission:providers.coverage.manage,sanctum');
                Route::get('providers/{provider}/balance', [AdminProviderController::class, 'balance'])->middleware('permission:providers.view,sanctum');
                Route::get('providers/{provider}/discount-rules', [AdminProviderController::class, 'discountRules'])->middleware('permission:providers.view,sanctum');
                Route::get('providers/{provider}/webhook', [AdminProviderController::class, 'showWebhook'])->middleware('permission:providers.view,sanctum');
                Route::put('providers/{provider}/webhook', [AdminProviderController::class, 'saveWebhook'])->middleware(['permission:providers.manage,sanctum', 'throttle:sensitive']);

                Route::get('esim/vendor-packages', [AdminCatalogController::class, 'vendorPackages'])->middleware('permission:esim.plans.view|esim_products.view,sanctum');
                Route::get('esim/plans', [AdminCatalogController::class, 'index'])->middleware('permission:esim.plans.view|esim_products.view,sanctum');
                Route::post('esim/plans', [AdminCatalogController::class, 'store'])->middleware('permission:esim.plans.manage|esim_products.create|esim_products.update,sanctum');
                Route::get('esim/plans/{plan}', [AdminCatalogController::class, 'show'])->middleware('permission:esim.plans.view|esim_products.view,sanctum');
                Route::patch('esim/plans/{plan}', [AdminCatalogController::class, 'update'])->middleware('permission:esim.plans.manage|esim_products.update,sanctum');
                Route::get('esim/plans/{plan}/prices', [AdminCatalogController::class, 'prices'])->middleware('permission:esim.prices.view,sanctum');
                Route::post('esim/plans/{plan}/prices', [AdminCatalogController::class, 'storePrice'])->middleware('permission:esim.prices.manage,sanctum');
                Route::post('esim/prices/{price}/deactivate', [AdminCatalogController::class, 'deactivatePrice'])->middleware('permission:esim.prices.manage,sanctum');
                Route::put('esim/plans/{plan}/countries', [AdminCatalogController::class, 'syncCountries'])->middleware('permission:esim.plans.manage,sanctum');
                Route::put('esim/plans/{plan}/networks', [AdminCatalogController::class, 'syncNetworks'])->middleware('permission:esim.plans.manage,sanctum');

                Route::get('esim/profiles', [AdminEsimProfileController::class, 'index'])->middleware('permission:esim.profiles.view|esims.view,sanctum');
                Route::get('esim/profiles/{profile}', [AdminEsimProfileController::class, 'show'])->middleware('permission:esim.profiles.view|esims.view,sanctum');
                Route::get('esim/profiles/{profile}/usage', [AdminEsimProfileController::class, 'usage'])->middleware('permission:esim.usage.view|esims.view_detail,sanctum');
                Route::post('esim/profiles/{profile}/usage/sync', [AdminEsimProfileController::class, 'syncUsage'])->middleware('permission:esim.usage.sync|esims.manage,sanctum');
                Route::post('esim/profiles/{profile}/cancel', [AdminEsimProfileController::class, 'cancel'])->middleware(['permission:esims.manage,sanctum', 'throttle:sensitive']);
                Route::post('esim/profiles/{profile}/suspend', [AdminEsimProfileController::class, 'suspend'])->middleware(['permission:esims.manage,sanctum', 'throttle:sensitive']);
                Route::post('esim/profiles/{profile}/unsuspend', [AdminEsimProfileController::class, 'unsuspend'])->middleware(['permission:esims.manage,sanctum', 'throttle:sensitive']);
                Route::post('esim/profiles/{profile}/revoke', [AdminEsimProfileController::class, 'revoke'])->middleware(['permission:esims.manage,sanctum', 'throttle:sensitive']);
                Route::post('esim/profiles/{profile}/sms', [AdminEsimProfileController::class, 'sendSms'])->middleware(['permission:esims.manage,sanctum', 'throttle:sensitive']);

                Route::get('orders', [AdminOrderController::class, 'index'])->middleware('permission:orders.view,sanctum');
                Route::get('orders/{order}', [AdminOrderController::class, 'show'])->middleware('permission:orders.view|orders.view_detail,sanctum');
                Route::post('orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->middleware(['permission:orders.cancel|orders.manage,sanctum', 'throttle:sensitive']);
                Route::post('orders/{order}/retry-fulfillment', [AdminOrderController::class, 'retryFulfillment'])->middleware(['permission:orders.retry_fulfillment|orders.manage,sanctum', 'throttle:sensitive']);

                Route::get('payments', [AdminPaymentController::class, 'index'])->middleware('permission:payments.view,sanctum');
                Route::get('payments/{payment}', [AdminPaymentController::class, 'show'])->middleware('permission:payments.view|payments.view_detail,sanctum');
                Route::get('payments/{payment}/attempts', [AdminPaymentController::class, 'attempts'])->middleware('permission:payments.view|payments.view_detail,sanctum');
                Route::get('payments/{payment}/refunds', [AdminPaymentController::class, 'refunds'])->middleware('permission:payment_refunds.view|refunds.view,sanctum');
                Route::post('payments/{payment}/refunds', [AdminPaymentController::class, 'storeRefund'])->middleware(['permission:payments.refund|refunds.create|refunds.manage,sanctum', 'throttle:sensitive', 'idempotency']);
                Route::get('refunds', [AdminPaymentController::class, 'refundIndex'])->middleware('permission:payment_refunds.view|refunds.view,sanctum');

                Route::get('support/tickets', [AdminSupportController::class, 'index'])->middleware('permission:support.tickets.view|tickets.view,sanctum');
                Route::get('support/tickets/{ticket}', [AdminSupportController::class, 'show'])->middleware('permission:support.tickets.view|tickets.view|tickets.view_detail,sanctum');
                Route::post('support/tickets/{ticket}/assign', [AdminSupportController::class, 'assign'])->middleware('permission:support.tickets.assign|tickets.assign,sanctum');
                Route::post('support/tickets/{ticket}/messages', [AdminSupportController::class, 'message'])->middleware('permission:support.tickets.reply|tickets.update,sanctum');
                Route::post('support/tickets/{ticket}/close', [AdminSupportController::class, 'close'])->middleware(['permission:support.tickets.close|tickets.close,sanctum', 'throttle:sensitive']);

                Route::get('wallets', [AdminWalletController::class, 'index'])->middleware('permission:wallets.view,sanctum');
                Route::get('wallets/{wallet}', [AdminWalletController::class, 'show'])->middleware('permission:wallets.view|wallets.view_detail,sanctum');
                Route::get('wallets/{wallet}/transactions', [AdminWalletController::class, 'transactions'])->middleware('permission:wallets.transactions.view|wallets.view_detail,sanctum');
                Route::get('wallets/{wallet}/holds', [AdminWalletController::class, 'holds'])->middleware('permission:wallets.holds.view|wallets.view_detail,sanctum');
                Route::get('wallets/{wallet}/ledger', [AdminWalletController::class, 'ledger'])->middleware('permission:wallets.ledger.view|wallets.view_detail,sanctum');
                Route::post('wallets/{wallet}/adjustments', [AdminWalletController::class, 'adjust'])->middleware(['permission:wallets.adjust|wallets.manage,sanctum', 'throttle:sensitive']);

                Route::get('webhooks/dead-letter', [AdminWebhookController::class, 'deadLetter'])->middleware('permission:webhooks.dead_letter.view,sanctum');
                Route::get('webhooks', [AdminWebhookController::class, 'index'])->middleware('permission:webhooks.view,sanctum');
                Route::get('webhooks/{webhookEvent}', [AdminWebhookController::class, 'show'])->middleware('permission:webhooks.view,sanctum');
                Route::post('webhooks/{webhookEvent}/retry', [AdminWebhookController::class, 'retry'])->middleware(['permission:webhooks.retry,sanctum', 'throttle:sensitive']);

                Route::get('promotions', [OperationalIndexController::class, 'promotions'])->middleware('permission:products.view|promotions.view,sanctum');
                Route::get('trips', [OperationalIndexController::class, 'trips'])->middleware('permission:orders.view,sanctum');
                Route::get('esim/topups', [OperationalIndexController::class, 'topups'])->middleware('permission:esim.profiles.view|topups.view|esims.view,sanctum');
                Route::get('esim/renewals', [OperationalIndexController::class, 'renewals'])->middleware('permission:esim.profiles.view|esims.view,sanctum');
                Route::get('sim-products', [OperationalIndexController::class, 'simProducts'])->middleware('permission:products.view|esim_products.view,sanctum');
                Route::get('sim-inventory', [OperationalIndexController::class, 'simInventory'])->middleware('permission:products.view|sim_inventory.view,sanctum');
                Route::get('network-operators', [OperationalIndexController::class, 'networks'])->middleware('permission:esim.plans.view|network_operators.view,sanctum');
                Route::get('notifications', [OperationalIndexController::class, 'notifications'])->middleware('permission:dashboard.view|support.tickets.view|notifications.view,sanctum');

                Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view,sanctum');

                Route::get('staff', [StaffController::class, 'index'])->middleware('permission:staff.view,sanctum')->name('admin.staff.index');
                Route::post('staff', [StaffController::class, 'store'])->middleware('permission:staff.create,sanctum')->name('admin.staff.store');
                Route::get('staff/{staff}', [StaffController::class, 'show'])->middleware('permission:staff.view_detail,sanctum')->name('admin.staff.show');
                Route::patch('staff/{staff}', [StaffController::class, 'update'])->middleware('permission:staff.update,sanctum')->name('admin.staff.update');
                Route::patch('staff/{staff}/status', [StaffController::class, 'status'])->middleware('permission:staff.suspend|staff.activate,sanctum')->name('admin.staff.status');
                Route::get('staff/{staff}/permissions', [StaffController::class, 'permissions'])->middleware('permission:staff.view_detail|staff.permissions.manage,sanctum')->name('admin.staff.permissions');
                Route::put('staff/{staff}/permissions', [StaffController::class, 'syncPermissions'])->middleware('permission:staff.permissions.manage,sanctum')->name('admin.staff.permissions.sync');

                Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view,sanctum');
                Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create,sanctum');
                Route::get('permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.view|staff.permissions.manage,sanctum');
                Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.view,sanctum');
                Route::patch('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update,sanctum');
                Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete,sanctum');
                Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->middleware('permission:roles.permissions.manage,sanctum');
            });
    });
});
