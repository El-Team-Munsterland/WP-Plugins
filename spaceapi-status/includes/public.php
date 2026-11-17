<?php
/**
 * Öffentliche SpaceAPI (Version 15) unter /spaceapi.json.
 *
 * Reihenfolge beim Zusammenbau: eigene Angaben → ausgewählte Felder der versteckten SpaceAPI
 * (ersetzen eigene Angaben) → Werkstatt-Öffnungen aus dem Plugin "El-Team Termine".
 * Die SpaceAPI hat kein Feld für zukünftige Öffnungen; sie stehen deshalb in feeds.calendar
 * (iCal), im Zusatzfeld ext_upcoming_openings (Präfix ext_ laut spaceapi.io für eigene Felder)
 * und bei geschlossener Werkstatt in state.message. Termine ändern nie state.open.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adresse der öffentlichen SpaceAPI.
 *
 * @return string
 */
function spaceapi_status_public_url() {
	return get_option( 'permalink_structure' ) ? home_url( '/spaceapi.json' ) : add_query_arg( 'spaceapi_json', '1', home_url( '/' ) );
}

/**
 * Felder der versteckten SpaceAPI, die weitergegeben werden können: Objekte werden aufgeteilt,
 * Listen und Einzelwerte bleiben ganz (damit z. B. ein Sensor vollständig bleibt).
 *
 * @param array  $data   Antwort der versteckten SpaceAPI
 * @param string $prefix Pfad bis hierher
 * @return array Pfad => Wert
 */
function spaceapi_status_passthrough_candidates( $data, $prefix = '' ) {
	$out = array();
	foreach ( $data as $key => $wert ) {
		$pfad = '' === $prefix ? (string) $key : $prefix . '.' . $key;
		// Versionsangaben setzt die öffentliche SpaceAPI selbst
		if ( in_array( $pfad, array( 'api', 'api_compatibility' ), true ) ) {
			continue;
		}
		$ist_objekt = is_array( $wert ) && $wert && array_keys( $wert ) !== range( 0, count( $wert ) - 1 );
		if ( $ist_objekt ) {
			$out += spaceapi_status_passthrough_candidates( $wert, $pfad );
		} else {
			$out[ $pfad ] = $wert;
		}
	}
	return $out;
}

/**
 * Setzt einen Wert per Punktnotation, z. B. "state.open".
 *
 * @param array  $data Ziel
 * @param string $pfad Pfad
 * @param mixed  $wert Wert
 */
function spaceapi_status_set_path( &$data, $pfad, $wert ) {
	$teile = explode( '.', $pfad );
	$ziel  = &$data;
	foreach ( $teile as $i => $teil ) {
		if ( $i === count( $teile ) - 1 ) {
			$ziel[ $teil ] = $wert;
			return;
		}
		if ( ! isset( $ziel[ $teil ] ) || ! is_array( $ziel[ $teil ] ) ) {
			$ziel[ $teil ] = array();
		}
		$ziel = &$ziel[ $teil ];
	}
}

/**
 * Zeilen eines Textfelds (leere entfallen).
 *
 * @param string $text Text
 * @return string[]
 */
function spaceapi_status_lines( $text ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $text ) ), 'strlen' ) );
}

/**
 * Kurzer Text für die nächste Öffnung, z. B. "Fr., 4.12., 19:00 Uhr (El-Café im Dezember)".
 *
 * @param WP_Post $termin Termin
 * @return string
 */
function spaceapi_status_opening_text( $termin ) {
	$d = el_team_termin_daten( $termin );
	if ( ! $d ) {
		return '';
	}
	$ts    = $d['beginn']->getTimestamp();
	$heute = current_time( 'Y-m-d' );
	$tag   = $d['beginn']->format( 'Y-m-d' );
	if ( $tag <= $heute ) {
		$wann = 'heute'; // auch bei mehrtägigen Terminen, die schon laufen
	} elseif ( $tag === wp_date( 'Y-m-d', strtotime( $heute . ' +1 day' ) ) ) {
		$wann = 'morgen';
	} else {
		$wann = wp_date( 'D., j.n.', $ts );
	}
	if ( ! $d['ganztags'] && $tag >= $heute ) {
		$wann .= ', ' . wp_date( 'G:i', $ts );
		// Mit Endzeit am selben Tag, damit ein schon laufender Termin verständlich bleibt
		if ( $d['ende'] && $d['ende']->format( 'Y-m-d' ) === $tag ) {
			$wann .= '–' . wp_date( 'G:i', $d['ende']->getTimestamp() );
		}
		$wann .= ' Uhr';
	}
	return $wann . ' (' . wp_strip_all_tags( get_the_title( $termin ) ) . ')';
}

/**
 * Inhalt der öffentlichen SpaceAPI.
 *
 * @return array
 */
function spaceapi_status_public_data() {
	$s   = spaceapi_status_get_settings();
	$own = $s['own'];

	// Logo: eigene Angabe, sonst das Logo der Website
	$logo = $own['logo'];
	if ( '' === $logo && get_theme_mod( 'custom_logo' ) ) {
		$logo = (string) wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
	}

	$o = array(
		'api_compatibility' => array( '15' ),
		'space'             => '' !== $own['space'] ? $own['space'] : get_bloginfo( 'name' ),
		'logo'              => $logo,
		'url'               => '' !== $own['url'] ? $own['url'] : home_url( '/' ),
	);

	$location = array_filter( array(
		'address'      => $own['address'],
		'timezone'     => $own['timezone'],
		'country_code' => $own['country_code'],
		'hint'         => $own['hint'],
	), 'strlen' );
	if ( '' !== $own['lat'] && '' !== $own['lon'] ) {
		$location['lat'] = (float) $own['lat'];
		$location['lon'] = (float) $own['lon'];
	}
	if ( $location ) {
		$o['location'] = $location;
	}

	$o['contact'] = array_filter( array(
		'email'    => $own['email'],
		'ml'       => $own['ml'],
		'phone'    => $own['phone'],
		'matrix'   => $own['matrix'],
		'mastodon' => $own['mastodon'],
		'xmpp'     => $own['xmpp'],
	), 'strlen' );

	$state = array();
	if ( '' !== $own['state_message'] ) {
		$state['message'] = $own['state_message'];
	}
	if ( '' !== $own['icon_open'] && '' !== $own['icon_closed'] ) {
		$state['icon'] = array( 'open' => $own['icon_open'], 'closed' => $own['icon_closed'] );
	}
	if ( $state ) {
		$o['state'] = $state;
	}

	if ( ! empty( $own['feed_blog'] ) ) {
		$o['feeds']['blog'] = array( 'type' => 'rss', 'url' => get_feed_link() );
	}

	$links = array();
	foreach ( spaceapi_status_lines( $own['links'] ) as $zeile ) {
		$teile = array_map( 'trim', explode( '|', $zeile ) );
		if ( count( $teile ) >= 2 && '' !== $teile[0] && wp_http_validate_url( $teile[1] ) ) {
			$link = array( 'name' => $teile[0], 'url' => $teile[1] );
			if ( ! empty( $teile[2] ) ) {
				$link['description'] = $teile[2];
			}
			$links[] = $link;
		}
	}
	if ( $links ) {
		$o['links'] = $links;
	}
	foreach ( array( 'projects', 'cam' ) as $liste ) {
		$urls = array_values( array_filter( spaceapi_status_lines( $own[ $liste ] ), 'wp_http_validate_url' ) );
		if ( $urls ) {
			$o[ $liste ] = $urls;
		}
	}

	// Ausgewählte Felder der versteckten SpaceAPI
	$message_von_quelle = false;
	if ( $s['passthrough'] ) {
		$quelle = spaceapi_status_data();
		foreach ( $s['passthrough'] as $pfad ) {
			if ( 0 === strpos( $pfad, 'state.' ) && ! isset( $o['state'] ) ) {
				// state vorhanden, auch wenn die Quelle gerade nichts liefert: "Status gerade unbekannt"
				$o['state'] = array();
			}
			$gefunden = false;
			$wert     = spaceapi_status_value( $quelle, $pfad, $gefunden );
			if ( $gefunden && null !== $wert ) {
				spaceapi_status_set_path( $o, $pfad, $wert );
				$message_von_quelle = $message_von_quelle || 'state.message' === $pfad;
			}
		}
	}

	// Werkstatt-Öffnungen aus dem Plugin "El-Team Termine"
	if ( function_exists( 'el_team_termine_oeffnungen' ) ) {
		if ( ! empty( $s['openings_calendar'] ) ) {
			$o['feeds']['calendar'] = array( 'type' => 'ical', 'url' => el_team_termine_ics_url( 'oeffnungen' ) );
		}
		$oeffnungen = ( ! empty( $s['openings_enabled'] ) || ! empty( $s['openings_message'] ) ) ? el_team_termine_oeffnungen( max( 1, (int) $s['openings_count'] ) ) : array();

		if ( ! empty( $s['openings_enabled'] ) ) {
			$o['ext_upcoming_openings'] = array();
			foreach ( $oeffnungen as $termin ) {
				$d = el_team_termin_daten( $termin );
				if ( ! $d ) {
					continue;
				}
				$eintrag = array(
					'name'    => wp_strip_all_tags( get_the_title( $termin ) ),
					'start'   => $d['beginn']->getTimestamp(),
					'all_day' => $d['ganztags'],
					'url'     => get_permalink( $termin ),
				);
				if ( $d['ende'] ) {
					$eintrag['end'] = $d['ende']->getTimestamp();
				} elseif ( $d['ganztags'] ) {
					$eintrag['end'] = $d['beginn']->setTime( 23, 59 )->getTimestamp();
				}
				if ( '' !== $d['ort'] ) {
					$eintrag['location'] = $d['ort'];
				}
				$o['ext_upcoming_openings'][] = $eintrag;
			}
		}

		// Statustext: nächste Öffnung, solange nicht geöffnet (und die Quelle keinen eigenen Text liefert)
		$offen = isset( $o['state']['open'] ) ? $o['state']['open'] : null;
		if ( ! empty( $s['openings_message'] ) && true !== $offen && ! $message_von_quelle && $oeffnungen ) {
			$naechste = spaceapi_status_opening_text( $oeffnungen[0] );
			if ( '' !== $naechste ) {
				$o['state']['message'] = ( false === $offen ? 'Geschlossen – nächste Öffnung: ' : 'Nächste Öffnung: ' ) . $naechste;
			}
		}
	}

	// Leere Objekte als {} ausgeben, Pflichtfeld contact immer vorhanden
	foreach ( array( 'contact', 'state', 'feeds' ) as $objekt ) {
		if ( isset( $o[ $objekt ] ) && array() === $o[ $objekt ] ) {
			$o[ $objekt ] = new stdClass();
		}
	}

	/**
	 * Filtert die öffentliche SpaceAPI vor der Ausgabe.
	 *
	 * @param array $o SpaceAPI-Daten
	 */
	return apply_filters( 'spaceapi_status_public_data', $o );
}

/**
 * Prüft die wichtigsten Regeln von Version 15 (für die Vorschau im Adminbereich).
 *
 * @param array $o SpaceAPI-Daten
 * @return string[] Probleme
 */
function spaceapi_status_public_problems( $o ) {
	$probleme = array();
	foreach ( array( 'space' => 'Name', 'logo' => 'Logo', 'url' => 'Website' ) as $key => $name ) {
		if ( empty( $o[ $key ] ) || ! is_string( $o[ $key ] ) ) {
			/* translators: %s: Feldname */
			$probleme[] = sprintf( '%s fehlt (Pflichtfeld).', $name );
		}
	}
	if ( isset( $o['state']['open'] ) && ! is_bool( $o['state']['open'] ) ) {
		$probleme[] = 'state.open aus der versteckten SpaceAPI ist kein true/false.';
	}
	if ( isset( $o['location'] ) && ( isset( $o['location']['lat'] ) xor isset( $o['location']['lon'] ) ) ) {
		$probleme[] = 'Breiten- und Längengrad nur gemeinsam angeben.';
	}
	return $probleme;
}

/**
 * Adresse /spaceapi.json.
 */
function spaceapi_status_rewrite() {
	add_rewrite_rule( '^spaceapi\.json$', 'index.php?spaceapi_json=1', 'top' );
	// Nach einem Update ohne erneute Aktivierung die Adressen einmalig neu aufbauen
	if ( get_option( 'spaceapi_status_rewrite' ) !== SPACEAPI_STATUS_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'spaceapi_status_rewrite', SPACEAPI_STATUS_VERSION );
	}
}
add_action( 'init', 'spaceapi_status_rewrite' );

/**
 * Abfrage-Variable spaceapi_json.
 *
 * @param array $vars Abfrage-Variablen
 * @return array
 */
function spaceapi_status_query_var( $vars ) {
	$vars[] = 'spaceapi_json';
	return $vars;
}
add_filter( 'query_vars', 'spaceapi_status_query_var' );

/**
 * Liefert die öffentliche SpaceAPI aus – vor der Statusleiste und vor WordPress' Weiterleitungen.
 */
function spaceapi_status_public_output() {
	if ( ! get_query_var( 'spaceapi_json' ) ) {
		return;
	}
	$s = spaceapi_status_get_settings();
	if ( empty( $s['public_enabled'] ) ) {
		// Ausgeschaltet: "nicht gefunden"
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		return;
	}
	status_header( 200 );
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Access-Control-Allow-Origin: *' );
	header( 'Cache-Control: public, max-age=60' );
	echo wp_json_encode( spaceapi_status_public_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON.
	exit;
}
add_action( 'template_redirect', 'spaceapi_status_public_output', -10 );
