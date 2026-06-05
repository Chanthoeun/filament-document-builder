<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Pages;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Schemas\DocumentTemplateForm;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Tables\DocumentTemplateTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return filament('filament-document-builder')->getNavigationIcon();
    }

    public static function getNavigationGroup(): ?string
    {
        return filament('filament-document-builder')->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return filament('filament-document-builder')->getNavigationSort();
    }

    public static function form(Schema $schema): Schema
    {
        return DocumentTemplateForm::schema($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentTemplateTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentTemplates::route('/'),
            'create' => Pages\CreateDocumentTemplate::route('/create'),
            'edit' => Pages\EditDocumentTemplate::route('/{record}/edit'),
        ];
    }
}
