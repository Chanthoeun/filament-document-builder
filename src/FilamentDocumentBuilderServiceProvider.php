<?php

namespace Chanthoeun\FilamentDocumentBuilder;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;

class FilamentDocumentBuilderServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-document-builder')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasMigration('create_document_templates_table')
            ->hasCommand(\Chanthoeun\FilamentDocumentBuilder\Commands\PublishResourceCommand::class);
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Js::make('custom-shapes', __DIR__ . '/../resources/js/custom-shapes.js'),
        ], 'chanthoeun/filament-document-builder');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/Resources/DocumentTemplateResource.php' => app_path('Filament/Resources/DocumentTemplateResource.php'),
                __DIR__ . '/Resources/DocumentTemplateResource' => app_path('Filament/Resources/DocumentTemplateResource'),
            ], 'filament-document-template');
        }
    }
}
