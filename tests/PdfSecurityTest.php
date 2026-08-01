<?php
/**
 * Untrusted content reaching a PDF.
 *
 * @package   SugarMill\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarMill\TextPdf\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SugarMill\TextPdf\Color;
use SugarMill\TextPdf\Document;
use SugarMill\TextPdf\Svg;

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
