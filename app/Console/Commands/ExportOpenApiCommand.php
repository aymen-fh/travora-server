<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\OpenApi\OpenApiDocument;
use Illuminate\Console\Command;

final class ExportOpenApiCommand extends Command
{
    protected $signature = 'openapi:export';

    protected $description = 'Write the Travora OpenAPI contract from implemented API routes.';

    public function handle(OpenApiDocument $document): int
    {
        $yaml = base_path('docs/openapi.yaml');
        $json = base_path('docs/openapi.json');

        file_put_contents($yaml, $document->yaml());
        file_put_contents($json, json_encode($document->document(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");

        $this->info('Wrote '.$yaml);
        $this->info('Wrote '.$json);

        return self::SUCCESS;
    }
}
