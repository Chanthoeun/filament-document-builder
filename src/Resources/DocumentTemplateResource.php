<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Pages;

class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationGroup = 'Document Builder';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Details')->schema([
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
                ])->columnSpan(1),
                
                Forms\Components\Section::make('Document Designer')->schema([
                    Forms\Components\Builder::make('content')
                        ->blocks([
                            Forms\Components\Builder\Block::make('header')
                                ->schema([
                                    Forms\Components\TextInput::make('title')->required(),
                                    Forms\Components\TextInput::make('subtitle'),
                                    Forms\Components\FileUpload::make('logo')->image(),
                                ]),
                            Forms\Components\Builder\Block::make('text')
                                ->schema([
                                    Forms\Components\RichEditor::make('content')->required(),
                                ]),
                            Forms\Components\Builder\Block::make('table')
                                ->schema([
                                    Forms\Components\TextInput::make('array_variable')
                                        ->label('Array Variable Name')
                                        ->placeholder('e.g. items')
                                        ->required(),
                                    Forms\Components\Repeater::make('columns')
                                        ->schema([
                                            Forms\Components\TextInput::make('header')->required(),
                                            Forms\Components\TextInput::make('variable')->required(),
                                        ])
                                ]),
                        ])
                        ->collapsible(),
                ])->columnSpan(2),
            ])
            ->columns(3);
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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
