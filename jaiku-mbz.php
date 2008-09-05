<?php
/*
Plugin Name: jaiku-mbz
Plugin URI: http://mbz.nu/portfolio/jaiku-mbz/
Description: Plugin widget that lets you display your latest jaikus in the sidebar. Somewhat based on the plugin jaiku-activity by Douglas Karr.
Version: 0.2.0
Author: Matts Bengtzén
Author URI: http://mbz.nu/
*/

function widget_jaiku_mbz_register(){
	if ( function_exists('register_sidebar_widget') ) :
	
		function wpjm_verify_cache_dir($wpjm_cache_dir) {
			$dir = dirname($wpjm_cache_dir);
			
			if( !file_exists($wpjm_cache_dir) ) {
				if( !is_writable( $dir ) || !($dir = mkdir( $wpjm_cache_dir, 0777) ) ) {
					echo "<b>Error:</b> Your cache directory (<b>$wpjm_cache_dir</b>) did not exist and couldn't be created by the web server.<br />Check  $dir permissions.";
					return false;
				}
			}
			
			if( !is_writable($wpjm_cache_dir) ){
				echo "<b>Error:</b> Your cache directory (<b>$wpjm_cache_dir</b>) or <b>$dir</b> need to be writable for this plugin to work.<br />Double-check it.";
				return false;
			}
		
			if ( '/' != substr($wpjm_cache_dir, -1) ) {
				$wpjm_cache_dir .= '/';
			}
			return true;
		}
		
		// taken directly from jaiku-activity
		function wpjm_isSimpleXMLLoaded() {
			$array = array();
			$array = get_loaded_extensions();
			$result = false;
			
			foreach ($array as $i => $value) {
				if (strtolower($value) == "simplexml") { 
					$result = true; 
				}
			}
			
			return $result;
		}
		
		function strip($txt){
			return trim( htmlspecialchars( stripslashes( $txt ) ) );
		}	
	
		function wpjm_jaiku() {
			$wpjm_cache_dir = ABSPATH.'wp-content/plugins/jaiku-mbz/cache/';
			
			$options = get_option('widget_jaiku_mbz');
			
			
			$wpjm_title		= stripslashes($options['wpjm_title']);
			$wpjm_user 		= stripslashes($options['wpjm_user']);
			$wpjm_minutes 	= stripslashes($options['wpjm_minutes']);
			$wpjm_count 	= stripslashes($options['wpjm_count']);
			
			if( !is_numeric($wpjm_count) )
				$wpjm_count = 5;

				
			if( !wpjm_verify_cache_dir($wpjm_cache_dir) ){
				echo "<br />Cannot continue... fix previous problems and retry.<br />";
				return;
			}
			
			if( !wpjm_isSimpleXMLLoaded() ){
				echo "<br />Cannot continue... this plugin requires SimpleXML, released in PHP5 or higher.  Check with your hosting company to find out why they aren't providing you with the latest and greatest version of PHP!<br />";
				return;
			}
			
			if( !( ($wpjm_user != "") && ($wpjm_minutes!="") ) ) {
				echo "<h2>Jaiku</h2>\n<ul>Please configure jaiku!</ul>\n";
			}
			else{
				$cachefile = $wpjm_cache_dir."/jaiku-mbz.html";
				$cachetime = $wpjm_minutes * 60;
		
				// Serve from the cache if it is younger than $cachetime
				if( (file_exists($cachefile) && (time() - $cachetime < filemtime($cachefile) ) ) ) {
					include($cachefile);
					echo "<!-- Cached ".date('Y-m-d H:i', filemtime($cachefile))." -->\n";
				} else {
					// Retrieve the current rank from the API
					$request = 'http://'.$wpjm_user.'.jaiku.com/feed/rss';
				
					// Initialize the session
					$session = curl_init($request);
				
					// Set curl options
					curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($session, CURLOPT_CONNECTTIMEOUT, 4);
					curl_setopt($session, CURLOPT_TIMEOUT, 8);
					curl_setopt($session, CURLOPT_USERAGENT, get_option('siteurl'));
					curl_setopt($session, CURLOPT_HEADER, FALSE);
				
					// Make the request
					$response = curl_exec($session);
				
					// Close the curl session
					curl_close($session);
					
				
					// Get the XML from the response
					$parse_error = false;
					try{
						$xml = new SimpleXMLElement($response);
		
						$message .= '<ul>'."\n";
		
						// start parsing the rss-file
						$i = 0;
						foreach ($xml->xpath('//item') as $item) {
							$i++;
							
							if($i>$wpjm_count)
								break;
							
							$title = strip( (string)$item->title );
							$link = strip( (string)$item->link );
							
							// We need to explicitly name the namespace for jaikus time-inclusion
							$jaiku = $item->children("http://jaiku.com/ns");
							$time = $jaiku->timesince;
							
							if(substr($time,-1,1)=='.'){
								$time = substr($time,0,strlen($time)-1);
							}
								
							$message .= "<li><a href=\"$link\">$title<br /><span class=\"wpjm_time\">[$time]</span></a></li>\n";
						}
					
						// Construct the output
						$message .= '</ul>' . "\n";
						
						
						// Write the results to a cache file
						$fp = fopen($cachefile, 'w');
						fwrite($fp, $message);
						fclose($fp);
					}catch (Exception $e) {
						$message = "<!-- There was an error! ".date('Y-m-d H:i')." -->";
						$parse_error = true;
					}
					
					// If Jaiku doesn't respond, just load the cached response
					if( $parse_error && file_exists($cachefile) ) {
						include($cachefile);
						echo "<!-- Cached ".date('Y-m-d H:i', filemtime($cachefile))." -->\n";
					} else {
						// Display the results
						echo $message;
					}
				}
			} 
		
		}


	
		function widget_jaiku_mbz($args) {
			extract($args);
			$options = get_option('widget_jaiku_mbz');
			
			echo "<!-- http://mbz.nu/wp/jaiku -->\n";
			echo $before_widget;
			echo $before_title . $options['wpjm_title'] . $after_title;
			
			wpjm_jaiku();
			echo "<!-- Updated ".date('Y-m-d H:i')." -->\n";
			echo "<!-- End Jaiku-mbz Plugin Results -->\n";
			
			echo $after_widget;
		}
	
		function widget_jaiku_mbz_control() {
			$options = $newoptions = get_option('widget_jaiku_mbz');
			
			if ( $_POST["wpjm_submit"] ) {
				$newoptions['wpjm_title'] = strip($_POST["wpjm_title"]);
				if( empty($newoptions['wpjm_title']) ) 
					$newoptions['wpjm_title'] = 'Latest Jaikus';
				
				$newoptions['wpjm_user'] 	= strip_tags(stripslashes($_POST["wpjm_user"]));
				$newoptions['wpjm_minutes'] = strip_tags(stripslashes($_POST["wpjm_minutes"]));
				$newoptions['wpjm_count'] 	= strip_tags(stripslashes($_POST["wpjm_count"]));
			}
			
			if ( $options != $newoptions ) {
				$options = $newoptions;
				update_option('widget_jaiku_mbz', $options);
			}
			
			$wpjm_title   = htmlspecialchars($options['wpjm_title'], ENT_QUOTES);
			$wpjm_user	  = htmlspecialchars($options['wpjm_user'], ENT_QUOTES);
			$wpjm_minutes = htmlspecialchars($options['wpjm_minutes'], ENT_QUOTES);
			$wpjm_count   = htmlspecialchars($options['wpjm_count'], ENT_QUOTES);
			
			if( 0==strlen($wpjm_minutes) )
				$wpjm_minutes = "15";
			if( 0==strlen($wpjm_count) )
				$wpjm_count = "5";
			
			echo '<a href="http://mbz.nu/portfolio/jaiku-mbz/">Jaiku-mbz homepage!</a>' . "\n";
			echo '<p>' . "\n";
			echo '	<label for="wpjm_title">'._e('Title:').'</label>' . "\n";
			echo '	<input style="width: 250px;" id="wpjm_title" name="wpjm_title" type="text" value="'.$wpjm_title.'" />' . "\n";
			echo '	<br />' . "\n";
			echo '	<label for="wpjm_user">'._e('User:').'</label>' . "\n";
			echo '	<input style="width: 250px;" id="wpjm_user" name="wpjm_user" type="text" value="'.$wpjm_user.'" />' . "\n";
			echo '	<br />' . "\n";
			echo '	<label for="wpjm_count">'._e('Display count:').'</label><br />' . "\n";
			echo '	<select name="wpjm_count" id="wpjm_count">' . "\n";
			for($i=1;$i<=10;$i++){
				echo '\t\t<option value="'.$i.'"'.($i==$wpjm_count?' selected="selected"':'').'>'.$i.'</option>' . "\n";	
			}			
			echo '	</select>' . "\n";
			echo '	<br />' . "\n";
			echo '	<label for="wpjm_minutes">'._e('Cache update interval:').'</label><br />' . "\n";
			echo '	<select name="wpjm_minutes" id="wpjm_minutes">' . "\n";
			echo '		<option value="0"'.("0" == $wpjm_minutes?' selected="selected"':'').'>Do not cache</option>' . "\n";
			echo '		<option value="5"'.("5" == $wpjm_minutes?' selected="selected"':'').'>5 minutes</option>' . "\n";
			echo '		<option value="10"'.("10" == $wpjm_minutes?' selected="selected"':'').'>10 minutes</option>' . "\n";
			echo '		<option value="15"'.("15" == $wpjm_minutes?' selected="selected"':'').'>15 minutes</option>' . "\n";
			echo '		<option value="30"'.("30" == $wpjm_minutes?' selected="selected"':'').'>30 minutes</option>' . "\n";
			echo '		<option value="60"'.("60" == $wpjm_minutes?' selected="selected"':'').'>60 minutes</option>' . "\n";
			echo '	</select>' . "\n";
			echo '</p>' . "\n";
			echo '<input type="hidden" id="wpjm_submit" name="wpjm_submit" value="1" />' . "\n";
		}
		
		function widget_jaiku_mbz_style(){
			?>
			<style type="text/css">
			.wpjm_time {font-weight: bold;}
			</style>
			<?php
		}
	
		register_sidebar_widget('Jaiku-mbz', 'widget_jaiku_mbz', null, 'jaiku_mbz');
		register_widget_control('Jaiku-mbz', 'widget_jaiku_mbz_control', null, 75, 'jaiku_mbz');
		if ( is_active_widget('widget_jaiku_mbz') )
			add_action('wp_head', 'widget_jaiku_mbz_style');
	
	endif;	
}

add_action('init', 'widget_jaiku_mbz_register');
?>