<?

if( !defined( 'ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') )
exit();

delete_action('admin_menu', 'iso_to_utf_create_menu');
delete_menu_page('ISO 2 UTF Data Plugin Settings', 'ISO 2 UTF Data', 'administrator', __FILE__, 'iso_to_utf_settings_page',plugins_url('/images/icon.png', __FILE__));
delete_action( 'admin_init', 'register_iso_to_utf_settings' );

?>
