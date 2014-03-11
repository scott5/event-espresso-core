<?php if ( ! defined('EVENT_ESPRESSO_VERSION')) exit('No direct script access allowed');
/**
 * Event Espresso
 *
 * Event Registration and Management Plugin for WordPress
 *
 * @ package			Event Espresso
 * @ author				Seth Shoultes
 * @ copyright		(c) 2008-2011 Event Espresso  All Rights Reserved.
 * @ license			http://eventespresso.com/support/terms-conditions/   * see Plugin Licensing *
 * @ link					http://www.eventespresso.com
 * @ version		 	4.0
 *
 * ------------------------------------------------------------------------
 *
 * Payment Method Model
 *
 * For storing all payment methods (things that interact between EE and gateways).
 * As of 4.3, payment methods are NOT singletons so there can be multiple instances of payment methods
 * of the same type, with different details. Eg, multiple paypal standard gateways so different
 * events can have their proceeds going to different paypal accounts
 *
 * @package			Event Espresso
 * @subpackage		includes/models/EEM_Checkin.model.php
 * @author			Mike Nelson
 *
 * ------------------------------------------------------------------------
 */

class EEM_Payment_Method extends EEM_Base {

	/**
	 *
	 * @var EEM_Payment_Method
	 */
	private static $_instance = NULL;



	/**
	 * 		This funtion is a singleton method used to instantiate the EEM_Payment_Method object
	 *
	 * 		@access public
	 * 		@return EEM_Payment_Method instance
	 */
	public static function instance() {

		// check if instance of EEM_Checkin already exists
		if (self::$_instance === NULL) {
			// instantiate Price_model
			self::$_instance = new self( $timezone );
		}

		//set timezone if we have in incoming string
		if ( !empty( $timezone ) )
			self::$_instance->set_timezone( $timezone );
		
		// EEM_Checkin object
		return self::$_instance;
	}



	/**
	 * 		private constructor to prevent direct creation
	 * 		@Constructor
	 * 		@access protected
	 * 		@param string $timezone string representing the timezone we want to set for returned Date Time Strings (and any incoming timezone data that gets saved).  Note this just sends the timezone info to the date time model field objects.  Default is NULL (and will be assumed using the set timezone in the 'timezone_string' wp option)
	 * 		@return void
	 */
	protected function __construct() {
		$this->singlular_item = __('Payment Method','event_espresso');
		$this->plural_item = __('Payment Methods','event_espresso');		

		$this->_tables = array(
			'Payment_Method'=>new EE_Primary_Table('esp_payment_method','PMD_ID')
		);
		$this->_fields = array(
			'Payment_Method'=> array(
				'PMD_ID'=>new EE_Primary_Key_Int_Field('PMD_ID', __("ID", 'event_espresso')),
				'PMD_type'=>new EE_Plain_Text_Field('PMD_type', __("Payment Method Type", 'event_espresso'), false),
				'PMD_name'=>new EE_Plain_Text_Field('PMD_name', __("Name", 'event_espresso'), false),
				'PMD_desc'=>new EE_Simple_HTML_Field('PMD_desc', __("Description", 'event_espresso'), false, ''),
				'PMD_slug'=>new EE_Slug_Field('PMD_slug', __("Slug", 'event_espresso'), false),
				'PMD_order'=>new EE_Integer_Field('PMD_order', __("Order", 'event_espresso'), false, 0),
				'PRC_ID'=>new EE_Foreign_Key_Int_Field('PRC_ID', __("Surcharge Price", 'event_espresso'), true, NULL, 'Price'),
				'PMD_debug_model'=>new EE_Boolean_Field('PMD_debug_model', __("Debug Mode On?", 'event_espresso'), false, false),
				'PMD_logging'=>new EE_Boolean_Field('PMD_logging', __("Logging On?", 'event_espresso'), false,false),
				'PMD_wp_user_id'=>new EE_Integer_Field('PMD_wp_user_Id', __("User ID", 'event_espresso'), false, 1),
				'PMD_open_by_default'=>new EE_Boolean_Field('PMD_open_by_default', __("Open by Default?", 'event_espresso'), false, false),
				'PMD_active'=>new EE_Boolean_Field('PMD_active', __("Active?", 'event_espresso'), false,true),
				'PMD_button_url'=>new EE_Plain_Text_Field('PMD_button_url', __("Button URL", 'event_espresso'), true,''),
				'PMD_preferred_currency'=>new EE_Plain_Text_Field('PMD_preferred_currency', __("Preferred Currency", 'event_espresso'), false, 'USD'),
				
			)
		);
		$this->_model_relations = array(
			'Price'=>new EE_Belongs_To_Relation(),
			'Event'=>new EE_HABTM_Relation('Event_Payment_Method'),
			'Payment'=>new EE_Has_Many_Relation(),
		);
		parent::__construct();
	}
}