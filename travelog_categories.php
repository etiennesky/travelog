<?php

/*
	Plugin: Travelog
	Component: Category manager
	Author: Shane Warner
	Author URI: http://www.sublimity.ca/

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

*/
     

		//Get information from the database
		$categories = Travelog::get_categories();
		$query_sql = "SELECT category, count(category) as locations FROM ".DB_TABLE." GROUP BY category";
		$results = $wpdb->get_results($query_sql);
		$locations_per_cat = array();
		if(count($results) > 0) {
			foreach($results as $result) {
				$locations_per_cat[$result->category] = $result->locations;
			}
		}
		
    if(isset($_POST['update'])) {	
		//Update dates_visited list if needed
		$i=0;
		foreach($_POST as $key => $value) {
			if($remove_category = stristr($key, 'remove_category_')) $delete_categories[$i++] = str_replace('remove_category_', "", $remove_category);
		}
		
		if(count($delete_categories) >=1 ) {
			foreach($delete_categories as $key => $delete_category) {
				unset($categories[$delete_category]);
			}
		}
			
		// Add new date visited if one was passed
		if($_POST['new_category'] != "") {
			$categories = array_merge($categories, $_POST['new_category']);
		}
		
		asort($categories); //sort the dates so the most recent is always at the end of the list
		
		update_option('travelog_categories', $categories);
	}
	
	// Show Travelog Manager submeny
	Travelog::adminheader();
?>

	<div class="wrap">
		<p style="float: right;margin-top: 2px;"><a href="options-general.php?page=travelog.php" >Edit Travelog Options</a> &raquo;&nbsp;&nbsp;&nbsp;&nbsp;</p>
		<h2><?= __('Travelog Categories', DOMAIN) ?></h2>
		<form method="post">
			<table cellspacing="2" cellpadding="5">
				<tr valign="top">
					<th scope="col"><?= __('Category', DOMAIN) ?></th>
					<th scope="col"><?= __('# Locations', DOMAIN) ?></th>
					<th scope="col"><?= __('Delete?', DOMAIN) ?></th>
				</tr>
						<?php
						$categories = Travelog::get_categories();
						if(is_array($categories) && $categories[0] != '') {
							foreach ($categories as $id => $category) {
								$alternate = $alternate == ''? ' class="alternate"' : '';
								if(array_key_exists($category, $locations_per_cat)) {
									$num_locations = $locations_per_cat[$category];
								}else{
									$num_locations = "0";
								}
								echo "<tr $alternate><td>$category</td><td style='text-align: center;'>$num_locations</td><td style='text-align: center;'><input type='checkbox' name='remove_category_$id' /></td></tr>";
							}
						}else{
							echo '<tr><td colspan="2"><strong>There are no categories in your Travelog</strong></td></tr>';
						}
						
						?>
			</table>
			<p>Add Category: <input type="text" name="new_category" value="" size="12" /></p>
			<div class="submit"><input type="submit" name="update" value="<?= __('Update Categories', DOMAIN) ?> &raquo;" /></div>
		</form>
	</div>