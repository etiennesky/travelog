<?php

/*
	Plugin: Travelog
	Component: Location manager
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
	require_once('wp-config.php');

	
// ### Get information from the database ###	
	$categories = Travelog::get_categories();
	$trips = Travelog::get_trips();
	

?>	

<?php get_header(); ?>
	  	<style>
  		.blockToggle {
			padding: 0 2px 0 2px;
			background: #eee;
			color: #000;
			font-size: 9px;
			border: 1px solid #888;
			text-align: center;
			font-weight: bold;
			float:right;
			margin-right:-5px;
			width: 0.8em;
		}
		.blockToggle:hover {
			cursor: pointer;
		}
  	</style>
	
	
	<div id="travelog_explorer">
		<h2>Travelog Explorer</h2>
		<script type="text/javascript" src="<?php bloginfo('url');?>/wp-content/plugins/travelog/mapfunction.js"></script>
		<form method="post" id="travelogExplorerForm">
			<fieldset>
				<legend>Search Travelog</legend>
				Name: <input type="text" name="locationSearchQuery" id="locationSearchQuery" size="20" value="<?php echo $show_search;?>"/>
				&nbsp;&nbsp;&nbsp;&nbsp;Show: <select name="showNumResults" id="showNumResults">
						<option value="" >All</option>
						<option value="5">5</option>
						<option value="10" selected="selected">10</option>
						<option value="20">20</option>
						<option value="50">50</option>
				</select>
				<p>Category:
				<select name="showCategory" id="showCategory">
					<option value="">All</option>
					   <?php foreach ($categories as $category) {
							echo "<option value=\"$category\"";
							if($_POST['show_category'] == $category) echo ' selected="selected"';
							echo ">$category</option>";
						} ?>
				</select>&nbsp;&nbsp;&nbsp;&nbsp;Sort by:
				<select name="showOrder" id="showOrder">
					<option value="id">ID#</option>
					<option value="name">Name</option>
					<option value="recent">Recently visited</option>
					<option value="visits">Most visited</option>
					<option value="posts_from">Most posted from</option>
				</select>
				</p>
			</fieldset>
				<div id="mapContainer">
					<?php echo Travelog::embed_map(array('height'=>300,'width'=>300,'show_types'=>1)); ?>
				</div>
				<div style="float:left;">
				<fieldset id="selectLocations" >
					<legend>Results</legend>
					<table id="locationResults">
						<thead>
							<th scope="col"><span id="locationsToggle" class="blockToggle" onclick="BlockToggle('travelogLocationResults', 'locationsToggle')" title="Show/hide results">&#150;</span>Locations (<span id="numLocationResults"></span>)</th>
						</thead>
						<tbody>
							<tr><td><ul id="travelogLocationResults">
							</ul></td></tr>
						</tbody>
						<tfoot>
							<th><small style="text-align:center;"><a href="javascript:dataForm.listMappedLocations();" title="List Mapped Locations">List mapped</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="javascript:dataForm.mapCurrent()" title="Map all listed locations">Map listed</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="javascript: dataForm.clearLocations()" title="Clear map">Clear map</a></small></th>
						</tfoot>
					</table>
					<table id="tripResults">
						<thead>
							<th scope="col"><span id="tripsToggle" class="blockToggle" onclick="BlockToggle('travelogTripResults', 'tripsToggle')" title="Show/hide results">&#150;</span>Trips (<span id="numTripResults"></span>)</th>
						</thead>
						<tbody>
							<tr><td><ul id="travelogTripResults">
							</ul></td></tr>
						</tbody>
						<tfoot>
							<th><small style="text-align:center;"><a href="javascript:dataForm.mapCurrentTrips()" title="Map all trips">Map all</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="javascript: dataForm.clearTrips()" title="Remove all trips">Remove all</a></small></th>
						</tfoot>
					</table>
				</fieldset>
				</div>
		</form>
		<script type="text/javascript">
			dataForm = new TravelogDataForm('dataForm', 'travelogExplorerForm', maps[0], 'renderResults(this)', 'travelogResultsList', 'tablesummary', 'showNumResults', 'locationSearchQuery', 'showCategory', 'showOrder', '');
			maps[0].dataForm = dataForm;
			// Set the handler function
			handler = 'dataForm.render';
			isMapEnabled = true;
			dataForm.doSearch();
			
			function renderResults(obj) {
				// Clear existing results
				var locationRender = document.getElementById('travelogLocationResults');
				var tripRender = document.getElementById('travelogTripResults');
				
				// Render locations
				obj.emptyRenderer(locationRender);
				var shown = 0;
				if(getObjLength(lastResults.locations) > 0) {
					for (tLocationKey in lastResults.locations) {
						var tLocation = tLocations[lastResults.locations[tLocationKey]];
						var isMapped = false;
						var inTrip = false;
						if(obj.linkedMap.contents.locations[tLocation.ID] == 't') inTrip = true;
						if(obj.linkedMap.contents.locations[tLocation.ID] == 'l' || obj.linkedMap.contents.locations[tLocation.ID] == 't') isMapped = true;
				
						var rowData = '<input type="checkbox" class="locationMapToggle" id="l'+tLocation.ID+'" value="0" title="Map this location" onclick="'+obj.myName+'.locationBoxClicked(this)"';
						if(isMapped) rowData += ' checked="checked"';
						if(inTrip || !isMapEnabled) rowData += ' disabled="disabled"';
						rowData += '/> ';
						rowData += tLocation.name;
						locationRender.appendChild(document.createElement('li'));
						locationRender.lastChild.innerHTML = rowData;
						if(shown%2==1) locationRender.lastChild.className = "alternate";
						shown++;
					}
				}else{
					locationRender.appendChild(document.createElement('li'));
					locationRender.lastChild.innerHTML = 'No locations match your search';
					locationRender.lastChild.style.padding = '4px';
				}
				var numLocationResults = document.getElementById('numLocationResults');
				numLocationResults.innerHTML = shown;
				
				// Render trips
				obj.emptyRenderer(tripRender);
				var shown = 0;
				if(getObjLength(lastResults.trips) > 0) {
					for (tTripKey in lastResults.trips) {
						var tTrip = tTrips[lastResults.trips[tTripKey]];
						var isMapped = false;
						if(obj.linkedMap.contents.trips[tTrip.ID] === true) isMapped = true;
				
						var rowData = '<input type="checkbox" class="tripMapToggle" id="t'+tTrip.ID+'" value="0" title="Map this trip" onclick="'+obj.myName+'.tripBoxClicked(this)"';
						if(isMapped) rowData += ' checked="checked"';
						rowData += '/> ';
						rowData += tTrip.name;
						tripRender.appendChild(document.createElement('li'));
						tripRender.lastChild.innerHTML = rowData;
						if(shown%2==1) tripRender.lastChild.className = "alternate";
						shown++;
					}
				}else{
					tripRender.appendChild(document.createElement('li'));
					tripRender.lastChild.innerHTML = 'No trips match your search';
					tripRender.lastChild.style.padding = '4px';
				}
				var numTripResults = document.getElementById('numTripResults');
				numTripResults.innerHTML = shown;
			}
			
			function BlockToggle(objId, togId, display) {
				var o = document.getElementById(objId), t = document.getElementById(togId);
				if (o.style.display == 'none') {
				if (!display) display = 'block';
				if (display == 'table-row') {   /* No table-row for IE */
					var agent = navigator.userAgent.toLowerCase();
					if (agent.indexOf('msie') >= 0 && agent.indexOf('opera') < 0) display = 'block';
				}
				o.style.display = display;
				t.innerHTML = '&#150;';
				} else {
				o.style.display = 'none';
				t.innerHTML = '+';
				}
			}
		</script>
	</div>
		
<?php get_sidebar(); ?>

<?php get_footer(); ?>		
		