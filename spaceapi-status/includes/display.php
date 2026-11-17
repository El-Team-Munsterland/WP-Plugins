<?php
/**
 * Anzeige auf der Website: Status, Shortcodes [space_status] und [spaceapi], Statusleiste, Widget.
 * Die Anzeige liest weiterhin die versteckte SpaceAPI.
 */
if (!defined('ABSPATH')) exit;

/**
 * Statustext für die Anzeige auf der Website – nach denselben Regeln wie state.message in der
 * öffentlichen SpaceAPI: ein weitergegebener Text der versteckten SpaceAPI hat Vorrang, sonst
 * bei nicht geöffneter Werkstatt die nächste Öffnung aus den Terminen, sonst der eigene Statustext.
 * Neben "Geschlossen" entfällt das doppelte "Geschlossen –".
 *
 * @param bool|null $open Status aus der versteckten SpaceAPI (null = unbekannt)
 * @return string
 */
function spaceapi_status_display_message( $open ) {
	static $cache = array();
	$key = var_export( $open, true );
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$s    = spaceapi_status_get_settings();
	$text = '';
	if ( in_array( 'state.message', $s['passthrough'], true ) ) {
		$found = false;
		$m     = spaceapi_status_value( spaceapi_status_data(), 'state.message', $found );
		if ( $found && is_string( $m ) ) {
			$text = trim( $m );
		}
	}
	if ( '' === $text && true !== $open && ! empty( $s['openings_message'] ) && function_exists( 'el_team_termine_oeffnungen' ) ) {
		$oeffnungen = el_team_termine_oeffnungen( 1 );
		if ( $oeffnungen ) {
			$naechste = spaceapi_status_opening_text( $oeffnungen[0] );
			$text     = '' !== $naechste ? 'Nächste Öffnung: ' . $naechste : '';
		}
	}
	if ( '' === $text ) {
		$text = (string) $s['own']['state_message'];
	}
	return $cache[ $key ] = $text;
}

/**
 * Status als HTML: Punkt und "Geöffnet"/"Geschlossen"/"Unbekannt", optional letzte Änderung und Statustext.
 *
 * @param bool $updated "Zuletzt geändert" anzeigen
 * @param bool $message Statustext anzeigen (spaceapi_status_display_message)
 * @return string
 */
function spaceapi_status_render( $updated = true, $message = false ) {
	$r    = spaceapi_status_fetch();
	$open = null;
	if ( 'ok' !== $r['status'] ) {
		$h = '<span class="spaceapi-status spaceapi-status--error">Status nicht verfügbar</span>';
	} else {
		$d     = $r['data'];
		$found = false;
		$o     = spaceapi_status_value( $d, 'state.open', $found );
		$open  = ( $found && is_bool( $o ) ) ? $o : null;
		$label = true === $open ? 'Geöffnet' : ( false === $open ? 'Geschlossen' : 'Unbekannt' );
		$cl    = true === $open ? 'open' : ( false === $open ? 'closed' : 'unknown' );
		$h     = '<span class="spaceapi-status spaceapi-status--' . $cl . '"><span class="spaceapi-status__dot"></span><span>' . $label . '</span></span>';
		if ( $updated ) {
			$f = false;
			$t = spaceapi_status_value( $d, 'state.lastchange', $f );
			if ( $f && is_numeric( $t ) && $t > 0 ) {
				$h .= ' <span class="spaceapi-status__updated">Zuletzt geändert: ' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $t ) ) . '</span>';
			}
		}
	}
	if ( $message ) {
		$text = spaceapi_status_display_message( $open );
		if ( '' !== $text ) {
			$h .= ' <span class="spaceapi-status__message">' . esc_html( $text ) . '</span>';
		}
	}
	return $h;
}

/**
 * Shortcode [space_status show_updated="yes" show_message="no"].
 *
 * @param array $a Attribute
 * @return string
 */
function spaceapi_status_shortcode( $a ) {
	$a    = shortcode_atts( array( 'show_updated' => 'yes', 'show_message' => 'no' ), $a, 'space_status' );
	$nein = array( 'no', 'false', '0', 'nein' );
	$show = ! in_array( strtolower( $a['show_updated'] ), $nein, true );
	$msg  = ! in_array( strtolower( $a['show_message'] ), $nein, true );
	return '<div class="spaceapi-status-wrapper">' . spaceapi_status_render( $show, $msg ) . '</div>';
}
add_shortcode('space_status','spaceapi_status_shortcode');

function spaceapi_status_field_shortcode($a){
 $a=shortcode_atts(array('field'=>'','label'=>'','fallback'=>'','class'=>''),$a,'spaceapi');$field=trim($a['field']);if($field==='')return '';
 $d=spaceapi_status_data();$found=false;$v=spaceapi_status_value($d,$field,$found);if(!$found){if($a['fallback']==='')return ''; $v=$a['fallback'];}
 $s=spaceapi_status_get_settings();$label=trim($a['label']);if($label===''&&!empty($s['field_labels'][$field]))$label=$s['field_labels'][$field];
 $cl='spaceapi-field'.($a['class']!==''?' '.preg_replace('/[^A-Za-z0-9_-]/','',$a['class']):'');
 return '<span class="'.esc_attr($cl).'">'.($label!==''?'<span class="spaceapi-field__label">'.esc_html($label).': </span>':'').'<span class="spaceapi-field__value">'.esc_html(spaceapi_status_format($v)).'</span></span>';
}
add_shortcode('spaceapi','spaceapi_status_field_shortcode');

function spaceapi_status_css(){if(is_admin())return;wp_register_style('spaceapi-status',false,array(),SPACEAPI_STATUS_VERSION);wp_enqueue_style('spaceapi-status');
 wp_add_inline_style('spaceapi-status','.spaceapi-status-wrapper{margin:1rem 0}.spaceapi-status{display:inline-flex;align-items:center;gap:.5rem;font-weight:600}.spaceapi-status__dot{width:.7rem;height:.7rem;border-radius:50%;background:currentColor;display:inline-block}.spaceapi-status--open{color:#15803d}.spaceapi-status--closed{color:#b91c1c}.spaceapi-status--unknown,.spaceapi-status--error{color:#6b7280}.spaceapi-status__updated{font-size:.9em;opacity:.75;margin-left:.35rem}.spaceapi-header-status{width:100%;box-sizing:border-box;display:flex;align-items:center;justify-content:center;gap:.75rem;padding:.65rem 1rem;border-bottom:1px solid rgba(0,0,0,.08);background:#fff}.spaceapi-header-status__title,.spaceapi-field__label{font-weight:600}.spaceapi-field{display:inline-flex;align-items:baseline}.spaceapi-status__message{font-weight:400}.spaceapi-status-wrapper .spaceapi-status__message{display:block;margin-top:.25rem}.spaceapi-header-status{flex-wrap:wrap;row-gap:.2rem;text-align:center}.spaceapi-header-status .spaceapi-status__message{margin-left:.35rem;opacity:.85}');
}
add_action('wp_enqueue_scripts','spaceapi_status_css');
function spaceapi_status_header(){ $s=spaceapi_status_get_settings();if(empty($s['header_enabled']))return;$t=trim($s['header_title'])?:'Space Status';echo '<div class="spaceapi-header-status" role="status"><span class="spaceapi-header-status__title">'.esc_html($t).'</span><span>'.spaceapi_status_render(false,!empty($s['header_message'])).'</span></div>'; }
function spaceapi_status_after_body(){ $s=spaceapi_status_get_settings();if(!empty($s['header_enabled'])&&$s['header_position']==='after_body_open')spaceapi_status_header(); }
// The get_header action fires BEFORE header.php, not afterwards.
// Buffer the full page and insert the bar after the actual closing header tag.
function spaceapi_status_start_buffer(){
 $s=spaceapi_status_get_settings();
 if(is_admin()||wp_doing_ajax()||empty($s['header_enabled'])||$s['header_position']!=='under_header')return;
 // Render BEFORE starting the output-buffer callback; never start buffers in a callback.
 global $spaceapi_status_header_markup;
 ob_start();
 spaceapi_status_header();
 $spaceapi_status_header_markup=ob_get_clean();
 ob_start('spaceapi_status_inject_below_header');
}
function spaceapi_status_inject_below_header($html){
 global $spaceapi_status_header_markup;
 if(empty($spaceapi_status_header_markup))return $html;
 // No WP calls, output buffering, or other side effects inside this callback.
 return preg_replace_callback('~</header\s*>~i',function($m)use($spaceapi_status_header_markup){
  return $m[0].$spaceapi_status_header_markup;
 },$html,1);
}
add_action('template_redirect','spaceapi_status_start_buffer',0);
add_action('wp_body_open','spaceapi_status_after_body',20);

/** WordPress legacy widget, available in Appearance > Widgets and block-based widget areas. */
class SpaceAPI_Status_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('spaceapi_status_widget', __('SpaceAPI Kurzstatus', 'spaceapi-status'), array(
            'classname' => 'spaceapi_status_widget',
            'description' => __('Zeigt den aktuellen SpaceAPI-Öffnungsstatus an.', 'spaceapi-status'),
        ));
    }

    public function widget($args, $instance) {
        $title = isset($instance['title']) ? $instance['title'] : __('Space Status', 'spaceapi-status');
        echo $args['before_widget'];
        if ($title !== '') {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }
        echo '<div class="spaceapi-status-wrapper">' . spaceapi_status_render(false, !empty($instance['show_message'])) . '</div>';
        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = isset($instance['title']) ? $instance['title'] : __('Space Status', 'spaceapi-status');
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Titel:', 'spaceapi-status'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <input class="checkbox" type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_message')); ?>"
                name="<?php echo esc_attr($this->get_field_name('show_message')); ?>" value="1" <?php checked(!empty($instance['show_message'])); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_message')); ?>"><?php esc_html_e('Statustext anzeigen (z. B. nächste Öffnung)', 'spaceapi-status'); ?></label>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        return array(
            'title'        => sanitize_text_field($new_instance['title'] ?? ''),
            'show_message' => !empty($new_instance['show_message']) ? 1 : 0,
        );
    }
}

function spaceapi_status_register_widget() {
    register_widget('SpaceAPI_Status_Widget');
}
add_action('widgets_init', 'spaceapi_status_register_widget');
