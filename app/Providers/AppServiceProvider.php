<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\EsimPlan;
use App\Models\EsimProfile;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Provider;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WebhookEvent;
use App\Policies\AuditLogPolicy;
use App\Policies\EsimPlanPolicy;
use App\Policies\EsimPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PaymentRefundPolicy;
use App\Policies\ProviderPolicy;
use App\Policies\SupportTicketPolicy;
use App\Policies\UserPolicy;
use App\Policies\WalletPolicy;
use App\Policies\WebhookEventPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Provider::class, ProviderPolicy::class);
        Gate::policy(EsimPlan::class, EsimPlanPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(EsimProfile::class, EsimPolicy::class);
        Gate::policy(Wallet::class, WalletPolicy::class);
        Gate::policy(WebhookEvent::class, WebhookEventPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(PaymentRefund::class, PaymentRefundPolicy::class);
        Gate::policy(SupportTicket::class, SupportTicketPolicy::class);

        // Break-glass: super_admin is the highest privilege, not a normal role.
        // Gate::before grants every ability. The role still stores the full
        // permission list so /me and audit stay explicit. Do not make this
        // matrix reducible — a stored reduction would not take effect.
        Gate::before(function ($user, string $ability) {
            if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
                return true;
            }

            return null;
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) config('travora.rate_limits.api', 60))
                ->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute((int) config('travora.rate_limits.sensitive', 20))
                ->by((string) ($request->user()?->id ?: $request->ip()));
        });
    }
}
