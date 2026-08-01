<?php
/**
 * Shared visual identity for the built-in templates.
 *
 * @package   SugarMill\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarMill\TextPdf\Templates;

use SugarMill\TextPdf\Color;

/**
 * Class Branding
 *
 * Everything about how a document looks, separated from what it says, so
 * one definition styles every invoice, statement and receipt you issue.
 *
 * @since 1.0.0
 */
final readonly class Branding {

	/**
	 * @since 1.0.0
	 *
	 * @param string      $name       Trading name, printed as the wordmark.
	 * @param string      $tagline    Optional line under the name.
	 * @param string      $accent     Brand colour as hex. Black by default:
	 *                                a monochrome invoice is harder to date,
	 *                                prints correctly on any device, stays
	 *                                legible when photocopied or faxed to an
	 *                                accounts department, and never clashes
	 *                                with whatever the recipient's own
	 *                                paperwork looks like.
	 * @param string|null $logo_path  SVG `d` attribute for a vector mark.
	 * @param float       $logo_box   viewBox height of that path.
	 * @param string[]    $address    Your address, one line per element.
	 * @param string[]    $footnotes  Small print for the page foot.
	 */
	public function __construct(
		public string $name,
		public string $tagline = '',
		public string $accent = '#111111',
		public ?string $logo_path = null,
		public float $logo_box = 100.0,
		public array $address = array(),
		public array $footnotes = array(),
	) {}

	/**
	 * The accent colour.
	 *
	 * @since 1.0.0
	 *
	 * @return Color
	 */
	public function accent_color(): Color {
		return Color::hex( $this->accent );
	}

	/**
	 * Secondary text colour.
	 *
	 * @since 1.0.0
	 *
	 * @return Color
	 */
	public function muted(): Color {
		return Color::grey( 115 );
	}

	/**
	 * Primary text colour.
	 *
	 * Not pure black — slightly lifted reads better in print and on
	 * screen, and is what most well-set documents actually use.
	 *
	 * @since 1.0.0
	 *
	 * @return Color
	 */
	public function ink(): Color {
		return Color::grey( 32 );
	}

	/**
	 * Faint fill for banded rows and panels.
	 *
	 * @since 1.0.0
	 *
	 * @return Color
	 */
	public function wash(): Color {
		return Color::grey( 250 );
	}

	/**
	 * Whether this branding is effectively monochrome.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_monochrome(): bool {
		$accent = $this->accent_color();

		return $accent->red === $accent->green && $accent->green === $accent->blue;
	}

	/**
	 * Hairline colour for rules and borders.
	 *
	 * @since 1.0.0
	 *
	 * @return Color
	 */
	public function hairline(): Color {
		return Color::grey( 214 );
	}
}
