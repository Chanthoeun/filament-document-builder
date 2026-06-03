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
            ->hasMigration('create_document_templates_table')
            ->hasMigration('add_model_class_to_document_templates_table')
            ->hasMigration('add_extra_data_sources_to_document_templates_table');
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Js::make('custom-shapes', __DIR__ . '/../resources/js/custom-shapes.js'),
        ], 'chanthoeun/filament-document-builder');
    }
}
