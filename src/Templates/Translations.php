<?php
/**
 * Adapter from any translation system to the template contract.
 *
 * @package   SugarCommerce\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\TextPdf\Templates;

/**
 * Class Translations
 *
 * Wraps a callable, so an existing translation system becomes a
 * {@see Translator} in one line and the strings in your PDFs go through
 * the same workflow as everything else you translate.
 *
 *   // WordPress — looks up the English source, as gettext does.
 *   $translator = new Translations(
 *       fn( string $key, string $default ): string => __( $default, 'my-plugin' )
 *   );
 *
 *   // Laravel — looks up a stable key, falling back to the source.
 *   $translator = new Translations(
 *       fn( string $key, string $default ): string => trans( "pdf.$key" ) ?: $default
 *   );
 *
 *   ( new Invoice( …, translator: $translator ) )->save( 'invoice.pdf' );
 *
 * Whatever the callable returns is still subject to WinAnsi encoding —
 * see {@see Language} — so a translation into Polish or Greek will not
 * render whatever system supplied it.
 *
 * @since 1.0.0
 */
final class Translations implements Translator {

	/**
	 * The wrapped translation function.
	 *
	 * Not a promoted readonly property: PHP requires those to be typed,
	 * and there is no type for "callable" that also accepts a plain
	 * function name or an object with __invoke.
	 *
	 * @var callable(string, string): string
	 */
	private $callback;

	/**
	 * @since 1.0.0
	 *
	 * @param callable(string, string): string $callback Receives the key
	 *                                                   and the English
	 *                                                   default, returns
	 *                                                   the translation.
	 * @param Translator|null                  $fallback Used when the
	 *                                                   callback returns
	 *                                                   nothing usable.
	 *                                                   Defaults to English.
	 */
	public function __construct( callable $callback, private readonly ?Translator $fallback = null ) {
		$this->callback = $callback;
	}

	/**
	 * {@inheritDoc}
	 */
	public function translate( string $key, string $default ): string {
		$result = ( $this->callback )( $key, $default );

		// A translation system that returns null, false or an empty
		// string for a missing entry would otherwise blank the label.
		if ( ! is_string( $result ) || '' === trim( $result ) ) {
			return ( $this->fallback ?? Language::English )->translate( $key, $default );
		}

		return $result;
	}
}
