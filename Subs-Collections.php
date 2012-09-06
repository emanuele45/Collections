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
		'edit_elements' => 'collections_editElements',
		'move_element' => 'collections_moveElement',
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

	$current_collection = isset($_GET['collection']) ? (int) $_GET['collection'] : 0;
	$current_move = isset($_REQUEST['move']) ? $_REQUEST['move'] : 0;

	if (empty($current_move) || !in_array($current_move, $allowedMoves) || empty($current_collection))
		return collections_listCollections();

	checkSession('get');

	$request = $smcFunc['db_query']('', '
		SELECT ol1.position as current_position, ol2.position as next_position, ol2.id_collection
		FROM {db_prefix}collections_list as ol1
		LEFT JOIN {db_prefix}collections_list as ol2 ON (ol2.position = (ol1.position + {int:movement}))
		WHERE ol1.id_collection = {int:current_collection}',
		array(
			'current_collection' => $current_collection,
			'movement' => $current_move == 'down' ? 1 : -1,
		)
	);
	list($current_position, $next_position, $swap_collection) = $smcFunc['db_fetch_row']($request);

	if (empty($next_position))
		return collections_listCollections();

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}collections_list
		SET
			position = {int:next_position}
		WHERE id_collection = {int:current_collection}',
		array(
			'next_position' => $next_position,
			'current_collection' => $current_collection,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}collections_list
		SET
			position = {int:current_position}
		WHERE id_collection = {int:swap_collection}',
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

	if (isset($_POST['element_delete']))
	{
		collections_deleteElement($_POST['element_delete']);
	}

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
			'action' => array(
				'header' => array(
					'value' => '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" />',
					'style' => 'width: 6%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '
							<input type="checkbox" name="element_delete[]" value="%1$d" class="input_check" /><br />
							[<a href="' . $scripturl . '?action=admin;area=collections;sa=edit_elements;elem=%1$d">' . $txt['collections_edit'] . '</a>]<br />
							[<a href="' . $scripturl . '?action=admin;area=collections;sa=move_element;move=up;elem=%1$d;' . $context['session_var'] . '=' . $context['session_id'] . '"><img src="' . $settings['images_url'] . '/sort_up.gif" alt="" /></a>
							<a href="' . $scripturl . '?action=admin;area=collections;sa=move_element;move=down;elem=%1$d;' . $context['session_var'] . '=' . $context['session_id'] . '"><img src="' . $settings['images_url'] . '/sort_down.gif" alt="" /></a>]',
						'params' => array(
							'id_element' => false,
						),
					),
					'style' => 'text-align: center;',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=collections;sa=edit_elements',
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '
					<input type="submit" name="go" value="' . $txt['delete'] . '" onclick="var sel = document.getElementById(\'req_action\'); if (sel.value != 0 &amp;&amp; sel.value != \'reason\' &amp;&amp; !confirm(\'' . $txt['quickmod_confirm'] . '\')) return false;" class="button_submit" />
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

function collections_editElements ()
{
	global $context, $smcFunc, $txt, $sourcedir, $scripturl;

	loadTemplate('Collections');
	loadLanguage('Collections/Collections');

	$current_elem = isset($_GET['elem']) ? (int) $_GET['elem'] : '';
	if (!empty($current_elem) && isset($_POST['delete_element']))
	{
		checkSession();
		collections_deleteElement($current_elem);
		redirectexit('action=admin;area=collections;sa=elements');
	}
	elseif (isset($_POST['element_delete']))
	{
		checkSession();
		collections_deleteElement($_POST['element_delete']);
		redirectexit('action=admin;area=collections;sa=elements');
	}

	$name = isset($_POST['name']) ? trim($smcFunc['htmlspecialchars']($_POST['name'])) : '';
	$desc = isset($_POST['description']) ? trim($smcFunc['htmlspecialchars']($_POST['description'])) : '';
	$name_error = isset($_POST['name']) && empty($name) || $smcFunc['strlen']($name) > 255;
	$desc_error = isset($_POST['description']) && empty($desc);

	if (isset($_POST['save']) && empty($name_error) && empty($desc_error))
	{
		collections_saveElement($current_elem, $name, $desc);
		redirectexit('action=admin;area=collections;sa=elements');
	}

	if (empty($name) && empty($desc) && !empty($current_elem))
	{
		$request = $smcFunc['db_query']('', '
			SELECT name, description
			FROM {db_prefix}collections_elements
			WHERE id_element = {int:element}',
			array(
				'element' => $current_elem
			)
		);
		list($name, $desc) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// We're going to want this for making our list.
	require_once($sourcedir . '/Subs-List.php');

	$listOptions = array(
		'id' => 'collections_admin_list',
		'title' => $txt['collections_edit_element'],
		'width' => '100%',
		'get_items' => array(
			'function' => create_function('', '
				global $txt;

				return array(
					array(
						\'id\' => \'name\',
						\'value\' => $txt[\'collections_field_name\'],
						\'input\' => \'' . (empty($name) ? '' : $name) . '\',
						\'error\' => \'' . (empty($name_error) ? '' : ' error') . '\'
					),
					array(
						\'id\' => \'description\',
						\'value\' => $txt[\'collections_field_description\'],
						\'input\' => \'' . (empty($desc) ? '' : $desc) . '\',
						\'error\' => \'' . (empty($desc_error) ? '' : ' error') . '\'
					)
				);
			'),
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['collections_field'],
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
					'sprintf' => array(
						'format' => '<input type="text" name="%1$s" value="%2$s" class="input_text%3$s" />',
						'params' => array(
							'id' => false,
							'input' => false,
							'error' => false,
						),
					),
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=collections;sa=edit_elements' . (empty($current_elem) ? '' : ';elem=' . $current_elem),
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

function collections_saveElement($id, $name, $desc)
{
	global $smcFunc;

	if (empty($id))
	{
		$request = $smcFunc['db_query']('', '
			SELECT MAX(position)
			FROM {db_prefix}collections_elements',
			array()
		);
		list($last_position) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		$last_position++;

		$smcFunc['db_insert']('',
			'{db_prefix}collections_elements',
			array(
				'name' => 'string-255', 'description' => 'string', 'position' => 'int'
			),
			array(
				$name, $desc, $last_position
			),
			array('id_element')
		);
	}
	else
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}collections_elements
			SET
				name = {string:name},
				description = {string:desc}
			WHERE id_element = {int:element}',
			array(
				'name' => $name,
				'desc' => $desc,
				'element' => $id
			)
		);
}

function collections_deleteElement($elements)
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

	if (!empty($_POST['collection_delete']))
	{
		checkSession();
		collections_deleteCollections($_POST['collection_delete']);
	}
	if (isset($_POST['new']))
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
					'value' => '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" />',
					'style' => 'width: 6%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '
							<input type="checkbox" name="collection_delete[]" value="%1$d" class="input_check" /><br />
							[<a href="' . $scripturl . '?action=admin;area=collections;sa=edit_collection;collection=%1$d">' . $txt['collections_edit'] . '</a>]<br />
							[<a href="' . $scripturl . '?action=admin;area=collections;sa=move_collection;move=up;collection=%1$d;' . $context['session_var'] . '=' . $context['session_id'] . '"><img src="' . $settings['images_url'] . '/sort_up.gif" alt="" /></a>
							<a href="' . $scripturl . '?action=admin;area=collections;sa=move_collection;move=down;collection=%1$d;' . $context['session_var'] . '=' . $context['session_id'] . '"><img src="' . $settings['images_url'] . '/sort_down.gif" alt="" /></a>]',
						'params' => array(
							'id_collection' => false,
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
					<input type="submit" name="go" value="' . $txt['delete'] . '" onclick="var sel = document.getElementById(\'req_action\'); if (sel.value != 0 &amp;&amp; sel.value != \'reason\' &amp;&amp; !confirm(\'' . $txt['quickmod_confirm'] . '\')) return false;" class="button_submit" />
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
		SELECT id_collection, name, description
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
		WHERE id_collection IN ({array_int:collections})',
		array(
			'collections' => $collection_ids
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}collections_list
		WHERE id_collection IN ({array_int:collections})',
		array(
			'collections' => $collection_ids
		)
	);
}

function collections_editCollection ()
{
	global $context, $smcFunc, $sourcedir, $txt, $scripturl;

// 	$collection_data['page'] = isset($_GET['page']) ? (int) $_GET['page'] : '';
	$collection_data['collection_id'] = isset($_GET['collection']) ? (int) $_GET['collection'] : '';

	if (isset($_REQUEST['save']))
	{
		checkSession();
		$collection_data['name'] = isset($_POST['collection_edit']['name']) ? trim($smcFunc['htmlspecialchars']($_POST['collection_edit']['name'])) : '';
		$collection_data['description'] = isset($_POST['collection_edit']['description']) ? trim($smcFunc['htmlspecialchars']($_POST['collection_edit']['description'])) : '';
		$collection_data['details'] = array();
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
			'function' => 'list_getElements',
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

						if ($smcFunc[\'strlen\']($data[\'value\']) > 50)
							return \'<textarea rows="10" cols="30" name="collection_edit[\' . $data[\'id_element\'] . \']">\' . $data[\'value\'] . \'</textarea>\';
						else
							return \'<input onkeyup="checkSize(this);" type="text" name="collection_edit[\' . $data[\'id_element\'] . \']" value="\' . $data[\'value\'] . \'" class="input_text" />\';'
					),
					'style' => 'text-align: center;',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=collections;sa=save_collection' . (!empty($collection_data['collection_id']) ? ';collection=' . $collection_data['collection_id'] : ''),
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '
					<input type="submit" name="save" value="' . $txt['save'] . '" class="button_submit" />
					<script type="text/javascript"><!-- // --><![CDATA[
						function checkSize (elem)
						{
							if (elem.value.length > 50)
							{
								var name = elem.name;
								var value = elem.value;
								var container = elem.parentNode;
								elem.name = "not_used";
								elem.style.display = "none";
								var textarea = document.createElement("textarea");
								textarea.name = name;
								textarea.rows = 10;
								textarea.cols = 30;
								textarea.innerHTML = value;
								container.appendChild(textarea);
								textarea.focus();
							}
						}
					// ]]></script>',
				'align' => 'right',
			),
		),
	);

	// Create the request list.
	createList($listOptions);

	$context['default_list'] = 'collections_admin_edit';
	$context['sub_template'] = 'show_list';
}

function list_getElements ($start, $items, $sort, $collection_id)
{
	global $txt, $smcFunc;

	$collection_name = '';
	$collection_desc = '';

	if (isset($_REQUEST['save']))
	{
		$collection_name = !empty($_POST['collection_edit']['name']) ? $smcFunc['htmlspecialchars']($_POST['collection_edit']['name']) : '';
		$collection_desc = !empty($_POST['collection_edit']['description']) ? $smcFunc['htmlspecialchars']($_POST['collection_edit']['description']) : '';
		foreach ($_POST['collection_edit'] as $key => $value)
			$entries[$key] = $value;
	}
	else
	{
		$get_entries = collection_getEntries($collection_id, 'id_element');
		foreach ($get_entries as $key => $entry)
			$entries[$key] = $entry['value'];

		if (!empty($collection_id))
		{
			$request = $smcFunc['db_query']('', '
				SELECT name, description
				FROM {db_prefix}collections_list
				WHERE id_collection = {int:collection}
				ORDER BY position',
				array(
					'collection' => $collection_id
				)
			);
			list($collection_name, $collection_desc) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}
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
	);

	$elements = collection_getElements();
	foreach ($elements as $element)
		$collection_details[] = array(
			'id_element' => $element['id_element'],
			'name' => $element['name'],
			'description' => $element['description'],
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
				$data['name'], $data['description'], 0, $last_pos
			),
			array(
				'id_collection'
			)
		);
		$data['collection_id'] = $smcFunc['db_insert_id']('{db_prefix}collections_list');

	}
	// Just an update
	else
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}collections_list
			SET
				name = {string:upd_name},
				description = {string:upd_desc},
				page = {string:upd_page}
			WHERE id_collection = {int:collection}',
			array(
				'upd_name' => $data['name'],
				'upd_desc' => $data['description'],
				'upd_page' => 0,
				'collection' => $data['collection_id'],
			)
		);
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}collections_entries
			WHERE id_collection = {int:collection}',
			array(
				'collection' => $data['collection_id']
			)
		);
	}

	$inserts = array();
	foreach ($data['details'] as $id_element => $value)
		if (is_numeric($id_element))
			$inserts[] = array(
				$data['collection_id'],
				$id_element,
				$value,
			);

	$smcFunc['db_insert']('',
		'{db_prefix}collections_entries',
		array(
			'id_collection' => 'int', 'id_element' => 'int', 'value' => 'string'
		),
		$inserts,
		array(
			'id_entry'
		)
	);
}

function collections_show_collection ()
{
	global $smcFunc, $context, $sourcedir, $txt;

	loadTemplate('Collections');
	loadLanguage('Collections/Collections');
	$context['sub_template'] = 'collection_page';

	$request = $smcFunc['db_query']('', '
		SELECT id_collection, name, description
		FROM {db_prefix}collections_list
		ORDER BY position',
		array(
		)
	);

	// We're going to want this for making our list.
	require_once($sourcedir . '/Subs-List.php');

	$context['collection_lists'] = array();
	$context['page_collection_data'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$listOptions = array(
			'id' => 'collections_list_' . $row['id_collection'],
			'title' => $row['name'],
			'width' => '100%',
			'no_items_label' => $txt['collections_no_elements_found'],
			'get_items' => array(
				'function' => 'collection_getListEntries',
				'params' => array($row['id_collection'])
			),
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => '',
						'style' => 'display:none;',
					),
					'data' => array(
						'style' => 'width:33%;',
						'db' => 'name',
						'class' => 'windowbg',
					),
				),
				'value' => array(
					'header' => array(
						'value' => '',
						'style' => 'display:none',
					),
					'data' => array(
						'db' => 'value',
						'class' => 'windowbg2',
					),
				),
			),
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

		// Create the request list.
		createList($listOptions);

		$context['collection_lists'][] = 'collections_list_' . $row['id_collection'];
	}
	$smcFunc['db_free_result']($request);
}

function collection_getListEntries ($start, $items, $sort, $collection_id)
{
	return collection_getEntries($collection_id, 'id_element');
}

function collection_getEntries ($collections, $array_index = 'id_collection')
{
	global $smcFunc;

	if (empty($collections))
		return false;

	$collections = is_array($collections) ? $collections : array($collections);

	$request = $smcFunc['db_query']('', '
		SELECT el.name, en.value, en.id_collection, el.id_element
		FROM {db_prefix}collections_elements as el
		LEFT JOIN {db_prefix}collections_entries as en ON (el.id_element = en.id_element)
		WHERE en.id_collection IN ({array_int:id_collection})
			AND en.value != {string:empty}
		ORDER BY el.position',
		array(
			'id_collection' => $collections,
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
		SELECT id_element, name, description
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
?>