<?php
/**
 * Invoice template.
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
 * Class Invoice
 *
 * A complete invoice from data alone — no layout code at the call site.
 *
 * Everything money-shaped is a pre-formatted string. Rendering an amount
 * correctly means knowing the currency's decimal exponent, its thousands
 * convention and its symbol placement, and a layout class has no
 * business guessing at any of that. Format it where the money lives and
 * pass the result.
 *
 * Typical use:
 *
 *   $pdf = new Invoice(
 *       branding: $branding,
 *       number:   'INV-2026-0042',
 *       from:     $seller,
 *       to:       $buyer,
 *       items:    $items,
 *       totals:   [ 'Subtotal' => '£901.00', 'VAT at 20%' => '£162.18' ],
 *       total:    [ 'Total due' => '£973.08' ],
 *   );
 *
 *   $pdf->render()->save( 'invoice.pdf' );
 *
 * @since 1.0.0
 */
final readonly class Invoice {

	/**
	 * @since 1.0.0
	 *
	 * @param Branding              $branding Visual identity.
	 * @param string                $number   Invoice reference.
	 * @param Party                 $from     Who is issuing it.
	 * @param Party                 $to       Who is being billed.
	 * @param LineItem[]            $items    Billable lines.
	 * @param array<string, string> $totals   Subtotal rows: label => amount,
	 *                                        or a list of [label, amount] pairs.
	 * @param array<string, string> $total    The headline total, one entry.
	 * @param array<string, string> $details  Dates, terms, references.
	 * @param string                $notes        Payment instructions or terms.
	 * @param string                $status       Optional stamp, e.g. `PAID`.
	 * @param PageSize              $size         Paper size.
	 * @param VatTreatment          $vat          How VAT is being handled. Anything
	 *                                            other than Standard prints the
	 *                                            mandatory wording.
	 * @param VatLine[]             $vat_lines    Per-rate breakdown. Required once
	 *                                            more than one rate appears.
	 * @param string                $supply_date  Date of supply, which German law
	 *                                            requires separately from the
	 *                                            invoice date and which is the
	 *                                            particular most often left off.
	 * @param bool                  $german_notes Print the German wording as well
	 *                                            as the English.
	 */
	public function __construct(
		public Branding $branding,
		public string $number,
		public Party $from,
		public Party $to,
		public array $items,
		public array $totals = array(),
		public array $total = array(),
		public array $details = array(),
		public string $notes = '',
		public string $status = '',
		public PageSize $size = PageSize::A4,
		public VatTreatment $vat = VatTreatment::Standard,
		public array $vat_lines = array(),
		public string $supply_date = '',
		public bool $german_notes = false,
		public Translator $translator = Language::English,
	) {}

	/**
	 * Mandatory particulars that are missing.
	 *
	 * Checks the invoice against the fields §14 UStG and Article 226 of
	 * the VAT Directive require. An invoice missing any of them can be
	 * refused as evidence for the recipient's input-tax deduction — which
	 * makes it their problem and your support ticket.
	 *
	 * Not tax advice, and not exhaustive: it verifies the particulars this
	 * template can see, not whether the treatment you chose is correct.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Human-readable problems; empty when nothing is missing.
	 */
	public function compliance_warnings(): array {
		$missing = array();

		if ( '' === trim( $this->from->tax_id ) ) {
			$missing[] = 'The supplier VAT or tax number is missing.';
		}

		if ( array() === $this->from->address ) {
			$missing[] = 'The supplier address is missing.';
		}

		if ( array() === $this->to->address ) {
			$missing[] = 'The customer address is missing.';
		}

		if ( '' === trim( $this->number ) ) {
			$missing[] = 'A sequential invoice number is required.';
		}

		if ( '' === trim( $this->supply_date ) ) {
			$missing[] = 'The date of supply is required, separately from the invoice date.';
		}

		if ( $this->vat->requires_both_vat_numbers() && '' === trim( $this->to->tax_id ) ) {
			$missing[] = 'The customer VAT number is required for this VAT treatment.';
		}

		if ( count( $this->vat_lines ) > 1 ) {
			return $missing;
		}

		if ( VatTreatment::Standard === $this->vat && array() === $this->vat_lines && array() === $this->totals ) {
			$missing[] = 'The taxable amount and VAT must be shown per rate.';
		}

		return $missing;
	}

	/**
	 * Lay the invoice out.
	 *
	 * @since 1.0.0
	 *
	 * @return Document Ready to save or render.
	 */
	public function render(): Document {
		$pdf   = new Document( $this->size, Orientation::Portrait, margin: 48, font_size: 9.5, leading: 13 );
		$brand = $this->branding;

		$this->masthead( $pdf, $this->translator->translate( 'invoice', 'INVOICE' ), $this->number );
		$this->parties( $pdf );
		$this->items( $pdf );
		$this->vat_breakdown( $pdf );
		$this->totals( $pdf );
		$this->vat_notes( $pdf );
		$this->notes( $pdf );

		$translator = $this->translator;

		$pdf->on_each_page( function ( Document $doc, int $page, int $total ) use ( $brand, $translator ): void {
			if ( array() !== $brand->footnotes ) {
				$doc->line( $doc->left(), 58, $doc->right(), 58, $brand->hairline() );

				$y = 46;

				foreach ( $brand->footnotes as $note ) {
					$doc->text_at( $note, $doc->left(), $y, 7.5, Font::Helvetica, Color::grey( 150 ) );
					$y -= 10;
				}
			}

			if ( $total > 1 ) {
				$doc->text_at(
					$translator->translate( 'page', 'Page' ) . ' ' . $page . ' ' . $translator->translate( 'of', 'of' ) . ' ' . $total,
					$doc->left(),
					46,
					7.5,
					Font::Helvetica,
					Color::grey( 150 ),
					Align::Right,
					$doc->content_width()
				);
			}
		} );

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
			'Title'   => $this->translator->translate( 'invoice', 'INVOICE' ) . ' ' . $this->number,
			'Author'  => $this->from->name,
			'Subject' => 'Invoice for ' . $this->to->name,
		) );
	}

	/* ─── Sections ──────────────────────────────────────────────────── */

	/**
	 * Logo, wordmark and document title.
	 *
	 * @since 1.0.0
	 *
	 * @param Document $pdf   Target.
	 * @param string   $title Document type.
	 * @param string   $ref   Reference number.
	 *
	 * @return void
	 */
	private function masthead( Document $pdf, string $title, string $ref ): void {
		$brand = $this->branding;
		$top   = $pdf->height() - 48;
		$x     = $pdf->left();

		if ( null !== $brand->logo_path ) {
			$pdf->svg_path( $brand->logo_path, $x, $top - 34, 34 / $brand->logo_box, $brand->accent_color(), $brand->logo_box );
			$x += 46;
		}

		$pdf->text_at( strtoupper( $brand->name ), $x, $top - 14, 15, Font::HelveticaBold, $brand->accent_color() );

		if ( '' !== $brand->tagline ) {
			$pdf->text_at( $brand->tagline, $x, $top - 27, 8, Font::Helvetica, $brand->muted() );
		}

		// Title, reference and status stack down the right edge. Each
		// needs its own band — overlaying them puts the stamp across the
		// reference number, which is the one thing on an invoice a
		// customer has to quote back.
		$pdf->text_at( $title, $pdf->left(), $top - 20, 26, Font::HelveticaBold, $brand->ink(), Align::Right, $pdf->content_width() );
		$pdf->text_at( $ref, $pdf->left(), $top - 36, 9.5, Font::Helvetica, $brand->muted(), Align::Right, $pdf->content_width() );

		$rule_y = $top - 66;

		if ( '' !== $this->status ) {
			$this->stamp( $pdf, $top - 58 );
			$rule_y = $top - 78;
		}

		$pdf->move_to( $rule_y );
		$pdf->line( $pdf->left(), $pdf->cursor(), $pdf->right(), $pdf->cursor(), $brand->ink(), 0.75 );
		$pdf->move_to( $pdf->cursor() - 28 );
	}

	/**
	 * A status stamp such as PAID or OVERDUE.
	 *
	 * @since 1.0.0
	 *
	 * @param Document $pdf Target.
	 * @param float    $y   Baseline for the stamp box.
	 *
	 * @return void
	 */
	private function stamp( Document $pdf, float $y ): void {
		$brand  = $this->branding;
		$label  = strtoupper( $this->status );
		$width  = Font::HelveticaBold->width_of( $label, 8 ) + 22;
		$height = 16.0;
		$left   = $pdf->right() - $width;
		$bottom = $y - 4;

		// Outlined rather than filled: reversed-out white text on a light
		// accent is unreadable, and on a mono palette it disappears
		// entirely.
		$pdf->line( $left, $bottom, $left + $width, $bottom, $brand->ink(), 0.75 );
		$pdf->line( $left, $bottom + $height, $left + $width, $bottom + $height, $brand->ink(), 0.75 );
		$pdf->line( $left, $bottom, $left, $bottom + $height, $brand->ink(), 0.75 );
		$pdf->line( $left + $width, $bottom, $left + $width, $bottom + $height, $brand->ink(), 0.75 );

		$pdf->text_at(
			$label,
			$left,
			$bottom + ( ( $height - Font::HelveticaBold->cap_height( 8 ) ) / 2 ),
			8,
			Font::HelveticaBold,
			$brand->ink(),
			Align::Center,
			$width
		);
	}

	/**
	 * Billing parties and document details, side by side.
	 *
	 * @since 1.0.0
	 *
	 * @param Document $pdf Target.
	 *
	 * @return void
	 */
	private function parties( Document $pdf ): void {
		$brand  = $this->branding;
		$top    = $pdf->cursor();
		$column = $pdf->content_width() / 2;

		$pdf->text_at( $this->translator->translate( 'billed_to', 'BILLED TO' ), $pdf->left(), $top, 7, Font::HelveticaBold, $brand->muted() );
		$pdf->text_at( $this->to->name, $pdf->left(), $top - 16, 11, Font::HelveticaBold, $brand->ink() );

		$y = $top - 29;

		foreach ( $this->to->translated_lines( $this->translator ) as $line ) {
			$pdf->text_at( $line, $pdf->left(), $y, 9, Font::Helvetica, $brand->muted() );
			$y -= 11.5;
		}

		$detail_x = $pdf->left() + $column + 20;
		$detail_w = $column - 20;

		// The supply date is a mandatory particular in its own right and
		// is the one most often left off, so it is added rather than
		// relying on the caller to remember it.
		$details = $this->details;

		$supply_label = $this->translator->translate( 'date_of_supply', 'Date of supply' );

		if ( '' !== $this->supply_date && ! isset( $details[ $supply_label ] ) ) {
			$details[ $supply_label ] = $this->supply_date;
		}

		if ( array() !== $details ) {
			$pdf->text_at( $this->translator->translate( 'details', 'DETAILS' ), $detail_x, $top, 7, Font::HelveticaBold, $brand->muted() );

			$dy = $top - 16;

			foreach ( Rows::pairs( $details ) as [ $label, $value ] ) {
				$pdf->text_at( (string) $label, $detail_x, $dy, 9, Font::Helvetica, $brand->muted() );
				$pdf->text_at( (string) $value, $detail_x, $dy, 9, Font::HelveticaBold, $brand->ink(), Align::Right, $detail_w );
				$dy -= 13;
			}

			$y = min( $y, $dy );
		}

		$pdf->move_to( $y - 18 );
	}

	/**
	 * The line-item table.
	 *
	 * @since 1.0.0
	 *
	 * @param Document $pdf Target.
	 *
	 * @return void
	 */
	private function items( Document $pdf ): void {
		$has_unit = false;

		foreach ( $this->items as $item ) {
			if ( '' !== $item->unit_price ) {
				$has_unit = true;
				break;
			}
		}

		$headers = $has_unit
			? array(
				$this->translator->translate( 'description', 'Description' ),
				$this->translator->translate( 'quantity', 'Qty' ),
				$this->translator->translate( 'unit_price', 'Unit price' ),
				$this->translator->translate( 'amount', 'Amount' ),
			)
			: array(
				$this->translator->translate( 'description', 'Description' ),
				$this->translator->translate( 'quantity', 'Qty' ),
				$this->translator->translate( 'amount', 'Amount' ),
			);

		$table = new Table( $headers );
		$table->widths( $has_unit ? array( 0.50, 0.10, 0.20, 0.20 ) : array( 0.66, 0.14, 0.20 ) )
			->align(
				$has_unit
					? array( 1 => Align::Center, 2 => Align::Right, 3 => Align::Right )
					: array( 1 => Align::Center, 2 => Align::Right )
			)
			->row_height( 22 );

		foreach ( $this->items as $item ) {
			$description = '' !== $item->note
				? $item->description . '   ' . $item->note
				: $item->description;

			$table->row(
				$has_unit
					? array( $description, $item->quantity, $item->unit_price, $item->amount )
					: array( $description, $item->quantity, $item->amount )
			);
		}

		$table->draw( $pdf, 9, $this->branding->wash() );
	}

	/**
	 * Subtotals and the headline total.
	 *
	 * @since 1.0.0
	 *
	 * @param Document $pdf Target.
	 *
	 * @return void
	 */
	private function totals( Document $pdf ): void {
		$brand = $this->branding;
		$width = $pdf->content_width() * 0.42;
		$x     = $pdf->right() - $width;

		$pdf->gap( 14 );

		foreach ( Rows::pairs( $this->totals ) as [ $label, $value ] ) {
			$pdf->break_if_needed( 60 );
			$pdf->cell( (string) $label, $x, $width, 9, Font::Helvetica, $brand->muted() );
			$pdf->cell( (string) $value, $x, $width, 9, Font::Helvetica, $brand->ink(), Align::Right );
			$pdf->gap( 15 );
		}

		if ( array() === $this->total ) {
			return;
		}

		$pdf->gap( 4 );

		// A rule and a change of weight, rather than a filled block. It
		// reads as clearly, prints the same on any device, and cannot
		// look subtly wrong the way a band of colour with text in it can.
		$pdf->gap( 2 );
		$pdf->line( $x, $pdf->cursor(), $pdf->right(), $pdf->cursor(), $brand->ink(), 1 );
		$pdf->gap( 20 );

		foreach ( Rows::pairs( $this->total ) as [ $label, $value ] ) {
			$baseline = $pdf->cursor();

			$pdf->text_at( (string) $label, $x, $baseline, 11, Font::HelveticaBold, $brand->ink() );
			$pdf->text_at( (string) $value, $x, $baseline, 14, Font::HelveticaBold, $brand->ink(), Align::Right, $width );

			$pdf->gap( 12 );
			$pdf->line( $x, $pdf->cursor(), $pdf->right(), $pdf->cursor(), $brand->hairline(), 0.5 );
			$pdf->gap( 14 );
		}
	}

	/**
	 * Per-rate VAT breakdown.
	 *
	 * Required as soon as more than one rate appears — both §14 UStG and
	 * Article 226 want the taxable amount shown against each rate, not a
	 * single combined tax figure.
	 *
	 * @since 1.0.0
	 *
	 * @param Document $pdf Target.
	 *
	 * @return void
	 */
	private function vat_breakdown( Document $pdf ): void {
		if ( array() === $this->vat_lines ) {
			return;
		}

		$brand = $this->branding;

		$pdf->gap( 18 );
		$pdf->break_if_needed( 90 );

		$width = $pdf->content_width() * 0.55;

		$pdf->cell( $this->translator->translate( 'vat_breakdown', 'VAT BREAKDOWN' ), $pdf->left(), $width, 7, Font::HelveticaBold, $brand->muted() );
		$pdf->gap( 14 );

		$table = ( new Table( array(
			$this->translator->translate( 'rate', 'Rate' ),
			$this->translator->translate( 'net', 'Net' ),
			$this->translator->translate( 'vat', 'VAT' ),
		) ) )
			->widths( array( 0.4, 0.3, 0.3 ) )
			->align( array( 1 => Align::Right, 2 => Align::Right ) )
			->row_height( 18 );

		foreach ( $this->vat_lines as $line ) {
			$rate = '' !== $line->label ? $line->label . ' (' . $line->rate . ')' : $line->rate;

			$table->row( array( $rate, $line->net, $line->vat ) );
		}

		// Drawn in the left half so it sits beside the totals rather than
		// stretching across a page of whitespace.
		$saved = $pdf->cursor();
		$table->draw( $pdf, 8.5, $brand->wash() );
		$pdf->move_to( min( $pdf->cursor(), $saved ) );
	}

	/**
	 * The mandatory VAT wording.
	 *
	 * Printed prominently rather than buried in the small print, because
	 * its absence is what invalidates the recipient's deduction.
	 *
	 * @since 1.0.0
	 *
	 * @param Document $pdf Target.
	 *
	 * @return void
	 */
	private function vat_notes( Document $pdf ): void {
		// German wording is added automatically when the document itself
		// is German — a German-language invoice needs the German note,
		// and remembering to set both flags is a trap.
		$notes = $this->vat->notes( $this->german_notes || Language::German === $this->translator );

		if ( array() === $notes ) {
			return;
		}

		$brand  = $this->branding;
		$height = 20 + ( 12 * count( $notes ) );

		$pdf->gap( 24 );
		$pdf->break_if_needed( $height + 20 );

		$top    = $pdf->cursor() + 14;
		$bottom = $pdf->cursor() - $height + 14;

		$pdf->line( $pdf->left(), $top, $pdf->right(), $top, $brand->hairline(), 0.5 );
		$pdf->line( $pdf->left(), $bottom, $pdf->right(), $bottom, $brand->hairline(), 0.5 );

		// A heavier stub on the left edge draws the eye without needing
		// colour, which matters because this is the wording that makes
		// the invoice valid for the recipient's deduction.
		$pdf->line( $pdf->left(), $top, $pdf->left(), $bottom, $brand->ink(), 2 );

		$pdf->gap( 2 );

		foreach ( $notes as $index => $note ) {
			$pdf->cell(
				$note,
				$pdf->left() + 14,
				$pdf->content_width() - 28,
				8.5,
				0 === $index ? Font::HelveticaBold : Font::Helvetica,
				$brand->ink()
			);
			$pdf->gap( 12 );
		}
	}

	/**
	 * Payment instructions.
	 *
	 * @since 1.0.0
	 *
	 * @param Document $pdf Target.
	 *
	 * @return void
	 */
	private function notes( Document $pdf ): void {
		if ( '' === trim( $this->notes ) ) {
			return;
		}

		$pdf->gap( 26 );
		$pdf->break_if_needed( 70 );

		$brand  = $this->branding;
		$height = 22 + ( 13 * max( 1, substr_count( $this->notes, "\n" ) + 1 ) );

		$pdf->line( $pdf->left(), $pdf->cursor() + 12, $pdf->right(), $pdf->cursor() + 12, $brand->hairline(), 0.5 );
		$pdf->gap( 6 );
		$pdf->cell( $this->translator->translate( 'payment', 'PAYMENT' ), $pdf->left(), 200, 7, Font::HelveticaBold, $brand->muted() );
		$pdf->gap( 14 );

		foreach ( explode( "\n", $this->notes ) as $line ) {
			$pdf->cell( $line, $pdf->left(), $pdf->content_width(), 8.5, Font::Helvetica, $brand->ink() );
			$pdf->gap( 12 );
		}
	}
}
