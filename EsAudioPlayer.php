<?php
/*
Plugin Name: EsAudioPlayer
Plugin URI: http://tempspace.net/plugins/?page_id=4
Description: This is an Extremely Simple Audio Player plugin.
Version: 1.3.2
Author: Atsushi Ueda
Author URI: http://tempspace.net/plugins/
License: GPL2
*/

define("ESP_DEBUG", 0);

//function dbg2($str){$fp=fopen("/tmp/smdebug.txt","a");fwrite($fp,$str . "\n");fclose($fp);}

function esplayer_init() {
	wp_enqueue_script('jquery');
}
add_action('init', 'esplayer_init');


$player_number = 1;
$esAudioPlayer_plugin_URL = get_option( 'siteurl' ) . '/wp-content/plugins/' . plugin_basename(dirname(__FILE__));



define("LEX_NULL", 100);
define("LEX_STRING", 101);
define("LEX_ALNUM", 102);
define("LEX_WHITE", 103);
define("LEX_MISC", 105);
define("LEX_EOL", 106);

define("LEX_PTAG_OPEN", 1000);
define("LEX_PTAG_CLOSE", 1001);
define("LEX_CANVASTAG", 1002);
define("LEX_IMGTAG_OPEN", 1003);
define("LEX_SRC", 1004);
define("LEX_GT", 1005);

$esplayer_local_token[0] = array("token"=>"<p>", "case"=>false, "code"=>LEX_PTAG_OPEN);
$esplayer_local_token[1] = array("token"=>"</p>", "case"=>false, "code"=>LEX_PTAG_CLOSE);
$esplayer_local_token[2] = array("token"=>"<canvas ", "case"=>false, "code"=>LEX_CANVASTAG);
$esplayer_local_token[3] = array("token"=>"<img", "case"=>false, "code"=>LEX_IMGTAG_OPEN);
$esplayer_local_token[4] = array("token"=>"src", "case"=>false, "code"=>LEX_SRC);
$esplayer_local_token[5] = array("token"=>">", "case"=>false, "code"=>LEX_GT);
$esplayer_local_token_idx = array();
$esplayer_max_token_length = 0;

function esplayer_simplelexer(&$str, $pos, &$ret_str)
{
	global $esplayer_local_token;
	global $esplayer_max_token_length;
	global $esplayer_local_token_idx;
	$tbuf_len = 0;
	
	if ($esplayer_max_token_length==0) {
		for ($i=0; $i<count($esplayer_local_token); $i++) {
			if (mb_strlen($esplayer_local_token[$i]["token"]) > $esplayer_max_token_length) {
				$esplayer_max_token_length = mb_strlen($esplayer_local_token[$i]["token"]);
				$esplayer_local_token_idx[mb_substr($esplayer_local_token[$i]["token"],0,1)]=1;
			}
		}
	}
	$tbuf_len = $esplayer_max_token_length*2;
	if ($tbuf_len<50) $tbuf_len=50;

	$tlen = mb_strlen($str);
	
	if ($pos >= $tlen) {
		return LEX_EOL;
	}

	$tmpstr = mb_substr($str, $pos, $tbuf_len);

	$mtr_lexer_white=" \t\n\r";
	if (!(mb_strpos($mtr_lexer_white, mb_substr($tmpstr,0,1))===FALSE)) {
		$tpos=0;
		for ($i=$pos; $i<mb_strlen($str); $i++) {
			if (mb_strpos($mtr_lexer_white, mb_substr($tmpstr,$tpos,1))===FALSE) {
				break;
			}
			$tpos ++;
			if ($tpos >= $tbuf_len-$esplayer_max_token_length) {
				$tpos = 0;
				$tmpstr = mb_substr($str,$i+1,$tbuf_len);			
			}			
		}
		$ret_str = mb_substr($str, $pos, $i-$pos);
		return LEX_WHITE;
	}

	for ($i=0; $i<count($esplayer_local_token); $i++) {
		$tok =$esplayer_local_token[$i]["token"]; 
		$rtok = mb_substr($tmpstr, 0, mb_strlen($tok));
		if (($esplayer_local_token[$i]["case"] && $rtok == $tok) || !(mb_stripos($rtok,$tok)===false)) {
			$ret_str = $rtok;
			return $esplayer_local_token[$i]["code"];
		} 
	}

	$mtr_lexer_alnum="abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_";
	if (!(mb_strpos($mtr_lexer_alnum, mb_substr($tmpstr,0,1))===FALSE)) {
		$tpos=0;
		for ($i=$pos; $i<mb_strlen($str); $i++) {
			if (mb_strpos($mtr_lexer_alnum, mb_substr($tmpstr,$tpos,1))===FALSE) {
				break;
			}
			$tpos++;
			if ($tpos >= $tbuf_len-$esplayer_max_token_length) {
				$tpos = 0;
				$tmpstr = mb_substr($str,$i+1,$tbuf_len);			
			}			
		}
		$ret_str = mb_substr($str, $pos, $i-$pos);
		return LEX_ALNUM;
	}

	if (!(mb_strpos("\"", mb_substr($tmpstr,0,1))===FALSE)) {
		$tpos=1;
		for ($i=$pos+1; $i<mb_strlen($str); $i++) {
			if (mb_substr($tmpstr,$tpos,1) == "\"") {
				$i++;
				break;
			}
			$tpos++;
			if ($tpos >= $tbuf_len-$esplayer_max_token_length) {
				$tpos = 0;
				$tmpstr = mb_substr($str,$i+1,$tbuf_len);			
			}
		}
		$ret_str = mb_substr($str, $pos, $i-$pos);
		return LEX_STRING;
	}
	
	$tpos=0;
	for ($i=$pos; $i<$tlen; $i++) {
		$chr = mb_substr($tmpstr,$tpos,1);
		if (!(mb_strpos($mtr_lexer_white, $chr)===FALSE)) {
			break;
		}
		if ($chr=="\"") {
			break;
		}
		if ($esplayer_local_token_idx[$chr]==1) {
			$flg = 0;
			for ($j=0; $j<count($esplayer_local_token); $j++) {
				$tok =$esplayer_local_token[$j]["token"]; 
				$rtok = mb_substr($tmpstr, $tpos, mb_strlen($tok));
				if (($esplayer_local_token[$j]["case"] && $rtok == $tok) || !(mb_stripos($rtok,$tok)===false)) {
					$flg=1;
					break;
				} 
			}
			if ($flg) break;
		}
		$tpos ++;
		if ($tpos >= $tbuf_len-$esplayer_max_token_length) {
			$tpos = 0;
			$tmpstr = mb_substr($str,$i+1,$tbuf_len);			
		}
	}
	
	$ret_str = mb_substr($str,$pos,$i-$pos);
	
	return LEX_NULL;
}



$esplayer_imgs_num = 0;
$esplayer_imgs[0] = '';
$esplayer_imgs_player_number[0] = 0;


function esplayer_lex(&$content,&$lex_pos, &$token)
{
	for (;;) {
		$ret =  esplayer_simplelexer($content, $lex_pos, $token);
		$lex_pos += mb_strlen($token);
		if ($ret == LEX_WHITE) {
			continue;
		}
		return $ret;
	}
}


function EsAudioPlayer_filter_0($raw_text) 
{
	$ret = mb_ereg_replace("\]<br />[\n]\[esplayer", "] [esplayer", $raw_text);
	$ret = mb_ereg_replace("[\n]*\[esplayer", "[esplayer", $ret);
	$ret = mb_ereg_replace("\]\[esplayer", "] [esplayer", $ret);
	return $ret;
}
add_filter('the_content',  "EsAudioPlayer_filter_0", 10) ;



$esplayer_script = "";
$esplayer_mode = "x";

function EsAudioPlayer_read_accessibility_setting()
{
	global $esplayer_acc_text_enable;
	global $esplayer_acc_msg_download;
	global $esplayer_acc_scr_enable;
	global $esplayer_acc_scr_basic_btns;
	global $esplayer_acc_scr_msg_play_btn;
	global $esplayer_acc_scr_msg_stop_btn;
	global $esplayer_acc_scr_msg_playstop_btn;
	global $esplayer_acc_scr_msg_playpause_btn;
	global $esplayer_acc_scr_fw_enable;
	global $esplayer_acc_scr_fw_amount;
	global $esplayer_acc_scr_fw_unit;
	global $esplayer_acc_scr_fw_msg;
	global $esplayer_acc_scr_rew_enable;
	global $esplayer_acc_scr_rew_amount;
	global $esplayer_acc_scr_rew_unit;
	global $esplayer_acc_scr_rew_msg;
	global $esplayer_acc_scr_ffw_enable;
	global $esplayer_acc_scr_ffw_amount;
	global $esplayer_acc_scr_ffw_unit;
	global $esplayer_acc_scr_ffw_msg;
	global $esplayer_acc_scr_frew_enable;
	global $esplayer_acc_scr_frew_amount;
	global $esplayer_acc_scr_frew_unit;
	global $esplayer_acc_scr_frew_msg;
	$esplayer_acc_text_enable = get_option("esaudioplayer_acc_text_enable", "0");
	$esplayer_acc_msg_download = get_option("esaudioplayer_acc_msg_download", "download the audio");
	$esplayer_acc_scr_enable = get_option("esaudioplayer_acc_scr_enable", "0");
	$esplayer_acc_scr_basic_btns = get_option("esaudioplayer_acc_scr_basic_btns", "playstop");
	$esplayer_acc_scr_msg_play_btn = get_option("esaudioplayer_acc_scr_msg_play_btn", "play");
	$esplayer_acc_scr_msg_stop_btn = get_option("esaudioplayer_acc_scr_msg_stop_btn", "stop");
	$esplayer_acc_scr_msg_playstop_btn = get_option("esaudioplayer_acc_scr_msg_playstop_btn", "play or stop");
	$esplayer_acc_scr_msg_playpause_btn = get_option("esaudioplayer_acc_scr_msg_playpause_btn", "play or pause");
	$esplayer_acc_scr_fw_enable = get_option("esaudioplayer_acc_scr_fw_enable", "1");
	$esplayer_acc_scr_fw_amount = get_option("esaudioplayer_acc_scr_fw_amount", "15");
	$esplayer_acc_scr_fw_unit = get_option("esaudioplayer_acc_scr_fw_unit", "sec");
	$esplayer_acc_scr_fw_msg = get_option("esaudioplayer_acc_scr_fw_msg", "forward 15 seconds");
	$esplayer_acc_scr_rew_enable = get_option("esaudioplayer_acc_scr_rew_enable", "1");
	$esplayer_acc_scr_rew_amount = get_option("esaudioplayer_acc_scr_rew_amount", "15");
	$esplayer_acc_scr_rew_unit = get_option("esaudioplayer_acc_scr_rew_unit", "sec");
	$esplayer_acc_scr_rew_msg = get_option("esaudioplayer_acc_scr_rew_msg", "rewind 15 seconds");
	$esplayer_acc_scr_ffw_enable = get_option("esaudioplayer_acc_scr_ffw_enable", "0");
	$esplayer_acc_scr_ffw_amount = get_option("esaudioplayer_acc_scr_ffw_amount", "10");
	$esplayer_acc_scr_ffw_unit = get_option("esaudioplayer_acc_scr_ffw_unit", "pct");
	$esplayer_acc_scr_ffw_msg = get_option("esaudioplayer_acc_scr_ffw_msg", "forward 10%");
	$esplayer_acc_scr_frew_enable = get_option("esaudioplayer_acc_scr_frew_enable", "0");
	$esplayer_acc_scr_frew_amount = get_option("esaudioplayer_acc_scr_frew_amount", "10");
	$esplayer_acc_scr_frew_unit = get_option("esaudioplayer_acc_scr_frew_unit", "pct");
	$esplayer_acc_scr_frew_msg = get_option("esaudioplayer_acc_scr_frew_msg", "rewind 10%");

}
EsAudioPlayer_read_accessibility_setting();

function EsAudioPlayer_shortcode($atts, $content = null) {
	global $player_number;
	global $esplayer_imgs_num, $esplayer_imgs, $esplayer_imgs_player_number;
	global $esplayer_script;
	global $esplayer_mode;
	global $esplayer_acc_text_enable;
	global $esplayer_acc_msg_download;
	global $esplayer_acc_scr_enable;
	global $esplayer_acc_scr_basic_btns;
	global $esplayer_acc_scr_msg_play_btn;
	global $esplayer_acc_scr_msg_stop_btn;
	global $esplayer_acc_scr_msg_playstop_btn;
	global $esplayer_acc_scr_msg_playpause_btn;
	global $esplayer_acc_scr_fw_enable;
	global $esplayer_acc_scr_fw_amount;
	global $esplayer_acc_scr_fw_unit;
	global $esplayer_acc_scr_fw_msg;
	global $esplayer_acc_scr_rew_enable;
	global $esplayer_acc_scr_rew_amount;
	global $esplayer_acc_scr_rew_unit;
	global $esplayer_acc_scr_rew_msg;
	global $esplayer_acc_scr_ffw_enable;
	global $esplayer_acc_scr_ffw_amount;
	global $esplayer_acc_scr_ffw_unit;
	global $esplayer_acc_scr_ffw_msg;
	global $esplayer_acc_scr_frew_enable;
	global $esplayer_acc_scr_frew_amount;
	global $esplayer_acc_scr_frew_unit;
	global $esplayer_acc_scr_frew_msg;

	do_shortcode($content);
	$url = "";
	$img_id = "";
	$timetable_id="";
	$width="";
	$height="";
	$bgcolor="#ffffff" ;
	$shadow_color="";
	$shadow_size="-999";
	$vp="0";
	$border_box="";
	$border_img="0";
	$esplayer_mode="0";
	$loop="false";
	$duration="";
	$acc_basic_btns="";
	$acc_fwd_btn="";
	$acc_rwd_btn="";
	$acc_ffwd_btn="";
	$acc_frwd_btn="";
	$acc_scr_enable="";


	extract($atts);
	if (substr($vp,0,1)=="-") {
		$vp = substr($vp,1);
	} else {
		$vp = "-".$vp;
	}

	if ($width=="") $width=$height;
	if ($height=="") $height=$width;
	if ($height=="") {$height="27px"; $width="27px";}

	if (is_numeric($width)) $width = $width . "px";
	if (is_numeric($height)) $height = $height . "px";
	if (is_numeric($vp)) $vp = $vp . "px";

	$id = "esplayer_" . (string)($player_number);
	$js_var='esplayervar' . (string)($player_number);

	$acc_scr_enable = $esplayer_acc_scr_enable;

	if ($acc_basic_btns=="") $acc_basic_btns = $esplayer_acc_scr_basic_btns; else $acc_scr_enable="1";
	if ($acc_fwd_btn=="") $acc_fwd_btn = $esplayer_acc_scr_fw_enable;
	if ($acc_rew_btn=="") $acc_rew_btn = $esplayer_acc_scr_rew_enable;
	if ($acc_ffwd_btn=="") $acc_ffwd_btn = $esplayer_acc_scr_ffw_enable;
	if ($acc_frew_btn=="") $acc_frew_btn = $esplayer_acc_scr_frew_enable;

	if ($img_id == "" && $timetable_id == "") {
		$esplayer_mode="simple";
		$ret = "<div style=\"display:inline;position:relative;border:solid 0px #f00;\" id=\"" . $id . "_tmpspan\"><canvas id=\"" . $id . "\" style=\"cursor:pointer;\"></canvas></div>";
	} else if ($timetable_id != "") {
		$esplayer_mode="slideshow";
		$ret = "<div id=\"" . $id . "_tmpspan\" style=\"width:".$width."; height:".$height."; background-color:".$bgcolor."; border:".$border_box.";\">&nbsp;</div>";
		$url = "aa.mp3";
	} else {
		$esplayer_mode="imgclick";
		$ret = "";
	}
	if ($esplayer_acc_text_enable == "1") {
		$ret .= "<div style=\"display:none;\"><a href=\"" .$url. "\">" . $esplayer_acc_msg_download . "</a></div>";
	}
	if ($acc_scr_enable == "1") {
		$ret .= "<div style=\"position:absolute;left:-3000px;\">";
		if ($acc_basic_btns == "playstop") {
			$ret .= "<input type='button' title='" . $esplayer_acc_scr_msg_playstop_btn . "' onclick=\"".$js_var.".func_acc_play_stop();return -1;\"/>";
		}
		if ($acc_basic_btns == "play+stop") {
			$ret .= "<input type='button' title='" . $esplayer_acc_scr_msg_play_btn . "' onclick=\"".$js_var.".func_acc_play();return -1;\"/>";
		}
		if ($acc_basic_btns == "playpause+stop") {
			$ret .= "<input type='button' title='" . $esplayer_acc_scr_msg_playpause_btn . "' onclick=\"".$js_var.".func_acc_play_pause();return -1;\"/>";
		}
		if ($acc_basic_btns == "play+stop" || $acc_basic_btns == "playpause+stop") {
			$ret .= "<input type='button' title='" . $esplayer_acc_scr_msg_stop_btn . "' onclick=\"".$js_var.".func_acc_stop();return -1;\"/>";
		}
		if ($acc_fwd_btn=="1") {
			$ret .= "<input type='button' title='" . $esplayer_acc_scr_fw_msg . "' onclick=\"".$js_var.".func_acc_seek(".$esplayer_acc_scr_fw_amount.",'".$esplayer_acc_scr_fw_unit."');return -1;\"/>";
		}
		if ($acc_rew_btn=="1") {
			$ret .= "<input type='button' title='" . $esplayer_acc_scr_rew_msg . "' onclick=\"".$js_var.".func_acc_seek(-".$esplayer_acc_scr_rew_amount.",'".$esplayer_acc_scr_rew_unit."');return -1;\"/>";
		}
		if ($acc_ffwd_btn=="1") {
			$ret .= "<input type='button' title='" . $esplayer_acc_scr_ffw_msg . "' onclick=\"".$js_var.".func_acc_seek(".$esplayer_acc_scr_ffw_amount.",'".$esplayer_acc_scr_ffw_unit."');return -1;\"/>";
		}
		if ($acc_frew_btn=="1") {
			$ret .= "<input type='button' title='" . $esplayer_acc_scr_frew_msg . "' onclick=\"".$js_var.".func_acc_seek(-".$esplayer_acc_scr_frew_amount.",'".$esplayer_acc_scr_frew_unit."');return -1;\"/>";
		}

		$ret .= "</div>";

	}

	$title_utf8="";
	$artist_utf8="";
	
	$esplayer_script = $esplayer_script . "var " . $js_var . ";\njQuery(document).ready(function() {\n";

	if ($esplayer_mode=="simple") {
		//$esplayer_script = $esplayer_script . "ReplaceContainingCanvasPtag2div('".$id."_tmpspan');\n";
	}
	$esplayer_script = $esplayer_script . $js_var . " = new EsAudioPlayer(\"" 
		. $esplayer_mode
		. '", "' 
		. $id 
		. '", "' 
		. ($esplayer_mode=="slideshow"?$timetable_id:$url) 
		. '", "' 
		. $width
		. '", "' 
		. $height
		. '", "' 
		. $vp
		. '", '
		. $shadow_size
		. ', "' 
		. $shadow_color
		. '", "' 
		. $border_img 
		. '", ' 
		. $loop
		. ', "'
		. $duration 
		. '", "' 
		. $img_id
		. '", "' 
		. $artist_utf8 
		. '", "' 
		. $title_utf8 
		. "\"); });\n";
	if ($img != "") {
		$esplayer_script = $esplayer_script . "document.getElementById('img_esplayer_".(string)($player_number)."').style.cursor=\"pointer\";\n";
	}

	$player_number ++;
	return $ret;
}

add_shortcode('esplayer', 'EsAudioPlayer_shortcode',12);

function EsAudioPlayer_filter_2($raw_text) {
	global $player_number;
	if ($player_number == 1) return $raw_text;
	global $esplayer_script;
	$ret = $raw_text . "<script type=\"text/javascript\">\n" . $esplayer_script . "</script>\n";
	$esplayer_script = "";
	return $ret;
}

add_filter('the_content',  "EsAudioPlayer_filter_2", 99) ;


include 'EsAudioPlayer_tt.php';


/*  <head>sectionに、player Javascriptを追加   */
add_action( 'wp_head', 'EsAudioPlayer_title_filter' );

function EsAudioPlayer_title_filter( $title ) {
	global $esAudioPlayer_plugin_URL;
	global $esplayer_mode;

	echo  "<!--[if lt IE 9]><script type=\"text/javascript\" src=\"" . $esAudioPlayer_plugin_URL . "/excanvas.js\"></script><![endif]-->\n";
	echo  "<script type=\"text/javascript\" src=\"" . $esAudioPlayer_plugin_URL . "/jquery.base64.min.js\"></script>\n";
	echo  "<script type=\"text/javascript\" src=\"" . $esAudioPlayer_plugin_URL . "/print_r.js\"></script>\n";
	echo  "<script type=\"text/javascript\" src=\"" . $esAudioPlayer_plugin_URL . "/binaryajax.js\"></script>\n";
	echo  "<script type=\"text/javascript\" src=\"" . $esAudioPlayer_plugin_URL . "/soundmanager2-jsmin.js\"></script>\n";
	echo  "<script type=\"text/javascript\"> var esAudioPlayer_plugin_URL = '" . $esAudioPlayer_plugin_URL . "'; </script>\n";
	echo  "<script type=\"text/javascript\" src=\"" . $esAudioPlayer_plugin_URL . "/esplayer_tes_min.js\"></script>\n";
	echo "<script type=\"text/javascript\">\nvar esp_tt_data_encoded='';\nvar esp_tt_data; </script>\n";
	echo  "<script type=\"text/javascript\" src=\"" . $esAudioPlayer_plugin_URL . "/esplayer_tt.js\"></script>\n";
} 


// 設定メニューの追加
add_action('admin_menu', 'esaudioplayer_plugin_menu');
function esaudioplayer_plugin_menu()
{
	/*  設定画面の追加  */
	add_submenu_page('plugins.php', 'EsAudioPlayer Configuration', 'EsAudioPlayer', 'manage_options', 'esaudioplayer-submenu-handle', 'esaudioplayer_magic_function'); 
}

$esaudioplayer_col_ar[0] = '#esaudioplayer_basecolor_play';
$esaudioplayer_col_ar[1] = '#esaudioplayer_symbolcolor_play';
$esaudioplayer_col_ar[2] = '#esaudioplayer_basecolor_stop';
$esaudioplayer_col_ar[3] = '#esaudioplayer_symbolcolor_stop';
$esaudioplayer_col_ar[4] = '#esaudioplayer_basecolor_pause';
$esaudioplayer_col_ar[5] = '#esaudioplayer_symbolcolor_pause';
$esaudioplayer_col_ar[6] = '#esaudioplayer_slidercolor_line';
$esaudioplayer_col_ar[7] = '#esaudioplayer_slidercolor_knob';
$esaudioplayer_col_ar[8] = '#esaudioplayer_shadowcolor';


function esaudioplayer_farbtastic_prepare($ar)
{
	$scr = "";
	for ($i=0; $i<count($ar); $i++) {
		$id = 'colorpicker'.$i;
		echo "<div id=\"" . $id . "\" style=\"position:absolute;\"></div>";
		$scr .= "			jQuery('#".$id."').farbtastic('".$ar[$i]."').hide();\n";
		$scr .= "			SetPosition('".$ar[$i]."','#".$id."');\n";
		$scr .= "			jQuery('".$ar[$i]."').focus(function(){jQuery('#".$id."').show();});\n";
		$scr .= "			jQuery('".$ar[$i]."').blur(function(){jQuery('#".$id."').hide();});\n";
	}
	echo 	"	<script type=\"text/javascript\">\n		jQuery(document).ready(function(){\n" . $scr . "		});\n".
		"		function SetPosition(el, cl)\n".
		"		{\n".
		"			var left = 0;\n".
		"			var top = 0;\n".
		"			left = jQuery(el).offset().left + jQuery(el).width()*1.2;\n".
		"			top = jQuery(el).offset().top -jQuery(cl).height()/2;\n".
		"			var height = jQuery(el).height();\n".
		"			if (!isNaN(parseInt(jQuery(el).css('padding-top')))) height += parseInt(jQuery(el).css('padding-top'));\n".
		"			if (!isNaN(parseInt(jQuery(el).css('margin-top')))) height += parseInt(jQuery(el).css('margin-top'));\n".
		"			var y = Math.floor(top) + height;\n".
		"			var x = Math.floor(left);\n".
		"			jQuery(cl).css('top',y+\"px\");\n".
		"			jQuery(cl).css('left',x+\"px\");\n".
		"		}\n".
		"	</script>\n";
}

/*  設定画面出力  */
function esaudioplayer_magic_function()
{
	global $esplayer_acc_text_enable;
	global $esplayer_acc_msg_download;
	global $esplayer_acc_scr_enable;
	global $esplayer_acc_scr_basic_btns;
	global $esplayer_acc_scr_msg_play_btn;
	global $esplayer_acc_scr_msg_stop_btn;
	global $esplayer_acc_scr_msg_playstop_btn;
	global $esplayer_acc_scr_msg_playpause_btn;
	global $esplayer_acc_scr_fw_enable;
	global $esplayer_acc_scr_fw_amount;
	global $esplayer_acc_scr_fw_unit;
	global $esplayer_acc_scr_fw_msg;
	global $esplayer_acc_scr_rew_enable;
	global $esplayer_acc_scr_rew_amount;
	global $esplayer_acc_scr_rew_unit;
	global $esplayer_acc_scr_rew_msg;
	global $esplayer_acc_scr_ffw_enable;
	global $esplayer_acc_scr_ffw_amount;
	global $esplayer_acc_scr_ffw_unit;
	global $esplayer_acc_scr_ffw_msg;
	global $esplayer_acc_scr_frew_enable;
	global $esplayer_acc_scr_frew_amount;
	global $esplayer_acc_scr_frew_unit;
	global $esplayer_acc_scr_frew_msg;

	/*  Save Changeボタン押下でコールされた場合、$_POSTに格納された設定情報を保存  */
	if ( isset($_POST['updateEsAudioPlayerSetting'] ) ) {
		echo '<div id="message" class="updated fade"><p><strong>Options saved.</strong></p></div>';
		update_option('esaudioplayer_basecolor_play', $_POST['esaudioplayer_basecolor_play']);
		update_option('esaudioplayer_symbolcolor_play', $_POST['esaudioplayer_symbolcolor_play']);
		update_option('esaudioplayer_basecolor_stop', $_POST['esaudioplayer_basecolor_stop']);
		update_option('esaudioplayer_symbolcolor_stop', $_POST['esaudioplayer_symbolcolor_stop']);
		update_option('esaudioplayer_basecolor_pause', $_POST['esaudioplayer_basecolor_pause']);
		update_option('esaudioplayer_symbolcolor_pause', $_POST['esaudioplayer_symbolcolor_pause']);
		update_option('esaudioplayer_slidercolor_line', $_POST['esaudioplayer_slidercolor_line']);
		update_option('esaudioplayer_slidercolor_knob', $_POST['esaudioplayer_slidercolor_knob']);
		update_option('esaudioplayer_shadowcolor', $_POST['esaudioplayer_shadowcolor']);
		update_option('esaudioplayer_shadowsize', $_POST['esaudioplayer_shadowsize']);

		update_option('esaudioplayer_acc_text_enable', $_POST['esaudioplayer_acc_text_enable']);
		update_option('esaudioplayer_acc_text_enable', $_POST['esaudioplayer_acc_text_enable']);
		update_option('esaudioplayer_acc_msg_download', $_POST['esaudioplayer_acc_msg_download']);
		update_option('esaudioplayer_acc_scr_enable', $_POST['esaudioplayer_acc_scr_enable']);
		update_option('esaudioplayer_acc_scr_basic_btns', $_POST['esaudioplayer_acc_scr_basic_btns']);
		update_option('esaudioplayer_acc_scr_msg_play_btn', $_POST['esaudioplayer_acc_scr_msg_play_btn']);
		update_option('esaudioplayer_acc_scr_msg_stop_btn', $_POST['esaudioplayer_acc_scr_msg_stop_btn']);
		update_option('esaudioplayer_acc_scr_msg_playstop_btn', $_POST['esaudioplayer_acc_scr_msg_playstop_btn']);
		update_option('esaudioplayer_acc_scr_msg_playpause_btn', $_POST['esaudioplayer_acc_scr_msg_playpause_btn']);

		update_option('esaudioplayer_acc_scr_fw_enable', isset($_POST['esaudioplayer_acc_scr_fw_enable'])?"1":"0");
		update_option('esaudioplayer_acc_scr_fw_amount', $_POST['esaudioplayer_acc_scr_fw_amount']);
		update_option('esaudioplayer_acc_scr_fw_unit', $_POST['esaudioplayer_acc_scr_fw_unit']);
		update_option('esaudioplayer_acc_scr_fw_msg', $_POST['esaudioplayer_acc_scr_fw_msg']);
		update_option('esaudioplayer_acc_scr_rew_enable', isset($_POST['esaudioplayer_acc_scr_rew_enable'])?"1":"0");
		update_option('esaudioplayer_acc_scr_rew_amount', $_POST['esaudioplayer_acc_scr_rew_amount']);
		update_option('esaudioplayer_acc_scr_rew_unit', $_POST['esaudioplayer_acc_scr_rew_unit']);
		update_option('esaudioplayer_acc_scr_rew_msg', $_POST['esaudioplayer_acc_scr_rew_msg']);
		update_option('esaudioplayer_acc_scr_ffw_enable', isset($_POST['esaudioplayer_acc_scr_ffw_enable'])?"1":"0");
		update_option('esaudioplayer_acc_scr_ffw_amount', $_POST['esaudioplayer_acc_scr_ffw_amount']);
		update_option('esaudioplayer_acc_scr_ffw_unit', $_POST['esaudioplayer_acc_scr_ffw_unit']);
		update_option('esaudioplayer_acc_scr_ffw_msg', $_POST['esaudioplayer_acc_scr_ffw_msg']);
		update_option('esaudioplayer_acc_scr_frew_enable', isset($_POST['esaudioplayer_acc_scr_frew_enable'])?"1":"0");
		update_option('esaudioplayer_acc_scr_frew_amount', $_POST['esaudioplayer_acc_scr_frew_amount']);
		update_option('esaudioplayer_acc_scr_frew_unit', $_POST['esaudioplayer_acc_scr_frew_unit']);
		update_option('esaudioplayer_acc_scr_frew_msg', $_POST['esaudioplayer_acc_scr_frew_msg']);
	}

	global $esaudioplayer_col_ar;
	esaudioplayer_farbtastic_prepare($esaudioplayer_col_ar);

	$plugin = plugin_basename('EsAudioPlayer'); $plugin = dirname(__FILE__);
	?>

	<div id="colorpicker1" style="position:absolute"></div>
	<div id="colorpicker2" style="position:absolute"></div>
	<div id="colorpicker3" style="position:absolute"></div>
	<div id="colorpicker4" style="position:absolute"></div>
	<div id="colorpicker5" style="position:absolute"></div>
	<div id="colorpicker6" style="position:absolute"></div>
	<div id="colorpicker7" style="position:absolute"></div>
	<div id="colorpicker8" style="position:absolute"></div>
	<div id="colorpicker9" style="position:absolute"></div>


	<script type="text/javascript" src="js/jquery-1.4.2.min.js"></script>
	<div class="wrap">
		<h2>EsAudioPlayer configuration</h2>

		<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">

		<?php
		wp_nonce_field('update-options');  
		$basecolor_play = get_option("esaudioplayer_basecolor_play", "#ffcc99"); 
		$symbolcolor_play = get_option("esaudioplayer_symbolcolor_play", "#cc0066"); 
		$basecolor_stop = get_option("esaudioplayer_basecolor_stop", "#ffcc99"); 
		$symbolcolor_stop = get_option("esaudioplayer_symbolcolor_stop", "#cc0066"); 
		$basecolor_pause = get_option("esaudioplayer_basecolor_pause", "#ffcc99"); 
		$symbolcolor_pause = get_option("esaudioplayer_symbolcolor_pause", "#cc0066"); 
		$slidercolor_line = get_option("esaudioplayer_slidercolor_line", "#cc0066"); 
		$slidercolor_knob = get_option("esaudioplayer_slidercolor_knob", "#cc0066"); 
		$shadowcolor = get_option("esaudioplayer_shadowcolor", "#888888"); 
		$shadowsize = get_option("esaudioplayer_shadowsize", "0.1"); 

		EsAudioPlayer_read_accessibility_setting(); 
		$acc_text_enable = $esplayer_acc_text_enable; 
		$acc_msg_download = $esplayer_acc_msg_download; 
		$acc_scr_enable = $esplayer_acc_scr_enable; 
		$acc_scr_basic_btns = $esplayer_acc_scr_basic_btns; 
		$acc_scr_msg_play_btn = $esplayer_acc_scr_msg_play_btn; 
		$acc_scr_msg_stop_btn = $esplayer_acc_scr_msg_stop_btn; 
		$acc_scr_msg_playstop_btn = $esplayer_acc_scr_msg_playstop_btn; 
		$acc_scr_msg_playpause_btn = $esplayer_acc_scr_msg_playpause_btn;
		$acc_scr_fw_enable = $esplayer_acc_scr_fw_enable;
		$acc_scr_fw_amount = $esplayer_acc_scr_fw_amount;
		$acc_scr_fw_unit = $esplayer_acc_scr_fw_unit;
		$acc_scr_fw_msg = $esplayer_acc_scr_fw_msg;
		$acc_scr_rew_enable = $esplayer_acc_scr_rew_enable;
		$acc_scr_rew_amount = $esplayer_acc_scr_rew_amount;
		$acc_scr_rew_unit = $esplayer_acc_scr_rew_unit;
		$acc_scr_rew_msg = $esplayer_acc_scr_rew_msg;
		$acc_scr_ffw_enable = $esplayer_acc_scr_ffw_enable;
		$acc_scr_ffw_amount = $esplayer_acc_scr_ffw_amount;
		$acc_scr_ffw_unit = $esplayer_acc_scr_ffw_unit;
		$acc_scr_ffw_msg = $esplayer_acc_scr_ffw_msg;
		$acc_scr_frew_enable = $esplayer_acc_scr_frew_enable;
		$acc_scr_frew_amount = $esplayer_acc_scr_frew_amount;
		$acc_scr_frew_unit = $esplayer_acc_scr_frew_unit;
		$acc_scr_frew_msg = $esplayer_acc_scr_frew_msg;
 		?>

		<h3>Color Settings</h3>

		<table class="form-table">
		<tr>
		<th scope="row" style="text-align:right;">Base Color (Play)</th>
		<td> <input type="text" id="esaudioplayer_basecolor_play" name="esaudioplayer_basecolor_play" value="<?php echo $basecolor_play; ?>" /></td>
		</tr>
		<tr>
		<th scope="row" style="text-align:right;">Symbol Color (Play)</th>
		<td> <input type="text" id="esaudioplayer_symbolcolor_play" name="esaudioplayer_symbolcolor_play" value="<?php echo $symbolcolor_play; ?>" /></td>

		</tr>
		<tr>
		<th scope="row" style="text-align:right;">Base Color (Stop)</th>
		<td> <input type="text" id="esaudioplayer_basecolor_stop" name="esaudioplayer_basecolor_stop" value="<?php echo $basecolor_stop; ?>" /></td>
		</tr>
		<tr>
		<th scope="row" style="text-align:right;">Symbol Color (Stop)</th>
		<td> <input type="text" id="esaudioplayer_symbolcolor_stop" name="esaudioplayer_symbolcolor_stop" value="<?php echo $symbolcolor_stop; ?>" /></td>
		</tr>

		</tr>
		<tr>
		<th scope="row" style="text-align:right;">Base Color (Pause)</th>
		<td> <input type="text" id="esaudioplayer_basecolor_pause" name="esaudioplayer_basecolor_pause" value="<?php echo $basecolor_pause; ?>" /></td>
		</tr>
		<tr>
		<th scope="row" style="text-align:right;">Symbol Color (Pause)</th>
		<td> <input type="text" id="esaudioplayer_symbolcolor_pause" name="esaudioplayer_symbolcolor_pause" value="<?php echo $symbolcolor_pause; ?>" /></td>
		</tr>

		</tr>
		<tr>
		<th scope="row" style="text-align:right;">Slider Color (line)</th>
		<td> <input type="text" id="esaudioplayer_slidercolor_line" name="esaudioplayer_slidercolor_line" value="<?php echo $slidercolor_line; ?>" /></td>
		</tr>
		<tr>
		<th scope="row" style="text-align:right;">Slider Color (knob)</th>
		<td> <input type="text" id="esaudioplayer_slidercolor_knob" name="esaudioplayer_slidercolor_knob" value="<?php echo $slidercolor_knob; ?>" /></td>
		</tr>

		</tr>
		<tr>
		<th scope="row" style="text-align:right;">Shadow Size</th>
		<td><input type="text" name="esaudioplayer_shadowsize" value="<?php echo $shadowsize; ?>" /></td>
		</tr>
		<tr>
		<th scope="row" style="text-align:right;">Shadow Color</th>
		<td><input type="text" id="esaudioplayer_shadowcolor" name="esaudioplayer_shadowcolor" value="<?php echo $shadowcolor; ?>" /></td>
		</tr>
		</table>

		<h3>Accessibility Settings</h3>

		<h4>For Text-based Browsers</h4>

		<table class="form-table">
		<tr>
		<th scope="row" style="text-align:right;">Status</th>
		<td>
		<input type="radio" name="esaudioplayer_acc_text_enable" value="1" <?php echo $acc_text_enable=="1"?"checked ":""; ?>/>Enabled<br/>
		<input type="radio" name="esaudioplayer_acc_text_enable" value="0" <?php echo $acc_text_enable=="0"?"checked ":""; ?>/>Disabled
		</td>
		</tr>

		<tr>
		<th scope="row" style="text-align:right;">Download link speech</th>
		<td><input type="text" id="esaudioplayer_acc_msg_download" name="esaudioplayer_acc_msg_download" value="<?php echo $acc_msg_download; ?>" /></td>
		</tr>		</table>




		<h4>For Screen Readers</h4>		

		<table class="form-table">
		<tr>
		<th scope="row" style="text-align:right;">Status</th>
		<td>
		<input type="radio" name="esaudioplayer_acc_scr_enable" value="1" <?php echo $acc_scr_enable=="1"?"checked":""; ?>>Enabled<br/>
		<input type="radio" name="esaudioplayer_acc_scr_enable" value="0" <?php echo $acc_scr_enable=="0"?"checked":""; ?>>Disabled
		</td>
		</tr>

		<tr>
		<th scope="row" style="text-align:right;">Basic buttons</th>
		<td>
		<input type="radio" name="esaudioplayer_acc_scr_basic_btns" value="playstop" <?php echo $acc_scr_basic_btns=="playstop"?"checked":""; ?>>[Play/Stop]<br/>
		<input type="radio" name="esaudioplayer_acc_scr_basic_btns" value="play+stop" <?php echo $acc_scr_basic_btns=="play+stop"?"checked":""; ?>>[Play] + [Stop]<br/>
		<input type="radio" name="esaudioplayer_acc_scr_basic_btns" value="playpause+stop" <?php echo $acc_scr_basic_btns=="playpause+stop"?"checked":""; ?>>[Play/Pause] + [Stop]<br/>
		</td>
		</tr>
		<tr>
		<th scope="row" style="text-align:right;">Play button speech</th>
		<td><input type="text" name="esaudioplayer_acc_scr_msg_play_btn" value="<?php echo $acc_scr_msg_play_btn; ?>" /></td>
		</tr>		<tr>
		<th scope="row" style="text-align:right;">Stop button speech</th>
		<td><input type="text" name="esaudioplayer_acc_scr_msg_stop_btn" value="<?php echo $acc_scr_msg_stop_btn; ?>" /></td>
		</tr>		<tr>
		<th scope="row" style="text-align:right;">Play/Stop button speech</th>
		<td><input type="text" name="esaudioplayer_acc_scr_msg_playstop_btn" value="<?php echo $acc_scr_msg_playstop_btn; ?>" /></td>
		</tr>		<tr>
		<th scope="row" style="text-align:right;">Play/Pause button speech</th>
		<td><input type="text" name="esaudioplayer_acc_scr_msg_playpause_btn" value="<?php echo $acc_scr_msg_playpause_btn; ?>" /></td>
		</tr>

		<tr>
		<th scope="row" style="text-align:right;">Forward Button</th>
		<td><input type="checkbox" name="esaudioplayer_acc_scr_fw_enable" value="1" <?php echo $acc_scr_fw_enable=="1"?"checked":""; ?> />Enable<br/>
		Amount <input type="text" name="esaudioplayer_acc_scr_fw_amount" value="<?php echo $acc_scr_fw_amount; ?>" />
		<input type="radio" name="esaudioplayer_acc_scr_fw_unit" value="sec" <?php echo $acc_scr_fw_unit=="sec"?"checked":""; ?>>sec.
		<input type="radio" name="esaudioplayer_acc_scr_fw_unit" value="pct" <?php echo $acc_scr_fw_unit=="pct"?"checked":""; ?>>%<br/>
		Speech <input type="text" name="esaudioplayer_acc_scr_fw_msg" value="<?php echo $acc_scr_fw_msg; ?>" />
		</td>
		</tr>

		<tr>
		<th scope="row" style="text-align:right;">Rewind Button</th>
		<td><input type="checkbox" name="esaudioplayer_acc_scr_rew_enable" value="1" <?php echo $acc_scr_rew_enable=="1"?"checked":""; ?> />Enable<br/>
		Amount <input type="text" name="esaudioplayer_acc_scr_rew_amount" value="<?php echo $acc_scr_rew_amount; ?>" />
		<input type="radio" name="esaudioplayer_acc_scr_rew_unit" value="sec" <?php echo $acc_scr_rew_unit=="sec"?"checked":""; ?>>sec.
		<input type="radio" name="esaudioplayer_acc_scr_rew_unit" value="pct" <?php echo $acc_scr_rew_unit=="pct"?"checked":""; ?>>%<br/>
		Speech <input type="text" name="esaudioplayer_acc_scr_rew_msg" value="<?php echo $acc_scr_rew_msg; ?>" />
		</td>
		</tr>

		<tr>
		<th scope="row" style="text-align:right;">Fast Forward Button</th>
		<td><input type="checkbox" name="esaudioplayer_acc_scr_ffw_enable" value="1" <?php echo $acc_scr_ffw_enable=="1"?"checked":""; ?> />Enable<br/>
		Amount <input type="text" name="esaudioplayer_acc_scr_ffw_amount" value="<?php echo $acc_scr_ffw_amount; ?>" />
		<input type="radio" name="esaudioplayer_acc_scr_ffw_unit" value="sec" <?php echo $acc_scr_ffw_unit=="sec"?"checked":""; ?>>sec.
		<input type="radio" name="esaudioplayer_acc_scr_ffw_unit" value="pct" <?php echo $acc_scr_ffw_unit=="pct"?"checked":""; ?>>%<br/>
		Speech <input type="text" name="esaudioplayer_acc_scr_ffw_msg" value="<?php echo $acc_scr_ffw_msg; ?>" />
		</td>
		</tr>

		<tr>
		<th scope="row" style="text-align:right;">Fast Rewind Button</th>
		<td><input type="checkbox" name="esaudioplayer_acc_scr_frew_enable" value="1" <?php echo $acc_scr_frew_enable=="1"?"checked":""; ?> />Enable<br/>
		Amount <input type="text" name="esaudioplayer_acc_scr_frew_amount" value="<?php echo $acc_scr_frew_amount; ?>" />
		<input type="radio" name="esaudioplayer_acc_scr_frew_unit" value="sec" <?php echo $acc_scr_frew_unit=="sec"?"checked":""; ?>>sec.
		<input type="radio" name="esaudioplayer_acc_scr_frew_unit" value="pct" <?php echo $acc_scr_frew_unit=="pct"?"checked":""; ?>>%<br/>
		Speech <input type="text" name="esaudioplayer_acc_scr_frew_msg" value="<?php echo $acc_scr_frew_msg; ?>" />
		</td>
		</tr>


		</table>


		<input type="hidden" name="action" value="update" />
		<input type="hidden" name="page_options" value="esaudioplayer_basecolor_play,esaudioplayer_symbolcolor_play,esaudioplayer_basecolor_stop,esaudioplayer_symbolcolor_stop" />
		<p class="submit">
			<input type="submit" name="updateEsAudioPlayerSetting" class="button-primary" value="<?php _e('Save  Changes')?>" onclick="" />
		</p>
		</form>


	</div>
	<?php 
	if ( isset($_POST['updateEsAudioPlayerSetting'] ) ) {
		//echo '<script type="text/javascript">alert("Options Saved.");</script>';
	}
}

function EsAudioPlayer_admin_head()
{
	global $esAudioPlayer_plugin_URL;
	echo "<link rel='stylesheet' href='". $esAudioPlayer_plugin_URL . "/mattfarina-farbtastic/farbtastic.css' type='text/css' media='all' />\n";
	echo "<script type=\"text/javascript\" src=\"" . $esAudioPlayer_plugin_URL . "/mattfarina-farbtastic/farbtastic.min.js\"></script>\n";
}
add_action( 'admin_head', 'EsAudioPlayer_admin_head' ); 


/*  <footer>sectionに、player Javascriptを追加   */
add_action( 'wp_footer', 'EsAudioPlayer_footer_filter' );

function EsAudioPlayer_footer_filter( $title ) 
{
	global $esp_tt_data;
	$esp_tt_data_encoded = base64_encode ( json_encode($esp_tt_data) );
	echo "<script type=\"text/javascript\">\n";
	echo "esp_tt_data_encoded = \"" . $esp_tt_data_encoded . "\";\n";
	echo "</script>\n";
}

/*
memo
0. EsAudioPlayer_title_filter makes code of including scripts and declaration of variables, and a script of deleting p-tags enclosing canvas tags.
1. EsAudioPlayer_filter_tt (priority 9) reads time tables.
2. EsAudioPlayer_filter_0 (priority 10) deletes white spaces in the series of shortcords.
3. EsAudioPlayer_shortcode (priority 12) makes code of declaration of class instances of players.
4. (deleted)EsAudioPlayer_filter_pdel (priority 15) replaces <p></p> tags encloseing canvas tags to <div></div> so that IE (explorercanvas.js) can display canvases.
5. (deleted)EsAudioPlayer_filter (priority 15) makes markups for image-click-mode.
6. EsAudioPlayer_filter_2 (priority 99) makes code of declaration of class instances of players at the end of the article.
7. EsAudioPlayer_footer_filter makes code of obtaining mime64-encoded time-table JSON data
*/
?>
