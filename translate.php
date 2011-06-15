<?php
/**
 * Plugin Name: Translate
 * Plugin URI: http://genja.org/wp/2011/06/worpress-translate/
 * Description: Display different content from within a single post or page based on a user's language preference. The administrator may define terms and invoke their translations with template_tags() or [shortcodes]. 
 * Version: 1.3
 * Author: Jaroslav Rakhmatoullin
 * Author URI: http://genja.org/
 *
 * @package Translate
 * @version 1.3
 */


/*
 * DEFAULTS AND GLOBALS
 */

global $wpdb, $tbllangs, $tbldict;
global $WPTRPLG_ERRATA;
global $plugdir;

$path = preg_split(":".DIRECTORY_SEPARATOR.":", __FILE__);
$plugdir = $path[(count($path)-2)];


$WPTRPLG_ERRATA  =  array(
	'error_add_language_first'	=>	
"You can not add terms before at least one language has  been defined.",
	'error_no_empty_languages'	=>	
"A language can not be an empty string.",
	'error_only_lphnmrc_langs'	=>	
"Languages may comprise only alphanumeric (abc123...), latin characters.",
	'error_pre12_unsupported'	=>	
'The upgrade script has not been tested with versions of Translate below 1.2.
<form method="post" action=""><input name="do_upgrade_12to13" type="submit" 
value="Run anyway"/></form>',
	'error_term_undefined'	=>	
"\n<!-- The term $term is not defined. Check your spelling or add it in the admin panel-->\n",
);

$tbllangs	= $wpdb->prefix."translate_langs";
$tbldict	= $wpdb->prefix."translate_dict";

/*
 * HOOKS AND FILTERS
 */

// on upgrade
add_action('plugins_loaded', 'jrv_install_translate');
register_activation_hook(__FILE__,'jrv_install_translate');

add_action('admin_menu', 	'translate_admin_options');
add_action('init',			'set_session_language');
add_action('wp_head',		't_shortcode_style');
add_action('plugins_loaded','translate_widget');
add_filter('widget_text',	'do_shortcode');
add_shortcode('translate',	't_shortcode',2);
add_shortcode('translations','list_translations',1); 
add_shortcode('list_pages', 'translate_list_pages');
add_shortcode('tdict',		'translate_term_shortcde' );
add_shortcode('tseveral',	't_several_shortcode' );

/*
 * INSTALL AND UPGRADE 
 */

function jrv_install_translate(){

	global $wpdb, $tbldict, $tbllangs;

	$current_version = jrv_grep_version(__FILE__);
	$installed_version = get_option('jrv_translate_version');


	$wpdb->query(
	"CREATE TABLE IF NOT EXISTS `$tbllangs` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`name` varchar(25) NOT NULL,
		`main` tinyint(1) NOT NULL default 0,
		`order` tinyint(1) NOT NULL default 0,
		`icon` varchar(20) NOT NULL default '',
		PRIMARY KEY  (`id`)
	)");

	$wpdb->query(
	"CREATE TABLE IF NOT EXISTS `$tbldict` (
		`term` varchar(25) NOT NULL,
		`lang_id` int(11) NOT NULL,
		`translation` text NOT NULL DEFAULT '',
		PRIMARY KEY (`term`,`lang_id`),
		FOREIGN KEY (`lang_id`) REFERENCES $tbllangs(`id`) ON DELETE CASCADE ON UPDATE CASCADE
	)");



	if (!$installed_version){
		add_option("jrv_translate_version", $current_version);

		require_once(ABSPATH.'/wp-admin/admin-functions.php');
		$plugins = get_plugins();

		$name = "";

		$cv = floatval( jrv_grep_version(__FILE__));

		do {
			$plugin = next($plugins);

			$name  = $plugin['Name'];
			$iv = floatval($plugin['Version']);

			$tr12 = ! (($name == "Translate") && ($iv != $cv) && ($cv > $iv));

		} while ( $tr12 && $plugin) ;

		if ( $iv < 1.2) {
			say_error('error_pre12_unsupported');
		} else {
			jrv_translate_upgrade12to13();
		}
	}
}

function jrv_translate_upgrade12to13(){
	global $wpdb, $tbllangs, $tbldict;

	$wpdb->query(
		"INSERT INTO $tbllangs (name,main) "
		."(SELECT name,main FROM wp_translate)");

	$wpdb->query("DROP TABLE wp_translate");

	$langs = $wpdb->get_col("SELECT name from $tbllangs");

	foreach ($langs as $lang){

		$lt = $lang."_title";

		$wpdb->query(
		"UPDATE $wpdb->postmeta SET meta_key='$lt' WHERE meta_key='$lang'");
	}

}

//// on update
//add_action('plugins_loaded', 'myplugin_update_db_check');
//function myplugin_update_db_check() {
//	    global $jal_db_version;
//	   if (get_site_option('jal_db_version') != $jal_db_version) {
//		        jal_install();
//	   }
//}

function testink(){
}



/*
 * SHORTCODES
 */
function t_shortcode_style() {

    global $wpdb, $tbllangs, $tbldict;
    echo '<style type="text/css">';
    $langs = $wpdb->get_results("SELECT name FROM $tbllangs WHERE name != '".$_SESSION['language']."'", ARRAY_A);

    foreach($langs as $lang) {
        echo ".translate_".$lang['name']."{display:none;}\n";
    }
    echo '</style>';
}

function t_shortcode($atts, $content = null) {
    $lang = strtolower($atts['lang']);

	if ( ! isset($atts['nodiv'])){
    	$content = "<div class='translate_".$lang."'>".$content."</div>";
	}

	if ($lang == $_SESSION['language']){
    	return $content;
	} 
}


/*
 * WIDGETS 
 *
 */

function translate_pages_widget() {
     translate_list_pages();
}

function translate_widget() {
     register_sidebar_widget(__('Translate: Page List'), 'translate_pages_widget');
}

function translate_admin_options() { 
	//add_menu_page('Translate','Translate',8,__FILE__, 'translate_options');
	add_submenu_page(
		'plugins.php',
		'Translate',
		'Translate',
		8,
		__FILE__,
		'translate_options_page'
	);

}

function translate_options_page() {

	$disable_confirm = ($_POST['disable_confirm'] == "on")? 'checked="checked"' : "";
	$disable_expand = ($_POST['disable_expand'] == "on")? 'checked="checked"' : "";

	/*
	if ( ! isset($_POST['disable_confirm'])){
		$disable_confirm = 'checked="checked"';
	}
	 */

	do_post_actions_translate( $_POST ); //figure out what to run...

	global $plugdir;
	global $wpdb, $tbllangs, $tbldict;
	$terms = array(); 
	$lanlen =  0;

	$ie = getBrowser();
	$ie = $ie['name'] == "Internet Explorer";

	// result, languages, terms sets
	
	$rs = $wpdb->get_results(
		'SELECT id, lang_id, term, name, translation '.
		"FROM $tbllangs, $tbldict ".
		'WHERE id=lang_id AND term IN '.
			"(SELECT DISTINCT term FROM $tbldict) ".
		'ORDER BY term, lang_id'
		,ARRAY_A);

	$ls = $wpdb->get_results(
		"SELECT * FROM $tbllangs ORDER BY `order`,name", ARRAY_N); 
	$ts = $wpdb->get_results(
		"SELECT DISTINCT term FROM $tbldict ORDER BY term", ARRAY_N); 

	$languages = $wpdb->get_col("SELECT name FROM $tbllangs ORDER BY `order`,name"); // languages array


	/* Just flip things around so we have an "array of" terms instead of an
	 * "array of rows".
	 * rows were: 
	 * +----+---------+--------+-----------+--------------+
	 * | id | lang_id | term   | name      | translation  |
	 * +----+---------+--------+-----------+--------------+
	 * |  1 |       1 | home   | english   | home         |
	 * |  2 |       2 | home   | norwegian | hjem         |
	 * (...)
	 *
	 * $terms are: 
	 * [home] => Array
	 *	(
	 *		[english] => home
	 *		[norwegian] => hjem
	 *		[nederlans] => huis
	 *		[russian] => dom
	 *	)
	 * (...)
	 */
	foreach ($rs as $row)  {
		$translation[$row['name']] = $row['translation'];
		$terms[$row['term']] = $translation;
	}
	foreach ($terms as $term => $translations){
		for ( $i=0; $i<count($languages); $i++){
			$lang = $languages[$i];
			if (!array_key_exists($lang, $translations) ){
				// make sure every term has a value for every language. 
				$translations[$lang] = "";
			}
		}
		ksort($translations);
		$terms[$term] = $translations;
	}

	reset($terms);
	$firstKey = key($terms);

	if ( count($terms)> 0){
	foreach ($terms[$firstKey] as $lang => $trans){
		$prelen = $lanlen;
		$lanlen = strlen($lang);
		$lanlen = ( $prelen > $lanlen)? $prelen : $lanlen;
	}
	}

	//debug($lanlen,"lanlen");

	/*
	 *  Some dimentions for the css style. 
	 *
	 *  $charwidth is of courier new at 12 px, ofc idk for sure, its just an 
	 *  aproximation.
	 *
	 *  $border assumes all sides of an element are the same size and that 
	 *  they are divisible by 2. so if you have 1 px borders then set this 
	 *  var to 2. and you are on your own with odd-sized borders. 
	 *
	 *  the general idea here is to make the cell directive at least as wide as the 
	 *  longest language name. 
	 */

	$border = 2;
	$charwidth = 9; 
	$cols 	= count($terms[$firstKey]) +1 ;	// add 1 for the terms column
	if ($cols == 1) { $cols =  2; }
	if ($lanlen == 0 ) { $lanlen = 10; }
	$rows 	= count($terms) +2;		// add one for headers and one for new term
	$cellw 	= $lanlen * $charwidth;
	$cellw 	= 75;
	$roww 	= $cellw * $cols + $border*$cols; 
	$btnw 	=  20;
	$langs 	= count($ls) +1 ; // +new +headers

	$lang_table_w = ($cellw * 3 + $btnw*3 + 30 + 4 );
	$term_table_w = ($cellw * 2 + $btnw*2 + 30 + 4 );



	$thickbox_css = file_get_contents("../wp-content/plugins/$plugdir/thickbox.css");
	echo '
	<script type="text/javascript" src="../wp-content/plugins/'.$plugdir.'/jquery-1.6.1.min.js"></script>
	<script type="text/javascript" src="../wp-content/plugins/'.$plugdir.'/confirm-delete.js"></script>
	<script type="text/javascript" src="../wp-content/plugins/'.$plugdir.'/thickbox-compressed.js"></script>

	<style type="text/css">
	'.$thickbox_css.'
	</style>
	<style type="text/css">
	.cell {
		font-size:12px;
		font-family:Courier new;
		display:block;
		width:'. ($cellw -6 + ($ie? 0:0)) .'px;
		padding:0px 3px 0px 3px;
		margin:0;
		height:20px;
		float:left;
		border:1px solid #ffffdd;
		line-height:17px;
		letter-spacing:0px;
	}

	input.cell {
		font-size:12px;
		font-family:Courier new;
		display:block;
		width:'. ($cellw -6 + ($ie? 8:0)) .'px;
		padding:0px 3px 0px 3px;
		margin:0;
		height:'.(20 + ($ie? 2:0)).'px;
		float:left;
		border:1px solid #ffffdd;
		line-height:17px;
		letter-spacing:0px;
		background:#eff3b4;

	}

	.term {
		height:20px;
		font-family: Verdana;
		font-weight:bold;
		font-size:14px;
		background:#d4d0c8;
		border:1px solid #ffffdd;
	}

	select.flag_icon {
		width:'. ($cellw  +5) .'px;
		border:1px solid #ffffdd;
		height:15px;
		float:left;
		background:#eff3b4;
		margin:0;
		padding:0;
	}
	option.flag_icon {
		border:0px solid black;
	}

	.lastcell {
	}

	.tableholder {
		width:'.($lang_table_w +20).';
		border:0px solid red;
	}


	.lang_table {
		width:'. ($lang_table_w+20) .'px;
		height:'. (20*($langs+1) + $border * $langs ) .'px;
		border:1px solid #abcccc;
		border:0px solid #abcccc;
	}
	.lang_row {
		width:'. ($lang_table_w - 0)  .'px; /* 6: buttons-margin */
		margin-left:0px;
		border:0px solid black;
	}


	.term_table {
		width:'. ($term_table_w) .'px;
		height:'. (20*($langs+1) + $border * $langs ) .'px;
		border:0px solid #abcccc;
	}
	.term_row {
		width:'. ($term_table_w - 30 +8)  .'px; /* 8: buttons-margin */
		margin-left:20px;
	}


	.dict_table {
		width:'. ($roww + $btnw*2 + 32 ).'px;
		height:'. (20*$rows + $border*$rows ) .'px;
		border:0px solid #123fff;
		margin-top:21px;
	}
	.dict_row {
		width:'. ($roww + $btnw*2  +8) .'px; /* 8: buttons-margin*/
		margin-left:20px;
	}


	.header {
		background:#d4d0c8;
	}

	.nobg {	background: none; }
	.add { background: url("../wp-content/plugins/'.$plugdir.'/buttons/add20.png") no-repeat top left;}
	.rename { background: url("../wp-content/plugins/'.$plugdir.'/buttons/refresh20.png") no-repeat top left;}
	.save { background: url("../wp-content/plugins/'.$plugdir.'/buttons/floppy20.png") no-repeat top left;}
	.delete { background: url("../wp-content/plugins/'.$plugdir.'/buttons/delete20.png") no-repeat top left;}
	.tb_no { background: url("../wp-content/plugins/'.$plugdir.'/buttons/cancel.png") no-repeat 5px 4px;}
	.tb_yes { background: url("../wp-content/plugins/'.$plugdir.'/buttons/checkmark.png") no-repeat 5px 4px;}
	.go_up { background: url("../wp-content/plugins/'.$plugdir.'/buttons/up.png") no-repeat top left;}
	.go_down { background: url("../wp-content/plugins/'.$plugdir.'/buttons/down.png") no-repeat top left;}

	input.button_thickbox {
		float:right;
		width:100px;
		border:none;
		cursor: pointer;
		border:1px solid pink;
		font-size:28px;
		text-indent:32px;
		margin:20px 0px 0px 0px;
	}
	input.button_dict {
		float:left;
		width:20px;
		height:20px;
		margin:0;
		padding:0;
		margin-right:4px;
		border:none;
		text-indent:100px;
		cursor: pointer;
	}
	input.button_padding {
		float:left;
		width:20px;
		height:20px;
		margin:0;
		padding:0;
		margin-right:4px;
		border:none;
		text-indent:100px;
    	opacity:0.0;
		filter:alpha(opacity=0);
		background: url("../wp-content/plugins/'.$plugdir.'/buttons/delete20.png") no-repeat top left;
	}

	.translate_error {
		color:red;
	}
	h2.leftmargin75 {
		margin-left:75px;
	}
	.deflang {
		text-decoration:underline;
	}
	.newword {
		color:green;
		font-weight:bold;
	}
	p#TBmsg {
		margin:0px 0px 0px 0px;

	}
	table,tr,td {
		padding:0;
		margin:0;
	}
	</style>';
	/*
	 * LANGUAGES
	 */
echo '
	<div class="wrap">
	<div id="icon-plugins" class="icon32"><br /></div>
	<h2>Translate Options</h2>
	<table><tr>
	<td class="tableholder">


	<h2 class="leftmargin75">Languages</h2>
	<div class="lang_table">
	  <div class="lang_row">
	    <input type="submit" class="button_padding" />
	    <input type="submit" class="button_padding" />
	    <input type="submit" class="button_padding" />
	    <b class="cell term">language</b> 
	    <b class="cell term">rename</b> 
	    <b class="cell term">flag</b> 
	  </div>';
	foreach ($ls as $row) {
		$i = $row[0]; // id
		$n = $row[1]; // name 
		$m = $row[2]; // main
		$f = $row[4]; // flag
echo '
	  <div class="lang_row">
	    <form method="post" action="">
		  <table style="float:left;">
		  <tr><td> <input name="do_resort" style="height:8px;margin-right:0px;" class="button_dict go_up" type="submit" value="up" /> </td> </tr>
		  <tr><td> <input name="do_resort" style="height:8px;margin-right:0px;" class="button_dict go_down" type="submit" value="down" /> </td></tr>
		  </table>
	      <input name="do_save_lang" class="button_dict save" type="submit" value="1" />
	      <input name="do_delete_lang" class="button_dict delete" type="submit" value="1" />
	      <input name="id" type="hidden" value="'.$i.'" />
	      <b class="cell term'.($m?" deflang":"").'">'.$n.'</b>
  	      <input name="newname" value="'.$n.'" class="cell'.($m?" deflang":"").'" /><!-- FLAG: '.$f.'-->';
echo 	get_flag_options_html($f);
echo '
	    </form>
	  </div>';
	}
echo '
	  <div class="lang_row">
	    <form method="post" action="">
	      <input name="dummy" class="button_dict nobg" type="submit" value="1" />
	      <input name="do_add_lang" class="button_dict add" type="submit" value="1" />
	      <input name="dummy" class="button_dict nobg" type="submit" value="1" />
	      <b class="cell term">n/a</b>
  	      <input name="newname" value="" class="cell" />';
echo 	get_flag_options_html("default.gif");
echo '
	    </form>
	  </div>
	</div>';
	/*
	 * TRANSLATION TERMS
	 */
echo '
	<!--/td-->
	<!--td class="tableholder"-->
	<h2 class="leftmargin75">Terms</h2>
	<div class="term_table">
	  <div class="term_row">
	    <input type="submit" class="button_padding" />
	    <input type="submit" class="button_padding" />
	    <b class="cell term">current</b> 
	    <b class="cell term">rename</b> 
	  </div>';
	foreach ($ts as $row) {
		$t = $row[0]; //term
echo '
	  <div class="term_row">
	    <form method="post" action="">
	      <input name="do_rename_term" class="button_dict rename" type="submit" value="1" />
	      <input name="do_delete_term" class="button_dict delete" type="submit" value="1" />
	      <input name="term" type="hidden" value="'.$t.'" />
	      <b class="cell term">'.$t.'</b>
  	      <input name="newname" value="'.$t.'" class="cell" />
	    </form>
	  </div>';
	}
echo '
	</div>

	</td><td valign="top">
	<div style="width:400px;padding:40px 10px 0px 10px;">
	  <p><b>Languages:</b>  The default language is displayed with an underline. Re-add a language to make it default. The ordering mechanism is not the smartest, use both up and down on all languages to make it work. </p>
	  <p><b>Flags:</b> Add a file to wp-content/plugins/translate/flags/ to see it listed in the drop-down menu.</p>
	  <p><b>Terms:</b>	The refresh button will rename a term.</p>
	  <p><b>Dictionary:</b> After adding a new <b class="newword">word</b> you will see a table. You may add more terms or modify the translations of existing ones in tat table.</p>
	  <p><b>All tables:</b> Use the buttons on the left of each table row to commit your changes. Editing several rows is currently unsupported. Please work with one term at a time.</p>
	<!--
	<form action="" method="post">
	  <fieldset>
	    <legend>Annoyances:</legend>
	      <input id="disable_confirm" type="checkbox" name="disable_confirm" '.$disable_confirm.'">
	      Disable &laquo;Are you sure?&raquo; dialog <br />
	      <input id="disable_expand" type="checkbox" name="disable_expand" '.$disable_expand.'">
	      Disable input field expansion
	  </fieldset>
	</form>
	-->
	</div>
	</td></tr>
	</table>
	<!--tr><td class="tableholder" colspan="2"-->';
	/*
	 * DICTIONARY 
	 */
echo '
	<h2 class="leftmargin75">Dictionary entries</h2>';
	if ( count($terms)>0): 
echo '
	  <div class="dict_table" style="margin-top:-3px;">
	    <div class="dict_row">
	      <input type="submit" class="button_padding" />
	      <input type="submit" class="button_padding" />
	      <b class="cell term">term</b>';
		foreach ($terms[$firstKey] as $lang => $trans){
		    echo "\n\t      <b class=\"cell header\">$lang</b>";
		}
echo '
	      <b style="lastcell"></b>
	  </div>';
		foreach ($terms as $term => $translations){
echo '
	  <div class="dict_row">
	    <form method="post" action="">
	      <input name="do_save_term" class="button_dict save" type="submit" value="1" />
	      <input name="do_delete_term" class="button_dict delete" type="submit" value="1" />
	      <input name="term" type="hidden" value="'.$term.'" />
	      <b class="cell term">'.$term.'</b>';
			foreach ($translations as $lang => $translation) {
echo '
	      <input name="'.$lang.'" value="'.$translation.'" class="cell" />';
			}
echo '
	      <b style="lastcell"></b>
	    </form>
	  </div>';
		}
echo '
	  <div class="dict_row">
	    <form method="post" action="">
	      <input name="do_add_term" class="button_dict add" type="submit" value="1" />
	      <input name="dummy" class="button_dict nobg" type="submit" value="1" />
	      <input name="term" class="cell" value="" />';
		foreach ($translations as $lang => $translation) {
echo '
	      <input name="'.$lang.'" value="" class="cell" />';
		}
echo '
	  <!--/td></tr></table--></div>';

	else:  // initial state where there are no terms.
echo '
	  <p>
	    <form method="post" action="">
	      <input name="do_add_term" class="button_dict add" type="submit" value="1" />
	      <input name="dummy" class="button_dict nobg" type="submit" value="1" />
	      <input name="term" class="cell newword" value="word"  />';
		foreach ($languages as $lang ) {
echo '
	      <input name="'.$lang.'" value="'.$lang.'" class="cell" />';
			}
echo '
	    </form>
	  </p>';
	endif;

	//echo "<div style=\"clear:both\"></div>";
echo '

	</div>
	<div class="clear"></div>


	<div id="TBcontent" style="display: none;">
	  <p id="TBmsg"></p>
	  <input type="submit" id="TBsubmit" class="button_thickbox tb_yes" value="Yes" >
	  <input type="submit" id="TBcancel" class="button_thickbox tb_no"  value="No" />&nbsp;
	  <input type="hidden" id="TBok" value="" name="0" />
	</div>

	</div>';
}

/*
 * ADMIN PANEL POST ACTIONS
 */


function do_post_actions_translate( $post ){
	//debug($post, "POST");

	$plugpath = dirname(__FILE__);
	$plugdir = preg_split(":/:", strrev($plugpath));
	$plugdir = strrev( $plugdir[0]);

	$testink	= $post['testink'] == "testink";
	$t_delete	= $post['do_delete_term'] == 1;
	$t_save		= $post['do_save_term'] == 1;
	$t_rename	= $post['do_rename_term'] == 1;
	$t_add		= $post['do_add_term'] == 1;

	$l_save		= $post['do_save_lang'] ==1;
	$l_delete	= $post['do_delete_lang'] ==1;
	$l_add		= $post['do_add_lang'] ==1;
	$l_sort		= $post['do_resort'] == "up" || 
				  $post['do_resort'] == "down";

	$u_12to13	= $post['do_upgrade_12to13'] == "Run anyway";

	$term = $post['term'];

	// unset action keys so we can pass $post directly to 
	// actions (term actions with translations)
	unset($post['do_save_term']);
	unset($post['term']);
	unset($post['do_add_term']);
	unset($post['dummy']);
	unset($post['term']);

	unset($post['disable_expand']);
	unset($post['disable_confirm']);

	if       ($t_save) {	update_term($term, $post);
	} elseif ($t_delete) {	delete_term($term);
	} elseif ($t_rename) {	rename_term($term, $post['newname']);
	} elseif ($t_add) {		addnew_term($term, $post);
	} elseif ($l_delete) {	delete_language($post['id']);
	} elseif ($l_save) {	update_language($post['id'], $post['newname'], $post['flag']);
	} elseif ($l_add) {		addnew_language($post['newname'], $post['flag']);
	} elseif ($l_sort) {	resort_language($post['id'], $post['do_resort']);
	} elseif ($u_12to13) {	jrv_translate_upgrade12to13();
	} elseif ($testink) {	testink();
	}

	//global $wpdb;
	//$wpdb->print_error();
}

/*
 * TERM ACTIONS
 */

function update_term($term, $translations){
	global $wpdb, $tbllangs, $tbldict;

	foreach ($translations as $lang => $text){

		$LID = get_language_id($lang);

		$wpdb->query( $wpdb->prepare(
			"UPDATE $tbldict SET translation=%s WHERE lang_id=%d AND term=%s", 
			$text, $LID, $term)
		);
		$wpdb->print_error();
	}
}

function delete_term($term){
	global $wpdb, $tbllangs, $tbldict;

	$wpdb->query( $wpdb->prepare(
		"DELETE from $tbldict WHERE term=%s", 
		$term)
	);
}

function rename_term($from, $to){
	global $wpdb, $tbllangs, $tbldict;

	$wpdb->query( $wpdb->prepare(
		"UPDATE $tbldict SET term=%s WHERE term=%s", 
		$to, $from)
	);
}

function addnew_term($term, $translations){
	global $wpdb, $tbllangs, $tbldict;

	/* $translations will be empty if the user is trying to add a term
	 * while there are no languages
	 */
	if ( count($translations) == 0 ){
		say_error('error_add_language_first');
		//debug($translations, "count ZERO!!!");
	}

	foreach ($translations as $lang => $text){

		$LID = get_language_id($lang);

		$text = (empty($text))? "\"\"" : $text;

		$wpdb->query( $wpdb->prepare(
			"INSERT INTO $tbldict (term,lang_id,translation) ".
			"VALUES (%s,%d,%s)", 
			$term, $LID, $text)
		);
	}
}

/*
 * LANGUAGE ACTIONS
 */

/* 
 * re-submitting an existing language makes it default. a language is 
 * re-submitted if is is the first table entry (i.e there is only one row).
 */
function addnew_language($name, $flag){

	global $wpdb, $tbllangs, $tbldict;
	$name = trim(strtolower($name));

	//TODO asian chars.
	if (preg_match("/[^a-z0-9µÞ-öø-ÿÀ-žα-ωа-яё]/",$name) ){
		say_error('error_only_lphnmrc_langs'); 
		return; //TODO add support for russian, greek, 
	}

	if ($name == ""){ 
		say_error('error_no_empty_languages'); 
		return;
	}

	// set default lang
	if (lang_defined($name)) {	
		$wpdb->query("UPDATE $tbllangs set main=0 ");
		$wpdb->query( $wpdb->prepare(
			"UPDATE $tbllangs set main=1 WHERE name=%s",
			$name));

	} else {
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO $tbllangs (name, icon) VALUES (%s,%s)",
			$name,$flag));

		$only1 = (1 == $wpdb->get_var( "SELECT count(*) FROM $tbllangs"));

		if ($only1) { addnew_language($name); } // set default 

		// fill in empty for all terms
		$terms = $wpdb->get_col(
		"SELECT DISTINCT term FROM $tbldict ORDER BY term", 0); 

		$LID = get_language_id($name);

		foreach($terms as $t){
			$wpdb->query( $wpdb->prepare( 
				"INSERT INTO $tbldict (term,lang_id,translation) VALUES (%s,%d,%s)", 
				$t,$LID,"")
			);

		}

	}
}

function delete_language($id){
	global $wpdb, $tbllangs, $tbldict;
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM $tbllangs WHERE id=%d",
		$id
	));
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM $tbldict WHERE lang_id=%d",
		$id
	));

	$nodef = 0 == $wpdb->get_var(
		"SELECT count(*) FROM $tbllangs WHERE main=1");

	if ($nodef) {
			$wpdb->query("UPDATE $tbllangs SET main = '1' LIMIT 1");
	}
}
function update_language( $id, $newname, $flag){
	
	global $wpdb, $tbllangs, $tbldict;

	$oldname = $wpdb->get_var($wpdb->prepare("SELECT name from $tbllangs where id=%d", $id));

	$wpdb->query($wpdb->prepare(
		//"UPDATE $tbllangs set name=%s, `order`=%s, icon=%s where id=%d "),$newname,0,$flag,$id);
		"UPDATE $tbllangs set name=%s, icon=%s where id=%d ",$newname,$flag,$id)
	);


	// update all custom keys too
	$wpdb->query($wpdb->prepare(
		"UPDATE $wpdb->postmeta set meta_key=%s where meta_key=%s",
		$newname."_title",$oldname."_title")
	);

	//$wpdb->show_errors();
	//$wpdb->print_error();
}

function resort_language($id, $direction){

	global $wpdb, $tbllangs, $tbldict;

	$max = $wpdb->get_var("SELECT count(*) FROM $tbllangs");

	$order = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT `order` FROM $tbllangs WHERE id=%d", $id
	));

	if ($direction == "up"){ $order--; } else
	if ($direction == "down"){ $order++; };

	if ($order < 0) {$order = $max; } else
	if ($order > $max) {$order = 0; }

	$wpdb->query( $wpdb->prepare(
		"UPDATE $tbllangs set `order`=%d WHERE id=%d",
		$order,$id 
	));
}




/*
 * SHORTCODES
 */


// [tdict term='predefined term']
function translate_term_shortcde($atts){
	extract( shortcode_atts( array(
		'term' => '',
	), $atts ) );
	return translate_term($term);
}

// [tseveral english=Home greek=Σπίτι german=Zuhause ...] 
function t_several_shortcode( $atts ) {

	global $wpdb, $tbllangs, $tbldict;

	// get defined languages
	$langs = $wpdb->get_col("SELECT name FROM $tbllangs", 0); 
	$deflang = $wpdb->get_var("SELECT name FROM $tbllangs WHERE main = 1");
	$lang = get_session_language();

	// convert langs array into an attributes array with language names 
	// as keys and empty default values
	$defargs = array();
	foreach ( $langs as $val){
		$defargs[$val] = "";
	}

	// assign each key to a local variable and merge the defaults 
	// attributes with ones given in [translate_options ]
	extract( shortcode_atts( $defargs, $atts ) );

	// return the message in default language if none is selected by user
	if ( empty($lang) ) { return "{$$deflang}"; } 

	// return the translated message
	return  "{$$lang}";
}
/*
 * TEMPLATE TAGS
 */


function translate_title() {

    global $wpdb, $tbllangs, $tbldict;
    global $post;

	$lang 		= get_session_language();
	$lang_title = $lang."_title";


    if (!empty($lang)){

		$translated = $wpdb->get_results( $wpdb->prepare(
			"SELECT meta_value, meta_key, post_id FROM ".
			"$wpdb->postmeta WHERE meta_key = %s".
			"AND post_id = %d", 
			$lang_title, $post->ID
		),ARRAY_N);

		if (!empty($translated)){
				//$title = $translated[0];
				$title = $translated[0][0];
				//$title = $title->meta_value;
				echo $title;
			return;
		} 
    } 

    the_title();
}

function translate_term($term){
	global $wpdb, $tbllangs, $tbldict;

	$lang = get_session_language();
	$LID = get_language_id($lang);

	$translation = $wpdb->get_var(
		$wpdb->prepare(
		"SELECT translation FROM $tbldict WHERE lang_id=%s AND term=%s", 
		$LID, $term)
		);

	if (!term_defined( $term)){
		say_error('error_term_undefined');
		return $term; 
	}

	return $translation;
}

// english=fag|american=smoke
function translate_several($params){
	$translations = preg_split("/\|/", $params);

	foreach ($translations as $t){
		$langtrans = preg_split("/\=/",$t);
		$atts[$langtrans[0]] = $langtrans[1];
	}
	return t_several_shortcode($atts);
}


function list_translations($atts) {
	global $wpdb, $tbllangs, $tbldict;
	global $plugdir;

	$show_flags = isset($atts['flags']);

	//TODO asian letters
	$langexpr = '/[\&\?]lang\=[a-z0-9µÞ-öø-ÿÀ-žα-ωа-яё]*/';
	$indexexpr = '/index\.[a-z0-9µÞ-öø-ÿÀ-žα-ωа-яё]*/';

	$currenturl = (!empty($_SERVER['HTTPS'])) ? "https://" : "http://";
	$currenturl .= $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];

    $trans = $wpdb ->get_results("SELECT name,icon FROM $tbllangs ORDER by `order`,name");

	$imgdir = get_settings('home') . "/wp-content/plugins/$plugdir/flags";

    echo '<ul class="languages_list">';

    foreach($trans as $k =>$row) {

		$name = $row->name;
		$flag = $row->icon;

		// todo asian letters
		// guidelines say no use preg_replace_callback and not the /e flag?

		$link = preg_replace( 
			"/(.*[\&\?])lang=[a-z0-9µÞ-öø-ÿÀ-žα-ωа-яё]*(.*)/e", 
			"'\\1'.'lang=$name'.'\\2'", 
			$currenturl);

		if ($link == $currenturl){
			$varsign = (strpos($currenturl, "?"))? "&" : "?";
			$link = $currenturl.$varsign."lang=$name";
		}

		$prpr = ucfirst(strtolower($name));
		
	$translist .= "\n\t\t"
		."<li class=\"list_item_$name\">\n\t\t  <a href=\"".$link."\">"
		. ($show_flags? "<img \n\t\t    src=\"$imgdir/$flag\" alt=\"$prpr\" />" : $prpr)
		."</a></li>";
    }
	
	echo $translist;
	echo "\n\t\t</ul>\n";
}


function translate_list_pages() {

    global $wpdb, $tbllangs, $tbldict;
    global $table_prefix;

	$lang_title = get_session_language() . "_title";

	$pages = $wpdb->get_results( $wpdb->prepare(
		"SELECT ID, menu_order, post_type, post_status, post_id, meta_key, meta_value 
		 FROM $wpdb->posts, $wpdb->postmeta 
		 WHERE post_status = 'publish' AND 
			post_type = 'page' AND 
			ID = post_id AND 
			meta_key = %s 
		ORDER BY menu_order ASC", $lang_title
	), ARRAY_A);

    echo "\n\t<ul>\n";

	if (count($pages)> 0){

		foreach($pages as $page) {
			$tt 	= $page['meta_value']; // translated title
			$id 	= $page['ID'];
			$link	= get_page_link($id);

			echo "\t  <li class=\"page_item page-item-$id\">"
				."<a href=\"$link\" title=\"$tt\">$tt</a></li>\n";
		}
	}
	echo "\t</ul>\n";
}

function translate_link($args) {

    $template = get_bloginfo('template_directory');
    $home = get_bloginfo('home');
    $args = preg_replace("/TEMPLATEPATH/", $template, $args);
    $args = preg_replace("/HOME/", $home, $args);
    $atts = explode('|', $args);
    $params[] = '';
    foreach($atts as $att)
    {
        $att = trim($att);
        $param = explode('=', $att);
        $params[$param[0]] = $param[1];
    }
    $lang = $params['lang'];
    $langcheck = lang_defined($lang);
    if($langcheck):
        if($lang == $_SESSION['language']):
        $text = $params['text'];
        $link = $params['link'];
        $target = $params['target'];
        if($target):$target = " target='".$target."'"; else: $target=null; endif; 
        $class = $params['class'];
        if (!$class): $class = " class='".$lang."_link' "; else: $class = " class='".$params['class']."' "; endif;
            echo '<a'.$class.'href="'.$link.'"'.$target.'>'.$text.'</a>';
        endif;
    endif;
    //link, text, class, lang, target  %language%_link  
}

function translate_text($args) {

    $template = get_bloginfo('template_directory');
    $home = get_bloginfo('home');
    $args = preg_replace("/TEMPLATEPATH/", $template, $args);
    $args = preg_replace("/HOME/", $home, $args);
    $atts = explode('|', $args);
    $params[] = '';
    foreach($atts as $att)
    {
        $att = trim($att);
        $param = explode('=', $att);
        $params[$param[0]] = $param[1];
    }
    $lang = $params['lang'];
    $langcheck = lang_defined($lang);
    if($langcheck):
        if($lang == $_SESSION['language']):
            $text = $params['text'];
            $class = $params['class'];
            $p = $params['p'];
            if (!$class): $class = " class='".$lang."_text'"; else: $class = " class='".$params['class']."'"; endif;
            if($p == null || $p == "yes"):
            if($text):
                echo "<p".$class.">".$text."</p>";
            endif;
            endif;
            if ($p == "no"):
            if($text):
                echo $text;
            endif;
            endif;
        endif;
    endif;
    //text, class, lang %language%_link
}


function translate_image($args) {

    $template = get_bloginfo('template_directory');
    $home = get_bloginfo('home');
    $args = preg_replace("/TEMPLATEPATH/", $template, $args);
    $args = preg_replace("/HOME/", $home, $args);
    $atts = explode('|', $args);
    $params[] = '';
    foreach($atts as $att)
    {
        $att = trim($att);
        $param = explode('=', $att);
        $params[$param[0]] = $param[1];
    }
    $lang = $params['lang'];
    $langcheck = lang_defined($lang);
    if($langcheck):
    if($lang == $_SESSION['language']):
        $link = $params['link'];
        $target = $params['target'];
        $src = $params['src'];
        $alt = $params['alt'];
        $title = $params['title'];
        $class = $params['class'];
        if (!$class): $class = " class='".$lang."_image' "; else: $class = " class='".$params['class']."' "; endif;
        if(!$target):$target=null;endif;
        if($title):$title = " title='".$title."'";endif;
        if($alt): $alt = " alt='".$alt."'";endif;
        if($target):$target = " target='".$target."'";endif; 
        if($lang == $_SESSION['language']):
            if($link): echo "<a href='".$link."'".$target.">"; endif;
            if($src): echo "<img ".$class."src='".$src."'".$alt.$title.">"; endif;
            if($link): echo "</a>"; endif;
        endif;
    endif;
    endif;
    //tags - link, target, src, alt, title, lang, class  %language%_image
}


/*
 * HELPER FUNCTIONS
 */

function set_session_language() { 
    
    global $wpdb, $tbllangs, $tbldict;

	// unset if we deleted them all
	if (0 == $wpdb->get_var("SELECT count(*) from $tbllangs")){
        unset($_SESSION['language']);
	}

	// try itself, cookie, default.
	$_SESSION['language'] = get_session_language();

	
	// or set session languageuage to the lang in url 
	// (user is changing language)
	if (lang_defined($_GET['lang'])){
		$_SESSION['language'] = $_GET['lang'];
	}

	$c_name 	= "minpro_language";
	$c_value	= $_SESSION['language'];
	$c_expire	= time() + 31536000; // a year: 3600 * 24 * 365
	$c_path		= get_bloginfo('home');
	$c_path		= preg_replace("#http://.*/#","/", get_bloginfo('home'));

	// COOKIEPATH, COOKIE_DOMAIN) 
	setcookie($c_name,$c_value,$c_expire, $c_path);
}

function get_language_id($lang){
	global $wpdb, $tbllangs, $tbldict;

	$LID = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM $tbllangs WHERE name=%s", $lang
	)); 

	if ($LID >= 1){
		return $LID;
	}
	return -1;
}

// session, cookie, default, empty string
function get_session_language(){
	global $wpdb, $tbllangs, $tbldict;

	$lang = $_SESSION['language'];

	if (!lang_defined($lang)){
		$lang = $_COOKIE['minpro_language'];
	}

	if (!lang_defined($lang)){
		$lang = $wpdb->get_var("SELECT name FROM $tbllangs WHERE main=1");
	}

	if (!lang_defined($lang)){
		return NULL;
	}

	return $lang;

}


function lang_defined($lang) {
    global $wpdb, $tbllangs, $tbldict;
	$langs = $wpdb->get_col("SELECT name FROM $tbllangs",0);
	return in_array($lang, $langs);
}
function term_defined($term){

	global $wpdb, $tbllangs, $tbldict;
	$terms = $wpdb->get_col("SELECT distinct term FROM $tbldict",0);
	return in_array($term, $terms);
}


//function url_check() {
//
//    $currenturl = (!empty($_SERVER['HTTPS'])) ? "https://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'] : "http://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
//    $currenturl = preg_replace('/[\&\?]lang\=[a-z]*/', '', $currenturl);
//    $currenturl = preg_replace('/index\.[a-z]*/', '', $currenturl);
//    if(strpos($currenturl, '?')):
//    return true;
//    else: return false;
//    endif;
//}

function say_error($message){
	global $WPTRPLG_ERRATA;
	$message = $WPTRPLG_ERRATA[$message];
	echo "<b class=\"translate_error\"> $message</b>";
}

function jrv_grep_version($filepath) {

	$lines = file($filepath);
	$lc = count($lines);

	for ( $i=0; $i<$lc; $i++ ) {

		$l = $lines[$i];

		if (preg_match("/^[\ \t\*]*Version:/",$l) ){

			$version = preg_split('/:/', $l );

			$i = $lc;
			return trim($version[1]);
		}
	}
}

function get_flag_options_html($selected){

	$path = preg_split(":".DIRECTORY_SEPARATOR.":", __FILE__);
	$dir = dirname(__FILE__);
	$dir .= "/flags";

	$html = "\t  <select name=\"flag\" class=\"flag_icon\">\n";
	
	if (is_dir($dir)) {
		if ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false) {
				$files[] = $file;
			}

			unset($files[ array_search('.', $files) ]); 
			unset($files[ array_search('..', $files) ]); 

			sort($files);
			$filez[] = "default.gif";
			foreach ($files as $file){
				$filez[] = $file;
			}
			$files = $filez;

			foreach ($files as $file){
				$current = $file == $selected;
				$option = substr($file, 0, 14);
				$option = $file;

				$html .= "\t    <option class=\"flag_icon\" ".($current? "selected=\"selected\"": "")." value=\"$option\">$option</option>\n";
				//echo "filename: $file : filetype: " . filetype($dir . $file) . "\n";
			}

			closedir($dh);
		}
	}
	$html .= "\t  </select>\n";
	return $html;

}

function debug($moo, $title){

	echo '<pre style="font-size:9px;font-family:courier new;">';
	echo '<b style="color:red;">'.$title.'</b><br/>';
	echo 'type: '. gettype($moo);
	echo "\n";
	print_r($moo);
	echo "</pre>";
}

function getBrowser() {
    $u_agent = $_SERVER['HTTP_USER_AGENT'];
    $bname = 'Unknown';
    $platform = 'Unknown';
    $version= "";

    //First get the platform?
    if (preg_match('/linux/i', $u_agent)) {
        $platform = 'linux';
    }
    elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
        $platform = 'mac';
    }
    elseif (preg_match('/windows|win32/i', $u_agent)) {
        $platform = 'windows';
    }
   
    // Next get the name of the useragent yes seperately and for good reason
    if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent))
    {
        $bname = 'Internet Explorer';
        $ub = "MSIE";
    }
    elseif(preg_match('/Firefox/i',$u_agent))
    {
        $bname = 'Mozilla Firefox';
        $ub = "Firefox";
    }
    elseif(preg_match('/Chrome/i',$u_agent))
    {
        $bname = 'Google Chrome';
        $ub = "Chrome";
    }
    elseif(preg_match('/Safari/i',$u_agent))
    {
        $bname = 'Apple Safari';
        $ub = "Safari";
    }
    elseif(preg_match('/Opera/i',$u_agent))
    {
        $bname = 'Opera';
        $ub = "Opera";
    }
    elseif(preg_match('/Netscape/i',$u_agent))
    {
        $bname = 'Netscape';
        $ub = "Netscape";
    }
   
    // finally get the correct version number
    $known = array('Version', $ub, 'other');
    $pattern = '#(?<browser>' . join('|', $known) .
    ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
    if (!preg_match_all($pattern, $u_agent, $matches)) {
        // we have no matching number just continue
    }
   
    // see how many we have
    $i = count($matches['browser']);
    if ($i != 1) {
        //we will have two since we are not using 'other' argument yet
        //see if version is before or after the name
        if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
            $version= $matches['version'][0];
        }
        else {
            $version= $matches['version'][1];
        }
    }
    else {
        $version= $matches['version'][0];
    }
   
    // check if we have a number
    if ($version==null || $version=="") {$version="?";}
   
    return array(
        'userAgent' => $u_agent,
        'name'      => $bname,
        'version'   => $version,
        'platform'  => $platform,
        'pattern'    => $pattern
    );
} 

?>
