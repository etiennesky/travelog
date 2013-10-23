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
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="tools.php?page=travelog.php&amp;area=locations&amp;action=add" target="_blank">Create new location</a>
			
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
			<div id="add_dates" class="dbx-content" >Add visit to this location <input type="text" name="travelog_add_visit" id="travelog_add_visit" size="18" value="yyyy/mm/dd hh:mm" onfocus="if(this.value == 'yyyy/mm/dd hh:mm') this.value = '';"/>&nbsp;&nbsp;<input type="checkbox" name="travelog_add_date_today" value="1" onclick="var new_date=document.getElementById('travelog_add_visit'); if(this.checked){new_date.value ='<?=date('Y/m/d H:i'); ?>';}else{if(new_date.value == '<?=date('Y/m/d H:i'); ?>'){new_date.value ='';}}" /> Today</div>
		</div>
	</fieldset>