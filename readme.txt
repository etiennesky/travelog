= Travelog Plugin =

Tags: travel, map, location, GPS, address, visit
Contributors: swift
Version: 1.0
Plugin websites:
http://dev.wp-plugins.org/wiki/Travelog 
http://www.sublimity.ca/2005/09/01/travelog/

The Travelog plugin for WordPress lets you input and organize information about the places you've been (coordinates and/or addresses, dates visited & a description), and allows  individual posts or text within posts to be associated with these locations. The plugin can also provide direct links to online maps of the locations from various online mapping services, including [http://maps.google.com/ GoogleMaps] and [http://www.mapquest.com/ Mapquest].

--------

= Installation =

 1. Upload the 'travelog' folder from this archive to your /wp-content/plugins/ folder on your webserver.
 2. Active the "Travelog" plugin in WordPress's Site Admin Plugins page.
 3. Modify your WordPress theme so that location information is displayed for posts in summary view. The easiest way to do this is to use the standard Travelog output function, travelog_summary_info(). This is done by adding the following code to your index.php file, right after the post title section. For the default theme, you would change:

<h2><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title(); ?>">
<?php the_title(); ?></a></h2>
<small><?php the_time('F jS, Y') ?> <!-- by <?php the_author() ?> --></small>

 to be:

<h2><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title(); ?>">
<?php the_title(); ?></a></h2>
<?php echo travelog_summary_info(); ?><br />
<small><?php the_time('F jS, Y') ?> <!-- by <?php the_author() ?> --></small>

 Make similar modifications to archive.php and search.php. Alternatively, You can create your own customized location information output using the Travelog's PHP functions, which are listed and described below.
 4. Modify your WordPress theme so that location information is displayed for posts in detail view. The easiest way to do this is to use the standard Travelog output function, travelog_single_info(). This is done by adding the following code to your single.php file, right after the post date information. For the default theme, you would change:

on <?php the_time('l, F jS, Y') ?> at <?php the_time() ?>
and is filed under <?php the_category(', ') ?>.

 to be:

on <?php the_time('l, F jS, Y') ?> at <?php the_time() ?>
<?php echo travelog_single_info(); ?>
and is filed under <?php the_category(', ') ?>.

 Again, you can create your own custom output using Travelog's PHP functions listed and described below.
 5. Review the Travelog options by clicking on Travelog on the Options page sub-menu of WordPress's Site Admin.
 6. Start adding locations to your Travelog by clicking on Travelog on the Manage page sub-menu of WordPress's Site Admin

= Components =
 * Travelog Options: This page let's you control some basic setting about how your Travelog works. You can access this page by clicking on Travelog in the sub-menu on the Options page of WordPress's Site Admin.
 * Travelog Locations Manager: This page shows summary information for the locations in your Travelog. You can access this page by clicking on Travelog in the sub-menu on the Manage page of WordPress's Site Admin.
 * Travelog Location Editor: This page lets you update/change any of the information associated with a location, and optionally shows an embedded map of the location. To access this page, click the 'Edit' link next to the location you want to edit in the Travelog Location Manager.
 * Travelog Categories Manager: This page lets you add/edit/remove Travelog categories to help keep your Travelog organized. To access this page, go to the Travelog Location Manager, and click on the 'Categories' tab just below the main menus.
 * Travelog's 'Edit Post' Component: This form is attached to the 'Edit Post' page and allows you to select which location to associate with the post you're editing, or optionally create a new location to associate with that post. To access this, follow normal post editing procedure, either go to the Site Admin's Manage page and click on the post to edit, or click the edit link that appears below the post on your blog while you are logged in as an administrator.

= Usage & License =
The Travelog plugin is licensed under the GPL (the same license that WordPress uses), and to sum it up, you can freely use, distribute and modify this plugin however you desire. If you do use it on your site, I'd appreciate you [http://www.sublimity.ca/2005/09/01/travelog/#coments leaving a comment on my site] telling me what you think about it, but it's more to satisfy my curiousity about what ends up happening to this little project of mine than anything else, so it's not a big deal.

If you find any bugs (I'm sure there are some in here), or have ideas about things you'd like to see in future releases of this plugin, [http://www.sublimity.ca/2005/09/01/travelog/#coments  please let me know] (especially if you'd like to help implement them). I'll do my best to implement suggestions, but I can't make any rock-solid promises as there is more to life than coding...

= Change Log =

=== Version 1.0 ===
Initial public release

--------

= Function Reference =
The Travelog plugin provides several functions for interacting with your Travelog. There are three sets of functions provided; General Functions, Advanced Functions and Internal Functions:


== General Functions ==
These functions allow for basic retrieval and output of location information for posts

travelog_summary_info()
Outputs a string with location information about the post. It shows the location name (which is linked to a GoogleMap of the location) as well as the coordinates of the location in degree-minute-second format. This is meant to be used on your index.php page right below the post title. If there is no location associated with the post, nothing is displayed.

the_latitude()
Outputs the latitude of the location associated with the post in decimal degree format.

the_latitudeDMS()
Outputs the latitude of the location associated with the post in degree-minute-second format (ddû mm' ss").

the_longitude()
Outputs the longitude of the location associated with the post in decimal degree format.

the_longitudeDMS()
Outputs the longitude of the location associated with the post in degree-minute-second format (ddû mm' ss").

the_location_name()
Outputs the name of the location associated with the post.

the_location_description()
Outputs the description of the location associated with the post.

get_latitude()
Returns the value of the latitude for the location associated with the post in decimal degree format, with north latitudes being positive and south latitudes being negative. This can be used if you want to display the latitude in some other format, check to make sure there is a latitude for the post or output different latitudes differently.

get_longitude()
Returns the value of the longitude for the location associated with the post in decimal degree format, with east longitudes being positive and west longitudes being negative. This can be used if you want to display the longitudes in some other format, check to make sure there is a longitudes for the post or output different longitudes differently.

get_location_name()
Returns the name of the location associated with the post.

get_location_description()
Returns the description of the location associated with the post.

map_location_url($map, $location_id)
Returns the URL for a map of the location whose ID is passed as $location_id. $map lets you choose which mapping service to use. Acceptable values for $map are:
 * 'GoogleMaps'
 * 'MapQuest'
 * 'AcmeMap'
 * 'GeoURL'
 * 'GeoCache'
 * 'SideBit'
 * 'DegreeConfluence'
 * 'TopoZone'
 * 'FindU'
 * 'MapTech'

distance_between($id1, $id2, $unit)
Calculates the distance between the two locations whose ids are passed as $id1 and $id2 (using their latitudes & longitudes), and returns the value as a number in terms of the units specified in the $unit parameter. For kilometers use 'k', for miles use 'm' and for nautical miles use 'n'.



== Advanced Functions ==
These functions control the inner workings of the Travelog plugin, and provide advanced access to the information contained in your Travelog. You should have at least moderate experience working with PHP if you want to use these functions.

NOTE: All the advanced functions are contained in the static class 'Travelog'. To call them in your templates, you must add 'Travelog::' in front of their names (eg. to call get_categories(), you would use <?php $categories = Travelog::get_categories(); ?> **

add_location($location)
Adds a location to your Travelog based on information passed in the $location parameter. $location must be an object with variables for each of the Travelog database fields.

get_locations($category, $limit, $order)
Returns an array of objects, one object for each location in your travelog, and each object has a set of variables containing the all information about that location.

get_location($id)
Returns a location object with all the information for the location whose id is passed as the id parameter.

get_post_location()
Returns a location object with all the information for the location associated with the post. If no location is associated with the post and a default location is set, the location object will contain information about the default location.

get_categories()
Returns an numerically-indexed array of all of the Travelog category names

coordinate_DMS($value, $coordinate)
Returns a HTML string showing the value of the coordinate passed as $value in the degrees-minutes-seconds format. $coordinate must be either 'latitude' or 'longitude' to tell the function which direction (N/S or E/W) to append on the end.



== Internal Functions ==
These functions control the inner workings of the Travelog plugin, and should not be used in your templates. If you want to make changes to how the Travelog plugin works, this is where it should be done, but only if you have an advanced understanding of PHP, as making any changes could destroy your Travelog. The functions are just listed briefly here to let you know generally what they do.

inline_locations($content)
Used to process travelog tags that appear within posts

update_post($post_id)
Does the processing of associating a location with a post

add_menus()
Displays the Travelog links in the Options and Manage sub-menus in the WordPress Site Admin

install_db()
Create the travelog database table and initializes the Travelog's WordPress options

edit_form_advanced()
Controls what is shown on the Edit Post Page

show_adminheader()
CSS and HTML to create the submenu on the Manage page

manage_locations()
Handles the addition, updating and removal of locations and categories

manage_options()
Handles the updating of Travelog's options

googlemaps_javahook()
Outputs a javascript link in the page headers to allow [http://maps.google.com/ GoogleMaps] to be embedded.

coordinate_metatags()
Outputs some standard meta tags with location information