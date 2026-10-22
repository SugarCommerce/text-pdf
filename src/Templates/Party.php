<?php
/**
 * A billed or billing party.
 *
 * @package   SugarCommerce\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\TextPdf\Templates;

/**
 * Class Party
 *
 * @since 1.0.0
 */
final readonly class Party {

	/**
	 * Describe a party to the document.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $name    Company or person.
	 * @param array<int, string> $address Address lines.
	 * @param string   $email   Contact address.
	 * @param string   $tax_id  VAT or tax registration number.
	 */
	public function __construct(
		public string $name,
		public array $address = array(),
		public string $email = '',
		public string $tax_id = '',
	) {}

	/**
	 * The address block, translated.
	 *
	 * @since 1.0.0
	 *
	 * @param Translator $translator Wording source.
	 *
	 * @return list<string>
	 */
	public function translated_lines( Translator $translator ): array {
		$lines = $this->address;

		if ( '' !== $this->email ) {
			$lines[] = $this->email;
		}

		if ( '' !== $this->tax_id ) {
			$lines[] = $translator->translate( 'vat', 'VAT' ) . ' ' . $this->tax_id;
		}

		return array_values( array_filter( $lines, static fn( $line ): bool => '' !== trim( (string) $line ) ) );
	}

	/**
	 * The address block, one string per line.
	 *
	 * @since 1.0.0
	 *
	 * @return list<string>
	 */
	public function lines(): array {
		return $this->translated_lines( Language::English );
	}
}
