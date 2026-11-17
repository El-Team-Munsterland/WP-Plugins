<?php
/**
 * Ausgabe auf der Website: Übersicht /termine/, Infokasten am Termin, Shortcode [termine],
 * Terminliste (auch für die Startseite des Themes) und strukturierte Daten (schema.org/Event).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Styles der Termin-Ausgabe. Sie nutzen die Farben des Themes (--el-color-…), wenn vorhanden.
 */
function el_team_termine_enqueue() {
	wp_enqueue_style( 'el-team-termine', EL_TEAM_TERMINE_URL . 'assets/termine.css', array(), EL_TEAM_TERMINE_VERSION );
}
add_action( 'wp_enqueue_scripts', 'el_team_termine_enqueue', 20 );

/**
 * Abfrage-Variable "zeitraum" für /termine/?zeitraum=vergangen.
 *
 * @param array $vars Abfrage-Variablen
 * @return array
 */
function el_team_termine_query_var( $vars ) {
	$vars[] = 'zeitraum';
	return $vars;
}
add_filter( 'query_vars', 'el_team_termine_query_var' );

/**
 * Ist die aktuelle Seite die Termin-Übersicht oder eine Terminart?
 *
 * @return bool
 */
function el_team_termine_is_archive() {
	return is_post_type_archive( 'termin' ) || is_tax( 'terminart' );
}

/**
 * Übersicht /termine/ und Terminarten: kommende Termine nach Datum, mit ?zeitraum=vergangen die vergangenen.
 *
 * @param WP_Query $query Abfrage
 */
function el_team_termine_archive_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! ( $query->is_post_type_archive( 'termin' ) || $query->is_tax( 'terminart' ) ) ) {
		return;
	}
	$zeitraum = 'vergangen' === $query->get( 'zeitraum' ) ? 'vergangen' : 'kommend';
	foreach ( el_team_termine_query_vars( $zeitraum ) as $key => $wert ) {
		$query->set( $key, $wert );
	}
	$query->set( 'posts_per_page', 20 );
}
add_action( 'pre_get_posts', 'el_team_termine_archive_query' );

/**
 * Überschrift der Übersicht.
 *
 * @param string $titel Überschrift
 * @return string
 */
function el_team_termine_archive_title( $titel ) {
	if ( ! el_team_termine_is_archive() ) {
		return $titel;
	}
	$vergangen = 'vergangen' === get_query_var( 'zeitraum' );
	if ( is_tax( 'terminart' ) ) {
		$art = single_term_title( '', false );
		/* translators: %s: Terminart, z. B. El-Café */
		return $vergangen ? sprintf( __( 'Vergangene Termine: %s', 'el-team-termine' ), $art ) : sprintf( __( 'Termine: %s', 'el-team-termine' ), $art );
	}
	return $vergangen ? __( 'Vergangene Termine', 'el-team-termine' ) : __( 'Termine', 'el-team-termine' );
}
add_filter( 'get_the_archive_title', 'el_team_termine_archive_title' );

/**
 * Unter der Überschrift: Wechsel zwischen kommenden und vergangenen Terminen, Kalender abonnieren.
 *
 * @param string $beschreibung Beschreibung
 * @return string
 */
function el_team_termine_archive_description( $beschreibung ) {
	if ( ! el_team_termine_is_archive() ) {
		return $beschreibung;
	}
	$basis     = is_tax( 'terminart' ) ? get_term_link( get_queried_object() ) : get_post_type_archive_link( 'termin' );
	$vergangen = 'vergangen' === get_query_var( 'zeitraum' );
	$links     = array(
		$vergangen
			? '<a href="' . esc_url( $basis ) . '">' . esc_html__( 'Kommende Termine', 'el-team-termine' ) . '</a>'
			: '<a href="' . esc_url( add_query_arg( 'zeitraum', 'vergangen', $basis ) ) . '">' . esc_html__( 'Vergangene Termine', 'el-team-termine' ) . '</a>',
		'<a href="' . esc_url( el_team_termine_ics_url() ) . '">' . esc_html__( 'Alle Termine im eigenen Kalender abonnieren (iCal)', 'el-team-termine' ) . '</a>',
	);
	return $beschreibung . '<p class="el-termine-links">' . implode( '<span aria-hidden="true"> · </span>', $links ) . '</p>';
}
add_filter( 'get_the_archive_description', 'el_team_termine_archive_description' );

/**
 * Bei Terminen ist "das Datum" das Datum des Termins, nicht das der Veröffentlichung
 * (z. B. in Beitragslisten des Themes und im Footer).
 *
 * @param string      $datum  Datum
 * @param string      $format Format
 * @param WP_Post|int $post   Beitrag
 * @return string
 */
function el_team_termine_date( $datum, $format, $post ) {
	$d = el_team_termin_daten( $post );
	if ( ! $d ) {
		return $datum;
	}
	return '' === $format ? el_team_termin_zeit_text( $post ) : wp_date( $format, $d['beginn']->getTimestamp() );
}
add_filter( 'get_the_date', 'el_team_termine_date', 10, 3 );

/**
 * Link zu OpenStreetMap für eine Adresse (keine eingebettete Karte).
 *
 * @param string $adresse Adresse
 * @return string URL
 */
function el_team_termine_karte_url( $adresse ) {
	return 'https://www.openstreetmap.org/search?query=' . rawurlencode( $adresse );
}

/**
 * Infokasten eines Termins: Wann, Wo, Anmeldung, Kalender.
 *
 * @param int|WP_Post|null $post    Termin
 * @param bool             $kompakt nur Wann und Wo (in Listen)
 * @return string HTML
 */
function el_team_termin_info_html( $post = null, $kompakt = false ) {
	$post = get_post( $post );
	$d    = el_team_termin_daten( $post );
	if ( ! $d ) {
		return '';
	}
	$status = el_team_termin_status_liste();
	ob_start();
	?>
	<div class="el-termin-info<?php echo '' !== $d['status'] ? ' is-' . esc_attr( $d['status'] ) : ''; ?><?php echo $kompakt ? ' el-termin-info--kompakt' : ''; ?>">
		<?php if ( '' !== $d['status'] && isset( $status[ $d['status'] ] ) ) : ?>
			<p class="el-termin-info__status"><?php echo esc_html( ucfirst( $status[ $d['status'] ] ) ); ?></p>
		<?php endif; ?>
		<dl class="el-termin-info__liste">
			<dt><?php esc_html_e( 'Wann', 'el-team-termine' ); ?></dt>
			<dd><time datetime="<?php echo esc_attr( $d['beginn']->format( 'c' ) ); ?>"><?php echo esc_html( el_team_termin_zeit_text( $post ) ); ?></time></dd>
			<?php if ( '' !== $d['ort'] || '' !== $d['adresse'] ) : ?>
				<dt><?php esc_html_e( 'Wo', 'el-team-termine' ); ?></dt>
				<dd>
					<?php echo esc_html( $d['ort'] ); ?>
					<?php if ( '' !== $d['adresse'] ) : ?>
						<?php echo '' !== $d['ort'] ? '<br>' : ''; ?><?php echo esc_html( $d['adresse'] ); ?>
						<?php if ( ! $kompakt ) : ?>
							<br><a href="<?php echo esc_url( el_team_termine_karte_url( $d['adresse'] ) ); ?>"><?php esc_html_e( 'Auf der Karte zeigen', 'el-team-termine' ); ?></a>
						<?php endif; ?>
					<?php endif; ?>
				</dd>
			<?php endif; ?>
			<?php if ( el_team_termin_ist_oeffnung( $post ) && '' === $d['status'] ) : ?>
				<dt><?php esc_html_e( 'Werkstatt', 'el-team-termine' ); ?></dt>
				<dd><?php esc_html_e( 'geöffnet – komm einfach vorbei', 'el-team-termine' ); ?></dd>
			<?php endif; ?>
			<?php if ( ! $kompakt && '' !== $d['anmeldung'] ) : ?>
				<dt><?php esc_html_e( 'Anmeldung', 'el-team-termine' ); ?></dt>
				<dd><a href="<?php echo esc_url( $d['anmeldung'] ); ?>"><?php esc_html_e( 'Anmeldung und weitere Infos', 'el-team-termine' ); ?></a></dd>
			<?php endif; ?>
		</dl>
		<?php if ( ! $kompakt ) : ?>
			<p class="el-termin-info__kalender"><a href="<?php echo esc_url( el_team_termine_ics_url( $post ) ); ?>" download><?php esc_html_e( 'In den eigenen Kalender eintragen (.ics)', 'el-team-termine' ); ?></a></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Infokasten vor dem Text eines Termins: ausführlich auf der Terminseite, kurz in der Übersicht.
 * Läuft nach der Absatzformatierung (wpautop), damit der Kasten unverändert bleibt.
 *
 * @param string $inhalt Inhalt
 * @return string
 */
function el_team_termine_content( $inhalt ) {
	if ( 'termin' !== get_post_type() || ! in_the_loop() || ! is_main_query() || doing_filter( 'get_the_excerpt' ) ) {
		return $inhalt;
	}
	return el_team_termin_info_html( null, ! is_singular( 'termin' ) ) . $inhalt;
}
add_filter( 'the_content', 'el_team_termine_content', 20 );

/**
 * Terminliste (Shortcode [termine], Startseite des Themes).
 *
 * @param WP_Post[] $termine Termine
 * @return string HTML
 */
function el_team_termine_liste_html( $termine ) {
	if ( ! $termine ) {
		return '';
	}
	$status = el_team_termin_status_liste();
	ob_start();
	?>
	<ul class="el-termine">
		<?php
		foreach ( $termine as $termin ) :
			$d = el_team_termin_daten( $termin );
			if ( ! $d ) {
				continue;
			}
			$ts         = $d['beginn']->getTimestamp();
			$mehrtaegig = $d['ende'] && $d['ende']->format( 'Y-m-d' ) !== $d['beginn']->format( 'Y-m-d' );
			if ( $mehrtaegig ) {
				$wann = wp_date( 'j.n.', $ts ) . '–' . wp_date( 'j.n.', $d['ende']->getTimestamp() );
			} elseif ( $d['ganztags'] ) {
				$wann = wp_date( 'l', $ts );
			} else {
				/* translators: 1: Wochentag, 2: Uhrzeit */
				$wann = sprintf( __( '%1$s, %2$s Uhr', 'el-team-termine' ), wp_date( 'l', $ts ), wp_date( 'G:i', $ts ) );
			}
			$meta = array_filter( array( $wann, $d['ort'] ) );
			?>
			<li class="el-termine__eintrag<?php echo '' !== $d['status'] ? ' is-' . esc_attr( $d['status'] ) : ''; ?>">
				<time class="el-termine__datum" datetime="<?php echo esc_attr( $d['beginn']->format( 'c' ) ); ?>">
					<span class="el-termine__tag"><?php echo esc_html( wp_date( 'j', $ts ) ); ?></span>
					<span class="el-termine__monat"><?php echo esc_html( wp_date( 'M', $ts ) ); ?></span>
				</time>
				<span class="el-termine__text">
					<a class="el-termine__titel" href="<?php echo esc_url( get_permalink( $termin ) ); ?>"><?php echo esc_html( get_the_title( $termin ) ); ?></a>
					<span class="el-termine__meta">
						<?php if ( '' !== $d['status'] && isset( $status[ $d['status'] ] ) ) : ?>
							<strong class="el-termine__status"><?php echo esc_html( ucfirst( $status[ $d['status'] ] ) ); ?>:</strong>
						<?php endif; ?>
						<?php echo esc_html( implode( ' · ', $meta ) ); ?>
					</span>
				</span>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
	return ob_get_clean();
}

/**
 * Shortcode [termine anzahl="5" zeitraum="kommend" art=""].
 *
 * zeitraum: kommend (Standard), vergangen oder alle; art: Slug einer Terminart.
 *
 * @param array $atts Attribute
 * @return string HTML
 */
function el_team_termine_shortcode( $atts ) {
	$atts    = shortcode_atts( array(
		'anzahl'   => 5,
		'zeitraum' => 'kommend',
		'art'      => '',
	), $atts, 'termine' );
	$termine = el_team_termine_query( array(
		'anzahl'   => max( 1, min( 50, (int) $atts['anzahl'] ) ),
		'zeitraum' => in_array( $atts['zeitraum'], array( 'kommend', 'vergangen', 'alle' ), true ) ? $atts['zeitraum'] : 'kommend',
		'art'      => $atts['art'],
	) )->posts;
	if ( ! $termine ) {
		return '<p class="el-termine-leer">' . esc_html__( 'Zurzeit stehen keine Termine an.', 'el-team-termine' ) . '</p>';
	}
	return el_team_termine_liste_html( $termine );
}
add_shortcode( 'termine', 'el_team_termine_shortcode' );

/**
 * Strukturierte Daten (schema.org/Event) auf der Terminseite, damit Suchmaschinen den Termin erkennen.
 */
function el_team_termine_json_ld() {
	if ( ! is_singular( 'termin' ) ) {
		return;
	}
	$post = get_queried_object();
	$d    = el_team_termin_daten( $post );
	if ( ! $d ) {
		return;
	}
	$format = $d['ganztags'] ? 'Y-m-d' : 'c';
	$daten  = array(
		'@context'            => 'https://schema.org',
		'@type'               => 'Event',
		'name'                => wp_strip_all_tags( get_the_title( $post ) ),
		'startDate'           => $d['beginn']->format( $format ),
		'eventStatus'         => 'abgesagt' === $d['status'] ? 'https://schema.org/EventCancelled' : ( 'verschoben' === $d['status'] ? 'https://schema.org/EventPostponed' : 'https://schema.org/EventScheduled' ),
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'url'                 => get_permalink( $post ),
		'organizer'           => array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		),
	);
	if ( $d['ende'] ) {
		$daten['endDate'] = $d['ende']->format( $format );
	}
	if ( '' !== $d['ort'] || '' !== $d['adresse'] ) {
		$daten['location'] = array(
			'@type'   => 'Place',
			'name'    => '' !== $d['ort'] ? $d['ort'] : $d['adresse'],
			'address' => '' !== $d['adresse'] ? $d['adresse'] : $d['ort'],
		);
	}
	if ( has_excerpt( $post ) ) {
		$daten['description'] = wp_strip_all_tags( get_the_excerpt( $post ) );
	}
	if ( has_post_thumbnail( $post ) ) {
		$daten['image'] = get_the_post_thumbnail_url( $post, 'large' );
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $daten, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
add_action( 'wp_head', 'el_team_termine_json_ld' );
