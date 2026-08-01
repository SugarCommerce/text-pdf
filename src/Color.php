<?php
/**
 * RGB colour.
 *
 * @package   SugarMill\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarMill\TextPdf;

/**
 * Class Color
 *
 * PDF works in 0-1 floats per channel; this takes the 0-255 values
 * people actually have.
 *
 * @since 1.0.0
 */
final readonly class Color {

	/**
	 * @since 1.0.0
	 *
	 * @param int $red   0-255.
	 * @param int $green 0-255.
	 * @param int $blue  0-255.
	 */
	public function __construct(
		public int $red = 0,
		public int $green = 0,
		public int $blue = 0,
	) {}

	/**
	 * Build from a hex string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hex e.g. `#1a2b3c` or `1a2b3c`.
	 *
	 * @return self
	 */
	public static function hex( string $hex ): self {
		$hex = ltrim( trim( $hex ), '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 1 !== preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
			return new self();
		}

		return new self(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) )
		);
	}

	/**
	 * A shade of grey.
	 *
	 * @since 1.0.0
	 *
	 * @param int $level 0 black to 255 white.
	 *
	 * @return self
	 */
	public static function grey( int $level ): self {
		return new self( $level, $level, $level );
	}

	/**
	 * Black.
	 *
	 * @since 1.0.0
	 *
	 * @return self
	 */
	public static function black(): self {
		return new self();
	}

	/**
	 * The PDF operator arguments for this colour.
	 *
	 * @since 1.0.0
	 *
	 * @return string Three space-separated floats.
	 */
	public function operands(): string {
		return sprintf(
			'%.3F %.3F %.3F',
			max( 0, min( 255, $this->red ) ) / 255,
			max( 0, min( 255, $this->green ) ) / 255,
			max( 0, min( 255, $this->blue ) ) / 255
		);
	}

	/**
	 * Whether this is pure black, the PDF default.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_black(): bool {
		return 0 === $this->red && 0 === $this->green && 0 === $this->blue;
	}
}
