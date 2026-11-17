<?php
/**
 * Termin-Daten: Felder, Zeittext und Abfragen.
 *
 * Gespeichert wird in der Zeitzone der Website (Einstellungen → Allgemein) im Format
 * "Y-m-d H:i". _el_termin_bis ist das Ende (oder der Beginn) und entscheidet, ob ein Termin
 * noch kommt oder vorbei ist; bei ganztägigen Terminen gilt das Tagesende.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Status, die ein Termin haben kann.
 *
 * @return array Schlüssel => Bezeichnung
 */
function el_team_termin_status_liste() {
	return array(
		''           => __( 'findet statt', 'el-team-termine' ),
		'abgesagt'   => __( 'abgesagt', 'el-team-termine' ),
		'verschoben' => __( 'verschoben', 'el-team-termine' ),
	);
}

/**
 * Daten eines Termins.
 *
 * @param int|WP_Post|null $post Termin, Standard: aktueller Beitrag
 * @return array|null beginn, ende (DateTimeImmutable|null), ganztags, ort, adresse, anmeldung, status
 */
function el_team_termin_daten( $post = null ) {
	$post = get_post( $post );
	if ( ! $post || 'termin' !== $post->post_type ) {
		return null;
	}
	$zone   = wp_timezone();
	$datum  = function ( $wert ) use ( $zone ) {
		$wert = (string) $wert;
		$d    = '' !== $wert ? DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $wert, $zone ) : false;
		return $d ? $d : null;
	};
	$beginn = $datum( get_post_meta( $post->ID, '_el_termin_beginn', true ) );
	if ( ! $beginn ) {
		return null;
	}
	return array(
		'beginn'    => $beginn,
		'ende'      => $datum( get_post_meta( $post->ID, '_el_termin_ende', true ) ),
		'ganztags'  => (bool) get_post_meta( $post->ID, '_el_termin_ganztags', true ),
		'ort'       => (string) get_post_meta( $post->ID, '_el_termin_ort', true ),
		'adresse'   => (string) get_post_meta( $post->ID, '_el_termin_adresse', true ),
		'anmeldung' => (string) get_post_meta( $post->ID, '_el_termin_anmeldung', true ),
		'status'    => (string) get_post_meta( $post->ID, '_el_termin_status', true ),
	);
}

/**
 * Datum und Uhrzeit als Text, z. B. "Fr., 5. Dezember 2026, 19:00–22:00 Uhr"
 * oder "21.–23. August 2026".
 *
 * @param int|WP_Post|null $post Termin
 * @return string
 */
function el_team_termin_zeit_text( $post = null ) {
	$d = el_team_termin_daten( $post );
	if ( ! $d ) {
		return '';
	}
	$b     = $d['beginn'];
	$e     = $d['ende'];
	$tag   = function ( $datum, $format ) {
		return wp_date( $format, $datum->getTimestamp() );
	};
	$mehrtaegig = $e && $e->format( 'Y-m-d' ) !== $b->format( 'Y-m-d' );

	if ( $mehrtaegig ) {
		if ( $b->format( 'Y-m' ) === $e->format( 'Y-m' ) ) {
			$text = $tag( $b, 'j.' ) . '–' . $tag( $e, 'j. F Y' );
		} elseif ( $b->format( 'Y' ) === $e->format( 'Y' ) ) {
			$text = $tag( $b, 'j. F' ) . ' – ' . $tag( $e, 'j. F Y' );
		} else {
			$text = $tag( $b, 'j. F Y' ) . ' – ' . $tag( $e, 'j. F Y' );
		}
		if ( ! $d['ganztags'] ) {
			/* translators: 1: Uhrzeit Beginn, 2: Uhrzeit Ende */
			$text .= ', ' . sprintf( __( 'ab %1$s bis %2$s Uhr', 'el-team-termine' ), $tag( $b, 'G:i' ), $tag( $e, 'G:i' ) );
		}
		return $text;
	}

	$text = $tag( $b, 'D., j. F Y' );
	if ( ! $d['ganztags'] ) {
		$text .= ', ' . $tag( $b, 'G:i' );
		$text .= $e ? '–' . $tag( $e, 'G:i' ) : '';
		$text .= ' ' . __( 'Uhr', 'el-team-termine' );
	}
	return $text;
}

/**
 * Abfrage von Terminen.
 *
 * @param array $args zeitraum ("kommend", "vergangen", "alle"), anzahl (-1 = alle), art (Slug der Terminart)
 * @return WP_Query
 */
function el_team_termine_query( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'zeitraum' => 'kommend',
		'anzahl'   => 10,
		'art'      => '',
	) );
	return new WP_Query( el_team_termine_query_vars( $args['zeitraum'], array(
		'post_type'           => 'termin',
		'post_status'         => 'publish',
		'posts_per_page'      => (int) $args['anzahl'],
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'terminart'           => sanitize_title( $args['art'] ),
	) ) );
}

/**
 * Sortierung und Zeitfilter für Termin-Abfragen (auch für die Übersicht /termine/).
 *
 * @param string $zeitraum "kommend" (Standard), "vergangen" oder "alle"
 * @param array  $vars     weitere Abfrage-Variablen
 * @return array
 */
function el_team_termine_query_vars( $zeitraum, $vars = array() ) {
	$jetzt   = current_time( 'Y-m-d H:i' );
	$kommend = 'vergangen' !== $zeitraum;

	$vars['meta_key'] = '_el_termin_beginn';
	$vars['orderby']  = 'meta_value';
	$vars['order']    = $kommend ? 'ASC' : 'DESC';
	if ( 'alle' !== $zeitraum ) {
		$vars['meta_query'] = array(
			array(
				'key'     => '_el_termin_bis',
				'value'   => $jetzt,
				'compare' => $kommend ? '>=' : '<',
				'type'    => 'CHAR',
			),
		);
	}
	return $vars;
}

/**
 * Kommende Termine, z. B. für die Startseite des Themes.
 *
 * @param int $anzahl Anzahl
 * @return WP_Post[]
 */
function el_team_termine_kommend( $anzahl = 3 ) {
	return el_team_termine_query( array( 'anzahl' => $anzahl ) )->posts;
}
