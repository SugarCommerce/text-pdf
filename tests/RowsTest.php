<?php
/**
 * Label/value row normalisation.
 *
 * @package   SugarCommerce\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\TextPdf\Tests;

use PHPUnit\Framework\TestCase;
use SugarCommerce\TextPdf\Templates\Branding;
use SugarCommerce\TextPdf\Templates\Invoice;
use SugarCommerce\TextPdf\Templates\LineItem;
use SugarCommerce\TextPdf\Templates\Party;
use SugarCommerce\TextPdf\Templates\Rows;

/**
 * A totals block written as a list of pairs used to iterate as
 * `$index => $array`, printing the word `Array` onto the invoice. Silent
 * corruption of a financial document is worth a regression test.
 */
final class RowsTest extends TestCase {

	public function test_a_map_becomes_pairs(): void {
		$this->assertSame(
			array( array( 'Subtotal', '10.00' ), array( 'VAT', '2.30' ) ),
			Rows::pairs( array( 'Subtotal' => '10.00', 'VAT' => '2.30' ) )
		);
	}

	public function test_a_list_of_pairs_passes_through(): void {
		$this->assertSame(
			array( array( 'Subtotal', '10.00' ), array( 'VAT', '2.30' ) ),
			Rows::pairs( array( array( 'Subtotal', '10.00' ), array( 'VAT', '2.30' ) ) )
		);
	}

	/**
	 * A map cannot express this at all — two discount lines collapse to
	 * one.
	 */
	public function test_the_list_form_allows_a_repeated_label(): void {
		$pairs = Rows::pairs( array( array( 'Discount', '-1.00' ), array( 'Discount', '-2.00' ) ) );

		$this->assertCount( 2, $pairs );
	}

	public function test_scalars_are_coerced(): void {
		$this->assertSame(
			array( array( 'Quantity', '3' ), array( 'Paid', '1' ) ),
			Rows::pairs( array( 'Quantity' => 3, 'Paid' => true ) )
		);
	}

	public function test_a_nested_array_never_becomes_the_word_array(): void {
		$this->assertSame( array( array( '', '' ) ), Rows::pairs( array( array( array(), array() ) ) ) );
	}

	public function test_stringable_values_are_used(): void {
		$value = new class() implements \Stringable {
			public function __toString(): string {
				return '£9.99';
			}
		};

		$this->assertSame( array( array( 'Total', '£9.99' ) ), Rows::pairs( array( 'Total' => $value ) ) );
	}

	public function test_empty_stays_empty(): void {
		$this->assertSame( array(), Rows::pairs( array() ) );
	}

	/**
	 * Both shapes must produce the same document, byte for byte apart
	 * from the timestamp.
	 */
	public function test_both_shapes_render_identically(): void {
		$map  = $this->invoice( array( 'Subtotal' => '1,240.00' ), array( 'Total due' => '1,240.00' ) );
		$list = $this->invoice( array( array( 'Subtotal', '1,240.00' ) ), array( array( 'Total due', '1,240.00' ) ) );

		$this->assertSame(
			$this->without_timestamp( $map ),
			$this->without_timestamp( $list )
		);
	}

	public function test_rendering_a_list_emits_no_warning(): void {
		set_error_handler(
			static function ( int $severity, string $message ): bool {
				throw new \ErrorException( $message, 0, $severity );
			}
		);

		try {
			$pdf = $this->invoice( array( array( 'Subtotal', '1,240.00' ) ), array( array( 'Total due', '1,240.00' ) ) );

			$this->assertStringNotContainsString( '(Array)', $pdf );
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * Render a minimal invoice with the given totals blocks.
	 *
	 * @param array<mixed, mixed> $totals Subtotal rows.
	 * @param array<mixed, mixed> $total  Headline total.
	 */
	private function invoice( array $totals, array $total ): string {
		return ( new Invoice(
			branding: new Branding( name: 'Acme Ltd' ),
			number: 'INV-1',
			from: new Party( 'Acme Ltd' ),
			to: new Party( 'Kunde GmbH' ),
			items: array( new LineItem( 'Annual licence', '1,240.00' ) ),
			totals: $totals,
			total: $total,
		) )->render()->render();
	}

	/**
	 * Strip the creation date, which differs between two renders.
	 */
	private function without_timestamp( string $pdf ): string {
		return (string) preg_replace( '/\/CreationDate \(D:[^)]*\)/', '', $pdf );
	}
}
