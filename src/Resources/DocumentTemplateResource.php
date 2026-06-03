<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Pages;

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

    public static function form(Schema $schema): Schema
    {
        return DocumentTemplateResource\DocumentTemplateForm::schema($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentTemplateResource\DocumentTemplateTable::table($table);
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
