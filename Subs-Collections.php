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

if (!defined('SMF'))
	die('Hacking attempt...');

function collections_add_action (&$actionArray)
{
	loadLanguage('Collections/Collections');
	$actionArray['collections'] = array('Subs-Collections.php', 'collections_show_collection');
}

function collections_add_permissions (&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
{
	loadLanguage('Collections/Collections');
	$permissionList['membergroup']['collection_modify'] = array(false, 'maintenance', 'administrate');
}

function collections_add_admin_menu (&$admin_areas)
{
	global $txt, $context;

	loadLanguage('Collections/Collections');
	$admin_areas['layout']['areas']['collections'] = array(
		'label' => $txt['collections'],
		'file' => 'Subs-Collections.php',
		'function' => 'CollectionsAdmin',
		'password' => true,
		'permission' => array('collection_modify'),
		'subsections' => array(
			'collections' => array($txt['collections']),
			'elements' => array($txt['collections_elements']),
		),
	);
}

function CollectionsAdmin ($action_override = false)
{
	global $context, $txt;

	isAllowedTo('collection_modify');

	loadLanguage('Collections/Collections');
	$subActions = array(
		'show_collection' => 'collections_listCollections',
		'edit_collection' => 'collections_editCollection',
		'save_collection' => 'collections_editCollection',
		'move_collection' => 'collections_moveCollection',
		'elements' => 'collections_listElements',
	);

	// This uses admin tabs - as it should!
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['collections'],
		'description' => $txt['collections_desc'],
		'tabs' => array(
			'collections' => array(
				'description' => $txt['collections'],
			),
			'elements' => array(
				'description' => $txt['collections_elements'],
			),
		),
	);

	if (!empty($action_override))
		$subAction = in_array($action_override, array_keys($subActions)) ? $subActions[$action_override] : $subActions['show_collection'];
	else
		$subAction = isset($_GET['sa']) && in_array($_GET['sa'], array_keys($subActions)) ? $subActions[$_GET['sa']] : $subActions['show_collection'];
	$subAction();
}

function collections_moveCollection ()
{
	global $smcFunc;

	$allowedMoves = array('up', 'down');

	$current_collection = isset($_GET['id_list']) ? (int) $_GET['id_list'] : 0;
	$current_move = isset($_REQUEST['move']) ? $_REQUEST['move'] : 0;

	if (empty($current_move) || !in_array($current_move, $allowedMoves) || empty($current_collection))
		return collections_listCollections();

	checkSession('get');

	$request = $smcFunc['db_query']('', '
		SELECT ol1.position as current_position, ol2.position as next_position, ol2.id_list
		FROM {db_prefix}collections_list as ol1
		LEFT JOIN {db_prefix}collections_list as ol2 ON (ol2.position = (ol1.position + {int:movement}))
		WHERE ol1.id_list = {int:current_collection}',
		array(
			'current_collection' => $current_collection,
			'movement' => $current_move == 'down' ? 1 : -1,
		)
	);
	list($current_position, $next_position, $swap_collection) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if (empty($next_position))
		return collections_listCollections();

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}collections_list
		SET
			position = {int:next_position}
		WHERE id_list = {int:current_collection}',
		array(
			'next_position' => $next_position,
			'current_collection' => $current_collection,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}collections_list
		SET
			position = {int:current_position}
		WHERE id_list = {int:swap_collection}',
		array(
			'current_position' => $current_position,
			'swap_collection' => $swap_collection,
		)
	);

	redirectexit('action=admin;area=collections');
}

function collections_moveElement ()
{
	global $smcFunc;

	$allowedMoves = array('up', 'down');

	$current_element = isset($_GET['elem']) ? (int) $_GET['elem'] : 0;
	$current_move = isset($_REQUEST['move']) ? $_REQUEST['move'] : 0;

	if (empty($current_move) || !in_array($current_move, $allowedMoves) || empty($current_element))
		return collections_listElements();

	checkSession('get');

	$request = $smcFunc['db_query']('', '
		SELECT el1.position as current_position, IFNULL(el2.position, -1) as next_position, el2.id_element
		FROM {db_prefix}collections_elements as el1
		LEFT JOIN {db_prefix}collections_elements as el2 ON (el2.position = (el1.position + {int:movement}))
		WHERE el1.id_element = {int:current_element}',
		array(
			'current_element' => $current_element,
			'movement' => $current_move == 'down' ? 1 : -1,
		)
	);
	list($current_position, $next_position, $swap_element) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if ($next_position == -1)
		return collections_listElements();

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}collections_elements
		SET
			position = {int:next_position}
		WHERE id_element = {int:current_element}',
		array(
			'next_position' => $next_position,
			'current_element' => $current_element,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}collections_elements
		SET
			position = {int:current_position}
		WHERE id_element = {int:swap_element}',
		array(
			'current_position' => $current_position,
			'swap_element' => $swap_element,
		)
	);

	redirectexit('action=admin;area=collections;sa=elements');
}

function collections_listElements ()
{
	global $context, $sourcedir, $scripturl, $txt, $settings;

	if (isset($_GET['editel']))
		return collections_editElements();
	if (isset($_GET['moveel']))
		collections_moveElement();
	if (isset($_POST['element_delete']))
		collections_deleteElement($_POST['element_delete']);

	// We're going to want this for making our list.
	require_once($sourcedir . '/Subs-List.php');

	$listOptions = array(
		'id' => 'collections_admin_list_elements',
		'title' => $txt['collections'],
		'width' => '100%',
		'no_items_label' => $txt['collections_no_elements_found'],
		'get_items' => array(
			'function' => 'collection_getElements',
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['collections_element_name'],
				),
				'data' => array(
					'db' => 'name',
				),
			),
			'description' => array(
				'header' => array(
					'value' => $txt['collections_element_description'],
				),
				'data' => array(
					'db' => 'description',
				),
			),
			'edit' => array(
				'header' => array(
					'value' => '',
					'style' => 'width: 6%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '
							[<a href="' . $scripturl . '?action=admin;area=collections;sa=elements;editel;elem=%1$d">' . $txt['collections_edit'] . '</a>]
						',
						'params' => array(
							'id_element' => false,
						),
					),
					'style' => 'text-align: center;white-space: nowrap;',
				),
			),
			'move' => array(
				'header' => array(
					'value' => '',
					'style' => 'width: 6%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '
							[<a href="' . $scripturl . '?action=admin;area=collections;sa=elements;moveel;move=up;elem=%1$d;' . $context['session_var'] . '=' . $context['session_id'] . '"><img src="' . $settings['images_url'] . '/sort_up.gif" alt="" /></a>
							<a href="' . $scripturl . '?action=admin;area=collections;sa=elements;moveel;move=down;elem=%1$d;' . $context['session_var'] . '=' . $context['session_id'] . '"><img src="' . $settings['images_url'] . '/sort_down.gif" alt="" /></a>]
						',
						'params' => array(
							'id_element' => false,
						),
					),
					'style' => 'text-align: center;white-space: nowrap;',
				),
			),
			'action' => array(
				'header' => array(
					'value' => '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" />',
					'style' => 'width: 6%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '
							<input type="checkbox" name="element_delete[]" value="%1$d" class="input_check" />
						',
						'params' => array(
							'id_element' => false,
						),
					),
					'style' => 'text-align: center;',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=collections;sa=elements;editel',
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '
					<input type="submit" name="go" value="' . $txt['delete_selected'] . '" onclick="var sel = document.getElementById(\'req_action\'); if (sel.value != 0 &amp;&amp; sel.value != \'reason\' &amp;&amp; !confirm(\'' . $txt['quickmod_confirm'] . '\')) return false;" class="button_submit" />
					<input type="submit" name="new" value="' . $txt['collections_add_new'] . '" onclick="var sel = document.getElementById(\'req_action\'); if (sel.value != 0 &amp;&amp; sel.value != \'reason\' &amp;&amp; !confirm(\'' . $txt['quickmod_confirm'] . '\')) return false;" class="button_submit" />',
				'align' => 'right',
			),
		),
	);

	// Create the request list.
	createList($listOptions);

	$context['default_list'] = 'collections_admin_list_elements';
	$context['sub_template'] = 'show_list';
}

function collections_createElementMask ($data)
{
	global $txt;

	if ($data['type'] == 'select')
	{
		$return = '
	<select id="' . $data['id'] . '" ' . $data['options'] . ' name="' . $data['id'] . '" class="' . (!empty($data['error']) ? ' error' : '') . '">';

		foreach (array('check', 'int', 'text', 'largetext', 'select', 'fixed', 'increment') as $type)
			$return .= '
		<option value="' . $type . '"' . ($data['input'] == $type ? ' selected="selected"' : '') . '>' . $txt['collections_' . $type] . '</option>';

		$return .= '
	</select>';
	}
	elseif ($data['type'] == 'check')
		$return = '
			<input id="' . $data['id'] . '" type="checkbox" ' . $data['options'] . ' name="' . $data['id'] . '"' . ($data['input'] ? ' checked="checked"' : '') . ' class="input_check' . (!empty($data['error']) ? ' error' : '') . '" />';
	else
		$return = '
			<input id="' . $data['id'] . '" type="text" ' . $data['options'] . ' name="' . $data['id'] . '" value="' . $data['input'] . '" class="input_text' . (!empty($data['error']) ? ' error' : '') . '" />';

	if (!empty($data['children']))
		foreach ($data['children'] as $child)
			$return .= collections_createElementMask($child);

	return $return . $data['script'];
}

function collections_loadElementsMask ($start = null, $items = null, $sort = null)
{
	global $context;

	return $context['elements']->loadMask();
}

function collections_editElements ()
{
	global $context, $smcFunc, $txt, $sourcedir, $scripturl;

	loadTemplate('Collections');
	loadLanguage('Collections/Collections');

	$context['elements'] = new collections_elements();

	$current_elem = isset($_GET['elem']) ? (int) $_GET['elem'] : '';

	if (!empty($current_elem) && isset($_POST['delete_element']))
	{
		checkSession();
		$context['elements']->delete($current_elem);
		redirectexit('action=admin;area=collections;sa=elements');
	}
	elseif (isset($_POST['element_delete']))
	{
		checkSession();
		$context['elements']->delete($_POST['element_delete']);
		redirectexit('action=admin;area=collections;sa=elements');
	}

	if (isset($_POST['save']) && !$context['elements']->hasErrors())
	{
		$context['elements']->save($current_elem);
		redirectexit('action=admin;area=collections;sa=elements');
	}

	$context['elements']->loadParams($current_elem);

	// We're going to want this for making our list.
	require_once($sourcedir . '/Subs-List.php');
	$listOptions = array(
		'id' => 'collections_admin_list',
		'title' => $txt['collections_edit_element'],
		'width' => '100%',
		'get_items' => array(
			'function' => 'collections_loadElementsMask',
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['collections_field'],
					'style' => 'width:25%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<span class="%2$s">%1$s</span>',
						'params' => array(
							'value' => false,
							'error' => false,
						),
					),
				),
			),
			'action' => array(
				'header' => array(
					'value' => $txt['collections_field_value'],
				),
				'data' => array(
					'function' => create_function('$data', '
						return collections_createElementMask($data);
					'),
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=collections;sa=elements;editel' . (empty($current_elem) ? '' : ';elem=' . $current_elem),
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '
					<input type="submit" name="delete_element" value="' . $txt['delete'] . '" onclick="var sel = document.getElementById(\'req_action\'); if (sel.value != 0 &amp;&amp; sel.value != \'reason\' &amp;&amp; !confirm(\'' . $txt['quickmod_confirm'] . '\')) return false;" class="button_submit" />
					<input type="submit" name="save" value="' . $txt['save'] . '" class="button_submit" />',
				'align' => 'right',
			),
		),
	);

	// Create the request list.
	createList($listOptions);

	$context['default_list'] = 'collections_admin_list';
	$context['sub_template'] = 'show_list';
}

// @todo: a lot of things...
function collections_deleteElement ($elements)
{
	global $smcFunc;

	$elements = is_array($elements) ? $elements : array($elements);

	foreach ($elements as &$element)
		$element = (int) $element;

	$elements = array_unique($elements);

	if (empty($elements))
		return;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}collections_elements
		WHERE id_element IN ({array_int:element})',
		array(
			'element' => $elements
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}collections_entries
		WHERE id_element IN ({array_int:element})',
		array(
			'element' => $elements
		)
	);
}

function collections_listCollections ()
{
	global $sourcedir, $context, $txt, $scripturl, $settings;

	if (isset($_GET['populate']))
		return collections_populateCollection();
	elseif (!empty($_POST['collection_delete']))
	{
		checkSession();
		collections_deleteCollections($_POST['collection_delete']);
	}
	elseif (isset($_POST['new']))
		return CollectionsAdmin('edit_collection');

	loadTemplate('Collections');
	loadLanguage('Collections/Collections');

	// We're going to want this for making our list.
	require_once($sourcedir . '/Subs-List.php');

	$listOptions = array(
		'id' => 'collections_admin_list',
		'title' => $txt['collections'],
		'width' => '100%',
		'no_items_label' => $txt['collections_none_found'],
		'get_items' => array(
			'function' => 'list_getCollections',
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['collections_name'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '
						<a href="' . $scripturl . '?action=collections;page=%2$d">%1$s</a>',
						'params' => array(
							'name' => false,
							'page' => 0
						)
					)
				),
			),
			'description' => array(
				'header' => array(
					'value' => $txt['collections_description'],
				),
				'data' => array(
					'db' => 'description',
				),
			),
			'populate' => array(
				'header' => array(
					'value' => '',
					'style' => 'width: 6%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '
							[<a href="' . $scripturl . '?action=admin;area=collections;sa=collection;populate;id_list=%1$d">' . $txt['collections_populate'] . '</a>]<br />',
						'params' => array(
							'id_list' => false,
						),
					),
					'style' => 'text-align: center;white-space: nowrap;',
				),
			),
			'edit' => array(
				'header' => array(
					'value' => '',
					'style' => 'width: 6%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '
							[<a href="' . $scripturl . '?action=admin;area=collections;sa=edit_collection;id_list=%1$d">' . $txt['collections_edit'] . '</a>]<br />',
						'params' => array(
							'id_list' => false,
						),
					),
					'style' => 'text-align: center;white-space: nowrap;',
				),
			),
			'move' => array(
				'header' => array(
					'value' => '',
					'style' => 'width: 6%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '
							[<a href="' . $scripturl . '?action=admin;area=collections;sa=move_collection;move=up;id_list=%1$d;' . $context['session_var'] . '=' . $context['session_id'] . '"><img src="' . $settings['images_url'] . '/sort_up.gif" alt="" /></a>
							<a href="' . $scripturl . '?action=admin;area=collections;sa=move_collection;move=down;id_list=%1$d;' . $context['session_var'] . '=' . $context['session_id'] . '"><img src="' . $settings['images_url'] . '/sort_down.gif" alt="" /></a>]',
						'params' => array(
							'id_list' => false,
						),
					),
					'style' => 'text-align: center;white-space: nowrap;',
				),
			),
			'delete' => array(
				'header' => array(
					'value' => '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" />',
					'style' => 'width: 6%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '
							<input type="checkbox" name="collection_delete[]" value="%1$d" class="input_check" />',
						'params' => array(
							'id_list' => false,
						),
					),
					'style' => 'text-align: center;',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=collections;sa=show_collection',
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '
					<input type="submit" name="go" value="' . $txt['delete_selected'] . '" onclick="var sel = document.getElementById(\'req_action\'); if (sel.value != 0 &amp;&amp; sel.value != \'reason\' &amp;&amp; !confirm(\'' . $txt['quickmod_confirm'] . '\')) return false;" class="button_submit" />
					<input type="submit" name="new" value="' . $txt['collections_add_new'] . '" onclick="var sel = document.getElementById(\'req_action\'); if (sel.value != 0 &amp;&amp; sel.value != \'reason\' &amp;&amp; !confirm(\'' . $txt['quickmod_confirm'] . '\')) return false;" class="button_submit" />',
				'align' => 'right',
			),
		),
	);

	// Create the request list.
	createList($listOptions);

	$context['default_list'] = 'collections_admin_list';
	$context['sub_template'] = 'show_list';
}

function list_getCollections ()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_list, name, description, page
		FROM {db_prefix}collections_list
		ORDER by position',
		array()
	);

	$collections = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$collections[] = $row;
	$smcFunc['db_free_result']($request);

	return $collections;
}

function collections_deleteCollections ($collection_ids)
{
	global $smcFunc;

	$collection_ids = is_array($collection_ids) ? $collection_ids : array($collection_ids);

	foreach ($collection_ids as &$collection)
		$collection = (int) $collection;

	$collection_ids = array_unique($collection_ids);

	if (empty($collection_ids))
		return;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}collections_entries
		WHERE id_list IN ({array_int:collections})',
		array(
			'collections' => $collection_ids
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}collections_list
		WHERE id_list IN ({array_int:collections})',
		array(
			'collections' => $collection_ids
		)
	);
}

function collections_populateCollection ()
{
	global $context, $smcFunc, $sourcedir, $txt, $scripturl;

	$id_list = isset($_GET['id_list']) ? (int) $_GET['id_list'] : 0;
	if (empty($id_list))
		fatal_lang_error('collections_list_not_found', false);

	if (isset($_REQUEST['save']))
	{
		checkSession();

		$errors = array();

		if (isset($_POST['delete']) && !empty($_POST['collection_delete']))
			$errors[] = collections_deleteItems($_POST['collection_delete'], $id_list);
		if (!empty($_POST['collection_new']))
			$errors[] = collections_insertNewItems($_POST['collection_new'], $id_list);
		if (!empty($_POST['collection_edit']))
			$errors[] = collections_updateItems($_POST['collection_edit']);

		$errors = array_filter($errors, create_function('$data', 'return !empty($data);'));

		if (empty($errors))
			redirectexit('action=admin;area=collections');
	}

	$request = $smcFunc['db_query']('', '
		SELECT en.id_entry, en.id_element,
			el.name, el.description, el.c_type as type, el.type_values
		FROM {db_prefix}collections_entries as en
		LEFT JOIN {db_prefix}collections_elements as el ON (en.id_element = el.id_element)
		WHERE en.id_list = {int:current_list}
			AND en.value = {int:enabled}
		ORDER BY el.position',
		array(
			'current_list' => $id_list,
			'enabled' => 1,
		)
	);

	$current_columns = array();
	$params = array();
	$params['columns'] = array();
	$makeScript = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$header = empty($row['description']) ? $row['name'] : '<div title="' . $smcFunc['htmlspecialchars']($row['description']) . '">' . $row['name'] . '</div>';
		$current_columns[$row['id_element']] = array(
			'header' => array(
				'value' => $header,
			),
			'data' => array(
				'function' => create_function('$datas', '
					$default = array(' . call_user_func_array(
						create_function('$row', '
							$return = \'\';

							foreach ($row as $key => $val)
								$return .= \'\\\'\' . $key . \'\\\' => \\\'\' . addslashes($val) . \'\\\', \';
							return $return;'), array($row)) . ');

						return collections_createItemsMask(!empty($datas[' . $row['id_element'] . ']) ? $datas[' . $row['id_element'] . '] : $default, ' . $row['id_element'] . ', !empty($datas[\'glue\']) ? $datas[\'glue\'] : false);
				')
			),
		);
		$params['columns'][$row['id_element']] = $row;
		$makeScript[] = collections_createItemsMask($row, $row['id_element']);
	}
	$smcFunc['db_free_result']($request);
	$params['id_list'] = $id_list;
	$context['admin'] = true;

	// We're going to want this for making our list.
	require_once($sourcedir . '/Subs-List.php');

	$listOptions = array(
		'id' => 'collections_populate',
		'title' => $txt['collections'],
		'width' => '100%',
		'no_items_label' => $txt['collections_no_elements_found'],
		'get_items' => array(
			'function' => 'list_getCollectionEntries',
			'params' => $params,
		),
		'columns' => array_merge(
			$current_columns,
			array(
				'action' => array(
					'header' => array(
						'value' => '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" />',
						'style' => 'width: 6%;',
					),
					'data' => array(
						'function' => create_function('$datas', '
								global $smcFunc;
								foreach ($datas as $data)
									if (isset($data[\'glue\']))
									{
										$glue = $data[\'glue\'];
										break;
									}
								if (isset($glue))
									return \'<input type="checkbox" name="collection_delete[]" value="\' . $glue . \'" class="input_check" />\';
								else
									return;
						'),
						'style' => 'text-align: center;',
					),
				),
			)
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=collections;sa=collection;populate;save;id_list=' . $id_list,
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '
					<input type="submit" name="delete" value="' . $txt['delete_selected'] . '" onclick="var sel = document.getElementById(\'req_action\'); if (sel.value != 0 &amp;&amp; sel.value != \'reason\' &amp;&amp; !confirm(\'' . $txt['quickmod_confirm'] . '\')) return false;" class="button_submit" />
					<input type="submit" name="save" value="' . $txt['save'] . '" class="button_submit" />
					<script type="text/javascript"><!-- // --><![CDATA[
						var last_node = document.getElementById(\'list_collections_populate_\' + collections_last_item);
						toggleEvents(last_node, true);
						function addNewCollectionItem ()
						{
							last_node = document.getElementById(\'list_collections_populate_\' + collections_last_item);
							collections_last_item++;
							var new_node = document.createElement(\'tr\');
							new_node.className = \'windowbg\' + (collections_last_item % 2 ? \'2\' : \'\');
							new_node.id = \'list_collections_populate_\' + collections_last_item;
							new_node.innerHTML = \'<td>\' + ' . JavaScriptEscape(implode('</td><td>', $makeScript)) . ' + \'</td><td></td>\';
							last_node.parentNode.insertBefore(new_node, null);
							toggleEvents(last_node, false);
							toggleEvents(new_node, true);
						}
						function toggleEvents (elem, add)
						{
							if (typeof(add) == undefined)
								add = true;

							var oElements = elem.getElementsByTagName(\'td\');
							for (oElement in oElements)
							{
								if (oElements[oElement].childNodes.length == 0)
									break;
								var node_name = oElements[oElement].firstChild.nodeName.toLowerCase();
								if (node_name == \'input\' || node_name == \'textarea\' || node_name == \'select\')
								{
									if (add)
										oElements[oElement].addEventListener(node_name == \'select\' ? \'change\' : \'keyup\', addNewCollectionItem);
									else
										oElements[oElement].removeEventListener(node_name == \'select\' ? \'change\' : \'keyup\', addNewCollectionItem);
								}
							}
						}
					// ]]></script>
				',
				'align' => 'right',
			),
		),
	);

	// Create the request list.
	createList($listOptions);

	$context['default_list'] = 'collections_populate';
	$context['sub_template'] = 'show_list';
}

function collections_createItemsMask ($data, $id_entry, $glue = false)
{
	global $txt;

	$item_name = empty($data['id_collection']) ? 'collection_new[' . $id_entry . '][]' : 'collection_edit[' . $id_entry . '][' . $data['id_collection'] . ']';
	$glue_input = !empty($glue) ? '<input type="hidden" name="glue_' . $item_name . '" value="' . $glue . '" />' : '';

	if (!empty($data['type']))
	{
		if ($data['type'] == 'check')
			$return = '<input type="checkbox" name="' . $item_name . '" ' . 
			(!empty($data['value']) ? 'checked="checked" ' : '') . 'class="input_check" />';
		elseif ($data['type'] == 'select')
		{
			$return = '<select name="' . $item_name . '">';
			$entries[] = '';
			$entries = array_merge($entries, explode(',', $data['type_values']));

			// @TODO this is not particularly safe...I mean have real values in value can be problematic...
			foreach ($entries as $entry)
				$return .= '
					<option value="' . $entry . '"' . (!empty($data['value']) && $data['value'] == $entry ? ' selected="selected"' : '' ) . '>' . $entry . '</option>';
			$return .= '
				</select>';
		}
		elseif ($data['type'] == 'largetext')
			$return = '<textarea rows="10" cols="30" name="' . $item_name . '">' . (isset($data['value']) ? $data['value'] : '') . '</textarea>';
		elseif ($data['type'] == 'fixed')
			$return = $data['type_values'];
		elseif ($data['type'] == 'increment')
			$return = $txt['collections_increment'];
	}
	// May be worth use htmlspecialchars
	if (empty($return))
		$return = '<input type="text" name="' . $item_name . '" value="' . (isset($data['value']) ? $data['value'] : '') . '" class="input_text" />';

	return $return . $glue_input;
}

function list_getCollectionEntries ($start, $items, $sort, $params, $id_list, $is_sortable = false)
{
	global $smcFunc, $context;

	$ret = $return = array();
	if (!empty($sort))
	{
		$t = explode(' ', $sort);
		$sort_id = $t[0];
		$sort_dir = isset($t[1]) ? '-' : '';
	}
	else
	{
		$sort_id = 1;
		$sort_dir = '';
	}

	foreach ($params as $par)
		$ret[$par['id_element']] = $par;

	$request = $smcFunc['db_query']('', '
		SELECT co.id_collection, co.glue, co.id_entry, co.value,
			en.id_element, el.options,
			el.c_type as type, el.is_sortable
		FROM {db_prefix}collections_collections as co
		LEFT JOIN {db_prefix}collections_entries as en ON (co.id_entry = en.id_entry)
		LEFT JOIN {db_prefix}collections_elements as el ON (en.id_element = el.id_element)
		WHERE en.id_list = {int:current_list}',
		array(
			'current_list' => $id_list
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$tmp[$row['glue']][$row['id_element']] = array_merge($ret[$row['id_element']], $row);
	}
	$smcFunc['db_free_result']($request);

	$counter = 1;
	if (!empty($tmp))
		foreach ($tmp as $key => $val)
		{
			$val['increment'] = $counter++;
			if ($is_sortable)
				$val['sort'] = $val[$sort_id]['value'];
			$val['glue'] = $key;
			$return[] = $val;
		}

	if ($is_sortable)
		usort($return, create_function('$v1, $v2', '
			return ' . $sort_dir . 'strcmp($v1[\'sort\'], $v2[\'sort\']);'));

	if (!empty($context['admin']))
	{
		$context['html_headers'] .= '
		<script type="text/javascript"><!-- // --><![CDATA[
			var collections_last_item = ' . count($return) . ';
		// ]]></script>
	';

		$return[] = $ret;
	}

	return $return;
}

function collections_insertNewItems ($items, $id_list)
{
	global $smcFunc;

	if (empty($items))
		return;

	$request = $smcFunc['db_query']('', '
		SELECT MAX(co.glue)
		FROM {db_prefix}collections_collections as co
		LEFT JOIN {db_prefix}collections_entries as en ON (co.id_entry = en.id_entry)
		WHERE en.id_list = {int:current_list}',
		array(
			'current_list' => $id_list
		)
	);
	list($start_glue) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);
	$start_glue++;

	// This insane piece of code is necessary to cleanup the input and find empty new items.
	// I wonder if there is a better way to do it...
	foreach ($items as $key => $values)
	{
		foreach ($values as $k => $v)
		{
			if (isset($total[$k]))
				$total[$k]++;
			else
				$total[$k] = 1;
			if (empty($v))
				if (isset($count[$k]))
					$count[$k]++;
				else
					$count[$k] = 1;
		}
	}
	$max = max($total);
	if (isset($count))
		foreach ($count as $k => $v)
		{
			if ($v == $max)
				foreach ($items as $key => $values)
					unset($items[$key][$k]);
		}

	// Validation is the key to success!
	$request = $smcFunc['db_query']('', '
		SELECT en.id_entry, el.c_type as type, el.type_values, el.id_element
		FROM {db_prefix}collections_entries as en
		LEFT JOIN {db_prefix}collections_elements as el ON (en.id_element = el.id_element)
		WHERE en.id_list = {int:current_list}',
		array(
			'current_list' => $id_list,
		)
	);
	$validation_data = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$validation_data[$row['id_element']] = $row;
	$smcFunc['db_free_result']($request);

	// Let's prepare things to be stored into the database
	$inserts = array();
	foreach ($items as $key => $values)
	{
		$possible_glue = isset($_POST['glue_collection_new'][$key]) ? (int) $_POST['glue_collection_new'][$key] : 0;
		$glue = !empty($possible_glue) ? $possible_glue : $start_glue;
		foreach ($values as $val)
			if (collections_isValidEntry($val, $validation_data[$key]['type'], $validation_data[$key]['type_values']) && !empty($val))
				$inserts[] = array($validation_data[$key]['id_entry'], $glue++, $val);
	}

	if (empty($inserts))
		return;

	$smcFunc['db_insert']('',
		'{db_prefix}collections_collections',
		array(
			'id_entry' => 'int', 'glue' => 'int', 'value' => 'string'
		),
		$inserts,
		array(
			'id_collection'
		)
	);
}

function collections_updateItems ($items)
{
	global $smcFunc;

	if (empty($items))
		return;

	$normal = array();
	$changed = array();
	$errors = array();

	// Normalization
	foreach ($items as $key => $collections)
		foreach ($collections as $k => $v)
			$normal[$k] = $v;

	// First let's update only what has changed
	$request = $smcFunc['db_query']('', '
		SELECT co.id_collection, co.value,
			el.c_type as type, el.type_values
		FROM {db_prefix}collections_collections as co
		LEFT JOIN {db_prefix}collections_entries as en ON (co.id_entry = en.id_entry)
		LEFT JOIN {db_prefix}collections_elements as el ON (en.id_element = el.id_element)
		WHERE co.id_collection IN ({array_int:id_affected})',
		array(
			'id_affected' => array_keys($normal),
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		if ($normal[$row['id_collection']] != $row['value'])
		{
			$changed[$row['id_collection']] = $row;
			$changed[$row['id_collection']]['new_value'] = $normal[$row['id_collection']];
		}
	$smcFunc['db_free_result']($request);

	// Hopefully we are lucky enough it will not timeout..and we have also to validate the fields!
	foreach ($changed as $key => $val)
		if (collections_isValidEntry($val['new_value'], $val['type'], $val['type_values']))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}collections_collections
				SET
					value = {string:new_value}
				WHERE id_collection = {int:collection}',
				array(
					'collection' => $key,
					'new_value' => $val['new_value'],
				)
			);
		else
			$errors[] = $key;
}

function collections_isValidEntry (&$value, $type, $validation)
{
	global $smcFunc;

	if ($type == 'check')
		$value = !empty($value);
	elseif ($type == 'int')
		$value = (int) $value;
	elseif ($type == 'text' || $type == 'largetext')
		$value = $smcFunc['htmlspecialchars']($value);
	elseif ($type == 'select' && !empty($validation))
	{
		$allowed_types = explode(',', $validation);
		if (in_array($value, $allowed_types))
			return true;
	}
	else
		return false;

	return true;
}

function collections_deleteItems($items, $id_list)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_entry
		FROM {db_prefix}collections_entries
		WHERE id_list = {int:current_list}',
		array(
			'current_list' => $id_list,
		)
	);
	$entries = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$entries[] = $row['id_entry'];
	$smcFunc['db_free_result']($request);

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}collections_collections
		WHERE id_entry IN ({array_int:current_entries})
			AND glue IN ({array_int:glue})',
		array(
			'glue' => $items,
			'current_entries' => $entries,
		)
	);
}

function collections_editCollection ()
{
	global $context, $smcFunc, $sourcedir, $txt, $scripturl;

	$collection_data['collection_id'] = isset($_GET['id_list']) ? (int) $_GET['id_list'] : '';

	if (isset($_REQUEST['save']))
	{
		checkSession();
		$collection_data['name'] = isset($_POST['collection_edit']['name']) ? trim($smcFunc['htmlspecialchars']($_POST['collection_edit']['name'])) : '';
		$collection_data['description'] = isset($_POST['collection_edit']['description']) ? trim($smcFunc['htmlspecialchars']($_POST['collection_edit']['description'])) : '';
		$collection_data['details'] = array();
		$collection_data['page'] = isset($_POST['collection_edit']['page']) ? (int) $_POST['collection_edit']['page'] : 0;
		foreach ($_POST['collection_edit'] as $key => $value)
			$collection_data['details'][$key] = trim($smcFunc['htmlspecialchars']($value));

		$errors = collection_saveCollection($collection_data);

		if (empty($errors))
			redirectexit('action=admin;area=collections');
	}

	// We're going to want this for making our list.
	require_once($sourcedir . '/Subs-List.php');

	$listOptions = array(
		'id' => 'collections_admin_edit',
		'title' => $txt['collections'],
		'width' => '100%',
		'no_items_label' => $txt['collections_no_elements_found'],
		'get_items' => array(
			'function' => 'list_getCollectionElements',
			'params' => array($collection_data['collection_id'])
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['collections_name'],
				),
				'data' => array(
					'db' => 'name',
				),
			),
			'description' => array(
				'header' => array(
					'value' => $txt['collections_description'],
				),
				'data' => array(
					'db' => 'description',
				),
			),
			'action' => array(
				'header' => array(
					'value' => $txt['collections_field_value'],
				),
				'data' => array(
					'function' => create_function('$data', '
						global $smcFunc;

						if (in_array($data[\'id_element\'], array(\'name\', \'description\', \'page\')))
							return \'<input type="text" name="collection_edit[\' . $data[\'id_element\'] . \']" value="\' . $data[\'value\'] . \'" class="input_text" />\';
						else
							return \'<input type="checkbox" name="collection_edit[\' . $data[\'id_element\'] . \']" \' . (!empty($data[\'value\']) ? \'checked="checked" \' : \'\') . \'class="input_check" />\';
					'),
					'style' => 'text-align: center;',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=collections;sa=save_collection' . (!empty($collection_data['collection_id']) ? ';id_list=' . $collection_data['collection_id'] : ''),
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '
					<input type="submit" name="save" value="' . $txt['save'] . '" class="button_submit" />',
				'align' => 'right',
			),
		),
	);

	// Create the request list.
	createList($listOptions);

	$context['default_list'] = 'collections_admin_edit';
	$context['sub_template'] = 'show_list';
}

function list_getCollectionElements ($start, $items, $sort, $collection_id)
{
	global $txt, $smcFunc;

	$collection_name = '';
	$collection_desc = '';
	$page = 0;

	if (isset($_REQUEST['save']))
	{
		$collection_name = !empty($_POST['collection_edit']['name']) ? $smcFunc['htmlspecialchars']($_POST['collection_edit']['name']) : '';
		$collection_desc = !empty($_POST['collection_edit']['description']) ? $smcFunc['htmlspecialchars']($_POST['collection_edit']['description']) : '';
		$page = isset($_POST['collection_edit']['page']) ? (int) $_POST['collection_edit']['page'] : 0;
		foreach ($_POST['collection_edit'] as $key => $value)
			$entries[$key] = $value;
	}
	elseif (!empty($collection_id))
	{
		$get_entries = collection_getEntries($collection_id, 'id_element');

		foreach ($get_entries as $key => $entry)
			$entries[$key] = $entry['value'];

		$request = $smcFunc['db_query']('', '
			SELECT name, description, page
			FROM {db_prefix}collections_list
			WHERE id_list = {int:collection}
			ORDER BY position',
			array(
				'collection' => $collection_id
			)
		);
		list($collection_name, $collection_desc, $page) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	$collection_details = array(
		array(
			'id_element' => 'name',
			'name' => $txt['collections_collection_name'],
			'description' => $txt['collections_collection_name_description'],
			'value' => $collection_name
		),
		array(
			'id_element' => 'description',
			'name' => $txt['collections_collection_description'],
			'description' => $txt['collections_collection_description_description'],
			'value' => $collection_desc
		),
		array(
			'id_element' => 'page',
			'name' => $txt['collections_collection_page'],
			'description' => $txt['collections_collection_page_description'],
			'value' => $page
		),
	);

	$elements = collection_getElements();
	foreach ($elements as $element)
		$collection_details[] = array(
			'id_element' => $element['id_element'],
			'name' => $element['name'],
			'description' => $element['description'],
			'type' => $element['type'],
			'value' => isset($entries[$element['id_element']]) ? $entries[$element['id_element']] : '',
		);

	return $collection_details;
}

function collection_saveCollection ($data)
{
	global $smcFunc;

	$errors = array();
	foreach (array('name') as $check)
		if (empty($data[$check]))
			$errors[] = 'no_collection_' . $check;

	if (!empty($errors))
		return $errors;

	$inserts = array();

	// That's a new entry
	if (empty($data['collection_id']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT MAX(position)
			FROM {db_prefix}collections_list',
			array()
		);
		list($last_pos) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		$last_pos++;

		$smcFunc['db_insert']('',
			'{db_prefix}collections_list',
			array(
				'name' => 'string-255', 'description' => 'string', 'page' => 'int', 'position' => 'int'
			),
			array(
				$data['name'], $data['description'], $data['page'], $last_pos
			),
			array(
				'id_list'
			)
		);
		$data['collection_id'] = $smcFunc['db_insert_id']('{db_prefix}collections_list');

	}
	// Just an update
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_element
			FROM {db_prefix}collections_entries
			WHERE id_list = {int:collection}',
			array(
				'collection' => $data['collection_id']
			)
		);
		$elements = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$elements[] = $row['id_element'];
		$smcFunc['db_free_result']($request);

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}collections_list
			SET
				name = {string:upd_name},
				description = {string:upd_desc},
				page = {string:upd_page}
			WHERE id_list = {int:collection}',
			array(
				'upd_name' => $data['name'],
				'upd_desc' => $data['description'],
				'upd_page' => $data['page'],
				'collection' => $data['collection_id'],
			)
		);

		// These have been removed
		foreach ($elements as $element)
			if (!isset($data['details'][$element]))
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}collections_entries
					SET
						value = {int:upd_value}
					WHERE id_list = {int:collection}
						AND id_element = {int:element}',
					array(
						'upd_value' => 0,
						'collection' => $data['collection_id'],
						'element' => $element,
					)
				);
				unset($data['details'][$element]);
			}

		// Elements other than the fixed one have a numeric ID
		foreach ($data['details'] as $id_element => $value)
			if (is_numeric($id_element) && in_array($id_element, $elements))
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}collections_entries
					SET
						value = {int:upd_value}
					WHERE id_list = {int:collection}
						AND id_element = {int:element}',
					array(
						'upd_value' => (int) ($value == 'on'),
						'collection' => $data['collection_id'],
						'element' => $id_element,
					)
				);
				unset($data['details'][$id_element]);
			}

		if (empty($data['details']))
			return;
	}

	foreach ($data['details'] as $id_element => $value)
		if (is_numeric($id_element))
			$inserts[] = array(
				$data['collection_id'],
				$id_element,
				(int) ($value == 'on'),
			);

	$smcFunc['db_insert']('',
		'{db_prefix}collections_entries',
		array(
			'id_list' => 'int', 'id_element' => 'int', 'value' => 'int'
		),
		$inserts,
		array(
			'id_entry'
		)
	);
}

function collections_show_collection ()
{
	global $smcFunc, $context, $sourcedir, $txt, $scripturl;

	loadTemplate('Collections');
	loadLanguage('Collections/Collections');
	$context['sub_template'] = 'collection_page';

	$page = isset($_GET['page']) ? (int) $_GET['page'] : 0;

	$request = $smcFunc['db_query']('', '
		SELECT id_list, name, description
		FROM {db_prefix}collections_list
		WHERE page = {int:id_page}
		ORDER BY position',
		array(
			'id_page' => $page
		)
	);

	$context['collection_lists'] = array();
	$lists_info = array();
	$titles = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$titles[] = $row['name'];
		$lists_info[$row['id_list']] = $row;
	}
	$smcFunc['db_free_result']($request);

	$context['page_title'] = empty($lists_info) ? $txt['collections_no_lists_in_page'] : implode(' - ', $titles);

	if (empty($lists_info))
		fatal_lang_error('collections_page_not_found', false);

	// This will grab all the info about the columns
	$request = $smcFunc['db_query']('', '
		SELECT en.id_list, en.id_entry, en.id_element,
			el.name, el.description, el.c_type as type, el.type_values, el.is_sortable, el.options
		FROM {db_prefix}collections_entries as en
		LEFT JOIN {db_prefix}collections_elements as el ON (en.id_element = el.id_element)
		WHERE en.id_list IN ({array_int:current_list})
			AND en.value = {int:enabled}
		ORDER BY el.position',
		array(
			'current_list' => empty($lists_info) ? array(0) : array_keys($lists_info),
			'enabled' => 1,
		)
	);

	// We're going to want this for making our list.
	require_once($sourcedir . '/Subs-List.php');

	$current_columns = array();
	$is_sortable = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$header = empty($row['description']) ? $row['name'] : '<span title="' . $smcFunc['htmlspecialchars']($row['description']) . '">' . $row['name'] . '</span>';

		$opt = @unserialize($row['options']);
		if (!empty($opt))
			foreach ($opt as $k => $v)
				$row[$k] = $v;

		$current_columns[$row['id_list']]['a' . $row['id_element']] = array(
			'header' => array(
				'value' => $header,
			),
			'data' => array(
				'function' => create_function('$datas', '
					global $txt, $sourcedir;
					$default = array(' . call_user_func_array(
						create_function('$row', '
							$return = \'\';

							foreach ($row as $key => $val)
								$return .= \'\\\'\' . $key . \'\\\' => \\\'\' . addslashes($val) . \'\\\', \';
							return $return;'), array($row)) . ');

					$data = !empty($datas[' . $row['id_element'] . ']) ? $datas[' . $row['id_element'] . '] : $default;

					if ($data[\'type\'] == \'check\')
						return !empty($data[\'value\']) ? $txt[\'collections_yes\'] : $txt[\'collections_no\'];
					elseif ($data[\'type\'] == \'fixed\')
						return $data[\'type_values\'];
					elseif ($data[\'type\'] == \'increment\')
						return $datas[\'increment\'];
					else
					{
						if (empty($data[\'value\']))
							return \'&nbsp;\';
						else
							if (!empty($data[\'bb_code\']))
							{
								require_once($sourcedir . \'/Subs-Post.php\');
								preparsecode($data[\'value\']);
								return parse_bbc($data[\'value\']);
							}
							else
								return $data[\'value\'];
					}
				'),
			),
		);

		if (!empty($row['is_sortable']))
		{
			$is_sortable[$row['id_list']] = true;
			$current_columns[$row['id_list']]['a' . $row['id_element']]['sort'] = array(
				'default' => $row['id_element'],
				'reverse' => $row['id_element'] . ' desc',
			);
		}

		if (!empty($row['head_styles']))
			$current_columns[$row['id_list']]['a' . $row['id_element']]['header']['style'] = $row['head_styles'];

		if (!empty($row['col_styles']))
			$current_columns[$row['id_list']]['a' . $row['id_element']]['data']['style'] = $row['col_styles'];

		$params['columns'][] = $row;
	}
	$smcFunc['db_free_result']($request);

	foreach ($lists_info as $row)
	{
		if (empty($current_columns[$row['id_list']]))
			continue;

		$params['id_list'] = $row['id_list'];
		$params['is_sortable'] = !empty($is_sortable[$row['id_list']]);
		$listOptions = array(
			'id' => 'collections_list_' . $row['id_list'],
			'title' => $row['name'],
			'width' => '100%',
			'base_href' => $scripturl . '?action=collections;page=' . $page,
			'no_items_label' => $txt['collections_no_elements_found'],
			'get_items' => array(
				'function' => 'list_getCollectionEntries',
				'params' => $params,
			),
			'columns' => $current_columns[$row['id_list']],
			'additional_rows' => array(
				array(
					'position' => 'top_of_list',
					'value' => (!empty($row['description']) ? '
						<div class="windowbg description">' . $row['description'] . '</div>' : ''),
					'align' => 'right',
				),
				array(
					'position' => 'below_table_data',
					'value' => '
						<div style="padding-top:1em"></div>',
					'align' => 'right',
				),
			),
		);

		if ($params['is_sortable'])
			$listOptions += array(
				'default_sort_col' => 'a1',
				'default_sort_dir' => 'asc',
			);

		// Create the request list.
		createList($listOptions);

		$context['collection_lists'][] = 'collections_list_' . $row['id_list'];
	}
}

function collection_getEntries ($collections, $array_index = 'id_list')
{
	global $smcFunc;

	if (empty($collections))
		return false;

	$collections = is_array($collections) ? $collections : array($collections);

	$request = $smcFunc['db_query']('', '
		SELECT el.name, en.value, en.id_list, el.id_element, el.type_values
		FROM {db_prefix}collections_elements as el
		LEFT JOIN {db_prefix}collections_entries as en ON (el.id_element = en.id_element)
		WHERE en.id_list IN ({array_int:id_list})
			AND en.value != {string:empty}
		ORDER BY el.position',
		array(
			'id_list' => $collections,
			'empty' => ''
		)
	);

	$elements = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$elements[$row[$array_index]] = $row;
	$smcFunc['db_free_result']($request);

	return $elements;
}

function collection_getElements ()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_element, name, description, c_type as type
		FROM {db_prefix}collections_elements
		ORDER BY position',
		array()
	);
	$return = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$return[] = $row;
	$smcFunc['db_free_result']($request);

	return $return;
}

class collections_elements extends collections_functions
{
	protected $valid_options = array();

	private $params = array();
	private $errors = array();

	public function __construct ($validate = true)
	{
		parent::__construct();
		$this->valid_options = array(
		'name' => array(
			'type' => 'text',
			'validate' => create_function('$data', '
				global $smcFunc;
				return trim($smcFunc[\'htmlspecialchars\']($data));'
			),
			'default' => '',
		),
		'description' => array(
			'type' => 'text',
			'validate' => create_function('$data', '
				global $smcFunc;
				return trim($smcFunc[\'htmlspecialchars\']($data));'
			),
			'default' => '',
		),
		'selected' => array(
			'type' => 'select',
			'validate' => create_function('$data', '
				$allowed_types = array(\'check\', \'int\', \'text\', \'largetext\', \'select\', \'fixed\', \'increment\');
				return in_array($data, $allowed_types) ? $data : \'text\';'
			),
			'post_name' => 'type',
			'default' => 'text',
			'has_children' => array('type_values'),
			'options' => 'onchange="toggleInput(this)"',
			'script' => '
		function toggleInput(elem)
		{
			if (elem.options[elem.selectedIndex].value == \'select\' || elem.options[elem.selectedIndex].value == \'fixed\')
				document.getElementById(\'type_values\').style.display = \'\';
			else
				document.getElementById(\'type_values\').style.display = \'none\';
		}
		toggleInput(document.getElementById(\'selected\'));',
		),
		'sortable' => array(
			'type' => 'check',
			'validate' => create_function('$data, $selected', '
				$sortable_types = array(\'int\', \'text\', \'largetext\', \'select\', \'increment\');
				return !empty($data) && in_array($selected, $sortable_types) ? 1 : 0;'
			),
			'require' => 'selected',
			'default' => 0,
		),
		'type_values' => array(
			'type' => 'text',
			'validate' => create_function('$data, $selected', '
				global $smcFunc;
				return isset($data) && ($selected == \'select\' || $selected == \'fixed\') ? trim($smcFunc[\'htmlspecialchars\']($data)) : \'\';'
				),
			'require' => 'selected',
			'only_child' => true,
			'default' => '',
		),
		'bb_code' => array(
			'type' => 'check',
			'validate' => create_function('$data, $selected', '
				$bbcodable_types = array(\'text\', \'largetext\', \'select\');
				return !empty($data) && in_array($selected, $bbcodable_types) ? 1 : 0;'
			),
			'require' => 'selected',
			'default' => 0,
		),
		'head_styles' => array(
			'type' => 'text',
			'validate' => create_function('$data', '
				global $smcFunc;
				return !empty($data) ? $smcFunc[\'htmlspecialchars\']($data) : \'\';'
			),
			'default' => '',
		),
		'col_styles' => array(
			'type' => 'text',
			'validate' => create_function('$data', '
				global $smcFunc;
				return !empty($data) ? $smcFunc[\'htmlspecialchars\']($data) : \'\';'
			),
			'default' => '',
		),
	);

		if ($validate)
			$this->validate();
	}

	public function getValidOptions ()
	{
		return $this->valid_options;
	}

	public function hasErrors ($return = false)
	{
		if ($return)
			return $this->errors;
		else
			return !empty($this->errors);
	}

	public function validate ()
	{
		global $smcFunc;

		// Reset
		$this->params = array();
		$this->errors = array();

		foreach ($this->valid_options as $key => $check)
			if (isset($_POST[isset($check['post_name']) ? $check['post_name'] : $key]))
			{
				if (isset($check['require']) && isset($this->params[$check['require']]))
					$this->params[$key] = $check['validate']($_POST[isset($check['post_name']) ? $check['post_name'] : $key], $this->params[$check['require']]);
				elseif (!isset($check['require']))
					$this->params[$key] = $check['validate']($_POST[isset($check['post_name']) ? $check['post_name'] : $key]);
				else
					$this->params[$key] = $check['default'];
			}
			else
				$this->params[$key] = $check['default'];

		$this->errors['name'] = isset($_POST['name']) && empty($this->params['name']) || $smcFunc['strlen']($this->params['name']) > 255;
		$this->errors['description'] = isset($_POST['description']) && empty($this->params['description']);
	}

	public function getParams ($id)
	{
		if (empty($this->params['name']) && empty($this->params['description']) && !empty($id))
			$this->loadParams($id);

		return $this->params;
	}

	public function loadParams ($id)
	{
		$rets = $this->db_query_assoc('', '
			SELECT name, description, c_type, type_values, is_sortable, options
			FROM {db_prefix}collections_elements
			WHERE id_element = {int:element}',
			array(
				'element' => $id
			)
		);

		$opt = $rets[0]['options'];
		$opt = @unserialize($opt);
		unset($rets[0]['options']);
		$this->params = $rets[0];

		if (!empty($opt))
			foreach ($opt as $key => $value)
				$this->params[$key] = $value;
	}

	public function save ($id)
	{
		$other_options = array(
			'bb_code' => 0,
			'head_styles' => '',
			'col_styles' => '',
		);

		$additional = array();
		foreach ($other_options as $option => $default)
			if (isset($this->params[$option]))
				$additional[$option] = $this->params[$option];
			else
				$additional[$option] = $default;

		$additional = serialize($additional);

		if (empty($id))
		{
			list($last_position) = $this->db_query_row('', '
				SELECT MAX(position)
				FROM {db_prefix}collections_elements',
				array()
			);
			$last_position++;

			$this->db_insert('',
				'{db_prefix}collections_elements',
				array(
					'name' => 'string-255',
					'description' => 'string',
					'position' => 'int',
					'c_type' => 'string-10',
					'type_values' => 'string',
					'is_sortable' => 'int',
					'options' => 'string'
				),
				array(
					$this->params['name'],
					$this->params['description'],
					$last_position,
					$this->params['selected'],
					$this->params['type_values'],
					$this->params['sortable'],
					$additional
				),
				array('id_element')
			);
		}
		else
			$this->db_query('', '
				UPDATE {db_prefix}collections_elements
				SET
					name = {string:name},
					description = {string:desc},
					c_type = {string:type},
					type_values = {string:type_values},
					is_sortable = {string:is_sortable},
					options = {string:options}
				WHERE id_element = {int:element}',
				array(
					'name' => $this->params['name'],
					'desc' => $this->params['description'],
					'type' => $this->params['selected'],
					'type_values' => $this->params['type_values'],
					'element' => $id,
					'is_sortable' => $this->params['sortable'],
					'options' => $additional,
				)
			);
	}

	public function delete ($ids = array())
	{
		$ids = is_array($ids) ? $ids : array($ids);
		$ids = array_map('intval', $ids);
		$ids = array_unique($ids);

		if (!empty($ids))
		{
			$this->db_query('', '
				DELETE FROM {db_prefix}collections_elements
				WHERE id_element IN ({array_int:element})',
				array(
					'element' => $ids
				)
			);
			$this->db_query('', '
				DELETE FROM {db_prefix}collections_entries
				WHERE id_element IN ({array_int:element})',
				array(
					'element' => $ids
				)
			);
		}

		return true;
	}

	public function loadMask ()
	{
		global $txt;

		$options = array();

		foreach (array_keys($this->valid_options) as $key)
			if (!isset($this->valid_options[$key]['only_child']))
				$options[$key] = $this->loadRecursive($key);

		return $options;
	}

	private function loadRecursive ($key)
	{
		global $txt;

		$option = array(
			'id' => $key,
			'value' => isset($txt['collections_field_' . $key]) ? $txt['collections_field_' . $key] : '',
			'type' => $this->valid_options[$key]['type'],
			'input' => isset($this->params[$key]) ? $this->params[$key] : '',
			'error' => !empty($errors[$key]),
			'options' => !empty($this->valid_options[$key]['options']) ? $this->valid_options[$key]['options'] : '',
			'script' => !empty($this->valid_options[$key]['script']) ? '
	<script type="text/javascript"><!-- // --><![CDATA[' . $this->valid_options[$key]['script'] . '
	// ]]></script>' : '',
		);
		if (isset($this->valid_options[$key]['has_children']))
			foreach ($this->valid_options[$key]['has_children'] as $child_id)
				$option['children'][$child_id] = $this->loadRecursive($child_id);

		return $option;
	}
}

/**
 * For now it is a simple wrapper around the most used functions in $smcFunc
 * I'll change things while I go the way I like them :P
 */
class collections_functions
{
	private $_smcFunc;

	public function __construct ()
	{
		global $smcFunc;
		$this->_smcFunc = $smcFunc;
	}

	public function db_insert ($method = 'replace', $table, $columns, $data, $keys, $disable_trans = false, $connection = null)
	{
		$this->_smcFunc['db_insert'](
			$method,
			$table,
			$columns,
			$data,
			$keys,
			$disable_trans,
			$connection
		);
		return $this->db_insert_id($table, null, $connection);
	}
	public function db_insert_id ($table, $field = null, $connection = null)
	{
		return $this->_smcFunc['db_insert_id'](
			$table,
			$field,
			$connection
		);
	}
	public function db_query ($identifier, $db_string, $db_values, $connection = null)
	{
		return $this->_smcFunc['db_query'](
			$identifier,
			$db_string,
			$db_values,
			$connection
		);
	}
	public function db_fetch_assoc ($request)
	{
		return $this->_smcFunc['db_fetch_assoc']($request);
	}
	public function db_fetch_row ($request, $free = true)
	{
		$row = $this->_smcFunc['db_fetch_row']($request);
		if ($free)
			$this->db_free_result($request);

		return $row;
	}
	public function db_free_result ($request)
	{
		$this->_smcFunc['db_free_result']($request);
	}
	public function db_num_rows ($request)
	{
		return $this->_smcFunc['db_num_rows']($request);
	}

	/**
	 * A couple of new functions
	 */
	public function db_fetch_assoc_all ($request, $free = true, $id = null)
	{
		$rets = array();
		// This is needed to speed up things and hopefully avoid inconsistencies
		$row = $this->db_fetch_assoc($request);
		if ($id !== null && isset($row[$id]))
		{
			$rets[$row[$id]] = $row;
			while ($row = $this->db_fetch_assoc($request))
				$rets[$row[$id]] = $row;
		}
		else
		{
			$rets[] = $row;
			while ($row = $this->db_fetch_assoc($request))
				$rets[] = $row;
		}

		if ($free)
			$this->db_free_result($request);

		return $rets;
	}
	public function db_query_assoc ($identifier, $db_string, $db_values, $connection = null)
	{
		return $this->db_fetch_assoc_all($this->db_query($identifier, $db_string, $db_values, $connection));
	}
	public function db_query_row ($identifier, $db_string, $db_values, $free = true, $connection = null)
	{
		return $this->db_fetch_row($this->db_query($identifier, $db_string, $db_values, $connection), $free);
	}
}
?>