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
	
// ### Get information from the database ###
	require_once('../../../../../wp-config.php');
	
	$inTinyMCE = $_GET['tinymce'];
	
	$categories = Travelog::get_categories();
	$trips = Travelog::get_trips();
?>

<html xmlns='http://www.w3.org/1999/xhtml'>
	<head>
		<title>Insert From Travelog</title>
		
		<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
		<link rel='stylesheet' href='css/travelog.css' type='text/css' />
		<?php if($inTinyMCE) { ?><script language='javascript' type='text/javascript' src='../../tiny_mce_popup.js'></script><?php } ?>
		<script language='javascript' type='text/javascript' src='jscripts/functions.js'></script>
	</head>
	<body onload="setupPage();">		
		<script type="text/javascript" src="<?php bloginfo('url');?>/wp-content/plugins/travelog/mapfunction.js"></script>
		<script type="text/javascript">
			var XMLAddress = "<?php echo get_settings('siteurl');?>/wp-content/plugins/travelog/travelog_xml.php";
			isMapEnabled = true;
		</script>
		<form method="post" id="travelogMCEForm">
			<input type="hidden" id="inTinyMCE" value="<?php echo $_GET['tinymce'];?>" />
			<input type="hidden" id="callingForm" value="<?php echo $_GET['form'];?>" />
			<input type="hidden" id="callingField" value="<?php echo $_GET['field'];?>" />
			<input type="hidden" id="initType" value="<?php echo $_GET['type'];?>" />
			<div>
				<fieldset>
				<legend>Insert Type</legend>
					<input type="radio" name="mcetlInsertWhat" value="travelog" id="mcetlInsertWhat_travelog" checked="checked" onclick="if(this.checked) setTagType('link');">Travelog Link <em>(&lt;travelog&gt;)</em></input>
					<input type="radio" name="mcetlInsertWhat" value="travelogmap" id="mcetlInsertWhat_travelogmap" onclick="if(this.checked) setTagType('map');"/>Travelogmap <em>(&lt;!--travelogmap--&gt;)</em></input>
				</fieldset>
				<fieldset>
				<legend>Map Options</legend>
					<div id="mapOptions">
						<input type="hidden" name="defaultMapType" id="defaultMapType" value="<?php echo Travelog::map_type(get_option('travelog_googlemaps_view'),'url');?>" />
						<input type="hidden" name="defaultMapZoom" id="defaultMapZoom" value="<?php echo get_option('travelog_googlemaps_zoom');?>" />
						Map Type: <select name="mapType" id="mapType" onchange="maps[0].updateMap();">
							<option value="">Default</option>
							<option value="map">Map</option>
							<option value="satellite">Satellite</option>
							<option value="hybrid">Hybrid</option>
						</select>
						&nbsp;
						Zoom Level: <select name="zoomLevel" id="zoomLevel" onchange="maps[0].updateMap();">
							<option value="">Default</option>
							<option value="1">1</option>
							<option value="2">2</option>
							<option value="3">3</option>
							<option value="4">4</option>
							<option value="5">5</option>
							<option value="6">6</option>
							<option value="7">7</option>
							<option value="8">8</option>
							<option value="9">9</option>
							<option value="10">10</option>
							<option value="11">11</option>
							<option value="12">12</option>
							<option value="13">13</option>
							<option value="14">14</option>
							<option value="15">15</option>
							<option value="16">16</option>
							<option value="17">17</option>
						</select>&nbsp;
						<span id="travelogmapOptions1" style="display:none;">Controls: <select name="mapControls" id="mapControls" onchange="maps[0].updateMap();">
								<option value="large">Large</option>
								<option value="small">Small</option>
								<option value="zoom">Zoom</option>
							</select></span>
						<div id="traveloglinkOptions1" style="display:none;margin-top: 4px;">
							Link Text: <input type="text" name="linkText" id="linkText" value="" size="20" /> &nbsp;<input type="checkbox" id="linkTextUseName" name="linkTextUseName" value="1" onclick="var txt=document.getElementById('linkText');if(txt.disabled)txt.disabled=false;else txt.disabled=true;setLinkText();" />Use location name
						</div>
						<div id="travelogmapOptions2" style="display:none;margin-top: 4px;">
							Width: <input type="text" name="mapWidth" id="mapWidth" value="<?php echo get_option('travelog_googlemaps_width');?>" size="4"/>px&nbsp;&nbsp;
							Height: <input type="text" name="mapHeight" id="mapHeight" value="<?php echo get_option('travelog_googlemaps_height');?>" size="4"/>px&nbsp;&nbsp;
							<input type="checkbox" id="mapShowTypes" value="1" checked="checked" onclick="maps[0].updateMap();">Show types&nbsp;&nbsp;
							<input type="checkbox" id="mapShowScale" value="1" checked="checked" onclick="maps[0].updateMap();">Show Scale
						</div>
					</div>
				</fieldset>
				<fieldset>
					<legend>Search Travelog Locations</legend>
					<p>Name: <input type="text" name="locationSearchQuery" id="locationSearchQuery" size="20" value="<?php echo $show_search;?>"/>
					&nbsp;&nbsp;&nbsp;&nbsp;Show: <select name="showNumResults" id="showNumResults">
							<option value="" >All</option>
							<option value="5">5</option>
							<option value="10" selected="selected">10</option>
							<option value="20">20</option>
							<option value="50">50</option>
					</select></p>
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
						<option value="recent" <?php if($_POST['show_order'] == 'recent') echo 'selected="selected"';?>>Recently visited</option>
						<option value="visits" <?php if($_POST['show_order'] == 'visits') echo 'selected="selected"';?>>Most visited</option>
						<option value="posts_from" <?php if($_POST['show_order'] == 'posts_from') echo 'selected="selected"';?>>Most posted from</option>
					</select>
					</p>
				</fieldset>
				<fieldset>
					<legend>Select Travelog Data</legend>
					<div style="float:left;width:200px;">
						<table id="locationResults">
							<thead>
								<th scope="col"><span id="locationsToggle" class="blockToggle" onclick="BlockToggle('travelogLocationResults', 'locationsToggle')" title="Show/hide results">&#150;</span>Locations (<span id="numLocationResults"></span>)</th>
							</thead>
							<tbody>
								<tr><td><ul id="travelogLocationResults">
								</ul></td></tr>
							</tbody>
							<tfoot>
								<th><small><a href="javascript:dataForm.listMappedLocations();" title="List Mapped Locations">List mapped</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="javascript:dataForm.mapCurrent()" title="Map all listed locations">Map listed</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="javascript: dataForm.clearLocations()" title="Clear map">Clear map</a></small></th>
							</tfoot>
						</table>
						<table id="tripResults" style="width:100%;">
							<thead>
								<th scope="col"><span id="tripsToggle" class="blockToggle" onclick="BlockToggle('travelogTripResults', 'tripsToggle')" title="Show/hide results">&#150;</span>Trips (<span id="numTripResults"></span>)</th>
							</thead>
							<tbody>
								<tr><td><ul id="travelogTripResults">
								</ul></td></tr>
							</tbody>
							<tfoot>
								<th><small><a href="javascript:dataForm.mapCurrentTrips()" title="Map all trips">Map all</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="javascript: dataForm.clearTrips()" title="Remove all trips">Remove all</a></small>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</th>
							</tfoot>
						</table>
					</div>
					<div id="map" style="width:300px;height:300px;border:1px #999 solid;float:right;position:relative;margin-top:5px;">
						&nbsp;
					</div>
					<p id="mapInsertButton" style="float:right;clear:right;margin-right:20px;display:none;"><strong>Alignment:</strong><br/><input type="radio" name="mapAlign" id="mapAlignDefault" value="" checked="checked" /> Default &nbsp;<input type="radio" name="mapAlign" id="mapAlignLeft" value="left" /> Left &nbsp;<input type="radio" name="mapAlign" id="mapAlignRight" value="right" /> Right &nbsp;<input type="button" value="Insert Map" onclick="insertTravelogMap()" /></p>
					<p id="linkInsertButton" style="float:right;clear:right;margin-right:20px;display:none;"><input type="button" value="Insert Link" onclick="insertTravelogLink()" /></p>
				</fieldset>
			</div>
		</form>
	</body>
</html>