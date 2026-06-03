<?php

namespace Chanthoeun\FilamentDocumentBuilder\Actions;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

class GeneratePdfAction extends Action
{
    protected ?string $templateType = null;
    protected $dataResolver = null;

    public static function getDefaultName(): ?string
    {
        return 'generate_pdf';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Download PDF');
        $this->icon('heroicon-o-document-arrow-down');
        
        $this->action(function (Model $record) {
            $template = DocumentTemplate::where('type', $this->templateType)->first();
            
            if (!$template) {
                // If template type isn't specified, just grab the first one as fallback
                $template = DocumentTemplate::first();
            }

            if (!$template) {
                return; // Or throw exception / show notification
            }

            $data = $this->dataResolver ? call_user_func($this->dataResolver, $record) : $record;

            $renderer = app(DocumentRenderer::class);
            $pdf = $renderer->render($template, $data);

            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->output();
            }, 'document.pdf');
        });
    }

    public function templateType(string $type): static
    {
        $this->templateType = $type;

        return $this;
    }

    public function data(\Closure $resolver): static
    {
        $this->dataResolver = $resolver;

        return $this;
    }
}
