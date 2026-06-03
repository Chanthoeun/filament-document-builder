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
                            ->profile('full')
                            ->setCustomConfigs([
                                'plugins' => 'accordion autoresize codesample directionality advlist autolink link image lists charmap preview anchor pagebreak searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media table emoticons help template',
                                'toolbar' => 'undo redo removeformat | fontfamily fontsize fontsizeinput font_size_formats styles | bold italic underline | rtl ltr | alignjustify alignright aligncenter alignleft | numlist bullist outdent indent accordion | forecolor backcolor | blockquote table toc hr | image link anchor media codesample emoticons template | visualblocks print preview wordcount fullscreen help',
                                'templates' => [
                                    [
                                        'title' => 'Circle Shape (Logo)',
                                        'description' => 'A circular shape for logos or avatars',
                                        'content' => '<div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; border-radius: 50%; text-align: center;">LOGO</div>'
                                    ],
                                    [
                                        'title' => 'Square Box',
                                        'description' => 'A simple square box',
                                        'content' => '<div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; text-align: center;">BOX</div>'
                                    ],
                                    [
                                        'title' => 'Rectangle Photo Box (4x6)',
                                        'description' => '4x6 Photo Box for Khmer forms',
                                        'content' => '<div style="display: inline-block; width: 80px; height: 100px; border: 1px solid #000; text-align: center;">រូបថត<br>៤x៦</div>'
                                    ]
                                ]
                            ]),
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
