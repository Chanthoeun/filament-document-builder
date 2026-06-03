<?php

namespace Chanthoeun\FilamentDocumentBuilder;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource;

class DocumentBuilderPlugin implements Plugin
{
    protected ?string $navigationGroup = 'Document Builder';

    public function getId(): string
    {
        return 'filament-document-builder';
    }

    public function navigationGroup(bool | string | null $group = null): static
    {
        // If they pass false, they want to explicitly disable the group
        $this->navigationGroup = $group === false ? null : $group;
        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
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
