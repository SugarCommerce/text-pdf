<?php
/**
 * VAT treatment and its mandatory invoice wording.
 *
 * @package   SugarMill\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarMill\TextPdf\Templates;

/**
 * Enum VatTreatment
 *
 * Where VAT is not charged at the normal domestic rate, the invoice has
 * to say why — and in most of the EU it has to say why in specific
 * words. An invoice missing that wording can be refused as evidence for
 * the recipient's input-tax deduction, which turns a formatting
 * oversight into their tax problem and your support ticket.
 *
 * Germany is the strictest common case: §14 UStG lists the mandatory
 * particulars, and §14a adds the reverse-charge note. Each case here
 * carries the customary English and German wording together, because a
 * cross-border invoice normally shows both.
 *
 * **This is not tax advice.** Treatments, thresholds and wording change,
 * and which one applies depends on facts this library cannot see — where
 * both parties belong, whether the customer is in business, whether the
 * supply is goods or services. Confirm with an accountant; this only
 * makes sure the note you chose is printed correctly and prominently.
 *
 * @since 1.0.0
 */
enum VatTreatment {

	/**
	 * Ordinary domestic VAT at the applicable rate.
	 */
	case Standard;

	/**
	 * Cross-border B2B supply where the customer accounts for the VAT.
	 *
	 * Requires both parties' VAT numbers on the invoice. Article 196 of
	 * Directive 2006/112/EC for services; §13b UStG in Germany.
	 */
	case ReverseCharge;

	/**
	 * Zero-rated intra-community supply of goods.
	 *
	 * Article 138 of Directive 2006/112/EC.
	 */
	case IntraCommunitySupply;

	/**
	 * Supply to a customer outside the EU.
	 */
	case Export;

	/**
	 * Small-business exemption — §19 UStG in Germany.
	 */
	case SmallBusiness;

	/**
	 * Exempt for another reason.
	 */
	case Exempt;

	/**
	 * The wording that must appear on the invoice.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $german Include the German text alongside the English.
	 *
	 * @return string[] One line per element, or empty for standard VAT.
	 */
	public function notes( bool $german = false ): array {
		$notes = match ( $this ) {
			self::Standard => array(),

			self::ReverseCharge => array(
				'Reverse charge: VAT to be accounted for by the recipient.',
				'Article 196, Council Directive 2006/112/EC.',
			),

			self::IntraCommunitySupply => array(
				'Zero-rated intra-community supply.',
				'Article 138, Council Directive 2006/112/EC.',
			),

			self::Export => array(
				'Zero-rated export outside the European Union.',
			),

			self::SmallBusiness => array(
				'No VAT charged under the small-business scheme.',
			),

			self::Exempt => array(
				'Exempt from VAT.',
			),
		};

		if ( ! $german || array() === $notes ) {
			return $notes;
		}

		return array_merge( $notes, match ( $this ) {
			self::ReverseCharge        => array( 'Steuerschuldnerschaft des Leistungsempfangers (§ 13b UStG).' ),
			self::IntraCommunitySupply => array( 'Steuerfreie innergemeinschaftliche Lieferung (§ 4 Nr. 1b UStG).' ),
			self::Export               => array( 'Steuerfreie Ausfuhrlieferung (§ 4 Nr. 1a UStG).' ),
			self::SmallBusiness        => array( 'Gemass § 19 UStG wird keine Umsatzsteuer berechnet.' ),
			self::Exempt               => array( 'Steuerfrei.' ),
			default                    => array(),
		} );
	}

	/**
	 * Whether both parties' VAT numbers must appear on the invoice.
	 *
	 * Mandatory for reverse charge and intra-community supply — the
	 * recipient's number is what evidences their status, and without it
	 * the zero rating is not supportable.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function requires_both_vat_numbers(): bool {
		return in_array( $this, array( self::ReverseCharge, self::IntraCommunitySupply ), true );
	}
}
