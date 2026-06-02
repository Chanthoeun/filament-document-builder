# Filament Document Builder

A powerful and simplified Filament PHP plugin to build dynamic document templates (like invoices, certificates, and registration forms) using a full-featured TinyMCE editor, and automatically export them to PDFs with native support for complex scripts like Khmer.

It is designed to easily integrate with standalone Filament panels or with other plugins like `chanthoeun/filament-custom-forms`.

## Features

- **Advanced HTML Designer:** Uses `amidesfahani/filament-tinyeditor` (TinyMCE) instead of the native Tiptap editor to provide robust support for complex HTML structures and native table editing.
- **Dynamic Variables:** Inject runtime database values directly into your text blocks (e.g., `{{ customer.name }}`). Fallbacks safely to a blank space if data is missing (perfect for printing blank forms).
- **Native PDF Engine:** Powered by `carlos-meneses/laravel-mpdf` (mPDF) for pure PHP generation.
- **Complex Text Shaping:** Pre-configured with `autoScriptToLang` and `autoLangToFont` to perfectly render complex alphabets like Khmer natively, without needing headless Chrome or Puppeteer.
- **Easy Integration:** Drop a simple `Action` onto any Filament table to export the current record as a PDF.
- **Support for Filament v4 & v5 (Laravel 10-13).**

---

## Installation

### 1. Requirements
- PHP 8.2+
- Filament v4.0 or v5.0
- TinyMCE (`amidesfahani/filament-tinyeditor`)
- mPDF (`carlos-meneses/laravel-mpdf`)

*(Note: Node.js, Puppeteer, and Chromium are **no longer required**!)*

### 2. Install via Composer
```bash
composer require chanthoeun/filament-document-builder
```

### 3. Run Migrations
You must run the migrations to create the `document_templates` table:
```bash
php artisan migrate
```

### 4. Register the Plugin
Add the plugin to your Filament Panel provider (`app/Providers/Filament/AdminPanelProvider.php`):

```php
use Chanthoeun\FilamentDocumentBuilder\DocumentBuilderPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(
            DocumentBuilderPlugin::make()
        );
}
```

---

## Usage

### 1. Creating a Template
1. Navigate to the **Document Builder** resource in your Filament sidebar.
2. Click **Create Document Template**.
3. Set the layout rules (A4, Portrait) in the Template Details tab.
4. Construct your document using the **Document Designer** tab. Because it uses TinyMCE, you can easily insert and modify highly complex HTML tables.
5. If you plan to inject dynamic variables, type them using standard brace syntax (e.g., `Hello {{ name }}!`).

### 2. Exporting PDFs from your Resources
To allow users to download a PDF of a specific record (like an Invoice or a Custom Form Entry), add the `GeneratePdfAction` to your resource's table actions.

```php
use Chanthoeun\FilamentDocumentBuilder\Actions\GeneratePdfAction;

public static function table(Table $table): Table
{
    return $table
        // ... columns ...
        ->actions([
            GeneratePdfAction::make('download_pdf')
                ->templateType('invoice') // The type string of the template you created
                ->data(fn ($record) => [
                    'name' => $record->customer_name,
                    'total' => $record->total_amount,
                    'items' => $record->line_items->toArray(),
                ])
        ]);
}
```

---

## License

The MIT License (MIT).

