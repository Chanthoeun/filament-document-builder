<?php

namespace Chanthoeun\FilamentDocumentBuilder;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource;

class DocumentBuilderPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-document-builder';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                DocumentTemplateResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
