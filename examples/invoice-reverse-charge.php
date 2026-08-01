<?php
/**
 * Sample: a cross-border B2B invoice under the reverse charge, with the
 * German wording and a per-rate VAT breakdown.
 */
declare( strict_types=1 );

require __DIR__ . '/../vendor/autoload.php';

use SugarMill\TextPdf\Templates\{Invoice, Branding, Party, LineItem, VatTreatment, VatLine};

$mark = 'M50,4 C26,4 8,18 8,38 C8,56 22,64 44,70 C60,74 66,78 66,86 '
      . 'C66,94 58,98 48,98 C34,98 22,92 12,82 L12,96 C24,104 38,108 50,108 '
      . 'C74,108 92,96 92,76 C92,58 78,50 56,44 C40,40 34,36 34,28 '
      . 'C34,20 42,14 52,14 C64,14 74,20 82,28 L82,14 C72,8 62,4 50,4 Z';

$branding = new Branding(
	name:      'Sugarcart',
	tagline:   'Digital goods, done properly',
	accent:    '#111111',
	logo_path: $mark,
	logo_box:  112,
	footnotes: [
		'Sugarcart Ltd is registered in England and Wales, company number 12345678.',
		'Registered office: 71-75 Shelton Street, London WC2H 9JQ.',
	],
);

$invoice = new Invoice(
	branding: $branding,
	number:   'INV-2026-0119',
	from:     new Party(
		'Sugarcart Ltd',
		[ '71-75 Shelton Street', 'London WC2H 9JQ', 'United Kingdom' ],
		'billing@sugarcart.com',
		'GB 123 4567 89'
	),
	to: new Party(
		'Klangwerk Studios GmbH',
		[ 'Oranienburger Strasse 87', '10178 Berlin', 'Deutschland' ],
		'rechnungen@klangwerk.de',
		'DE 811 5678 90'
	),
	items: [
		new LineItem( 'Vintage Keys Collection', 'EUR 1,490.00', '2', 'EUR 745.00', '— Agency licence' ),
		new LineItem( 'Modular Textures Vol. 3', 'EUR 380.00', '1', 'EUR 380.00' ),
		new LineItem( 'Custom sound design', 'EUR 2,400.00', '16', 'EUR 150.00', '— per hour' ),
	],
	totals: [
		'Net total'            => 'EUR 4,270.00',
		'VAT (reverse charge)' => 'EUR 0.00',
	],
	total:   [ 'Total due' => 'EUR 4,270.00' ],
	details: [
		'Invoice date' => '14 August 2026',
		'Due'          => '13 September 2026',
		'Terms'        => 'Net 30',
		'Your ref.'    => 'PO-2026-4471',
	],
	notes:  "Bank transfer to Sugarcart Ltd, IBAN GB29 NWBK 6016 1331 9268 19, BIC NWBKGB2L.\nPlease quote INV-2026-0119 as the payment reference.",
	vat:    VatTreatment::ReverseCharge,
	vat_lines: [
		new VatLine( '0%', 'EUR 4,270.00', 'EUR 0.00', 'Reverse charge' ),
	],
	supply_date:  '31 July 2026',
	german_notes: true,
);

$warnings = $invoice->compliance_warnings();

if ( [] !== $warnings ) {
	echo "compliance warnings:\n";
	foreach ( $warnings as $w ) {
		echo "  - $w\n";
	}
} else {
	echo "compliance: no missing particulars\n";
}

$invoice->save( $argv[1] ?? 'invoice-rc.pdf' );
echo "reverse-charge invoice written\n";
