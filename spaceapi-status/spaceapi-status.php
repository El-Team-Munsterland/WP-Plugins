<?php
/**
 * Plugin Name: SpaceAPI Status
 * Description: SpaceAPI-Status, Header-Anzeige und frei konfigurierbare SpaceAPI-Feld-Shortcodes.
 * Version: 1.4.0
 * Author: Lukas Nacke
 * License: GNU GENERAL PUBLIC LICENSE
 */
if (!defined('ABSPATH')) exit;
define('SPACEAPI_STATUS_VERSION','1.4.0');

function spaceapi_status_defaults(){return array(
 'endpoint'=>'https://ha-werkstatt.nacke.xyz/api/spaceapi','cache_ttl'=>300,
 'header_enabled'=>0,'header_title'=>'Space Status','header_position'=>'under_header','field_labels'=>array()
);}
function spaceapi_status_get_settings(){
 $s=get_option('spaceapi_status_settings',array()); return wp_parse_args(is_array($s)?$s:array(),spaceapi_status_defaults());
}
function spaceapi_status_sanitize_settings($in){
 $d=spaceapi_status_defaults(); $in=is_array($in)?$in:array();
 $u=isset($in['endpoint'])?trim($in['endpoint']):$d['endpoint']; $u=wp_http_validate_url($u)?:$d['endpoint'];
 $ttl=min(absint($in['cache_ttl']??$d['cache_ttl']),86400);
 $pos=sanitize_key($in['header_position']??$d['header_position']);
 if(!in_array($pos,array('under_header','after_body_open'),true))$pos=$d['header_position'];
 $labels=array(); foreach((array)($in['field_labels']??array()) as $p=>$l){$p=sanitize_text_field($p);$l=sanitize_text_field($l);if($p!==''&&$l!=='')$labels[$p]=$l;}
 return array('endpoint'=>esc_url_raw($u),'cache_ttl'=>$ttl,'header_enabled'=>!empty($in['header_enabled'])?1:0,
  'header_title'=>sanitize_text_field($in['header_title']??$d['header_title']),'header_position'=>$pos,'field_labels'=>$labels);
}
function spaceapi_status_fetch(){
 $s=spaceapi_status_get_settings();$key='spaceapi_status_'.md5($s['endpoint']);
 if($s['cache_ttl']>0&&false!==($c=get_transient($key)))return $c;
 $r=wp_remote_get($s['endpoint'],array('timeout'=>8,'headers'=>array('Accept'=>'application/json')));
 if(is_wp_error($r))return array('status'=>'error','message'=>$r->get_error_message());
 $code=wp_remote_retrieve_response_code($r);$data=json_decode(wp_remote_retrieve_body($r),true);
 if($code<200||$code>=300)return array('status'=>'error','message'=>'HTTP-Status '.$code);
 if(!is_array($data))return array('status'=>'error','message'=>'Die API-Antwort ist kein gültiges JSON.');
 $out=array('status'=>'ok','data'=>$data);if($s['cache_ttl']>0)set_transient($key,$out,$s['cache_ttl']);return $out;
}
function spaceapi_status_data(){ $r=spaceapi_status_fetch();return $r['status']==='ok'?$r['data']:array(); }
function spaceapi_status_value($data,$path,&$found=false){
 $found=false;$path=trim((string)$path);if($path==='')return null;$v=$data;
 foreach(explode('.',$path) as $p){if(is_array($v)&&array_key_exists($p,$v))$v=$v[$p];else return null;}
 $found=true;return $v;
}
function spaceapi_status_flatten($v,$prefix=''){
 $out=array();
 if(is_array($v)){foreach($v as $k=>$x){$p=$prefix===''?(string)$k:$prefix.'.'.$k;
  if(is_array($x)&&$x!==array())$out+=spaceapi_status_flatten($x,$p);else $out[$p]=array('value'=>is_array($x)?'[]':$x,'type'=>gettype($x));}}
 else $out[$prefix]=array('value'=>$v,'type'=>gettype($v));return $out;
}
function spaceapi_status_format($v){
 if(is_bool($v))return $v?'Ja':'Nein';if($v===null)return 'Unbekannt';if(is_scalar($v))return (string)$v;
 $j=wp_json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);return $j===false?'':$j;
}
function spaceapi_status_render($updated=true){
 $r=spaceapi_status_fetch();if($r['status']!=='ok')return '<span class="spaceapi-status spaceapi-status--error">Status nicht verfügbar</span>';
 $d=$r['data'];$found=false;$o=spaceapi_status_value($d,'state.open',$found);
 $label=($found&&$o===true)?'Geöffnet':(($found&&$o===false)?'Geschlossen':'Unbekannt');
 $cl=($label==='Geöffnet')?'open':(($label==='Geschlossen')?'closed':'unknown');
 $h='<span class="spaceapi-status spaceapi-status--'.$cl.'"><span class="spaceapi-status__dot"></span><span>'.$label.'</span></span>';
 if($updated){$f=false;$t=spaceapi_status_value($d,'state.lastchange',$f);if($f&&is_numeric($t)&&$t>0)$h.=' <span class="spaceapi-status__updated">Zuletzt geändert: '.esc_html(wp_date(get_option('date_format').' '.get_option('time_format'),(int)$t)).'</span>';}
 return $h;
}
function spaceapi_status_shortcode($a){$a=shortcode_atts(array('show_updated'=>'yes'),$a,'space_status');$show=!in_array(strtolower($a['show_updated']),array('no','false','0'),true);return '<div class="spaceapi-status-wrapper">'.spaceapi_status_render($show).'</div>';}
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
 wp_add_inline_style('spaceapi-status','.spaceapi-status-wrapper{margin:1rem 0}.spaceapi-status{display:inline-flex;align-items:center;gap:.5rem;font-weight:600}.spaceapi-status__dot{width:.7rem;height:.7rem;border-radius:50%;background:currentColor;display:inline-block}.spaceapi-status--open{color:#15803d}.spaceapi-status--closed{color:#b91c1c}.spaceapi-status--unknown,.spaceapi-status--error{color:#6b7280}.spaceapi-status__updated{font-size:.9em;opacity:.75;margin-left:.35rem}.spaceapi-header-status{width:100%;box-sizing:border-box;display:flex;align-items:center;justify-content:center;gap:.75rem;padding:.65rem 1rem;border-bottom:1px solid rgba(0,0,0,.08);background:#fff}.spaceapi-header-status__title,.spaceapi-field__label{font-weight:600}.spaceapi-field{display:inline-flex;align-items:baseline}');
}
add_action('wp_enqueue_scripts','spaceapi_status_css');
function spaceapi_status_header(){ $s=spaceapi_status_get_settings();if(empty($s['header_enabled']))return;$t=trim($s['header_title'])?:'Space Status';echo '<div class="spaceapi-header-status" role="status"><span class="spaceapi-header-status__title">'.esc_html($t).'</span><span>'.spaceapi_status_render(false).'</span></div>'; }
function spaceapi_status_after_header(){ $s=spaceapi_status_get_settings();if(!empty($s['header_enabled'])&&$s['header_position']==='under_header')spaceapi_status_header(); }
function spaceapi_status_after_body(){ $s=spaceapi_status_get_settings();if(!empty($s['header_enabled'])&&$s['header_position']==='after_body_open')spaceapi_status_header(); }
add_action('get_header','spaceapi_status_after_header',20);add_action('wp_body_open','spaceapi_status_after_body',20);

function spaceapi_status_settings(){register_setting('spaceapi_status','spaceapi_status_settings',array('type'=>'array','sanitize_callback'=>'spaceapi_status_sanitize_settings','default'=>spaceapi_status_defaults()));}
add_action('admin_init','spaceapi_status_settings');
function spaceapi_status_menu(){add_options_page('SpaceAPI Status','SpaceAPI Status','manage_options','spaceapi-status','spaceapi_status_page');}
add_action('admin_menu','spaceapi_status_menu');
function spaceapi_status_page(){if(!current_user_can('manage_options'))return;$s=spaceapi_status_get_settings();$r=spaceapi_status_fetch();$fields=$r['status']==='ok'?spaceapi_status_flatten($r['data']):array();?>
<div class="wrap"><h1>SpaceAPI Status</h1><form method="post" action="options.php"><?php settings_fields('spaceapi_status');?>
<h2>Verbindung</h2><table class="form-table"><tr><th>SpaceAPI Endpoint</th><td><input name="spaceapi_status_settings[endpoint]" type="url" class="regular-text" value="<?php echo esc_attr($s['endpoint']);?>"></td></tr>
<tr><th>Cache-Dauer</th><td><input name="spaceapi_status_settings[cache_ttl]" type="number" min="0" max="86400" value="<?php echo esc_attr($s['cache_ttl']);?>"> Sekunden (0 = aus)</td></tr></table>
<h2>Header-Anzeige</h2><table class="form-table"><tr><th>Anzeige</th><td><label><input name="spaceapi_status_settings[header_enabled]" type="checkbox" value="1" <?php checked($s['header_enabled'],1);?>> Im Header anzeigen</label></td></tr>
<tr><th>Titel</th><td><input name="spaceapi_status_settings[header_title]" type="text" class="regular-text" value="<?php echo esc_attr($s['header_title']);?>"></td></tr>
<tr><th>Position</th><td><label><input name="spaceapi_status_settings[header_position]" type="radio" value="under_header" <?php checked($s['header_position'],'under_header');?>> Direkt unter dem Header</label><br><label><input name="spaceapi_status_settings[header_position]" type="radio" value="after_body_open" <?php checked($s['header_position'],'after_body_open');?>> Direkt nach dem &lt;body&gt;-Tag</label></td></tr></table>
<h2>Aktuell erkannte SpaceAPI-Felder</h2><p>Diese Liste wird aus der aktuellen JSON-Antwort erzeugt. Das Label wird zum Standard-Label für <code>[spaceapi]</code>.</p>
<?php if($r['status']!=='ok'):?><p><strong>Fehler:</strong> <?php echo esc_html($r['message']);?></p><?php elseif(empty($fields)):?><p>Keine Felder gefunden.</p><?php else:?><table class="widefat striped"><thead><tr><th>API-Feld</th><th>Aktueller Wert</th><th>Label</th></tr></thead><tbody>
<?php foreach($fields as $p=>$f){$label=$s['field_labels'][$p]??ucwords(str_replace(array('_','-'),' ',strrchr($p,'.')!==false?substr(strrchr($p,'.'),1):$p));?><tr><td><code><?php echo esc_html($p);?></code></td><td><?php echo esc_html(spaceapi_status_format($f['value']));?></td><td><input class="regular-text" name="spaceapi_status_settings[field_labels][<?php echo esc_attr($p);?>]" value="<?php echo esc_attr($label);?>"></td></tr><?php }?></tbody></table><?php endif;?>
<?php submit_button('Einstellungen speichern');?></form><hr><h2>Shortcodes</h2>
<p><code>[space_status]</code> – Öffnungsstatus</p><p><code>[spaceapi field="space"]</code> – beliebiges Feld</p><p><code>[spaceapi field="state.open" label="Status"]</code> – eigenes Label</p><p><code>[spaceapi field="location.lat"]</code> – Label aus den Einstellungen</p><p><code>[spaceapi field="state.open" label="Status" fallback="Nicht verfügbar"]</code></p>
<p>Verschachtelte Felder: <code>state.open</code>, <code>location.lat</code>; Array-Elemente: z. B. <code>temperature.0.value</code>.</p></div><?php }
