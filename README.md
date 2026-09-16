# Text PDF

A PDF writer with no dependencies. Invoices, statements, receipts and reports — tables, vector logos, multi-page flow, and VAT-compliant templates — in about 2,000 lines of PHP.

## Why

Generating an invoice usually means one of three things: shelling out to `wkhtmltopdf` (a binary you now have to install, sandbox and keep patched), pulling in Dompdf and its dependency tree, or paying for an API to render a document that never leaves your own server.

This writes the PDF directly. It covers what a business document actually needs — text flow, tables, rules, a vector logo, page breaks — and nothing else. No HTML engine, no font embedding, no image codecs.

## Features

- 📄 **Real multi-page flow** — content breaks across pages, with repeated headers and footers.
- 📊 **Tables** — proportional columns, alignment, striping, and headers repeated after a break.
- ✒️ **Vector logos** — an SVG `path` becomes PDF path operators, sharp at any zoom, a few hundred bytes.
- 🧾 **Templates** — `Invoice`, `Statement` and `Receipt`, ready to fill in.
- 🇪🇺 **VAT-compliant** — reverse charge, intra-community supply, export and small-business treatments with the required wording.
- 🌍 **11 languages** — or wire it to your framework's translator.
- 🔒 **Injection-safe** — customer names and product titles cannot escape the content stream.
- 🪶 **Zero dependencies** — no Composer packages, no binaries, no `ext-gd`.

## Requirements

PHP 8.5+

## Installation

```bash
composer require sugarcommerce/text-pdf
```

## A document from scratch

```php
use SugarCommerce\TextPdf\{Document, Font, Color, PageSize};

$pdf = new Document( size: PageSize::A4, margin: 56.0 );

$pdf->heading( 'Monthly Report' )
    ->gap( 8 )
    ->text( 'Generated ' . date( 'j F Y' ), color: Color::grey( 120 ) )
    ->rule()
    ->text( $long_paragraph );        // wraps and breaks across pages

$pdf->save( '/tmp/report.pdf', [ 'Title' => 'Monthly Report' ] );
```

`render()` returns the bytes instead, for streaming to a browser or handing to a mailer.

### Layout

```php
$pdf->text( $text, size: 10.0, font: Font::HelveticaBold, align: Align::Right );
$pdf->columns( [ 'Left', 'Middle', 'Right' ], [ 0.5, 0.3, 0.2 ] );
$pdf->rule();
$pdf->gap( 12 );
$pdf->page_break();
$pdf->break_if_needed( 80 );   // break only if 80pt won't fit
```

Two positioned primitives, for the places a flowing paragraph won't do:

```php
// Aligned inside a box, advancing the cursor.
$pdf->cell( 'Total due', $pdf->left(), $pdf->content_width(), align: Align::Right );

// The same, vertically centred inside a band of a given height —
// which is how a totals row lines up with its background.
$pdf->banded( '€1,240.00', $pdf->left(), $pdf->content_width(), band_height: 26.0 );

// Absolute placement, no cursor movement — for a footer.
$pdf->text_at( 'Page 1', $pdf->right() - 40, 30, 8.0 );
```

### Repeating chrome

```php
$pdf->on_each_page( function ( Document $pdf, int $page ): void {
    $pdf->text_at( 'Page ' . $page, $pdf->right() - 40, 30, 8.0 );
} );
```

### Tables

```php
use SugarCommerce\TextPdf\{Table, Align};

( new Table( [ 'Date', 'Description', 'Amount' ] ) )
    ->widths( [ 0.2, 0.6, 0.2 ] )
    ->align( [ 2 => Align::Right ] )
    ->striped()
    ->rows( $rows )
    ->draw( $pdf );
```

Headers repeat on every page the table spills onto — which is the part that is tedious to get right by hand.

### Logos

```php
use SugarCommerce\TextPdf\Svg;

$path = Svg::to_pdf_path( $svg_d_attribute, scale: 0.4, height: 100.0 );
$pdf->svg_path( $path, $pdf->left(), $pdf->cursor() - 40 );
```

Takes the `d` attribute of an SVG `<path>`. Supports `M L H V C S Q T Z`, absolute and relative; arcs (`A`) are approximated. A value that is not a path — prose, or a hostile string — produces nothing rather than random vectors.

## Templates

The reason this package exists. Fill in a struct, get a document.

```php
use SugarCommerce\TextPdf\Templates\{Invoice, Branding, Party, LineItem};

$invoice = new Invoice(
    branding: new Branding(
        name: 'Acme Ltd',
        tagline: 'Digital goods',
        logo_path: $svg_path_data,
        address: [ '12 Example Street', 'Dublin 2', 'Ireland' ],
    ),
    number: 'INV-2026-0042',
    from: new Party( 'Acme Ltd', [ '12 Example Street', 'Dublin 2' ], tax_id: 'IE1234567X' ),
    to:   new Party( 'Kunde GmbH', [ 'Hauptstraße 1', '10115 Berlin' ], tax_id: 'DE123456789' ),
    items: [
        new LineItem( 'Annual licence', '€1,000.00', quantity: '1' ),
        new LineItem( 'Priority support', '€240.00' ),
    ],
    totals: [ 'Subtotal' => '€1,240.00', 'VAT' => '€0.00' ],
    total:  [ 'Total due' => '€1,240.00' ],
    details: [ 'Issued' => '1 August 2026', 'Due' => '31 August 2026' ],
);

$invoice->save( '/tmp/invoice.pdf' );
```

`Statement` (multi-page ledger with a running balance) and `Receipt` (A5, for a completed sale) follow the same shape.

Every label/value block also accepts a list of pairs — `[ [ 'Discount', '-1.00' ], [ 'Discount', '-2.00' ] ]` — which is what a loop tends to build, and the only way to write the same label twice.

Documents are monochrome by default. It is the look that ages best, it photocopies and faxes without loss, and it never clashes with a customer's own branding — set an `accent` on `Branding` if you want colour.

## VAT

```php
use SugarCommerce\TextPdf\Templates\{VatTreatment, VatLine};

new Invoice(
    // …
    vat: VatTreatment::ReverseCharge,
    vat_lines: [ new VatLine( rate: '0%', net: '€1,240.00', vat: '€0.00' ) ],
    supply_date: '1 August 2026',
    german_notes: true,
);
```

| Treatment | Adds |
|---|---|
| `Standard` | Rate table |
| `ReverseCharge` | Article 196 wording, both VAT numbers required |
| `IntraCommunitySupply` | Article 138 exemption wording |
| `Export` | Third-country exemption |
| `SmallBusiness` | §19 UStG small-business notice |
| `Exempt` | General exemption wording |

```php
$invoice->compliance_warnings();   // what's missing before this is issuable
```

That last one is worth calling: it checks the things §14 UStG requires — both VAT numbers on a reverse-charge invoice, a supply date, a sequential number — and tells you which are absent, rather than producing a document that looks fine and isn't valid.

## Languages

```php
use SugarCommerce\TextPdf\Templates\Language;

new Invoice( /* … */, translator: Language::German );
Language::from_locale( 'de_DE' );
```

English, German, French, Spanish, Italian, Dutch, Portuguese, Swedish, Danish, Norwegian, Finnish.

The list stops there for a concrete reason: the base-14 PDF fonts are WinAnsi-encoded, so a language needing characters outside Latin-1 — Polish, Czech, Greek, Russian — cannot be rendered without embedding a font, and a shipped translation that prints as question marks is worse than no translation. A test asserts every shipped phrase survives the transcode byte for byte.

For any other language, or to use your framework's translations:

```php
use SugarCommerce\TextPdf\Templates\Translations;

new Invoice( /* … */, translator: new Translations(
    fn( string $key, string $default ): string => __( $default, 'my-plugin' ),
    fallback: Language::English,
) );
```

Works with WordPress `__()`, Laravel `trans()`, Symfony's translator, or plain gettext.

## Fonts

Helvetica, Helvetica-Bold and Courier — three of the base-14 fonts every reader is required to have, so nothing is embedded and a document is a few kilobytes. WinAnsi (Latin-1) coverage; characters outside it degrade to `?`.

If you need CJK, Cyrillic, Greek or Arabic, you need font embedding, and you need a different library.

## Security

An invoice is assembled from customer-supplied names, addresses and product titles. A `)` in any of them would close the string literal and turn the rest into content-stream operators — so every value is escaped, control characters are stripped, and non-finite dimensions are clamped before they can write `INF` into a stream. See [SECURITY.md](SECURITY.md).

## What it does not do

- No raster images (no JPEG or PNG embedding)
- No font embedding, so no non-Latin scripts
- No HTML or CSS
- No forms, annotations, encryption or digital signatures
- No reading or editing of existing PDFs

For anything on that list, use a full library. This one is for documents you generate.

## Testing

```bash
composer install
composer test
```

136 tests, 1,309 assertions — the cross-reference table byte-offsets, page-break arithmetic, table pagination, SVG conversion, every VAT treatment's required wording, the Latin-1 boundary for all 11 languages, and the injection cases.

## License

GPL-2.0-or-later
