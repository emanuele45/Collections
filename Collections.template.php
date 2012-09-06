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

function template_collection_page ()
{
	global $context, $txt;

	echo '
	<div id="collection_page">';

	foreach ($context['collection_lists'] as $list)
		template_show_list($list);

	echo '
	</div>';
}

?>