<?php
/**
 * PDF document builder.
 *
 * @package   SugarCommerce\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\TextPdf;

/**
 * Class Document
 *
 * Builds invoices, statements and reports — text, tables, rules, boxes
 * and vector logos — with no dependencies and no embedded fonts.
 *
 * Uses the base-14 fonts every reader is required to have, so output is
 * a few kilobytes rather than a few hundred. Real font metrics are
 * carried (see {@see Font}), which is what makes right-aligned money
 * columns and measured table layout possible at all — padding with
 * spaces only lines up in a monospace font.
 *
 * **What it cannot do.** WinAnsi encoding covers Latin-1, so Cyrillic,
 * Greek, Hebrew, Arabic and CJK will not render. Those need an embedded
 * font, and embedding fonts is where this stops and a real PDF library
 * starts.
 *
 * @since 1.0.0
 */
final class Document {

	/**
	 * Completed page content streams.
	 *
	 * @var list<string>
	 */
	private array $pages = array();

	/**
	 * The stream currently being written.
	 */
	private string $current = '';

	/**
	 * Vertical position, in points from the page bottom.
	 */
	private float $y;

	/**
	 * Page width in points, after orientation is applied.
	 */
	private readonly float $page_width;

	/**
	 * Page height in points, after orientation is applied.
	 */
	private readonly float $page_height;

	/**
	 * Callback drawing a footer on every page.
	 *
	 * @var (callable(Document, int, int): mixed)|null
	 */
	private $footer = null;

	/**
	 * Start an empty document with the cursor at the top margin.
	 *
	 * @since 1.0.0
	 *
	 * @param PageSize    $size        Paper size. A4 by default, since
	 *                                 everywhere outside North America
	 *                                 uses it and a Letter layout prints
	 *                                 clipped on A4.
	 * @param Orientation $orientation Portrait or landscape.
	 * @param float       $margin      Margin on all sides, in points.
	 * @param float       $font_size   Default body size.
	 * @param float       $leading     Line height.
	 */
	public function __construct(
		public readonly PageSize $size = PageSize::A4,
		public readonly Orientation $orientation = Orientation::Portrait,
		public readonly float $margin = 56.0,
		public readonly float $font_size = 10.0,
		public readonly float $leading = 14.0,
	) {
		[ $width, $height ] = $size->dimensions();

		$this->page_width  = Orientation::Landscape === $orientation ? $height : $width;
		$this->page_height = Orientation::Landscape === $orientation ? $width : $height;
		$this->y           = $this->page_height - $margin;
	}

	/* ─── Geometry ──────────────────────────────────────────────────── */

	/**
	 * Width of the printable column.
	 *
	 * @since 1.0.0
	 *
	 * @return float
	 */
	public function content_width(): float {
		return $this->page_width - ( $this->margin * 2 );
	}

	/**
	 * Left edge of the printable column.
	 *
	 * @since 1.0.0
	 *
	 * @return float
	 */
	public function left(): float {
		return $this->margin;
	}

	/**
	 * Right edge of the printable column.
	 *
	 * @since 1.0.0
	 *
	 * @return float
	 */
	public function right(): float {
		return $this->page_width - $this->margin;
	}

	/**
	 * Page height in points.
	 *
	 * @since 1.0.0
	 *
	 * @return float
	 */
	public function height(): float {
		return $this->page_height;
	}

	/**
	 * Current vertical position.
	 *
	 * @since 1.0.0
	 *
	 * @return float
	 */
	public function cursor(): float {
		return $this->y;
	}

	/**
	 * Space left before the bottom margin.
	 *
	 * @since 1.0.0
	 *
	 * @return float
	 */
	public function remaining(): float {
		return $this->y - $this->margin;
	}

	/**
	 * Move the cursor down.
	 *
	 * @since 1.0.0
	 *
	 * @param float $points Distance.
	 *
	 * @return self
	 */
	public function gap( float $points ): self {
		$this->y -= $points;

		return $this;
	}

	/**
	 * Move the cursor to an absolute position.
	 *
	 * @since 1.0.0
	 *
	 * @param float $y Points from the page bottom.
	 *
	 * @return self
	 */
	public function move_to( float $y ): self {
		$this->y = $y;

		return $this;
	}

	/* ─── Pages ─────────────────────────────────────────────────────── */

	/**
	 * Start a new page.
	 *
	 * @since 1.0.0
	 *
	 * @return self
	 */
	public function page_break(): self {
		$this->pages[] = $this->current;
		$this->current = '';
		$this->y       = $this->page_height - $this->margin;

		return $this;
	}

	/**
	 * Break only if the next block would not fit.
	 *
	 * @since 1.0.0
	 *
	 * @param float $needed Height required.
	 *
	 * @return bool Whether a break happened.
	 */
	public function break_if_needed( float $needed ): bool {
		if ( $this->remaining() >= $needed ) {
			return false;
		}

		$this->page_break();

		return true;
	}

	/**
	 * Register a footer, drawn on every page at render time.
	 *
	 * Deferred rather than drawn per page because "Page 2 of 7" is not
	 * knowable until the seventh page exists.
	 *
	 * @since 1.0.0
	 *
	 * @param callable(Document, int, int): mixed $callback Receives
	 *                                                     ( Document, int $page, int $total ).
	 *
	 * @return self
	 */
	public function on_each_page( callable $callback ): self {
		$this->footer = $callback;

		return $this;
	}

	/**
	 * Pages created so far.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function page_count(): int {
		return count( $this->pages ) + ( '' !== $this->current ? 1 : 0 );
	}

	/* ─── Text ──────────────────────────────────────────────────────── */

	/**
	 * Write a line and advance the cursor.
	 *
	 * Wraps to the content width and paginates.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $text  Text.
	 * @param float|null $size  Font size, or null for the document default.
	 * @param Font       $font  Typeface.
	 * @param Color|null $color Colour.
	 * @param Align      $align Alignment within the content column.
	 *
	 * @return self
	 */
	public function text(
		string $text,
		?float $size = null,
		Font $font = Font::Helvetica,
		?Color $color = null,
		Align $align = Align::Left
	): self {
		$size ??= $this->font_size;

		foreach ( $this->wrap( $text, $font, $size, $this->content_width() ) as $line ) {
			$this->break_if_needed( $this->leading );

			// The cursor marks the TOP of the line box; PDF positions
			// text by its baseline. Drawing at the cursor puts ascenders
			// above the margin and through anything drawn above.
			$this->text_at(
				$line,
				$this->margin,
				$this->y - $font->ascender( $size ),
				$size,
				$font,
				$color,
				$align,
				$this->content_width()
			);

			$this->y -= $this->leading;
		}

		return $this;
	}

	/**
	 * Write a heading.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $text  Text.
	 * @param float|null $size  Font size.
	 * @param Color|null $color Colour.
	 *
	 * @return self
	 */
	public function heading( string $text, ?float $size = null, ?Color $color = null ): self {
		return $this->text( $text, $size ?? $this->font_size + 2, Font::HelveticaBold, $color );
	}

	/**
	 * Write text at a horizontal offset on the current line, without
	 * advancing the cursor.
	 *
	 * Baseline handling is done for you — pass the column position and
	 * the width to align within.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $text      Text.
	 * @param float      $x         Left edge of the box.
	 * @param float      $box_width Width to align within.
	 * @param float|null $size      Font size.
	 * @param Font       $font      Typeface.
	 * @param Color|null $color     Colour.
	 * @param Align      $align     Alignment.
	 *
	 * @return self
	 */
	public function cell(
		string $text,
		float $x,
		float $box_width,
		?float $size = null,
		Font $font = Font::Helvetica,
		?Color $color = null,
		Align $align = Align::Left
	): self {
		$size ??= $this->font_size;

		return $this->text_at( $text, $x, $this->y - $font->ascender( $size ), $size, $font, $color, $align, $box_width );
	}

	/**
	 * Write text vertically centred in a band starting at the cursor.
	 *
	 * The band runs from the cursor down by `$band_height`. Use it for
	 * anything drawn on a filled rectangle — totals boxes, table rows,
	 * header strips — so the text sits optically centred rather than
	 * pinned to the top.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $text        Text.
	 * @param float      $x           Left edge of the box.
	 * @param float      $box_width   Width to align within.
	 * @param float      $band_height Height of the band.
	 * @param float|null $size        Font size.
	 * @param Font       $font        Typeface.
	 * @param Color|null $color       Colour.
	 * @param Align      $align       Alignment.
	 *
	 * @return self
	 */
	public function banded(
		string $text,
		float $x,
		float $box_width,
		float $band_height,
		?float $size = null,
		Font $font = Font::Helvetica,
		?Color $color = null,
		Align $align = Align::Left
	): self {
		$size ??= $this->font_size;

		return $this->text_at(
			$text,
			$x,
			$this->y - $font->baseline_in_band( $band_height, $size ),
			$size,
			$font,
			$color,
			$align,
			$box_width
		);
	}

	/**
	 * Write text at an absolute position, leaving the cursor alone.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $text      Text.
	 * @param float      $x         Left edge of the box.
	 * @param float      $y         Baseline.
	 * @param float      $size      Font size.
	 * @param Font       $font      Typeface.
	 * @param Color|null $color     Colour.
	 * @param Align      $align     Alignment within `$box_width`.
	 * @param float      $box_width Width to align within.
	 *
	 * @return self
	 */
	public function text_at(
		string $text,
		float $x,
		float $y,
		float $size,
		Font $font = Font::Helvetica,
		?Color $color = null,
		Align $align = Align::Left,
		float $box_width = 0.0
	): self {
		if ( '' === $text ) {
			return $this;
		}

		$shown   = self::winansi( $text );
		$escaped = str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $shown );

		// Alignment is computed from the measured width — the reason the
		// font metrics exist at all. The escaping backslashes are never
		// drawn, so they are not measured.
		if ( Align::Left !== $align && $box_width > 0 ) {
			$measured = $font->width_of( $shown, $size );
			$x       += Align::Right === $align ? $box_width - $measured : ( $box_width - $measured ) / 2;
		}

		$color ??= Color::black();

		$this->current .= "q\n";

		if ( ! $color->is_black() ) {
			$this->current .= $color->operands() . " rg\n";
		}

		$this->current .= sprintf(
			"BT\n/%s %.2F Tf\n%.2F %.2F Td\n(%s) Tj\nET\nQ\n",
			self::font_resource( $font ),
			self::number( $size ),
			self::number( $x ),
			self::number( $y ),
			$escaped
		);

		return $this;
	}

	/**
	 * Write a row of columns across the content width.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $cells  Cell text.
	 * @param array<int, float>  $widths Column widths. Empty splits evenly.
	 * @param array<int, Align>  $aligns Per-column alignment.
	 * @param Font               $font   Typeface.
	 * @param float|null         $size   Font size.
	 * @param Color|null         $color  Colour.
	 *
	 * @return self
	 */
	public function columns(
		array $cells,
		array $widths = array(),
		array $aligns = array(),
		Font $font = Font::Helvetica,
		?float $size = null,
		?Color $color = null
	): self {
		$size ??= $this->font_size;
		$cells  = array_values( $cells );
		$count  = count( $cells );

		if ( 0 === $count ) {
			return $this;
		}

		if ( array() === $widths ) {
			$widths = array_fill( 0, $count, $this->content_width() / $count );
		}

		$this->break_if_needed( $this->leading );

		$x = $this->margin;

		foreach ( $cells as $index => $cell ) {
			$column = $widths[ $index ] ?? ( $this->content_width() / $count );

			$this->text_at(
				$font->truncate( (string) $cell, $column - 6, $size ),
				$x,
				$this->y - $font->ascender( $size ),
				$size,
				$font,
				$color,
				$aligns[ $index ] ?? Align::Left,
				$column - 6
			);

			$x += $column;
		}

		$this->y -= $this->leading;

		return $this;
	}

	/* ─── Vector ────────────────────────────────────────────────────── */

	/**
	 * Draw a horizontal rule at the cursor.
	 *
	 * @since 1.0.0
	 *
	 * @param Color|null $color     Colour.
	 * @param float      $thickness Line width.
	 * @param float      $spacing   Space above and below.
	 *
	 * @return self
	 */
	public function rule( ?Color $color = null, float $thickness = 0.5, float $spacing = 6.0 ): self {
		$this->y -= $spacing;
		$this->line( $this->margin, $this->y, $this->right(), $this->y, $color, $thickness );
		$this->y -= $spacing;

		return $this;
	}

	/**
	 * Draw a straight line.
	 *
	 * @since 1.0.0
	 *
	 * @param float      $x1        Start x.
	 * @param float      $y1        Start y.
	 * @param float      $x2        End x.
	 * @param float      $y2        End y.
	 * @param Color|null $color     Colour.
	 * @param float      $thickness Line width.
	 *
	 * @return self
	 */
	public function line( float $x1, float $y1, float $x2, float $y2, ?Color $color = null, float $thickness = 0.5 ): self {
		$color ??= Color::grey( 190 );

		$this->current .= sprintf(
			"q\n%s RG\n%.2F w\n%.2F %.2F m\n%.2F %.2F l\nS\nQ\n",
			$color->operands(),
			self::number( $thickness ),
			self::number( $x1 ),
			self::number( $y1 ),
			self::number( $x2 ),
			self::number( $y2 )
		);

		return $this;
	}

	/**
	 * Draw a filled rectangle.
	 *
	 * @since 1.0.0
	 *
	 * @param float      $x      Left.
	 * @param float      $y      Bottom.
	 * @param float      $width  Width.
	 * @param float      $height Height.
	 * @param Color|null $color  Fill colour.
	 *
	 * @return self
	 */
	public function rect( float $x, float $y, float $width, float $height, ?Color $color = null ): self {
		$color ??= Color::grey( 242 );

		$this->current .= sprintf(
			"q\n%s rg\n%.2F %.2F %.2F %.2F re\nf\nQ\n",
			$color->operands(),
			self::number( $x ),
			self::number( $y ),
			self::number( $width ),
			self::number( $height )
		);

		return $this;
	}

	/**
	 * Draw an SVG path as vector artwork — a logo, typically.
	 *
	 * Stays vector, so it is sharp at any zoom and adds a few hundred
	 * bytes rather than an embedded bitmap. See {@see Svg} for what
	 * converts and what does not.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $path_data  SVG `d` attribute.
	 * @param float      $x          Left position.
	 * @param float      $y          Bottom position.
	 * @param float      $scale      Scale applied to path units.
	 * @param Color|null $color      Fill colour.
	 * @param float      $svg_height viewBox height, for the Y flip.
	 *
	 * @return self
	 */
	public function svg_path(
		string $path_data,
		float $x,
		float $y,
		float $scale = 1.0,
		?Color $color = null,
		float $svg_height = 100.0
	): self {
		$operators = Svg::to_pdf_path( $path_data, $scale, $svg_height );

		if ( '' === $operators ) {
			return $this;
		}

		$color ??= Color::black();

		$this->current .= sprintf(
			"q\n%s rg\n1 0 0 1 %.2F %.2F cm\n%sf\nQ\n",
			$color->operands(),
			self::number( $x ),
			self::number( $y ),
			$operators
		);

		return $this;
	}

	/* ─── Output ────────────────────────────────────────────────────── */

	/**
	 * Serialise to PDF bytes.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string|int> $metadata Title, Author, Subject,
	 *                                            Keywords, Creator, and
	 *                                            CreationDate (a Unix
	 *                                            timestamp, since 1.1.0)
	 *                                            for the same bytes on
	 *                                            every render.
	 *
	 * @return string
	 */
	public function render( array $metadata = array() ): string {
		$pages = $this->pages;

		if ( '' !== $this->current || array() === $pages ) {
			$pages[] = $this->current;
		}

		return Writer::write( $this->apply_footers( $pages ), $this->page_width, $this->page_height, $metadata );
	}

	/**
	 * Write the document to a file.
	 *
	 * @since 1.0.0
	 *
	 * @param string                    $path     Destination.
	 * @param array<string, string|int> $metadata Document metadata, as
	 *                                            for {@see render()}.
	 *
	 * @return int Bytes written.
	 *
	 * @throws \RuntimeException When the file cannot be written.
	 */
	public function save( string $path, array $metadata = array() ): int {
		$written = @file_put_contents( $path, $this->render( $metadata ) );

		if ( false === $written ) {
			throw new \RuntimeException( 'Could not write the PDF to: ' . $path );
		}

		return $written;
	}

	/* ─── Internals ─────────────────────────────────────────────────── */

	/**
	 * Draw the registered footer onto every page.
	 *
	 * @since 1.0.0
	 *
	 * @param list<string> $pages Page streams.
	 *
	 * @return list<string>
	 */
	private function apply_footers( array $pages ): array {
		if ( null === $this->footer ) {
			return $pages;
		}

		$total    = count( $pages );
		$callback = $this->footer;
		$saved    = $this->current;
		$saved_y  = $this->y;

		foreach ( $pages as $index => $stream ) {
			$this->current = '';
			$callback( $this, $index + 1, $total );
			$pages[ $index ] = $stream . $this->current;
		}

		$this->current = $saved;
		$this->y       = $saved_y;

		return $pages;
	}

	/**
	 * Break text to fit a width, honouring embedded newlines.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text  Text.
	 * @param Font   $font  Typeface.
	 * @param float  $size  Font size.
	 * @param float  $width Available width.
	 *
	 * @return list<string>
	 */
	private function wrap( string $text, Font $font, float $size, float $width ): array {
		$lines = array();

		foreach ( explode( "\n", $text ) as $paragraph ) {
			$paragraph = rtrim( $paragraph );

			if ( '' === $paragraph ) {
				$lines[] = '';
				continue;
			}

			$line = '';

			foreach ( explode( ' ', $paragraph ) as $word ) {
				$candidate = '' === $line ? $word : $line . ' ' . $word;

				if ( $font->width_of( self::winansi( $candidate ), $size ) <= $width ) {
					$line = $candidate;
					continue;
				}

				if ( '' !== $line ) {
					$lines[] = $line;
					$line    = '';
				}

				// A single word wider than the column — a long URL, say —
				// must be cut or it runs off the page.
				if ( $font->width_of( self::winansi( $word ), $size ) > $width ) {
					$word = self::cut( $word, $font, $size, $width, $lines );
				}

				$line = $word;
			}

			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}

		return $lines;
	}

	/**
	 * Cut a word wider than the column into column-wide pieces.
	 *
	 * Walks the word once, measuring a character at a time and cutting on
	 * character boundaries. Re-measuring the remainder after every cut —
	 * the obvious loop — is quadratic, and a few hundred kilobytes of
	 * unbroken text in a customer-supplied field then holds a worker for
	 * minutes. Cutting mid-character would also break the UTF-8, and one
	 * broken sequence turns every accent on the line into `?`.
	 *
	 * @since 1.2.0
	 *
	 * @param string       $word  The word, wider than `$width`.
	 * @param Font         $font  Typeface.
	 * @param float        $size  Font size.
	 * @param float        $width Available width.
	 * @param list<string> $lines Completed lines; every full piece is
	 *                            appended here.
	 *
	 * @return string The last, partial piece, which later words may join.
	 */
	private static function cut( string $word, Font $font, float $size, float $width, array &$lines ): string {
		$piece    = '';
		$used     = 0.0;
		$measured = array();

		foreach ( self::characters( $word ) as $character ) {
			$advance = $measured[ $character ] ??= $font->width_of( self::winansi( $character ), $size );

			// Never emit an empty piece: a single character wider than the
			// column stands on a line of its own, which also guarantees
			// the walk makes progress.
			if ( '' !== $piece && $used + $advance > $width ) {
				$lines[] = $piece;
				$piece   = '';
				$used    = 0.0;
			}

			$piece .= $character;
			$used  += $advance;
		}

		return $piece;
	}

	/**
	 * The resource name for a font.
	 *
	 * @since 1.0.0
	 *
	 * @param Font $font Typeface.
	 *
	 * @return string
	 */
	private static function font_resource( Font $font ): string {
		return match ( $font ) {
			Font::Helvetica     => 'F1',
			Font::HelveticaBold => 'F2',
			Font::Courier       => 'F3',
		};
	}

	/**
	 * Force a value into a number the format can express.
	 *
	 * `sprintf( '%.2F', INF )` writes the literal `INF` into the content
	 * stream, and `NAN` writes `NAN` — neither is a PDF number, and a
	 * reader that meets one drops the page or refuses the file. A
	 * caller-supplied dimension that overflowed (a width computed from a
	 * division by zero, a coordinate scaled by a huge factor) is exactly
	 * how one gets there.
	 *
	 * The bound is the specification's implementation limit for a real
	 * number, ±32767 — far beyond any page.
	 *
	 * @since 1.0.0
	 *
	 * @param float $value Raw value.
	 *
	 * @return float
	 */
	public static function number( float $value ): float {
		if ( is_nan( $value ) ) {
			return 0.0;
		}

		return max( -32767.0, min( 32767.0, $value ) );
	}

	/**
	 * Prepare a string for a PDF text operator.
	 *
	 * Transcodes UTF-8 to Windows-1252 so £, é and — survive, removes
	 * every control character, and escapes the three bytes that would
	 * otherwise terminate the string early. An unescaped `)` produces a
	 * file no reader will open.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Raw text.
	 *
	 * @return string
	 */
	public static function escape( string $text ): string {
		return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), self::winansi( $text ) );
	}

	/**
	 * Transcode text to the bytes the page will actually show.
	 *
	 * UTF-8 to Windows-1252, with every C0 control and DEL removed but
	 * the delimiters not yet escaped. This is what gets measured: a
	 * backslash added by escaping is never drawn, so counting it pushes
	 * a right-aligned `(£5.00)` a backslash-width out of line.
	 *
	 * Invalid UTF-8 cannot be transcoded at all, so it degrades to
	 * printable ASCII with `?` for everything else rather than failing.
	 *
	 * @since 1.2.0
	 *
	 * @param string $text Raw text.
	 *
	 * @return string Windows-1252 bytes, no control characters.
	 */
	private static function winansi( string $text ): string {
		$converted = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $text );

		if ( false === $converted ) {
			$converted = (string) preg_replace( '/[^\x20-\x7E]/', '?', $text );
		}

		// Every C0 control and DEL, including CR and LF — a stray CR in a
		// customer's name would otherwise displace the rest of the line.
		return (string) preg_replace( '/[\x00-\x1F\x7F]/', '', $converted );
	}

	/**
	 * Split text into characters, or into bytes when it is not UTF-8.
	 *
	 * Cutting on a byte boundary can split a multibyte character, and one
	 * broken sequence makes the whole string invalid UTF-8 — at which
	 * point every accent in it degrades to `?`.
	 *
	 * @since 1.2.0
	 *
	 * @param string $text Text.
	 *
	 * @return list<string>
	 */
	private static function characters( string $text ): array {
		return mb_check_encoding( $text, 'UTF-8' ) ? mb_str_split( $text, 1, 'UTF-8' ) : str_split( $text );
	}
}
