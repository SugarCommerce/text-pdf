<?php
/**
 * Horizontal alignment.
 *
 * @package   Sugar\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace Sugar\TextPdf;

/**
 * Enum Align
 *
 * Right alignment is the one that matters. A column of money read down
 * the page is only legible when the decimal points line up, and in a
 * proportional font that cannot be faked with padding — it needs the
 * string measured and the start position computed.
 *
 * @since 1.0.0
 */
enum Align {

	case Left;
	case Right;
	case Center;
}
