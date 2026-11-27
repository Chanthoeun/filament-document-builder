<?php

namespace Chanthoeun\FilamentDocumentBuilder\Tests\Unit;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Chanthoeun\FilamentDocumentBuilder\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Mccarlosen\LaravelMpdf\LaravelMpdfServiceProvider;
use Mockery;
use SimpleSoftwareIO\QrCode\Generator;

class DocumentRendererTest extends TestCase
{
    protected DocumentRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new DocumentRenderer;
    }

    protected function mockQrCodeGenerator(string $fakeSvg = '<svg></svg>'): void
    {
        $mock = Mockery::mock(Generator::class);
        $mock->shouldReceive('format')->with('svg')->andReturnSelf();
        $mock->shouldReceive('size')->andReturnSelf();
        $mock->shouldReceive('generate')->andReturn($fakeSvg);

        $this->app->instance(Generator::class, $mock);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    protected function callProtected(string $method, array $args): mixed
    {
        $reflection = new \ReflectionMethod(DocumentRenderer::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->renderer, $args);
    }

    protected function makeTemplate(array $overrides = []): DocumentTemplate
    {
        $template = new DocumentTemplate;
        $template->setRawAttributes([
            'id' => 1,
            'name' => 'Test Template',
            'type' => 'test',
            'model_class' => null,
            'content' => isset($overrides['content']) ? json_encode($overrides['content']) : null,
            'page_settings' => json_encode([
                'format' => 'a4',
                'orientation' => 'portrait',
            ]),
            'extra_data_sources' => null,
        ]);

        return $template;
    }

    // ─── resolveWithParentScope ─────────────────────────────────────

    public function test_resolve_with_parent_scope_returns_value_when_found(): void
    {
        $data = ['name' => 'John'];
        $result = $this->callProtected('resolveWithParentScope', [data_get($data, 'name'), $data, 'name']);

        $this->assertEquals('John', $result);
    }

    public function test_resolve_with_parent_scope_falls_back_to_parent(): void
    {
        $data = [
            '_parent' => ['name' => 'Parent Value'],
        ];

        $result = $this->callProtected('resolveWithParentScope', [data_get($data, 'name'), $data, 'name']);

        $this->assertEquals('Parent Value', $result);
    }

    public function test_resolve_with_parent_scope_returns_null_when_not_found(): void
    {
        $data = ['other' => 'value'];

        $result = $this->callProtected('resolveWithParentScope', [data_get($data, 'missing'), $data, 'missing']);

        $this->assertNull($result);
    }

    // ─── replaceVariables ───────────────────────────────────────────

    public function test_replace_variables_replaces_simple_key(): void
    {
        $result = $this->callProtected('replaceVariables', [
            'Hello {{ name }}',
            ['name' => 'World'],
        ]);

        $this->assertEquals('Hello World', $result);
    }

    public function test_replace_variables_replaces_nested_key(): void
    {
        $result = $this->callProtected('replaceVariables', [
            '{{ customer.name }}',
            ['customer' => ['name' => 'Acme Corp']],
        ]);

        $this->assertEquals('Acme Corp', $result);
    }

    public function test_replace_variables_returns_empty_for_null(): void
    {
        $result = $this->callProtected('replaceVariables', [
            'Hello {{ name }}',
            ['name' => null],
        ]);

        $this->assertEquals('Hello ', $result);
    }

    public function test_replace_variables_returns_empty_for_array(): void
    {
        $result = $this->callProtected('replaceVariables', [
            '{{ items }}',
            ['items' => [1, 2, 3]],
        ]);

        $this->assertEquals('', $result);
    }

    public function test_replace_variables_preserves_qrcode_tag(): void
    {
        $result = $this->callProtected('replaceVariables', [
            '{{#qrcode code size=100}}',
            ['code' => 'test'],
        ]);

        $this->assertEquals('{{#qrcode code size=100}}', $result);
    }

    public function test_replace_variables_preserves_barcode_tag(): void
    {
        $result = $this->callProtected('replaceVariables', [
            '{{#barcode code width=2 height=30}}',
            ['code' => 'test'],
        ]);

        $this->assertEquals('{{#barcode code width=2 height=30}}', $result);
    }

    public function test_replace_variables_preserves_foreach_tags(): void
    {
        $result = $this->callProtected('replaceVariables', [
            '{{#foreach items as item}}{{ item.name }}{{/foreach}}',
            ['items' => [['name' => 'A']]],
        ]);

        // The foreach tags should be preserved, but inner variables replaced
        $this->assertStringContainsString('{{#foreach', $result);
        $this->assertStringContainsString('{{/foreach}}', $result);
    }

    public function test_replace_variables_handles_empty_content(): void
    {
        $result = $this->callProtected('replaceVariables', ['', ['name' => 'Test']]);

        $this->assertEquals('', $result);
    }

    public function test_replace_variables_resolves_through_parent_scope(): void
    {
        $data = [
            '_parent' => ['company' => 'My Company'],
        ];

        $result = $this->callProtected('replaceVariables', [
            '{{ company }}',
            $data,
        ]);

        $this->assertEquals('My Company', $result);
    }

    // ─── replaceLoops ───────────────────────────────────────────────

    public function test_replace_loops_basic_foreach(): void
    {
        $html = '{{#foreach items as item}}<p>{{ item.name }}</p>{{/foreach}}';
        $data = [
            'items' => [
                ['name' => 'Item 1'],
                ['name' => 'Item 2'],
            ],
        ];

        $result = $this->callProtected('replaceLoops', [$html, $data]);

        $this->assertStringContainsString('<p>Item 1</p>', $result);
        $this->assertStringContainsString('<p>Item 2</p>', $result);
    }

    public function test_replace_loops_empty_array_returns_empty(): void
    {
        $html = '{{#foreach items as item}}<p>{{ item.name }}</p>{{/foreach}}';
        $data = ['items' => []];

        $result = $this->callProtected('replaceLoops', [$html, $data]);

        $this->assertEquals('', $result);
    }

    public function test_replace_loops_non_iterable_returns_empty(): void
    {
        $html = '{{#foreach items as item}}<p>{{ item }}</p>{{/foreach}}';
        $data = ['items' => 'not an array'];

        $result = $this->callProtected('replaceLoops', [$html, $data]);

        $this->assertEquals('', $result);
    }

    public function test_replace_loops_falls_back_to_parent_scope(): void
    {
        $html = '{{#foreach items as item}}<p>{{ item }}</p>{{/foreach}}';
        $data = [
            '_parent' => [
                'items' => ['A', 'B'],
            ],
        ];

        $result = $this->callProtected('replaceLoops', [$html, $data]);

        $this->assertStringContainsString('<p>A</p>', $result);
        $this->assertStringContainsString('<p>B</p>', $result);
    }

    public function test_replace_loops_handles_empty_content(): void
    {
        $result = $this->callProtected('replaceLoops', ['', ['items' => []]]);

        $this->assertEquals('', $result);
    }

    // ─── parseRelationPaths ─────────────────────────────────────────

    public function test_parse_relation_paths_extracts_nested_relations(): void
    {
        $html = '{{ customer.name }} {{ customer.address.city }}';
        $result = $this->callProtected('parseRelationPaths', [$html]);

        $this->assertContains('customer', $result);
        $this->assertContains('customer.address', $result);
    }

    public function test_parse_relation_paths_ignores_simple_keys(): void
    {
        $html = '{{ name }} {{ created_at }}';
        $result = $this->callProtected('parseRelationPaths', [$html]);

        $this->assertEmpty($result);
    }

    public function test_parse_relation_paths_deduplicates(): void
    {
        $html = '{{ customer.name }} {{ customer.email }}';
        $result = $this->callProtected('parseRelationPaths', [$html]);

        $this->assertCount(1, $result);
        $this->assertContains('customer', $result);
    }

    // ─── clearCache ─────────────────────────────────────────────────

    public function test_clear_cache_resets_static_cache(): void
    {
        DocumentRenderer::clearCache();

        $reflection = new \ReflectionProperty(DocumentRenderer::class, 'extraDataCache');
        $reflection->setAccessible(true);

        $this->assertEmpty($reflection->getValue());
    }

    // ─── processHtmlContent mPDF polyfills ──────────────────────────

    public function test_process_html_converts_inline_flex(): void
    {
        $template = $this->makeTemplate([
            'content' => '<div style="display: inline-flex;">Test</div>',
        ]);

        $result = $this->callProtected('processHtmlContent', [$template, []]);

        $this->assertStringContainsString('display: inline-block;', $result);
        $this->assertStringNotContainsString('inline-flex', $result);
    }

    public function test_process_html_converts_align_items(): void
    {
        $template = $this->makeTemplate([
            'content' => '<div style="align-items: center;">Test</div>',
        ]);

        $result = $this->callProtected('processHtmlContent', [$template, []]);

        $this->assertStringContainsString('vertical-align: middle;', $result);
    }

    public function test_process_html_converts_justify_content(): void
    {
        $template = $this->makeTemplate([
            'content' => '<div style="justify-content: center;">Test</div>',
        ]);

        $result = $this->callProtected('processHtmlContent', [$template, []]);

        $this->assertStringContainsString('text-align: center;', $result);
    }

    public function test_process_html_strips_zwsp_characters(): void
    {
        $template = $this->makeTemplate([
            'content' => "Hello\xE2\x80\x8BWorld\xE2\x80\x8C!",
        ]);

        $result = $this->callProtected('processHtmlContent', [$template, []]);

        $this->assertEquals('HelloWorld!', $result);
    }

    public function test_process_html_strips_google_fonts_import(): void
    {
        $template = $this->makeTemplate([
            'content' => '<style>@import url("https://fonts.googleapis.com/css2?family=Roboto");</style><p>Test</p>',
        ]);

        $result = $this->callProtected('processHtmlContent', [$template, []]);

        $this->assertStringNotContainsString('googleapis.com', $result);
        $this->assertStringContainsString('<p>Test</p>', $result);
    }

    public function test_process_html_rewrites_storage_urls(): void
    {
        $appUrl = config('app.url');
        // Use a space after the URL to match what the regex expects
        $template = $this->makeTemplate([
            'content' => '<img src="'.$appUrl.'/storage/uploads/logo.png" width="100">',
        ]);

        $result = $this->callProtected('processHtmlContent', [$template, []]);

        // The storage URL should be rewritten to a local filesystem path
        $expectedPath = public_path('storage/uploads/logo.png');
        $this->assertStringContainsString($expectedPath, $result);
    }

    public function test_process_html_adds_line_height_to_divs_with_height(): void
    {
        $template = $this->makeTemplate([
            'content' => '<div style="height: 50px;">Test</div>',
        ]);

        $result = $this->callProtected('processHtmlContent', [$template, []]);

        $this->assertStringContainsString('line-height: 50px;', $result);
    }

    public function test_process_html_preserves_existing_line_height(): void
    {
        $template = $this->makeTemplate([
            'content' => '<div style="height: 50px; line-height: 60px;">Test</div>',
        ]);

        $result = $this->callProtected('processHtmlContent', [$template, []]);

        // Should not add a second line-height
        $this->assertStringContainsString('line-height: 60px;', $result);
        substr_count($result, 'line-height');
        $this->assertEquals(1, substr_count($result, 'line-height'));
    }

    // ─── replaceQrCodes ─────────────────────────────────────────────

    public function test_replace_qr_codes_generates_base64_img(): void
    {
        $this->mockQrCodeGenerator('fake-png-data');

        $html = '{{#qrcode code size=150}}';
        $data = ['code' => 'https://example.com'];

        $result = $this->callProtected('replaceQrCodes', [$html, $data]);

        $this->assertStringContainsString('<img src="data:image/svg+xml;base64,', $result);
        $this->assertStringContainsString('width="150"', $result);
        $this->assertStringContainsString('height="150"', $result);
    }

    public function test_replace_qr_codes_uses_default_size(): void
    {
        $this->mockQrCodeGenerator('data');

        $html = '{{#qrcode code}}';
        $data = ['code' => 'test'];

        $result = $this->callProtected('replaceQrCodes', [$html, $data]);

        $this->assertStringContainsString('width="100"', $result);
    }

    public function test_replace_qr_codes_returns_empty_for_null_value(): void
    {
        $html = '{{#qrcode code size=100}}';
        $data = ['code' => null];

        $result = $this->callProtected('replaceQrCodes', [$html, $data]);

        $this->assertEquals('', $result);
    }

    public function test_replace_qr_codes_resolves_through_parent(): void
    {
        $this->mockQrCodeGenerator('data');

        $html = '{{#qrcode code size=100}}';
        $data = [
            '_parent' => ['code' => 'parent-value'],
        ];

        $result = $this->callProtected('replaceQrCodes', [$html, $data]);

        $this->assertStringContainsString('<img src="data:image/svg+xml;base64,', $result);
    }

    // ─── replaceBarcodes ────────────────────────────────────────────

    public function test_replace_barcodes_generates_base64_img(): void
    {
        $html = '{{#barcode code width=2 height=30}}';
        $data = ['code' => 'ABC123'];

        $result = $this->callProtected('replaceBarcodes', [$html, $data]);

        $this->assertStringContainsString('<img src="data:image/png;base64,', $result);
        $this->assertStringContainsString('height:30px;', $result);
    }

    public function test_replace_barcodes_uses_defaults(): void
    {
        $html = '{{#barcode code}}';
        $data = ['code' => 'ABC123'];

        $result = $this->callProtected('replaceBarcodes', [$html, $data]);

        $this->assertStringContainsString('<img src="data:image/png;base64,', $result);
    }

    public function test_replace_barcodes_returns_empty_for_null(): void
    {
        $html = '{{#barcode code width=2 height=30}}';
        $data = ['code' => null];

        $result = $this->callProtected('replaceBarcodes', [$html, $data]);

        $this->assertEquals('', $result);
    }

    // ─── Integration: full processHtmlContent pipeline ──────────────

    public function test_full_pipeline_replaces_variables_and_preserves_qrcode(): void
    {
        $this->mockQrCodeGenerator('data');

        $template = $this->makeTemplate([
            'content' => '<p>{{ name }}</p>{{#qrcode code size=80}}',
        ]);

        $result = $this->callProtected('processHtmlContent', [
            $template,
            ['name' => 'Test User', 'code' => 'ABC123'],
        ]);

        $this->assertStringContainsString('<p>Test User</p>', $result);
        $this->assertStringContainsString('<img src="data:image/svg+xml;base64,', $result);
    }

    public function test_full_pipeline_replaces_foreach_and_variables(): void
    {
        $template = $this->makeTemplate([
            'content' => '{{#foreach items as item}}<tr><td>{{ item }}</td></tr>{{/foreach}}',
        ]);

        $result = $this->callProtected('processHtmlContent', [
            $template,
            ['items' => ['A', 'B', 'C']],
        ]);

        $this->assertStringContainsString('<tr><td>A</td></tr>', $result);
        $this->assertStringContainsString('<tr><td>B</td></tr>', $result);
        $this->assertStringContainsString('<tr><td>C</td></tr>', $result);
    }

    // ─── Barcode type support ─────────────────────────────────────

    public function test_replace_barcodes_with_type_parameter(): void
    {
        $html = '{{#barcode code type=EAN13 width=2 height=30}}';
        $data = ['code' => '123456789012'];

        $result = $this->callProtected('replaceBarcodes', [$html, $data]);

        $this->assertStringContainsString('<img src="data:image/png;base64,', $result);
        $this->assertStringContainsString('height:30px;', $result);
    }

    public function test_replace_barcodes_defaults_to_code128(): void
    {
        $html = '{{#barcode code}}';
        $data = ['code' => 'ABC123'];

        $result = $this->callProtected('replaceBarcodes', [$html, $data]);

        $this->assertStringContainsString('<img src="data:image/png;base64,', $result);
    }

    // ─── Regex safety ─────────────────────────────────────────────

    public function test_replace_variables_does_not_match_across_braces(): void
    {
        $result = $this->callProtected('replaceVariables', [
            '{{ name }} and {{ age }}',
            ['name' => 'John'],
        ]);

        $this->assertStringContainsString('John and ', $result);
    }

    public function test_replace_variables_with_html_entities(): void
    {
        $result = $this->callProtected('replaceVariables', [
            'Hello {{ name&nbsp;}}',
            ['name' => 'World'],
        ]);

        $this->assertStringContainsString('Hello World', $result);
    }

    // ─── applyMpdfPolyfills ───────────────────────────────────────

    public function test_apply_mpdf_polyfills_converts_inline_flex(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            '<div style="display: inline-flex;">Test</div>',
        ]);

        $this->assertStringContainsString('display: inline-block;', $result);
        $this->assertStringNotContainsString('inline-flex', $result);
    }

    public function test_apply_mpdf_polyfills_strips_zwsp(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            "Hello\xE2\x80\x8BWorld",
        ]);

        $this->assertEquals('HelloWorld', $result);
    }

    public function test_apply_mpdf_polyfills_strips_google_fonts(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            '<style>@import url("https://fonts.googleapis.com/css2?family=Roboto");</style>',
        ]);

        $this->assertStringNotContainsString('googleapis.com', $result);
    }

    public function test_apply_mpdf_polyfills_adds_line_height(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            '<div style="height: 50px;">Test</div>',
        ]);

        $this->assertStringContainsString('line-height: 50px;', $result);
    }

    public function test_apply_mpdf_polyfills_rewrites_storage_url(): void
    {
        $appUrl = config('app.url');
        $result = $this->callProtected('applyMpdfPolyfills', [
            '<img src="'.$appUrl.'/storage/uploads/logo.png" width="100">',
        ]);

        $this->assertStringContainsString(public_path('storage/uploads/logo.png'), $result);
    }

    public function test_apply_mpdf_polyfills_converts_tinymce_pagebreak_comment(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            '<p>Page 1</p><!-- pagebreak --><p>Page 2</p>',
        ]);

        $this->assertStringContainsString('<pagebreak />', $result);
        $this->assertStringNotContainsString('<!-- pagebreak -->', $result);
        $this->assertStringContainsString('<p>Page 1</p>', $result);
        $this->assertStringContainsString('<p>Page 2</p>', $result);
    }

    public function test_apply_mpdf_polyfills_converts_tinymce_pagebreak_comment_with_varying_whitespace(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            '<p>Part 1</p><!--pagebreak--><p>Part 2</p><!--  PAGEBREAK  --><p>Part 3</p>',
        ]);

        $this->assertStringContainsString('<pagebreak />', $result);
        $this->assertEquals(2, substr_count($result, '<pagebreak />'));
    }

    public function test_apply_mpdf_polyfills_unwraps_paragraph_and_div_pagebreaks(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            '<p>Page 1</p><p><!-- pagebreak --></p><p>Page 2</p><div class="mce-pagebreak"><!-- pagebreak --></div><p>Page 3</p>',
        ]);

        $this->assertStringNotContainsString('<p><pagebreak /></p>', $result);
        $this->assertStringNotContainsString('<p><!-- pagebreak --></p>', $result);
        $this->assertStringNotContainsString('mce-pagebreak', $result);
        $this->assertEquals('<p>Page 1</p><pagebreak /><p>Page 2</p><pagebreak /><p>Page 3</p>', $result);
    }

    public function test_apply_mpdf_polyfills_strips_empty_paragraphs_around_pagebreak(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            '<p>Page 1</p><p>&nbsp;</p><p><br></p><!-- pagebreak --><p>&nbsp;</p><p>Page 2</p>',
        ]);

        $this->assertEquals('<p>Page 1</p><pagebreak /><p>Page 2</p>', $result);
    }

    public function test_apply_mpdf_polyfills_deduplicates_consecutive_pagebreaks(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            '<p>Page 1</p><!-- pagebreak --><!-- pagebreak --><p>Page 2</p>',
        ]);

        $this->assertEquals('<p>Page 1</p><pagebreak /><p>Page 2</p>', $result);
    }

    public function test_apply_mpdf_polyfills_removes_trailing_pagebreak(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            '<p>Page 1</p><!-- pagebreak --><p>&nbsp;</p>',
        ]);

        $this->assertEquals('<p>Page 1</p>', $result);
    }

    public function test_apply_mpdf_polyfills_converts_tinymce_pagebreak_placeholder_image(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            '<p>Page 1</p><img src="data:image/gif;base64,R0lGODlh" class="mce-pagebreak" data-mce-resize="false" data-mce-placeholder><p>Page 2</p>',
        ]);

        $this->assertStringContainsString('<pagebreak />', $result);
        $this->assertStringNotContainsString('mce-pagebreak', $result);
    }

    public function test_apply_mpdf_polyfills_converts_tinymce_pagebreak_placeholder_hr(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            '<p>Page 1</p><hr class="mce-pagebreak"><p>Page 2</p>',
        ]);

        $this->assertStringContainsString('<pagebreak />', $result);
        $this->assertStringNotContainsString('mce-pagebreak', $result);
    }

    public function test_process_html_preserves_pagebreak(): void
    {
        $template = $this->makeTemplate([
            'content' => '<p>{{ title }}</p><!-- pagebreak --><p>{{ body }}</p>',
        ]);

        $result = $this->callProtected('processHtmlContent', [
            $template,
            ['title' => 'Title', 'body' => 'Body'],
        ]);

        $this->assertStringContainsString('<p>Title</p>', $result);
        $this->assertStringContainsString('<pagebreak />', $result);
        $this->assertStringContainsString('<p>Body</p>', $result);
    }

    public function test_apply_mpdf_polyfills_strips_styled_and_nested_empty_blocks_around_pagebreak(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            '<p>Page 1</p><p><span style="font-family: Battambang;">&nbsp;</span></p><p><strong><br data-mce-bogus="1" /></strong></p><div><div>&nbsp;</div></div><!-- pagebreak --><div><br /></div><p><span>&nbsp;</span></p><p>Page 2</p>',
        ]);

        $this->assertEquals('<p>Page 1</p><pagebreak /><p>Page 2</p>', $result);
    }

    public function test_apply_mpdf_polyfills_strips_zwsp_and_nbsp_only_blocks_around_pagebreak(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            "<p>Page 1</p><p>\xE2\x80\x8B</p><p>&#8203;</p><p><wbr /></p><!-- pagebreak --><p>\xC2\xA0</p><p>&#160;</p><p>Page 2</p>",
        ]);

        $this->assertEquals('<p>Page 1</p><pagebreak /><p>Page 2</p>', $result);
    }

    public function test_apply_mpdf_polyfills_unwraps_inline_and_text_containing_pagebreak_paragraphs(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            '<p style="text-align:center;">Page 1<br /><!-- pagebreak --></p><p>&nbsp;<!-- pagebreak --></p><p><span><!-- pagebreak --></span></p><p><!-- pagebreak --><br />Page 2</p>',
        ]);

        $this->assertEquals('<p style="text-align:center;">Page 1</p><pagebreak /><p>Page 2</p>', $result);
    }

    public function test_apply_mpdf_polyfills_removes_leading_and_empty_separated_duplicate_pagebreaks(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            '<!-- pagebreak --><p><span>&nbsp;</span></p><!-- pagebreak --><p>Page 1</p><!-- pagebreak --><p><span>&nbsp;</span></p><!-- pagebreak --><p>Page 2</p>',
        ]);

        $this->assertEquals('<p>Page 1</p><pagebreak /><p>Page 2</p>', $result);
    }

    public function test_apply_mpdf_polyfills_preserves_visual_boxes_and_closing_wrappers_around_pagebreak(): void
    {
        $checkbox = '<div style="display: inline-block; width: 16px; height: 16px; border: 1px solid #000;"></div>';
        $withCheckbox = $this->callProtected('applyMpdfPolyfills', [
            $checkbox.'<!-- pagebreak --><p>Page 2</p>',
        ]);

        $this->assertEquals(
            '<div style="display: inline-block; width: 16px; height: 16px; border: 1px solid #000; line-height: 16px;"></div><pagebreak /><p>Page 2</p>',
            $withCheckbox
        );

        $wrappedTrailing = $this->callProtected('applyMpdfPolyfills', [
            '<div><p>Page 1</p><!-- pagebreak --></div>',
        ]);

        $this->assertEquals('<div><p>Page 1</p></div>', $wrappedTrailing);
    }

    public function test_apply_mpdf_polyfills_strips_trailing_empty_blocks_without_pagebreak(): void
    {
        $result = $this->callProtected('applyMpdfPolyfills', [
            '<p>Page 1</p><p><span style="font-family: Battambang;">&nbsp;</span></p><p><br /></p>',
        ]);

        $this->assertEquals('<p>Page 1</p>', $result);
    }

    public function test_render_does_not_produce_blank_page_near_bottom_with_styled_empty_blocks_or_wrapper_padding(): void
    {
        $this->app->register(LaravelMpdfServiceProvider::class);

        $template = $this->makeTemplate([
            'content' => '<div style="padding-bottom: 20px;"><div style="height:262mm;">Page 1</div><p><span style="font-family: Battambang;">&nbsp;</span></p><!-- pagebreak --><p>Page 2</p></div>',
        ]);
        $template->setAttribute('page_settings', [
            'format' => 'a4',
            'orientation' => 'portrait',
            'margin_top' => 16,
            'margin_bottom' => 16,
            'margin_left' => 15,
            'margin_right' => 15,
            'temp_dir' => sys_get_temp_dir(),
        ]);

        $pdf = $this->renderer->render($template, []);

        $this->assertCount(2, $pdf->getMpdf()->pages);
    }

    public function test_render_multiple_strips_trailing_empty_blocks_between_records(): void
    {
        $this->app->register(LaravelMpdfServiceProvider::class);

        $template = $this->makeTemplate([
            'content' => '<div style="height:262mm;">{{ name }}</div><p><span style="font-family: Battambang;">&nbsp;</span></p>',
        ]);
        $template->setAttribute('page_settings', [
            'format' => 'a4',
            'orientation' => 'portrait',
            'margin_top' => 16,
            'margin_bottom' => 16,
            'margin_left' => 15,
            'margin_right' => 15,
            'temp_dir' => sys_get_temp_dir(),
        ]);

        $pdf = $this->renderer->renderMultiple($template, [
            ['name' => 'Record 1'],
            ['name' => 'Record 2'],
        ]);

        $this->assertCount(2, $pdf->getMpdf()->pages);
    }

    public function test_apply_mpdf_polyfills_rewrites_relative_encoded_and_scheme_mismatched_storage_urls(): void
    {
        config(['app.url' => 'http://ams.datacolabx.com']);

        $relative = $this->callProtected('applyMpdfPolyfills', [
            '<img src="../../storage/document-templates/my%20logo.png?v=2" data-mce-src="/storage/ignored.png" width="80">',
        ]);

        $this->assertStringContainsString('src="'.public_path('storage/document-templates/my logo.png').'"', $relative);
        $this->assertStringContainsString('data-mce-src="/storage/ignored.png"', $relative);

        $schemeMismatch = $this->callProtected('applyMpdfPolyfills', [
            '<img src="https://ams.datacolabx.com/storage/uploads/logo.png">',
        ]);

        $this->assertStringContainsString('src="'.public_path('storage/uploads/logo.png').'"', $schemeMismatch);

        $external = $this->callProtected('applyMpdfPolyfills', [
            '<img src="https://cdn.example.com/storage/remote-logo.png">',
        ]);

        $this->assertEquals('<img src="https://cdn.example.com/storage/remote-logo.png">', $external);
    }

    public function test_render_embeds_logo_on_first_page_and_image_on_second_page(): void
    {
        $this->app->register(LaravelMpdfServiceProvider::class);
        Storage::fake('public');
        config(['app.url' => 'http://localhost']);

        $image = imagecreatetruecolor(40, 40);
        $this->assertNotFalse($image);
        ob_start();
        imagepng($image);
        $pngBytes = (string) ob_get_clean();

        Storage::disk('public')->put('document-templates/school logo.png', $pngBytes);
        Storage::disk('public')->put('students/photo-591.png', $pngBytes);

        $template = $this->makeTemplate([
            'content' => '<table><tr><td><img src="https://ams.datacolabx.com/storage/document-templates/school%20logo.png?v=1" width="40" height="40"></td><td>Header</td></tr></table><!-- pagebreak --><p><img src="{{ data.photo }}" width="40" height="40"></p><p><img src="/storage/{{ data.existing_url }}" width="40" height="40"></p>',
        ]);
        $template->setAttribute('page_settings', [
            'format' => 'a4',
            'orientation' => 'portrait',
            'temp_dir' => sys_get_temp_dir(),
        ]);

        $pdf = $this->renderer->render($template, [
            'data' => [
                'photo' => 'students/photo-591.png',
                'existing_url' => '/storage/students/photo-591.png',
            ],
        ]);

        $mpdf = $pdf->getMpdf();
        $this->assertCount(2, $mpdf->pages);
        $this->assertCount(2, $mpdf->images);
        $this->assertMatchesRegularExpression('/\/I\d+\s+Do/', $mpdf->pages[1]);
        $this->assertMatchesRegularExpression('/\/I\d+\s+Do/', $mpdf->pages[2]);
    }
}
