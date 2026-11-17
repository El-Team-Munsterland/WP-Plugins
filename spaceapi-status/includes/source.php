<?php
/**
 * Versteckte SpaceAPI: Abruf (mit Zwischenspeicher), Feldzugriff per Punktnotation, Formatierung.
 */
if (!defined('ABSPATH')) exit;

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
