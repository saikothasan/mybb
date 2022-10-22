<?php

/**
 * Copyright Isaiah 2015
 * Random Passwords Generator
 */

if (!defined("IN_MYBB")) {
	die( "Please be within mybb to use this plugin." );
}

// Cache templates
if (defined('THIS_SCRIPT')) {
	global $templatelist;
	if (isset($templatelist)) {
		$templatelist .= ', ';
	}
	if (THIS_SCRIPT == 'misc.php') {
		$template_prefix = 'ircher_password_generator_template_';
		$templates_to_cache = array($template_prefix.'gen', $template_prefix.'rng');
		$templatelist .= implode(", ", $templates_to_cache);
	}
}

// Main hook
$plugins->add_hook( "misc_start", "password_generator_page" );

function password_generator_info() {
	global $lang;
	$lang->load( 'ircher_password_generator_a' );
	return array(
		'name' => $lang->ircher_password_generator_plugin_name,
		'description' => $lang->ircher_password_generator_plugin_desc,
		'author' => 'Isaiah',
		'authorsite' => 'http://localhost',
		'website' => 'http://community.mybb.com/',
		'version' => '1.1',
		'guid' => '',
		'codename' => 'ircher_password_generator',
		'compatibility' => '18*'
	);
}

function password_generator_install() {
	global $db, $lang;
	$lang->load( 'ircher_password_generator_a' );
	// Create the settings group
	$generator_settings_group = array(
		'name' => 'ircher_password_generator',
		'title' => $lang->ircher_password_generator_settings_name,
		'description' => $lang->ircher_password_generator_settings_desc,
		'disporder' => 10,
		'isdefault' => 0
	);
	// Insert the setting group into the database
	$db->insert_query( 'settinggroups', $generator_settings_group );
	// Get the gid from the above insert query
	$gid = $db->insert_id();
	// Create the setting(s)
	$generator_setting_one = array(
		'name' => 'ircher_password_generator_min',
		'title' => $lang->ircher_password_generator_setting_min_name,
		'description' => $lang->ircher_password_generator_setting_min_desc,
		'optionscode' => 'numeric',
		'value' => 10,
		'disporder' => 1,
		'gid' => intval($gid)
	);
	$db->insert_query( 'settings', $generator_setting_one );
	$generator_setting_two = array(
		'name' => 'ircher_password_generator_max',
		'title' => $lang->ircher_password_generator_setting_max_name,
		'description' => $lang->ircher_password_generator_setting_max_desc,
		'optionscode' => 'numeric',
		'value' => 20,
		'disporder' => 2,
		'gid' => intval($gid)
	);
	$db->insert_query( 'settings', $generator_setting_two );
	// Complexity optionscode, split on multiple lines here for simplicity.
	$generator_complexity_options = "radio
0=Alphabetical
1=Alphanumeric
2=Alphabetical + Symbol
3=Alphanumeric + Symbol";
	// Third Setting
	$generator_setting_three = array(
		'name' => 'ircher_password_generator_complex',
		'title' => $lang->ircher_password_generator_setting_complex_name,
		'description' => $lang->ircher_password_generator_setting_complex_desc,
		'optionscode' => $generator_complexity_options,
		'value' => 1,
		'disporder' => 3,
		'gid' => intval($gid)
	);
	$db->insert_query( 'settings', $generator_setting_three );
	// Don't forget to rebuild the settings cache.
	rebuild_settings();
}

function password_generator_is_installed() {
	global $mybb;
	if(isset($mybb->settings['ircher_password_generator_complex'])) {
		return true;
	}
	else {
		return false;
	}
}

function password_generator_uninstall() {
	global $db;
	// Drop the added settings
	$db->delete_query( 'settinggroups', "name = 'ircher_password_generator'" );
	$db->delete_query( 'settings' , "name LIKE 'ircher\_password\_generator%'" );
	// Don't forget to rebuild settings.
	rebuild_settings();
}

function password_generator_activate() {
	// Yay, first template changes!
	global $mybb,$db;
	// Template Variable
	$template_gen = '
<html>
<head>
	<title>{$mybb->settings[\'bbname\']}</title>
	{$headerinclude}
</head>
<body>
	{$header}
	<div>
		<p>Click the following button to generate a password:</p>
		<p><form action="misc.php?action=password_generator_do_gen" method="POST"><input type="submit" value="Generate Password" /></form></p>
		<p>Generated Password:</p>
		<p><b>{$generated_password}</b></p>
		<p>Link to basic number generator: <a href="misc.php?action=password_generator_view_rng">here</a></p>
	</div>
	{$footer}
</body>
</html>';
	$template_rng = '
<html>
<head>
	<title>{$mybb->settings[\'bbname\']}</title>
	{$headerinclude}
</head>	
<body>
	{$header}
	<div>
		<p>If you wish for a random number, use the following form:</p>
		<p><form action="misc.php?action=password_generator_do_rng" method="POST"><label>Amount to Generate: <input type="numeric" value="1" id="dice" name="dice" />&nbsp;<label>Minimum Value: <input type="numeric" value="1" id="minimum" name="minimum" /></label>&nbsp;&nbsp;<label>Maximum Value: <input type="numeric" value="6" id="maximum" name="maximum" /></label>&nbsp;<input type="submit" value="Obtain random number." /></form></p>
		<p>Obtained Random Number: <b>{$generated_number}</b></p>
		<p>Code: <pre><code>{$generated_code}</code></pre></p>
		<p>Link to password generator: <a href="misc.php?action=password_generator_view_gen">here</a></p>
	</div>
	{$footer}
</body>
</html>';

	// Template insert array
	$template_insert_arrays = array(array(
		'title' => 'ircher_password_generator_template_gen',
		'template' => $db->escape_string($template_gen),
		'sid' => '-1',
		'version' => '1.1',
		'dateline' => time()
	), array(
		'title' => 'ircher_password_generator_template_rng',
		'template' => $db->escape_string($template_rng),
		'sid' => '-1',
		'version' => '1.0',
		'dateline' => time()
	));
	foreach ($template_insert_arrays as $template_insert_array) {
		// Insert into database
		$db->insert_query('templates', $template_insert_array );
	}
	$generator_link = '<li><a href="misc.php?action=password_generator_view_gen" style="background-image: none;">Password Generator</a></li>';

	// Note: Commenting out til I find the fix -- Messing up my forum
	// Now we have to alter one of the original templates.
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets(
		// Where to search
		'header',
		// Regular Expression
		'#'.preg_quote('{$menu_search}').'#',
		// Replacement Text
		'{$menu_search}'.$generator_link
	);
}

function password_generator_deactivate() {
	// Delete the template I created
	global $db;
	$db->delete_query("templates", "title = 'ircher_password_generator_template_gen'" );
	$db->delete_query("templates", "title = 'ircher_password_generator_template_rng'" );

	$generator_link = '<li><a href="misc.php?action=password_generator_view_gen" style="background-image: none;">Password Generator</a></li>';
	// Note: Commenting out until I fix this in the activate() function.
	// Remove template edit
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('header', '#'.preg_quote($generator_link).'#', '');
}

// The function for displaying the page.
function password_generator_page() {
	// Start globals
	global $mybb, $templates, $lang, $header, $headerinclude, $footer, $password_generator;
	// We're adding a page to the usercp
	if ($mybb->get_input('action', MyBB::INPUT_STRING) == 'password_generator_view_gen') {
		add_breadcrumb( 'Password Generator', "misc.php?action=password_generator_view");
		$generated_password = '';
		eval('$sections = "'.$templates->get('ircher_password_generator_template_gen').'";');
		// Using the misc_help template wrapper
		eval("\$page = \"".$templates->get('ircher_password_generator_template_gen')."\";");
	}
	else if ($mybb->get_input('action', MyBB::INPUT_STRING) == 'password_generator_do_gen') {
		add_breadcrumb( 'Password Generator', "misc.php?action=password_generator_do_gen");
		// Generate the password
		$generated_password = password_generator_generate_password(
			$mybb->settings['ircher_password_generator_min'],
			$mybb->settings['ircher_password_generator_max'],
			$mybb->settings['ircher_password_generator_complex']
		);
		eval('$sections = "'.$templates->get('ircher_password_generator_template_gen').'";');
		// Using the misc_help template wrapper
		eval("\$page = \"".$templates->get('ircher_password_generator_template_gen')."\";");
	}
	else if ($mybb->get_input('action', MyBB::INPUT_STRING) == 'password_generator_view_rng') {
		add_breadcrumb( 'Number Generator', "misc.php?action=password_generator_view_rng");
		$generated_number = '';
		eval('$sections = "'.$templates->get('ircher_password_generator_template_rng').'";');
		// Using the misc_help template wrapper
		eval("\$page = \"".$templates->get('ircher_password_generator_template_rng')."\";");
	}
	else if ($mybb->get_input('action', MyBB::INPUT_STRING) == 'password_generator_do_rng') {
		add_breadcrumb( 'Number Generator', "misc.php?action=password_generator_do_rng");
		// Generate random number
		$dice = $mybb->get_input('dice', MyBB::INPUT_INT) or 0;
		$min = $mybb->get_input('minimum', MyBB::INPUT_INT) or -1;
		$max = $mybb->get_input('maximum', MyBB::INPUT_INT) or -1;
		if ($dice <= 0 || $min < 0 || $max <= 0 || ($min > $max)) {
			$errors = array();
			if ($dice <= 0) {
				array_push( $errors, '# to generate cannot be <= 0' );
			}
			if ($min < 0) {
				array_push( $errors, 'Minimum cannot be < 0' );
			}
			if ($max <= 0) {
				array_push( $errors, 'Maximum cannot be < 1' );
			}
			if ($min > $max) {
				array_push( $errors, 'Minimum cannot be > maximum.' );
			}
			$generated_number = inline_error( $errors, 'Number Generator - Error<br />' );
			$generated_number .= 'An error occurred! Provided Values: '.$dice.'d'.$min.'-'.$max;
		}
		else {
			$rolls = array();
			for ($i = 0; $i < $dice; ++$i) {
				$rolls[$i] = mt_rand( $min, $max );
				$total += $rolls[$i];
			}
			$generated_number = '['.implode( ",", $rolls ).'] ==> '.$total;
			$generated_code = $generated_number."\nTimestamp: ".time();
		}
		eval('$sections = "'.$templates->get('ircher_password_generator_template_rng').'";');
		// Using the misc_help template wrapper
		eval("\$page = \"".$templates->get('ircher_password_generator_template_rng')."\";");
	}
	else {
		// We don't want to run with the wrong action
		return;
	}
	// Output the page.
	output_page($page);
}

function password_generator_generate_password( $min, $max, $complexity ) {
	$errors = array();
	if ($min > $max) {
		// Fail silently
		array_push( $errors, 'Maximum length is lower than minimum length.' );
	}
	else if ($max > 40) {
		array_push( $errors, 'Maximum length is too long. It is recommended to keep passwords to a max size of 40 characters for easy use.' );
	}
	else if ($min < 5) {
		array_push( $errors, 'Minimum length is too short. It is recommended to keep passwords to a minimum size of 5 characters for security purposes.' );
	}
	if ($errors) {
		// Error occurred; fail and return error
		return inline_error( $errors."\nPlease contact your administrator.", 'Password Generator Failed!' );
	}

	// Full character list to use.
	$character_list = '';
	// Subsets of the list to possibly use.
	$lowercase_list = 'abcdefghijklmnopqrstuvwxyz';
	$uppercase_list = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$number_list = '1234567890';
	$symbol_list = '!@#$%^&*()-_=+[]{}<>,.?;:';
	// Extra lists of characters to use.
	$extra_one = '';
	$extra_two = '';
	$extra_three = '';
	$extra_four = '';
	// Password to return
	$password = '';
	// Etc.
	$extra_num = 0;
	if ($min < 10) {
		$extra_num = 0;
	}
	else if ($min < 15) {
		$extra_num = 1;
	}
	else if ($min < 20) {
		$extra_num = 2;
	}
	else if ($min < 25) {
		$extra_num = 3;
	}
	else if ($min < 30) {
		$extra_num = 4;
	}
	else if ($min < 35) {
		$extra_num = 5;
	}
	else {
		$extra_num = 6;
	}
	// $complexity - 0 = Alpha, 1 = Alphanumeric, 2 = AlphaSymbol, 3 = All
	switch ($complexity) {
		case 0:
			$character_list = $lowercase_list.$uppercase_list;
			$extra_one = str_repeat(password_generator_generate_subset( $lowercase_list ), 3);
			$extra_two = str_repeat(password_generator_generate_subset( $uppercase_list ), 3);
			$extra_three = str_repeat(password_generator_generate_subset( $lowercase_list ), 3);
			$extra_four = str_repeat(password_generator_generate_subset( $uppercase_list ), 3);
			break;
		case 1:
			$character_list = $lowercase_list.$uppercase_list.$number_list;
			$extra_one = str_repeat(password_generator_generate_subset( $lowercase_list ), 3);
			$extra_two = str_repeat(password_generator_generate_subset( $uppercase_list ), 3);
			$extra_three = str_repeat(password_generator_generate_subset( $number_list ), 3);
			$extra_four = password_generator_generate_subset( $lowercase_list.$uppercase_list, 15 );
			break;
		case 2:
			$character_list = $lowercase_list.$uppercase_list.$symbol_list;
			$extra_one = str_repeat(password_generator_generate_subset( $lowercase_list ), 3);
			$extra_two = str_repeat(password_generator_generate_subset( $uppercase_list ), 3);
			$extra_three = str_repeat(password_generator_generate_subset( $symbol_list ), 3);
			$extra_four = password_generator_generate_subset( $lowercase_list.$uppercase_list, 15 );
			break;
		case 3:
			$character_list = $lowercase_list.$uppercase_list.$number_list;
			$extra_one = str_repeat(password_generator_generate_subset( $lowercase_list ), 3);
			$extra_two = str_repeat(password_generator_generate_subset( $uppercase_list ), 3);
			$extra_three = str_repeat(password_generator_generate_subset( $number_list ), 3);
			$extra_four = str_repeat(password_generator_generate_subset( $symbol_list ), 3);
			break;
	}
	// Shuffle and repeat main list
	$character_list = str_shuffle( $character_list );
	$character_list = str_repeat( $character_list, 2 );
	// Generate a password
	$password = password_generator_generate_subset($character_list, strlen( $character_list ) / 2);
	$password .= password_generator_generate_subset($extra_one, $extra_num);
	$password .= password_generator_generate_subset($extra_two, $extra_num);
	$password .= password_generator_generate_subset($extra_three, $extra_num);
	$password .= password_generator_generate_subset($extra_four, $extra_num);
	// Get final password
	$password = password_generator_generate_subset($password, mt_rand($min, $max));
	return $password;
}

function password_generator_generate_subset( $list, $len = 5 ) {
	if (!is_string( $list ) || !is_int( $len )) {
		error( 'An unknown error occurred!' );
	}
	if (strlen( $list <= 10 )) {
		$list = str_repeat( $list, 2 );
	}
	$return_value = '';
	$temp_arr = str_split( $list );
	shuffle( $temp_arr );
	$temp_arr2 = array_rand( $temp_arr, $len );
	foreach ($temp_arr2 as $key) {
		$return_value .= $temp_arr[$key];
	}
	return str_shuffle( $return_value );
}
?>