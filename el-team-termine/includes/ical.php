<?php
/**
 * Kalender-Export im iCalendar-Format (.ics):
 * ?el_termin_ics=123 für einen Termin, ?el_termin_ics=alle für alle kommenden und die
 * Termine der letzten 90 Tage (zum Abonnieren in Kalender-Apps).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adresse der .ics-Datei.
 *
 * @param int|WP_Post|null $post Termin; ohne Angabe der Kalender mit allen Terminen
 * @return string URL
 */
function el_team_termine_ics_url( $post = null ) {
	$wert = $post ? get_post( $post )->ID : 'alle';
	return add_query_arg( 'el_termin_ics', $wert, home_url( '/' ) );
}

/**
 * Abfrage-Variable el_termin_ics.
 *
 * @param array $vars Abfrage-Variablen
 * @return array
 */
function el_team_termine_ics_query_var( $vars ) {
	$vars[] = 'el_termin_ics';
	return $vars;
}
add_filter( 'query_vars', 'el_team_termine_ics_query_var' );

/**
 * Text für iCalendar maskieren (RFC 5545, 3.3.11).
 *
 * @param string $text Text
 * @return string
 */
function el_team_termine_ics_text( $text ) {
	$text = wp_strip_all_tags( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) );
	return str_replace( array( '\\', ';', ',', "\r\n", "\n" ), array( '\\\\', '\;', '\,', '\n', '\n' ), $text );
}

/**
 * Zeile auf höchstens 75 Bytes falten (RFC 5545, 3.1), ohne UTF-8-Zeichen zu zerteilen.
 *
 * @param string $zeile Zeile
 * @return string
 */
function el_team_termine_ics_fold( $zeile ) {
	$aus   = '';
	$teil  = '';
	$laenge = 0;
	foreach ( preg_split( '//u', $zeile, -1, PREG_SPLIT_NO_EMPTY ) as $zeichen ) {
		$bytes = strlen( $zeichen );
		if ( $laenge + $bytes > ( '' === $aus ? 75 : 74 ) ) {
			$aus   .= ( '' === $aus ? '' : "\r\n " ) . $teil;
			$teil   = '';
			$laenge = 0;
		}
		$teil   .= $zeichen;
		$laenge += $bytes;
	}
	return $aus . ( '' === $aus ? '' : "\r\n " ) . $teil;
}

/**
 * VEVENT eines Termins.
 *
 * @param WP_Post $post Termin
 * @return string[] Zeilen
 */
function el_team_termine_ics_event( $post ) {
	$d = el_team_termin_daten( $post );
	if ( ! $d ) {
		return array();
	}
	$utc    = new DateTimeZone( 'UTC' );
	$zeilen = array(
		'BEGIN:VEVENT',
		'UID:termin-' . $post->ID . '@' . wp_parse_url( home_url(), PHP_URL_HOST ),
		'DTSTAMP:' . gmdate( 'Ymd\THis\Z', strtotime( $post->post_modified_gmt . ' UTC' ) ),
		'SUMMARY:' . el_team_termine_ics_text( get_the_title( $post ) ),
		'URL:' . get_permalink( $post ),
	);
	if ( $d['ganztags'] ) {
		// Ganztägig: Ende ist der Tag nach dem letzten Tag
		$ende     = $d['ende'] ? $d['ende'] : $d['beginn'];
		$zeilen[] = 'DTSTART;VALUE=DATE:' . $d['beginn']->format( 'Ymd' );
		$zeilen[] = 'DTEND;VALUE=DATE:' . $ende->modify( '+1 day' )->format( 'Ymd' );
	} else {
		$ende     = $d['ende'] ? $d['ende'] : $d['beginn']->modify( '+2 hours' );
		$zeilen[] = 'DTSTART:' . $d['beginn']->setTimezone( $utc )->format( 'Ymd\THis\Z' );
		$zeilen[] = 'DTEND:' . $ende->setTimezone( $utc )->format( 'Ymd\THis\Z' );
	}
	$ort = implode( ', ', array_filter( array( $d['ort'], $d['adresse'] ) ) );
	if ( '' !== $ort ) {
		$zeilen[] = 'LOCATION:' . el_team_termine_ics_text( $ort );
	}
	$beschreibung = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( strip_shortcodes( $post->post_content ), 40 );
	if ( '' !== $d['anmeldung'] ) {
		$beschreibung .= "\n\n" . __( 'Anmeldung und Infos:', 'el-team-termine' ) . ' ' . $d['anmeldung'];
	}
	if ( '' !== trim( $beschreibung ) ) {
		$zeilen[] = 'DESCRIPTION:' . el_team_termine_ics_text( $beschreibung );
	}
	if ( 'abgesagt' === $d['status'] ) {
		$zeilen[] = 'STATUS:CANCELLED';
	}
	$zeilen[] = 'END:VEVENT';
	return $zeilen;
}

/**
 * Liefert die .ics-Datei aus, wenn ?el_termin_ics=… aufgerufen wird.
 */
function el_team_termine_ics_output() {
	$wert = get_query_var( 'el_termin_ics' );
	if ( '' === $wert ) {
		return;
	}
	if ( 'alle' === $wert ) {
		$termine = get_posts( array_merge( el_team_termine_query_vars( 'alle' ), array(
			'post_type'      => 'termin',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => '_el_termin_bis',
					'value'   => wp_date( 'Y-m-d H:i', time() - 90 * DAY_IN_SECONDS ),
					'compare' => '>=',
					'type'    => 'CHAR',
				),
			),
		) ) );
		$datei   = sanitize_title( get_bloginfo( 'name' ) ) . '-termine.ics';
	} else {
		$termin  = get_post( absint( $wert ) );
		$termine = ( $termin && 'termin' === $termin->post_type && 'publish' === $termin->post_status ) ? array( $termin ) : array();
		if ( ! $termine ) {
			return;
		}
		$datei = $termin->post_name . '.ics';
	}

	$zeilen = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//' . el_team_termine_ics_text( get_bloginfo( 'name' ) ) . '//Termine//DE',
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'X-WR-CALNAME:' . el_team_termine_ics_text( get_bloginfo( 'name' ) . ' – ' . __( 'Termine', 'el-team-termine' ) ),
	);
	foreach ( $termine as $termin ) {
		$zeilen = array_merge( $zeilen, el_team_termine_ics_event( $termin ) );
	}
	$zeilen[] = 'END:VCALENDAR';

	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: ' . ( 'alle' === $wert ? 'inline' : 'attachment' ) . '; filename="' . $datei . '"' );
	echo implode( "\r\n", array_map( 'el_team_termine_ics_fold', $zeilen ) ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- iCalendar, oben maskiert.
	exit;
}
add_action( 'template_redirect', 'el_team_termine_ics_output' );
