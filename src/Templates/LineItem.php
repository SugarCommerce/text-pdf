<?php
/**
 * A billable line.
 *
 * @package   SugarMill\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarMill\TextPdf\Templates;

/**
 * Class LineItem
 *
 * Amounts are pre-formatted strings rather than numbers, deliberately.
 * Formatting money correctly means knowing the currency's decimal
 * exponent and its thousands convention, and that belongs in whatever
 * owns the money — not in a PDF layout class guessing at it.
 *
 * @since 1.0.0
 */
final readonly class LineItem {

	/**
	 * @since 1.0.0
	 *
	 * @param string $description What was sold.
	 * @param string $amount      Line total, formatted.
	 * @param string $quantity    Quantity, formatted.
	 * @param string $unit_price  Price each, formatted.
	 * @param string $note        Optional second line, set smaller.
	 */
	public function __construct(
		public string $description,
		public string $amount,
		public string $quantity = '1',
		public string $unit_price = '',
		public string $note = '',
	) {}
}
