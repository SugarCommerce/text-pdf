<?php
/**
 * Sample: the Statement and Receipt templates.
 */
declare( strict_types=1 );

require __DIR__ . '/../vendor/autoload.php';

use SugarCommerce\TextPdf\Templates\{Statement, Receipt, Branding, Party, LineItem};
use SugarCommerce\TextPdf\Align;

$mark = 'M50,4 C26,4 8,18 8,38 C8,56 22,64 44,70 C60,74 66,78 66,86 '
      . 'C66,94 58,98 48,98 C34,98 22,92 12,82 L12,96 C24,104 38,108 50,108 '
      . 'C74,108 92,96 92,76 C92,58 78,50 56,44 C40,40 34,36 34,28 '
      . 'C34,20 42,14 52,14 C64,14 74,20 82,28 L82,14 C72,8 62,4 50,4 Z';

$branding = new Branding(
	name:      'Sugarcart',
	tagline:   'Digital goods, done properly',
	logo_path: $mark,
	logo_box:  112,
	footnotes: [ 'Sugarcart Ltd, registered in England and Wales, company number 12345678.' ],
);

$customer = new Party(
	'Acme Recordings Ltd',
	[ 'Studio 4, 118 Brick Lane', 'London E1 6RL' ],
	'accounts@acmerecordings.com'
);

// ── Statement, long enough to paginate ───────────────────────────
$rows    = [];
$balance = 0;
$items   = [ 'Drum Kit Vol. 2', 'Analogue Bass', 'Vintage Keys', 'Tape Saturation', 'Field Recordings', 'Modular Textures' ];

for ( $i = 1; $i <= 58; $i++ ) {
	$amount   = 4900 + ( $i * 337 ) % 21000;
	$refund   = 0 === $i % 11;
	$amount   = $refund ? -$amount : $amount;
	$balance += $amount;

	$rows[] = [
		sprintf( '%02d Jul 2026', ( $i % 28 ) + 1 ),
		( $refund ? 'Refund — ' : '' ) . $items[ $i % count( $items ) ],
		'ORD-' . str_pad( (string) ( 4000 + $i ), 5, '0', STR_PAD_LEFT ),
		( $refund ? '-' : '' ) . '£' . number_format( abs( $amount ) / 100, 2 ),
		'£' . number_format( $balance / 100, 2 ),
	];
}

$statement = new Statement(
	branding:  $branding,
	account:   $customer,
	period:    '1 – 31 July 2026',
	rows:      $rows,
	closing:   [ 'Closing balance' => '£' . number_format( $balance / 100, 2 ) ],
	summary:   [ 'Opening balance' => '£0.00', 'Transactions' => (string) count( $rows ), 'Currency' => 'GBP' ],
	reference: 'Account 4471-2290',
);

$statement->save( $argv[1] ?? 'statement.pdf' );
printf( "statement written: %d pages\n", $statement->render()->page_count() );

// ── Receipt ──────────────────────────────────────────────────────
$receipt = new Receipt(
	branding: $branding,
	number:   'RCT-2026-8814',
	to:       $customer,
	items:    [
		new LineItem( 'Drum Kit Vol. 2', '£149.00', '1', note: '— Studio licence' ),
		new LineItem( 'Analogue Bass Collection', '£89.00', '1' ),
	],
	total:     '£285.60',
	details:   [ 'Paid on' => '14 August 2026', 'Method' => 'Visa ending 4242', 'Order' => 'ORD-04188' ],
	breakdown: [ 'Subtotal' => '£238.00', 'VAT at 20%' => '£47.60' ],
	notes:     'Downloads remain available in your account for 12 months. No action is required — this receipt is for your records.',
);

$receipt->save( $argv[2] ?? 'receipt.pdf' );
echo "receipt written\n";
