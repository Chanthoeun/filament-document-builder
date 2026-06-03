<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Pages;

use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocumentTemplate extends EditRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('load_example_layout')
                ->label('Load Example Layout')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Load Example Layout')
                ->modalDescription('Warning: This will overwrite any existing content in your Document Designer.')
                ->modalSubmitActionLabel('Load Layout')
                ->form([
                    \Filament\Forms\Components\Select::make('layout')
                        ->label('Select a Layout')
                        ->options(\Chanthoeun\FilamentDocumentBuilder\Support\LayoutTemplates::getOptions())
                        ->required(),
                ])
                ->action(function (array $data) {
                    $html = \Chanthoeun\FilamentDocumentBuilder\Support\LayoutTemplates::getTemplate($data['layout']);
                    
                    $this->data['content'] = $html;

                    if ($data['layout'] === 'certificate') {
                        $this->data['page_settings']['orientation'] = 'landscape';
                    } else {
                        $this->data['page_settings']['orientation'] = 'portrait';
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Layout Loaded')
                        ->success()
                        ->send();
                }),
            \Filament\Actions\Action::make('preview_pdf')
                ->label('Preview PDF')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('success')
                ->action(function () {
                    $record = $this->record;

                    if (empty($record->model_class)) {
                        \Filament\Notifications\Notification::make()
                            ->title('No Database Model Selected')
                            ->body('You must select a Database Model in the Template Details and click Save before you can preview.')
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
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Invalid Model')
                            ->body("The model {$record->model_class} does not exist.")
                            ->danger()
                            ->send();
                        return;
                    }

                    $renderer = app(\Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer::class);
                    $pdf = $renderer->render($record, $data);

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, 'preview-' . \Illuminate\Support\Str::slug($record->name) . '.pdf');
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
