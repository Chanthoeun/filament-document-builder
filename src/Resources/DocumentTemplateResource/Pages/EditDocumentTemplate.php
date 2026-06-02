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
            \Filament\Actions\Action::make('preview_pdf')
                ->label('Preview PDF')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('success')
                ->action(function () {
                    $renderer = app(\Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer::class);
                    $pdf = $renderer->render($this->record, []);

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, 'preview-' . \Illuminate\Support\Str::slug($this->record->name) . '.pdf');
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
