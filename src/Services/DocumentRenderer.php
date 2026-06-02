<?php

namespace Chanthoeun\FilamentDocumentBuilder\Services;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Spatie\LaravelPdf\Facades\Pdf;
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
            return data_get($data, $key, $matches[0]); // Return the original string if key not found
        }, $content);
    }

    public function render(DocumentTemplate $template, array $data = [])
    {
        $blocks = $template->content ?? [];
        $renderedBlocks = [];

        foreach ($blocks as $block) {
            $type = $block['type'];
            $blockData = $block['data'];

            // Replace variables in text content
            if ($type === 'text' && isset($blockData['content'])) {
                $blockData['content'] = $this->replaceVariables($blockData['content'], $data);
            }
            if ($type === 'header') {
                if (isset($blockData['title'])) {
                    $blockData['title'] = $this->replaceVariables($blockData['title'], $data);
                }
                if (isset($blockData['subtitle'])) {
                    $blockData['subtitle'] = $this->replaceVariables($blockData['subtitle'], $data);
                }
            }

            // Render the blade view for the block
            $viewName = "filament-document-builder::blocks.{$type}";
            if (view()->exists($viewName)) {
                $renderedBlocks[] = view($viewName, [
                    'block' => $blockData,
                    'data' => $data,
                ])->render();
            }
        }

        $html = view('filament-document-builder::document', [
            'blocksHtml' => implode("\n", $renderedBlocks),
            'settings' => $template->page_settings,
        ])->render();

        $format = data_get($template->page_settings, 'format', 'a4');
        
        $pdf = Pdf::html($html)->format($format);
        
        $orientation = data_get($template->page_settings, 'orientation', 'portrait');
        if ($orientation === 'landscape') {
            $pdf->landscape();
        }

        return $pdf;
    }
}
