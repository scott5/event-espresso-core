<?php if ( ! defined('EVENT_ESPRESSO_VERSION')) { exit('No direct script access allowed'); }

/**
 * This class is the signature for an object representing prepped message for queueing.
 *
 *
 * @package    Event Espresso
 * @subpackage messages
 * @author     Darren Ethier
 * @since      4.9.0
*/
class EE_Message_To_Generate {


	/**
	 * @type EE_Message_Resource_Manager $_message_resource_manager
	 */
	public $_message_resource_manager = null;

	/**
	 * @type EE_Messenger
	 */
	public $messenger = null;


	/**
	 * @type EE_Message_Type
	 */
	public $message_type = null;

	/**
	 * Identifier for the context the message is to be generated for.
	 * @type string
	 */
	public $context = '';

	/**
	 * Data that will be used to generate message.
	 * @type array
	 */
	public $data = array();

	/**
	 * Whether this message is for a preview or not.
	 * @type bool
	 */
	public $preview = false;

	/**
	 * @type EE_Message
	 */
	protected $_EE_Message;

	/**
	 * This is set by the constructor to indicate whether the incoming messenger
	 * and message type are valid.  This can then be checked by callers to determine whether
	 * to generate this message or not.
	 * @type bool
	 */
	protected $_valid = false;

	/**
	 * If there are any errors (non exception errors) they get added to this array for callers to decide
	 * how to handle.
	 * @type array
	 */
	protected $_error_msg = array();

	/**
	 * Can be accessed via the send_now() method, this is set in the validation
	 * routine via the EE_Messenger::send_now() method.
	 * @type bool
	 */
	protected $_send_now = false;

	/**
	 * Holds the classname for the data handler used by the current message type.
	 * This is set on the first call to the public `get_data_handler_class_name()` method.
	 * @type string
	 */
	protected $_data_handler_class_name = '';



	/**
	 * Constructor
	 *
	 * @param string                       $messenger    Slug representing messenger
	 * @param string                       $message_type Slug representing message type.
	 * @param mixed                        $data         Data used for generating message.
	 * @param null 						   $deprecated   used to be EE_Messages class
	 * @param string                       $context      Optional context to restrict message generated for.
	 * @param bool                         $preview      Whether this is being used to generate a preview or not.
	 * @param \EE_Message_Resource_Manager $message_resource_manager
	 */
	public function __construct(
		$messenger,
		$message_type,
		$data,
		$deprecated = null,
		$context = '',
		$preview = false,
		EE_Message_Resource_Manager $message_resource_manager
	) {
		$this->_message_resource_manager = $message_resource_manager;
		$this->data = is_array( $data ) ? $data : array( $data );
		$this->context = $context;
		$this->preview = $preview;
		//this immediately validates whether the given messenger/messagetype are active or not
		//and sets the valid flag.
		$this->_set_valid( $messenger, $message_type );
	}


	/**
	 * Validates messenger and message type and sets the related properties.
	 *
	 * NOTE: this sort of validation is no longer necessary if you have an  object available,
	 * 		 as you can now simply call one of the following on your EE_Message object to validate it:
	 *
	 *  * EE_Message::valid_messenger();
	 *  * EE_Message::valid_message_type()
	 *  * EE_Message::is_valid(); // validates both messenger and message type
	 *
	 * @param string $messenger_slug
	 * @param string $message_type_slug
	 */
	protected function _set_valid( $messenger_slug , $message_type_slug ) {
		$this->_valid = true;
		try {
			$this->messenger = $this->_message_resource_manager->valid_messenger( $messenger_slug );
			$this->_send_now = $this->messenger->send_now();
		} catch ( Exception $e ) {
			$this->_error_msg[] = $e->getMessage();
			$this->_valid = false;
		}
		try {
			$this->message_type = $this->_message_resource_manager->valid_message_type( $message_type_slug );
		} catch ( Exception $e ) {
			$this->_valid = false;
			$this->_error_msg[] = $e->getMessage();
		}
	}


	/**
	 * Simply returns the state of the $_valid property.
	 * @return bool
	 */
	public function valid() {
		return $this->_valid;
	}



	public function send_now() {
		return $this->_send_now;
	}



	/**
	 *  Returns an instantiated EE_Message object from the internal data.
	 */
	public function get_EE_Message() {
		if ( ! $this->valid() ) {
			return null;
		}
		if ( $this->_EE_Message instanceof EE_Message ) {
			return $this->_EE_Message;
		}
		$this->_EE_Message = EE_Message_Factory::create(
			array(
				'MSG_messenger' => $this->messenger->name,
				'MSG_message_type' => $this->message_type->name,
				'MSG_context' => $this->context,
				'STS_ID' => EEM_Message::status_incomplete,
				'MSG_priority' => $this->_get_priority_for_message_type()
			)
		);
		return $this->_EE_Message;
	}



	/**
	 * This returns the data_handler class name for the internal message type set.
	 * Note: this also verifies that the data handler class exists.  If it doesn't then $_valid is set to false
	 * and the data_handler_class name is set to an empty string.
	 *
	 * @param   bool    $preview    Used to indicate that the preview data handler is to be returned.
	 * @return  string
	 */
	public function get_data_handler_class_name( $preview = false ) {
		if ( $this->_data_handler_class_name === '' && $this->valid() ) {
			$ref = $preview ? 'Preview' : $this->message_type->get_data_handler( $this->data );
			//make sure internal data is updated.
			$this->data = $this->message_type->get_data();

			//verify
			$this->_data_handler_class_name = EE_Message_To_Generate::verify_and_retrieve_class_name_for_data_handler_reference( $ref );
			if ( $this->_data_handler_class_name === '' ) {
				$this->_valid = false;
			}
		}
		return $this->_data_handler_class_name;
	}



	/**
	 * Validates the given string as a reference for an existing, accessible data handler and returns the class name
	 * For the handler the reference matches.
	 *
	 * @param string $data_handler_reference
	 * @return string
	 */
	public static function verify_and_retrieve_class_name_for_data_handler_reference( $data_handler_reference ) {
		$class_name = 'EE_Messages_' . $data_handler_reference . '_incoming_data';
		if ( ! class_exists( $class_name ) ) {
			EE_Error::add_error(
				sprintf(
					__(
						'The included data handler reference (%s) does not match any valid, accessible, "EE_Messages_incoming_data" classes.  Looking for %s.',
						'event_espresso'
					),
					$data_handler_reference,
					$class_name
				),
				__FILE__,
				__FUNCTION__,
				__LINE__
			);
			$class_name = ''; //clear out class_name so caller knows this isn't valid.
		}
		return $class_name;
	}



	/**
	 * Returns what the message type has set as a priority.
	 * @return  int   EEM_Message priority.
	 */
	protected function _get_priority_for_message_type() {
		return $this->send_now() ? EEM_Message::priority_high : $this->message_type->get_priority();
	}


} //end class EE_Message_To_Generate