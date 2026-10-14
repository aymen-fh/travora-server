<?php

declare(strict_types=1);

use App\Support\OpenApi\OpenApiDocument;

it('documents every implemented /api/v1 route and no extra paths', function (): void {
    $document = app(OpenApiDocument::class);
    $fromRoutes = $document->operationKeys();

    $fromSpec = [];
    foreach ($document->document()['paths'] as $path => $operations) {
        foreach (array_keys($operations) as $method) {
            $fromSpec[] = strtoupper((string) $method).' '.$path;
        }
    }
    sort($fromSpec);

    expect($fromSpec)->toBe($fromRoutes)
        ->and($fromSpec)->not->toContain('GET /products')
        ->and($fromSpec)->toContain('POST /orders')
        ->and($fromSpec)->toContain('POST /admin/providers/{provider}/sync')
        ->and($fromSpec)->toContain('GET /admin/providers/{provider}/services')
        ->and($fromSpec)->toContain('GET /admin/payments')
        ->and($fromSpec)->toContain('GET /admin/support/tickets')
        ->and($fromSpec)->toContain('POST /webhooks/providers/{provider}');
});

it('uses stable unique operation ids and admin permission metadata', function (): void {
    $document = app(OpenApiDocument::class)->document();
    $ids = [];

    foreach ($document['paths'] as $path => $operations) {
        foreach ($operations as $operation) {
            $id = $operation['operationId'];
            expect($ids)->not->toContain($id);
            $ids[] = $id;

            if (str_starts_with((string) $path, '/admin/')) {
                expect($operation)->toHaveKey('x-permission')
                    ->and($operation['x-sanctum-ability'])->toBe('admin')
                    ->and($operation['security'][0]['sanctum'])->toBe(['admin']);
            }
        }
    }

    expect($ids)->toContain('CreateOrder')
        ->and($ids)->toContain('AdminRetryWebhook')
        ->and($ids)->toContain('AdminSyncProviderProducts')
        ->and($ids)->toContain('AdminStoreProviderCredentials')
        ->and($ids)->toContain('IngestProviderWebhook');
});

it('never examples secrets, encrypted codes, or raw credential values', function (): void {
    $yaml = app(OpenApiDocument::class)->yaml();
    $json = json_encode(app(OpenApiDocument::class)->document(), JSON_THROW_ON_ERROR);

    foreach (['vault://', 'password_hash', 'activation_code_encrypted', 'confirmation_code_encrypted', 'super-secret'] as $forbidden) {
        expect($yaml)->not->toContain($forbidden);
        expect($json)->not->toContain($forbidden);
    }

    expect($json)->toContain('"masked":true')
        ->and($json)->toContain('confirmation_code_masked')
        ->and($json)->toContain('public_reference')
        ->and($json)->not->toContain('"secret_reference":"vault');
});

it('keeps the committed OpenAPI files in sync with implemented routes', function (): void {
    $document = app(OpenApiDocument::class);

    expect(file_get_contents(base_path('docs/openapi.yaml')))->toBe($document->yaml());
    expect(json_decode((string) file_get_contents(base_path('docs/openapi.json')), true, 512, JSON_THROW_ON_ERROR))
        ->toBe($document->document());
});
