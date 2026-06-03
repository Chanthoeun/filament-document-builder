<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource;

use Filament\Forms;
use Filament\Schemas\Schema;

class DocumentTemplateForm
{
    public static function schema(Schema $schema): Schema
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
                        Forms\Components\Repeater::make('extra_data_sources')
                            ->label('Additional Data Sources')
                            ->schema([
                                Forms\Components\TextInput::make('variable_name')
                                    ->required()
                                    ->placeholder('e.g. school'),
                                Forms\Components\Select::make('model_class')
                                    ->label('Database Model')
                                    ->required()
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
                                    ->searchable(),
                                Forms\Components\Select::make('retrieval_method')
                                    ->required()
                                    ->options([
                                        'first' => 'First Record',
                                        'latest' => 'Latest Record',
                                    ])
                                    ->default('first'),
                            ])
                            ->columns(3)
                            ->live()
                            ->itemLabel(fn (array $state): ?string => $state['variable_name'] ?? null),
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
                                }

                                $extraSources = $get('extra_data_sources') ?? [];
                                foreach ($extraSources as $source) {
                                    if (!empty($source['variable_name'])) {
                                        $vars[] = $source['variable_name'];
                                        
                                        // Try to load fillable fields from the extra model to give better autocompletion
                                        if (!empty($source['model_class']) && class_exists($source['model_class'])) {
                                            $extraModel = new $source['model_class'];
                                            $fields = array_merge(['id', 'created_at', 'updated_at'], $extraModel->getFillable());
                                            foreach ($fields as $field) {
                                                $vars[] = $source['variable_name'] . '.' . $field;
                                            }
                                        }
                                    }
                                }
                                
                                sort($vars);

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
}
