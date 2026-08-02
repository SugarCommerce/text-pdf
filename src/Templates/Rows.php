<?php
/**
 * Label/value row normalisation.
 *
 * @package   Sugar\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace Sugar\TextPdf\Templates;

/**
 * Class Rows
 *
 * Every template takes several label/value blocks — totals, details,
 * a breakdown, closing figures — and the natural way to write one is a
 * map:
 *
 *   totals: array( 'Subtotal' => '1,240.00', 'VAT' => '0.00' )
 *
 * The equally natural way is a list of pairs, and that is what a caller
 * building rows in a loop tends to produce:
 *
 *   totals: array( array( 'Subtotal', '1,240.00' ), array( 'VAT', '0.00' ) )
 *
 * Iterating the second shape as `$label => $value` yields integer keys
 * and array values, so PHP emits an "Array to string conversion" notice
 * and the word `Array` is printed onto the invoice. On a financial
 * document that is silent corruption, and it is worth a few lines to
 * make impossible.
 *
 * The list form also allows a repeated label — two `Discount` lines —
 * which a map cannot express at all.
 *
 * @since 1.0.0
 */
final readonly class Rows {

	/**
	 * Normalise either shape into a list of pairs.
	 *
	 * @since 1.0.0
	 *
	 * @param array<mixed, mixed> $rows Map of label => value, or a list
	 *                                  of [label, value] pairs.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	public static function pairs( array $rows ): array {
		$pairs = array();

		foreach ( $rows as $key => $value ) {
			if ( is_array( $value ) ) {
				$pairs[] = array( self::text( $value[0] ?? '' ), self::text( $value[1] ?? '' ) );

				continue;
			}

			$pairs[] = array( self::text( $key ), self::text( $value ) );
		}

		return $pairs;
	}

	/**
	 * Coerce a cell to a string without tripping over an array.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw cell.
	 *
	 * @return string
	 */
	private static function text( mixed $value ): string {
		return match ( true ) {
			is_string( $value )                                     => $value,
			is_scalar( $value )                                     => (string) $value,
			$value instanceof \Stringable                           => (string) $value,
			default                                                 => '',
		};
	}
}
