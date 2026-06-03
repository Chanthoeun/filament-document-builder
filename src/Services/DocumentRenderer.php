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
    protected function replaceVariables(?string $content, array|object $data): ?string
    {
        if (empty($content)) {
            return $content;
        }

        // Basic replacement logic for {{ variable }} or {{ variable.key }}
        // We use the 's' modifier to match across lines, and strip_tags to handle TinyMCE styling inside braces
        return preg_replace_callback('/{{(.*?)}}/s', function ($matches) use ($data) {
            $key = trim(strip_tags($matches[1]));
            // Clean up any html entities or non-breaking spaces
            $key = html_entity_decode(str_replace('&nbsp;', '', $key));
            $key = trim($key);
            
            $value = data_get($data, $key);
            if ($value === null || $value === '') {
                $dataType = is_object($data) ? get_class($data) : gettype($data);
                return '[NOT FOUND: ' . $key . ' IN ' . $dataType . ']';
            }
            return $value;
        }, $content);
    }

    public function render(DocumentTemplate $template, array|object $data = [])
    {
        $htmlContent = $template->content ?? '';
        
        // Replace variables in the entire HTML content
        $htmlContent = $this->replaceVariables($htmlContent, $data);

        // mPDF compatibility fixes: convert Flexbox to inline-block
        $htmlContent = preg_replace('/display:\s*inline-flex;?/', 'display: inline-block;', $htmlContent);
        $htmlContent = preg_replace('/align-items:\s*center;?/', 'vertical-align: middle;', $htmlContent);
        $htmlContent = preg_replace('/justify-content:\s*center;?/', 'text-align: center;', $htmlContent);
        
        // mPDF vertical centering fix: if a div has height and inline-block, set line-height to match height
        $htmlContent = preg_replace_callback(
            '/<div[^>]*style="([^"]*height:\s*(\d+px)[^"]*)"[^>]*>/i',
            function ($matches) {
                $style = $matches[1];
                $height = $matches[2];
                // Only add line-height if it doesn't exist
                if (strpos($style, 'line-height') === false) {
                    $newStyle = $style . ' line-height: ' . $height . ';';
                    return str_replace($style, $newStyle, $matches[0]);
                }
                return $matches[0];
            },
            $htmlContent
        );

        // Convert local asset URLs to absolute file paths so mPDF doesn't fail on Docker/localhost networking
        $appUrl = config('app.url');
        if (!str_ends_with($appUrl, '/')) {
            $appUrl .= '/';
        }
        
        // Convert both app.url/storage and /storage to public_path
        $htmlContent = preg_replace(
            '/src=["\'](' . preg_quote($appUrl, '/') . ')?storage\/(.*?)["\']/i',
            'src="' . public_path('storage/$2') . '"',
            $htmlContent
        );

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
