<?php
/**
 * Untrusted content reaching a PDF.
 *
 * @package   SugarCommerce\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\TextPdf\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SugarCommerce\TextPdf\Align;
use SugarCommerce\TextPdf\Color;
use SugarCommerce\TextPdf\Document;
use SugarCommerce\TextPdf\Font;
use SugarCommerce\TextPdf\Svg;

/**
 * An invoice is built from customer-supplied names, addresses and product
 * titles. None of it may escape the string literal it is written into, and
 * none of it may produce an operand a reader refuses.
 */
final class PdfSecurityTest extends TestCase {

	/* ─── String literals ───────────────────────────────────────────── */

	/**
	 * An unescaped `)` closes the literal and turns the rest of the value
	 * into content-stream operators.
	 */
	#[DataProvider( 'literal_breakouts' )]
	public function test_text_cannot_escape_its_literal( string $hostile ): void {
		$pdf = new Document();
		$pdf->text( $hostile );

		$rendered = $pdf->render();

		// Every parenthesis in the stream is either escaped or one of the
		// delimiters the writer emitted itself.
		$this->assertSame(
			substr_count( $rendered, '(' ) - substr_count( $rendered, '\(' ),
			substr_count( $rendered, ')' ) - substr_count( $rendered, '\)' ),
			'Unbalanced delimiters mean a value broke out of its literal.'
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function literal_breakouts(): array {
		return array(
			'close paren'    => array( 'Acme) Tj /F1 40 Tf (INJECTED' ),
			'open paren'     => array( 'Acme (Holdings' ),
			'javascript'     => array( ') ] /Type /Action /S /JavaScript /JS (app.alert(1)) (' ),
			'backslash'      => array( 'C:\\Users\\dave' ),
			'escaped paren'  => array( 'a\\) Tj (b' ),
			'stream end'     => array( 'endstream endobj trailer' ),
		);
	}

	public function test_the_payload_stays_inside_one_literal(): void {
		$pdf = new Document();
		$pdf->text( 'a) Tj 1 0 0 rg (injected' );

		preg_match_all( '/\((?:\\\\.|[^()\\\\])*\)\s*Tj/', $pdf->render(), $matches );

		$this->assertCount( 1, $matches[0] );
		$this->assertSame( '(a\) Tj 1 0 0 rg \(injected) Tj', $matches[0][0] );
	}

	/**
	 * A stray CR would displace the rest of the line; a NUL would
	 * truncate it in some readers.
	 */
	public function test_control_characters_are_removed(): void {
		$escaped = Document::escape( "line\r\none\ttwo\x00\x08" );

		$this->assertSame( 'lineonetwo', $escaped );
	}

	public function test_metadata_is_escaped_too(): void {
		$pdf = new Document();
		$pdf->text( 'Body' );

		$rendered = $pdf->render( array( 'Title' => 'x) >> /OpenAction << /S /JavaScript /JS (alert(1)' ) );

		// The payload is inert because it never leaves the /Title literal
		// — the dictionary cannot be reopened, so /OpenAction is text.
		preg_match( '/\/Title \((?:\\\\.|[^()\\\\])*\)/', $rendered, $matches );

		$this->assertCount( 1, $matches );
		$this->assertStringContainsString( '/OpenAction', $matches[0] );
		$this->assertStringContainsString( '\)', $matches[0] );
	}

	public function test_latin1_characters_survive(): void {
		// WinAnsi bytes: fc = u-umlaut, 97 = em dash.
		$this->assertSame( '4dfc6c6c6572209720dc', bin2hex( Document::escape( 'Müller — Ü' ) ) );
	}

	public function test_unrepresentable_scripts_degrade_rather_than_break(): void {
		$escaped = Document::escape( '日本語' );

		$this->assertMatchesRegularExpression( '/^\?+$/', $escaped );
	}

	/**
	 * Outside the Latin range of the page fonts, the information
	 * dictionary is UTF-16 in hex — which has no delimiter at all, so
	 * there is nothing for a value to break out of.
	 */
	public function test_non_ascii_metadata_is_utf16_hex(): void {
		$title    = 'Café) >> /OpenAction << /S /JavaScript /JS (alert(1)';
		$rendered = ( new Document() )->text( 'Body' )->render( array( 'Title' => $title ) );
		$expected = '<FEFF' . strtoupper( bin2hex( mb_convert_encoding( $title, 'UTF-16BE', 'UTF-8' ) ) ) . '>';

		$this->assertStringContainsString( '/Title ' . $expected, $rendered );
		$this->assertStringNotContainsString( '/OpenAction', $rendered );
	}

	/**
	 * Windows-1252 bytes in the information dictionary are read as
	 * PDFDocEncoding, where 0x97 and 0x80 are different characters.
	 */
	public function test_metadata_is_not_written_in_winansi(): void {
		$rendered = ( new Document() )->text( 'x' )->render( array( 'Title' => 'Statement — €5' ) );

		$this->assertStringNotContainsString( "\x97", $rendered );
		$this->assertStringNotContainsString( "\x80", $rendered );
		$this->assertStringContainsString( '/Title <FEFF', $rendered );
	}

	public function test_control_characters_are_removed_from_metadata(): void {
		$rendered = ( new Document() )->text( 'x' )->render( array( 'Author' => "Acme\r\n/Evil" ) );

		$this->assertStringContainsString( '/Author (Acme/Evil)', $rendered );
	}

	/**
	 * A creation date outside 0001-9999 is clamped, not written as a
	 * year the date format cannot hold.
	 */
	#[DataProvider( 'out_of_range_dates' )]
	public function test_an_out_of_range_creation_date_is_clamped( int|string $created, string $expected ): void {
		$rendered = ( new Document() )->text( 'x' )->render( array( 'CreationDate' => $created ) );

		$this->assertStringContainsString( '/CreationDate (D:' . $expected . 'Z)', $rendered );
	}

	/**
	 * @return array<string, array{0: int|string, 1: string}>
	 */
	public static function out_of_range_dates(): array {
		return array(
			'int max'      => array( PHP_INT_MAX, '99991231235959' ),
			'huge string'  => array( '1e30', '99991231235959' ),
			'far past'     => array( PHP_INT_MIN, '00010101000000' ),
			'numeric text' => array( '1700000000', '20231114221320' ),
		);
	}

	/* ─── Layout ────────────────────────────────────────────────────── */

	/**
	 * Truncating dropped a byte and re-measured the whole string, which
	 * is quadratic — a long customer-supplied cell held a worker for
	 * minutes.
	 */
	public function test_truncating_a_huge_value_is_fast(): void {
		$start     = hrtime( true );
		$truncated = Font::Helvetica->truncate( str_repeat( 'a', 100_000 ), 100, 10 );

		$this->assertLessThan( 2.0, ( hrtime( true ) - $start ) / 1e9 );
		$this->assertLessThanOrEqual( 100, Font::Helvetica->width_of( $truncated, 10 ) );
		$this->assertStringEndsWith( '...', $truncated );
	}

	/**
	 * Cutting an unbroken word re-measured the remainder after every
	 * cut, which is quadratic in the length of the word.
	 */
	public function test_wrapping_a_huge_unbroken_value_is_fast(): void {
		$start = hrtime( true );
		$pdf   = ( new Document() )->text( str_repeat( 'a', 500_000 ) );

		$this->assertLessThan( 2.0, ( hrtime( true ) - $start ) / 1e9 );
		$this->assertGreaterThan( 1, $pdf->page_count() );
	}

	/**
	 * A cut through the middle of a multibyte character made the string
	 * invalid UTF-8, and every accent in it then printed as `?`.
	 */
	public function test_truncation_never_splits_a_character(): void {
		$truncated = Font::Helvetica->truncate( str_repeat( 'Ü', 100 ), 50, 10 );

		$this->assertTrue( mb_check_encoding( $truncated, 'UTF-8' ) );
		$this->assertStringNotContainsString( '?', Document::escape( $truncated ) );
	}

	public function test_wrapping_never_splits_a_character(): void {
		$rendered = ( new Document() )->text( str_repeat( 'Ü', 300 ) )->render();

		$this->assertStringNotContainsString( '?', $rendered );
		$this->assertGreaterThan( 1, substr_count( $rendered, ') Tj' ) );
	}

	/**
	 * Every cut piece fits the column it was cut for.
	 */
	public function test_cut_pieces_fit_the_column(): void {
		$pdf = new Document();
		$pdf->text( str_repeat( 'W', 400 ) );

		preg_match_all( '/\((W+)\) Tj/', $pdf->render(), $matches );

		$this->assertNotEmpty( $matches[1] );

		foreach ( $matches[1] as $piece ) {
			$this->assertLessThanOrEqual( $pdf->content_width(), Font::Helvetica->width_of( $piece, $pdf->font_size ) );
		}
	}

	/**
	 * The backslashes escaping adds are never drawn, so measuring them
	 * pushed right-aligned text out of line.
	 */
	public function test_alignment_measures_the_text_not_its_escaping(): void {
		$pdf = new Document();
		$pdf->text_at( '(5.00)', 100, 500, 10, Font::Helvetica, null, Align::Right, 200 );

		$expected = 100 + 200 - Font::Helvetica->width_of( '(5.00)', 10 );

		$this->assertStringContainsString( sprintf( '%.2F 500.00 Td', $expected ), $pdf->render() );
	}

		/* ─── Numbers ───────────────────────────────────────────────────── */

	/**
	 * `sprintf( '%.2F', INF )` writes the literal `INF`, which is not a
	 * PDF number — a reader meeting one drops the page or refuses the
	 * file.
	 */
	public function test_non_finite_dimensions_never_reach_the_stream(): void {
		$pdf = new Document();
		$pdf->rect( INF, NAN, 1e400, 10, Color::black() );
		$pdf->line( NAN, 0, INF, 0 );
		$pdf->text_at( 'x', INF, NAN, 10.0 );

		$rendered = $pdf->render();

		$this->assertStringNotContainsString( 'INF', $rendered );
		$this->assertStringNotContainsString( 'NAN', $rendered );
	}

	public function test_dimensions_are_clamped_to_the_format_limit(): void {
		$this->assertSame( 32767.0, Document::number( 1e400 ) );
		$this->assertSame( -32767.0, Document::number( -1e400 ) );
		$this->assertSame( 0.0, Document::number( NAN ) );
		$this->assertSame( 12.5, Document::number( 12.5 ) );
	}

	/* ─── SVG paths ─────────────────────────────────────────────────── */

	public function test_svg_coordinates_are_clamped(): void {
		$path = Svg::to_pdf_path( 'M0 0 L1e400 1' );

		$this->assertStringNotContainsString( 'INF', $path );
		$this->assertStringContainsString( '32767.000', $path );
	}

	public function test_an_enormous_coordinate_is_clamped(): void {
		$path = Svg::to_pdf_path( 'M0 0 L' . str_repeat( '9', 400 ) . ' 1' );

		$this->assertStringNotContainsString( 'INF', $path );
	}

	/**
	 * `t`, `a`, `h` and `c` are all valid SVG commands, so prose would
	 * otherwise tokenise into arbitrary vectors.
	 */
	#[DataProvider( 'non_paths' )]
	public function test_prose_produces_no_path( string $input ): void {
		$this->assertSame( '', Svg::to_pdf_path( $input ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function non_paths(): array {
		return array(
			'sentence'    => array( 'not a path at all' ),
			'no moveto'   => array( 'L10 10 h5' ),
			'bare letter' => array( 'M' ),
			'empty'       => array( '' ),
			'operators'   => array( ') Tj /F1 40 Tf (' ),
		);
	}

	public function test_a_real_path_still_converts(): void {
		$path = Svg::to_pdf_path( 'M0,0 h10 v10 h-10 z' );

		$this->assertStringContainsString( ' m', $path );
		$this->assertStringContainsString( ' l', $path );
	}

	/* ─── Structure ─────────────────────────────────────────────────── */

	public function test_a_document_built_from_hostile_input_is_still_valid(): void {
		$pdf = new Document();

		foreach ( self::literal_breakouts() as [ $hostile ] ) {
			$pdf->text( $hostile );
		}

		$rendered = $pdf->render( array( 'Title' => ') evil (' ) );

		$this->assertStringStartsWith( '%PDF-', $rendered );
		$this->assertStringContainsString( 'xref', $rendered );
		$this->assertStringEndsWith( '%%EOF', $rendered );
	}
}
