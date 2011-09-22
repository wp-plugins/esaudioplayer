<?php
/*
Plugin Name: EsAudioPlayer
Plugin URI: http://tempspace.net/plugins/?page_id=4
Description: Extremely Simple Ausio Player
Version: 1.0.0
Author: Atsushi Ueda
Author URI: http://tempspace.net/plugins/
License: GPL2
*/

define("ESP_DEBUG", 1);

function dbg2($str){$fp=fopen("/tmp/smdebug.txt","a");fwrite($fp,$str . "\n");fclose($fp);}

function esplayer_init() {
	if (!is_admin()) {
		wp_enqueue_script('jquery');
	}
}
add_action('init', 'esplayer_init');


$player_number = 1;
$esAudioPlayer_plugin_URL = get_option( 'siteurl' ) . '/wp-content/plugins/' . plugin_basename(dirname(__FILE__));



define("LEX_NULL", 100);
define("LEX_STRING", 101);
define("LEX_ALNUM", 102);
define("LEX_WHITE", 103);
define("LEX_MARK", 104);
define("LEX_EOL", 105);

define("LEX_PTAG_OPEN", 1000);
define("LEX_PTAG_CLOSE", 1001);
define("LEX_CANVASTAG", 1002);
define("LEX_IMGTAG_OPEN", 1003);
define("LEX_SRC", 1004);

$esplayer_local_token[0] = array("token"=>"<p>", "case"=>false, "code"=>LEX_PTAG_OPEN);
$esplayer_local_token[1] = array("token"=>"</p>", "case"=>false, "code"=>LEX_PTAG_CLOSE);
$esplayer_local_token[2] = array("token"=>"<canvas ", "case"=>false, "code"=>LEX_CANVASTAG);
$esplayer_local_token[3] = array("token"=>"<img", "case"=>false, "code"=>LEX_IMGTAG_OPEN);
$esplayer_local_token[4] = array("token"=>"src", "case"=>false, "code"=>LEX_SRC);

function esplayer_simplelexer(&$str, $pos, &$ret_str)
{
	global $esplayer_local_token;

	if ($pos >= mb_strlen($str)) {
		return LEX_EOL;
	}

	$rest_len = mb_strlen(mb_substr($str, $pos));

	for ($i=0; $i<count($esplayer_local_token); $i++) {
		$tok =$esplayer_local_token[$i]["token"]; 
		if ($rest_len >= mb_strlen($tok)) {
			$rtok = mb_substr($str, $pos, mb_strlen($tok));
			if (($esplayer_local_token[$i]["case"] && $rtok == $tok) || !(mb_stripos($rtok,$tok)===false)) {
				$ret_str = $rtok;
				return $esplayer_local_token[$i]["code"];	
			} 
		}
	}

	$mtr_lexer_alnum="abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_";
	$mtr_lexer_mark="/><>/\\!#$%&'()~=~-^~|[]?;:";
	$mtr_lexer_white=" \t\n\r";
	if (!(mb_strpos($mtr_lexer_alnum, mb_substr($str,$pos,1))===FALSE)) {
		for ($i=$pos; $i<mb_strlen($str); $i++) {
			if (mb_strpos($mtr_lexer_alnum, mb_substr($str,$i,1))===FALSE) {
				break;
			}
		}
		$ret_str = mb_substr($str, $pos, $i-$pos);
		return LEX_ALNUM;
	}

	if (!(mb_strpos($mtr_lexer_mark, mb_substr($str,$pos,1))===FALSE)) {
		$ret_str = mb_substr($str, $pos, 1);
		return LEX_MARK;
	}

	if (!(mb_strpos($mtr_lexer_white, mb_substr($str,$pos,1))===FALSE)) {
		for ($i=$pos; $i<mb_strlen($str); $i++) {
			if (mb_strpos($mtr_lexer_white, mb_substr($str,$i,1))===FALSE) {
				break;
			}
		}
		$ret_str = mb_substr($str, $pos, $i-$pos);
		return LEX_WHITE;
	}

	if (!(mb_strpos("\"", mb_substr($str,$pos,1))===FALSE)) {
		for ($i=$pos+1; $i<mb_strlen($str); $i++) {
			if (mb_substr($str,$i,1) == "\"") {
				$i++;
				break;
			}
		}
		$ret_str = mb_substr($str, $pos, $i-$pos);
		return LEX_STRING;
	}
		
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
//echo strlen($content)." ". $lex_pos . "  " . $ret . "  " . /*$token .*/ "<br>";
		if ($ret == LEX_WHITE) {
			continue;
		}
		//echo $lex_pos." ".$ret . " " . $token . "<br>";
//echo "(".$token.")";
		return $ret;
	}
}


function EsAudioPlayer_filter_0($raw_text) 
{
	$ret = mb_ereg_replace("\]<br />[\n]\[esplayer", "] [esplayer", $raw_text);
	return mb_ereg_replace("[\n]*\[esplayer", "[esplayer", $ret);
}
add_filter('the_content',  "EsAudioPlayer_filter_0", 10) ;


function EsAudioPlayer_filter_pdel($raw_text) 
{
	$ret = "";

	$cur_pos = 0;
	$lex_pos = 0;
	$token="";
	$p_open_pos = -1;
	$p_close_pos = -1;
	$es_pos = -1;

	for ( $i=0 ; $i<99999 ; $i++ ) {
		$token_code = esplayer_lex($raw_text, $lex_pos, $token);
		if ($token_code == LEX_EOL) {
			$ret = $ret . mb_substr($raw_text, $cur_pos);   // output final part of text
			break;
		}
		if ($token_code == LEX_PTAG_OPEN) {
			$p_open_pos = $lex_pos - mb_strlen("<p>");
		}
		if ($token_code == LEX_CANVASTAG) {
			$es_pos = $lex_pos - mb_strlen("<canvas ");
		}
		if ($token_code == LEX_PTAG_CLOSE) {
			$p_close_pos = $lex_pos - mb_strlen("</p>");
			if ($p_open_pos >= 0 && $p_open_pos < $es_pos && $es_pos < $p_close_pos) {
				$ret = $ret . mb_substr($raw_text, $cur_pos, $p_open_pos-$cur_pos);
				$ret = $ret . "<div>";
				$ret = $ret . mb_substr($raw_text, $p_open_pos+3, $p_close_pos-$p_open_pos-3);
				$ret = $ret . "</div>";
				$cur_pos = $lex_pos;
			}
		}
	}

	return $ret;
}
add_filter('the_content',  "EsAudioPlayer_filter_pdel", 15) ;


function EsAudioPlayer_filter($raw_text) {
	$cur_pos = 0;
	$lex_pos = 0;
	$token="";

	$ret = "";
	$flg_inner_img = false;
	$flg_src = false;
	$player_id = -1;

	global $esplayer_imgs_num;
	global $esplayer_imgs;
	global $esplayer_imgs_player_number;

	for ( $i=0 ; $i<99999 ; $i++ ) {
		$token_code = esplayer_lex($raw_text, $lex_pos, $token);
		if ($token_code == LEX_EOL) {
			$ret = $ret . mb_substr($raw_text, $cur_pos);   // output final part of text
			break;
		}
		if ($token_code == LEX_MARK && $token==">") {  // img tag closing detection
			if ($flg_inner_img) {
				$ret = $ret . mb_substr($raw_text, $cur_pos, $lex_pos - 1 - $cur_pos);

				// if the image is connected to audio, then the onClick attribute will be added to the img tag.
				if (mb_substr($ret, mb_strlen($ret)-1,1) == "/") {
					$ret = mb_substr($ret, 0, mb_strlen($ret)-1);
				}
				if ($player_id >= 0) {
					//$ret = $ret . "id=\"img_esplayer_".$player_id."\" onClick=\"alert('a');\"";
					$ret = $ret . "id=\"img_esplayer_".$player_id."\" onClick=\"javascript:esplayervar" . $player_id. ".func_play_stop();return false;\"";
				}
				$player_id = -1;
				$ret = $ret . "/>";
				$cur_pos = $lex_pos;
				$flg_inner_img = false;
				continue;
			}
		}
		if ($token_code == LEX_IMGTAG_OPEN) {// <img> start
			$flg_inner_img = true;
		}
		if ($flg_inner_img && $token_code==LEX_SRC) {
			$flg_src = true;
		}
		if ($flg_src && $token_code==LEX_STRING) {  // the string enclosed by double quotations after "src" is an url.
			$flg_src = false;
			$url = mb_ereg_replace('"', '', $token);
			for ($j=0; $j<$esplayer_imgs_num; $j++) {
				if ($url == $esplayer_imgs[$j]) {
					$player_id = $esplayer_imgs_player_number[$j];
					$flg_del_atag = true;
					break;
				}
			}
		}
	}

	return $ret ;
}
add_filter('the_content',  "EsAudioPlayer_filter", 15) ;

$esplayer_script = "";
$esplayer_mode = "x";



function EsAudioPlayer_shortcode($atts, $content = null) {
	global $player_number;
	global $esplayer_imgs_num, $esplayer_imgs, $esplayer_imgs_player_number;
	global $esplayer_script;
	global $esplayer_mode;

	do_shortcode($content);
	$url = "";
	$img = "";
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

	if ($img != "") {
		$esplayer_imgs[$esplayer_imgs_num] = $img;
		$esplayer_imgs_player_number[$esplayer_imgs_num++] = $player_number;
	}

	$id = "esplayer_" . (string)($player_number);
	$js_var='esplayervar' . (string)($player_number);

	if ($img == "" && $timetable_id == "") {
		$esplayer_mode="simple";
		$ret = "<div style=\"display:inline;position:relative;border:solid 0px #f00;\" id=\"" . $id . "_tmpspan\"><canvas id=\"" . $id . "\"></canvas></div>";
	} else if ($timetable_id != "") {
		$esplayer_mode="slideshow";
		$ret = "<div id=\"" . $id . "_tmpspan\" style=\"width:".$width."; height:".$height."; background-color:".$bgcolor."; border:".$border_box.";\">&nbsp;</div>";
		$url = "aa.mp3";
	} else {
		$esplayer_mode="imgclick";
		$ret = "";
	}

	$title_utf8="";
	$artist_utf8="";
	
	$esplayer_script = $esplayer_script . "var " . $js_var . ";\njQuery(document).ready(function() {\n";

	if ($esplayer_mode=="simple") {
		$esplayer_script = $esplayer_script . "ReplaceContainingCanvasPtag2div('".$id."_tmpspan');\n";
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
	echo  "<script type=\"text/javascript\" src=\"" . $esAudioPlayer_plugin_URL . "/esplayer_jqm_ready.js\"></script>\n";
	echo  "<script src=\"http://code.jquery.com/mobile/1.0b3/jquery.mobile-1.0b3.min.js\"></script>\n";
	echo  "<script type=\"text/javascript\" src=\"" . $esAudioPlayer_plugin_URL . "/print_r.js\"></script>\n";
	echo  "<script type=\"text/javascript\" src=\"" . $esAudioPlayer_plugin_URL . "/binaryajax.js\"></script>\n";
	echo  "<script type=\"text/javascript\" src=\"" . $esAudioPlayer_plugin_URL . "/soundmanager2-jsmin.js\"></script>\n";
	echo  "<script type=\"text/javascript\"> var esAudioPlayer_plugin_URL = '" . $esAudioPlayer_plugin_URL . "'; </script>\n";
	echo  "<script type=\"text/javascript\" src=\"" . $esAudioPlayer_plugin_URL . "/esplayer_tes.js\"></script>\n";
	echo "<script type=\"text/javascript\">\nvar esp_tt_data_encoded='';\nvar esp_tt_data; </script>\n";
	echo  "<script type=\"text/javascript\" src=\"" . $esAudioPlayer_plugin_URL . "/esplayer_tt.js\"></script>\n";
	echo	"<script type=\"text/javascript\">\n".
		"function ReplaceContainingCanvasPtag2div(id)\n" . 
		"{return;\n" . 
		"	var elm = jQuery('#'+id);\n" .  
		"	var i;\n" .  
		"	for (i=0; i<999; i++) {\n" . 
		"		elm = elm.parent();\n" . 
		"		if (elm.get(0).tagName.toLowerCase() == 'p') {\n" . 
		"			var html = elm.html(); \n" .
		"			var pr = jQuery(elm).parent();".
		"			//elm.replaceWith(\"<div style=\\\"border:3px solid #ff0000\\\">\"+html+\"</div>\"); \n".
		"			//alert(jQuery(pr).html());\n".
		"			break; \n".
		"		} else if (elm.get(0).tagName.toLowerCase() == 'body') {\n" . 
		"			break;\n" . 
		"		}\n" . 
		"	}\n" . 
		"}\n".
		"</script>\n";

} 


// 設定メニューの追加
add_action('admin_menu', 'esaudioplayer_plugin_menu');
function esaudioplayer_plugin_menu()
{
	/*  設定画面の追加  */
	add_submenu_page('plugins.php', 'EsAudioPlayer Configuration', 'EsAudioPlayer', 'manage_options', 'esaudioplayer-submenu-handle', 'esaudioplayer_magic_function'); 
}


/*  設定画面出力  */
function esaudioplayer_magic_function()
{
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

	}

	$plugin = plugin_basename('EsAudioPlayer'); $plugin = dirname(__FILE__);
	?>
	<script type="text/javascript" src="js/jquery-1.4.2.min.js"></script>
	<script type="text/javascript">

	</script>
	<div class="wrap">
		<h2>EsAudioPlayer configuration</h2>

		<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
		<?php wp_nonce_field('update-options');  ?>
		<?php $basecolor_play = get_option("esaudioplayer_basecolor_play", "#ffcc99"); ?>
		<?php $symbolcolor_play = get_option("esaudioplayer_symbolcolor_play", "#cc0066"); ?>
		<?php $basecolor_stop = get_option("esaudioplayer_basecolor_stop", "#ffcc99"); ?>
		<?php $symbolcolor_stop = get_option("esaudioplayer_symbolcolor_stop", "#cc0066"); ?>
		<?php $basecolor_pause = get_option("esaudioplayer_basecolor_pause", "#ffcc99"); ?>
		<?php $symbolcolor_pause = get_option("esaudioplayer_symbolcolor_pause", "#cc0066"); ?>
		<?php $slidercolor_line = get_option("esaudioplayer_slidercolor_line", "#cc0066"); ?>
		<?php $slidercolor_knob = get_option("esaudioplayer_slidercolor_knob", "#cc0066"); ?>
		<?php $shadowcolor = get_option("esaudioplayer_shadowcolor", "#888888"); ?>
		<?php $shadowsize = get_option("esaudioplayer_shadowsize", "0.1"); ?>
		<table class="form-table">
		<tr valign="top">
		<th scope="row">Base Color (Play) <input type="text" name="esaudioplayer_basecolor_play" value="<?php echo $basecolor_play; ?>" /></th>
		</tr>
		<tr>
		<th scope="row">Symbol Color (Play) <input type="text" name="esaudioplayer_symbolcolor_play" value="<?php echo $symbolcolor_play; ?>" /></th>

		</tr>
		<tr valign="top">
		<th scope="row">Base Color (Stop) <input type="text" name="esaudioplayer_basecolor_stop" value="<?php echo $basecolor_stop; ?>" /></th>
		</tr>
		<tr>
		<th scope="row">Symbol Color (Stop) <input type="text" name="esaudioplayer_symbolcolor_stop" value="<?php echo $symbolcolor_stop; ?>" /></th>
		</tr>

		</tr>
		<tr valign="top">
		<th scope="row">Base Color (Pause) <input type="text" name="esaudioplayer_basecolor_pause" value="<?php echo $basecolor_pause; ?>" /></th>
		</tr>
		<tr>
		<th scope="row">Symbol Color (Pause) <input type="text" name="esaudioplayer_symbolcolor_pause" value="<?php echo $symbolcolor_pause; ?>" /></th>
		</tr>

		</tr>
		<tr valign="top">
		<th scope="row">Slider Color (line) <input type="text" name="esaudioplayer_slidercolor_line" value="<?php echo $slidercolor_line; ?>" /></th>
		</tr>
		<tr>
		<th scope="row">Slider Color (knob) <input type="text" name="esaudioplayer_slidercolor_knob" value="<?php echo $slidercolor_knob; ?>" /></th>
		</tr>

		</tr>
		<tr valign="top">
		<th scope="row">Shadow Size<input type="text" name="esaudioplayer_shadowsize" value="<?php echo $shadowsize; ?>" /></th>
		</tr>
		<tr>
		<th scope="row">Shadow Color<input type="text" name="esaudioplayer_shadowcolor" value="<?php echo $shadowcolor; ?>" /></th>
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
4. EsAudioPlayer_filter_pdel (priority 15) replaces <p></p> tags encloseing canvas tags to <div></div> so that IE (explorercanvas.js) can display canvases.
5. EsAudioPlayer_filter (priority 15) makes markups for image-click-mode.
6. EsAudioPlayer_filter_2 (priority 99) makes code of declaration of class instances of players at the end of the article.
7. EsAudioPlayer_footer_filter makes code of obtaining mime64-encoded time-table JSON data
*/
?>
