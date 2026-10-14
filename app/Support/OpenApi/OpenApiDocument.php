<?php

declare(strict_types=1);

namespace App\Support\OpenApi;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

final class OpenApiDocument
{
    /**
     * @return array<string, mixed>
     */
    public function document(): array
    {
        $paths = [];

        foreach ($this->apiRoutes() as $route) {
            $path = $this->openApiPath($route);
            foreach ($this->httpMethods($route) as $method) {
                $paths[$path][$method] = $this->operation($route, $method);
            }
            ksort($paths[$path]);
        }

        ksort($paths);

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Travora API',
                'version' => '1.0.0',
                'description' => 'Admin and Mobile HTTP contracts over the same Travora Core. Provider credentials, encrypted activation fields, and raw provider payloads are never returned.',
            ],
            'servers' => [
                ['url' => '/api/v1'],
            ],
            'tags' => $this->tags(),
            'paths' => $paths,
            'components' => $this->components(),
        ];
    }

    public function yaml(): string
    {
        return OpenApiYaml::dump($this->document());
    }

    /**
     * @return list<string>
     */
    public function operationKeys(): array
    {
        $keys = [];

        foreach ($this->apiRoutes() as $route) {
            $path = $this->openApiPath($route);
            foreach ($this->httpMethods($route) as $method) {
                $keys[] = strtoupper($method).' '.$path;
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * @return list<Route>
     */
    public function apiRoutes(): array
    {
        $routes = [];

        foreach (RouteFacade::getRoutes() as $route) {
            if (! is_string($route->uri()) || ! str_starts_with($route->uri(), 'api/v1')) {
                continue;
            }

            if ($this->httpMethods($route) === []) {
                continue;
            }

            $routes[] = $route;
        }

        usort($routes, fn (Route $left, Route $right): int => $left->uri() <=> $right->uri());

        return $routes;
    }

    /**
     * @return list<string>
     */
    private function httpMethods(Route $route): array
    {
        return array_values(array_filter(
            array_map(strtolower(...), $route->methods()),
            fn (string $method): bool => in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true),
        ));
    }

    private function openApiPath(Route $route): string
    {
        return '/'.Str::after($route->uri(), 'api/v1/');
    }

    /**
     * @return array<string, mixed>
     */
    private function operation(Route $route, string $method): array
    {
        $middleware = $this->middleware($route);
        $admin = str_contains($this->controllerClass($route), '\\Admin\\');
        $operation = [
            'operationId' => $this->operationId($route),
            'tags' => [$this->tag($route)],
            'summary' => $this->summary($route),
            'responses' => $this->responses($route, $method),
        ];

        if ($this->isAuthenticated($middleware)) {
            $operation['security'] = [['sanctum' => $admin ? ['admin'] : ['mobile']]];
            $operation['responses']['401'] = ['$ref' => '#/components/responses/Unauthenticated'];
            $operation['responses']['403'] = ['$ref' => '#/components/responses/Forbidden'];
        }

        $permission = $this->permission($middleware);
        if ($permission !== null) {
            $operation['x-permission'] = $permission;
        }

        if ($admin) {
            $operation['x-sanctum-ability'] = 'admin';
        }

        $parameters = $this->parameters($route, $method, $middleware);
        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        $body = $this->requestBody($route, $method);
        if ($body !== null) {
            $operation['requestBody'] = $body;
        }

        ksort($operation['responses']);

        return $operation;
    }

    /**
     * @return list<string>
     */
    private function middleware(Route $route): array
    {
        return array_values(array_filter(
            $route->gatherMiddleware(),
            fn (mixed $middleware): bool => is_string($middleware),
        ));
    }

    /**
     * @param  list<string>  $middleware
     */
    private function isAuthenticated(array $middleware): bool
    {
        return collect($middleware)->contains(
            fn (string $item): bool => str_contains($item, 'Authenticate:sanctum') || $item === 'auth:sanctum',
        );
    }

    /**
     * @param  list<string>  $middleware
     */
    private function permission(array $middleware): ?string
    {
        foreach ($middleware as $item) {
            if (preg_match('/PermissionMiddleware:([^,]+)/', $item, $matches) === 1) {
                return $matches[1];
            }

            if (str_starts_with($item, 'permission:')) {
                return explode(',', substr($item, 11))[0];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $middleware
     * @return list<array<string, mixed>>
     */
    private function parameters(Route $route, string $method, array $middleware): array
    {
        $parameters = [];

        foreach ($route->parameterNames() as $name) {
            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => $name === 'token' ? 'integer' : 'string', 'format' => $name === 'token' ? null : 'uuid'],
            ];
        }

        $parameters = array_map(function (array $parameter): array {
            if (($parameter['schema']['format'] ?? null) === null) {
                unset($parameter['schema']['format']);
            }

            return $parameter;
        }, $parameters);

        if ($method === 'get' && in_array($route->getActionMethod(), ['index', 'transactions', 'holds', 'ledger', 'prices', 'deadLetter', 'products', 'refundIndex'], true)) {
            $parameters[] = ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1]];
            $parameters[] = ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]];
        }

        if ($route->uri() === 'api/v1/esim-plans') {
            foreach (['currency', 'country_iso2', 'region_code', 'q'] as $name) {
                $parameters[] = ['name' => $name, 'in' => 'query', 'schema' => ['type' => 'string']];
            }
            foreach (['min_data_mb', 'max_data_mb', 'min_validity_days', 'max_validity_days', 'min_price_minor', 'max_price_minor'] as $name) {
                $parameters[] = ['name' => $name, 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 0]];
            }
            $parameters[] = ['name' => 'featured', 'in' => 'query', 'schema' => ['type' => 'boolean']];
        }

        if (collect($middleware)->contains(fn (string $item): bool => str_contains($item, 'EnsureIdempotencyKey') || $item === 'idempotency')) {
            $parameters[] = [
                'name' => 'Idempotency-Key',
                'in' => 'header',
                'required' => false,
                'schema' => ['type' => 'string'],
                'description' => 'Reusing the key with a different payload returns 409 IDEMPOTENCY_CONFLICT.',
            ];
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestBody(Route $route, string $method): ?array
    {
        if (! in_array($method, ['post', 'put', 'patch'], true)) {
            return null;
        }

        $schema = $this->requestSchema($route);
        if ($schema === null) {
            return null;
        }

        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => $schema,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestSchema(Route $route): ?array
    {
        $key = class_basename($this->controllerClass($route)).'@'.$this->actionMethod($route);

        return match ($key) {
            'AuthController@register' => [
                'type' => 'object',
                'required' => ['name', 'email', 'password', 'password_confirmation'],
                'properties' => [
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'password' => ['type' => 'string', 'minLength' => 8],
                    'password_confirmation' => ['type' => 'string'],
                    'phone' => ['type' => 'string'],
                    'preferred_language' => ['type' => 'string', 'enum' => ['en', 'ar']],
                    'preferred_currency' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 3],
                    'device_name' => ['type' => 'string'],
                ],
            ],
            'AuthController@login' => [
                'type' => 'object',
                'required' => ['email', 'password'],
                'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'password' => ['type' => 'string'],
                    'device_name' => ['type' => 'string'],
                ],
            ],
            'AuthController@forgotPassword' => [
                'type' => 'object',
                'required' => ['email'],
                'properties' => ['email' => ['type' => 'string', 'format' => 'email']],
            ],
            'AuthController@resetPassword' => [
                'type' => 'object',
                'required' => ['token', 'email', 'password', 'password_confirmation'],
                'properties' => [
                    'token' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'password' => ['type' => 'string', 'minLength' => 8],
                    'password_confirmation' => ['type' => 'string'],
                ],
            ],
            'OrderController@store' => [
                'type' => 'object',
                'required' => ['plan_id'],
                'properties' => [
                    'plan_id' => ['type' => 'string', 'format' => 'uuid'],
                    'quantity' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                ],
            ],
            'WalletController@topup' => [
                'type' => 'object',
                'required' => ['amount_minor'],
                'properties' => [
                    'amount_minor' => ['type' => 'integer', 'minimum' => 1],
                    'currency' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 3],
                ],
            ],
            'EsimController@topup', 'EsimController@renew' => [
                'type' => 'object',
                'required' => ['plan_id'],
                'properties' => ['plan_id' => ['type' => 'string', 'format' => 'uuid']],
            ],
            'SupportController@store' => [
                'type' => 'object',
                'required' => ['subject', 'body'],
                'properties' => [
                    'category' => ['type' => 'string'],
                    'subject' => ['type' => 'string'],
                    'body' => ['type' => 'string'],
                    'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']],
                ],
            ],
            'SupportController@message' => [
                'type' => 'object',
                'required' => ['body'],
                'properties' => ['body' => ['type' => 'string']],
            ],
            'UserController@update' => [
                'type' => 'object',
                'properties' => [
                    'phone' => ['type' => 'string', 'nullable' => true],
                    'display_name' => ['type' => 'string'],
                    'preferred_language' => ['type' => 'string', 'enum' => ['en', 'ar']],
                ],
            ],
            'UserController@suspend' => [
                'type' => 'object',
                'required' => ['reason'],
                'properties' => ['reason' => ['type' => 'string']],
            ],
            'ProviderController@store' => [
                'type' => 'object',
                'required' => ['name', 'code'],
                'properties' => [
                    'name' => ['type' => 'string'],
                    'code' => ['type' => 'string'],
                    'type' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                    'priority' => ['type' => 'integer'],
                ],
            ],
            'ProviderController@update' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'priority' => ['type' => 'integer'],
                    'status' => ['type' => 'string'],
                ],
            ],
            'ProviderController@disable' => [
                'type' => 'object',
                'required' => ['reason'],
                'properties' => ['reason' => ['type' => 'string']],
            ],
            'ProviderController@syncServices' => [
                'type' => 'object',
                'required' => ['services'],
                'properties' => [
                    'services' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'required' => ['service_type'],
                            'properties' => [
                                'service_type' => ['type' => 'string'],
                                'is_enabled' => ['type' => 'boolean'],
                                'priority' => ['type' => 'integer'],
                            ],
                        ],
                    ],
                ],
            ],
            'ProviderController@syncCountries' => [
                'type' => 'object',
                'required' => ['coverage'],
                'properties' => [
                    'coverage' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'required' => ['country_id', 'service_type'],
                            'properties' => [
                                'country_id' => ['type' => 'string', 'format' => 'uuid'],
                                'service_type' => ['type' => 'string'],
                                'status' => ['type' => 'string', 'enum' => ['active', 'inactive', 'archived']],
                            ],
                        ],
                    ],
                ],
            ],
            'SupportController@assign' => [
                'type' => 'object',
                'required' => ['assigned_to'],
                'properties' => [
                    'assigned_to' => ['type' => 'string', 'format' => 'uuid'],
                ],
            ],
            'SupportController@close' => [
                'type' => 'object',
                'required' => ['reason'],
                'properties' => ['reason' => ['type' => 'string']],
            ],
            'ProviderController@storeCredentials' => [
                'type' => 'object',
                'required' => ['environment', 'credential_key', 'secret_reference'],
                'properties' => [
                    'environment' => ['type' => 'string'],
                    'credential_key' => ['type' => 'string'],
                    'secret_reference' => ['type' => 'string', 'writeOnly' => true],
                ],
            ],
            'CatalogController@store' => [
                'type' => 'object',
                'required' => ['provider_id', 'name', 'validity_days'],
                'properties' => [
                    'provider_id' => ['type' => 'string', 'format' => 'uuid'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'data_mb' => ['type' => 'integer'],
                    'validity_days' => ['type' => 'integer'],
                    'is_unlimited' => ['type' => 'boolean'],
                    'status' => ['type' => 'string'],
                ],
            ],
            'CatalogController@update' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                ],
            ],
            'CatalogController@storePrice' => [
                'type' => 'object',
                'required' => ['price_minor', 'currency'],
                'properties' => [
                    'price_minor' => ['type' => 'integer', 'minimum' => 0],
                    'currency' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 3],
                    'starts_at' => ['type' => 'string', 'format' => 'date-time'],
                    'ends_at' => ['type' => 'string', 'format' => 'date-time'],
                    'is_active' => ['type' => 'boolean'],
                ],
            ],
            'CatalogController@syncCountries' => [
                'type' => 'object',
                'required' => ['country_ids'],
                'properties' => [
                    'country_ids' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'uuid']],
                ],
            ],
            'CatalogController@syncNetworks' => [
                'type' => 'object',
                'required' => ['network_ids'],
                'properties' => [
                    'network_ids' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'uuid']],
                ],
            ],
            'OrderController@cancel' => str_contains($this->controllerClass($route), '\\Admin\\')
                ? [
                    'type' => 'object',
                    'required' => ['reason'],
                    'properties' => ['reason' => ['type' => 'string']],
                ]
                : null,
            'WalletController@adjust' => [
                'type' => 'object',
                'required' => ['direction', 'amount_minor', 'reason', 'idempotency_key'],
                'properties' => [
                    'direction' => ['type' => 'string', 'enum' => ['credit', 'debit']],
                    'amount_minor' => ['type' => 'integer', 'minimum' => 1],
                    'reason' => ['type' => 'string'],
                    'idempotency_key' => ['type' => 'string'],
                ],
            ],
            'EsimProfileController@sendSms' => [
                'type' => 'object',
                'required' => ['message'],
                'properties' => [
                    'message' => ['type' => 'string', 'maxLength' => 500],
                ],
            ],
            'ProviderController@saveWebhook' => [
                'type' => 'object',
                'required' => ['webhook'],
                'properties' => [
                    'webhook' => ['type' => 'string', 'format' => 'uri'],
                ],
            ],
            'ProviderWebhookController@__invoke' => [
                'type' => 'object',
                'properties' => [
                    'event_id' => ['type' => 'string'],
                    'type' => ['type' => 'string'],
                    'travora_fixture' => ['type' => 'string'],
                ],
            ],
            default => in_array($this->actionMethod($route), ['store', 'update', 'syncCountries', 'syncNetworks', 'syncServices', 'storePrice', 'storeCredentials', 'adjust', 'suspend', 'disable', 'cancel', 'message', 'assign', 'close', 'topup', 'renew'], true)
                ? ['type' => 'object']
                : null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function responses(Route $route, string $method): array
    {
        $success = match (true) {
            $method === 'post' && str_contains($route->uri(), '/sync') => '202',
            $method === 'post' && str_contains($route->uri(), '/health-check') => '202',
            $method === 'post' && str_contains($route->uri(), 'webhooks/providers') => '202',
            $method === 'post' && $route->getActionMethod() === 'store' => '201',
            $method === 'post' && in_array($route->getActionMethod(), ['topup', 'renew', 'message'], true) && ! str_contains($this->controllerClass($route), '\\Admin\\') => '201',
            default => '200',
        };

        $schema = match (true) {
            str_contains($route->uri(), 'credentials') => '#/components/schemas/MaskedCredential',
            $route->uri() === 'api/v1/wallet' || str_ends_with($route->uri(), 'wallet/topup') => '#/components/schemas/Wallet',
            str_contains($route->uri(), '/installation') => '#/components/schemas/InstallationPayload',
            default => '#/components/schemas/SuccessEnvelope',
        };

        $responses = [
            $success => [
                'description' => $success === '202' ? 'Accepted async operation' : 'OK',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => $schema],
                    ],
                ],
            ],
            '422' => ['$ref' => '#/components/responses/ValidationError'],
        ];

        if (str_contains($route->uri(), 'orders') || str_contains($route->uri(), 'wallet/topup') || str_contains($route->uri(), 'topups') || str_contains($route->uri(), 'renewals')) {
            $responses['409'] = ['$ref' => '#/components/responses/IdempotencyConflict'];
        }

        return $responses;
    }

    private function operationId(Route $route): string
    {
        $controller = class_basename($this->controllerClass($route));
        $method = $this->actionMethod($route);
        $admin = str_contains($this->controllerClass($route), '\\Admin\\');
        $key = $controller.'@'.$method;

        $mapped = match ($key) {
            'AuthController@register' => 'RegisterUser',
            'AuthController@login' => 'LoginUser',
            'AuthController@logout' => 'LogoutUser',
            'AuthController@me' => 'GetCurrentUser',
            'AuthController@forgotPassword' => 'ForgotPassword',
            'AuthController@resetPassword' => 'ResetPassword',
            'AuthController@sessions' => 'ListAuthSessions',
            'AuthController@revokeSession' => 'RevokeAuthSession',
            'CountryController@index' => 'ListCountries',
            'CountryController@show' => 'GetCountry',
            'EsimPlanController@index' => 'ListEsimPlans',
            'EsimPlanController@show' => 'GetEsimPlan',
            'OrderController@index' => $admin ? 'AdminListOrders' : 'ListOrders',
            'OrderController@store' => 'CreateOrder',
            'OrderController@show' => $admin ? 'AdminGetOrder' : 'GetOrder',
            'OrderController@cancel' => $admin ? 'AdminCancelOrder' : 'CancelOrder',
            'OrderController@retryFulfillment' => 'AdminRetryOrderFulfillment',
            'EsimController@index' => 'ListEsims',
            'EsimController@show' => 'GetEsim',
            'EsimController@usage' => 'GetEsimUsage',
            'EsimController@refreshUsage' => 'RefreshEsimUsage',
            'EsimController@installation' => 'GetEsimInstallation',
            'EsimController@topup' => 'CreateEsimTopup',
            'EsimController@renew' => 'CreateEsimRenewal',
            'WalletController@show' => $admin ? 'AdminGetWallet' : 'GetWallet',
            'WalletController@transactions' => $admin ? 'AdminListWalletTransactions' : 'ListWalletTransactions',
            'WalletController@topup' => 'TopUpWallet',
            'WalletController@index' => 'AdminListWallets',
            'WalletController@holds' => 'AdminListWalletHolds',
            'WalletController@ledger' => 'AdminListWalletLedger',
            'WalletController@adjust' => 'AdminAdjustWallet',
            'NotificationController@index' => 'ListNotifications',
            'NotificationController@read' => 'MarkNotificationRead',
            'SupportController@index' => $admin ? 'AdminListSupportTickets' : 'ListSupportTickets',
            'SupportController@store' => 'CreateSupportTicket',
            'SupportController@show' => $admin ? 'AdminGetSupportTicket' : 'GetSupportTicket',
            'SupportController@message' => $admin ? 'AdminReplySupportTicket' : 'CreateSupportMessage',
            'SupportController@assign' => 'AdminAssignSupportTicket',
            'SupportController@close' => 'AdminCloseSupportTicket',
            'PaymentController@index' => 'AdminListPayments',
            'PaymentController@show' => 'AdminGetPayment',
            'PaymentController@attempts' => 'AdminListPaymentAttempts',
            'PaymentController@refunds' => 'AdminListPaymentRefunds',
            'PaymentController@refundIndex' => 'AdminListRefunds',
            'ProviderWebhookController@__invoke' => 'IngestProviderWebhook',
            'DashboardController@__invoke' => 'AdminGetDashboard',
            'UserController@index' => 'AdminListUsers',
            'UserController@show' => 'AdminGetUser',
            'UserController@update' => 'AdminUpdateUser',
            'UserController@suspend' => 'AdminSuspendUser',
            'UserController@sessions' => 'AdminListUserSessions',
            'UserController@revokeSession' => 'AdminRevokeUserSession',
            'ProviderController@index' => 'AdminListProviders',
            'ProviderController@store' => 'AdminCreateProvider',
            'ProviderController@show' => 'AdminGetProvider',
            'ProviderController@update' => 'AdminUpdateProvider',
            'ProviderController@enable' => 'AdminEnableProvider',
            'ProviderController@disable' => 'AdminDisableProvider',
            'ProviderController@sync' => 'AdminSyncProviderProducts',
            'ProviderController@healthCheck' => 'AdminRunProviderHealthCheck',
            'ProviderController@credentials' => 'AdminListProviderCredentials',
            'ProviderController@storeCredentials' => 'AdminStoreProviderCredentials',
            'ProviderController@services' => 'AdminListProviderServices',
            'ProviderController@syncServices' => 'AdminSyncProviderServices',
            'ProviderController@products' => 'AdminListProviderProducts',
            'ProviderController@countries' => 'AdminListProviderCountries',
            'ProviderController@syncCountries' => 'AdminSyncProviderCountries',
            'CatalogController@index' => 'AdminListEsimPlans',
            'CatalogController@store' => 'AdminCreateEsimPlan',
            'CatalogController@show' => 'AdminGetEsimPlan',
            'CatalogController@update' => 'AdminUpdateEsimPlan',
            'CatalogController@prices' => 'AdminListEsimPlanPrices',
            'CatalogController@storePrice' => 'AdminCreateEsimPlanPrice',
            'CatalogController@deactivatePrice' => 'AdminDeactivateEsimPlanPrice',
            'CatalogController@syncCountries' => 'AdminSyncEsimPlanCountries',
            'CatalogController@syncNetworks' => 'AdminSyncEsimPlanNetworks',
            'EsimProfileController@index' => 'AdminListEsimProfiles',
            'EsimProfileController@show' => 'AdminGetEsimProfile',
            'EsimProfileController@usage' => 'AdminGetEsimUsage',
            'EsimProfileController@syncUsage' => 'AdminSyncEsimUsage',
            'EsimProfileController@cancel' => 'AdminCancelEsimProfile',
            'EsimProfileController@suspend' => 'AdminSuspendEsimProfile',
            'EsimProfileController@unsuspend' => 'AdminUnsuspendEsimProfile',
            'EsimProfileController@revoke' => 'AdminRevokeEsimProfile',
            'EsimProfileController@sendSms' => 'AdminSendEsimSms',
            'ProviderController@balance' => 'AdminGetProviderBalance',
            'ProviderController@discountRules' => 'AdminGetProviderDiscountRules',
            'ProviderController@showWebhook' => 'AdminGetProviderWebhook',
            'ProviderController@saveWebhook' => 'AdminSaveProviderWebhook',
            'WebhookController@index' => 'AdminListWebhooks',
            'WebhookController@show' => 'AdminGetWebhook',
            'WebhookController@deadLetter' => 'AdminListDeadLetterWebhooks',
            'WebhookController@retry' => 'AdminRetryWebhook',
            'AuditLogController@index' => 'AdminListAuditLogs',
            default => ($admin ? 'Admin' : '').Str::studly($method).str_replace('Controller', '', $controller),
        };

        return $mapped;
    }

    private function tag(Route $route): string
    {
        $uri = $route->uri();

        return match (true) {
            str_starts_with($uri, 'api/v1/auth') => 'Auth',
            str_starts_with($uri, 'api/v1/admin/dashboard') => 'Dashboard',
            str_starts_with($uri, 'api/v1/admin/users') => 'Users',
            str_starts_with($uri, 'api/v1/admin/providers') => 'Providers',
            str_starts_with($uri, 'api/v1/admin/esim') => 'eSIM',
            str_starts_with($uri, 'api/v1/admin/orders') => 'Orders',
            str_starts_with($uri, 'api/v1/admin/payments'), str_starts_with($uri, 'api/v1/admin/refunds') => 'Payments',
            str_starts_with($uri, 'api/v1/admin/support') => 'Support',
            str_starts_with($uri, 'api/v1/admin/wallets') => 'Wallets',
            str_starts_with($uri, 'api/v1/admin/webhooks') => 'Webhooks',
            str_starts_with($uri, 'api/v1/admin/audit-logs') => 'Audit',
            str_starts_with($uri, 'api/v1/webhooks') => 'Webhooks',
            str_starts_with($uri, 'api/v1/esim-plans'), str_starts_with($uri, 'api/v1/countries') => 'Catalog',
            str_starts_with($uri, 'api/v1/orders') => 'Orders',
            str_starts_with($uri, 'api/v1/esims') => 'eSIM',
            str_starts_with($uri, 'api/v1/wallet') => 'Wallets',
            str_starts_with($uri, 'api/v1/notifications') => 'Notifications',
            str_starts_with($uri, 'api/v1/support') => 'Support',
            default => 'Travora',
        };
    }

    private function summary(Route $route): string
    {
        return (string) Str::of($this->operationId($route))->headline();
    }

    /**
     * @return list<array{name: string}>
     */
    private function tags(): array
    {
        return array_map(
            fn (string $name): array => ['name' => $name],
            ['Auth', 'Catalog', 'Orders', 'eSIM', 'Wallets', 'Payments', 'Notifications', 'Support', 'Webhooks', 'Dashboard', 'Users', 'Providers', 'Audit'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function components(): array
    {
        return [
            'securitySchemes' => [
                'sanctum' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'Sanctum',
                    'description' => 'Customer tokens have the mobile ability. Staff tokens have admin and mobile.',
                ],
            ],
            'schemas' => [
                'SuccessEnvelope' => [
                    'type' => 'object',
                    'required' => ['success', 'message', 'data', 'meta'],
                    'properties' => [
                        'success' => ['type' => 'boolean', 'enum' => [true]],
                        'message' => ['type' => 'string'],
                        'data' => ['nullable' => true],
                        'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
                    ],
                ],
                'ErrorEnvelope' => [
                    'type' => 'object',
                    'required' => ['success', 'message', 'errors', 'code'],
                    'properties' => [
                        'success' => ['type' => 'boolean', 'enum' => [false]],
                        'message' => ['type' => 'string'],
                        'errors' => ['type' => 'object'],
                        'code' => ['type' => 'string', 'enum' => [
                            'UNAUTHENTICATED',
                            'FORBIDDEN',
                            'VALIDATION_ERROR',
                            'NOT_FOUND',
                            'IDEMPOTENCY_CONFLICT',
                            'INSUFFICIENT_BALANCE',
                            'HTTP_ERROR',
                            'ERROR',
                        ]],
                    ],
                    'example' => [
                        'success' => false,
                        'message' => 'Forbidden.',
                        'errors' => [],
                        'code' => 'FORBIDDEN',
                    ],
                ],
                'PaginationMeta' => [
                    'type' => 'object',
                    'properties' => [
                        'current_page' => ['type' => 'integer'],
                        'per_page' => ['type' => 'integer'],
                        'total' => ['type' => 'integer'],
                        'last_page' => ['type' => 'integer'],
                    ],
                ],
                'Wallet' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'currency' => ['type' => 'string'],
                        'available_balance_minor' => ['type' => 'integer'],
                        'held_balance_minor' => ['type' => 'integer'],
                        'status' => ['type' => 'string'],
                    ],
                ],
                'MaskedCredential' => [
                    'type' => 'object',
                    'properties' => [
                        'configured' => ['type' => 'boolean'],
                        'masked' => ['type' => 'boolean'],
                        'credential_key' => ['type' => 'string'],
                        'environment' => ['type' => 'string'],
                        'public_reference' => ['type' => 'string'],
                    ],
                    'example' => [
                        'configured' => true,
                        'masked' => true,
                        'credential_key' => 'api_token',
                        'environment' => 'sandbox',
                        'public_reference' => 'provider:00000000-0000-0000-0000-000000000001:credential:00000000-0000-0000-0000-000000000002',
                    ],
                ],
                'InstallationPayload' => [
                    'type' => 'object',
                    'properties' => [
                        'profile_id' => ['type' => 'string', 'format' => 'uuid'],
                        'iccid' => ['type' => 'string'],
                        'smdp_address' => ['type' => 'string'],
                        'matching_id' => ['type' => 'string'],
                        'activation_code' => ['type' => 'string'],
                        'qr_payload' => ['type' => 'string'],
                        'confirmation_code_masked' => ['type' => 'string'],
                    ],
                    'example' => [
                        'profile_id' => '00000000-0000-0000-0000-000000000003',
                        'iccid' => '8900001234567890123',
                        'smdp_address' => 'smdp.example.local',
                        'matching_id' => 'MATCHID',
                        'activation_code' => 'LPA:1$smdp.example.local$MATCHID',
                        'qr_payload' => 'LPA:1$smdp.example.local$MATCHID',
                        'confirmation_code_masked' => '***3210',
                    ],
                ],
            ],
            'responses' => [
                'Unauthenticated' => [
                    'description' => 'Missing or invalid Sanctum token.',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']]],
                ],
                'Forbidden' => [
                    'description' => 'Authenticated but missing role, permission, or token ability.',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']]],
                ],
                'ValidationError' => [
                    'description' => 'Request validation failed.',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']]],
                ],
                'IdempotencyConflict' => [
                    'description' => 'Idempotency-Key reused with a different payload.',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']]],
                ],
            ],
        ];
    }

    private function controllerClass(Route $route): string
    {
        $class = $route->getControllerClass();

        if (is_string($class) && $class !== '') {
            return $class;
        }

        $action = (string) $route->getActionName();

        return str_contains($action, '@') ? Str::before($action, '@') : $action;
    }

    private function actionMethod(Route $route): string
    {
        $method = $route->getActionMethod();
        $class = $this->controllerClass($route);

        if ($method === $class || $method === '' || str_contains($method, '\\')) {
            return '__invoke';
        }

        return $method;
    }
}
