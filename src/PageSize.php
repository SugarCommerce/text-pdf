<?php
/**
 * Page dimensions.
 *
 * @package   SugarCommerce\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\TextPdf;

/**
 * Enum PageSize
 *
 * Dimensions in PDF points, at 72 to the inch.
 *
 * A4 is the default because everywhere except the United States and
 * Canada uses it, and an invoice laid out for Letter prints with a
 * clipped footer on A4 paper.
 *
 * @since 1.0.0
 */
enum PageSize: string {

	case A4     = 'A4';
	case A5     = 'A5';
	case Letter = 'Letter';
	case Legal  = 'Legal';

	/**
	 * Width and height in points, portrait.
	 *
	 * @since 1.0.0
	 *
	 * @return array{0: float, 1: float}
	 */
	public function dimensions(): array {
		return match ( $this ) {
			self::A4     => array( 595.28, 841.89 ),
			self::A5     => array( 419.53, 595.28 ),
			self::Letter => array( 612.0, 792.0 ),
			self::Legal  => array( 612.0, 1008.0 ),
		};
	}
}
