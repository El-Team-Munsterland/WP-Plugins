<?php
/**
 * Werkstatt-Öffnungen: Termine, bei denen die Werkstatt geöffnet ist.
 *
 * Pro Termin: "automatisch" (nach Terminart), "ja" oder "nein" (_el_termin_oeffnung).
 * Pro Terminart: Voreinstellung "Termine dieser Art sind Werkstatt-Öffnungen" (el_oeffnung).
 * Genutzt vom Plugin "SpaceAPI Status" für zukünftige Öffnungen und vom Kalender
 * ?el_termin_ics=oeffnungen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ist bei diesem Termin die Werkstatt geöffnet?
 *
 * @param int|WP_Post|null $post Termin
 * @return bool
 */
function el_team_termin_ist_oeffnung( $post = null ) {
	$post = get_post( $post );
	if ( ! $post || 'termin' !== $post->post_type ) {
		return false;
	}
	$wert = (string) get_post_meta( $post->ID, '_el_termin_oeffnung', true );
	if ( 'ja' === $wert || 'nein' === $wert ) {
		return 'ja' === $wert;
	}
	// Automatisch: eine der Terminarten ist als Öffnung voreingestellt
	$arten = get_the_terms( $post, 'terminart' );
	if ( ! is_array( $arten ) ) {
		return false;
	}
	foreach ( $arten as $art ) {
		if ( get_term_meta( $art->term_id, 'el_oeffnung', true ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Kommende Werkstatt-Öffnungen (laufende eingeschlossen), ohne abgesagte und verschobene Termine.
 *
 * @param int $anzahl Anzahl
 * @return WP_Post[]
 */
function el_team_termine_oeffnungen( $anzahl = 5 ) {
	$gefunden = array();
	// In Blöcken lesen, bis genug Öffnungen gefunden sind (höchstens 200 Termine)
	for ( $seite = 1; $seite <= 10 && count( $gefunden ) < $anzahl; $seite++ ) {
		$termine = get_posts( el_team_termine_query_vars( 'kommend', array(
			'post_type'      => 'termin',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'paged'          => $seite,
		) ) );
		foreach ( $termine as $termin ) {
			$status = (string) get_post_meta( $termin->ID, '_el_termin_status', true );
			if ( '' === $status && el_team_termin_ist_oeffnung( $termin ) ) {
				$gefunden[] = $termin;
				if ( count( $gefunden ) >= $anzahl ) {
					break;
				}
			}
		}
		if ( count( $termine ) < 20 ) {
			break;
		}
	}
	return $gefunden;
}

/**
 * Auswahl im Kasten "Termin".
 *
 * @param WP_Post $post Termin
 */
function el_team_termine_oeffnung_feld( $post ) {
	$wert = (string) get_post_meta( $post->ID, '_el_termin_oeffnung', true );
	?>
	<tr>
		<th scope="row"><label for="el-termin-oeffnung"><?php esc_html_e( 'Werkstatt geöffnet', 'el-team-termine' ); ?></label></th>
		<td>
			<select id="el-termin-oeffnung" name="el_termin[oeffnung]">
				<option value="" <?php selected( $wert, '' ); ?>><?php esc_html_e( 'automatisch (nach Terminart)', 'el-team-termine' ); ?></option>
				<option value="ja" <?php selected( $wert, 'ja' ); ?>><?php esc_html_e( 'ja', 'el-team-termine' ); ?></option>
				<option value="nein" <?php selected( $wert, 'nein' ); ?>><?php esc_html_e( 'nein', 'el-team-termine' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Öffnungen erscheinen in der SpaceAPI als zukünftige Öffnung und im Öffnungs-Kalender. Welche Terminarten automatisch als Öffnung gelten, steht unter Termine → Terminarten.', 'el-team-termine' ); ?></p>
		</td>
	</tr>
	<?php
}

/**
 * Speichert die Auswahl (aufgerufen aus el_team_termine_save).
 *
 * @param int   $post_id Termin
 * @param array $in      Formulardaten
 */
function el_team_termine_oeffnung_speichern( $post_id, $in ) {
	$wert = isset( $in['oeffnung'] ) ? sanitize_key( $in['oeffnung'] ) : '';
	if ( 'ja' === $wert || 'nein' === $wert ) {
		update_post_meta( $post_id, '_el_termin_oeffnung', $wert );
	} else {
		delete_post_meta( $post_id, '_el_termin_oeffnung' );
	}
}

/**
 * Terminart: Feld beim Anlegen.
 */
function el_team_terminart_feld_neu() {
	?>
	<div class="form-field">
		<label><input type="checkbox" name="el_oeffnung" value="1"> <?php esc_html_e( 'Termine dieser Art sind Werkstatt-Öffnungen', 'el-team-termine' ); ?></label>
		<p><?php esc_html_e( 'Gilt für Termine mit „Werkstatt geöffnet: automatisch“.', 'el-team-termine' ); ?></p>
	</div>
	<?php
}
add_action( 'terminart_add_form_fields', 'el_team_terminart_feld_neu' );

/**
 * Terminart: Feld beim Bearbeiten.
 *
 * @param WP_Term $term Terminart
 */
function el_team_terminart_feld_bearbeiten( $term ) {
	?>
	<tr class="form-field">
		<th scope="row"><?php esc_html_e( 'Werkstatt-Öffnung', 'el-team-termine' ); ?></th>
		<td>
			<label><input type="checkbox" name="el_oeffnung" value="1" <?php checked( (bool) get_term_meta( $term->term_id, 'el_oeffnung', true ) ); ?>> <?php esc_html_e( 'Termine dieser Art sind Werkstatt-Öffnungen', 'el-team-termine' ); ?></label>
			<p class="description"><?php esc_html_e( 'Gilt für Termine mit „Werkstatt geöffnet: automatisch“.', 'el-team-termine' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'terminart_edit_form_fields', 'el_team_terminart_feld_bearbeiten' );

/**
 * Terminart: speichern (die Formulare sind von WordPress per Nonce geschützt).
 *
 * @param int $term_id Terminart
 */
function el_team_terminart_speichern( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	if ( ! empty( $_POST['el_oeffnung'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- Nonce prüft WordPress im Terminart-Formular.
		update_term_meta( $term_id, 'el_oeffnung', '1' );
	} else {
		delete_term_meta( $term_id, 'el_oeffnung' );
	}
}
add_action( 'created_terminart', 'el_team_terminart_speichern' );
add_action( 'edited_terminart', 'el_team_terminart_speichern' );

/**
 * Terminarten-Liste: Spalte "Öffnung".
 *
 * @param array $spalten Spalten
 * @return array
 */
function el_team_terminart_spalten( $spalten ) {
	$spalten['el_oeffnung'] = __( 'Werkstatt-Öffnung', 'el-team-termine' );
	return $spalten;
}
add_filter( 'manage_edit-terminart_columns', 'el_team_terminart_spalten' );

/**
 * Inhalt der Spalte "Öffnung".
 *
 * @param string $inhalt  Inhalt
 * @param string $spalte  Spalte
 * @param int    $term_id Terminart
 * @return string
 */
function el_team_terminart_spalte_inhalt( $inhalt, $spalte, $term_id ) {
	if ( 'el_oeffnung' === $spalte ) {
		return get_term_meta( $term_id, 'el_oeffnung', true ) ? esc_html__( 'ja', 'el-team-termine' ) : '–';
	}
	return $inhalt;
}
add_filter( 'manage_terminart_custom_column', 'el_team_terminart_spalte_inhalt', 10, 3 );
