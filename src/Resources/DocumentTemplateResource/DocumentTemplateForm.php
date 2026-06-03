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
                    \Filament\Schemas\Components\Wizard\Step::make(__('filament-document-builder::document-builder.labels.template_details'))->schema([
                        \Filament\Schemas\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('name')
                                ->label(__('filament-document-builder::document-builder.labels.template_name'))
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('type')
                                ->label(__('filament-document-builder::document-builder.labels.template_type'))
                                ->placeholder(__('filament-document-builder::document-builder.labels.type_placeholder'))
                                ->maxLength(255),
                            Forms\Components\Select::make('model_class')
                                ->label(__('filament-document-builder::document-builder.labels.database_model'))
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
                                ->placeholder(__('filament-document-builder::document-builder.labels.model_placeholder')),
                        ]),
                        Forms\Components\KeyValue::make('page_settings')
                            ->label(__('filament-document-builder::document-builder.labels.page_settings'))
                            ->keyLabel(__('filament-document-builder::document-builder.labels.setting'))
                            ->valueLabel(__('filament-document-builder::document-builder.labels.value'))
                            ->default([
                                'format' => 'a4',
                                'orientation' => 'portrait',
                                'default_font' => 'calibri',
                                'margin_left' => '15',
                                'margin_right' => '15',
                                'margin_top' => '16',
                                'margin_bottom' => '16',
                                'margin_header' => '9',
                                'margin_footer' => '9',
                            ]),
                        Forms\Components\Repeater::make('extra_data_sources')
                            ->label(__('filament-document-builder::document-builder.labels.additional_data_sources'))
                            ->schema([
                                Forms\Components\TextInput::make('variable_name')
                                    ->label(__('filament-document-builder::document-builder.labels.variable_name'))
                                    ->required()
                                    ->placeholder(__('filament-document-builder::document-builder.labels.variable_placeholder')),
                                Forms\Components\Select::make('model_class')
                                    ->label(__('filament-document-builder::document-builder.labels.database_model'))
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
                                    ->label(__('filament-document-builder::document-builder.labels.retrieval_method'))
                                    ->required()
                                    ->options([
                                        'first' => __('filament-document-builder::document-builder.labels.first_record'),
                                        'latest' => __('filament-document-builder::document-builder.labels.latest_record'),
                                    ])
                                    ->default('first'),
                            ])
                            ->columns(3)
                            ->live()
                            ->itemLabel(fn (array $state): ?string => $state['variable_name'] ?? null),
                    ]),
                    \Filament\Schemas\Components\Wizard\Step::make(__('filament-document-builder::document-builder.labels.document_designer'))->schema([
                        \AmidEsfahani\FilamentTinyEditor\TinyEditor::make('content')
                            ->label(__('filament-document-builder::document-builder.labels.document_designer'))
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
                                    'menubar' => 'file edit view insert format tools table help',
                                    'font_family_formats' => 'Arial=arial,helvetica,sans-serif; Calibri=calibri,sans-serif; Times New Roman="times new roman",times,serif; Khmer Battambang=Battambang,sans-serif; Khmer Moul Light="Khmer OS Muol Light",Moul,cursive; Khmer Siemreap=Siemreap,sans-serif;',
                                    'content_style' => '@import url("https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&family=Moul&family=Siemreap&display=swap"); html { background: #f3f4f6; padding: 20px 0; } body { font-family: Calibri, "Battambang", Arial, sans-serif; background: #fff; width: 210mm; min-height: 297mm; padding: 10mm 15mm; margin: 0 auto !important; box-shadow: 0 0 10px rgba(0,0,0,0.1); box-sizing: border-box; } p { margin-top: 0; }',
                                    'plugins' => 'custom_shapes accordion autoresize codesample directionality advlist autolink link image lists charmap preview anchor pagebreak searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media table emoticons help template',
                                    'toolbar' => 'undo redo removeformat | fontfamily fontsize fontsizeinput font_size_formats styles | bold italic underline | rtl ltr | alignjustify alignright aligncenter alignleft | numlist bullist outdent indent accordion | forecolor backcolor | blockquote table toc hr | image link anchor media codesample emoticons template insert_variable | visualblocks print preview wordcount fullscreen help',
                                    'templates' => [
                                        [
                                            'title' => 'Layout - 2 Columns',
                                            'description' => 'A table with 2 equal columns',
                                            'content' => '<table style="width: 100%; border-collapse: collapse; border: none;"><tbody><tr><td style="width: 50%; padding: 5px; vertical-align: top; border: none;">Column 1</td><td style="width: 50%; padding: 5px; vertical-align: top; border: none;">Column 2</td></tr></tbody></table><p><br></p>'
                                        ],
                                        [
                                            'title' => 'Layout - 3 Columns',
                                            'description' => 'A table with 3 equal columns',
                                            'content' => '<table style="width: 100%; border-collapse: collapse; border: none;"><tbody><tr><td style="width: 33.33%; padding: 5px; vertical-align: top; border: none;">Column 1</td><td style="width: 33.33%; padding: 5px; vertical-align: top; border: none;">Column 2</td><td style="width: 33.33%; padding: 5px; vertical-align: top; border: none;">Column 3</td></tr></tbody></table><p><br></p>'
                                        ],
                                        [
                                            'title' => 'Layout - 4 Columns',
                                            'description' => 'A table with 4 equal columns',
                                            'content' => '<table style="width: 100%; border-collapse: collapse; border: none;"><tbody><tr><td style="width: 25%; padding: 5px; vertical-align: top; border: none;">Column 1</td><td style="width: 25%; padding: 5px; vertical-align: top; border: none;">Column 2</td><td style="width: 25%; padding: 5px; vertical-align: top; border: none;">Column 3</td><td style="width: 25%; padding: 5px; vertical-align: top; border: none;">Column 4</td></tr></tbody></table><p><br></p>'
                                        ],
                                        [
                                            'title' => 'Shape - Circle (Logo)',
                                            'description' => 'A circular shape for logos or avatars',
                                            'content' => '<div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; border-radius: 50%; text-align: center;">LOGO</div>'
                                        ],
                                        [
                                            'title' => 'Shape - Square Box',
                                            'description' => 'A simple square box',
                                            'content' => '<div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; text-align: center;">BOX</div>'
                                        ],
                                        [
                                            'title' => 'Shape - Rectangle Photo Box (4x6)',
                                            'description' => '4x6 Photo Box for Khmer forms',
                                            'content' => '<div style="display: inline-block; width: 80px; height: 100px; border: 1px solid #000; text-align: center;">រូបថត<br>៤x៦</div>'
                                        ],
                                        [
                                            'title' => 'Element - Checkbox (Small Square)',
                                            'description' => 'Small square for checkboxes',
                                            'content' => '<div style="display: inline-block; width: 16px; height: 16px; border: 1px solid #000; text-align: center;"></div>'
                                        ],
                                        [
                                            'title' => 'Shape - Rounded Rectangle',
                                            'description' => 'A rectangle with rounded corners',
                                            'content' => '<div style="display: inline-block; width: 120px; height: 60px; border: 1px solid #000; border-radius: 10px; text-align: center;">TEXT</div>'
                                        ],
                                        [
                                            'title' => 'Shape - Oval',
                                            'description' => 'An oval shape',
                                            'content' => '<div style="display: inline-block; width: 120px; height: 60px; border: 1px solid #000; border-radius: 50%; text-align: center;">OVAL</div>'
                                        ],
                                        [
                                            'title' => 'Element - Signature Area',
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
