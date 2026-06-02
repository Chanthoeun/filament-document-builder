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
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';
    protected static string|\UnitEnum|null $navigationGroup = 'Document Builder';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Tabs::make('Template Builder')->tabs([
                    \Filament\Schemas\Components\Tabs\Tab::make('Template Details')->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('type')
                            ->placeholder('e.g. invoice, certificate, receipt')
                            ->maxLength(255),
                        Forms\Components\KeyValue::make('page_settings')
                            ->label('Page Settings')
                            ->keyLabel('Setting')
                            ->valueLabel('Value')
                            ->default([
                                'format' => 'a4',
                                'orientation' => 'portrait',
                            ]),
                    ]),
                    \Filament\Schemas\Components\Tabs\Tab::make('Document Designer')->schema([
                        \AmidEsfahani\FilamentTinyEditor\TinyEditor::make('content')
                            ->required()
                            ->columnSpanFull()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('document-templates')
                            ->profile('full'),
                    ]),
                ])->columnSpanFull(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('type')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
