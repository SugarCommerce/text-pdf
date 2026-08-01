<?php
/**
 * Sample: a multi-page A4 statement with repeating headers, page
 * numbers, and a landscape variant.
 */
declare( strict_types=1 );

require __DIR__ . '/../vendor/autoload.php';

use SugarMill\TextPdf\{Document, Table, Font, Color, Align, PageSize, Orientation};

$brand = Color::hex( '#0f766e' );
$muted = Color::grey( 110 );
$logo  = 'M10,0 L70,0 Q80,0 80,10 L80,60 Q80,70 70,70 L10,70 Q0,70 0,60 L0,10 Q0,0 10,0 Z';

$landscape = in_array( '--landscape', $argv, true );

$pdf = new Document(
	PageSize::A4,
	$landscape ? Orientation::Landscape : Orientation::Portrait,
	margin: 48
);

// A footer on every page — page totals are only known once the whole
// document has been laid out, which is why this is a callback.
$pdf->on_each_page( function ( Document $doc, int $page, int $total ) use ( $muted ): void {
	$doc->line( $doc->left(), 44, $doc->right(), 44, Color::grey( 225 ) );
	$doc->text_at( 'Sugarcart Ltd — Statement of account', $doc->left(), 32, 8, Font::Helvetica, $muted );
	$doc->text_at( "Page {$page} of {$total}", $doc->left(), 32, 8, Font::Helvetica, $muted, Align::Right, $doc->content_width() );
} );

$pdf->svg_path( $logo, $pdf->left(), $pdf->height() - 88, 0.42, $brand, 70 );
$pdf->text_at( 'SUGARCART', $pdf->left() + 44, $pdf->height() - 66, 13, Font::HelveticaBold, $brand );

$pdf->text_at( 'STATEMENT OF ACCOUNT', $pdf->left(), $pdf->height() - 62, 18, Font::HelveticaBold, Color::grey( 25 ), Align::Right, $pdf->content_width() );
$pdf->text_at( '1 July – 31 July 2026', $pdf->left(), $pdf->height() - 77, 9.5, Font::Helvetica, $muted, Align::Right, $pdf->content_width() );

$pdf->move_to( $pdf->height() - 115 );
$pdf->text( 'Acme Recordings Ltd', 11, Font::HelveticaBold );
$pdf->text( 'Account 4471-2290  ·  Currency GBP', 9.5, Font::Helvetica, $muted );
$pdf->gap( 10 );

// 60 rows, which is comfortably more than one page.
$rows    = array();
$balance = 0;
$items   = [ 'Drum Kit Vol. 2', 'Analogue Bass', 'Vintage Keys', 'Tape Saturation', 'Field Recordings', 'Modular Textures' ];

for ( $i = 1; $i <= 60; $i++ ) {
	$amount   = ( 4900 + ( $i * 337 ) % 21000 );
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

( new Table( [ 'Date', 'Description', 'Reference', 'Amount', 'Balance' ] ) )
	->widths( [ 0.14, 0.36, 0.18, 0.16, 0.16 ] )
	->align( [ 3 => Align::Right, 4 => Align::Right ] )
	->striped()
	->rows( $rows )
	->draw( $pdf, 9 );

$pdf->gap( 10 );
$pdf->rule( Color::grey( 200 ) );

$box = $pdf->content_width() * 0.4;
$x   = $pdf->right() - $box;
$y   = $pdf->cursor();

$pdf->text_at( 'Closing balance', $x, $y, 11, Font::HelveticaBold );
$pdf->text_at( '£' . number_format( $balance / 100, 2 ), $x, $y, 13, Font::HelveticaBold, $brand, Align::Right, $box );

$pdf->save( $argv[1] ?? 'statement.pdf', [
	'Title'  => 'Statement of account — July 2026',
	'Author' => 'Sugarcart Ltd',
] );

printf( "statement written: %d pages\n", $pdf->page_count() );
