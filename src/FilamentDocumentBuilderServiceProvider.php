<?php

namespace Chanthoeun\FilamentDocumentBuilder;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentDocumentBuilderServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-document-builder')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_document_templates_table');
    }
}
