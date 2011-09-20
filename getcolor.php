<?php
require_once('../../../wp-blog-header.php');
global $wpdb;

//header("HTTP/1.1 200 OK");

//$wpdb->show_errors();


function _json_encode($ary)
{
        $out = "{";
        $keys = array_keys($ary);
        for ($i=0; $i<count($keys); $i++) {
                $ary[$keys[$i]] = str_replace("/","\\/",$ary[$keys[$i]]);
                $out = $out . "\"" . $keys[$i] . "\":\"" . $ary[$keys[$i]] . "\"";
                if ($i < count($keys)-1) {
                        $out = $out . ",";
                }
        }
        $out = $out . "}";
        return $out;
}

$ary['basecolor_play']=get_option('esaudioplayer_basecolor_play');
$ary['symbolcolor_play']=get_option('esaudioplayer_symbolcolor_play');
$ary['basecolor_stop']=get_option('esaudioplayer_basecolor_stop');
$ary['symbolcolor_stop']=get_option('esaudioplayer_symbolcolor_stop');
$ary['basecolor_pause']=get_option('esaudioplayer_basecolor_pause');
$ary['symbolcolor_pause']=get_option('esaudioplayer_symbolcolor_pause');
$ary['color_slider_line']=get_option('esaudioplayer_slidercolor_line');
$ary['color_slider_knob']=get_option('esaudioplayer_slidercolor_knob');
$ary['shadowcolor']=get_option('esaudioplayer_shadowcolor');
$ary['shadowsize']=get_option('esaudioplayer_shadowsize');

if ($ary['basecolor_play']=="") $ary['basecolor_play']="#ffcc99";
if ($ary['symbolcolor_play']=="") $ary['symbolcolor_play']="#cc0066";
if ($ary['basecolor_stop']=="") $ary['basecolor_stop']="#ffcc99";
if ($ary['symbolcolor_stop']=="") $ary['symbolcolor_stop']="#cc0066";
if ($ary['basecolor_pause']=="") $ary['basecolor_pause']="#ffcc99";
if ($ary['symbolcolor_pause']=="") $ary['symbolcolor_pause']="#cc0066";
if ($ary['color_slider_line']=="") $ary['color_slider_line']="#cc0066";
if ($ary['color_slider_knob']=="") $ary['color_slider_knob']="#cc0066";

if ($ary['shadowcolor']=="") $ary['shadowcolor']="#cc0066";
if ($ary['shadowsize']=="") $ary['shadowsize']="#cc0066";


echo _json_encode($ary);


?>
