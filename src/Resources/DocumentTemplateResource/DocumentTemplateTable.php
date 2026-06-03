<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions as FilamentActions;

class DocumentTemplateTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Template Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (DocumentTemplate $record): string => $record->model_class ? 'Model: ' . class_basename($record->model_class) : 'No model linked'),
                    
                Tables\Columns\TextColumn::make('type')
                    ->label('Document Type')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'invoice' => 'success',
                        'receipt' => 'warning',
                        'certificate' => 'info',
                        'application' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created On')
                    ->dateTime('M j, Y h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M j, Y h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->actions([
                FilamentActions\Action::make('preview_pdf')
                    ->label('Preview PDF')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->action(function (DocumentTemplate $record) {
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
                FilamentActions\EditAction::make(),
                FilamentActions\DeleteAction::make(),
            ])
            ->bulkActions([
                FilamentActions\BulkActionGroup::make([
                    FilamentActions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
