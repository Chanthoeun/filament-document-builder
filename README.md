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
                // Optional: Customize or remove the navigation group
                // ->navigationGroup('Custom Group') // Move to another group
                // ->navigationGroup(false) // Remove from groups entirely
                // ->navigationIcon('heroicon-o-document-text') // Change the navigation icon
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

#### Built-in Shapes and Forms Support
This plugin adds several pre-configured shapes to the TinyMCE editor to make designing forms significantly easier:
- Click the **Template** icon in the toolbar (or use the `Shapes` dropdown) to insert pre-made shapes like a **Circle Logo Placeholder**, a **4x6 Photo Box**, a **Rounded Rectangle**, or a **Signature Line**.
- **Keyboard Shortcuts (Text Patterns):** You can also quickly insert shapes by typing the following codes and pressing Space:
    - `#logo` -> Circular Logo Box
    - `#box` -> Square Box
    - `#photo` -> 4x6 Rectangle Photo Box
    - `#checkbox` -> Small Checkbox
    - `#rounded` -> Rounded Rectangle
    - `#oval` -> Oval Shape
    - `#sign` -> Signature Area with "ហត្ថលេខា / Signature"

#### Flexbox Support in mPDF
By default, mPDF does not support modern CSS Flexbox (`display: inline-flex`), which often breaks designs created in TinyMCE. This plugin **automatically polyfills** Flexbox behaviors (such as `inline-flex`, `align-items`, and `justify-content`) directly in PHP during the export process. Your TinyMCE layouts will render pixel-perfect in the final PDF without requiring complex CSS hacks!

### 2. Looping over Data (Table Repeaters)
If you need to iterate over an array or Eloquent relationship (like line items on an invoice), you can use the built-in `{{#foreach}}` syntax directly in your template.

Switch your TinyMCE editor to **Source Code (`<>`)** view and wrap your repeating elements like this:
```html
<tbody>
    {{#foreach items as item}}
    <tr>
        <td>{{ item.description }}</td>
        <td>{{ item.qty }}</td>
        <td>{{ item.price }}</td>
        <td>{{ item.total }}</td>
    </tr>
    {{/foreach}}
</tbody>
```
- The engine will automatically loop through the `items` array or relationship.
- You can access properties of each item using the prefix you define (e.g., `item.`).
- **Bonus:** Inside the loop, you still have full access to global variables from the parent record!

### 3. Additional Data Sources (Multi-Model Support)
If your document requires data from multiple independent models (e.g., a student application form that needs global `SchoolSettings` or a specific `Principal` user), you can map them entirely through the UI without touching any code!

1. In the **Template Details** tab, click **Add to additional data sources**.
2. Give it a Variable Name (e.g., `school`).
3. Select the Database Model (e.g., `App\Models\SchoolSetting`) and choose "First Record" or "Latest Record".
4. The TinyMCE editor will instantly update its **Insert Variable** dropdown to include all the fields from your new model (e.g., `school.name`, `school.address`).

The PDF Engine will automatically execute these queries at runtime and securely merge the data into your document!

### 4. Exporting PDFs from your Resources
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

