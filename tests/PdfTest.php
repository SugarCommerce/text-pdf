<?php
/**
 * text-pdf test suite.
 *
 * PDF validity is checked structurally. A file that renders in one
 * viewer and fails in another almost always has a wrong xref offset, so
 * those are verified against the actual bytes rather than eyeballed.
 *
 * @package   Sugar\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace Sugar\TextPdf\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sugar\TextPdf\{Align, Color, Document, Font, Orientation, PageSize, Svg, Table};
use Sugar\TextPdf\Templates\{Branding, Invoice, Language, LineItem, Party, Receipt, Statement, Translations, VatLine, VatTreatment};

final class PdfTest extends TestCase {

	/* ─── File structure ────────────────────────────────────────────── */

	public function test_output_is_a_pdf(): void {
		$pdf = ( new Document() )->text( 'Hello' )->render();

		$this->assertStringStartsWith( '%PDF-1.4', $pdf );
		$this->assertStringEndsWith( '%%EOF', $pdf );
	}

	public function test_required_objects_are_present(): void {
		$pdf = ( new Document() )->text( 'Hello' )->render();

		foreach ( array( '/Type /Catalog', '/Type /Pages', '/Type /Page ', '/BaseFont /Helvetica',
			'/BaseFont /Helvetica-Bold', '/BaseFont /Courier', '/WinAnsiEncoding', 'trailer', 'startxref' ) as $needle ) {
			$this->assertStringContainsString( $needle, $pdf, $needle );
		}
	}

	/**
	 * Readers seek directly to these byte offsets, so an off-by-one
	 * produces a file that opens in one viewer and fails in another.
	 */
	public function test_xref_offsets_point_at_their_objects(): void {
		$doc = new Document();

		for ( $i = 0; $i < 200; $i++ ) {
			$doc->text( 'A line of text' );
		}

		$pdf = $doc->render();

		preg_match( '/startxref\n(\d+)/', $pdf, $m );
		$this->assertNotEmpty( $m );
		$this->assertSame( 'xref', substr( $pdf, (int) $m[1], 4 ) );

		preg_match_all( '/^(\d{10}) 00000 n $/m', $pdf, $entries );
		$this->assertNotEmpty( $entries[1] );

		foreach ( $entries[1] as $index => $offset ) {
			$number = $index + 1;
			$this->assertSame( $number . ' 0 obj', substr( $pdf, (int) $offset, strlen( (string) $number ) + 6 ), 'object ' . $number );
		}
	}

	public function test_metadata_is_written(): void {
		$pdf = ( new Document() )->text( 'x' )->render( array( 'Title' => 'Invoice 42', 'Author' => 'Acme' ) );

		$this->assertStringContainsString( '/Title (Invoice 42)', $pdf );
		$this->assertStringContainsString( '/Author (Acme)', $pdf );
		$this->assertStringContainsString( '/Producer (sugarcommerce/text-pdf)', $pdf );
	}

	/* ─── Page sizes ────────────────────────────────────────────────── */

	#[DataProvider( 'page_sizes' )]
	public function test_page_dimensions( PageSize $size, string $expected ): void {
		$this->assertStringContainsString( $expected, ( new Document( $size ) )->text( 'x' )->render() );
	}

	/** @return array<string, array{0: PageSize, 1: string}> */
	public static function page_sizes(): array {
		return array(
			'A4'     => array( PageSize::A4, '/MediaBox [0 0 595.28 841.89]' ),
			'A5'     => array( PageSize::A5, '/MediaBox [0 0 419.53 595.28]' ),
			'Letter' => array( PageSize::Letter, '/MediaBox [0 0 612.00 792.00]' ),
			'Legal'  => array( PageSize::Legal, '/MediaBox [0 0 612.00 1008.00]' ),
		);
	}

	/**
	 * A4 is the default because everywhere outside North America uses
	 * it, and a Letter layout prints with a clipped footer on A4.
	 */
	public function test_a4_is_the_default(): void {
		$this->assertSame( PageSize::A4, ( new Document() )->size );
	}

	public function test_landscape_swaps_the_axes(): void {
		$pdf = ( new Document( PageSize::A4, Orientation::Landscape ) )->text( 'x' )->render();

		$this->assertStringContainsString( '/MediaBox [0 0 841.89 595.28]', $pdf );
	}

	/* ─── Font metrics ──────────────────────────────────────────────── */

	/**
	 * The metrics are what make alignment possible. Helvetica is
	 * proportional, so padding with spaces cannot line up a column.
	 */
	public function test_helvetica_is_proportional(): void {
		$this->assertNotEquals(
			Font::Helvetica->width_of( 'iiii', 10 ),
			Font::Helvetica->width_of( 'MMMM', 10 )
		);
	}

	public function test_courier_is_monospaced(): void {
		$this->assertEqualsWithDelta(
			Font::Courier->width_of( 'iiii', 10 ),
			Font::Courier->width_of( 'MMMM', 10 ),
			0.001
		);
	}

	public function test_bold_is_wider_than_regular(): void {
		$this->assertGreaterThan(
			Font::Helvetica->width_of( 'Total due', 10 ),
			Font::HelveticaBold->width_of( 'Total due', 10 )
		);
	}

	public function test_width_scales_with_size(): void {
		$this->assertEqualsWithDelta(
			Font::Helvetica->width_of( 'abc', 20 ),
			Font::Helvetica->width_of( 'abc', 10 ) * 2,
			0.001
		);
	}

	/**
	 * The bug behind text drawn through a rule above it, and ascenders
	 * poking over the top margin.
	 */
	public function test_baseline_sits_below_the_line_top(): void {
		$this->assertGreaterThan( 0, Font::Helvetica->ascender( 10 ) );
		$this->assertLessThan( 10, Font::Helvetica->ascender( 10 ) );
	}

	/**
	 * The bug behind a totals box whose text sits at the top of the band.
	 */
	public function test_band_centring_leaves_equal_space(): void {
		$band     = 32.0;
		$size     = 13.0;
		$baseline = Font::HelveticaBold->baseline_in_band( $band, $size );
		$cap      = Font::HelveticaBold->cap_height( $size );

		$above = $baseline - $cap;
		$below = $band - $baseline;

		$this->assertEqualsWithDelta( $above, $below, 0.001, 'equal space above the capitals and below the baseline' );
	}

	public function test_truncation_fits_the_width(): void {
		$truncated = Font::Helvetica->truncate( str_repeat( 'long description ', 20 ), 100, 10 );

		$this->assertLessThanOrEqual( 100, Font::Helvetica->width_of( $truncated, 10 ) );
		$this->assertStringEndsWith( '...', $truncated );
	}

	public function test_short_text_is_not_truncated(): void {
		$this->assertSame( 'short', Font::Helvetica->truncate( 'short', 500, 10 ) );
	}

	/* ─── Text handling ─────────────────────────────────────────────── */

	public function test_content_appears_in_the_stream(): void {
		$this->assertStringContainsString( '(Hello world) Tj', ( new Document() )->text( 'Hello world' )->render() );
	}

	#[DataProvider( 'escaping' )]
	public function test_delimiters_are_escaped( string $input, string $expected ): void {
		$this->assertSame( $expected, Document::escape( $input ) );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public static function escaping(): array {
		return array(
			'open paren'  => array( 'a (b', 'a \\(b' ),
			'close paren' => array( 'a )b', 'a \\)b' ),
			'backslash'   => array( 'a \\ b', 'a \\\\ b' ),
			'all three'   => array( '(\\)', '\\(\\\\\\)' ),
			'plain'       => array( 'plain', 'plain' ),
		);
	}

	public function test_currency_and_accents_survive(): void {
		$escaped = Document::escape( '£42.00 — Café' );

		$this->assertStringNotContainsString( '?', $escaped );
		$this->assertSame( 0xA3, ord( $escaped[0] ), 'pound sign' );
	}

	/**
	 * A carriage return in a customer name would otherwise displace the
	 * rest of the line.
	 */
	public function test_all_control_characters_are_stripped(): void {
		$this->assertSame( 'abcd', Document::escape( "a\x00b\rc\nd" ) );
	}

	public function test_unrepresentable_text_does_not_break_the_file(): void {
		$pdf = ( new Document() )->text( '日本語 🎵' )->render();

		$this->assertStringStartsWith( '%PDF', $pdf );
		$this->assertStringEndsWith( '%%EOF', $pdf );
	}

	public function test_long_lines_wrap_to_the_content_width(): void {
		$this->assertGreaterThan( 1, substr_count( ( new Document() )->text( str_repeat( 'word ', 100 ) )->render(), ') Tj' ) );
	}

	public function test_unbroken_strings_are_cut(): void {
		$this->assertGreaterThan( 1, substr_count( ( new Document() )->text( str_repeat( 'a', 400 ) )->render(), ') Tj' ) );
	}

	/* ─── Pagination ────────────────────────────────────────────────── */

	public function test_short_documents_are_one_page(): void {
		$this->assertSame( 1, substr_count( ( new Document() )->text( 'Hello' )->render(), '/Type /Page ' ) );
	}

	public function test_long_documents_paginate(): void {
		$doc = new Document();

		for ( $i = 0; $i < 200; $i++ ) {
			$doc->text( 'A line' );
		}

		$this->assertGreaterThan( 1, substr_count( $doc->render(), '/Type /Page ' ) );
	}

	public function test_explicit_breaks_create_pages(): void {
		$pdf = ( new Document() )->text( 'one' )->page_break()->text( 'two' )->render();

		$this->assertSame( 2, substr_count( $pdf, '/Type /Page ' ) );
		$this->assertStringContainsString( '/Count 2', $pdf );
	}

	public function test_conditional_breaks_only_fire_when_needed(): void {
		$doc = new Document();

		$this->assertFalse( $doc->break_if_needed( 50 ) );
		$this->assertTrue( $doc->break_if_needed( 10000 ) );
	}

	/**
	 * Page totals are not knowable until every page exists, which is why
	 * footers are deferred to render time.
	 */
	public function test_footers_know_the_page_total(): void {
		$doc = new Document();

		for ( $i = 0; $i < 200; $i++ ) {
			$doc->text( 'A line' );
		}

		$doc->on_each_page( static function ( Document $d, int $page, int $total ): void {
			$d->text_at( "Page {$page} of {$total}", $d->left(), 30, 8 );
		} );

		$pdf   = $doc->render();
		$total = substr_count( $pdf, '/Type /Page ' );

		$this->assertGreaterThan( 1, $total );

		// Every page carries a footer, and each knows the true total —
		// which is only knowable once the last page exists.
		for ( $page = 1; $page <= $total; $page++ ) {
			$this->assertStringContainsString( "(Page {$page} of {$total}) Tj", $pdf );
		}
	}

	/* ─── Tables ────────────────────────────────────────────────────── */

	public function test_tables_render_headers_and_rows(): void {
		$doc = new Document();

		( new Table( array( 'Description', 'Amount' ) ) )
			->widths( array( 0.7, 0.3 ) )
			->rows( array( array( 'Drum Kit', '£149.00' ) ) )
			->draw( $doc );

		$pdf = $doc->render();

		$this->assertStringContainsString( '(Description) Tj', $pdf );
		$this->assertStringContainsString( '(Drum Kit) Tj', $pdf );
	}

	/**
	 * A statement whose headings appear only on page one is unreadable
	 * from page two, and that is the default of every hand-rolled loop.
	 */
	public function test_table_headers_repeat_on_every_page(): void {
		$doc = new Document();

		$table = ( new Table( array( 'Date', 'Amount' ) ) )->widths( array( 0.7, 0.3 ) );

		for ( $i = 0; $i < 120; $i++ ) {
			$table->row( array( 'Row ' . $i, '£1.00' ) );
		}

		$table->draw( $doc );

		$streams = self::streams( $doc->render() );

		$this->assertGreaterThan( 1, count( $streams ) );

		foreach ( $streams as $index => $stream ) {
			$this->assertStringContainsString( '(Date) Tj', $stream, 'page ' . ( $index + 1 ) );
		}
	}

	public function test_column_widths_are_fractions_of_the_content_width(): void {
		// The same definition must lay out on A4 and Letter without change.
		foreach ( array( PageSize::A4, PageSize::Letter ) as $size ) {
			$doc = new Document( $size );

			( new Table( array( 'A', 'B' ) ) )->widths( array( 0.5, 0.5 ) )->rows( array( array( 'x', 'y' ) ) )->draw( $doc );

			$this->assertStringContainsString( '(x) Tj', $doc->render() );
		}
	}

	public function test_an_empty_table_does_not_break(): void {
		$this->assertStringStartsWith( '%PDF', ( new Table() )->draw( new Document() )->render() );
	}

	/* ─── SVG ───────────────────────────────────────────────────────── */

	public function test_svg_lines_convert(): void {
		$path = Svg::to_pdf_path( 'M0,0 L10,0 L10,10 Z', 1.0, 10 );

		$this->assertStringContainsString( ' m', $path );
		$this->assertStringContainsString( ' l', $path );
		$this->assertStringContainsString( 'h', $path );
	}

	public function test_svg_cubic_curves_convert(): void {
		$this->assertStringContainsString( ' c', Svg::to_pdf_path( 'M0,0 C5,0 10,5 10,10', 1.0, 10 ) );
	}

	/**
	 * PDF has no quadratic operator, so Q/T are elevated to cubics — an
	 * exact conversion, not an approximation.
	 */
	public function test_svg_quadratic_curves_become_cubics(): void {
		$this->assertStringContainsString( ' c', Svg::to_pdf_path( 'M0,0 Q5,0 10,10', 1.0, 10 ) );
	}

	/**
	 * SVG measures down from the top-left, PDF up from the bottom-left.
	 */
	public function test_the_y_axis_is_flipped(): void {
		$path = Svg::to_pdf_path( 'M0,0 L0,100', 1.0, 100 );

		$this->assertStringContainsString( '100.000', $path, 'y=0 becomes the viewBox height' );
		$this->assertStringContainsString( '0.000', $path, 'y=100 becomes zero' );
	}

	public function test_svg_scaling( ): void {
		$this->assertStringContainsString( '20.000', Svg::to_pdf_path( 'M20,0 L20,0', 1.0, 0 ) );
		$this->assertStringContainsString( '40.000', Svg::to_pdf_path( 'M20,0 L20,0', 2.0, 0 ) );
	}

	/**
	 * Prose tokenizes into commands if you let it — `a`, `h`, `t` and `c`
	 * are all valid SVG letters, so "not a path" drew three curves.
	 * Requiring a leading moveto, as the spec does, rejects it.
	 */
	#[DataProvider( 'invalid_paths' )]
	public function test_unparseable_paths_yield_nothing( string $path ): void {
		$this->assertSame( '', Svg::to_pdf_path( $path, 1.0, 10 ) );
	}

	/** @return array<string, array{0: string}> */
	public static function invalid_paths(): array {
		return array(
			'prose'          => array( 'not a path' ),
			'empty'          => array( '' ),
			'whitespace'     => array( '   ' ),
			'numbers only'   => array( '10 20 30' ),
			'no moveto'      => array( 'L10,10 L20,20' ),
			'bare moveto'    => array( 'M' ),
			'incomplete'     => array( 'M10' ),
			'html'           => array( '<svg><path d="M0,0"/></svg>' ),
		);
	}

	public function test_valid_paths_still_convert(): void {
		$this->assertNotSame( '', Svg::to_pdf_path( 'M0,0 L10,10 Z', 1.0, 10 ) );
		$this->assertNotSame( '', Svg::to_pdf_path( 'm 0,0 l 10,10 z', 1.0, 10 ) );
	}

	public function test_a_logo_reaches_the_page(): void {
		$pdf = ( new Document() )->svg_path( 'M0,0 L50,0 L50,50 Z', 100, 700, 1.0, Color::hex( '#336699' ), 50 )->render();

		// Positioned, filled with the requested colour, and closed.
		$this->assertStringContainsString( '1 0 0 1 100.00 700.00 cm', $pdf );
		$this->assertStringContainsString( '0.200 0.400 0.600 rg', $pdf );
		$this->assertStringContainsString( 'h', $pdf );
	}

	public function test_an_invalid_logo_draws_nothing(): void {
		$blank = ( new Document() )->render();
		$with  = ( new Document() )->svg_path( 'not a path', 100, 700 )->render();

		$this->assertSame( strlen( $blank ), strlen( $with ) );
	}

	/* ─── Colour ────────────────────────────────────────────────────── */

	public function test_hex_colours_parse(): void {
		$this->assertSame( 255, Color::hex( '#ff0000' )->red );
		$this->assertSame( 255, Color::hex( 'f00' )->red, 'shorthand' );
		$this->assertTrue( Color::hex( 'nonsense' )->is_black(), 'invalid falls back to black' );
	}

	public function test_colour_operands_are_normalised_floats(): void {
		$this->assertSame( '1.000 0.000 0.000', Color::hex( '#ff0000' )->operands() );
	}

	/* ─── Templates ─────────────────────────────────────────────────── */

	private function branding(): Branding {
		return new Branding( 'Sugarcart', 'Digital goods', logo_path: 'M0,0 L50,0 L50,50 Z', logo_box: 50 );
	}

	private function invoice( VatTreatment $vat = VatTreatment::Standard, string $customer_vat = '' ): Invoice {
		return new Invoice(
			branding: $this->branding(),
			number:   'INV-001',
			from:     new Party( 'Sugarcart Ltd', array( 'London' ), tax_id: 'GB123' ),
			to:       new Party( 'Acme Ltd', array( 'Berlin' ), tax_id: $customer_vat ),
			items:    array( new LineItem( 'Drum Kit', '£149.00', '1', '£149.00' ) ),
			totals:   array( 'Subtotal' => '£149.00' ),
			total:    array( 'Total due' => '£178.80' ),
			vat:      $vat,
			supply_date: '31 July 2026',
		);
	}

	public function test_the_invoice_template_renders(): void {
		$pdf = $this->invoice()->render()->render();

		$this->assertStringStartsWith( '%PDF', $pdf );
		$this->assertStringContainsString( '(INVOICE) Tj', $pdf );
		$this->assertStringContainsString( '(Acme Ltd) Tj', $pdf );
		$this->assertStringContainsString( '(Drum Kit) Tj', $pdf );
	}

	/**
	 * Monochrome by default: it prints correctly on any device, survives
	 * being photocopied, and is harder to date.
	 */
	public function test_branding_defaults_to_monochrome(): void {
		$this->assertTrue( ( new Branding( 'Test' ) )->is_monochrome() );
		$this->assertFalse( ( new Branding( 'Test', accent: '#0f766e' ) )->is_monochrome() );
	}

	/**
	 * The date of supply is a mandatory particular separate from the
	 * invoice date, and the one most often left off.
	 */
	public function test_the_supply_date_is_printed(): void {
		$this->assertStringContainsString( '(Date of supply) Tj', $this->invoice()->render()->render() );
	}

	#[DataProvider( 'vat_treatments' )]
	public function test_vat_wording_is_printed( VatTreatment $vat, string $expected ): void {
		$pdf = $this->invoice( $vat, 'DE811' )->render()->render();

		$this->assertStringContainsString( $expected, $pdf );
	}

	/** @return array<string, array{0: VatTreatment, 1: string}> */
	public static function vat_treatments(): array {
		return array(
			'reverse charge'  => array( VatTreatment::ReverseCharge, 'Reverse charge' ),
			'intra-community' => array( VatTreatment::IntraCommunitySupply, 'intra-community' ),
			'export'          => array( VatTreatment::Export, 'export' ),
			'small business'  => array( VatTreatment::SmallBusiness, 'small-business' ),
		);
	}

	public function test_standard_vat_prints_no_special_wording(): void {
		$this->assertSame( array(), VatTreatment::Standard->notes() );
	}

	public function test_german_wording_is_available(): void {
		$notes = VatTreatment::ReverseCharge->notes( true );

		$this->assertGreaterThan( 2, count( $notes ) );
		$this->assertStringContainsString( 'Steuerschuldnerschaft', implode( ' ', $notes ) );
	}

	/**
	 * Reverse charge and intra-community supply both need the customer's
	 * VAT number, without which the zero rating is not supportable.
	 */
	public function test_missing_customer_vat_is_reported( ): void {
		$warnings = $this->invoice( VatTreatment::ReverseCharge )->compliance_warnings();

		$this->assertNotEmpty( $warnings );
		$this->assertStringContainsString( 'customer VAT number', implode( ' ', $warnings ) );
	}

	public function test_a_complete_invoice_reports_nothing_missing(): void {
		$this->assertSame( array(), $this->invoice( VatTreatment::ReverseCharge, 'DE811' )->compliance_warnings() );
	}

	public function test_a_missing_supply_date_is_reported(): void {
		$invoice = new Invoice(
			branding: $this->branding(),
			number:   'INV-002',
			from:     new Party( 'Sugarcart Ltd', array( 'London' ), tax_id: 'GB123' ),
			to:       new Party( 'Acme Ltd', array( 'Berlin' ) ),
			items:    array( new LineItem( 'Item', '£10.00' ) ),
			totals:   array( 'Subtotal' => '£10.00' ),
		);

		$this->assertStringContainsString( 'date of supply', strtolower( implode( ' ', $invoice->compliance_warnings() ) ) );
	}

	public function test_the_vat_breakdown_is_rendered(): void {
		$invoice = new Invoice(
			branding:  $this->branding(),
			number:    'INV-003',
			from:      new Party( 'Sugarcart Ltd', array( 'London' ), tax_id: 'GB123' ),
			to:        new Party( 'Acme Ltd', array( 'Berlin' ) ),
			items:     array( new LineItem( 'Item', '£10.00' ) ),
			vat_lines: array(
				new VatLine( '20%', '£100.00', '£20.00' ),
				new VatLine( '5%', '£50.00', '£2.50', 'Reduced' ),
			),
			supply_date: '1 July 2026',
		);

		$pdf = $invoice->render()->render();

		$this->assertStringContainsString( '(VAT BREAKDOWN) Tj', $pdf );
		$this->assertStringContainsString( '(Reduced \\(5%\\)) Tj', $pdf );
	}

	public function test_the_statement_template_paginates_with_headers(): void {
		$rows = array();

		for ( $i = 0; $i < 100; $i++ ) {
			$rows[] = array( '01 Jul', 'Item ' . $i, 'REF-' . $i, '£1.00', '£' . $i . '.00' );
		}

		$statement = new Statement(
			branding: $this->branding(),
			account:  new Party( 'Acme Ltd', array( 'Berlin' ) ),
			period:   'July 2026',
			rows:     $rows,
			closing:  array( 'Closing balance' => '£100.00' ),
		);

		$streams = self::streams( $statement->render()->render() );

		$this->assertGreaterThan( 1, count( $streams ) );

		foreach ( $streams as $stream ) {
			$this->assertStringContainsString( '(Date) Tj', $stream );
		}
	}

	/**
	 * A receipt is a record, not a demand. Sending an invoice-shaped
	 * document after payment produces "have I already paid this?".
	 */
	public function test_the_receipt_template_says_paid(): void {
		$receipt = new Receipt(
			branding: $this->branding(),
			number:   'RCT-001',
			to:       new Party( 'Acme Ltd', array( 'Berlin' ) ),
			items:    array( new LineItem( 'Drum Kit', '£149.00' ) ),
			total:    '£178.80',
		);

		$pdf = $receipt->render()->render();

		$this->assertStringContainsString( '(RECEIPT) Tj', $pdf );
		$this->assertStringContainsString( '(PAID IN FULL) Tj', $pdf );
	}

	public function test_receipts_default_to_a5(): void {
		$receipt = new Receipt(
			branding: $this->branding(),
			number:   'RCT-002',
			to:       new Party( 'Acme Ltd' ),
			items:    array( new LineItem( 'Item', '£1.00' ) ),
			total:    '£1.00',
		);

		$this->assertStringContainsString( '/MediaBox [0 0 419.53 595.28]', $receipt->render()->render() );
	}

	/* ─── Languages ─────────────────────────────────────────────────── */

	#[DataProvider( 'languages' )]
	public function test_every_language_has_a_complete_phrase_table( Language $language ): void {
		$english = Language::English->all();
		$theirs   = $language->all();

		foreach ( array_keys( $english ) as $key ) {
			$this->assertArrayHasKey( $key, $theirs, $language->value . ' is missing ' . $key );
			$this->assertNotSame( '', trim( $theirs[ $key ] ), $language->value . ':' . $key );
		}
	}

	/**
	 * WinAnsi covers Latin-1 only. A phrase containing a character
	 * outside it would print as a substitute or vanish, so every shipped
	 * translation has to survive the transcode unchanged.
	 */
	#[DataProvider( 'languages' )]
	public function test_every_phrase_survives_winansi_encoding( Language $language ): void {
		foreach ( $language->all() as $key => $phrase ) {
			$encoded = Document::escape( $phrase );

			$this->assertStringNotContainsString( '?', $encoded, $language->value . ':' . $key );
			$this->assertSame( mb_strlen( $phrase ), strlen( $encoded ), $language->value . ':' . $key );
		}
	}

	/** @return array<string, array{0: Language}> */
	public static function languages(): array {
		$cases = array();

		foreach ( Language::cases() as $language ) {
			$cases[ $language->value ] = array( $language );
		}

		return $cases;
	}

	public function test_locales_resolve_to_languages(): void {
		$this->assertSame( Language::German, Language::from_locale( 'de' ) );
		$this->assertSame( Language::German, Language::from_locale( 'de_DE' ) );
		$this->assertSame( Language::German, Language::from_locale( 'de-AT' ) );
		$this->assertSame( Language::Norwegian, Language::from_locale( 'no' ) );
		$this->assertSame( Language::Norwegian, Language::from_locale( 'nn_NO' ) );
		$this->assertSame( Language::English, Language::from_locale( 'zz' ), 'unknown falls back' );
	}

	public function test_templates_are_translated(): void {
		$german = new Invoice(
			branding: $this->branding(),
			number:   'RE-001',
			from:     new Party( 'Sugarcart Ltd', array( 'London' ), tax_id: 'GB123' ),
			to:       new Party( 'Klangwerk GmbH', array( 'Berlin' ), tax_id: 'DE811' ),
			items:    array( new LineItem( 'Drum Kit', '149,00 EUR', '1', '149,00 EUR' ) ),
			total:    array( 'Gesamtbetrag' => '178,80 EUR' ),
			supply_date: '31. Juli 2026',
			translator: Language::German,
		);

		$pdf = $german->render()->render();

		$this->assertStringContainsString( '(RECHNUNG) Tj', $pdf );
		$this->assertStringContainsString( '(Beschreibung) Tj', $pdf );
		$this->assertStringContainsString( '(Leistungsdatum) Tj', $pdf );
	}

	/**
	 * A German-language invoice needs the German VAT wording, and
	 * remembering to set two separate flags is a trap.
	 */
	public function test_german_documents_get_german_vat_wording(): void {
		$invoice = new Invoice(
			branding: $this->branding(),
			number:   'RE-002',
			from:     new Party( 'Sugarcart Ltd', array( 'London' ), tax_id: 'GB123' ),
			to:       new Party( 'Klangwerk GmbH', array( 'Berlin' ), tax_id: 'DE811' ),
			items:    array( new LineItem( 'Item', '10,00 EUR' ) ),
			vat:      VatTreatment::ReverseCharge,
			supply_date: '1. Juli 2026',
			translator: Language::German,
		);

		$this->assertStringContainsString( 'Steuerschuldnerschaft', $invoice->render()->render() );
	}

	public function test_statement_headers_follow_the_language(): void {
		$statement = new Statement(
			branding: $this->branding(),
			account:  new Party( 'Klangwerk GmbH' ),
			period:   'Juli 2026',
			rows:     array( array( '01.07.', 'Artikel', 'REF-1', '10,00', '10,00' ) ),
			translator: Language::German,
		);

		$pdf = $statement->render()->render();

		$this->assertStringContainsString( '(KONTOAUSZUG) Tj', $pdf );
		$this->assertStringContainsString( '(Datum) Tj', $pdf );
		$this->assertStringContainsString( '(Saldo) Tj', $pdf );
	}

	/**
	 * The point of the contract: an application's own translation system
	 * supplies the words, so PDF strings go through the same workflow as
	 * everything else rather than living in a second, parallel one.
	 */
	public function test_an_external_translator_supplies_the_wording(): void {
		// Shaped like a WordPress __() call, which keys on the source text.
		$translator = new Translations(
			static fn( string $key, string $default ): string => 'de' === $key ? $default : strtolower( $default )
		);

		$invoice = new Invoice(
			branding:   $this->branding(),
			number:     'INV-900',
			from:       new Party( 'Sugarcart Ltd', array( 'London' ), tax_id: 'GB123' ),
			to:         new Party( 'Acme Ltd', array( 'Berlin' ) ),
			items:      array( new LineItem( 'Item', '£1.00' ) ),
			supply_date: '1 July 2026',
			translator: $translator,
		);

		$this->assertStringContainsString( '(invoice) Tj', $invoice->render()->render() );
	}

	public function test_a_translator_returning_nothing_falls_back(): void {
		$empty = new Translations( static fn(): string => '' );

		$this->assertSame( 'Total due', $empty->translate( 'total_due', 'Total due' ) );
	}

	public function test_a_custom_fallback_is_honoured(): void {
		$empty = new Translations( static fn(): string => '', Language::German );

		$this->assertSame( 'Gesamtbetrag', $empty->translate( 'total_due', 'Total due' ) );
	}

	public function test_languages_satisfy_the_translator_contract(): void {
		foreach ( Language::cases() as $language ) {
			$this->assertInstanceOf( \Sugar\TextPdf\Templates\Translator::class, $language );
		}
	}

	public function test_explicit_statement_headers_win(): void {
		$statement = new Statement(
			branding: $this->branding(),
			account:  new Party( 'Acme' ),
			period:   '2026',
			rows:     array( array( 'a', 'b' ) ),
			headers:  array( 'Custom A', 'Custom B' ),
			widths:   array( 0.5, 0.5 ),
			aligns:   array(),
		);

		$this->assertStringContainsString( '(Custom A) Tj', $statement->render()->render() );
	}

	/* ─── Saving ────────────────────────────────────────────────────── */

	public function test_saving_writes_a_file(): void {
		$path = tempnam( sys_get_temp_dir(), 'pdf-test-' );

		try {
			$bytes = ( new Document() )->text( 'Hello' )->save( $path );

			$this->assertGreaterThan( 0, $bytes );
			$this->assertStringStartsWith( '%PDF', (string) file_get_contents( $path ) );
		} finally {
			@unlink( $path );
		}
	}

	public function test_saving_to_an_unwritable_path_throws(): void {
		$this->expectException( \RuntimeException::class );
		( new Document() )->save( '/definitely/not/a/directory/out.pdf' );
	}

	/**
	 * Split a rendered PDF into its page content streams.
	 *
	 * @param string $pdf Rendered document.
	 *
	 * @return string[]
	 */
	private static function streams( string $pdf ): array {
		preg_match_all( '/stream\n(.*?)\nendstream/s', $pdf, $m );

		return $m[1];
	}
}
