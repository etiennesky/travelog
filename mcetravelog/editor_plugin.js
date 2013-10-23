/* Import plugin specific language pack */
tinyMCE.importPluginLanguagePack('travelog', '');

/**
 * Information about the plugin.
 */
function TinyMCE_travelog_getInfo() {
	return {
		longname  : 'Travelog Plugin',
		author    : 'Shane Warner',
		authorurl : 'http://www.sublimity.ca/',
		infourl   : 'http://travelog.sublimity.c/',
		version   : "1.0"
	};
};

/**
 * Gets executed when a editor needs to generate a button.
 */
function TinyMCE_travelog_getControlHTML(control_name) {
	switch (control_name) {
		case "travelog":
		return '<img id="{$editor_id}_travelog" src="{$pluginurl}/images/travelog.gif" title="{$lang_travelog_button_title}" width="20" height="20" class="mceButtonNormal" onmouseover="tinyMCE.switchClass(this,\'mceButtonOver\');" onmouseout="tinyMCE.restoreClass(this);" onmousedown="tinyMCE.restoreAndSwitchClass(this,\'mceButtonDown\');" onclick="tinyMCE.execInstanceCommand(\'{$editor_id}\',\'mcetravelog\');">';
	}

	return "";
}

/**
 * Gets executed when travelog is called.
 */
function TinyMCE_travelog_execCommand(editor_id, element, command, user_interface, value) {

	var inst = tinyMCE.getInstanceById(editor_id);
	var focusElm = inst.getFocusElement();
	var doc = inst.getDoc();

	function getAttrib(elm, name) {
		return elm.getAttribute(name) ? elm.getAttribute(name) : "";
	}

	// Handle commands
	switch (command) {
		case "mcetravelog":

			var flag = "";
			var template = new Array();
			var file_name  = "";
			template['width'] = 550;
			template['height'] = 520;

			if (focusElm != null && getAttrib(focusElm, 'id') == "mce_plugin_travelog_travelogmap"){
				oldTagAttrs = getAttrib(focusElm, 'alt');
				template['file']   = '../../plugins/mcetravelog/travelog_mce.php?type=map&tinymce=1'; // Relative to theme
				tinyMCE.openWindow(template, {editor_id : editor_id, oldTagAttrs : oldTagAttrs, mceDo : 'update', resizable : "yes", scrollbars : "no"});
			}else {
				var linkText = '';
				if (tinyMCE.isMSIE) {
					var rng = doc.selection.createRange();
					linkText = rng.text;
				}else{
					linkText = inst.getSel().toString();
				}
				template['file'] = '../../plugins/mcetravelog/travelog_mce.php?type=link&tinymce=1'; // Relative to theme
				tinyMCE.openWindow(template, {editor_id : editor_id, mceDo : 'insert', linkText : linkText, resizable : "yes", scrollbars : "no"});
			}

			return true;
	}

	// Pass to next handler in chain
	return false;
}

function TinyMCE_travelog_cleanup(type, content) {

	switch (type) {

		case "insert_to_editor":

			// Parse all <travelog> tags and replace them with links to the location
			var startPos = 0;

			while ((startPos = content.indexOf('<travelog ', startPos)) != -1) {

				var endPos       = content.indexOf('</travelog>', startPos);
				var contentAfter = content.substring(endPos+11);
				
				var endAttrs = content.indexOf('>', startPos);
				var attrs = content.substring(startPos+10, endAttrs);
				var innerText   = content.substring(endAttrs + 1,endPos);
				var start=0;
				var id='';var zoom='';var mapType='';var mapVia='';
				attrFinder = new RegExp('id="(.*)"','gi');
				var unQuoter = new RegExp('"(.*)"','gi');
				if(attrs.match(attrFinder)) id = attrs.substring(start=attrs.indexOf('id=')+4 ,attrs.indexOf('"',start+1));
				attrFinder = new RegExp('zoom="(.*)"','gi');
				if(attrs.match(attrFinder)) zoom = attrs.substring(start=attrs.indexOf('zoom=')+6 ,attrs.indexOf('"',start+1));
				attrFinder = new RegExp('view="(.*)"','gi');
				if(attrs.match(attrFinder)) mapType = attrs.substring(start=attrs.indexOf('view=')+6 ,attrs.indexOf('"',start+1));
				attrFinder = new RegExp('use="(.*)"','gi');
				if(attrs.match(attrFinder)) mapVia = attrs.substring(start=attrs.indexOf('use=')+5 ,attrs.indexOf('"',start+1));
				
				var query = '';
				if(id != '') query += 'id='+id+'&';
				if(zoom != '') query += 'zoom='+zoom+'&';
				if(mapType != '') query += 'type='+mapType+'&';
				if(mapVia != '') query += 'use='+mapVia+'&';
				var mapURL = 'http://maps.google.com/?'+query;

				
				var insertedTag = '<a id="mce_plugin_travelog_travelog" href="'+mapURL+'" title="Map Location">' + innerText + '</a>';

				// Insert image
				content = content.substring(0, startPos);
				content += insertedTag + contentAfter;

				startPos++;
			}

			// Parse all <!--travelogmap--> tags and replace them with maps
			var startPos = 0;

			while ((startPos = content.indexOf('<!--travelogmap', startPos)) != -1) {

				var endPos       = content.indexOf('-->', startPos);
				var contentAfter = content.substring(endPos+3);

				var tagAttrs = content.substring(startPos+15,endPos);
				tagAttrs = tagAttrs.replace(/\"/gi,'\|');

				var placeholdermap_path = tinyMCE.baseURL + "/plugins/mcetravelog/images/map_placeholder.jpg";
				
				var fakeTag = '<img id="mce_plugin_travelog_travelogmap" src="' + placeholdermap_path + '" alt="'+tagAttrs+'" title="Travelog GoogleMap" />';

				// Insert image
				content = content.substring(0, startPos);
				content += fakeTag;
				content += contentAfter;

				startPos++;
			}

			break;

		case "get_from_editor":

			// Parse all Travelog placeholder <a> tags and replace them with <travelog>
			var startPos = -1;

			while ((startPos = content.indexOf('<a', startPos+1)) != -1) {

				var endPos = content.indexOf('>', startPos);
				var attribs = TinyMCE_travelog_parseAttributes(content.substring(startPos + 4, endPos));
				
				var linkedText = content.substring(endPos+1, content.indexOf('<',endPos));

				if (attribs['id'] == "mce_plugin_travelog_travelog") {
				
					// Reparse params from URL
					var URLattrs = attribs['href'];
					var id='';var zoom='';var mapType='';var mapVia='';
					if(URLattrs.indexOf('id=') != -1) id = URLattrs.substring(start=URLattrs.indexOf('id=')+3 ,URLattrs.indexOf('&',start));
					if(URLattrs.indexOf('zoom=') != -1) zoom = URLattrs.substring(start=URLattrs.indexOf('zoom=')+5 ,URLattrs.indexOf('&',start));
					if(URLattrs.indexOf('view=') != -1) mapType = URLattrs.substring(start=URLattrs.indexOf('view=')+5 ,URLattrs.indexOf('&',start));
					if(URLattrs.indexOf('use=') != -1) mapVia = URLattrs.substring(start=URLattrs.indexOf('use=')+4 ,URLattrs.indexOf('&',start));
					
					var tlAttrs = '';
					if(id != '') tlAttrs += ' id="'+id+'"';
					if(zoom != '') tlAttrs += ' zoom="'+zoom+'"';
					if(mapType != '') tlAttrs += ' type="'+mapType+'"';
					if(mapVia != '') tlAttrs += ' use="'+mapVia+'"';
					
					
					var embedHTML = '<travelog' + tlAttrs + '>' + linkedText + '</travelog>';

					// Insert embed/object chunk
					chunkBefore = content.substring(0, startPos);
					chunkAfter  = content.substring(content.indexOf('>',endPos+1)+1);

					content = chunkBefore + embedHTML + chunkAfter;
				}
			}

			// Parse all Travelogmap placeholder <img> tags and replace them with <!--travelogmap-->
			var startPos = -1;

			while ((startPos = content.indexOf('<img', startPos+1)) != -1) {

				var endPos = content.indexOf('/>', startPos);
				var attribs = TinyMCE_travelog_parseAttributes(content.substring(startPos + 4, endPos));

				if (attribs['id'] == "mce_plugin_travelog_travelogmap") {
					
					tagAttrs = attribs['alt'].replace(/\|/gi,'"');
					
					endPos += 2;
					var embedHTML = '<!--travelogmap' + tagAttrs + '-->';

					// Insert embed/object chunk
					chunkBefore = content.substring(0, startPos);
					chunkAfter  = content.substring(endPos);

					content = chunkBefore + embedHTML + chunkAfter;
				}
			}

			break;
	}

	// Pass through to next handler in chain
	return content;
}

function TinyMCE_travelog_parseAttributes(attribute_string) {

	var attributeName = "";
	var attributeValue = "";
	var withInName;
	var withInValue;
	var attributes = new Array();
	var whiteSpaceRegExp = new RegExp('^[ \n\r\t]+', 'g');

	if (attribute_string == null || attribute_string.length < 2)
		return null;

	withInName = withInValue = false;

	for (var i=0; i<attribute_string.length; i++) {
		var chr = attribute_string.charAt(i);

		if ((chr == '"' || chr == "'") && !withInValue)
			withInValue = true;

		else if ((chr == '"' || chr == "'") && withInValue) {

			withInValue = false;

			var pos = attributeName.lastIndexOf(' ');
			if (pos != -1)
				attributeName = attributeName.substring(pos+1);

			attributes[attributeName.toLowerCase()] = attributeValue.substring(1).toLowerCase();

			attributeName = "";
			attributeValue = "";
		}
		else if (!whiteSpaceRegExp.test(chr) && !withInName && !withInValue)
			withInName = true;

		if (chr == '=' && withInName)
			withInName = false;

		if (withInName)
			attributeName += chr;

		if (withInValue)
			attributeValue += chr;
	}

	return attributes;
}

function TinyMCE_travelog_handleNodeChange(editor_id, node, undo_index, undo_levels, visual_aid, any_selection) {

	function getAttrib(elm, name) {
		return elm.getAttribute(name) ? elm.getAttribute(name) : "";
	}

	tinyMCE.switchClassSticky(editor_id + '_travelog', 'mceButtonNormal');

	if (node == null)
		return;

	do {
		if ((node.nodeName.toLowerCase() == "a" && getAttrib(node, 'id').indexOf('mce_plugin_travelog_travelog') == 0) || (node.nodeName.toLowerCase() == "img" && getAttrib(node, 'id').indexOf('mce_plugin_travelog_travelogmap') == 0))
			tinyMCE.switchClassSticky(editor_id + '_travelog', 'mceButtonSelected');
	} while ((node = node.parentNode));

	return true;
}
