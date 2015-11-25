<?php if ( ! defined('EVENT_ESPRESSO_VERSION')) { exit('No direct script access allowed'); }
/**
 * Class EE_Psr4AutoloaderInit
 *
 * Loads the Psr4Autoloader class and registers namespaces
 *
 * @package 			Event Espresso
 * @subpackage 	core
 * @author 				Brent Christensen
 * @since 				4.8
 *
 */

class EE_Psr4AutoloaderInit {


	/**
	 * @type \EventEspresso\Core\Psr4Autoloader
	 */
	protected static $psr4_loader;

	/**
	 * @access    public
	 */
	public function __construct() {
		static $initialized = false;
		if ( ! $initialized ) {
			// instantiate PSR4 autoloader
			espresso_load_required( 'Psr4Autoloader', EE_CORE . 'Psr4Autoloader.php' );
			EE_Psr4AutoloaderInit::$psr4_loader = new \EventEspresso\Core\Psr4Autoloader();
			// register the autoloader
			EE_Psr4AutoloaderInit::$psr4_loader->register();
			// register the base directories for the namespace prefix
			EE_Psr4AutoloaderInit::$psr4_loader->addNamespace( 'EventEspresso', EE_PLUGIN_DIR_PATH );
			$initialized = true;
		}
	}



	/**
	 * @return \EventEspresso\Core\Psr4Autoloader
	 */
	public static function psr4_loader() {
		return self::$psr4_loader;
	}



}
// End of file EE_Psr4AutoloaderInit.core.php
// Location: /core/EE_Psr4AutoloaderInit.core.php