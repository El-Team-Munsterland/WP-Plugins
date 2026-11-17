<?php
/**
 * Plugin Name: SpaceAPI Status
 * Description: Eigene öffentliche SpaceAPI (Version 15) unter /spaceapi.json aus eigenen Angaben, ausgewählten Feldern einer versteckten SpaceAPI und Werkstatt-Öffnungen aus "El-Team Termine"; dazu Status-Anzeige, Header-Leiste und Shortcodes.
 * Version: 2.2.0
 * Author: Lukas Nacke
 * License: GPL-2.0-or-later
 * Update URI: https://github.com/El-Team-Munsterland/WP-Plugins/spaceapi-status
 */
if (!defined('ABSPATH')) exit;
define('SPACEAPI_STATUS_VERSION','2.2.0');

require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/source.php';
require_once __DIR__ . '/includes/public.php';
require_once __DIR__ . '/includes/display.php';
require_once __DIR__ . '/includes/github-updater.php';

// Updates aus den GitHub-Releases (Tag spaceapi-status/v…), siehe RELEASING.md im Repo
new El_Team_GitHub_Updater_1(__FILE__);

/** Beim Aktivieren die Adresse /spaceapi.json anlegen. */
function spaceapi_status_activate(){ spaceapi_status_rewrite(); flush_rewrite_rules(); }
register_activation_hook(__FILE__,'spaceapi_status_activate');
