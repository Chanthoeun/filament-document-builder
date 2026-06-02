<?php

namespace Chanthoeun\FilamentDocumentBuilder\Services;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;
use Illuminate\Support\Str;

class DocumentRenderer
{
    /**
     * Replaces string variables like {{ variable.name }} with actual data.
     */
    protected function replaceVariables(?string $content, array $data): ?string
    {
        if (empty($content)) {
            return $content;
        }

        // Basic replacement logic for {{ variable }} or {{ variable.key }}
        return preg_replace_callback('/{{\s*(.+?)\s*}}/', function ($matches) use ($data) {
            $key = $matches[1];
            return data_get($data, $key, ''); // Return empty string if key not found to print a clean blank form
        }, $content);
    }

    public function render(DocumentTemplate $template, array $data = [])
    {
        $htmlContent = $template->content ?? '';
        
        // Replace variables in the entire HTML content
        $htmlContent = $this->replaceVariables($htmlContent, $data);

        $html = view('filament-document-builder::document', [
            'htmlContent' => $htmlContent,
            'settings' => $template->page_settings,
        ])->render();

        $format = data_get($template->page_settings, 'format', 'a4');
        $orientation = data_get($template->page_settings, 'orientation', 'portrait');
        
        $pdf = Pdf::loadHTML($html, [
            'format' => $format,
            'orientation' => $orientation === 'landscape' ? 'L' : 'P',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        return $pdf;
    }
}
