<?php
/**
 * PDF object serialisation.
 *
 * @package   SugarCommerce\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\TextPdf;

/**
 * Class Writer
 *
 * Turns page content streams into a PDF file.
 *
 * The cross-reference table is the part that has to be exact: readers
 * seek directly to the byte offsets it records, so an off-by-one
 * produces a file that opens in one viewer and fails in another. Offsets
 * are therefore measured from the assembled output rather than
 * calculated.
 *
 * @since 1.0.0
 */
final readonly class Writer {

	/**
	 * Serialise pages into a PDF.
	 *
	 * @since 1.0.0
	 *
	 * @param string[]              $pages    Content streams.
	 * @param float                 $width    Page width in points.
	 * @param float                 $height   Page height in points.
	 * @param array<string, string> $metadata Title, Author, Subject,
	 *                                        Keywords, Creator.
	 *
	 * @return string Raw PDF bytes.
	 */
	public static function write( array $pages, float $width, float $height, array $metadata = array() ): string {
		$pages = array() === $pages ? array( '' ) : $pages;

		$objects   = array();
		$page_refs = array();

		// 1 Catalog, 2 Pages, 3-5 fonts, 6 Info, then page/content pairs.
		$first_page = 7;

		foreach ( $pages as $index => $stream ) {
			$page_object    = $first_page + ( $index * 2 );
			$content_object = $page_object + 1;
			$page_refs[]    = $page_object . ' 0 R';

			$objects[ $page_object ] = '<</Type /Page /Parent 2 0 R '
				. '/Resources <</Font <</F1 3 0 R /F2 4 0 R /F3 5 0 R>>>> '
				. '/Contents ' . $content_object . ' 0 R>>';

			$objects[ $content_object ] = '<</Length ' . strlen( $stream ) . ">>\nstream\n" . $stream . "\nendstream";
		}

		$objects[1] = '<</Type /Catalog /Pages 2 0 R>>';
		$objects[2] = '<</Type /Pages /Kids [' . implode( ' ', $page_refs ) . '] /Count ' . count( $pages )
			. sprintf( ' /MediaBox [0 0 %.2F %.2F]>>', $width, $height );
		$objects[3] = '<</Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding>>';
		$objects[4] = '<</Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding>>';
		$objects[5] = '<</Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding>>';
		$objects[6] = self::info( $metadata );

		return self::serialise( $objects, $first_page + ( count( $pages ) * 2 ) - 1 );
	}

	/**
	 * Build the document information dictionary.
	 *
	 * @since 1.0.0
	 *
	 * `CreationDate` (since 1.1.0), a Unix timestamp, fixes the one value
	 * that otherwise changes from run to run — so the same document renders
	 * to the same bytes, which is what a content-addressed store or a
	 * snapshot test needs. Without it, the time of rendering.
	 *
	 * @param array<string, string|int> $metadata Metadata values.
	 *
	 * @return string
	 */
	private static function info( array $metadata ): string {
		$fields = array( 'Title', 'Author', 'Subject', 'Keywords', 'Creator' );
		$parts  = array();

		foreach ( $fields as $field ) {
			$value = trim( (string) ( $metadata[ $field ] ?? $metadata[ strtolower( $field ) ] ?? '' ) );

			if ( '' !== $value ) {
				$parts[] = '/' . $field . ' (' . Document::escape( $value ) . ')';
			}
		}

		$parts[] = '/Producer (sugarcommerce/text-pdf)';
		$created = $metadata['CreationDate'] ?? $metadata['creationdate'] ?? null;

		$parts[] = '/CreationDate (D:' . gmdate( 'YmdHis', is_numeric( $created ) ? (int) $created : time() ) . "Z)";

		return '<<' . implode( ' ', $parts ) . '>>';
	}

	/**
	 * Serialise objects with a correct cross-reference table.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $objects Object number => body.
	 * @param int                $max     Highest object number.
	 *
	 * @return string
	 */
	private static function serialise( array $objects, int $max ): string {
		$pdf     = "%PDF-1.4\n";
		$offsets = array();

		for ( $n = 1; $n <= $max; $n++ ) {
			$offsets[ $n ] = strlen( $pdf );
			$pdf          .= $n . " 0 obj\n" . ( $objects[ $n ] ?? '<<>>' ) . "\nendobj\n";
		}

		$xref = strlen( $pdf );

		$pdf .= "xref\n0 " . ( $max + 1 ) . "\n0000000000 65535 f \n";

		for ( $n = 1; $n <= $max; $n++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $n ] );
		}

		return $pdf . "trailer\n<</Size " . ( $max + 1 ) . " /Root 1 0 R /Info 6 0 R>>\nstartxref\n" . $xref . "\n%%EOF";
	}
}
