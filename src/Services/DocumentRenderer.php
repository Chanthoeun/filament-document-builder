<?php

namespace Chanthoeun\FilamentDocumentBuilder\Services;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;
use Picqer\Barcode\BarcodeGeneratorPNG;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DocumentRenderer
{
    /**
     * Cache for extra data sources during the request lifecycle to prevent N+1 queries across multiple PDFs.
     */
    protected static array $extraDataCache = [];

    /**
     * Clear the extra data source cache. Call this between requests in long-running workers
     * (e.g. Octane, Horizon) to prevent stale data.
     */
    public static function clearCache(): void
    {
        self::$extraDataCache = [];
    }

    /**
     * Resolve a value by key, falling back through parent scopes if null.
     */
    protected function resolveWithParentScope(mixed $value, array|object $data, string $key): mixed
    {
        $currentContext = $data;
        while ($value === null && is_array($currentContext) && array_key_exists('_parent', $currentContext)) {
            $currentContext = $currentContext['_parent'];
            $value = data_get($currentContext, $key);
        }

        return $value;
    }

    /**
     * Parse the template HTML and extract relation paths for eager loading.
     */
    protected function parseRelationPaths(string $content): array
    {
        preg_match_all('/{{\s*(?:#foreach\s+)?([a-zA-Z0-9_\.]+)/', $content, $matches);

        $relationsToLoad = [];
        foreach ($matches[1] as $match) {
            $parts = explode('.', $match);
            if (count($parts) > 1) {
                array_pop($parts);
                $relationsToLoad[] = implode('.', $parts);
            }
        }

        return array_unique($relationsToLoad);
    }

    /**
     * Eager load relations used in the template to prevent N+1 queries.
     * Accepts a single Model or an Eloquent Collection.
     */
    protected function preloadRelations(?string $content, Model|Collection $data): void
    {
        if (empty($content)) {
            return;
        }

        $relationsToLoad = $this->parseRelationPaths($content);

        if (empty($relationsToLoad)) {
            return;
        }

        $referenceModel = $data instanceof Collection ? $data->first() : $data;

        if (! $referenceModel instanceof Model) {
            return;
        }

        $validRelations = [];
        foreach ($relationsToLoad as $rel) {
            if (method_exists($referenceModel, explode('.', $rel)[0])) {
                $validRelations[] = $rel;
            }
        }

        if (! empty($validRelations)) {
            $data->loadMissing($validRelations);
        }
    }

    /**
     * Replaces loop blocks like {{#foreach items as item}} ... {{/foreach}}
     */
    protected function replaceLoops(?string $content, array|object $data): ?string
    {
        if (empty($content)) {
            return $content;
        }

        // Use precise regex to prevent catastrophic backtracking and avoid swallowing preceding tags
        return preg_replace_callback(
            '/{{\s*#foreach\s+([a-zA-Z0-9_\.]+)\s+as\s+([a-zA-Z0-9_]+)\s*}}(.*?){{\s*\/foreach\s*}}/is',
            function ($matches) use ($data) {
                $arrayPath = trim(strip_tags($matches[1]));
                $arrayPath = html_entity_decode(str_replace('&nbsp;', '', $arrayPath));

                $itemName = trim(strip_tags($matches[2]));
                $itemName = html_entity_decode(str_replace('&nbsp;', '', $itemName));

                $blockContent = $matches[3];

                $items = $this->resolveWithParentScope(data_get($data, $arrayPath), $data, $arrayPath);

                if (! is_iterable($items)) {
                    return '';
                }

                $result = '';

                foreach ($items as $item) {
                    $loopData = [
                        '_parent' => $data,
                        $itemName => $item,
                    ];

                    $result .= $this->replaceVariables($blockContent, $loopData);
                }

                return $result;
            },
            $content
        );
    }

    /**
     * Generate a QR code image tag from a value.
     * Produces an SVG base64 data URI so mPDF can embed it without external requests.
     */
    protected function generateQrCodeTag(string $value, int $size): string
    {
        try {
            $svg = (string) QrCode::format('svg')->size($size)->generate($value);

            return '<img src="data:image/svg+xml;base64,'.base64_encode($svg).'" '
                .'width="'.$size.'" height="'.$size.'" style="display:inline-block;" />';
        } catch (\Throwable $e) {
            report($e);

            return '<!-- QR code generation failed -->';
        }
    }

    /**
     * Map of human-readable barcode type names to BarcodeGeneratorPNG constants.
     */
    protected static array $barcodeTypes = [
        'C128' => 'TYPE_CODE_128',
        'C128A' => 'TYPE_CODE_128_A',
        'C128B' => 'TYPE_CODE_128_B',
        'C128C' => 'TYPE_CODE_128_C',
        'C39' => 'TYPE_CODE_39',
        'C39+' => 'TYPE_CODE_39_CHECKSUM',
        'C93' => 'TYPE_CODE_93',
        'EAN13' => 'TYPE_EAN_13',
        'EAN8' => 'TYPE_EAN_8',
        'UPCA' => 'TYPE_UPC_A',
        'UPCE' => 'TYPE_UPC_E',
        'I25' => 'TYPE_INTERLEAVED_2_5',
        'S25' => 'TYPE_STANDARD_2_5',
        'CODABAR' => 'TYPE_CODABAR',
        'MSI' => 'TYPE_MSI',
    ];

    /**
     * Generate a barcode image tag from a value using the specified type.
     * Produces a PNG base64 data URI so mPDF can embed it without external requests.
     */
    protected function generateBarcodeTag(string $value, string $type, int $widthFactor, int $height): string
    {
        try {
            $generator = new BarcodeGeneratorPNG;
            $constant = self::$barcodeTypes[$type] ?? 'TYPE_CODE_128';
            $png = $generator->getBarcode($value, constant(BarcodeGeneratorPNG::class.'::'.$constant), $widthFactor, $height);

            return '<img src="data:image/png;base64,'.base64_encode($png).'" '
                .'style="display:inline-block; height:'.$height.'px;" />';
        } catch (\Throwable $e) {
            report($e);

            return '<!-- Barcode generation failed -->';
        }
    }

    /**
     * Replaces QR code blocks: {{#qrcode variable size=100}}
     */
    protected function replaceQrCodes(?string $content, array|object $data): ?string
    {
        if (empty($content)) {
            return $content;
        }

        return preg_replace_callback(
            '/{{\s*#qrcode\s+([a-zA-Z0-9_\.]+)(?:\s+size=(\d+))?\s*}}/i',
            function ($matches) use ($data) {
                $key = trim($matches[1]);
                $size = isset($matches[2]) ? (int) $matches[2] : 100;

                $value = $this->resolveWithParentScope(data_get($data, $key), $data, $key);

                if ($value === null || $value === '') {
                    return '';
                }

                return $this->generateQrCodeTag((string) $value, $size);
            },
            $content
        );
    }

    /**
     * Replaces barcode blocks: {{#barcode variable type=C128 width=2 height=30}}
     */
    protected function replaceBarcodes(?string $content, array|object $data): ?string
    {
        if (empty($content)) {
            return $content;
        }

        return preg_replace_callback(
            '/{{\s*#barcode\s+([a-zA-Z0-9_\.]+)(?:\s+type=([a-zA-Z0-9_]+))?(?:\s+width=(\d+))?(?:\s+height=(\d+))?\s*}}/i',
            function ($matches) use ($data) {
                $key = trim($matches[1]);
                $type = isset($matches[2]) ? strtoupper($matches[2]) : 'C128';
                $widthFactor = isset($matches[3]) ? (int) $matches[3] : 2;
                $height = isset($matches[4]) ? (int) $matches[4] : 30;

                $value = $this->resolveWithParentScope(data_get($data, $key), $data, $key);

                if ($value === null || $value === '') {
                    return '';
                }

                return $this->generateBarcodeTag((string) $value, $type, $widthFactor, $height);
            },
            $content
        );
    }

    /**
     * Replaces string variables like {{ variable.name }} with actual data.
     */
    protected function replaceVariables(?string $content, array|object $data): ?string
    {
        if (empty($content)) {
            return $content;
        }

        // Basic replacement logic for {{ variable }} or {{ variable.key }}
        return preg_replace_callback('/{{([^{}]*)}}/', function ($matches) use ($data) {
            $key = trim(strip_tags($matches[1]));
            $key = html_entity_decode(str_replace('&nbsp;', '', $key));
            $key = trim($key);

            if (str_starts_with($key, '#foreach') || str_starts_with($key, '/foreach')
                || str_starts_with($key, '#qrcode') || str_starts_with($key, '/qrcode')
                || str_starts_with($key, '#barcode') || str_starts_with($key, '/barcode')) {
                return $matches[0];
            }

            $value = $this->resolveWithParentScope(data_get($data, $key), $data, $key);

            if ($value === null || $value === '') {
                return ''; // Return empty string instead of debug text in production
            }
            if (is_array($value) || is_object($value)) {
                return ''; // Ignore array printing rather than crashing mPDF
            }

            return $value;
        }, $content);
    }

    protected function processHtmlContent(DocumentTemplate $template, array|object $data = []): string
    {
        $htmlContent = $template->content ?? '';

        // Eager load relations to prevent N+1 performance bottlenecks
        if ($data instanceof Model || $data instanceof Collection) {
            $this->preloadRelations($htmlContent, $data);
        }

        $data = $this->resolveExtraDataSources($template, $data);

        // Process QR codes and barcodes before standard variable replacement
        $htmlContent = $this->replaceQrCodes($htmlContent, $data);
        $htmlContent = $this->replaceBarcodes($htmlContent, $data);

        $htmlContent = $this->replaceLoops($htmlContent, $data);
        $htmlContent = $this->replaceVariables($htmlContent, $data);

        $htmlContent = $this->applyMpdfPolyfills($htmlContent);

        return $htmlContent;
    }

    /**
     * Fetch extra data sources defined in the template and merge them into the data array.
     */
    protected function resolveExtraDataSources(DocumentTemplate $template, array|object $data): array|object
    {
        $extraData = [];
        if (! empty($template->extra_data_sources)) {
            foreach ($template->extra_data_sources as $source) {
                if (! empty($source['variable_name']) && ! empty($source['model_class']) && class_exists($source['model_class']) && is_a($source['model_class'], Model::class, true)) {
                    $method = $source['retrieval_method'] ?? 'first';
                    $cacheKey = md5($source['model_class'].'_'.$method);

                    if (! isset(self::$extraDataCache[$cacheKey])) {
                        if ($method === 'latest') {
                            self::$extraDataCache[$cacheKey] = $source['model_class']::latest()->first();
                        } else {
                            self::$extraDataCache[$cacheKey] = $source['model_class']::first();
                        }
                    }

                    $extraData[$source['variable_name']] = self::$extraDataCache[$cacheKey];
                }
            }
        }

        if (! empty($extraData)) {
            $data = array_merge(['_parent' => $data], $extraData);
        }

        return $data;
    }

    /**
     * Strip zero-width and invisible formatting characters that break mPDF font shaping or hide empty blocks.
     */
    protected function stripInvisibleCharacters(string $htmlContent): string
    {
        $htmlContent = str_replace(["\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBB\xBF"], '', $htmlContent);
        $htmlContent = str_ireplace(
            ['&#8203;', '&#8204;', '&#8205;', '&#x200B;', '&#x200C;', '&#x200D;', '&ZeroWidthSpace;'],
            '',
            $htmlContent
        );

        return preg_replace('/<wbr\b[^>]*\/?>/i', '', $htmlContent) ?? $htmlContent;
    }

    /**
     * Determine whether an HTML fragment has visible box styling (borders, backgrounds, or explicit height).
     */
    protected function hasVisualBoxStyle(string $fragment): bool
    {
        if (! preg_match_all('/\bstyle\s*=\s*([\'"])(.*?)\1/is', $fragment, $matches)) {
            return false;
        }

        foreach ($matches[2] as $style) {
            if (preg_match('/(?:^|;)\s*(?:border(?:-(?:top|right|bottom|left|width|style))?|background(?:-color|-image)?|(?:min-)?height)\s*:\s*(?!none\b|transparent\b|0+(?:px|mm|cm|pt|em|rem|%)?\s*(?:;|$))/i', $style)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether an HTML fragment produces no visible content in mPDF.
     */
    protected function isEmptyHtmlFragment(string $fragment): bool
    {
        if (preg_match('/<(?:img|svg|hr|table|iframe|canvas|video|audio|object|embed|input|textarea|select|button)\b/i', $fragment)) {
            return false;
        }

        if ($this->hasVisualBoxStyle($fragment)) {
            return false;
        }

        $decoded = html_entity_decode(strip_tags($fragment), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleaned = preg_replace('/[\s\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+/u', '', $decoded) ?? '';

        return $cleaned === '';
    }

    /**
     * Find the closing offset (exclusive) of a block tag starting right after its opening tag.
     */
    protected function findMatchingTagEnd(string $html, string $tag, int $openTagEnd): ?int
    {
        $offset = $openTagEnd;
        $depth = 1;
        $pattern = '/<\/?'.preg_quote($tag, '/').'(?=[\s>])[^>]*>/i';

        while (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $tagText = $match[0][0];
            $tagPos = $match[0][1];
            $offset = $tagPos + strlen($tagText);

            if (str_starts_with($tagText, '</')) {
                $depth--;
                if ($depth === 0) {
                    return $offset;
                }
            } elseif (! str_ends_with(rtrim($tagText, '> '), '/')) {
                $depth++;
            }
        }

        return null;
    }

    /**
     * Find the opening offset of a block tag given the start offset of its closing tag.
     */
    protected function findMatchingTagStart(string $html, string $tag, int $closeTagStart): ?int
    {
        $prefix = substr($html, 0, $closeTagStart);
        if (! preg_match_all('/<\/?'.preg_quote($tag, '/').'(?=[\s>])[^>]*>/i', $prefix, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $depth = 1;
        for ($i = count($matches[0]) - 1; $i >= 0; $i--) {
            $tagText = $matches[0][$i][0];
            $tagPos = $matches[0][$i][1];

            if (str_starts_with($tagText, '</')) {
                $depth++;
            } elseif (! str_ends_with(rtrim($tagText, '> '), '/')) {
                $depth--;
                if ($depth === 0) {
                    return $tagPos;
                }
            }
        }

        return null;
    }

    /**
     * Strip leading or trailing empty blocks (`<p>`, `<div>`, headings, `<br>`, whitespace) from a segment.
     */
    protected function trimBoundaryEmptyBlocks(string $html, bool $fromStart): string
    {
        while (true) {
            if ($fromStart) {
                $trimmed = ltrim($html);
                if ($trimmed !== $html) {
                    $html = $trimmed;
                }

                if (preg_match('/^<br\b[^>]*\/?>/i', $html, $match)) {
                    $html = substr($html, strlen($match[0]));
                    continue;
                }

                if (preg_match('/^<(p|div|h[1-6])\b[^>]*>/i', $html, $match)) {
                    $tag = strtolower($match[1]);
                    $end = $this->findMatchingTagEnd($html, $tag, strlen($match[0]));
                    if ($end !== null && $this->isEmptyHtmlFragment(substr($html, 0, $end))) {
                        $html = substr($html, $end);
                        continue;
                    }
                }
            } else {
                $trimmed = rtrim($html);
                if ($trimmed !== $html) {
                    $html = $trimmed;
                }

                if (preg_match('/<br\b[^>]*\/?>$/i', $html, $match)) {
                    $html = substr($html, 0, -strlen($match[0]));
                    continue;
                }

                if (preg_match('/<\/(p|div|h[1-6])\s*>$/i', $html, $match, PREG_OFFSET_CAPTURE)) {
                    $tag = strtolower($match[1][0]);
                    $start = $this->findMatchingTagStart($html, $tag, $match[0][1]);
                    if ($start !== null && $this->isEmptyHtmlFragment(substr($html, $start))) {
                        $html = substr($html, 0, $start);
                        continue;
                    }
                }
            }

            break;
        }

        return $html;
    }

    /**
     * Regex capturing `<pagebreak />` tags (with optional attributes) as split delimiters.
     */
    protected const PAGEBREAK_SPLIT_PATTERN = '/(<pagebreak(?:\s[^>]*)?\s*\/?>)/i';

    /**
     * Determine whether a split fragment is a standalone `<pagebreak />` tag.
     */
    protected function isPageBreakTag(string $fragment): bool
    {
        return preg_match('/^<pagebreak(?:\s[^>]*)?\s*\/?>$/i', $fragment) === 1;
    }

    /**
     * Split HTML on `<pagebreak />` tags, keeping the tags as delimiters.
     *
     * @return array<int, string>|null
     */
    protected function splitOnPageBreaks(string $html): ?array
    {
        $parts = preg_split(self::PAGEBREAK_SPLIT_PATTERN, $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        return $parts !== false ? $parts : null;
    }

    /**
     * Split inline formatting wrappers around `<pagebreak />` so styled spans do not block unwrapping.
     */
    protected function unwrapInlinePageBreakWrappers(string $htmlContent): string
    {
        do {
            $previous = $htmlContent;
            $htmlContent = preg_replace_callback(
                '/<(span|strong|b|em|i|u|s|strike|font|small|sub|sup)\b([^>]*)>((?:(?!<\/?\1\b).)*?<pagebreak(?:\s[^>]*)?\s*\/?>(?:(?!<\/?\1\b).)*?)<\/\1>/is',
                function (array $matches): string {
                    $parts = $this->splitOnPageBreaks($matches[3]);
                    if ($parts === null) {
                        return $matches[0];
                    }

                    $output = '';
                    foreach ($parts as $part) {
                        if ($this->isPageBreakTag($part)) {
                            $output .= $part;
                        } elseif (! $this->isEmptyHtmlFragment($part)) {
                            $output .= '<'.$matches[1].$matches[2].'>'.$part.'</'.$matches[1].'>';
                        }
                    }

                    return $output;
                },
                $htmlContent
            ) ?? $htmlContent;
        } while ($htmlContent !== $previous);

        return $htmlContent;
    }

    /**
     * Unwrap `<p>` tags containing `<pagebreak />` and `<div>` wrappers containing only breaks and empty fragments.
     */
    protected function unwrapBlockPageBreakWrappers(string $htmlContent): string
    {
        $htmlContent = preg_replace_callback(
            '/<p\b([^>]*)>((?:(?!<\/p>).)*?<pagebreak(?:\s[^>]*)?\s*\/?>.*?)<\/p>/is',
            function (array $matches): string {
                $parts = $this->splitOnPageBreaks($matches[2]);
                if ($parts === null) {
                    return $matches[0];
                }

                $output = '';
                foreach ($parts as $part) {
                    if ($this->isPageBreakTag($part)) {
                        $output .= $part;
                        continue;
                    }

                    $trimmed = $this->trimBoundaryEmptyBlocks($this->trimBoundaryEmptyBlocks($part, true), false);
                    if (! $this->isEmptyHtmlFragment($trimmed)) {
                        $output .= '<p'.$matches[1].'>'.$trimmed.'</p>';
                    }
                }

                return $output;
            },
            $htmlContent
        ) ?? $htmlContent;

        do {
            $previous = $htmlContent;
            $htmlContent = preg_replace_callback(
                '/<div\b[^>]*>((?:(?!<\/?div\b).)*?<pagebreak(?:\s[^>]*)?\s*\/?>(?:(?!<\/?div\b).)*?)<\/div>/is',
                function (array $matches): string {
                    $parts = $this->splitOnPageBreaks($matches[1]);
                    if ($parts === null) {
                        return $matches[0];
                    }

                    $breaks = '';
                    foreach ($parts as $part) {
                        if ($this->isPageBreakTag($part)) {
                            $breaks .= $part;
                        } elseif (! $this->isEmptyHtmlFragment($part)) {
                            return $matches[0];
                        }
                    }

                    return $breaks;
                },
                $htmlContent
            ) ?? $htmlContent;
        } while ($htmlContent !== $previous);

        return $htmlContent;
    }

    /**
     * Trim empty blocks around every `<pagebreak />` (and at document end) and drop leading, consecutive, or trailing breaks.
     */
    protected function trimPageBreakBoundaries(string $htmlContent): string
    {
        $parts = $this->splitOnPageBreaks($htmlContent);
        if ($parts === null || count($parts) === 1) {
            return $this->trimBoundaryEmptyBlocks($htmlContent, false);
        }

        $segments = [];
        $breaks = [];
        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                $segments[] = $part;
            } else {
                $breaks[] = $part;
            }
        }

        $segmentCount = count($segments);
        for ($i = 0; $i < $segmentCount; $i++) {
            if ($i > 0) {
                $segments[$i] = $this->trimBoundaryEmptyBlocks($segments[$i], true);
            }
            $segments[$i] = $this->trimBoundaryEmptyBlocks($segments[$i], false);
        }

        $result = $segments[0];
        $hasContentBeforeBreak = ! $this->isEmptyHtmlFragment($segments[0]);

        foreach ($breaks as $index => $breakTag) {
            $nextSegment = $segments[$index + 1];
            $nextHasContent = ! $this->isEmptyHtmlFragment($nextSegment);

            if ($hasContentBeforeBreak && $nextHasContent) {
                $result .= $breakTag.$nextSegment;
            } elseif (! $hasContentBeforeBreak) {
                $result .= $nextSegment;
                $hasContentBeforeBreak = $nextHasContent;
            } else {
                // Keep unmatched structural closing tags (e.g. </div>) while dropping the redundant break
                $result .= $nextSegment;
            }
        }

        return $result;
    }

    /**
     * Convert TinyMCE pagebreak markup into `<pagebreak />`, unwrap enclosing blocks, and strip adjacent empty blocks.
     */
    protected function normalizePageBreaks(string $htmlContent): string
    {
        // Convert TinyMCE page breaks into mPDF's native <pagebreak /> tag.
        // TinyMCE stores them as HTML comments (<!-- pagebreak -->) by default, which mPDF ignores.
        // Also support placeholder images/HRs (e.g. content saved with the editor's default markup).
        $htmlContent = preg_replace('/<p\b[^>]*\bclass=([\'"])[^\'"]*\bmce-pagebreak\b[^\'"]*\1[^>]*>.*?<\/p>/is', '<pagebreak />', $htmlContent) ?? $htmlContent;
        $htmlContent = preg_replace('/<div\b[^>]*\bclass=([\'"])[^\'"]*\bmce-pagebreak\b[^\'"]*\1[^>]*>.*?<\/div>/is', '<pagebreak />', $htmlContent) ?? $htmlContent;
        $htmlContent = preg_replace('/<(?:img|hr)\b[^>]*\bclass=([\'"])[^\'"]*\bmce-pagebreak\b[^\'"]*\1[^>]*\/?>/is', '<pagebreak />', $htmlContent) ?? $htmlContent;
        $htmlContent = preg_replace('/<!--\s*pagebreak\s*-->/is', '<pagebreak />', $htmlContent) ?? $htmlContent;

        $htmlContent = $this->unwrapInlinePageBreakWrappers($htmlContent);
        $htmlContent = $this->unwrapBlockPageBreakWrappers($htmlContent);

        return $this->trimPageBreakBoundaries($htmlContent);
    }

    /**
     * Resolve a relative file path against the Laravel `public` storage disk if locally backed.
     */
    protected function resolvePublicDiskPath(string $relativePath): ?string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }

        if (config('filesystems.disks.public.driver') === 'local') {
            return Storage::disk('public')->path($relativePath);
        }

        $fallback = storage_path('app/public/'.$relativePath);

        return is_file($fallback) ? $fallback : null;
    }

    /**
     * Resolve an HTML `src` URL or path to a local filesystem path readable by mPDF, if applicable.
     */
    protected function resolveLocalAssetPath(string $src): ?string
    {
        $src = html_entity_decode(trim($src), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($src === '' || stripos($src, 'data:') === 0 || stripos($src, 'var:') === 0) {
            return null;
        }

        // Unwrap accidental duplicate /storage/ prefix from templates like src="/storage/{{ photo }}"
        $src = preg_replace('/^(?:\.\.\/|\.\/)*\/?storage\/+(?=(?:https?:)?\/\/|\/?storage\/)/i', '', $src) ?? $src;

        if (is_file($src)) {
            return $src;
        }

        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $src)) {
            return null;
        }

        $normalizedForParse = str_starts_with($src, '//') ? 'http:'.$src : $src;
        $parts = parse_url($normalizedForParse);
        if ($parts === false) {
            return null;
        }

        $host = $parts['host'] ?? null;
        $path = rawurldecode(str_replace('\\', '/', $parts['path'] ?? ''));
        if ($path === '') {
            return null;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $appHost = parse_url($appUrl, PHP_URL_HOST);
        $isLocalOrSameHost = $host === null || (is_string($appHost) && $appHost !== '' && strcasecmp($host, $appHost) === 0);

        $publicDiskUrl = rtrim((string) config('filesystems.disks.public.url', ''), '/');
        $publicDiskUrlPath = $publicDiskUrl !== '' ? trim((string) parse_url($publicDiskUrl, PHP_URL_PATH), '/') : 'storage';
        $storagePrefixes = array_values(array_unique(array_filter([$publicDiskUrlPath, 'storage'])));

        foreach ($storagePrefixes as $prefix) {
            if (preg_match('#(?:^|/)'.preg_quote($prefix, '#').'/(.+)$#i', $path, $matches)) {
                $relativeStoragePath = ltrim($matches[1], '/');
                if ($relativeStoragePath !== '' && ! str_contains($relativeStoragePath, '..')) {
                    $publicStoragePath = public_path('storage/'.$relativeStoragePath);
                    if (is_file($publicStoragePath)) {
                        return $publicStoragePath;
                    }

                    $diskPath = $this->resolvePublicDiskPath($relativeStoragePath);
                    if ($diskPath !== null && is_file($diskPath)) {
                        return $diskPath;
                    }

                    if ($isLocalOrSameHost) {
                        return $publicStoragePath;
                    }
                }
            }
        }

        $cleanPath = ltrim(preg_replace('#^(?:\.\./|\./)+#', '', $path) ?? $path, '/');
        $appBasePath = trim((string) parse_url($appUrl, PHP_URL_PATH), '/');
        if ($appBasePath !== '' && str_starts_with($cleanPath, $appBasePath.'/')) {
            $cleanPath = substr($cleanPath, strlen($appBasePath) + 1);
        }

        if ($cleanPath !== '' && ! str_contains($cleanPath, '..')) {
            $publicPath = public_path($cleanPath);
            if (is_file($publicPath)) {
                return $publicPath;
            }

            $diskPath = $this->resolvePublicDiskPath($cleanPath);
            if ($diskPath !== null && is_file($diskPath)) {
                return $diskPath;
            }

            $publicStoragePath = public_path('storage/'.$cleanPath);
            if (is_file($publicStoragePath)) {
                return $publicStoragePath;
            }
        }

        return null;
    }

    /**
     * Rewrite local, storage, and relative asset `src` URLs to absolute filesystem paths for mPDF.
     */
    protected function rewriteImageSources(string $htmlContent): string
    {
        return preg_replace_callback(
            '/(?<=\s)src\s*=\s*(["\'])(.*?)\1/is',
            function (array $matches): string {
                $quote = $matches[1];
                $resolved = $this->resolveLocalAssetPath($matches[2]);

                return $resolved !== null
                    ? 'src='.$quote.$resolved.$quote
                    : $matches[0];
            },
            $htmlContent
        ) ?? $htmlContent;
    }

    /**
     * Apply mPDF compatibility polyfills to HTML content.
     */
    protected function applyMpdfPolyfills(string $htmlContent): string
    {
        // Strip zero-width characters before pagebreak cleanup so ZWSP-only blocks count as empty
        $htmlContent = $this->stripInvisibleCharacters($htmlContent);
        $htmlContent = $this->normalizePageBreaks($htmlContent);

        // CSS flexbox polyfills for mPDF
        $htmlContent = preg_replace('/display:\s*inline-flex;?/', 'display: inline-block;', $htmlContent);
        $htmlContent = preg_replace('/align-items:\s*center;?/', 'vertical-align: middle;', $htmlContent);
        $htmlContent = preg_replace('/justify-content:\s*center;?/', 'text-align: center;', $htmlContent);

        // Add line-height to divs with explicit height for vertical centering
        $htmlContent = preg_replace_callback(
            '/<div[^>]*style="([^"]*height:\s*(\d+px)[^"]*)"[^>]*>/i',
            function ($matches) {
                $style = $matches[1];
                $height = $matches[2];
                if (strpos($style, 'line-height') === false) {
                    $newStyle = $style.' line-height: '.$height.';';

                    return str_replace($style, $newStyle, $matches[0]);
                }

                return $matches[0];
            },
            $htmlContent
        );

        // Strip remote Google Fonts @import rules to prevent massive mPDF network delays
        $htmlContent = preg_replace('/@import\s+url\([\'"]?https:\/\/fonts\.googleapis\.com.*?[\'"]?\);?/i', '', $htmlContent);

        // Rewrite storage, relative, and local public asset URLs to absolute filesystem paths for mPDF
        $htmlContent = $this->rewriteImageSources($htmlContent);

        return $htmlContent;
    }

    protected function generatePdfFromHtml(DocumentTemplate $template, string $htmlContent): mixed
    {
        /** @var view-string $viewName */
        $viewName = 'filament-document-builder::document';
        $settings = $template->getAttribute('page_settings') ?? [];

        $html = view($viewName, [
            'htmlContent' => $htmlContent,
            'settings' => $settings,
        ])->render();

        $format = strtoupper(data_get($settings, 'format', 'a4'));
        $orientation = data_get($settings, 'orientation', 'portrait');

        $pdfConfig = [
            'format' => $format,
            'orientation' => $orientation === 'landscape' ? 'L' : 'P',
            'defaultPagebreakType' => 'slice',
            'curlFollowLocation' => true,
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
            'default_font' => 'khmerbattambang',
            'custom_font_dir' => realpath(__DIR__.'/../../resources/fonts').'/',
            'custom_font_data' => [
                'khmerbattambang' => [
                    'R' => 'KhmerOSbattambang.ttf',
                    'useOTL' => 0xFF,
                ],
                'khmerosbattambang' => [
                    'R' => 'KhmerOSbattambang.ttf',
                    'useOTL' => 0xFF,
                ],
                'battambang' => [
                    'R' => 'KhmerOSbattambang.ttf',
                    'useOTL' => 0xFF,
                ],
                'khmermoullight' => [
                    'R' => 'KhmerOSmuollight.ttf',
                    'useOTL' => 0xFF,
                ],
                'khmerosmuollight' => [
                    'R' => 'KhmerOSmuollight.ttf',
                    'useOTL' => 0xFF,
                ],
                'moul' => [
                    'R' => 'KhmerOSmuollight.ttf',
                    'useOTL' => 0xFF,
                ],
                'khmersiemreap' => [
                    'R' => 'KhmerOSsiemreap.ttf',
                    'useOTL' => 0xFF,
                ],
                'khmerossiemreap' => [
                    'R' => 'KhmerOSsiemreap.ttf',
                    'useOTL' => 0xFF,
                ],
                'siemreap' => [
                    'R' => 'KhmerOSsiemreap.ttf',
                    'useOTL' => 0xFF,
                ],
                'calibri' => [
                    'R' => 'FreeSans.ttf',
                    'B' => 'FreeSansBold.ttf',
                    'I' => 'FreeSansOblique.ttf',
                    'BI' => 'FreeSansBoldOblique.ttf',
                ],
                'arial' => [
                    'R' => 'FreeSans.ttf',
                    'B' => 'FreeSansBold.ttf',
                    'I' => 'FreeSansOblique.ttf',
                    'BI' => 'FreeSansBoldOblique.ttf',
                ],
                'timesnewroman' => [
                    'R' => 'FreeSerif.ttf',
                    'B' => 'FreeSerifBold.ttf',
                    'I' => 'FreeSerifItalic.ttf',
                    'BI' => 'FreeSerifBoldItalic.ttf',
                ],
            ],
        ];

        if (is_array($settings)) {
            foreach ($settings as $key => $value) {
                if (! in_array($key, ['format', 'orientation']) && $value !== null && $value !== '') {
                    $pdfConfig[$key] = is_numeric($value) ? (float) $value : $value;
                }
            }
        }

        return Pdf::loadHTML($html, $pdfConfig);
    }

    public function render(DocumentTemplate $template, array|object $data = []): mixed
    {
        $htmlContent = $this->processHtmlContent($template, $data);

        return $this->generatePdfFromHtml($template, $htmlContent);
    }

    public function renderMultiple(DocumentTemplate $template, iterable $records): mixed
    {
        $htmlContent = $template->content ?? '';

        // Convert to Eloquent Collection to enable bulk relation loading
        $eloquentCollection = $records instanceof Collection ? $records : new Collection($records);

        // Bulk load relations for the entire collection to prevent N+1 queries
        $this->preloadRelations($htmlContent, $eloquentCollection);

        $htmlContents = [];
        foreach ($eloquentCollection as $record) {
            $htmlContents[] = $this->processHtmlContent($template, $record);
        }

        // Join multiple records with a pagebreak and normalize boundaries between records
        $combinedHtml = $this->normalizePageBreaks(implode('<pagebreak />', $htmlContents));

        return $this->generatePdfFromHtml($template, $combinedHtml);
    }
}
