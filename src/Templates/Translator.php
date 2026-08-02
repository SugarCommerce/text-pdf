<?php
/**
 * Translation contract for template wording.
 *
 * @package   Sugar\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace Sugar\TextPdf\Templates;

/**
 * Interface Translator
 *
 * How the templates get their words, without the library knowing or
 * caring where translations live.
 *
 * A standalone library cannot call `__()`, `trans()` or `gettext()` — it
 * has no idea which of them exists. Bundling a translation system of its
 * own is worse: an application then has two, and the strings in your
 * PDFs are the only ones not in the translation workflow your
 * translators already use.
 *
 * So the library states a contract and the application supplies the
 * implementation. {@see Language} is a working default so nothing has to
 * be wired up to get sensible output, and {@see Translations} adapts any
 * existing system in one line.
 *
 * **Keys and defaults are both passed** because translation systems
 * disagree about which they key on. gettext and WordPress look up the
 * English source string; Laravel, Symfony and most JSON-file systems
 * look up a stable identifier. Passing both means either style works
 * without the caller writing a mapping table.
 *
 *   // WordPress
 *   new Translations( fn( $key, $default ) => __( $default, 'my-plugin' ) );
 *
 *   // Laravel
 *   new Translations( fn( $key, $default ) => trans( "pdf.$key" ) ?: $default );
 *
 *   // gettext
 *   new Translations( fn( $key, $default ) => gettext( $default ) );
 *
 * @since 1.0.0
 */
interface Translator {

	/**
	 * Translate a phrase.
	 *
	 * Must always return something printable. Returning an empty string
	 * for a missing translation produces a document with blank column
	 * headings, which is worse than an untranslated one — fall back to
	 * `$default`.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Stable identifier, e.g. `total_due`.
	 * @param string $default English source text, e.g. `Total due`.
	 *
	 * @return string
	 */
	public function translate( string $key, string $default ): string;
}
