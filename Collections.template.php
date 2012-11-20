<?php
/**
 * Collections
 *
 * @package Collections
 * @author emanuele
 * @copyright 2012 emanuele
 * @license BSD
 *
 * The function template_short_list is derived from the
 * function template_show_list present in GenericList.template.php
 * of the SMF package:
 * @copyright 2011 Simple Machines
 *
 * @version 0.1.0
 */

function template_collection_page ()
{
	global $context, $txt;

	echo '
	<div id="collection_page">';

	foreach ($context['collection_lists'] as $list)
	 if (!empty($context[$list . '_template']))
		template_short_list($list);
	 else
		template_show_list($list);

	echo '
	</div>';
}

function template_short_list($list_id = null)
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Get a shortcut to the current list.
	$list_id = $list_id === null ? $context['default_list'] : $list_id;
	$cur_list = &$context[$list_id];

	// These are the main tabs that is used all around the template.
	if (!empty($settings['use_tabs']) && isset($cur_list['list_menu'], $cur_list['list_menu']['show_on']) && ($cur_list['list_menu']['show_on'] == 'both' || $cur_list['list_menu']['show_on'] == 'top'))
		template_create_list_menu($cur_list['list_menu'], 'top');

	if (isset($cur_list['form']))
		echo '
	<form action="', $cur_list['form']['href'], '" method="post"', empty($cur_list['form']['name']) ? '' : ' name="' . $cur_list['form']['name'] . '" id="' . $cur_list['form']['name'] . '"', ' accept-charset="', $context['character_set'], '">
		<div class="generic_list short_list short_list_', $list_id, '">';

	// Show the title of the table (if any).
	if (!empty($cur_list['title']))
		echo '
			<div class="title_bar clear_right">
				<h3 class="titlebg">
					', $cur_list['title'], '
				</h3>
			</div>';
	// This is for the old style menu with the arrows "> Test | Test 1"
	if (empty($settings['use_tabs']) && isset($cur_list['list_menu'], $cur_list['list_menu']['show_on']) && ($cur_list['list_menu']['show_on'] == 'both' || $cur_list['list_menu']['show_on'] == 'top'))
		template_create_list_menu($cur_list['list_menu'], 'top');

	if (isset($cur_list['additional_rows']['top_of_list']))
		template_additional_rows('top_of_list', $cur_list);

	if (isset($cur_list['additional_rows']['after_title']))
	{
		echo '
			<div class="information flow_hidden">';
		template_additional_rows('after_title', $cur_list);
		echo '
			</div>';
	}

	if (!empty($cur_list['items_per_page']) || isset($cur_list['additional_rows']['bottom_of_list']))
	{
		echo '
			<div class="flow_auto">';

		// Show the page index (if this list doesn't intend to show all items).
		if (!empty($cur_list['items_per_page']))
			echo '
				<div class="floatleft">
					<div class="pagesection">', $txt['pages'], ': ', $cur_list['page_index'], '</div>
				</div>';

		if (isset($cur_list['additional_rows']['above_column_headers']))
		{
			echo '
				<div class="floatright">';

			template_additional_rows('above_column_headers', $cur_list);

			echo '
				</div>';
		}

		echo '
			</div>';
	}

	echo '
			<table class="table_grid" cellspacing="0" width="', !empty($cur_list['width']) ? $cur_list['width'] : '100%', '">';

	echo '
			<tbody>';

	// Show a nice message informing there are no items in this list.
	if (empty($cur_list['rows']) && !empty($cur_list['no_items_label']))
		echo '
				<tr>
					<td class="windowbg" colspan="', $cur_list['num_columns'], '" align="', !empty($cur_list['no_items_align']) ? $cur_list['no_items_align'] : 'center', '"><div class="padding">', $cur_list['no_items_label'], '</div></td>
				</tr>';

	// Show the list rows.
	elseif (!empty($cur_list['rows']))
	{
		$alternate = false;
		foreach ($cur_list['rows'] as $id => $row)
		{
			echo '
				<tr class="windowbg', $alternate ? '2' : '', '" id="list_', $list_id, '_', $id, '">';

			$tops = '';
			$sides = '';
			$c_sides = 0;
			$defaults = '';

			foreach ($cur_list['headers'] as $key => $val)
				$id_headers[$val['id']] = $val;
			foreach ($row as $key => $row_data)
			{
				if (empty($row_data['style']) || $row_data['style'] == 'default')
				{
					$defaults .= '
					<tr>
						<td' . (empty($id_headers[$key]['class']) ? '' : ' class="' . $id_headers[$key]['class'] . '"') . (empty($id_headers[$key]['style']) ? '' : ' style="' . $id_headers[$key]['style'] . '"') . '>' . $id_headers[$key]['label'] . '</td>' . '
						<td' . (empty($row_data['class']) ? '' : ' class="' . $row_data['class'] . '"') . '>' . $row_data['value'] . '</td>
						</tr>';
				}
				elseif ($row_data['style'] == 'top')
					$tops .= '
					<tr>
						<td{cols}' . (empty($row_data['class']) ? '' : ' class="' . $row_data['class'] . '"') . '><strong>' . $row_data['value'] . '</strong></td>
					</tr>';
				elseif ($row_data['style'] == 'side')
				{
					$c_sides++;
					$sides .= '
					<div' . (empty($row_data['class']) ? '' : ' class="' . $row_data['class'] . '"') . '>' . $row_data['value'] . '</div>';
				}
			}

			if (!empty($tops))
				$tops = str_replace('{cols}', ' colspan="' . (2 + $c_sides) . '"', $tops);

			echo '
					<td>
						<table style="width:100%" class="coll_short_tmpl">' . $tops . '
							<tr>
								<td>' . $sides . '</td>
								<td style="width:100%">
									<table style="width:100%">' . $defaults . '</table>
								</td>
							</tr>
						</table>
					</td>';

			echo '
				</tr>';

			$alternate = !$alternate;
		}
	}

	echo '
			</tbody>
			</table>';

	if (!empty($cur_list['items_per_page']) || isset($cur_list['additional_rows']['below_table_data']) || isset($cur_list['additional_rows']['bottom_of_list']))
	{
		echo '
			<div class="flow_auto">';

		// Show the page index (if this list doesn't intend to show all items).
		if (!empty($cur_list['items_per_page']))
			echo '
				<div class="floatleft">
					<div class="pagesection">', $txt['pages'], ': ', $cur_list['page_index'], '</div>
				</div>';

		if (isset($cur_list['additional_rows']['below_table_data']))
		{
			echo '
				<div class="floatright">';

			template_additional_rows('below_table_data', $cur_list);

			echo '
				</div>';
		}

		if (isset($cur_list['additional_rows']['bottom_of_list']))
		{
			echo '
				<div class="floatright">';

			template_additional_rows('bottom_of_list', $cur_list);

			echo '
				</div>';
		}

		echo '
			</div>';
	}

	if (isset($cur_list['form']))
	{
		foreach ($cur_list['form']['hidden_fields'] as $name => $value)
			echo '
			<input type="hidden" name="', $name, '" value="', $value, '" />';

		echo '
		</div>
	</form>';
	}

	// Tabs at the bottom.  Usually bottom alligned.
	if (!empty($settings['use_tabs']) && isset($cur_list['list_menu'], $cur_list['list_menu']['show_on']) && ($cur_list['list_menu']['show_on'] == 'both' || $cur_list['list_menu']['show_on'] == 'bottom'))
		template_create_list_menu($cur_list['list_menu'], 'bottom');

	if (isset($cur_list['javascript']))
		echo '
	<script type="text/javascript"><!-- // --><![CDATA[
		', $cur_list['javascript'], '
	// ]]></script>';
}

?>