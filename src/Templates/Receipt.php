<?php
/**
 * Receipt template.
 *
 * @package   SugarCommerce\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\TextPdf\Templates;

use SugarCommerce\TextPdf\{Align, Color, Document, Font, Orientation, PageSize, Table};

/**
 * Class Receipt
 *
 * Confirmation that something has been paid — narrower and calmer than
 * an invoice, because nobody needs to act on it.
 *
 * The distinction matters more than it looks: an invoice is a demand and
 * a receipt is a record, and sending an invoice-shaped document after
 * payment reliably produces "have I already paid this?" support tickets.
 *
 * Defaults to A5, which suits a document with a handful of lines and
 * looks deliberate rather than like an invoice with the middle missing.
 *
 * @since 1.0.0
 */
final readonly class Receipt {

	/**
	 * Describe a receipt.
	 *
	 * @since 1.0.0
	 *
	 * @param Branding              $branding   Visual identity.
	 * @param string                $number     Receipt reference.
	 * @param Party                 $to         Who paid.
	 * @param array<int, LineItem>  $items      What was bought.
	 * @param string                $total      Amount paid, formatted.
	 * @param array<string, string> $details    Date, method, order reference.
	 * @param array<string, string> $breakdown  Subtotal and tax rows.
	 * @param string                $notes      Anything else worth saying.
	 * @param PageSize              $size       Paper size.
	 * @param Translator            $translator Source of the template's own
	 *                                          wording.
	 */
	public function __construct(
		public Branding $branding,
		public string $number,
		public Party $to,
		public array $items,
		public string $total,
		public array $details = array(),
		public array $breakdown = array(),
		public string $notes = '',
		public PageSize $size = PageSize::A5,
		public Translator $translator = Language::English,
	) {}

	/**
	 * Lay the receipt out.
	 *
	 * @since 1.0.0
	 *
	 * @return Document
	 */
	public function render(): Document {
		$pdf   = new Document( $this->size, Orientation::Portrait, margin: 40, font_size: 9, leading: 12 );
		$brand = $this->branding;
		$top   = $pdf->height() - 40;

		if ( null !== $brand->logo_path ) {
			$pdf->svg_path( $brand->logo_path, $pdf->left(), $top - 26, 26 / $brand->logo_box, $brand->ink(), $brand->logo_box );
		}

		$x = null !== $brand->logo_path ? $pdf->left() + 36 : $pdf->left();

		$pdf->text_at( strtoupper( $brand->name ), $x, $top - 12, 12, Font::HelveticaBold, $brand->ink() );
		$pdf->text_at( $this->translator->translate( 'receipt', 'RECEIPT' ), $pdf->left(), $top - 12, 15, Font::HelveticaBold, $brand->ink(), Align::Right, $pdf->content_width() );
		$pdf->text_at( $this->number, $pdf->left(), $top - 25, 8.5, Font::Helvetica, $brand->muted(), Align::Right, $pdf->content_width() );

		$pdf->move_to( $top - 44 );
		$pdf->line( $pdf->left(), $pdf->cursor(), $pdf->right(), $pdf->cursor(), $brand->ink(), 0.75 );
		$pdf->move_to( $pdf->cursor() - 22 );

		// A receipt says "paid" plainly. Anything less definite gets read
		// as another request for money.
		$pdf->text( $this->translator->translate( 'paid_in_full', 'PAID IN FULL' ), 10, Font::HelveticaBold, $brand->ink() );
		$pdf->gap( 8 );

		$pdf->text( $this->to->name, 10, Font::HelveticaBold, $brand->ink() );

		foreach ( $this->to->translated_lines( $this->translator ) as $line ) {
			$pdf->text( $line, 8.5, Font::Helvetica, $brand->muted() );
		}

		if ( array() !== $this->details ) {
			$pdf->gap( 10 );

			foreach ( Rows::pairs( $this->details ) as [ $label, $value ] ) {
				$baseline = $pdf->cursor();

				$pdf->text_at( (string) $label, $pdf->left(), $baseline - 7, 8.5, Font::Helvetica, $brand->muted() );
				$pdf->text_at( (string) $value, $pdf->left(), $baseline - 7, 8.5, Font::HelveticaBold, $brand->ink(), Align::Right, $pdf->content_width() );

				$pdf->gap( 12 );
			}
		}

		$pdf->gap( 12 );

		$table = ( new Table( array( $this->translator->translate( 'description', 'Description' ), $this->translator->translate( 'amount', 'Amount' ) ) ) )
			->widths( array( 0.72, 0.28 ) )
			->align( array( 1 => Align::Right ) )
			->row_height( 18 );

		foreach ( $this->items as $item ) {
			$description = '' !== $item->note ? $item->description . '   ' . $item->note : $item->description;
			$quantity    = '1' !== $item->quantity ? ' x' . $item->quantity : '';

			$table->row( array( $description . $quantity, $item->amount ) );
		}

		$table->draw( $pdf, 8.5 );

		$width = $pdf->content_width() * 0.5;
		$x     = $pdf->right() - $width;

		$pdf->gap( 12 );

		foreach ( Rows::pairs( $this->breakdown ) as [ $label, $value ] ) {
			$baseline = $pdf->cursor();

			$pdf->text_at( (string) $label, $x, $baseline, 8.5, Font::Helvetica, $brand->muted() );
			$pdf->text_at( (string) $value, $x, $baseline, 8.5, Font::Helvetica, $brand->ink(), Align::Right, $width );

			$pdf->gap( 13 );
		}

		$pdf->gap( 2 );
		$pdf->line( $x, $pdf->cursor(), $pdf->right(), $pdf->cursor(), $brand->ink(), 1 );
		$pdf->gap( 18 );

		$baseline = $pdf->cursor();
		$pdf->text_at( $this->translator->translate( 'paid', 'Paid' ), $x, $baseline, 10.5, Font::HelveticaBold, $brand->ink() );
		$pdf->text_at( $this->total, $x, $baseline, 13, Font::HelveticaBold, $brand->ink(), Align::Right, $width );

		if ( '' !== trim( $this->notes ) ) {
			$pdf->gap( 26 );
			$pdf->line( $pdf->left(), $pdf->cursor() + 12, $pdf->right(), $pdf->cursor() + 12, $brand->hairline(), 0.5 );
			$pdf->gap( 6 );
			$pdf->text( $this->notes, 8, Font::Helvetica, $brand->muted() );
		}

		return $pdf;
	}

	/**
	 * Save straight to a file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Destination.
	 *
	 * @return int Bytes written.
	 */
	public function save( string $path ): int {
		return $this->render()->save( $path, array(
			'Title'  => $this->translator->translate( 'receipt', 'RECEIPT' ) . ' ' . $this->number,
			'Author' => $this->branding->name,
		) );
	}
}
