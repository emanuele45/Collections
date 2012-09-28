<?php 
/**
 * Collections
 *
 * @package Collections
 * @author emanuele
 * @copyright 2012 emanuele
 * @license BSD
 *
 * @version 0.1.0
 */

// If we have found SSI.php and we are outside of SMF, then we are running standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF')) // If we are outside SMF and can't find SSI.php, then throw an error
	die('<b>Error:</b> Cannot install - please verify you put this file in the same place as SMF\'s SSI.php.');
  
global $hooks, $mod_name;
$hooks = array(
	'integrate_pre_include' => '$sourcedir/Subs-Collections.php',
	'integrate_admin_areas' => 'collections_add_admin_menu',
	'integrate_actions' => 'collections_add_action',
);
$mod_name = 'Collections';

// ---------------------------------------------------------------------------------------------------------------------
define('SMF_INTEGRATION_SETTINGS', serialize(array(
	'integrate_menu_buttons' => 'install_menu_button',)));

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	exit('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

if (SMF == 'SSI')
{
	// Let's start the main job
	install_mod();
	// and then let's throw out the template! :P
	obExit(null, null, true);
}
else
{
	setup_hooks();
}

function install_mod ()
{
	global $context, $mod_name;

	$context['mod_name'] = $mod_name;
	$context['sub_template'] = 'install_script';
	$context['page_title_html_safe'] = 'Install script of the mod: ' . $mod_name;
	if (isset($_GET['action']))
		$context['uninstalling'] = $_GET['action'] == 'uninstall' ? true : false;
	$context['html_headers'] .= '
	<style type="text/css">
    .buttonlist ul {
      margin:0 auto;
			display:table;
		}
	</style>';

	// Sorry, only logged in admins...
	isAllowedTo('admin_forum');

	if (isset($context['uninstalling']))
		setup_hooks();
}

function setup_hooks ()
{
	global $context, $hooks, $smcFunc;

	$integration_function = empty($context['uninstalling']) ? 'add_integration_function' : 'remove_integration_function';
	foreach ($hooks as $hook => $function)
		$integration_function($hook, $function);

	if (empty($context['uninstalling']))
	{
		db_extend('Packages');
		$smcFunc['db_create_table'](
			'{db_prefix}collections_list',
			array(
				array(
					'name' => 'id_list',
					'type' => 'smallint',
					'auto' => true,
				),
				array(
					'name' => 'name',
					'type' => 'varchar',
					'size' => 255
				),
				array(
					'name' => 'description',
					'type' => 'text'
				),
				array(
					'name' => 'page',
					'type' => 'smallint',
					'default' => 0
				),
				// @TODO not yet implemented
				array(
					'name' => 'owner',
					'type' => 'mediumint',
					'default' => 0
				),
				array(
					'name' => 'position',
					'type' => 'smallint',
					'default' => 0
				),
				// @TODO not yet implemented
				array(
					'name' => 'options',
					'type' => 'text'
				),
			),
			array(
				array(
					'name' => 'id_list',
					'type' => 'primary',
					'columns' => array('id_list'),
				),
			)
		);

		$smcFunc['db_create_table'](
			'{db_prefix}collections_elements',
			array(
				array(
					'name' => 'id_element',
					'type' => 'int',
					'auto' => true,
				),
				array(
					'name' => 'name',
					'type' => 'varchar',
					'size' => 255
				),
				array(
					'name' => 'description',
					'type' => 'text'
				),
				array(
					'name' => 'position',
					'type' => 'smallint',
					'default' => 0
				),
				array(
					'name' => 'c_type',
					'type' => 'varchar',
					'size' => 10
				),
				array(
					'name' => 'type_values',
					'type' => 'text'
				),
				array(
					'name' => 'is_sortable',
					'type' => 'tinyint',
					'default' => 0
				),
				array(
					'name' => 'options',
					'type' => 'text'
				),
			),
			array(
				array(
					'name' => 'id_element',
					'type' => 'primary',
					'columns' => array('id_element'),
				),
			)
		);

		$smcFunc['db_create_table'](
			'{db_prefix}collections_entries',
			array(
				array(
					'name' => 'id_entry',
					'type' => 'int',
					'auto' => true,
				),
				array(
					'name' => 'id_list',
					'type' => 'smallint',
					'default' => 0
				),
				array(
					'name' => 'id_element',
					'type' => 'smallint',
					'default' => 0
				),
				array(
					'name' => 'value',
					'type' => 'tinyint',
					'default' => 0
				),
			),
			array(
				array(
					'name' => 'id_entry',
					'type' => 'primary',
					'columns' => array('id_entry'),
				),
			)
		);

		$smcFunc['db_create_table'](
			'{db_prefix}collections_collections',
			array(
				array(
					'name' => 'id_collection',
					'type' => 'int',
					'default' => 0
				),
				array(
					'name' => 'glue',
					'type' => 'int',
					'default' => 0
				),
				array(
					'name' => 'id_entry',
					'type' => 'int',
					'default' => 0
				),
				array(
					'name' => 'value',
					'type' => 'text'
				),
			),
			array(
				array(
					'name' => 'id_collection',
					'type' => 'primary',
					'columns' => array('id_entry'),
				),
			)
		);

	}

	$context['installation_done'] = true;
}

function install_menu_button (&$buttons)
{
	global $boardurl, $context;

	$context['sub_template'] = 'install_script';
	$context['current_action'] = 'install';

	$buttons['install'] = array(
		'title' => 'Installation script',
		'show' => allowedTo('admin_forum'),
		'href' => $boardurl . '/install.php',
		'active_button' => true,
		'sub_buttons' => array(
		),
	);
}

function template_install_script ()
{
	global $boardurl, $context;

	echo '
	<div class="tborder login"">
		<div class="cat_bar">
			<h3 class="catbg">
				Welcome to the install script of the mod: ' . $context['mod_name'] . '
			</h3>
		</div>
		<span class="upperframe"><span></span></span>
		<div class="roundframe centertext">';
	if (!isset($context['installation_done']))
		echo '
			<strong>Please select the action you want to perform:</strong>
			<div class="buttonlist">
				<ul>
					<li>
						<a class="active" href="' . $boardurl . '/install.php?action=install">
							<span>Install</span>
						</a>
					</li>
					<li>
						<a class="active" href="' . $boardurl . '/install.php?action=uninstall">
							<span>Uninstall</span>
						</a>
					</li>
				</ul>
			</div>';
	else
		echo '<strong>Database adaptation successful!</strong>';

	echo '
		</div>
		<span class="lowerframe"><span></span></span>
	</div>';
}
?>