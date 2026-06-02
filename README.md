# Filament Document Builder

A powerful and simplified Filament PHP plugin to build dynamic document templates (like invoices, certificates, and receipts) using a native drag-and-drop builder, and automatically export them to PDFs.

It is designed to easily integrate with standalone Filament panels or with other plugins like `chanthoeun/filament-custom-forms`.

## Features

- **Drag-and-Drop Designer:** Uses the native Filament `Builder` field to design pages with Headers, Text (Rich Editor), and dynamic Data Tables.
- **Dynamic Variables:** Inject runtime database values directly into your text blocks (e.g., `{{ customer.name }}`).
- **PDF Export Engine:** Powered by `spatie/laravel-pdf` for high-fidelity, Tailwind-supported PDF generation.
- **Easy Integration:** Drop a simple `Action` onto any Filament table to export the current record as a PDF.
- **Support for Filament v4 & v5 (Laravel 10-13).**

---

## Installation

### 1. Requirements
- PHP 8.2+
- Filament v4.0 or v5.0
- Node.js & Puppeteer (Required by `spatie/laravel-pdf`)

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
3. Set the layout rules (A4, Portrait) and construct your document using the **Document Designer** blocks.
4. If you plan to inject dynamic variables, type them using standard brace syntax in the Rich Editor blocks (e.g., `Hello {{ name }}!`).

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

### 3. Integration with `filament-custom-forms`
If you are using `chanthoeun/filament-custom-forms`, you can drop the action directly into the Custom Form Entry table to export form submissions!

```php
GeneratePdfAction::make('export_entry')
    ->templateType('certificate')
    ->data(fn ($record) => $record->data) // Passes the raw JSON submission
```

---

## License

The MIT License (MIT).
