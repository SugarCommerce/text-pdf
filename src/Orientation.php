<?php
/**
 * Page orientation.
 *
 * @package   SugarCommerce\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\TextPdf;

/**
 * Enum Orientation
 *
 * @since 1.0.0
 */
enum Orientation {

	/**
	 * Taller than wide; the paper size as listed.
	 */
	case Portrait;

	/**
	 * Wider than tall; width and height swapped.
	 */
	case Landscape;

}
