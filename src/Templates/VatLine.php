<?php
/**
 * One row of a VAT breakdown.
 *
 * @package   SugarMill\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarMill\TextPdf\Templates;

/**
 * Class VatLine
 *
 * A net amount, the rate applied to it, and the tax that produced.
 *
 * Required as soon as an invoice mixes rates — §14(1) UStG and Article
 * 226 of the VAT Directive both want the taxable amount shown per rate,
 * not just one combined tax figure. Ebooks at a reduced rate alongside
 * services at the standard rate is the usual way a digital seller ends
 * up needing this.
 *
 * @since 1.0.0
 */
final readonly class VatLine {

	/**
	 * @since 1.0.0
	 *
	 * @param string $rate   Rate as displayed, e.g. `20%` or `0%`.
	 * @param string $net    Taxable amount, formatted.
	 * @param string $vat    Tax on that amount, formatted.
	 * @param string $label  Optional description, e.g. `Reduced rate`.
	 */
	public function __construct(
		public string $rate,
		public string $net,
		public string $vat,
		public string $label = '',
	) {}
}
