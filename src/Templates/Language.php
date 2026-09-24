<?php
/**
 * Template wording, translated.
 *
 * @package   SugarCommerce\TextPdf
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\TextPdf\Templates;

/**
 * Enum Language
 *
 * The fixed wording in the built-in templates — headings, column labels,
 * status lines — in the languages a European seller most often needs.
 *
 * **Only Latin-1 languages are included, and that is a hard limit rather
 * than a shortlist.** The base-14 PDF fonts use WinAnsi encoding, which
 * covers Western and Northern Europe: German umlauts, French accents,
 * Spanish tildes, the Nordic å/ä/ö/æ/ø all render. Polish, Czech,
 * Hungarian, Turkish, Greek and Cyrillic do not — `ł`, `ę`, `č`, `ő`,
 * `ğ` and anything Cyrillic are absent from the encoding, so they would
 * print as substitutes or vanish. Adding them means embedding a font,
 * which is a different library.
 *
 * Your own content — customer names, item descriptions — is subject to
 * exactly the same limit, whichever language is selected here.
 *
 * @since 1.0.0
 */
enum Language: string implements Translator {

	case English    = 'en';
	case German     = 'de';
	case French     = 'fr';
	case Spanish    = 'es';
	case Italian    = 'it';
	case Dutch      = 'nl';
	case Portuguese = 'pt';
	case Swedish    = 'sv';
	case Danish     = 'da';
	case Norwegian  = 'nb';
	case Finnish    = 'fi';

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function translate( string $key, string $default ): string {
		return self::strings()[ $this->value ][ $key ] ?? $default;
	}

	/**
	 * Look up a phrase.
	 *
	 * Falls back to English for anything untranslated, so a missing entry
	 * degrades to a comprehensible document rather than a blank label.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Phrase key.
	 *
	 * @return string
	 */
	public function get( string $key ): string {
		return self::strings()[ $this->value ][ $key ]
			?? self::strings()['en'][ $key ]
			?? $key;
	}

	/**
	 * Every phrase for this language, English-backfilled.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function all(): array {
		return array_merge( self::strings()['en'], self::strings()[ $this->value ] ?? array() );
	}

	/**
	 * Resolve a locale or language tag.
	 *
	 * Accepts `de`, `de_DE`, `de-AT` and similar, so a stored locale can
	 * be passed straight through.
	 *
	 * @since 1.0.0
	 *
	 * @param string $locale Language tag.
	 *
	 * @return self English when unrecognised.
	 */
	public static function from_locale( string $locale ): self {
		$code = strtolower( substr( trim( str_replace( '-', '_', $locale ) ), 0, 2 ) );

		// Norwegian has two written forms and several tags; both map here.
		if ( in_array( $code, array( 'nb', 'nn', 'no' ), true ) ) {
			return self::Norwegian;
		}

		return self::tryFrom( $code ) ?? self::English;
	}

	/**
	 * The phrase table.
	 *
	 * Kept as one const so the whole set is visible at a glance and a
	 * missing key in one language is obvious next to the others.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function strings(): array {
		return array(
			'en' => array(
				'invoice'        => 'INVOICE',
				'statement'      => 'STATEMENT',
				'receipt'        => 'RECEIPT',
				'billed_to'      => 'BILLED TO',
				'account'        => 'ACCOUNT',
				'details'        => 'DETAILS',
				'payment'        => 'PAYMENT',
				'paid_in_full'   => 'PAID IN FULL',
				'description'    => 'Description',
				'quantity'       => 'Qty',
				'unit_price'     => 'Unit price',
				'amount'         => 'Amount',
				'date'           => 'Date',
				'reference'      => 'Reference',
				'balance'        => 'Balance',
				'net'            => 'Net',
				'vat'            => 'VAT',
				'rate'           => 'Rate',
				'vat_breakdown'  => 'VAT BREAKDOWN',
				'total_due'      => 'Total due',
				'paid'           => 'Paid',
				'closing_balance' => 'Closing balance',
				'date_of_supply' => 'Date of supply',
				'page'           => 'Page',
				'of'             => 'of',
			),
			'de' => array(
				'invoice'        => 'RECHNUNG',
				'statement'      => 'KONTOAUSZUG',
				'receipt'        => 'QUITTUNG',
				'billed_to'      => 'RECHNUNGSEMPFÄNGER',
				'account'        => 'KONTO',
				'details'        => 'ANGABEN',
				'payment'        => 'ZAHLUNG',
				'paid_in_full'   => 'VOLLSTÄNDIG BEZAHLT',
				'description'    => 'Beschreibung',
				'quantity'       => 'Menge',
				'unit_price'     => 'Einzelpreis',
				'amount'         => 'Betrag',
				'date'           => 'Datum',
				'reference'      => 'Referenz',
				'balance'        => 'Saldo',
				'net'            => 'Netto',
				'vat'            => 'USt.',
				'rate'           => 'Satz',
				'vat_breakdown'  => 'STEUERAUFSTELLUNG',
				'total_due'      => 'Gesamtbetrag',
				'paid'           => 'Bezahlt',
				'closing_balance' => 'Schlusssaldo',
				'date_of_supply' => 'Leistungsdatum',
				'page'           => 'Seite',
				'of'             => 'von',
			),
			'fr' => array(
				'invoice'        => 'FACTURE',
				'statement'      => 'RELEVÉ DE COMPTE',
				'receipt'        => 'REÇU',
				'billed_to'      => 'FACTURÉ À',
				'account'        => 'COMPTE',
				'details'        => 'DÉTAILS',
				'payment'        => 'PAIEMENT',
				'paid_in_full'   => 'PAYÉ INTÉGRALEMENT',
				'description'    => 'Description',
				'quantity'       => 'Qté',
				'unit_price'     => 'Prix unitaire',
				'amount'         => 'Montant',
				'date'           => 'Date',
				'reference'      => 'Référence',
				'balance'        => 'Solde',
				'net'            => 'Net',
				'vat'            => 'TVA',
				'rate'           => 'Taux',
				'vat_breakdown'  => 'DÉTAIL DE LA TVA',
				'total_due'      => 'Total à payer',
				'paid'           => 'Payé',
				'closing_balance' => 'Solde final',
				'date_of_supply' => 'Date de prestation',
				'page'           => 'Page',
				'of'             => 'sur',
			),
			'es' => array(
				'invoice'        => 'FACTURA',
				'statement'      => 'EXTRACTO DE CUENTA',
				'receipt'        => 'RECIBO',
				'billed_to'      => 'FACTURAR A',
				'account'        => 'CUENTA',
				'details'        => 'DETALLES',
				'payment'        => 'PAGO',
				'paid_in_full'   => 'PAGADO ÍNTEGRAMENTE',
				'description'    => 'Descripción',
				'quantity'       => 'Cant.',
				'unit_price'     => 'Precio unitario',
				'amount'         => 'Importe',
				'date'           => 'Fecha',
				'reference'      => 'Referencia',
				'balance'        => 'Saldo',
				'net'            => 'Neto',
				'vat'            => 'IVA',
				'rate'           => 'Tipo',
				'vat_breakdown'  => 'DESGLOSE DE IVA',
				'total_due'      => 'Total a pagar',
				'paid'           => 'Pagado',
				'closing_balance' => 'Saldo final',
				'date_of_supply' => 'Fecha de entrega',
				'page'           => 'Página',
				'of'             => 'de',
			),
			'it' => array(
				'invoice'        => 'FATTURA',
				'statement'      => 'ESTRATTO CONTO',
				'receipt'        => 'RICEVUTA',
				'billed_to'      => 'FATTURATO A',
				'account'        => 'CONTO',
				'details'        => 'DETTAGLI',
				'payment'        => 'PAGAMENTO',
				'paid_in_full'   => 'PAGATO PER INTERO',
				'description'    => 'Descrizione',
				'quantity'       => 'Qtà',
				'unit_price'     => 'Prezzo unitario',
				'amount'         => 'Importo',
				'date'           => 'Data',
				'reference'      => 'Riferimento',
				'balance'        => 'Saldo',
				'net'            => 'Netto',
				'vat'            => 'IVA',
				'rate'           => 'Aliquota',
				'vat_breakdown'  => 'RIEPILOGO IVA',
				'total_due'      => 'Totale dovuto',
				'paid'           => 'Pagato',
				'closing_balance' => 'Saldo finale',
				'date_of_supply' => 'Data della prestazione',
				'page'           => 'Pagina',
				'of'             => 'di',
			),
			'nl' => array(
				'invoice'        => 'FACTUUR',
				'statement'      => 'REKENINGOVERZICHT',
				'receipt'        => 'KWITANTIE',
				'billed_to'      => 'GEFACTUREERD AAN',
				'account'        => 'REKENING',
				'details'        => 'GEGEVENS',
				'payment'        => 'BETALING',
				'paid_in_full'   => 'VOLLEDIG BETAALD',
				'description'    => 'Omschrijving',
				'quantity'       => 'Aantal',
				'unit_price'     => 'Stukprijs',
				'amount'         => 'Bedrag',
				'date'           => 'Datum',
				'reference'      => 'Referentie',
				'balance'        => 'Saldo',
				'net'            => 'Netto',
				'vat'            => 'BTW',
				'rate'           => 'Tarief',
				'vat_breakdown'  => 'BTW-SPECIFICATIE',
				'total_due'      => 'Totaal te betalen',
				'paid'           => 'Betaald',
				'closing_balance' => 'Eindsaldo',
				'date_of_supply' => 'Leveringsdatum',
				'page'           => 'Pagina',
				'of'             => 'van',
			),
			'pt' => array(
				'invoice'        => 'FATURA',
				'statement'      => 'EXTRATO DE CONTA',
				'receipt'        => 'RECIBO',
				'billed_to'      => 'FATURADO A',
				'account'        => 'CONTA',
				'details'        => 'DETALHES',
				'payment'        => 'PAGAMENTO',
				'paid_in_full'   => 'PAGO INTEGRALMENTE',
				'description'    => 'Descrição',
				'quantity'       => 'Qtd.',
				'unit_price'     => 'Preço unitário',
				'amount'         => 'Valor',
				'date'           => 'Data',
				'reference'      => 'Referência',
				'balance'        => 'Saldo',
				'net'            => 'Líquido',
				'vat'            => 'IVA',
				'rate'           => 'Taxa',
				'vat_breakdown'  => 'DISCRIMINAÇÃO DO IVA',
				'total_due'      => 'Total a pagar',
				'paid'           => 'Pago',
				'closing_balance' => 'Saldo final',
				'date_of_supply' => 'Data da prestação',
				'page'           => 'Página',
				'of'             => 'de',
			),
			'sv' => array(
				'invoice'        => 'FAKTURA',
				'statement'      => 'KONTOUTDRAG',
				'receipt'        => 'KVITTO',
				'billed_to'      => 'FAKTURERAS TILL',
				'account'        => 'KONTO',
				'details'        => 'UPPGIFTER',
				'payment'        => 'BETALNING',
				'paid_in_full'   => 'TILL FULLO BETALD',
				'description'    => 'Beskrivning',
				'quantity'       => 'Antal',
				'unit_price'     => 'Styckpris',
				'amount'         => 'Belopp',
				'date'           => 'Datum',
				'reference'      => 'Referens',
				'balance'        => 'Saldo',
				'net'            => 'Netto',
				'vat'            => 'Moms',
				'rate'           => 'Sats',
				'vat_breakdown'  => 'MOMSSPECIFIKATION',
				'total_due'      => 'Att betala',
				'paid'           => 'Betald',
				'closing_balance' => 'Utgående saldo',
				'date_of_supply' => 'Leveransdatum',
				'page'           => 'Sida',
				'of'             => 'av',
			),
			'da' => array(
				'invoice'        => 'FAKTURA',
				'statement'      => 'KONTOUDTOG',
				'receipt'        => 'KVITTERING',
				'billed_to'      => 'FAKTURERES TIL',
				'account'        => 'KONTO',
				'details'        => 'OPLYSNINGER',
				'payment'        => 'BETALING',
				'paid_in_full'   => 'FULDT BETALT',
				'description'    => 'Beskrivelse',
				'quantity'       => 'Antal',
				'unit_price'     => 'Stykpris',
				'amount'         => 'Beløb',
				'date'           => 'Dato',
				'reference'      => 'Reference',
				'balance'        => 'Saldo',
				'net'            => 'Netto',
				'vat'            => 'Moms',
				'rate'           => 'Sats',
				'vat_breakdown'  => 'MOMSSPECIFIKATION',
				'total_due'      => 'At betale',
				'paid'           => 'Betalt',
				'closing_balance' => 'Slutsaldo',
				'date_of_supply' => 'Leveringsdato',
				'page'           => 'Side',
				'of'             => 'af',
			),
			'nb' => array(
				'invoice'        => 'FAKTURA',
				'statement'      => 'KONTOUTSKRIFT',
				'receipt'        => 'KVITTERING',
				'billed_to'      => 'FAKTURERES TIL',
				'account'        => 'KONTO',
				'details'        => 'OPPLYSNINGER',
				'payment'        => 'BETALING',
				'paid_in_full'   => 'FULLT BETALT',
				'description'    => 'Beskrivelse',
				'quantity'       => 'Antall',
				'unit_price'     => 'Stykkpris',
				'amount'         => 'Beløp',
				'date'           => 'Dato',
				'reference'      => 'Referanse',
				'balance'        => 'Saldo',
				'net'            => 'Netto',
				'vat'            => 'MVA',
				'rate'           => 'Sats',
				'vat_breakdown'  => 'MVA-SPESIFIKASJON',
				'total_due'      => 'Å betale',
				'paid'           => 'Betalt',
				'closing_balance' => 'Sluttsaldo',
				'date_of_supply' => 'Leveringsdato',
				'page'           => 'Side',
				'of'             => 'av',
			),
			'fi' => array(
				'invoice'        => 'LASKU',
				'statement'      => 'TILIOTE',
				'receipt'        => 'KUITTI',
				'billed_to'      => 'LASKUTETAAN',
				'account'        => 'TILI',
				'details'        => 'TIEDOT',
				'payment'        => 'MAKSU',
				'paid_in_full'   => 'MAKSETTU KOKONAAN',
				'description'    => 'Kuvaus',
				'quantity'       => 'Määrä',
				'unit_price'     => 'Yksikköhinta',
				'amount'         => 'Summa',
				'date'           => 'Päivä',
				'reference'      => 'Viite',
				'balance'        => 'Saldo',
				'net'            => 'Netto',
				'vat'            => 'ALV',
				'rate'           => 'Kanta',
				'vat_breakdown'  => 'ALV-ERITTELY',
				'total_due'      => 'Maksettava',
				'paid'           => 'Maksettu',
				'closing_balance' => 'Loppusaldo',
				'date_of_supply' => 'Toimituspäivä',
				'page'           => 'Sivu',
				'of'             => '/',
			),
		);
	}
}
