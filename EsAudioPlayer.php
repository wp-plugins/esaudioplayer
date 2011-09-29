<?php
/*
Plugin Name: EsAudioPlayer
Plugin URI: http://tempspace.net/plugins/?page_id=4
Description: This is an Extremely Simple Audio Player plugin.
Version: 1.1.1a
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
	return mb_ereg_replace("[\n]*\[esplayer", "[esplayer", $ret);
}
add_filter('the_content',  "EsAudioPlayer_filter_0", 10) ;



$esplayer_script = "";
$esplayer_mode = "x";



function EsAudioPlayer_shortcode($atts, $content = null) {
	global $player_number;
	global $esplayer_imgs_num, $esplayer_imgs, $esplayer_imgs_player_number;
	global $esplayer_script;
	global $esplayer_mode;

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

	if ($img_id == "" && $timetable_id == "") {
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
	echo  "<script type=\"text/javascript\" src=\"" . $esAudioPlayer_plugin_URL . "/esplayer_tes.js\"></script>\n";
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
	<script type="text/javascript">
		jQuery(document).ready(function(){
			jQuery('#esaudioplayer_basecolor_play').focus(function(){jQuery('#colorpicker1').show();});

			jQuery('#colorpicker1').farbtastic('#esaudioplayer_basecolor_play').hide();
			jQuery('#colorpicker2').farbtastic('#esaudioplayer_symbolcolor_play').hide();
			jQuery('#colorpicker3').farbtastic('#esaudioplayer_basecolor_stop').hide();
			jQuery('#colorpicker4').farbtastic('#esaudioplayer_symbolcolor_stop').hide();
			jQuery('#colorpicker5').farbtastic('#esaudioplayer_basecolor_pause').hide();
			jQuery('#colorpicker6').farbtastic('#esaudioplayer_symbolcolor_pause').hide();
			jQuery('#colorpicker7').farbtastic('#esaudioplayer_slidercolor_line').hide();
			jQuery('#colorpicker8').farbtastic('#esaudioplayer_slidercolor_knob').hide();
			jQuery('#colorpicker9').farbtastic('#esaudioplayer_shadowcolor').hide();

			SetPosition('#esaudioplayer_basecolor_play','#colorpicker1');
			SetPosition('#esaudioplayer_symbolcolor_play','#colorpicker2');
			SetPosition('#esaudioplayer_basecolor_stop','#colorpicker3');
			SetPosition('#esaudioplayer_symbolcolor_stop','#colorpicker4');
			SetPosition('#esaudioplayer_basecolor_pause','#colorpicker5');
			SetPosition('#esaudioplayer_symbolcolor_pause','#colorpicker6');
			SetPosition('#esaudioplayer_slidercolor_line','#colorpicker7');
			SetPosition('#esaudioplayer_slidercolor_knob','#colorpicker8');
			SetPosition('#esaudioplayer_shadowcolor','#colorpicker9');

			jQuery('#esaudioplayer_basecolor_play').focus(function(){jQuery('#colorpicker1').show();});
			jQuery('#esaudioplayer_symbolcolor_play').focus(function(){jQuery('#colorpicker2').show();});
			jQuery('#esaudioplayer_basecolor_stop').focus(function(){jQuery('#colorpicker3').show();});
			jQuery('#esaudioplayer_symbolcolor_stop').focus(function(){jQuery('#colorpicker4').show();});
			jQuery('#esaudioplayer_basecolor_pause').focus(function(){jQuery('#colorpicker5').show();});
			jQuery('#esaudioplayer_symbolcolor_pause').focus(function(){jQuery('#colorpicker6').show();});
			jQuery('#esaudioplayer_slidercolor_line').focus(function(){jQuery('#colorpicker7').show();});
			jQuery('#esaudioplayer_slidercolor_knob').focus(function(){jQuery('#colorpicker8').show();});
			jQuery('#esaudioplayer_shadowcolor').focus(function(){jQuery('#colorpicker9').show();});

			jQuery('#esaudioplayer_basecolor_play').blur(function(){jQuery('#colorpicker1').hide();});
			jQuery('#esaudioplayer_symbolcolor_play').blur(function(){jQuery('#colorpicker2').hide();});
			jQuery('#esaudioplayer_basecolor_stop').blur(function(){jQuery('#colorpicker3').hide();});
			jQuery('#esaudioplayer_symbolcolor_stop').blur(function(){jQuery('#colorpicker4').hide();});
			jQuery('#esaudioplayer_basecolor_pause').blur(function(){jQuery('#colorpicker5').hide();});
			jQuery('#esaudioplayer_symbolcolor_pause').blur(function(){jQuery('#colorpicker6').hide();});
			jQuery('#esaudioplayer_slidercolor_line').blur(function(){jQuery('#colorpicker7').hide();});
			jQuery('#esaudioplayer_slidercolor_knob').blur(function(){jQuery('#colorpicker8').hide();});
			jQuery('#esaudioplayer_shadowcolor').blur(function(){jQuery('#colorpicker9').hide();});
		});
		function SetPosition(el, cl)
		{
			var left = 0;
			var top = 0;
			left = jQuery(el).offset().left + jQuery(el).width()*1.2;
			top = jQuery(el).offset().top -jQuery(cl).height()/2;
			var height = jQuery(el).height();
			if (!isNaN(parseInt(jQuery(el).css('padding-top')))) height += parseInt(jQuery(el).css('padding-top'));
			if (!isNaN(parseInt(jQuery(el).css('margin-top')))) height += parseInt(jQuery(el).css('margin-top'));
			var y = Math.floor(top) + height;
			var x = Math.floor(left);
			jQuery(cl).css('top',y+"px");
			jQuery(cl).css('left',x+"px");
		}
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
		<th scope="row">Base Color (Play) <input type="text" id="esaudioplayer_basecolor_play" name="esaudioplayer_basecolor_play" value="<?php echo $basecolor_play; ?>" /></th>
		</tr>
		<tr>
		<th scope="row">Symbol Color (Play) <input type="text" id="esaudioplayer_symbolcolor_play" name="esaudioplayer_symbolcolor_play" value="<?php echo $symbolcolor_play; ?>" /></th>

		</tr>
		<tr valign="top">
		<th scope="row">Base Color (Stop) <input type="text" id="esaudioplayer_basecolor_stop" name="esaudioplayer_basecolor_stop" value="<?php echo $basecolor_stop; ?>" /></th>
		</tr>
		<tr>
		<th scope="row">Symbol Color (Stop) <input type="text" id="esaudioplayer_symbolcolor_stop" name="esaudioplayer_symbolcolor_stop" value="<?php echo $symbolcolor_stop; ?>" /></th>
		</tr>

		</tr>
		<tr valign="top">
		<th scope="row">Base Color (Pause) <input type="text" id="esaudioplayer_basecolor_pause" name="esaudioplayer_basecolor_pause" value="<?php echo $basecolor_pause; ?>" /></th>
		</tr>
		<tr>
		<th scope="row">Symbol Color (Pause) <input type="text" id="esaudioplayer_symbolcolor_pause" name="esaudioplayer_symbolcolor_pause" value="<?php echo $symbolcolor_pause; ?>" /></th>
		</tr>

		</tr>
		<tr valign="top">
		<th scope="row">Slider Color (line) <input type="text" id="esaudioplayer_slidercolor_line" name="esaudioplayer_slidercolor_line" value="<?php echo $slidercolor_line; ?>" /></th>
		</tr>
		<tr>
		<th scope="row">Slider Color (knob) <input type="text" id="esaudioplayer_slidercolor_knob" name="esaudioplayer_slidercolor_knob" value="<?php echo $slidercolor_knob; ?>" /></th>
		</tr>

		</tr>
		<tr valign="top">
		<th scope="row">Shadow Size<input type="text" name="esaudioplayer_shadowsize" value="<?php echo $shadowsize; ?>" /></th>
		</tr>
		<tr>
		<th scope="row">Shadow Color<input type="text" id="esaudioplayer_shadowcolor" name="esaudioplayer_shadowcolor" value="<?php echo $shadowcolor; ?>" /></th>
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
