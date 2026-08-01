<?php
/**
 * Sample: a one-page A4 invoice with a vector logo.
 */
declare( strict_types=1 );

require __DIR__ . '/../vendor/autoload.php';

use SugarMill\TextPdf\{Document, Table, Font, Color, Align, PageSize};

$brand = Color::hex( '#0f766e' );
$muted = Color::grey( 110 );

// A simple mark: rounded square with a cut corner. Real logos export
// from any vector editor as a path exactly like this.
$logo = 'M10,0 L70,0 Q80,0 80,10 L80,60 Q80,70 70,70 L10,70 Q0,70 0,60 L0,10 Q0,0 10,0 Z';

$pdf = new Document( PageSize::A4, margin: 48 );

// ── Header band ──────────────────────────────────────────────────
$pdf->rect( 0, $pdf->height() - 130, 595.28, 130, Color::hex( '#f8fafc' ) );
$pdf->svg_path( $logo, 48, $pdf->height() - 96, 0.55, $brand, 70 );

$pdf->text_at( 'SUGARCART', 100, $pdf->height() - 64, 16, Font::HelveticaBold, $brand );
$pdf->text_at( 'Digital goods, done properly', 100, $pdf->height() - 78, 8.5, Font::Helvetica, $muted );

$pdf->text_at( 'INVOICE', $pdf->left(), $pdf->height() - 64, 24, Font::HelveticaBold, Color::grey( 25 ), Align::Right, $pdf->content_width() );
$pdf->text_at( 'INV-2026-0042', $pdf->left(), $pdf->height() - 80, 10, Font::Helvetica, $muted, Align::Right, $pdf->content_width() );

$pdf->move_to( $pdf->height() - 165 );

// ── Parties ──────────────────────────────────────────────────────
$half = $pdf->content_width() / 2;
$top  = $pdf->cursor();

$pdf->text_at( 'BILLED TO', $pdf->left(), $top, 7.5, Font::HelveticaBold, $muted );
$pdf->text_at( 'Acme Recordings Ltd', $pdf->left(), $top - 15, 11, Font::HelveticaBold );
$pdf->text_at( 'Studio 4, 118 Brick Lane', $pdf->left(), $top - 29, 9.5, Font::Helvetica, $muted );
$pdf->text_at( 'London E1 6RL', $pdf->left(), $top - 41, 9.5, Font::Helvetica, $muted );
$pdf->text_at( 'United Kingdom', $pdf->left(), $top - 53, 9.5, Font::Helvetica, $muted );

$pdf->text_at( 'DETAILS', $pdf->left() + $half, $top, 7.5, Font::HelveticaBold, $muted );
foreach ( [
	[ 'Issued', '14 August 2026' ],
	[ 'Due', '28 August 2026' ],
	[ 'Terms', 'Net 14' ],
	[ 'VAT no.', 'GB 123 4567 89' ],
] as $i => [ $label, $value ] ) {
	$y = $top - 15 - ( $i * 13 );
	$pdf->text_at( $label, $pdf->left() + $half, $y, 9.5, Font::Helvetica, $muted );
	$pdf->text_at( $value, $pdf->left() + $half, $y, 9.5, Font::HelveticaBold, null, Align::Right, $half );
}

$pdf->move_to( $top - 85 );

// ── Line items ───────────────────────────────────────────────────
( new Table( [ 'Description', 'Qty', 'Unit', 'Amount' ] ) )
	->widths( [ 0.52, 0.10, 0.19, 0.19 ] )
	->align( [ 1 => Align::Center, 2 => Align::Right, 3 => Align::Right ] )
	->striped()
	->rows( [
		[ 'Drum Kit Vol. 2 — Studio licence', '1', '£149.00', '£149.00' ],
		[ 'Analogue Bass Collection', '1', '£89.00', '£89.00' ],
		[ 'Vintage Keys — Agency licence', '2', '£249.00', '£498.00' ],
		[ 'Extended distribution rights', '1', '£120.00', '£120.00' ],
		[ 'Sample clearance report', '1', '£45.00', '£45.00' ],
	] )
	->draw( $pdf );

// ── Totals ───────────────────────────────────────────────────────
$pdf->gap( 8 );
$label_x = $pdf->left() + ( $pdf->content_width() * 0.55 );
$box     = $pdf->content_width() * 0.45;

foreach ( [
	[ 'Subtotal', '£901.00', false ],
	[ 'Discount (SPRING10)', '-£90.10', false ],
	[ 'VAT at 20%', '£162.18', false ],
] as [ $label, $value, $bold ] ) {
	$y = $pdf->cursor();
	$pdf->text_at( $label, $label_x, $y, 9.5, Font::Helvetica, $muted );
	$pdf->text_at( $value, $label_x, $y, 9.5, Font::Helvetica, null, Align::Right, $box );
	$pdf->gap( 15 );
}

$pdf->line( $label_x, $pdf->cursor() + 6, $pdf->right(), $pdf->cursor() + 6, Color::grey( 200 ) );
$pdf->gap( 6 );

$pdf->rect( $label_x, $pdf->cursor() - 6, $box, 24, Color::hex( '#f0fdfa' ) );
$y = $pdf->cursor();
$pdf->text_at( 'Total due', $label_x + 8, $y, 11.5, Font::HelveticaBold );
$pdf->text_at( '£973.08', $label_x, $y, 13, Font::HelveticaBold, $brand, Align::Right, $box - 8 );

// ── Footer ───────────────────────────────────────────────────────
$pdf->move_to( 108 );
$pdf->rule( Color::grey( 220 ) );
$pdf->text( 'Payment by bank transfer to Sugarcart Ltd, sort code 04-00-04, account 12345678. Please quote INV-2026-0042 as the reference.', 8.5, Font::Helvetica, $muted );
$pdf->gap( 4 );
$pdf->text( 'Sugarcart Ltd is registered in England and Wales, company number 12345678. Registered office: 71-75 Shelton Street, London WC2H 9JQ.', 8, Font::Helvetica, Color::grey( 150 ) );

$pdf->save( $argv[1] ?? 'invoice.pdf', [
	'Title'   => 'Invoice INV-2026-0042',
	'Author'  => 'Sugarcart Ltd',
	'Subject' => 'Invoice for Acme Recordings Ltd',
] );

echo "invoice written\n";
