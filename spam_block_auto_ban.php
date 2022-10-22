<?php
/****************************************************************************
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

if (!defined('IN_MYBB')){
	die('This file cannot be accessed directly.');
}

$plugins->add_hook('newthread_do_newthread_start', 'spam_block_auto_ban_trigger');
$plugins->add_hook('newreply_do_newreply_start', 'spam_block_auto_ban_trigger');
$plugins->add_hook('editpost_do_editpost_start', 'spam_block_auto_ban_trigger');

function spam_block_auto_ban_info(){
	return Array(
		"name" => "SPAM blocker & auto ban",
		"description" => "It automaticaly ban/block posting users who write specific SPAM words on threads or posts",
		"website" => "https://amxmodx-es.com",
		"author" => "Neeeeeeeeeel.-",
		"authorsite" => "https://amxmodx-es.com",
		"version" => "v1.3",
		"guid" => "",
		"compatibility" => "18*"
	);
}

function spam_block_auto_ban_install(){
	global $db;
	$ap_group = Array(
		'name'			=> "spam_block_auto_ban",
		'title'			=> "SPAM blocker & auto ban",
		'description'	=> "",
		'disporder'		=> 1,
		'isdefault'		=> 1
	);
	
	$gid = $db->insert_query("settinggroups", $ap_group);
	
	$spam_block_auto_ban_settings = Array();
	$spam_block_auto_ban_settings[] = Array(
		'name'			=> "spam_block_auto_ban_enabled",
		'title'			=> "Enabled?",
		'description'	=> "Enable/disable SPAM blocker & auto ban plugin.",
		'optionscode'	=> "yesno",
		'value'			=> "1",
		'disporder'		=> 1,
		'gid'			=> $gid
	);
	
	$spam_block_auto_ban_settings[] = Array(
		'name'			=> "spam_block_auto_ban_words",
		'title'			=> "Ban words/phrases",
		'description'	=> "Here you setup the words/phrases you would like to check for an instant ban separated by comma \",\"",
		'optionscode'	=> "text",
		'value'			=> "",
		'disporder'		=> 2,
		'gid'			=> $gid
	);
	
	$spam_block_auto_ban_settings[] = Array(
		'name'			=> "spam_block_auto_ban_minposts",
		'title'			=> "Max posts",
		'description'	=> "Checks will only be applied for users with less than this amount of posts. This settings is to reduce false-positives. 99% of spammers are new members",
		'optionscode'	=> "numeric",
		'value'			=> 10,
		'disporder'		=> 3,
		'gid'			=> $gid
	);
	
	$spam_block_auto_ban_settings[] = Array(
		'name'			=> "spam_block_auto_ban_banreason",
		'title'			=> "Ban reason",
		'description'	=> "The ban reason when a ban is triggered by the plugin",
		'optionscode'	=> "text",
		'value'			=> "SPAM",
		'disporder'		=> 4,
		'gid'			=> $gid
	);
	
	$spam_block_auto_ban_settings[] = Array(
		'name'			=> "spam_block_auto_ban_action",
		'title'			=> "Action",
		'description'	=> "You can choose between blocking post/thread and/or banning the user",
		'optionscode'	=> "select\n0=Block post & ban\n1=Block post\n2=Ban only",
		'value'			=> "0",
		'disporder'		=> 5,
		'gid'			=> $gid
	);
	
	$spam_block_auto_ban_settings[] = Array(
		'name'			=> "spam_block_auto_ban_ajaxerror",
		'title'			=> "Ajax error",
		'description'	=> "Here you can write a custom error message the user will get after being blocked replying a thread",
		'optionscode'	=> "text",
		'value'			=> "Your message has been flagged as SPAM",
		'disporder'		=> 6,
		'gid'			=> $gid
	);
	
	foreach ($spam_block_auto_ban_settings as $setting){
		$db->insert_query("settings", $setting);
	}
	rebuild_settings();

	$template = '{$lang->error_nopermission_user_1}
<ol>
	<li>{$lang->error_nopermission_user_2}</li>
	<li>{$lang->error_nopermission_user_3}</li>
	<li>{$lang->error_nopermission_user_4} (<a href="member.php?action=resendactivation">{$lang->error_nopermission_user_resendactivation}</a>)</li>
	<li>{$lang->error_nopermission_user_5}</li>
</ol>
<br />
{$lang->error_nopermission_user_username}';

	$insert_array = array(
		'title' => 'spam_block_auto_ban_error_page',
		'template' => $db->escape_string($template),
		'sid' => '-1',
		'version' => '',
		'dateline' => time()
	);

	$db->insert_query('templates', $insert_array);
}

function spam_block_auto_ban_uninstall(){
	global $db;
	$db->delete_query("settings", "name IN ('spam_block_auto_ban_words','spam_block_auto_ban_enabled','spam_block_auto_ban_minposts','spam_block_auto_ban_banreason','spam_block_auto_ban_action','spam_block_auto_ban_ajaxerror')");
	$db->delete_query("settinggroups", "name='spam_block_auto_ban'");
	$db->delete_query("templates", "title = 'spam_block_auto_ban_error_page'");
	
	rebuild_settings();
}

function spam_block_auto_ban_is_installed(){
	global $db;
	$query = $db->simple_select('settinggroups', '*', "name='spam_block_auto_ban'");
	if ($db->num_rows($query)){
		return true;
	}
	return false;
}

function spam_block_auto_ban_trigger(){
	global $mybb;
	
	if ($mybb->settings['spam_block_auto_ban_enabled'] == 0){
		return false;
	}
	
	if ($mybb->user['postnum'] < $mybb->settings['spam_block_auto_ban_minposts']){
		$message = $mybb->get_input('message');
		$subject = $mybb->get_input('subject');
		
		$phrases = explode(',',$mybb->settings['spam_block_auto_ban_words']);
		foreach ($phrases as $phrase){
			$phrase = trim($phrase);
			if ($phrase == ''){
				continue;
			}
			if (stristr($message, $phrase) || stristr($subject, $phrase)){
				if ($mybb->settings['spam_block_auto_ban_action'] == 0 || $mybb->settings['spam_block_auto_ban_action'] == 2){
					ban_user($mybb->user['uid']);
				}
				if ($mybb->settings['spam_block_auto_ban_action'] == 0 || $mybb->settings['spam_block_auto_ban_action'] == 1){
					error_post_blocked();
				}
			}
		}
	}
}

function error_post_blocked(){
	global $mybb, $theme, $templates, $db, $lang, $plugins, $session;
	if ($mybb->get_input('ajax', MyBB::INPUT_INT)){
		header("Content-type: application/json; charset={$lang->settings['charset']}");
		echo json_encode(array("errors" => array($mybb->settings['spam_block_auto_ban_ajaxerror'])));
		exit;
	}
	$lang->error_nopermission_user_username = $lang->sprintf($lang->error_nopermission_user_username, htmlspecialchars_uni($mybb->user['username']));
	eval("\$errorpage = \"".$templates->get("spam_block_auto_ban_error_page")."\";");
	error($errorpage);
}

function ban_user($uid){
	global $db,$cache,$mybb;
	$query = $db->simple_select("users", "uid, usergroup, additionalgroups, displaygroup, username", "uid='{$uid}'", array('limit' => 1));
	$user = $db->fetch_array($query);

	if (!$user['uid']){
		return false;
	} else if (is_super_admin($user['uid'])){
		return false;
	} else {
		$query = $db->simple_select("banned", "uid", "uid='{$user['uid']}'");
		if ($db->fetch_field($query, "uid")){
			return false;
		}
	}
	
	$lifted = 0;
	$bantime = '---';
	$reason = $mybb->settings['spam_block_auto_ban_banreason'];
	
	$query = $db->simple_select("usergroups", "gid", "isbannedgroup=1", array('order_by' => 'title', 'limit'=>1));
	$group = $db->fetch_array($query);
	$db->free_result($query);
	
	$group = $group['gid'];
	
	$insert_array = array(
		'uid' => $user['uid'],
		'gid' => intval($group),
		'oldgroup' => $user['usergroup'],
		'oldadditionalgroups' => $user['additionalgroups'],
		'olddisplaygroup' => $user['displaygroup'],
		'admin' => intval($mybb->user['uid']),
		'dateline' => TIME_NOW,
		'bantime' => $bantime,
		'lifted' => $lifted,
		'reason' => $db->escape_string($reason)
	);
	$db->insert_query('banned', $insert_array);
	
	// Move the user to the banned group
	$update_array = array(
		'usergroup' => intval($group),
		'displaygroup' => 0,
		'additionalgroups' => '',
	);
	$db->update_query('users', $update_array, "uid = '{$user['uid']}'");
	$db->delete_query("forumsubscriptions", "uid = '{$user['uid']}'");
	$db->delete_query("threadsubscriptions", "uid = '{$user['uid']}'");
	
	$cache->update_banned();
	return true;
} 
