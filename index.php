<?

/*
Plugin Name: ISO to UTF content
Plugin URI: http://www.johansand.com/wp-fun/iso-to-utf-content/
Description: This plugin will convert ISO-8859-1 data to UTF-8 data in a MySQL UTF-8 WordPress database where things at some stage went wrong.
Author: Johan Sand
Version: 1.0.0
Author URI: http://www.johansand.com/
*/

// create custom plugin settings menu
add_action('admin_menu', 'iso_to_utf_create_menu');

function iso_to_utf_create_menu() {

	//create new top-level menu
	add_menu_page('ISO 2 UTF Data Plugin Settings', 'ISO 2 UTF Data', 'administrator', __FILE__, 'iso_to_utf_settings_page',plugins_url('/images/icon.png', __FILE__));

	//call register settings function
	add_action( 'admin_init', 'register_iso_to_utf_settings' );
}

function register_iso_to_utf_settings() {
	//register our settings - not sure we relly need this, but well...
	register_setting( 'iso-to-utf-settings-group', 'check_for_iso_to_utf' );
}

function hex2bin($h) {
	if (!is_string($h)) {
		return null;
	}
	$r='';
	for ($a=0; $a<strlen($h); $a+=2) {
		$r.=chr(hexdec($h{$a}.$h{($a+1)}));
	}
	return $r;
}

function iso_to_utf_settings_page() {

?>

<div class="wrap">

	<h2>Convert ISO DB Data to UTF DB Data</h2>

<? if (!$_POST['check_for_iso_to_utf']) { ?>

	<p>This plugin will convert ISO-8859-1 data to UTF-8 data in a MySQL UTF-8 WordPress database where things at some
	stage went wrong - this can happen during a command line backup/restore batch if your shell is misconfigured, for
	instance.</p>
	<p>The script will first, when you push the button below, scan your WordPress database for applicable tables and
	columns. You will then have the choice to exclude any column you feel shouldn't be converted.</p>
	<p>Once you've checked your exclusions (if any) simply hit the next button to perform the conversion. Obviously,
	depending on database size, this my take a while.</p>
	<p>You need to ensure your PHP settings have <strong>sufficient</strong> execution time to perform the conversion.</p>
	<p>This plugin <strong>requires</strong> MySQL Improved Extension (mysqli).</p>

<?

}

if (!$_POST['do_iso_to_utf'] && $_POST['check_for_iso_to_utf']) { ?>

	<p>The following applicable tables and columns were found during the database scan.<p>
	<p>Mark/select the checkboxes of the columns you <strong>do not</strong> want to convert, before continuing by
	hitting the button by the bottom of the list</p>

<?

}

if((!$_POST['check_for_iso_to_utf']) || (!$_POST['do_iso_to_utf'] && $_POST['check_for_iso_to_utf'])) {

?>

	<p>&#160;</p>
	<p><strong>Use at your own risk. Take DB backups before proceeding.</strong></p>
	<p>&#160;</p>

<?

}

if ($_POST['do_iso_to_utf'] && $_POST['check_for_iso_to_utf']) { ?>

	<p>All done! Clear all cache instances and reload your site. That's it really.</p>
	<p>&#160;</p>
	<p><strong>This plugin was used at your own risk.</strong></p>
	<p>&#160;</p>

<?

}

	//create an array of possible table/column combinations we should exclude from the conversion
	if (isset($_POST['do_iso_to_utf'])) {
		foreach ($_POST as $pk => $pv) {
			$test = explode('---', $pk);
			if($test[1]) {
				$exclude[$test[0]][$test[1]] = 1;
			}
		}
	}

	//enable the DB scan form button on initial load
	if (!$_POST['check_for_iso_to_utf']) {

?>

	<form method="post" action="">
		<? settings_fields( 'iso-to-utf-settings-group' ); ?>
		<p class="submit">
			<input type="submit" class="button-primary" name="check_for_iso_to_utf" value="<?php _e('Check for ISO to UTF tables/columns') ?>" />
		</p>
	</form>

<?

	//do the DB queries
	} else {

		//create DB instance
		$db_host = DB_HOST;
		$db_user = DB_USER;
		$db_pass = DB_PASSWORD;
		$db_name = DB_NAME;
		$DB = new mysqli($db_host, $db_user, $db_pass, $db_name);

		//not all columns should be converted, only these
		$field_types = array('varchar','text','tinytext','longtext');

		//only display exclude form on database scan
		if (!$_POST['do_iso_to_utf']) {

?>

	<form method="post" action="">
		<? settings_fields( 'iso-to-utf-settings-group' ); ?>

<?

		}

		//first, get all tables in the DB
		if ($res_tables = $DB->query ("SHOW TABLES")) {
			while ($tables = $res_tables->fetch_array(MYSQLI_NUM) ) {

				//second, get all columns in the tables
				if ($res_fields = $DB->query ("SHOW COLUMNS FROM ".$tables[0])) {
					//fetch unique columns from the table
					if ($res_key = $DB->query ("SHOW COLUMNS FROM ".$tables[0]." WHERE `Key` LIKE 'PRI'")) {
						$key = $res_key->fetch_assoc();
						$unique_key = $key['Field'];
					}

					//only display exclude form on database scan
					if (!$_POST['do_iso_to_utf']) {

?>

		<table width="450">
			<tr valign="top">
				<td width="350"><strong><? echo $tables[0]; ?></strong></td>
				<td width="100"><strong>Exclude</strong></th>
			</tr>

<?

					}

					while ($fields = $res_fields->fetch_array(MYSQLI_ASSOC) ) {
						//only operate on columns that should be converted -and-
						//that have primary, unique columns
						if (in_array($fields['Type'], $field_types) && isset($unique_key)) {
							//cheap clean-up flag for table/column view
							$data_is_set=true;

							//only display exclude form on database scan
							if (!$_POST['do_iso_to_utf']) {

?>

			<tr valign="top">
				<td><? echo $fields['Field']; ?></td>
				<td><input type="checkbox" name="<? echo $tables[0].'---'.$fields['Field']; ?>" value="" /></td>
			</tr>

<?

							}

							//do the conversion if all criteria have been met
							if (isset($_POST['do_iso_to_utf']) && (!$exclude[$tables[0]][$fields['Field']])) {
								//magic 1: fetch data in ISO
								$DB->query("SET NAMES latin1");
								if ($res = $DB->query ("SELECT ".$unique_key.", ".$fields['Field']." FROM ".$tables[0]." WHERE 1=1")) {
									while ($data = $res->fetch_object() ) {
										//magic 2: insert data in UTF
										$DB->query("SET NAMES utf8;");
										$unique_field = $data->$unique_key;
										//magic 3: treat data has binary and convert it to HEX
										//so PHP and system won't fiddle with it
										$fix_field = bin2hex($data->$fields['Field']);
										//magic 4: ensure we're not running this on anything that would be
										//considered ISO-8859-1, which in reality is UTF-8 (due to conversion magic)
										//as MySQL update then would chop off the string
										$darr[] = "UTF-8";
										$darr[] = "ISO-8859-1";
										if (mb_detect_encoding($data->$fields['Field'], $darr) != 'UTF-8') {
											echo "<hr />";
											echo "Table: ".$tables[0]."<br />";
											echo "Column: ".$fields['Field']."<br />";
											echo "Content: ".$data->$fields['Field']."<br /><br />";
											echo "Was not processed as it contains UTF-8 specific characters";
											echo "<hr />";
										} else {
											//magic 5: let MySQL directly un-HEX the data
											$result = $DB->query ("
												UPDATE ".$tables[0]."
												SET ".$fields['Field']." = UNHEX('".$DB->real_escape_string($fix_field)."')
												WHERE ".$unique_key." = '".$unique_field."'
											");
										}
										//we're looping - better safe than donk
										unset($unique_field);
										unset($fix_field);
										unset($darr);
									}
								}
							}

						}
					}

					//if cheap clean-up flag is set, display alternative message
					if(!isset($data_is_set)) {

						//only display exclude form on database scan
						if (!$_POST['do_iso_to_utf']) {

?>

        		<tr valign="top">
                		<td colspan="2"><em>No columns to convert found in this table</em></td>
        		</tr>

<?

						}

						unset($data_is_set);
					}

					//only display exclude form on database scan
					if (!$_POST['do_iso_to_utf']) {
?>

		</table>
		<p>&#160;</p>

<?

					}

					unset($data_is_set);

				}
				//again, we're looping - better safe than sorry
				unset($key);
				unset($unique_key);

			}
		}

		//only display exclude form on database scan
		if (!$_POST['do_iso_to_utf']) {

?>

		<p class="submit">
			<input type="hidden" name="check_for_iso_to_utf" value="true" />
			<input type="submit" class="button-primary" name="do_iso_to_utf" value="<?php _e('Convert ISO data to UTF data in non-excluded tables/columns') ?>" />
		</p>

	</form>
	<p>&#160;</p>

<?

		}

	}

?>

</div>

<? } ?>
