<?php
/**
 * Inhaltstyp "Termin" (termin) und Taxonomie "Terminart" (terminart).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registriert Inhaltstyp und Taxonomie.
 */
function el_team_termine_register() {
	register_post_type( 'termin', array(
		'labels'        => array(
			'name'               => __( 'Termine', 'el-team-termine' ),
			'singular_name'      => __( 'Termin', 'el-team-termine' ),
			'add_new'            => __( 'Neuer Termin', 'el-team-termine' ),
			'add_new_item'       => __( 'Neuen Termin anlegen', 'el-team-termine' ),
			'edit_item'          => __( 'Termin bearbeiten', 'el-team-termine' ),
			'new_item'           => __( 'Neuer Termin', 'el-team-termine' ),
			'view_item'          => __( 'Termin ansehen', 'el-team-termine' ),
			'view_items'         => __( 'Termine ansehen', 'el-team-termine' ),
			'search_items'       => __( 'Termine durchsuchen', 'el-team-termine' ),
			'not_found'          => __( 'Keine Termine gefunden.', 'el-team-termine' ),
			'not_found_in_trash' => __( 'Keine Termine im Papierkorb.', 'el-team-termine' ),
			'all_items'          => __( 'Alle Termine', 'el-team-termine' ),
			'archives'           => __( 'Termine', 'el-team-termine' ),
			'menu_name'          => __( 'Termine', 'el-team-termine' ),
		),
		'public'        => true,
		'has_archive'   => 'termine',
		'rewrite'       => array( 'slug' => 'termine', 'with_front' => false ),
		'show_in_rest'  => true,
		'menu_position' => 5,
		'menu_icon'     => 'dashicons-calendar-alt',
		'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
	) );

	register_taxonomy( 'terminart', 'termin', array(
		'labels'            => array(
			'name'          => __( 'Terminarten', 'el-team-termine' ),
			'singular_name' => __( 'Terminart', 'el-team-termine' ),
			'add_new_item'  => __( 'Neue Terminart', 'el-team-termine' ),
			'edit_item'     => __( 'Terminart bearbeiten', 'el-team-termine' ),
			'search_items'  => __( 'Terminarten durchsuchen', 'el-team-termine' ),
			'all_items'     => __( 'Alle Terminarten', 'el-team-termine' ),
			'menu_name'     => __( 'Terminarten', 'el-team-termine' ),
		),
		'hierarchical'      => true,
		'public'            => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array( 'slug' => 'terminart', 'with_front' => false ),
	) );
}
add_action( 'init', 'el_team_termine_register' );
