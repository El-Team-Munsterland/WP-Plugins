<?php
/**
 * Updates für El-Team-Plugins aus GitHub-Releases (Repo WP-Plugins), ohne den
 * WordPress-Marktplatz.
 *
 * Diese Datei ist die Vorlage; jedes Plugin enthält eine identische Kopie unter
 * includes/github-updater.php (bin/sync-updater.sh kopiert sie, der Release-Workflow
 * prüft, dass die Kopie aktuell ist).
 *
 * Ablauf:
 * - Das Plugin trägt im Kopf "Update URI: https://github.com/El-Team-Munsterland/WP-Plugins/<ordner>".
 *   WordPress fragt dann nicht den Marktplatz, sondern den Filter update_plugins_github.com.
 * - Releases heißen "<ordner>/v<version>" und haben das ZIP "<ordner>.zip" angehängt
 *   (.github/workflows/release.yml). Gesucht wird das höchste passende Release, keine Vorabversion.
 * - Die Antwort von GitHub wird 12 Stunden gespeichert, nach einem Fehler 1 Stunde.
 *   "Nach Updates suchen" in der Plugin-Liste und "Erneut prüfen" unter
 *   Dashboard → Aktualisierungen fragen sofort neu.
 * - Optional in wp-config.php: define( 'EL_TEAM_GITHUB_TOKEN', '…' ); für angemeldete Abfragen
 *   (GitHub erlaubt ohne Anmeldung 60 Abfragen pro Stunde je Server-IP).
 *
 * Der Klassenname enthält die Version dieser Datei, damit zwei Plugins mit unterschiedlichen
 * Kopien nebeneinander laufen können.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'El_Team_GitHub_Updater_1' ) ) {

	/**
	 * Update-Prüfung für ein Plugin.
	 */
	class El_Team_GitHub_Updater_1 {

		/** @var string Hauptdatei des Plugins (__FILE__) */
		private $file;

		/** @var string Ordner des Plugins, z. B. el-team-termine */
		private $slug;

		/** @var string ordner/datei.php */
		private $basename;

		/** @var string Repo "owner/name" */
		private $repo;

		/**
		 * @param string $file Hauptdatei des Plugins
		 * @param string $repo GitHub-Repo "owner/name"
		 */
		public function __construct( $file, $repo = 'El-Team-Munsterland/WP-Plugins' ) {
			$this->file     = $file;
			$this->slug     = basename( dirname( $file ) );
			$this->basename = plugin_basename( $file );
			$this->repo     = $repo;

			add_filter( 'update_plugins_github.com', array( $this, 'update_check' ), 10, 3 );
			add_filter( 'plugins_api', array( $this, 'details' ), 10, 3 );
			add_filter( 'upgrader_source_selection', array( $this, 'source_selection' ), 10, 4 );
			add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
			add_action( 'admin_post_el_team_update_check_' . $this->slug, array( $this, 'manual_check' ) );
			add_action( 'admin_notices', array( $this, 'manual_check_notice' ) );
			add_action( 'load-update-core.php', array( $this, 'force_check' ) );
		}

		/**
		 * Name des Zwischenspeichers.
		 *
		 * @return string
		 */
		private function cache_key() {
			return 'el_team_gh_release_' . md5( $this->repo . '|' . $this->slug );
		}

		/**
		 * Installierte Version aus dem Plugin-Kopf.
		 *
		 * @return string
		 */
		public function installed_version() {
			$daten = get_file_data( $this->file, array( 'Version' => 'Version' ) );
			return (string) $daten['Version'];
		}

		/**
		 * Neuestes Release dieses Plugins (gespeichert).
		 *
		 * @param bool $neu ohne Zwischenspeicher fragen
		 * @return array version, package, url, notes, date – oder error
		 */
		public function latest( $neu = false ) {
			$gespeichert = $neu ? false : get_site_transient( $this->cache_key() );
			if ( is_array( $gespeichert ) ) {
				return $gespeichert;
			}

			$kopf = array(
				'Accept'               => 'application/vnd.github+json',
				'X-GitHub-Api-Version' => '2022-11-28',
				'User-Agent'           => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
			);
			if ( defined( 'EL_TEAM_GITHUB_TOKEN' ) && EL_TEAM_GITHUB_TOKEN ) {
				$kopf['Authorization'] = 'Bearer ' . EL_TEAM_GITHUB_TOKEN;
			}
			$antwort = wp_remote_get( 'https://api.github.com/repos/' . $this->repo . '/releases?per_page=50', array(
				'timeout' => 10,
				'headers' => $kopf,
			) );

			$ergebnis = $this->parse( $antwort );
			set_site_transient( $this->cache_key(), $ergebnis, isset( $ergebnis['error'] ) ? HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS );
			return $ergebnis;
		}

		/**
		 * Wertet die Release-Liste von GitHub aus.
		 *
		 * @param array|WP_Error $antwort Antwort von wp_remote_get
		 * @return array
		 */
		private function parse( $antwort ) {
			if ( is_wp_error( $antwort ) ) {
				return array( 'error' => $antwort->get_error_message() );
			}
			$code = (int) wp_remote_retrieve_response_code( $antwort );
			if ( 200 !== $code ) {
				$rest = wp_remote_retrieve_header( $antwort, 'x-ratelimit-remaining' );
				return array( 'error' => '0' === (string) $rest ? 'GitHub-Abfragegrenze erreicht, später erneut versuchen.' : 'GitHub antwortet mit HTTP ' . $code . '.' );
			}
			$releases = json_decode( wp_remote_retrieve_body( $antwort ), true );
			if ( ! is_array( $releases ) ) {
				return array( 'error' => 'Antwort von GitHub ist kein gültiges JSON.' );
			}

			$praefix = $this->slug . '/v';
			$bestes  = null;
			foreach ( $releases as $release ) {
				$tag = isset( $release['tag_name'] ) ? (string) $release['tag_name'] : '';
				if ( 0 !== strpos( $tag, $praefix ) || ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) {
					continue;
				}
				$version = substr( $tag, strlen( $praefix ) );
				$paket   = '';
				foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
					if ( isset( $asset['name'], $asset['browser_download_url'] ) && $this->slug . '.zip' === $asset['name'] ) {
						$paket = $asset['browser_download_url'];
					}
				}
				if ( '' === $paket || ! preg_match( '/^\d+(\.\d+)*$/', $version ) ) {
					continue;
				}
				if ( null === $bestes || version_compare( $version, $bestes['version'], '>' ) ) {
					$bestes = array(
						'version' => $version,
						'package' => $paket,
						'url'     => isset( $release['html_url'] ) ? $release['html_url'] : 'https://github.com/' . $this->repo . '/releases',
						'notes'   => isset( $release['body'] ) ? (string) $release['body'] : '',
						'date'    => isset( $release['published_at'] ) ? (string) $release['published_at'] : '',
					);
				}
			}
			return $bestes ? $bestes : array( 'error' => 'Kein Release für ' . $this->slug . ' gefunden.' );
		}

		/**
		 * Filter update_plugins_github.com: meldet WordPress eine neue Version.
		 *
		 * @param array|false $update      bisheriges Ergebnis
		 * @param array       $plugin_data Kopfdaten des Plugins
		 * @param string      $plugin_file ordner/datei.php
		 * @return array|false
		 */
		public function update_check( $update, $plugin_data, $plugin_file ) {
			if ( $plugin_file !== $this->basename ) {
				return $update;
			}
			$neu = $this->latest();
			if ( isset( $neu['error'] ) ) {
				return $update;
			}
			// Auch ohne neuere Version zurückgeben: WordPress zeigt das Plugin dann als aktuell (und erlaubt automatische Updates)
			return array(
				'id'      => 'github.com/' . $this->repo . '/' . $this->slug,
				'slug'    => $this->slug,
				'version' => $neu['version'],
				'url'     => $neu['url'],
				'package' => $neu['package'],
			);
		}

		/**
		 * Fenster "Details ansehen" mit den Release-Notizen.
		 *
		 * @param false|object|array $ergebnis bisheriges Ergebnis
		 * @param string             $aktion   Aktion
		 * @param object             $args     Anfrage
		 * @return false|object|array
		 */
		public function details( $ergebnis, $aktion, $args ) {
			if ( 'plugin_information' !== $aktion || ! isset( $args->slug ) || $args->slug !== $this->slug ) {
				return $ergebnis;
			}
			$neu   = $this->latest();
			$daten = get_plugin_data( $this->file, false, true );
			$notiz = isset( $neu['notes'] ) && '' !== trim( $neu['notes'] ) ? $neu['notes'] : 'Keine Notizen zu diesem Release.';
			return (object) array(
				'name'          => $daten['Name'],
				'slug'          => $this->slug,
				'version'       => isset( $neu['version'] ) ? $neu['version'] : $this->installed_version(),
				'author'        => $daten['Author'],
				'homepage'      => 'https://github.com/' . $this->repo,
				'last_updated'  => isset( $neu['date'] ) ? $neu['date'] : '',
				'download_link' => isset( $neu['package'] ) ? $neu['package'] : '',
				'sections'      => array(
					'description' => wpautop( esc_html( $daten['Description'] ) ),
					'changelog'   => wpautop( esc_html( $notiz ) ),
				),
			);
		}

		/**
		 * Sorgt dafür, dass der entpackte Ordner genau wie das Plugin heißt (sonst würde es deaktiviert).
		 *
		 * @param string      $quelle    entpackter Ordner
		 * @param string      $remote    Arbeitsordner
		 * @param WP_Upgrader $upgrader  Upgrader
		 * @param array       $extra     plugin = ordner/datei.php
		 * @return string|WP_Error
		 */
		public function source_selection( $quelle, $remote, $upgrader, $extra = array() ) {
			if ( empty( $extra['plugin'] ) || $extra['plugin'] !== $this->basename ) {
				return $quelle;
			}
			$soll = trailingslashit( $remote ) . $this->slug . '/';
			if ( trailingslashit( $quelle ) === $soll ) {
				return $quelle;
			}
			global $wp_filesystem;
			if ( $wp_filesystem && $wp_filesystem->move( $quelle, $soll, true ) ) {
				return $soll;
			}
			return new WP_Error( 'el_team_update_ordner', 'Der Plugin-Ordner im Update-Paket konnte nicht umbenannt werden.' );
		}

		/**
		 * Link "Nach Updates suchen" in der Plugin-Liste.
		 *
		 * @param array  $links Links unter der Beschreibung
		 * @param string $datei ordner/datei.php
		 * @return array
		 */
		public function row_meta( $links, $datei ) {
			if ( $datei !== $this->basename || ! current_user_can( 'update_plugins' ) ) {
				return $links;
			}
			$url     = wp_nonce_url( admin_url( 'admin-post.php?action=el_team_update_check_' . $this->slug ), 'el_team_update_check_' . $this->slug );
			$links[] = '<a href="' . esc_url( $url ) . '">Nach Updates suchen</a>';
			$links[] = '<a href="' . esc_url( 'https://github.com/' . $this->repo . '/releases' ) . '">Releases auf GitHub</a>';
			return $links;
		}

		/**
		 * Manuelle Prüfung: GitHub sofort fragen, WordPress' Update-Liste neu aufbauen, zurück zur Plugin-Liste.
		 */
		public function manual_check() {
			if ( ! current_user_can( 'update_plugins' ) ) {
				wp_die( 'Keine Berechtigung.' );
			}
			check_admin_referer( 'el_team_update_check_' . $this->slug );
			$neu = $this->latest( true );
			delete_site_transient( 'update_plugins' );
			wp_update_plugins();

			if ( isset( $neu['error'] ) ) {
				$status = 'fehler';
			} elseif ( version_compare( $neu['version'], $this->installed_version(), '>' ) ) {
				$status = 'neu';
			} else {
				$status = 'aktuell';
			}
			wp_safe_redirect( add_query_arg( array(
				'el_team_update' => $this->slug,
				'status'         => $status,
			), admin_url( 'plugins.php' ) ) );
			exit;
		}

		/**
		 * Hinweis nach der manuellen Prüfung.
		 */
		public function manual_check_notice() {
			// phpcs:disable WordPress.Security.NonceVerification -- nur Anzeige, keine Aktion
			if ( ! isset( $_GET['el_team_update'], $_GET['status'] ) || $this->slug !== $_GET['el_team_update'] || ! current_user_can( 'update_plugins' ) ) {
				return;
			}
			$status = sanitize_key( $_GET['status'] );
			// phpcs:enable
			$name = get_plugin_data( $this->file, false, true )['Name'];
			$neu  = $this->latest();
			if ( 'neu' === $status && isset( $neu['version'] ) ) {
				$text  = sprintf( '%1$s: Version %2$s ist verfügbar (installiert: %3$s). Die Aktualisierung steht unten in der Liste bereit.', $name, $neu['version'], $this->installed_version() );
				$klasse = 'notice-warning';
			} elseif ( 'aktuell' === $status ) {
				$text  = sprintf( '%1$s ist aktuell (Version %2$s).', $name, $this->installed_version() );
				$klasse = 'notice-success';
			} else {
				$text  = sprintf( '%1$s: Prüfung fehlgeschlagen – %2$s', $name, isset( $neu['error'] ) ? $neu['error'] : 'unbekannter Fehler' );
				$klasse = 'notice-error';
			}
			echo '<div class="notice ' . esc_attr( $klasse ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
		}

		/**
		 * "Erneut prüfen" unter Dashboard → Aktualisierungen fragt auch GitHub sofort neu.
		 */
		public function force_check() {
			// phpcs:ignore WordPress.Security.NonceVerification -- WordPress' eigener Link, nur Zwischenspeicher leeren
			if ( ! empty( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) {
				delete_site_transient( $this->cache_key() );
			}
		}
	}
}
