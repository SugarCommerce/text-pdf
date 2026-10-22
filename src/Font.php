<?php
/**
 * Standard PDF fonts and their metrics.
 *
 * @package   SugarCommerce\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\TextPdf;

/**
 * Enum Font
 *
 * The base-14 fonts every PDF reader is required to have, so nothing
 * needs embedding and the output stays a few kilobytes.
 *
 * The width tables are the reason this library can lay out a table at
 * all. Helvetica is proportional — a space is 278 units where a capital
 * `E` is 667 — so padding a label with spaces to align a column only
 * works in a monospace font. Anything that right-aligns a currency
 * column has to measure the string first, and measuring means carrying
 * the metrics.
 *
 * Widths are Adobe's AFM values, in 1/1000 em.
 *
 * @since 1.0.0
 */
enum Font: string {

	/**
	 * Helvetica, the proportional body face.
	 */
	case Helvetica = 'Helvetica';

	/**
	 * Helvetica-Bold, for headings, labels and totals.
	 */
	case HelveticaBold = 'Helvetica-Bold';

	/**
	 * Courier, monospaced — every glyph 600 units wide.
	 */
	case Courier = 'Courier';

	/**
	 * Character widths for ASCII 32-126, in 1/1000 em.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, int> Code point => width.
	 */
	public function widths(): array {
		// Built once per font: width_of() runs for every cell, line and
		// word of a document, and rebuilding a 95-entry table each time
		// was most of its cost.
		/** @var array<string, array<int, int>> $tables */
		static $tables = array();

		return $tables[ $this->value ] ??= match ( $this ) {
			self::Courier       => array_fill( 32, 95, 600 ),
			self::Helvetica     => self::helvetica_widths(),
			self::HelveticaBold => self::helvetica_bold_widths(),
		};
	}

	/**
	 * Distance from the top of a line box down to the baseline.
	 *
	 * PDF positions text by its baseline, but layout code thinks in
	 * terms of the top of a line. Confusing the two puts ascenders above
	 * the top margin and draws rules straight through the text beneath
	 * them.
	 *
	 * Helvetica's ascender is 718/1000 em; a little headroom is added so
	 * consecutive lines do not touch.
	 *
	 * @since 1.0.0
	 *
	 * @param float $size Font size in points.
	 *
	 * @return float
	 */
	public function ascender( float $size ): float {
		return $size * 0.78;
	}

	/**
	 * Cap height — the height of a capital letter above the baseline.
	 *
	 * The measurement to centre on. Using the full em box instead leaves
	 * text looking high, because the descender space below the baseline
	 * is counted even when nothing occupies it.
	 *
	 * @since 1.0.0
	 *
	 * @param float $size Font size in points.
	 *
	 * @return float
	 */
	public function cap_height( float $size ): float {
		return $size * 0.717;
	}

	/**
	 * Baseline offset for text vertically centred in a band.
	 *
	 * Returns how far below the top of the band the baseline sits, so
	 * equal space falls above the capitals and below the baseline.
	 * Without this, a filled band with text drawn one ascender down looks
	 * top-heavy — which is exactly how a totals box reads wrong.
	 *
	 * @since 1.0.0
	 *
	 * @param float $band_height Height of the band, in points.
	 * @param float $size        Font size in points.
	 *
	 * @return float Distance from the band top down to the baseline.
	 */
	public function baseline_in_band( float $band_height, float $size ): float {
		return ( $band_height + $this->cap_height( $size ) ) / 2;
	}

	/**
	 * Distance the descender falls below the baseline.
	 *
	 * @since 1.0.0
	 *
	 * @param float $size Font size in points.
	 *
	 * @return float
	 */
	public function descender( float $size ): float {
		return $size * 0.22;
	}

	/**
	 * The width of a string at a given size, in points.
	 *
	 * Characters outside the table — accented letters, currency symbols —
	 * fall back to the width of `n`, which is close enough that a column
	 * does not visibly drift.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Text to measure.
	 * @param float  $size Font size in points.
	 *
	 * @return float Width in points.
	 */
	public function width_of( string $text, float $size ): float {
		$widths   = $this->widths();
		$fallback = $widths[ ord( 'n' ) ] ?? 556;
		$total    = 0;

		for ( $i = 0, $length = strlen( $text ); $i < $length; $i++ ) {
			$total += $widths[ ord( $text[ $i ] ) ] ?? $fallback;
		}

		return ( $total / 1000 ) * $size;
	}

	/**
	 * How many characters of this font fit in a width.
	 *
	 * @since 1.0.0
	 *
	 * @param float $width Available width in points.
	 * @param float $size  Font size in points.
	 *
	 * @return int Conservative character count, using average width.
	 */
	public function fits( float $width, float $size ): int {
		// Lowercase 'n' is close to the average for both Helvetica and
		// Courier, so it makes a reasonable divisor for wrapping.
		$average = $this->width_of( 'n', $size );

		return max( 1, (int) floor( $width / $average ) );
	}

	/**
	 * Truncate text to a width, adding an ellipsis when it does not fit.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text  Text.
	 * @param float  $width Available width in points.
	 * @param float  $size  Font size in points.
	 *
	 * @return string
	 */
	public function truncate( string $text, float $width, float $size ): string {
		if ( $this->width_of( $text, $size ) <= $width ) {
			return $text;
		}

		// One pass, measuring a character at a time. Re-measuring the whole
		// string after dropping each byte is quadratic: twenty kilobytes
		// took seconds, and a long enough customer-supplied value held a
		// worker indefinitely. Cutting by character rather than by byte
		// also keeps the UTF-8 valid — a split `ü` would otherwise turn
		// every accent in the cell into `?`.
		$budget     = $width - $this->width_of( '...', $size );
		$widths     = $this->widths();
		$fallback   = $widths[ ord( 'n' ) ] ?? 556;
		$characters = mb_check_encoding( $text, 'UTF-8' ) ? mb_str_split( $text, 1, 'UTF-8' ) : str_split( $text );
		$units      = 0;
		$kept       = '';

		foreach ( $characters as $character ) {
			// Measured exactly as width_of() measures: every byte, with
			// anything outside the table at the width of `n`.
			for ( $i = 0, $length = strlen( $character ); $i < $length; $i++ ) {
				$units += $widths[ ord( $character[ $i ] ) ] ?? $fallback;
			}

			if ( ( $units / 1000 ) * $size > $budget ) {
				break;
			}

			$kept .= $character;
		}

		return rtrim( $kept ) . '...';
	}

	/**
	 * Helvetica widths.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, int>
	 */
	private static function helvetica_widths(): array {
		return array(
			32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
			40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
			48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
			56 => 556, 57 => 556, 58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556,
			64 => 1015, 65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
			72 => 722, 73 => 278, 74 => 500, 75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778,
			80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
			88 => 667, 89 => 667, 90 => 611, 91 => 278, 92 => 278, 93 => 278, 94 => 469, 95 => 556,
			96 => 333, 97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556,
			104 => 556, 105 => 222, 106 => 222, 107 => 500, 108 => 222, 109 => 833, 110 => 556, 111 => 556,
			112 => 556, 113 => 556, 114 => 333, 115 => 500, 116 => 278, 117 => 556, 118 => 500, 119 => 722,
			120 => 500, 121 => 500, 122 => 500, 123 => 334, 124 => 260, 125 => 334, 126 => 584,
		);
	}

	/**
	 * Helvetica-Bold widths.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, int>
	 */
	private static function helvetica_bold_widths(): array {
		return array(
			32 => 278, 33 => 333, 34 => 474, 35 => 556, 36 => 556, 37 => 889, 38 => 722, 39 => 238,
			40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
			48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
			56 => 556, 57 => 556, 58 => 333, 59 => 333, 60 => 584, 61 => 584, 62 => 584, 63 => 611,
			64 => 975, 65 => 722, 66 => 722, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
			72 => 722, 73 => 278, 74 => 556, 75 => 722, 76 => 611, 77 => 833, 78 => 722, 79 => 778,
			80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
			88 => 667, 89 => 667, 90 => 611, 91 => 333, 92 => 278, 93 => 333, 94 => 584, 95 => 556,
			96 => 333, 97 => 556, 98 => 611, 99 => 556, 100 => 611, 101 => 556, 102 => 333, 103 => 611,
			104 => 611, 105 => 278, 106 => 278, 107 => 556, 108 => 278, 109 => 889, 110 => 611, 111 => 611,
			112 => 611, 113 => 611, 114 => 389, 115 => 556, 116 => 333, 117 => 611, 118 => 556, 119 => 778,
			120 => 556, 121 => 556, 122 => 500, 123 => 389, 124 => 280, 125 => 389, 126 => 584,
		);
	}
}
