<?php
/**
 * Adminbereich: Eingabekasten "Termin", Spalten und Sortierung der Terminliste.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Eingabekasten "Termin" beim Bearbeiten.
 */
function el_team_termine_add_meta_box() {
	add_meta_box( 'el-team-termin', __( 'Termin', 'el-team-termine' ), 'el_team_termine_render_meta_box', 'termin', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'el_team_termine_add_meta_box' );

/**
 * Teilt "Y-m-d H:i" in Datum und Uhrzeit für die Eingabefelder.
 *
 * @param string $wert gespeicherter Wert
 * @return array Datum (Y-m-d), Uhrzeit (H:i)
 */
function el_team_termine_split( $wert ) {
	$teile = explode( ' ', (string) $wert );
	return array( isset( $teile[0] ) ? $teile[0] : '', isset( $teile[1] ) ? $teile[1] : '' );
}

/**
 * Inhalt des Eingabekastens.
 *
 * @param WP_Post $post Termin
 */
function el_team_termine_render_meta_box( $post ) {
	wp_nonce_field( 'el_team_termin_speichern', 'el_team_termin_nonce' );
	list( $beginn_datum, $beginn_zeit ) = el_team_termine_split( get_post_meta( $post->ID, '_el_termin_beginn', true ) );
	list( $ende_datum, $ende_zeit )     = el_team_termine_split( get_post_meta( $post->ID, '_el_termin_ende', true ) );
	$ganztags  = (bool) get_post_meta( $post->ID, '_el_termin_ganztags', true );
	$status    = (string) get_post_meta( $post->ID, '_el_termin_status', true );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="el-termin-beginn"><?php esc_html_e( 'Beginn', 'el-team-termine' ); ?></label></th>
			<td>
				<input type="date" id="el-termin-beginn" name="el_termin[beginn_datum]" value="<?php echo esc_attr( $beginn_datum ); ?>" required>
				<input type="time" name="el_termin[beginn_zeit]" value="<?php echo esc_attr( $beginn_zeit ); ?>" aria-label="<?php esc_attr_e( 'Uhrzeit Beginn', 'el-team-termine' ); ?>">
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="el-termin-ende"><?php esc_html_e( 'Ende', 'el-team-termine' ); ?></label></th>
			<td>
				<input type="date" id="el-termin-ende" name="el_termin[ende_datum]" value="<?php echo esc_attr( $ende_datum ); ?>">
				<input type="time" name="el_termin[ende_zeit]" value="<?php echo esc_attr( $ende_zeit ); ?>" aria-label="<?php esc_attr_e( 'Uhrzeit Ende', 'el-team-termine' ); ?>">
				<p class="description"><?php esc_html_e( 'Optional. Nur eine Uhrzeit ohne Datum gilt für den Tag des Beginns.', 'el-team-termine' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Ganztägig', 'el-team-termine' ); ?></th>
			<td><label><input type="checkbox" name="el_termin[ganztags]" value="1" <?php checked( $ganztags ); ?>> <?php esc_html_e( 'ohne Uhrzeit (z. B. mehrtägiges Treffen)', 'el-team-termine' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="el-termin-ort"><?php esc_html_e( 'Ort', 'el-team-termine' ); ?></label></th>
			<td><input type="text" id="el-termin-ort" class="regular-text" name="el_termin[ort]" value="<?php echo esc_attr( get_post_meta( $post->ID, '_el_termin_ort', true ) ); ?>" placeholder="<?php esc_attr_e( 'z. B. Werkstatt Olfen', 'el-team-termine' ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="el-termin-adresse"><?php esc_html_e( 'Adresse', 'el-team-termine' ); ?></label></th>
			<td>
				<input type="text" id="el-termin-adresse" class="large-text" name="el_termin[adresse]" value="<?php echo esc_attr( get_post_meta( $post->ID, '_el_termin_adresse', true ) ); ?>" placeholder="<?php esc_attr_e( 'Straße Nr., PLZ Ort', 'el-team-termine' ); ?>">
				<p class="description"><?php esc_html_e( 'Mit Adresse erscheint ein Link „Auf der Karte zeigen“ (OpenStreetMap). Es wird keine Karte eingebettet.', 'el-team-termine' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="el-termin-anmeldung"><?php esc_html_e( 'Anmeldung / Infos', 'el-team-termine' ); ?></label></th>
			<td><input type="url" id="el-termin-anmeldung" class="large-text" name="el_termin[anmeldung]" value="<?php echo esc_attr( get_post_meta( $post->ID, '_el_termin_anmeldung', true ) ); ?>" placeholder="https://"></td>
		</tr>
		<tr>
			<th scope="row"><label for="el-termin-status"><?php esc_html_e( 'Status', 'el-team-termine' ); ?></label></th>
			<td>
				<select id="el-termin-status" name="el_termin[status]">
					<?php foreach ( el_team_termin_status_liste() as $wert => $bezeichnung ) : ?>
						<option value="<?php echo esc_attr( $wert ); ?>" <?php selected( $status, $wert ); ?>><?php echo esc_html( $bezeichnung ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Speichert die Termin-Felder.
 *
 * @param int $post_id Termin
 */
function el_team_termine_save( $post_id ) {
	if ( ! isset( $_POST['el_team_termin_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['el_team_termin_nonce'] ), 'el_team_termin_speichern' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in = isset( $_POST['el_termin'] ) && is_array( $_POST['el_termin'] ) ? wp_unslash( $_POST['el_termin'] ) : array();

	$datum = function ( $key ) use ( $in ) {
		$wert = isset( $in[ $key ] ) ? sanitize_text_field( $in[ $key ] ) : '';
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $wert ) ? $wert : '';
	};
	$zeit  = function ( $key ) use ( $in ) {
		$wert = isset( $in[ $key ] ) ? sanitize_text_field( $in[ $key ] ) : '';
		return preg_match( '/^\d{2}:\d{2}$/', $wert ) ? $wert : '';
	};

	$ganztags     = ! empty( $in['ganztags'] );
	$beginn_datum = $datum( 'beginn_datum' );
	$ende_datum   = $datum( 'ende_datum' );
	$ende_zeit    = $zeit( 'ende_zeit' );
	if ( '' === $ende_datum && '' !== $ende_zeit ) {
		$ende_datum = $beginn_datum;
	}

	$beginn = '' !== $beginn_datum ? $beginn_datum . ' ' . ( $ganztags || '' === $zeit( 'beginn_zeit' ) ? '00:00' : $zeit( 'beginn_zeit' ) ) : '';
	$ende   = '' !== $ende_datum ? $ende_datum . ' ' . ( $ganztags || '' === $ende_zeit ? '23:59' : $ende_zeit ) : '';
	if ( '' !== $ende && $ende < $beginn ) {
		$ende = '';
	}
	// Bis wann der Termin als "kommend" gilt
	if ( '' !== $ende ) {
		$bis = $ende;
	} elseif ( '' !== $beginn ) {
		$bis = $ganztags ? $beginn_datum . ' 23:59' : $beginn;
	} else {
		$bis = '';
	}

	$status = isset( $in['status'] ) ? sanitize_key( $in['status'] ) : '';
	if ( ! array_key_exists( $status, el_team_termin_status_liste() ) ) {
		$status = '';
	}

	$felder = array(
		'_el_termin_beginn'    => $beginn,
		'_el_termin_ende'      => $ende,
		'_el_termin_bis'       => $bis,
		'_el_termin_ganztags'  => $ganztags ? '1' : '',
		'_el_termin_ort'       => isset( $in['ort'] ) ? sanitize_text_field( $in['ort'] ) : '',
		'_el_termin_adresse'   => isset( $in['adresse'] ) ? sanitize_text_field( $in['adresse'] ) : '',
		'_el_termin_anmeldung' => isset( $in['anmeldung'] ) ? esc_url_raw( $in['anmeldung'] ) : '',
		'_el_termin_status'    => $status,
	);
	foreach ( $felder as $key => $wert ) {
		if ( '' === $wert ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $wert );
		}
	}
}
add_action( 'save_post_termin', 'el_team_termine_save' );

/**
 * Spalten der Terminliste.
 *
 * @param array $spalten Spalten
 * @return array
 */
function el_team_termine_columns( $spalten ) {
	$neu = array();
	foreach ( $spalten as $key => $titel ) {
		$neu[ $key ] = $titel;
		if ( 'title' === $key ) {
			$neu['el_termin_zeit'] = __( 'Termin', 'el-team-termine' );
			$neu['el_termin_ort']  = __( 'Ort', 'el-team-termine' );
		}
	}
	unset( $neu['date'] );
	return $neu;
}
add_filter( 'manage_termin_posts_columns', 'el_team_termine_columns' );

/**
 * Inhalt der Spalten.
 *
 * @param string $spalte  Spalte
 * @param int    $post_id Termin
 */
function el_team_termine_column_content( $spalte, $post_id ) {
	if ( 'el_termin_zeit' === $spalte ) {
		$text   = el_team_termin_zeit_text( $post_id );
		$status = (string) get_post_meta( $post_id, '_el_termin_status', true );
		echo esc_html( '' !== $text ? $text : __( '– kein Datum –', 'el-team-termine' ) );
		if ( '' !== $status ) {
			echo ' <strong>(' . esc_html( el_team_termin_status_liste()[ $status ] ) . ')</strong>';
		}
	} elseif ( 'el_termin_ort' === $spalte ) {
		echo esc_html( get_post_meta( $post_id, '_el_termin_ort', true ) );
	}
}
add_action( 'manage_termin_posts_custom_column', 'el_team_termine_column_content', 10, 2 );

/**
 * Spalte "Termin" ist sortierbar.
 *
 * @param array $spalten sortierbare Spalten
 * @return array
 */
function el_team_termine_sortable( $spalten ) {
	$spalten['el_termin_zeit'] = 'el_termin_zeit';
	return $spalten;
}
add_filter( 'manage_edit-termin_sortable_columns', 'el_team_termine_sortable' );

/**
 * Terminliste im Admin: nach Termin sortieren, ohne Auswahl die neuesten zuerst.
 *
 * @param WP_Query $query Abfrage
 */
function el_team_termine_admin_order( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'termin' !== $query->get( 'post_type' ) ) {
		return;
	}
	$orderby = $query->get( 'orderby' );
	if ( '' === $orderby || 'el_termin_zeit' === $orderby ) {
		// Termine ohne Datum (z. B. Entwürfe) bleiben in der Liste
		$query->set( 'meta_query', array(
			'relation'       => 'OR',
			'el_termin_zeit' => array(
				'key'     => '_el_termin_beginn',
				'compare' => 'EXISTS',
			),
			'el_termin_ohne' => array(
				'key'     => '_el_termin_beginn',
				'compare' => 'NOT EXISTS',
			),
		) );
		$query->set( 'orderby', 'el_termin_zeit' );
		if ( '' === $orderby ) {
			$query->set( 'order', 'DESC' );
		}
	}
}
add_action( 'pre_get_posts', 'el_team_termine_admin_order' );
