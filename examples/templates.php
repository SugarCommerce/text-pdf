<?php
/**
 * Sample: the built-in Invoice template, driven entirely by data.
 */
declare( strict_types=1 );

require __DIR__ . '/../vendor/autoload.php';

use Sugar\TextPdf\Templates\{Invoice, Branding, Party, LineItem};

// A proper mark: an interlocking "S" built from Béziers, which also
// proves the curve conversion rather than just the line one.
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
	number:   'INV-2026-0042',
	from:     new Party( 'Sugarcart Ltd', [ '71-75 Shelton Street', 'London WC2H 9JQ' ], tax_id: 'GB 123 4567 89' ),
	to:       new Party(
		'Acme Recordings Ltd',
		[ 'Studio 4, 118 Brick Lane', 'London E1 6RL', 'United Kingdom' ],
		'accounts@acmerecordings.com',
		'GB 987 6543 21'
	),
	items: [
		new LineItem( 'Drum Kit Vol. 2', '£149.00', '1', '£149.00', '— Studio licence' ),
		new LineItem( 'Analogue Bass Collection', '£89.00', '1', '£89.00' ),
		new LineItem( 'Vintage Keys', '£498.00', '2', '£249.00', '— Agency licence' ),
		new LineItem( 'Extended distribution rights', '£120.00', '1', '£120.00' ),
		new LineItem( 'Sample clearance report', '£45.00', '1', '£45.00' ),
	],
	totals: [
		'Subtotal'            => '£901.00',
		'Discount (SPRING10)' => '-£90.10',
		'VAT at 20%'          => '£162.18',
	],
	total:   [ 'Total due' => '£973.08' ],
	details: [
		'Issued'    => '14 August 2026',
		'Due'       => '28 August 2026',
		'Terms'     => 'Net 14',
		'Reference' => 'ORD-04188',
	],
	notes:  "Bank transfer to Sugarcart Ltd, sort code 04-00-04, account 12345678.\nPlease quote INV-2026-0042 as the payment reference.",
	status: 'Unpaid',
);

$invoice->save( $argv[1] ?? 'invoice.pdf' );

echo "template invoice written\n";
