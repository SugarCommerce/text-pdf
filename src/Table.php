<?php
/**
 * Tabular layout with pagination.
 *
 * @package   SugarCommerce\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\TextPdf;

/**
 * Class Table
 *
 * Column layout with measured widths, per-column alignment, zebra
 * striping and — the part that matters for a statement — headers that
 * repeat on every page.
 *
 * A three-page statement whose column headings appear only on page one
 * is unreadable from page two onward, and that is the default behaviour
 * of every hand-rolled table loop.
 *
 * Typical use:
 *
 *   ( new Table( [ 'Description', 'Qty', 'Amount' ] ) )
 *       ->widths( [ 0.6, 0.15, 0.25 ] )
 *       ->align( [ 2 => Align::Right, 1 => Align::Center ] )
 *       ->rows( $rows )
 *       ->draw( $pdf );
 *
 * @since 1.0.0
 */
final class Table {

	/**
	 * Body rows.
	 *
	 * @var array<int, array<int, string>>
	 */
	private array $rows = array();

	/**
	 * Column widths as fractions of the content width.
	 *
	 * @var list<float>
	 */
	private array $widths = array();

	/**
	 * Per-column alignment.
	 *
	 * @var array<int, Align>
	 */
	private array $aligns = array();

	/**
	 * Whether alternate rows are shaded.
	 */
	private bool $striped = false;

	/**
	 * Row height in points.
	 */
	private float $row_height = 18.0;

	/**
	 * Start a table with optional column headings.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $headers Column headings.
	 */
	public function __construct( private readonly array $headers = array() ) {}

	/**
	 * Set column widths as fractions of the content width.
	 *
	 * Fractions rather than points, so the same table lays out correctly
	 * on A4 and Letter without being redefined.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, float> $fractions Values summing to roughly 1.0.
	 *
	 * @return self
	 */
	public function widths( array $fractions ): self {
		$this->widths = array_values( $fractions );

		return $this;
	}

	/**
	 * Set per-column alignment.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, Align> $aligns Column index => alignment.
	 *
	 * @return self
	 */
	public function align( array $aligns ): self {
		$this->aligns = $aligns;

		return $this;
	}

	/**
	 * Add a row.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $cells Cell values.
	 *
	 * @return self
	 */
	public function row( array $cells ): self {
		$this->rows[] = array_values( array_map( 'strval', $cells ) );

		return $this;
	}

	/**
	 * Add several rows.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<int, string>> $rows Rows.
	 *
	 * @return self
	 */
	public function rows( array $rows ): self {
		foreach ( $rows as $cells ) {
			$this->row( (array) $cells );
		}

		return $this;
	}

	/**
	 * Shade alternate rows.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $enabled Whether to stripe.
	 *
	 * @return self
	 */
	public function striped( bool $enabled = true ): self {
		$this->striped = $enabled;

		return $this;
	}

	/**
	 * Set the row height.
	 *
	 * @since 1.0.0
	 *
	 * @param float $points Height.
	 *
	 * @return self
	 */
	public function row_height( float $points ): self {
		$this->row_height = $points;

		return $this;
	}

	/**
	 * How many rows the table holds.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->rows );
	}

	/**
	 * Draw the table, paginating and repeating headers as needed.
	 *
	 * @since 1.0.0
	 *
	 * @param Document   $pdf         Target document.
	 * @param float|null $size        Font size.
	 * @param Color|null $header_fill Header background.
	 *
	 * @return Document The document, for chaining.
	 */
	public function draw( Document $pdf, ?float $size = null, ?Color $header_fill = null ): Document {
		$size  ??= $pdf->font_size;
		$widths  = $this->resolve_widths( $pdf->content_width() );

		$this->draw_header( $pdf, $widths, $size, $header_fill );

		foreach ( $this->rows as $index => $cells ) {
			// Repeat the header whenever the table spills onto a new page,
			// or everything past page one is a column of unlabelled
			// numbers.
			if ( $pdf->remaining() < $this->row_height * 2 ) {
				$pdf->page_break();
				$this->draw_header( $pdf, $widths, $size, $header_fill );
			}

			if ( $this->striped && 1 === $index % 2 ) {
				$pdf->rect(
					$pdf->left(),
					$pdf->cursor() - $this->row_height,
					$pdf->content_width(),
					$this->row_height,
					Color::grey( 248 )
				);
			}

			$this->draw_row( $pdf, $cells, $widths, $size, Font::Helvetica, null );
		}

		return $pdf;
	}

	/**
	 * Draw the header band.
	 *
	 * @since 1.0.0
	 *
	 * @param Document   $pdf    Target document.
	 * @param list<float> $widths Resolved column widths in points.
	 * @param float      $size   Font size.
	 * @param Color|null $fill   Background colour.
	 *
	 * @return void
	 */
	private function draw_header( Document $pdf, array $widths, float $size, ?Color $fill ): void {
		if ( array() === $this->headers ) {
			return;
		}

		if ( null !== $fill ) {
			$pdf->rect( $pdf->left(), $pdf->cursor() - $this->row_height, $pdf->content_width(), $this->row_height, $fill );
		}

		$top = $pdf->cursor();

		$this->draw_row( $pdf, $this->headers, $widths, $size, Font::HelveticaBold, Color::grey( 30 ) );

		// Rules above and below the heading row read as a header without
		// needing a filled band, and survive being printed in mono.
		$pdf->line( $pdf->left(), $top, $pdf->right(), $top, Color::grey( 60 ), 0.75 );
		$pdf->line( $pdf->left(), $pdf->cursor(), $pdf->right(), $pdf->cursor(), Color::grey( 60 ), 0.75 );
	}

	/**
	 * Draw one row of cells.
	 *
	 * @since 1.0.0
	 *
	 * @param Document   $pdf    Target document.
	 * @param array<int, string> $cells  Cell values.
	 * @param list<float>        $widths Column widths in points.
	 * @param float      $size   Font size.
	 * @param Font       $font   Typeface.
	 * @param Color|null $color  Text colour.
	 *
	 * @return void
	 */
	private function draw_row( Document $pdf, array $cells, array $widths, float $size, Font $font, ?Color $color ): void {
		$x = $pdf->left();
		$y = $pdf->cursor();

		foreach ( array_values( $cells ) as $index => $cell ) {
			$width = $widths[ $index ] ?? 0;

			if ( $width <= 0 ) {
				continue;
			}

			$align   = $this->aligns[ $index ] ?? Align::Left;
			$padding = 6.0;

			$pdf->text_at(
				$font->truncate( (string) $cell, $width - ( $padding * 2 ), $size ),
				$x + $padding,
				$y - $font->baseline_in_band( $this->row_height, $size ),
				$size,
				$font,
				$color,
				$align,
				$width - ( $padding * 2 )
			);

			$x += $width;
		}

		$pdf->gap( $this->row_height );
	}

	/**
	 * Turn width fractions into points.
	 *
	 * @since 1.0.0
	 *
	 * @param float $available Content width in points.
	 *
	 * @return list<float>
	 */
	private function resolve_widths( float $available ): array {
		$columns = max( count( $this->headers ), array() === $this->rows ? 0 : count( $this->rows[0] ) );

		if ( 0 === $columns ) {
			return array();
		}

		if ( array() === $this->widths ) {
			return array_fill( 0, $columns, $available / $columns );
		}

		$total = array_sum( $this->widths );

		if ( $total <= 0 ) {
			return array_fill( 0, $columns, $available / $columns );
		}

		return array_map(
			static fn( float $fraction ): float => ( $fraction / $total ) * $available,
			$this->widths
		);
	}
}
