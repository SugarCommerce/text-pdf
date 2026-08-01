<?php
/**
 * Sample: a German-language invoice under the reverse charge.
 */
declare( strict_types=1 );

require __DIR__ . '/../vendor/autoload.php';

use SugarMill\TextPdf\Templates\{Invoice, Branding, Party, LineItem, VatTreatment, VatLine, Language};

$mark = 'M50,4 C26,4 8,18 8,38 C8,56 22,64 44,70 C60,74 66,78 66,86 '
      . 'C66,94 58,98 48,98 C34,98 22,92 12,82 L12,96 C24,104 38,108 50,108 '
      . 'C74,108 92,96 92,76 C92,58 78,50 56,44 C40,40 34,36 34,28 '
      . 'C34,20 42,14 52,14 C64,14 74,20 82,28 L82,14 C72,8 62,4 50,4 Z';

$invoice = new Invoice(
	branding: new Branding(
		name:      'Sugarcart',
		tagline:   'Digitale Produkte, richtig gemacht',
		logo_path: $mark,
		logo_box:  112,
		footnotes: [ 'Sugarcart Ltd, eingetragen in England und Wales, Handelsregisternummer 12345678.' ],
	),
	number: 'RE-2026-0119',
	from:   new Party( 'Sugarcart Ltd', [ '71-75 Shelton Street', 'London WC2H 9JQ', 'Vereinigtes Königreich' ], tax_id: 'GB 123 4567 89' ),
	to:     new Party( 'Klangwerk Studios GmbH', [ 'Oranienburger Straße 87', '10178 Berlin', 'Deutschland' ], 'rechnungen@klangwerk.de', 'DE 811 5678 90' ),
	items: [
		new LineItem( 'Vintage Keys Collection', '1.490,00 EUR', '2', '745,00 EUR', '— Agenturlizenz' ),
		new LineItem( 'Modular Textures Vol. 3', '380,00 EUR', '1', '380,00 EUR' ),
		new LineItem( 'Individuelles Sounddesign', '2.400,00 EUR', '16', '150,00 EUR', '— pro Stunde' ),
	],
	totals: [ 'Nettobetrag' => '4.270,00 EUR', 'Umsatzsteuer' => '0,00 EUR' ],
	total:  [ 'Gesamtbetrag' => '4.270,00 EUR' ],
	details: [
		'Rechnungsdatum' => '14. August 2026',
		'Fällig am'      => '13. September 2026',
		'Zahlungsziel'   => '30 Tage netto',
		'Ihre Referenz'  => 'PO-2026-4471',
	],
	notes: "Überweisung an Sugarcart Ltd, IBAN GB29 NWBK 6016 1331 9268 19, BIC NWBKGB2L.\nBitte geben Sie RE-2026-0119 als Verwendungszweck an.",
	vat:   VatTreatment::ReverseCharge,
	vat_lines: [ new VatLine( '0%', '4.270,00 EUR', '0,00 EUR', 'Reverse Charge' ) ],
	supply_date: '31. Juli 2026',
	translator:  Language::German,
);

foreach ( $invoice->compliance_warnings() as $warning ) {
	echo "  warning: $warning\n";
}

$invoice->save( $argv[1] ?? 'rechnung.pdf' );
echo "German invoice written\n";
