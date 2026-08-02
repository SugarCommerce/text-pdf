<?php
/**
 * Statement of account template.
 *
 * @package   Sugar\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace Sugar\TextPdf\Templates;

use Sugar\TextPdf\{Align, Color, Document, Font, Orientation, PageSize, Table};

/**
 * Class Statement
 *
 * A running account over a period: transactions, a closing balance, and
 * headers that repeat on every page.
 *
 * The repeating header is the whole point. A statement is usually the
 * document that runs long, and a column of unlabelled figures on page
 * three is the most common way a generated statement is unusable.
 *
 * Typical use:
 *
 *   ( new Statement(
 *       branding: $branding,
 *       account:  $customer,
 *       period:   '1 July - 31 July 2026',
 *       rows:     $rows,
 *       closing:  [ 'Closing balance' => '£7,505.00' ],
 *   ) )->save( 'statement.pdf' );
 *
 * @since 1.0.0
 */
final readonly class Statement {

	/**
	 * @since 1.0.0
	 *
	 * @param Branding                       $branding    Visual identity.
	 * @param Party                          $account     Whose account it is.
	 * @param string                         $period      Period covered.
	 * @param array<int, array<int, string>> $rows        Transaction rows.
	 * @param array<string, string>          $closing     Closing figures.
	 * @param string[]                       $headers     Column headings.
	 * @param float[]                        $widths      Column width fractions.
	 * @param array<int, Align>              $aligns      Column alignment.
	 * @param array<string, string>          $summary     Opening balance, credit
	 *                                                    limit, and similar.
	 * @param string                         $reference   Account or statement number.
	 * @param PageSize                       $size        Paper size.
	 * @param Orientation                    $orientation Portrait or landscape.
	 */
	public function __construct(
		public Branding $branding,
		public Party $account,
		public string $period,
		public array $rows,
		public array $closing = array(),
		public array $headers = array(),
		public array $widths = array( 0.14, 0.36, 0.18, 0.16, 0.16 ),
		public array $aligns = array( 3 => Align::Right, 4 => Align::Right ),
		public array $summary = array(),
		public string $reference = '',
		public PageSize $size = PageSize::A4,
		public Orientation $orientation = Orientation::Portrait,
		public Translator $translator = Language::English,
	) {}

	/**
	 * Column headings, translated unless overridden.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	private function column_headers(): array {
		if ( array() !== $this->headers ) {
			return $this->headers;
		}

		return array(
			$this->translator->translate( 'date', 'Date' ),
			$this->translator->translate( 'description', 'Description' ),
			$this->translator->translate( 'reference', 'Reference' ),
			$this->translator->translate( 'amount', 'Amount' ),
			$this->translator->translate( 'balance', 'Balance' ),
		);
	}

	/**
	 * Lay the statement out.
	 *
	 * @since 1.0.0
	 *
	 * @return Document
	 */
	public function render(): Document {
		$pdf   = new Document( $this->size, $this->orientation, margin: 48, font_size: 9, leading: 12.5 );
		$brand = $this->branding;

		$this->masthead( $pdf );
		$this->account_block( $pdf );

		$table = ( new Table( $this->column_headers() ) )
			->widths( $this->widths )
			->align( $this->aligns )
			->row_height( 17 );

		$table->rows( $this->rows )->draw( $pdf, 8.5 );

		$this->closing( $pdf );

		$translator = $this->translator;

		$pdf->on_each_page( function ( Document $doc, int $page, int $total ) use ( $brand, $translator ): void {
			$doc->line( $doc->left(), 54, $doc->right(), 54, $brand->hairline(), 0.5 );

			$doc->text_at( $brand->name . ' — statement of account', $doc->left(), 42, 7.5, Font::Helvetica, Color::grey( 145 ) );
			$doc->text_at(
				$translator->translate( 'page', 'Page' ) . ' ' . $page . ' ' . $translator->translate( 'of', 'of' ) . ' ' . $total,
				$doc->left(),
				42,
				7.5,
				Font::Helvetica,
				Color::grey( 145 ),
				Align::Right,
				$doc->content_width()
			);
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
			'Title'   => $this->translator->translate( 'statement', 'STATEMENT' ) . ' — ' . $this->period,
			'Author'  => $this->branding->name,
			'Subject' => 'Statement for ' . $this->account->name,
		) );
	}

	/**
	 * Logo, wordmark and title.
	 *
	 * @since 1.0.0
	 *
	 * @param Document $pdf Target.
	 *
	 * @return void
	 */
	private function masthead( Document $pdf ): void {
		$brand = $this->branding;
		$top   = $pdf->height() - 48;
		$x     = $pdf->left();

		if ( null !== $brand->logo_path ) {
			$pdf->svg_path( $brand->logo_path, $x, $top - 30, 30 / $brand->logo_box, $brand->ink(), $brand->logo_box );
			$x += 42;
		}

		$pdf->text_at( strtoupper( $brand->name ), $x, $top - 13, 13, Font::HelveticaBold, $brand->ink() );

		if ( '' !== $brand->tagline ) {
			$pdf->text_at( $brand->tagline, $x, $top - 25, 7.5, Font::Helvetica, $brand->muted() );
		}

		$pdf->text_at( $this->translator->translate( 'statement', 'STATEMENT' ), $pdf->left(), $top - 18, 20, Font::HelveticaBold, $brand->ink(), Align::Right, $pdf->content_width() );
		$pdf->text_at( $this->period, $pdf->left(), $top - 32, 9, Font::Helvetica, $brand->muted(), Align::Right, $pdf->content_width() );

		$pdf->move_to( $top - 56 );
		$pdf->line( $pdf->left(), $pdf->cursor(), $pdf->right(), $pdf->cursor(), $brand->ink(), 0.75 );
		$pdf->move_to( $pdf->cursor() - 26 );
	}

	/**
	 * Who the statement is for, and any opening figures.
	 *
	 * @since 1.0.0
	 *
	 * @param Document $pdf Target.
	 *
	 * @return void
	 */
	private function account_block( Document $pdf ): void {
		$brand = $this->branding;
		$top   = $pdf->cursor();

		$pdf->text_at( $this->translator->translate( 'account', 'ACCOUNT' ), $pdf->left(), $top, 7, Font::HelveticaBold, $brand->muted() );
		$pdf->text_at( $this->account->name, $pdf->left(), $top - 15, 11, Font::HelveticaBold, $brand->ink() );

		$y = $top - 28;

		foreach ( $this->account->translated_lines( $this->translator ) as $line ) {
			$pdf->text_at( $line, $pdf->left(), $y, 8.5, Font::Helvetica, $brand->muted() );
			$y -= 11;
		}

		if ( '' !== $this->reference ) {
			$pdf->text_at( $this->reference, $pdf->left(), $y, 8.5, Font::Helvetica, $brand->muted() );
			$y -= 11;
		}

		if ( array() !== $this->summary ) {
			$column = $pdf->content_width() / 2;
			$dy     = $top - 15;

			foreach ( Rows::pairs( $this->summary ) as [ $label, $value ] ) {
				$pdf->text_at( (string) $label, $pdf->left() + $column + 20, $dy, 8.5, Font::Helvetica, $brand->muted() );
				$pdf->text_at( (string) $value, $pdf->left() + $column + 20, $dy, 8.5, Font::HelveticaBold, $brand->ink(), Align::Right, $column - 20 );
				$dy -= 12;
			}

			$y = min( $y, $dy );
		}

		$pdf->move_to( $y - 16 );
	}

	/**
	 * Closing figures.
	 *
	 * @since 1.0.0
	 *
	 * @param Document $pdf Target.
	 *
	 * @return void
	 */
	private function closing( Document $pdf ): void {
		if ( array() === $this->closing ) {
			return;
		}

		$brand = $this->branding;
		$width = $pdf->content_width() * 0.45;
		$x     = $pdf->right() - $width;

		$pdf->gap( 10 );
		$pdf->break_if_needed( 70 );
		$pdf->line( $x, $pdf->cursor(), $pdf->right(), $pdf->cursor(), $brand->ink(), 1 );
		$pdf->gap( 20 );

		foreach ( Rows::pairs( $this->closing ) as [ $label, $value ] ) {
			$baseline = $pdf->cursor();

			$pdf->text_at( (string) $label, $x, $baseline, 10.5, Font::HelveticaBold, $brand->ink() );
			$pdf->text_at( (string) $value, $x, $baseline, 13, Font::HelveticaBold, $brand->ink(), Align::Right, $width );

			$pdf->gap( 12 );
			$pdf->line( $x, $pdf->cursor(), $pdf->right(), $pdf->cursor(), $brand->hairline(), 0.5 );
			$pdf->gap( 14 );
		}
	}
}
