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
                \Filament\Schemas\Components\Wizard::make([
                    \Filament\Schemas\Components\Wizard\Step::make('Template Details')->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('type')
                            ->placeholder('e.g. invoice, certificate, receipt')
                            ->maxLength(255),
                        Forms\Components\Select::make('model_class')
                            ->label('Database Model')
                            ->options(function () {
                                $models = [];
                                $path = app_path('Models');
                                if (is_dir($path)) {
                                    foreach (scandir($path) as $file) {
                                        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                                            $class = 'App\\Models\\' . pathinfo($file, PATHINFO_FILENAME);
                                            if (class_exists($class)) {
                                                $models[$class] = class_basename($class);
                                            }
                                        }
                                    }
                                }
                                return $models;
                            })
                            ->live()
                            ->placeholder('Select a model to use dynamic variables'),
                        Forms\Components\KeyValue::make('page_settings')
                            ->label('Page Settings')
                            ->keyLabel('Setting')
                            ->valueLabel('Value')
                            ->default([
                                'format' => 'a4',
                                'orientation' => 'portrait',
                            ]),
                    ]),
                    \Filament\Schemas\Components\Wizard\Step::make('Document Designer')->schema([
                        \AmidEsfahani\FilamentTinyEditor\TinyEditor::make('content')
                            ->required()
                            ->columnSpanFull()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('document-templates')
                            ->profile('full')
                            ->setCustomConfigs(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                $vars = [];
                                $modelClass = $get('model_class');
                                if ($modelClass && class_exists($modelClass)) {
                                    $model = new $modelClass;
                                    $vars = array_merge(['id', 'created_at', 'updated_at'], $model->getFillable());
                                    sort($vars);
                                }

                                return [
                                    'document_variables' => $vars,
                                    'plugins' => 'custom_shapes accordion autoresize codesample directionality advlist autolink link image lists charmap preview anchor pagebreak searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media table emoticons help template',
                                    'toolbar' => 'undo redo removeformat | fontfamily fontsize fontsizeinput font_size_formats styles | bold italic underline | rtl ltr | alignjustify alignright aligncenter alignleft | numlist bullist outdent indent accordion | forecolor backcolor | blockquote table toc hr | image link anchor media codesample emoticons template insert_variable | visualblocks print preview wordcount fullscreen help',
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
                                        ],
                                        [
                                            'title' => 'Checkbox (Small Square)',
                                            'description' => 'Small square for checkboxes',
                                            'content' => '<div style="display: inline-block; width: 16px; height: 16px; border: 1px solid #000; text-align: center;"></div>'
                                        ],
                                        [
                                            'title' => 'Rounded Rectangle',
                                            'description' => 'A rectangle with rounded corners',
                                            'content' => '<div style="display: inline-block; width: 120px; height: 60px; border: 1px solid #000; border-radius: 10px; text-align: center;">TEXT</div>'
                                        ],
                                        [
                                            'title' => 'Oval',
                                            'description' => 'An oval shape',
                                            'content' => '<div style="display: inline-block; width: 120px; height: 60px; border: 1px solid #000; border-radius: 50%; text-align: center;">OVAL</div>'
                                        ],
                                        [
                                            'title' => 'Signature Area',
                                            'description' => 'A line for signatures',
                                            'content' => '<div style="display: inline-block; width: 200px; text-align: center; border-bottom: 1px solid #000; padding-bottom: 5px; margin-top: 40px;">ហត្ថលេខា / Signature</div>'
                                        ]
                                    ],
                                    'text_patterns' => [
                                        [ 'start' => '#logo', 'replacement' => '<div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; border-radius: 50%; text-align: center; line-height: 80px;">LOGO</div>' ],
                                        [ 'start' => '#box', 'replacement' => '<div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; text-align: center; line-height: 80px;">BOX</div>' ],
                                        [ 'start' => '#photo', 'replacement' => '<div style="display: inline-block; width: 80px; height: 100px; border: 1px solid #000; text-align: center; padding-top: 30px; box-sizing: border-box;">រូបថត<br>៤x៦</div>' ],
                                        [ 'start' => '#checkbox', 'replacement' => '<div style="display: inline-block; width: 16px; height: 16px; border: 1px solid #000; text-align: center;"></div>' ],
                                        [ 'start' => '#rounded', 'replacement' => '<div style="display: inline-block; width: 120px; height: 60px; border: 1px solid #000; border-radius: 10px; text-align: center; line-height: 60px;">TEXT</div>' ],
                                        [ 'start' => '#oval', 'replacement' => '<div style="display: inline-block; width: 120px; height: 60px; border: 1px solid #000; border-radius: 50%; text-align: center; line-height: 60px;">OVAL</div>' ],
                                        [ 'start' => '#sign', 'replacement' => '<div style="display: inline-block; width: 200px; text-align: center; border-bottom: 1px solid #000; padding-bottom: 5px; margin-top: 40px;">ហត្ថលេខា / Signature</div>' ]
                                    ]
                                ];
                            }),
                    ]),
                ])->columnSpanFull()->skippable(),
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
                Actions\Action::make('test_pdf')
                    ->label('Test PDF')
                    ->icon('heroicon-o-beaker')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('record_id')
                            ->label('Enter Record ID to Test (e.g. 1)')
                            ->required()
                            ->numeric(),
                    ])
                    ->action(function (Models\DocumentTemplate $record, array $data) {
                        if (empty($record->model_class)) {
                            \Filament\Notifications\Notification::make()
                                ->title('No Database Model Selected')
                                ->body('You must select a Database Model in the Template Details and click Save before you can test.')
                                ->warning()
                                ->send();
                            return;
                        }

                        if (!class_exists($record->model_class)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Invalid Model')
                                ->body("The model {$record->model_class} does not exist.")
                                ->danger()
                                ->send();
                            return;
                        }

                        $sampleRecord = $record->model_class::find($data['record_id']);
                        
                        if (!$sampleRecord) {
                            \Filament\Notifications\Notification::make()
                                ->title('Record Not Found')
                                ->body("No {$record->model_class} found with ID {$data['record_id']}.")
                                ->danger()
                                ->send();
                            return;
                        }

                        $renderer = app(\Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer::class);
                        $pdf = $renderer->render($record, $sampleRecord);

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'test_document_' . $data['record_id'] . '.pdf');
                    }),
                Actions\Action::make('preview_pdf')
                    ->label('Preview PDF')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->action(function (Models\DocumentTemplate $record) {
                        if (empty($record->model_class)) {
                            \Filament\Notifications\Notification::make()
                                ->title('No Database Model Selected')
                                ->body('You must select a Database Model in the Template Details and click Save before you can preview the PDF.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $data = [];
                        if (class_exists($record->model_class)) {
                            $sampleRecord = $record->model_class::first();
                            if ($sampleRecord) {
                                $data = $sampleRecord;
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('No Records Found')
                                    ->body("There are no records in the {$record->model_class} table to preview with.")
                                    ->warning()
                                    ->send();
                                return;
                            }
                        }

                        $renderer = app(\Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer::class);
                        $pdf = $renderer->render($record, $data);

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'preview.pdf');
                    }),
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
