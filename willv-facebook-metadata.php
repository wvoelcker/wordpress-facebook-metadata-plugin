<?php
/**
 * Plugin Name: Facebook Metadata
 * Plugin URI: https://github.com/wvoelcker/wordpress-facebook-metadata-plugin
 * Description: Allows setting of explicit image, title, and description for sharing on Facebook
 * Version: 1.0.0
 * Author: Will Voelcker
 * Author URI: http://willv.net
 * License: GPLv2 or later
 */

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

class FacebookMetadataPlugin {

	// Namespace for data etc related to this plugin that needs
	// to be in the global space
	const NS = "facebookmetadata";

	// Returns an array of metadata fields to use.
	// These should correspond to meta-tags from the OpenGraph protocol
	static public function getFields() {
		return array("title", "image", "description");
	}

	public function go() {

		// Add interface for setting relevant metadata
		add_action("add_meta_boxes_post", array("FacebookMetadataPlugin", "addMetaBoxPost"));
		add_action("add_meta_boxes_page", array("FacebookMetadataPlugin", "addMetaBoxPage"));
		add_action("admin_menu", array("FacebookMetadataPlugin", "addDefaultOptionsPage"));

		// Add admin javascript, e.g. activates the image upload / choose button
		// And admin CSS
		add_action("admin_enqueue_scripts", function() {

			// All resources necessary for post media
			wp_enqueue_media();

			// Javascript
			$scriptkey = FacebookMetadataPlugin::NS."-admin-js";
			wp_register_script($scriptkey, WP_PLUGIN_URL."/".basename(__DIR__)."/admin.js", array("jquery"));
			wp_enqueue_script($scriptkey);

			// CSS
			$stylekey = FacebookMetadataPlugin::NS."-admin-css";
			wp_register_style($stylekey, WP_PLUGIN_URL."/".basename(__DIR__)."/admin.css");
			wp_enqueue_style($stylekey);
		});

		// Save relevant metadata along with rest of post data
		add_action("save_post", function($post_id) {
			foreach ($_POST as $key => $value) {
				if (preg_match("/^".preg_quote(FacebookMetadataPlugin::NS."-postmeta-", "/")."(.*)$/", $key, $m)) {
					update_post_meta($post_id, FacebookMetadataPlugin::getFullMetaDataKey($m[1]), $value);
				}
			}
		});

		// Output the relevant meta tags in the header of the page
		add_action("wp_head", function() {
			if (is_single() or is_page()) {
				global $post;

				foreach (FacebookMetadataPlugin::getFields() as $fieldname) {
					if ($fieldname == "image") {
						$fieldtolookup = $fieldname."_url";
					} else {
						$fieldtolookup = $fieldname;
					}

					// Try post-specific value
					$value = FacebookMetadataPlugin::getMetadataValue($post, FacebookMetadataPlugin::getFullMetaDataKey($fieldtolookup));

					// Try fallback to global default value
					if (empty($value)) {
						$value = get_option(FacebookMetadataPlugin::NS."-".FacebookMetadataPlugin::getSubnamespace("defaults")."-".$fieldtolookup);
					}

					if (empty($value)) continue;
					?>
					<meta property="og:<?php echo htmlspecialchars($fieldname);?>" content="<?php echo htmlspecialchars($value);?>"/>
					<?php
				}
			}
		});
	}

	static public function getFullMetaDataKey($fieldname) {
		return FacebookMetadataPlugin::NS."-".$fieldname;
	}

	static public function addDefaultOptionsPage() {
		$requiredCapability = "manage_options";
		add_options_page(
			"Facebook sharing options",
			"Facebook",
			$requiredCapability,
			FacebookMetadataPlugin::NS."-defaults-page",
			function() use ($requiredCapability) {
				if (!current_user_can($requiredCapability)) {
					echo "Permission denied";
					exit;
				}
				?>
				<div class="wrap">
					<h1>Facebook sharing options (default for all pages)</h1>
					<form method="post" action="options.php" class="<?php echo FacebookMetadataPlugin::NS;?>-defaults-form">
					<?php
						wp_nonce_field("update-options");

						$options = FacebookMetadataPlugin::renderMetadataForm("defaults", FacebookMetadataPlugin::getFields());

						?>
						<input type="hidden" name="action" value="update" />
						<input type="hidden" name="page_options" value="<?php echo htmlspecialchars(join(", ", $options));?>" />
						<?php

						submit_button();
					?>
					</form>
				</div>
				<?php
			}
		);
	}

	static public function addMetaBoxPost($post) {
		self::addMetaBox($post);
	}

	static public function addMetaBoxPage($page) {
		self::addMetaBox($page, "page");
	}

	static private function addMetaBox($post, $postType = "post") {
		add_meta_box(
			FacebookMetadataPlugin::NS."-fields",
			"Facebook Sharing Options",
			function($post, $metabox) {
				FacebookMetadataPlugin::renderMetadataForm($post, FacebookMetadataPlugin::getFields());
			},
			$postType,
			"normal"
		);
	}

	static public function getMetadataValue($post, $key) {
		$returnFirstValue = true;
		return (string)get_post_meta(
			$post->ID,
			$key,
			$returnFirstValue
		);
	}

	static public function getSubnamespace($post) {
		$isDefaults = (is_string($post) and ($post == "defaults"));
		$subnamespace = "postmeta";
		if ($isDefaults) {
			$subnamespace .= "-default";
		}

		return $subnamespace;
	}

	static public function renderMetadataForm($post, $keys) {
		$isDefaults = (is_string($post) and ($post == "defaults"));
		$subnamespace = FacebookMetadataPlugin::getSubnamespace($post);

		// Get current values for the fields
		$currentmetadata = array();
		foreach ($keys as $key) {
			if ($key == "image") {
				$keytolookup = $key."_url";
			} else {
				$keytolookup = $key;
			}

			if ($isDefaults) {
				$currentmetadata[$key] = get_option(FacebookMetadataPlugin::NS."-".$subnamespace."-".$keytolookup);
			} else {
				$currentmetadata[$key] = self::getMetadataValue($post, self::getFullMetaDataKey($keytolookup));
			}
		}

		// Output fields, compile field names for returning (returning is necessary for the global options page)
		$fieldids = array();
		?>
		<table class="<?php echo htmlspecialchars(self::NS."-metadataformtable");?>">
			<?php
			foreach ($currentmetadata as $key => $value) {
				$fieldid = self::NS."-".$subnamespace."-".$key;
				$fieldids[] = $fieldid;
				?>
				<tr>
					<td class="label">
						<label for="<?php echo htmlspecialchars($fieldid);?>"><?php echo ucfirst(htmlspecialchars($key));?></label>:
					</td>
					<td>
						<?php
						switch ($key) {
							case "image":
								self::outputTextField($fieldid."_url", $value);
								$fieldids[] = $fieldid."_url";
								?>
								<p><input id="<?php echo htmlspecialchars($fieldid);?>" class="button <?php echo FacebookMetadataPlugin::NS;?>-image" type="button" value="Upload / Choose Image" /> (Must be larger than 200px by 200px)</p>
								<?php
								break;
							default:
								self::outputTextField($fieldid, $value);
						}
						?>

					</td>
				</tr>
				<?php
			}
			?>
		</table>
		<?php

		return $fieldids;
	}

	static private function outputTextField($id, $value) {
		?>
		<p><input id="<?php echo htmlspecialchars($id);?>" class="text" type="text" name="<?php echo htmlspecialchars($id);?>" value="<?php echo htmlspecialchars($value);?>" /></p>
		<?php
	}
}

$facebook_metadata_plugin = new FacebookMetadataPlugin;
$facebook_metadata_plugin->go();
