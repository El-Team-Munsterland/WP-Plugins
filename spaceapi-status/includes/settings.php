<?php
/**
 * Einstellungen (Option spaceapi_status_settings) und Einstellungsseite Einstellungen → SpaceAPI.
 *
 * Bisherige Werte (endpoint, cache_ttl, header_*, field_labels) bleiben erhalten; neu sind die
 * öffentliche SpaceAPI (public_enabled, own, passthrough) und die Öffnungen aus den Terminen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Eigene Angaben für die öffentliche SpaceAPI: Schlüssel => Standardwert.
 *
 * @return array
 */
function spaceapi_status_own_defaults() {
	$zeitzone = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : '';
	return array(
		'space'         => get_bloginfo( 'name' ),
		'logo'          => '',
		'url'           => home_url( '/' ),
		'address'       => '',
		'lat'           => '',
		'lon'           => '',
		'timezone'      => preg_match( '~^[A-Za-z_]+/[A-Za-z_/+-]+$~', $zeitzone ) ? $zeitzone : '',
		'country_code'  => 'DE',
		'hint'          => '',
		'email'         => '',
		'ml'            => '',
		'phone'         => '',
		'matrix'        => '',
		'mastodon'      => '',
		'xmpp'          => '',
		'state_message' => '',
		'icon_open'     => '',
		'icon_closed'   => '',
		'feed_blog'     => 1,
		'links'         => '',
		'projects'      => '',
		'cam'           => '',
	);
}

/**
 * Standardwerte aller Einstellungen.
 *
 * @return array
 */
function spaceapi_status_defaults() {
	return array(
		'endpoint'          => 'https://ha-werkstatt.nacke.xyz/api/spaceapi',
		'cache_ttl'         => 300,
		'header_enabled'    => 0,
		'header_title'      => 'Space Status',
		'header_position'   => 'under_header',
		'field_labels'      => array(),
		'public_enabled'    => 0,
		'own'               => spaceapi_status_own_defaults(),
		'passthrough'       => array(),
		'openings_enabled'  => 1,
		'openings_count'    => 5,
		'openings_message'  => 1,
		'openings_calendar' => 1,
	);
}

/**
 * Gespeicherte Einstellungen, ergänzt um die Standardwerte (auch innerhalb von "own").
 *
 * @return array
 */
function spaceapi_status_get_settings() {
	$s        = get_option( 'spaceapi_status_settings', array() );
	$s        = is_array( $s ) ? $s : array();
	$defaults = spaceapi_status_defaults();
	$merged   = wp_parse_args( $s, $defaults );
	$merged['own'] = wp_parse_args( isset( $s['own'] ) && is_array( $s['own'] ) ? $s['own'] : array(), $defaults['own'] );
	return $merged;
}

/**
 * Bereinigt die Eingaben der Einstellungsseite.
 *
 * @param array $in Formulardaten
 * @return array
 */
function spaceapi_status_sanitize_settings( $in ) {
	$d  = spaceapi_status_defaults();
	$in = is_array( $in ) ? $in : array();

	$u   = isset( $in['endpoint'] ) ? trim( $in['endpoint'] ) : $d['endpoint'];
	$u   = wp_http_validate_url( $u ) ? $u : $d['endpoint'];
	$ttl = min( absint( $in['cache_ttl'] ?? $d['cache_ttl'] ), 86400 );
	$pos = sanitize_key( $in['header_position'] ?? $d['header_position'] );
	if ( ! in_array( $pos, array( 'under_header', 'after_body_open' ), true ) ) {
		$pos = $d['header_position'];
	}
	$labels = array();
	foreach ( (array) ( $in['field_labels'] ?? array() ) as $p => $l ) {
		$p = sanitize_text_field( $p );
		$l = sanitize_text_field( $l );
		if ( '' !== $p && '' !== $l ) {
			$labels[ $p ] = $l;
		}
	}

	// Eigene Angaben
	$own_in = isset( $in['own'] ) && is_array( $in['own'] ) ? $in['own'] : array();
	$own    = array();
	foreach ( $d['own'] as $key => $standard ) {
		$wert = isset( $own_in[ $key ] ) ? $own_in[ $key ] : '';
		switch ( $key ) {
			case 'logo':
			case 'url':
			case 'icon_open':
			case 'icon_closed':
				$own[ $key ] = esc_url_raw( trim( $wert ) );
				break;
			case 'lat':
			case 'lon':
				$wert        = str_replace( ',', '.', trim( (string) $wert ) );
				$own[ $key ] = is_numeric( $wert ) ? (string) (float) $wert : '';
				break;
			case 'country_code':
				$own[ $key ] = preg_match( '/^[A-Za-z]{2}$/', trim( $wert ) ) ? strtoupper( trim( $wert ) ) : '';
				break;
			case 'email':
			case 'ml':
				$own[ $key ] = sanitize_email( $wert );
				break;
			case 'feed_blog':
				$own[ $key ] = empty( $wert ) ? 0 : 1;
				break;
			case 'links':
			case 'projects':
			case 'cam':
				$own[ $key ] = sanitize_textarea_field( $wert );
				break;
			default:
				$own[ $key ] = sanitize_text_field( $wert );
		}
	}

	// Weiterzugebende Felder der versteckten SpaceAPI (Punktnotation)
	$passthrough = array();
	foreach ( (array) ( $in['passthrough'] ?? array() ) as $pfad ) {
		$pfad = preg_replace( '/[^A-Za-z0-9_.\-]/', '', (string) $pfad );
		if ( '' !== $pfad ) {
			$passthrough[] = $pfad;
		}
	}

	return array(
		'endpoint'          => esc_url_raw( $u ),
		'cache_ttl'         => $ttl,
		'header_enabled'    => ! empty( $in['header_enabled'] ) ? 1 : 0,
		'header_title'      => sanitize_text_field( $in['header_title'] ?? $d['header_title'] ),
		'header_position'   => $pos,
		'field_labels'      => $labels,
		'public_enabled'    => ! empty( $in['public_enabled'] ) ? 1 : 0,
		'own'               => $own,
		'passthrough'       => array_values( array_unique( $passthrough ) ),
		'openings_enabled'  => ! empty( $in['openings_enabled'] ) ? 1 : 0,
		'openings_count'    => max( 1, min( 20, absint( $in['openings_count'] ?? $d['openings_count'] ) ) ),
		'openings_message'  => ! empty( $in['openings_message'] ) ? 1 : 0,
		'openings_calendar' => ! empty( $in['openings_calendar'] ) ? 1 : 0,
	);
}

/**
 * Registriert die Einstellung.
 */
function spaceapi_status_settings() {
	register_setting( 'spaceapi_status', 'spaceapi_status_settings', array(
		'type'              => 'array',
		'sanitize_callback' => 'spaceapi_status_sanitize_settings',
		'default'           => spaceapi_status_defaults(),
	) );
}
add_action( 'admin_init', 'spaceapi_status_settings' );

/**
 * Menüpunkt Einstellungen → SpaceAPI.
 */
function spaceapi_status_menu() {
	add_options_page( 'SpaceAPI', 'SpaceAPI', 'manage_options', 'spaceapi-status', 'spaceapi_status_page' );
}
add_action( 'admin_menu', 'spaceapi_status_menu' );

/**
 * Textfeld-Zeile der Einstellungsseite.
 *
 * @param string $key         Schlüssel in "own"
 * @param string $titel       Beschriftung
 * @param array  $own         eigene Angaben
 * @param string $typ         Eingabetyp
 * @param string $beschreibung Hinweis unter dem Feld
 */
function spaceapi_status_own_row( $key, $titel, $own, $typ = 'text', $beschreibung = '' ) {
	$id = 'spaceapi-own-' . $key;
	?>
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $titel ); ?></label></th>
		<td>
			<?php if ( 'textarea' === $typ ) : ?>
				<textarea id="<?php echo esc_attr( $id ); ?>" class="large-text" rows="3" name="spaceapi_status_settings[own][<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( $own[ $key ] ); ?></textarea>
			<?php else : ?>
				<input id="<?php echo esc_attr( $id ); ?>" type="<?php echo esc_attr( $typ ); ?>" class="regular-text" name="spaceapi_status_settings[own][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $own[ $key ] ); ?>">
			<?php endif; ?>
			<?php if ( '' !== $beschreibung ) : ?>
				<p class="description"><?php echo esc_html( $beschreibung ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

/**
 * Einstellungsseite.
 */
function spaceapi_status_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s       = spaceapi_status_get_settings();
	$own     = $s['own'];
	$r       = spaceapi_status_fetch();
	$fields  = 'ok' === $r['status'] ? spaceapi_status_flatten( $r['data'] ) : array();
	$kandidaten = 'ok' === $r['status'] ? spaceapi_status_passthrough_candidates( $r['data'] ) : array();
	// Gewählte Felder, die die versteckte API gerade nicht liefert, trotzdem anzeigen
	foreach ( $s['passthrough'] as $pfad ) {
		if ( ! array_key_exists( $pfad, $kandidaten ) ) {
			$kandidaten[ $pfad ] = null;
		}
	}
	$termine = function_exists( 'el_team_termine_oeffnungen' );
	?>
	<div class="wrap">
		<h1>SpaceAPI</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'spaceapi_status' ); ?>

			<h2>Öffentliche SpaceAPI</h2>
			<p>Eigene SpaceAPI nach Version 15 unter <a href="<?php echo esc_url( spaceapi_status_public_url() ); ?>"><code><?php echo esc_html( spaceapi_status_public_url() ); ?></code></a>. Sie besteht aus den eigenen Angaben, ausgewählten Feldern der versteckten SpaceAPI und den Werkstatt-Öffnungen aus den Terminen. Die Adresse der versteckten SpaceAPI wird nie ausgegeben.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Ausgabe</th>
					<td><label><input name="spaceapi_status_settings[public_enabled]" type="checkbox" value="1" <?php checked( $s['public_enabled'], 1 ); ?>> Öffentliche SpaceAPI ausliefern</label></td>
				</tr>
			</table>

			<h3>Eigene Angaben</h3>
			<p>Alles, was die versteckte SpaceAPI nicht (oder nicht in Version 15) liefert. Pflicht sind Name, Logo und Website.</p>
			<table class="form-table" role="presentation">
				<?php
				spaceapi_status_own_row( 'space', 'Name', $own );
				spaceapi_status_own_row( 'logo', 'Logo (URL)', $own, 'url', 'Ohne Angabe wird das Logo der Website verwendet.' );
				spaceapi_status_own_row( 'url', 'Website', $own, 'url' );
				spaceapi_status_own_row( 'address', 'Adresse', $own, 'text', 'z. B. Musterstraße 1, 59399 Olfen, Germany' );
				spaceapi_status_own_row( 'lat', 'Breitengrad', $own, 'text', 'z. B. 51.7189 – nur zusammen mit dem Längengrad' );
				spaceapi_status_own_row( 'lon', 'Längengrad', $own, 'text', 'z. B. 7.3786' );
				spaceapi_status_own_row( 'timezone', 'Zeitzone', $own, 'text', 'z. B. Europe/Berlin' );
				spaceapi_status_own_row( 'country_code', 'Ländercode', $own, 'text', 'ISO 3166, z. B. DE' );
				spaceapi_status_own_row( 'hint', 'Wegbeschreibung', $own, 'text', 'Hinweis, wie man hineinkommt, falls das an der Adresse nicht offensichtlich ist' );
				spaceapi_status_own_row( 'email', 'Kontakt: E-Mail', $own, 'email' );
				spaceapi_status_own_row( 'ml', 'Kontakt: Mailingliste', $own, 'email' );
				spaceapi_status_own_row( 'phone', 'Kontakt: Telefon', $own, 'text', 'mit Ländervorwahl, z. B. +49 2595 …' );
				spaceapi_status_own_row( 'matrix', 'Kontakt: Matrix', $own, 'text', 'z. B. #el-team:matrix.org' );
				spaceapi_status_own_row( 'mastodon', 'Kontakt: Mastodon', $own, 'text', 'z. B. @elteam@chaos.social' );
				spaceapi_status_own_row( 'xmpp', 'Kontakt: XMPP-Raum', $own );
				spaceapi_status_own_row( 'state_message', 'Statustext', $own, 'text', 'Freitext (state.message). Bei geschlossener Werkstatt ersetzt ihn auf Wunsch die nächste Öffnung.' );
				spaceapi_status_own_row( 'icon_open', 'Icon geöffnet (URL)', $own, 'url', 'Nur zusammen mit dem Icon „geschlossen“.' );
				spaceapi_status_own_row( 'icon_closed', 'Icon geschlossen (URL)', $own, 'url' );
				spaceapi_status_own_row( 'links', 'Links', $own, 'textarea', 'Eine Zeile je Link: Name | URL | Beschreibung (optional)' );
				spaceapi_status_own_row( 'projects', 'Projekte', $own, 'textarea', 'Eine URL je Zeile' );
				spaceapi_status_own_row( 'cam', 'Webcams', $own, 'textarea', 'Eine URL je Zeile' );
				?>
				<tr>
					<th scope="row">Feed</th>
					<td><label><input type="hidden" name="spaceapi_status_settings[own][feed_blog]" value="0"><input name="spaceapi_status_settings[own][feed_blog]" type="checkbox" value="1" <?php checked( $own['feed_blog'], 1 ); ?>> RSS-Feed der Website als <code>feeds.blog</code> angeben</label></td>
				</tr>
			</table>

			<h3>Aus der versteckten SpaceAPI weitergeben</h3>
			<p>Nur angehakte Felder werden öffentlich. Ein weitergegebener Wert ersetzt die eigene Angabe. Listen (z. B. Sensoren) werden als Ganzes weitergegeben. Ist die versteckte SpaceAPI nicht erreichbar, fehlen diese Felder – ein fehlendes <code>state.open</code> bedeutet laut Standard „Status gerade unbekannt“.</p>
			<?php if ( 'ok' !== $r['status'] ) : ?>
				<p><strong>Versteckte SpaceAPI nicht erreichbar:</strong> <?php echo esc_html( $r['message'] ); ?></p>
			<?php endif; ?>
			<?php if ( $kandidaten ) : ?>
				<table class="widefat striped" style="max-width: 980px;">
					<thead><tr><th style="width: 3em;">Weitergeben</th><th>Feld</th><th>Aktueller Wert</th></tr></thead>
					<tbody>
						<?php foreach ( $kandidaten as $pfad => $wert ) : ?>
							<tr>
								<td><input type="checkbox" name="spaceapi_status_settings[passthrough][]" value="<?php echo esc_attr( $pfad ); ?>" <?php checked( in_array( $pfad, $s['passthrough'], true ) ); ?> aria-label="<?php echo esc_attr( $pfad ); ?> weitergeben"></td>
								<td><code><?php echo esc_html( $pfad ); ?></code><?php echo 'state.trigger_person' === $pfad ? ' <span class="description">(Name einer Person – nur mit Einverständnis)</span>' : ''; ?></td>
								<td><?php echo null === $wert ? '<em>wird gerade nicht geliefert</em>' : esc_html( wp_trim_words( spaceapi_status_format( $wert ), 20 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h3>Werkstatt-Öffnungen aus den Terminen</h3>
			<?php if ( ! $termine ) : ?>
				<p>Dafür muss das Plugin <strong>El-Team Termine</strong> (ab 1.1) aktiv sein.</p>
			<?php else : ?>
				<p>Termine mit „Werkstatt geöffnet“ (direkt am Termin oder über die Terminart) erscheinen als zukünftige Öffnungen. Die SpaceAPI hat dafür kein eigenes Feld; deshalb werden sie als Kalender (<code>feeds.calendar</code>), als Zusatzfeld <code>ext_upcoming_openings</code> und auf Wunsch im Statustext angegeben. Ein Termin ändert nie den Status geöffnet/geschlossen.</p>
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Öffnungen</th>
					<td>
						<label><input name="spaceapi_status_settings[openings_enabled]" type="checkbox" value="1" <?php checked( $s['openings_enabled'], 1 ); ?>> Nächste Öffnungen als <code>ext_upcoming_openings</code> angeben</label>, Anzahl
						<input name="spaceapi_status_settings[openings_count]" type="number" min="1" max="20" class="small-text" value="<?php echo esc_attr( $s['openings_count'] ); ?>"><br>
						<label><input name="spaceapi_status_settings[openings_calendar]" type="checkbox" value="1" <?php checked( $s['openings_calendar'], 1 ); ?>> Öffnungs-Kalender als <code>feeds.calendar</code> angeben</label><br>
						<label><input name="spaceapi_status_settings[openings_message]" type="checkbox" value="1" <?php checked( $s['openings_message'], 1 ); ?>> Bei geschlossener Werkstatt die nächste Öffnung als Statustext (<code>state.message</code>)</label>
					</td>
				</tr>
			</table>

			<h2>Verbindung zur versteckten SpaceAPI</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">SpaceAPI Endpoint</th><td><input name="spaceapi_status_settings[endpoint]" type="url" class="regular-text" value="<?php echo esc_attr( $s['endpoint'] ); ?>"></td></tr>
				<tr><th scope="row">Cache-Dauer</th><td><input name="spaceapi_status_settings[cache_ttl]" type="number" min="0" max="86400" value="<?php echo esc_attr( $s['cache_ttl'] ); ?>"> Sekunden (0 = aus)</td></tr>
			</table>

			<h2>Header-Anzeige</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Anzeige</th><td><label><input name="spaceapi_status_settings[header_enabled]" type="checkbox" value="1" <?php checked( $s['header_enabled'], 1 ); ?>> Im Header anzeigen</label></td></tr>
				<tr><th scope="row">Titel</th><td><input name="spaceapi_status_settings[header_title]" type="text" class="regular-text" value="<?php echo esc_attr( $s['header_title'] ); ?>"></td></tr>
				<tr><th scope="row">Position</th><td><label><input name="spaceapi_status_settings[header_position]" type="radio" value="under_header" <?php checked( $s['header_position'], 'under_header' ); ?>> Direkt unter dem Header</label><br><label><input name="spaceapi_status_settings[header_position]" type="radio" value="after_body_open" <?php checked( $s['header_position'], 'after_body_open' ); ?>> Direkt nach dem &lt;body&gt;-Tag</label></td></tr>
			</table>

			<h2>Beschriftungen für <code>[spaceapi]</code></h2>
			<p>Diese Liste wird aus der aktuellen Antwort der versteckten SpaceAPI erzeugt. Das Label wird zum Standard-Label für <code>[spaceapi]</code>.</p>
			<?php if ( 'ok' !== $r['status'] ) : ?>
				<p><strong>Fehler:</strong> <?php echo esc_html( $r['message'] ); ?></p>
			<?php elseif ( empty( $fields ) ) : ?>
				<p>Keine Felder gefunden.</p>
			<?php else : ?>
				<table class="widefat striped"><thead><tr><th>API-Feld</th><th>Aktueller Wert</th><th>Label</th></tr></thead><tbody>
				<?php
				foreach ( $fields as $p => $f ) {
					$label = $s['field_labels'][ $p ] ?? ucwords( str_replace( array( '_', '-' ), ' ', false !== strrchr( $p, '.' ) ? substr( strrchr( $p, '.' ), 1 ) : $p ) );
					?>
					<tr><td><code><?php echo esc_html( $p ); ?></code></td><td><?php echo esc_html( spaceapi_status_format( $f['value'] ) ); ?></td><td><input class="regular-text" name="spaceapi_status_settings[field_labels][<?php echo esc_attr( $p ); ?>]" value="<?php echo esc_attr( $label ); ?>"></td></tr>
					<?php
				}
				?>
				</tbody></table>
			<?php endif; ?>
			<?php submit_button( 'Einstellungen speichern' ); ?>
		</form>

		<hr>
		<h2>Vorschau der öffentlichen SpaceAPI</h2>
		<?php
		$probleme = spaceapi_status_public_problems( spaceapi_status_public_data() );
		if ( $probleme ) {
			echo '<div class="notice notice-warning inline"><p><strong>Noch nicht gültig nach Version 15:</strong></p><ul style="list-style: disc; margin-left: 2em;">';
			foreach ( $probleme as $problem ) {
				echo '<li>' . esc_html( $problem ) . '</li>';
			}
			echo '</ul></div>';
		}
		?>
		<pre style="max-height: 420px; overflow: auto; background: #fff; border: 1px solid #ccd0d4; padding: 12px;"><?php echo esc_html( wp_json_encode( spaceapi_status_public_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
		<p>Prüfen lässt sich die Ausgabe zusätzlich mit dem offiziellen Validator auf spaceapi.io.</p>

		<hr>
		<h2>Shortcodes</h2>
		<p><code>[space_status]</code> – Öffnungsstatus</p><p><code>[spaceapi field="space"]</code> – beliebiges Feld</p><p><code>[spaceapi field="state.open" label="Status"]</code> – eigenes Label</p><p><code>[spaceapi field="location.lat"]</code> – Label aus den Einstellungen</p><p><code>[spaceapi field="state.open" label="Status" fallback="Nicht verfügbar"]</code></p>
		<p>Verschachtelte Felder: <code>state.open</code>, <code>location.lat</code>; Array-Elemente: z. B. <code>temperature.0.value</code>. Die Shortcodes lesen weiterhin die versteckte SpaceAPI.</p>
	</div>
	<?php
}
