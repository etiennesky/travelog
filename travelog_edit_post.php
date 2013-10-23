<?php

/*
	Plugin: Travelog
	Component: Post advanced editing form
	Author: Shane Warner
	Author URI: http://www.sublimity.ca/
	Updated: 7/23/05

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

*/
    global $postdata;
    global $post_ID;
    
	//Get information from the database
	$locations = Travelog::get_locations();
	$categories = Travelog::get_categories();
	$post_location_id = get_post_meta($post_ID, '_travelog_location_id', true);
?>
			
	<fieldset id="travelog" class="dbx-box">
		<h3 class="dbx-handle">Travelog Location</h3>
		<!--<legend><?= __('Travelog Location', DOMAIN) ?></legend> //-->
		<div class="dbx-content">
			Location: <input size="15" type="text" value="<?php if($post_location_id) echo $locations[$post_location_id]->name;?>" name="travelog_name" id="travelogLocationSelector" />
			<input size="5" type="hidden" value="<?php if($post_location_id) echo $post_location_id;?>" id="travelog_location_id" name="travelog_location_id"/>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" name="travelog_create_new_location" value="1" onclick="var new_details = document.getElementById('new_location'); var add_dates = document.getElementById('add_dates'); if(this.checked){new_details.style.display = 'block'; add_dates.style.display = 'none';document.getElementById('travelog_location_id').value='';}else{new_details.style.display = 'none'; add_dates.style.display = 'block';}" /> Create new location
			
			<!-- Autocomplete code adapted from Gallery, http://gallery.menalto.com/ //-->
			<script type="text/javascript" src="<?php bloginfo('wpurl');?>/wp-content/plugins/travelog/mapfunction.js"></script>
			<script type="text/javascript">
				var XMLAddress = '<?=get_settings("siteurl")?>/wp-content/plugins/travelog/travelog_xml.php';
				var locationChooser = new TravelogDataForm('locationChooser', 'post', null, '', '', 'autocomplete', '', 'travelogLocationSelector', '', '', '');
				locationChooser.ac.action = 'setTravelogLocation';
				
				function setTravelogLocation(locationID) {
					document.getElementById('travelogLocationSelector').value = tLocations[locationID].name; 
					document.getElementById('travelog_location_id').value = locationID;
				}
			</script>
			<style type="text/css">
			<!--
				.acBackground {
					border: 1px solid #ccc;
					border-top-style: none;
					margin: 0;
				}
				
				.acHighlight {
					text-decoration: underline;
				}
				
				.acNotSelected {
					background-color: #eee;
					padding: 3px 4px 3px 17px;
				}
				
				.acSelected {
					background-color: #9cf;
					padding: 3px 4px 3px 17px;
					cursor: pointer;
				}
			-->
			</style>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" name="travelog_unset_location" value="1" /> Clear location information
			<div id="add_dates" style="margin-top: 5px;margin-left: 10px;" >Add visit to this location <input type="text" name="travelog_add_visit" id="travelog_add_visit" size="18" value="yyyy/mm/dd hh:mm" onfocus="if(this.value == 'yyyy/mm/dd hh:mm') this.value = '';"/>&nbsp;&nbsp;<input type="checkbox" name="travelog_add_date_today" value="1" onclick="var new_date=document.getElementById('travelog_add_visit'); if(this.checked){new_date.value ='<?=date('Y/m/d H:m'); ?>';}else{if(new_date.value == '<?=date('Y/m/d H:m'); ?>'){new_date.value ='';}}" /> Today</div>
			<div id="new_location" style="display: none;margin-top: 5px;">
				<table>
					<tr class="alternate">
						<td style="text-align: right;"><label for="travelog_category"><?=__('Category', DOMAIN)?></label></td>
							<td><select name="travelog_category">
								<option value="Default">-- Select --</option>';
								<?php foreach ($categories as $key => $category) {
									echo "<option value='$category'>$category</option>";
								} ?>
							</select></td>
						<td style="text-align: right;"><label for="travelog_dates_visited"><?= __('Date Visited', DOMAIN) ?></label></td><td colspan="3"><input size="18" type="text" name="travelog_dates_visited" id="travelog_dates_visited" value="yyyy/mm/dd hh:mm" onfocus="if(this.value == 'yyyy/mm/dd hh:mm') this.value = '';"/>&nbsp;&nbsp;<input type="checkbox" name="travelog_add_date_today" value="1" onclick="var new_date=document.getElementById('travelog_dates_visited'); if(this.checked){new_date.value ='<?=date('Y/m/d H:m'); ?>';}else{if(new_date.value == '<?=date('Y/m/d H:m'); ?>'){new_date.value ='';}}" /> Today</td>	
					</tr>
					<tr>
						<td style="text-align: right;"><label for="travelog_latitude"><?= __('Latitude', DOMAIN) ?></label></td><td><input size="10" type="text" name="travelog_latitude" id="travelog_latitude" /></td>
						<td style="text-align: right;"><label for="travelog_longitude"><?= __('Longitude', DOMAIN) ?></label></td><td><input size="10" type="text" name="travelog_longitude" id="travelog_longitude" /></td>
						<td style="text-align: right;"><label for="travelog_elevation"><?= __('Elevation', DOMAIN) ?> (<?= get_option('travelog_elevation_unit') ?>)</label></td><td colspan="3"><input size="10" type="text" name="travelog_elevation" id="travelog_elevation" /></td>
					</tr>
					<tr class="alternate">
						<td style="text-align: right;"><label for="travelog_address"><?= __('Address', DOMAIN) ?></label></td><td><input size="15" type="text" value="" name="travelog_address" id="travelog_address" /></td>
						<td style="text-align: right;"><label for="travelog_city"><?= __('City', DOMAIN) ?></label></td><td><input size="15" type="text" value="" name="travelog_city" id="travelog_city" /></td>
						<td style="text-align: right;"><label for="travelog_state"><?= __('State', DOMAIN) ?></label></td><td><input size="10" type="text" value="" name="travelog_state" id="travelog_state" />&nbsp;&nbsp;&nbsp;
						<label for="travelog_country" style="text-align: right;"><?= __('Country', DOMAIN) ?></label>&nbsp;<input size="10" type="text" value="" name="travelog_country" id="travelog_country" /></td>
					</tr>
					
					<tr>
						<td style="text-align: right;"><label for="travelog_description"><?= __('Description', DOMAIN) ?></label></td><td colspan="5" rowspan="2"><textarea cols="80" rows="3" name="travelog_description" id="travelog_description"></textarea></td>
					</tr>
					<tr>
						<td>&nbsp;</td>
					</tr>
				</table>
			</div>
		</div>
	</fieldset>