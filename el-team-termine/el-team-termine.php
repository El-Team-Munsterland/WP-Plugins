<?php
/**
 * Plugin Name: El-Team Termine
 * Description: Termine (Treffen, El-Café, Ausfahrten) mit Datum, Ort und Anmeldung; Übersicht kommender und vergangener Termine, Kalender-Download (iCal) und Shortcode [termine].
 * Version: 1.0.0
 * Requires at least: 5.5
 * Requires PHP: 7.4
 * Author: Lukas Nacke
 * License: GPL-3.0-or-later
 * Text Domain: el-team-termine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EL_TEAM_TERMINE_VERSION', '1.0.0' );
define( 'EL_TEAM_TERMINE_FILE', __FILE__ );
define( 'EL_TEAM_TERMINE_DIR', plugin_dir_path( __FILE__ ) );
define( 'EL_TEAM_TERMINE_URL', plugin_dir_url( __FILE__ ) );

require_once EL_TEAM_TERMINE_DIR . 'includes/post-type.php';
require_once EL_TEAM_TERMINE_DIR . 'includes/termin.php';
require_once EL_TEAM_TERMINE_DIR . 'includes/admin.php';
require_once EL_TEAM_TERMINE_DIR . 'includes/frontend.php';
require_once EL_TEAM_TERMINE_DIR . 'includes/ical.php';

/**
 * Beim Aktivieren: Inhaltstyp registrieren und Adressen (/termine/) neu aufbauen.
 */
function el_team_termine_activate() {
	el_team_termine_register();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'el_team_termine_activate' );

/**
 * Beim Deaktivieren: Adressen ohne /termine/ neu aufbauen. Die Termine selbst bleiben erhalten.
 */
function el_team_termine_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'el_team_termine_deactivate' );
