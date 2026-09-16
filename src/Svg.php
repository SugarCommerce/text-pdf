<?php
/**
 * SVG path to PDF path conversion.
 *
 * @package   SugarCommerce\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\TextPdf;

/**
 * Class Svg
 *
 * Converts an SVG `d` attribute into PDF path operators.
 *
 * This works because both formats describe shapes with the same three
 * primitives — move, line, cubic Bézier — so a logo drawn as paths
 * transfers directly, staying vector and staying sharp at any zoom. No
 * rasterising, no image embedding, no extension.
 *
 * Two differences have to be reconciled:
 *
 * **The Y axis is inverted.** SVG measures down from the top-left, PDF
 * up from the bottom-left, so every Y coordinate is subtracted from the
 * viewBox height.
 *
 * **PDF has no arc or quadratic operator.** Quadratic Béziers (`Q`/`T`)
 * are converted to cubics exactly. Elliptical arcs (`A`) cannot be
 * represented exactly and are approximated with a line, so flatten arcs
 * to curves in your editor before exporting.
 *
 * Supported: `M m L l H h V v C c S s Q q T t Z z`. Everything else is
 * skipped rather than guessed at.
 *
 * @since 1.0.0
 */
final readonly class Svg {

	/**
	 * Convert an SVG path to PDF path operators.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path_data The `d` attribute.
	 * @param float  $scale     Scale applied to every coordinate.
	 * @param float  $height    viewBox height, for the Y flip.
	 *
	 * @return string PDF operators, or '' when nothing was parseable.
	 */
	public static function to_pdf_path( string $path_data, float $scale = 1.0, float $height = 100.0 ): string {
		$commands = self::tokenize( $path_data );

		if ( array() === $commands ) {
			return '';
		}

		// Every valid path begins with a moveto. Without this check, any
		// prose containing an `a`, `h`, `t` or `c` tokenizes into
		// commands and draws arbitrary vectors — "not a path" produces
		// three curve operators rather than nothing.
		if ( 'M' !== strtoupper( $commands[0][0] ) ) {
			return '';
		}

		// A moveto needs a coordinate pair. A bare letter is text, not a
		// path.
		if ( count( $commands[0][1] ) < 2 ) {
			return '';
		}

		$out      = '';
		$x        = 0.0;
		$y        = 0.0;
		$start_x  = 0.0;
		$start_y  = 0.0;
		$last_cx  = 0.0;
		$last_cy  = 0.0;
		$previous = '';

		// Coordinates are clamped on the way out. A path carrying `1e400`
		// — or simply thirty digits — overflows to infinity, and
		// `sprintf( '%.3F' )` writes the literal `INF` into the content
		// stream. That is not a PDF number, and a reader meeting one
		// either drops the page or refuses the file.
		$px = static fn( float $v ): float => self::clamp( $v * $scale );
		$py = static fn( float $v ): float => self::clamp( ( $height - $v ) * $scale );

		foreach ( $commands as [ $letter, $args ] ) {
			$relative = $letter === strtolower( $letter );
			$upper    = strtoupper( $letter );

			// Each command repeats while arguments remain, which is how
			// "M 0 0 10 10 20 20" means a move then two lines.
			$index = 0;
			$count = count( $args );

			do {
				switch ( $upper ) {
					case 'M':
						$x = $relative ? $x + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );
						$y = $relative ? $y + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );

						$out    .= sprintf( "%.3F %.3F m\n", $px( $x ), $py( $y ) );
						$start_x = $x;
						$start_y = $y;
						$upper   = 'L';   // Subsequent pairs are lines.
						break;

					case 'L':
						$x = $relative ? $x + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );
						$y = $relative ? $y + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );

						$out .= sprintf( "%.3F %.3F l\n", $px( $x ), $py( $y ) );
						break;

					case 'H':
						$x    = $relative ? $x + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );
						$out .= sprintf( "%.3F %.3F l\n", $px( $x ), $py( $y ) );
						break;

					case 'V':
						$y    = $relative ? $y + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );
						$out .= sprintf( "%.3F %.3F l\n", $px( $x ), $py( $y ) );
						break;

					case 'C':
						$x1 = $relative ? $x + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );
						$y1 = $relative ? $y + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );
						$x2 = $relative ? $x + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );
						$y2 = $relative ? $y + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );
						$x  = $relative ? $x + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );
						$y  = $relative ? $y + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );

						$out    .= sprintf( "%.3F %.3F %.3F %.3F %.3F %.3F c\n", $px( $x1 ), $py( $y1 ), $px( $x2 ), $py( $y2 ), $px( $x ), $py( $y ) );
						$last_cx = $x2;
						$last_cy = $y2;
						break;

					case 'S':
						// Reflect the previous control point, per spec.
						$reflect = in_array( strtoupper( $previous ), array( 'C', 'S' ), true );
						$x1      = $reflect ? 2 * $x - $last_cx : $x;
						$y1      = $reflect ? 2 * $y - $last_cy : $y;

						$x2 = $relative ? $x + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );
						$y2 = $relative ? $y + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );
						$x  = $relative ? $x + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );
						$y  = $relative ? $y + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );

						$out    .= sprintf( "%.3F %.3F %.3F %.3F %.3F %.3F c\n", $px( $x1 ), $py( $y1 ), $px( $x2 ), $py( $y2 ), $px( $x ), $py( $y ) );
						$last_cx = $x2;
						$last_cy = $y2;
						break;

					case 'Q':
					case 'T':
						if ( 'Q' === $upper ) {
							$qx = $relative ? $x + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );
							$qy = $relative ? $y + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );
						} else {
							$reflect = in_array( strtoupper( $previous ), array( 'Q', 'T' ), true );
							$qx      = $reflect ? 2 * $x - $last_cx : $x;
							$qy      = $reflect ? 2 * $y - $last_cy : $y;
						}

						$ex = $relative ? $x + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );
						$ey = $relative ? $y + ( $args[ $index++ ] ?? 0 ) : ( $args[ $index++ ] ?? 0 );

						// Exact quadratic-to-cubic elevation.
						$c1x = $x + ( 2 / 3 ) * ( $qx - $x );
						$c1y = $y + ( 2 / 3 ) * ( $qy - $y );
						$c2x = $ex + ( 2 / 3 ) * ( $qx - $ex );
						$c2y = $ey + ( 2 / 3 ) * ( $qy - $ey );

						$out    .= sprintf( "%.3F %.3F %.3F %.3F %.3F %.3F c\n", $px( $c1x ), $py( $c1y ), $px( $c2x ), $py( $c2y ), $px( $ex ), $py( $ey ) );
						$last_cx = $qx;
						$last_cy = $qy;
						$x       = $ex;
						$y       = $ey;
						break;

					case 'Z':
						$out .= "h\n";
						$x    = $start_x;
						$y    = $start_y;
						break 2;

					default:
						// Unsupported command — skip rather than guess.
						break 2;
				}

				$previous = $letter;
			} while ( $index < $count );
		}

		return $out;
	}

	/**
	 * Largest coordinate written to a content stream.
	 *
	 * The PDF specification's implementation limit for a real number is
	 * ±32767 — well beyond any page, and small enough that a hostile or
	 * mis-scaled path cannot produce an operand a reader will reject.
	 */
	private const LIMIT = 32767.0;

	/**
	 * Force a coordinate into a range the format can express.
	 *
	 * @since 1.0.0
	 *
	 * @param float $value Coordinate.
	 *
	 * @return float
	 */
	private static function clamp( float $value ): float {
		if ( is_nan( $value ) ) {
			return 0.0;
		}

		return max( -self::LIMIT, min( self::LIMIT, $value ) );
	}

	/**
	 * Split a path string into command letters and their numbers.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path_data The `d` attribute.
	 *
	 * @return array<int, array{0: string, 1: float[]}>
	 */
	private static function tokenize( string $path_data ): array {
		if ( 0 === preg_match_all( '/([MmLlHhVvCcSsQqTtAaZz])([^MmLlHhVvCcSsQqTtAaZz]*)/', $path_data, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		$commands = array();

		foreach ( $matches as $match ) {
			preg_match_all( '/-?\d*\.?\d+(?:[eE][-+]?\d+)?/', $match[2], $numbers );

			$commands[] = array( $match[1], array_map( 'floatval', $numbers[0] ) );
		}

		return $commands;
	}
}
