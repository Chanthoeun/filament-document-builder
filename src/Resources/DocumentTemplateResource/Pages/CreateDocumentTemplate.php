<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Pages;

use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDocumentTemplate extends CreateRecord
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
        ];
    }
}
