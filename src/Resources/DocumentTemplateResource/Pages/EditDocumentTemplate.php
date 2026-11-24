<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Pages;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Chanthoeun\FilamentDocumentBuilder\Support\LayoutTemplates;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditDocumentTemplate extends EditRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title(__('filament-panels::resources/pages/edit-record.notifications.saved.title'))
            ->body(__('filament-document-builder::document-builder.messages.saved'))
            ->success();
    }

    protected function resolveSampleRecord(DocumentTemplate $record): mixed
    {
        if (empty($record->model_class) || ! class_exists($record->model_class)) {
            return new \stdClass;
        }

        $modelClass = $record->model_class;

        if ($modelClass === 'Chanthoeun\FilamentCustomForms\Models\CustomFormEntry' && ! empty($record->type) && str_starts_with($record->type, 'custom_form_')) {
            $formId = str_replace('custom_form_', '', $record->type);
            $entry = $modelClass::where('custom_form_id', $formId)->latest()->first();
            if ($entry) {
                return $entry;
            }
        }

        $sample = $modelClass::latest()->first();
        if ($sample) {
            return $sample;
        }

        return new $modelClass;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('load_example_layout')
                ->label('Load Example Layout')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Load Example Layout')
                ->modalDescription('Warning: This will overwrite any existing content in your Document Designer.')
                ->modalSubmitActionLabel('Load Layout')
                ->form([
                    Select::make('layout')
                        ->label('Select a Layout')
                        ->options(LayoutTemplates::getOptions())
                        ->required(),
                ])
                ->action(function (array $data) {
                    $html = LayoutTemplates::getTemplate($data['layout']);

                    $this->data['content'] = $html;

                    if ($data['layout'] === 'certificate') {
                        $this->data['page_settings']['orientation'] = 'landscape';
                    } else {
                        $this->data['page_settings']['orientation'] = 'portrait';
                    }

                    Notification::make()
                        ->title('Layout Loaded')
                        ->success()
                        ->send();
                }),
            Action::make('preview_pdf')
                ->label(__('filament-document-builder::document-builder.labels.preview_pdf'))
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('success')
                ->modalHeading(fn () => __('filament-document-builder::document-builder.labels.preview_pdf').': '.($this->getRecord()?->getAttribute('name') ?? ''))
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('filament-document-builder::document-builder.actions.close'))
                ->extraModalFooterActions(fn (Action $action): array => [
                    Action::make('download_pdf')
                        ->label(__('filament-document-builder::document-builder.actions.download_pdf'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->action(function () {
                            /** @var DocumentTemplate|null $record */
                            $record = $this->getRecord();
                            if (! $record) {
                                return null;
                            }
                            $record = clone $record;
                            $record->fill($this->data);
                            $sampleRecord = $this->resolveSampleRecord($record);
                            $renderer = app(DocumentRenderer::class);
                            $pdf = $renderer->render($record, $sampleRecord);

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->output();
                            }, 'preview-'.Str::slug((string) $record->getAttribute('name')).'.pdf');
                        }),
                ])
                ->modalContent(function () {
                    /** @var DocumentTemplate|null $record */
                    $record = $this->getRecord();
                    if (! $record) {
                        return null;
                    }
                    $record = clone $record;
                    $record->fill($this->data);

                    if (empty($record->model_class)) {
                        return view('filament-document-builder::preview-pdf-modal', [
                            'error' => __('filament-document-builder::document-builder.messages.no_model_selected'),
                            'base64' => null,
                        ]);
                    }

                    try {
                        $sampleRecord = $this->resolveSampleRecord($record);
                        $renderer = app(DocumentRenderer::class);
                        $pdf = $renderer->render($record, $sampleRecord);
                        $base64 = base64_encode($pdf->output());

                        return view('filament-document-builder::preview-pdf-modal', [
                            'base64' => $base64,
                            'error' => null,
                        ]);
                    } catch (\Throwable $e) {
                        return view('filament-document-builder::preview-pdf-modal', [
                            'error' => __('filament-document-builder::document-builder.messages.unable_to_generate_preview').': '.$e->getMessage(),
                            'base64' => null,
                        ]);
                    }
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
