<?php
if (!defined('EVENT_ESPRESSO_VERSION'))
	exit('NO direct script access allowed');

/**
 * Event Espresso
 *
 * Event Registration and Management Plugin for Wordpress
 *
 * @package		Event Espresso
 * @author		Seth Shoultes
 * @copyright	(c)2009-2012 Event Espresso All Rights Reserved.
 * @license		http://eventespresso.com/support/terms-conditions/  ** see Plugin Licensing **
 * @link		http://www.eventespresso.com
 * @version		4.0
 *
 * ------------------------------------------------------------------------
 *
 * Events_Admin_Page
 *
 * This contains the logic for setting up the Events related pages.  Any methods without phpdoc comments have inline docs with parent class. 
 *
 *
 * @package		Events_Admin_Page
 * @subpackage	includes/core/admin/Events_Admin_Page.core.php
 * @author		Darren Ethier
 *
 * ------------------------------------------------------------------------
 */
class Events_Admin_Page extends EE_Admin_Page_CPT {

	/**
	 * _event
	 * This will hold the event object for event_details screen.
	 *
	 * @access protected
	 * @var object
	 */
	protected $_event;


	/**
	 * This will hold the event model instance
	 * @var object
	 */
	protected $_event_model;


	protected function _init_page_props() {
		require_once( 'EEM_Event.model.php' );
		$this->page_slug = EVENTS_PG_SLUG;
		$this->page_label = EVENTS_LABEL;
		$this->_cpt_model_name = 'EEM_Event';
		$this->_event_model = EEM_Event::instance();
	}

	protected function _ajax_hooks() {
		//todo: all hooks for events ajax goes in here.
	}

	protected function _define_page_props() {
		$this->_admin_base_url = EVENTS_ADMIN_URL;
		$this->_admin_page_title = EVENTS_LABEL;
		$this->_labels = array(
			'buttons' => array(
				'add' => __('Add New Event', 'event_espresso'),
				'edit' => __('Edit Event', 'event_espresso'),
				'delete' => __('Delete Event', 'event_espresso')
			),
			'editor_title' => __('Enter event title here', 'event_espresso'),
			'publishbox' => __('Save Event', 'event_espresso')
		);
	}

	protected function _set_page_routes() {
		//load formatter helper
		require_once EVENT_ESPRESSO_PLUGINFULLPATH . '/helpers/EE_Formatter.helper.php';

		//load field generator helper
		require_once EVENT_ESPRESSO_PLUGINFULLPATH . '/helpers/EE_Form_Fields.helper.php';

		//the model is used a lot so let's just require it.
		require_once( $this->_cpt_model_name . '.model.php' );

		$this->_page_routes = array(
			'default' => '_events_overview_list_table',
			'copy_event' => array(
				'func' => '_copy_events',
				'noheader' => true
			),
			'trash_event' => array(
				'func' => '_trash_or_restore_event',
				'args' => array('event_status' => 'trash'),
				'noheader' => true
			),
			'trash_events' => array(
				'func' => '_trash_or_restore_events',
				'args' => array('event_status' => 'trash'),
				'noheader' => true
			),
			'restore_event' => array(
				'func' => '_trash_or_restore_event',
				'args' => array('event_status' => 'draft'),
				'noheader' => true
			),
			'restore_events' => array(
				'func' => '_trash_or_restore_events',
				'args' => array('event_status' => 'draft'),
				'noheader' => true
			),
			'delete_event' => array(
				'func' => '_delete_event',
				'noheader' => true
			),
			'delete_events' => array(
				'func' => '_delete_events',
				'noheader' => true
			),
			'view_report' => '_view_report',
			'export_events' => array(
				'func' => '_events_export',
				'noheader' => true
			),
			'import' => '_import_events',
			'import_events' => '_import_events',
			'default_event_settings' => '_default_event_settings',
			'update_default_event_settings' => array(
				'func' => '_update_default_event_settings',
				'noheader' => TRUE,
			),
		);
	}

	protected function _set_page_config() {

		$default_espresso_boxes = $this->_default_espresso_metaboxes;
		$default_espresso_boxes[] = '_espresso_sponsors_post_box';

		$this->_page_config = array(
			'default' => array(
				'nav' => array(
					'label' => __('Overview', 'event_espresso'),
					'order' => 10
				),
				'list_table' => 'Events_Admin_List_Table'
			),
			'import_events' => array(
				'nav' => array(
					'label' => __('Import', 'event_esprsso'),
					'order' => 30
				),
				'metaboxes' => $default_espresso_boxes
			),
			'create_new' => array(
				'nav' => array(
					'label' => __('Add Event', 'event_espresso'),
					'order' => 5,
					'persistent' => false
				),
				'metaboxes' => array('_publish_post_box', '_register_event_editor_meta_boxes'),
				'help_tabs' => array(
					'event_date_info' => array(
						'title' => __('Event Date', 'event_espresso'),
						'callback' => 'event_date_info_help_tab'
					),
					'reg_date_info' => array(
						'title' => __('Registration Dates/Times', 'event_espresso'),
						'callback' => 'reg_date_info_help_tab'
					)
				)
			),
			'edit' => array(
				'nav' => array(
					'label' => __('Edit Event', 'event_espresso'),
					'order' => 5,
					'persistent' => false,
					'url' => isset($this->_req_data['id']) ? add_query_arg(array('id' => $this->_req_data['id']), $this->_current_page_view_url) : $this->_admin_base_url
				),
				'metaboxes' => array('_publish_post_box', '_register_event_editor_meta_boxes'),
				'help_tabs' => array(
					'event_date_info' => array(
						'title' => __('Event Date', 'event_espresso'),
						'callback' => 'event_date_info_help_tab'
					),
					'reg_date_info' => array(
						'title' => __('Registration Dates/Times', 'event_espresso'),
						'callback' => 'reg_date_info_help_tab'
					)
				)
			),
			'default_event_settings' => array(
				'nav' => array(
					'label' => __('Default Settings', 'event_esprsso'),
					'order' => 40
				),
				'metaboxes' => array_merge($default_espresso_boxes, array('_publish_post_box')),
				'labels' => array(
					'publishbox' => __('Update Settings', 'event_espresso')
				),
				'help_tabs' => array(
					'events_expire_on_reg_end_date_help_tab' => array(
						'title' => __('Events Expire on Reg End Date', 'event_espresso'),
						'callback' => 'events_expire_on_reg_end_date_help_tab'
					),
					'default_payment_status_help_tab' => array(
						'title' => __('Default Payment Status', 'event_espresso'),
						'callback' => 'default_payment_status_help_tab'
					)
				)
			)
		);
	}

	protected function _add_screen_options() {
		//todo
	}

	protected function _add_screen_options_default() {
		$this->_per_page_screen_option();
	}

	protected function _add_feature_pointers() {
		//todo
	}

	/**
	 * 		default_event_settings_help_tab
	 * 		@access public
	 * 		@return void
	 */
	public function default_event_settings_help_tab($tab_name) {
		require_once( EVENTS_TEMPLATE_PATH . 'default_event_settings_help_tab.template.php' );
		$template = call_user_func($tab_name . '_html');
		espresso_display_template($template, array());
	}

	public function events_expire_on_reg_end_date_help_tab() {
		$this->default_event_settings_help_tab(__FUNCTION__);
	}

	public function default_payment_status_help_tab() {
		$this->default_event_settings_help_tab(__FUNCTION__);
	}

	/**
	 * event edit help tabs
	 * @access public
	 * @return void
	 */
	public function event_edit_help_tab($tab_name) {
		require_once EVENTS_TEMPLATE_PATH . 'event_edit_help_tab.template.php';
		$template = call_user_func($tab_name . '_html');
		espresso_display_template($template, array());
	}

	public function event_date_info_help_tab() {
		$this->event_edit_help_tab(__FUNCTION__);
	}

	public function reg_date_info_help_tab() {
		$this->event_edit_help_tab(__FUNCTION__);
	}

	public function load_scripts_styles() {

		wp_register_style('events-admin-css', EVENTS_ASSETS_URL . 'events-admin-page.css', array(), EVENT_ESPRESSO_VERSION);
		wp_enqueue_style('events-admin-css');
		//todo note: we also need to load_scripts_styles per view (i.e. default/view_report/event_details
		//registers for all views
		//scripts
		wp_register_script('event_editor_js', EVENTS_ASSETS_URL . 'event_editor.js', array('ee_admin_js', 'jquery-ui-slider', 'jquery-ui-timepicker-addon'), EVENT_ESPRESSO_VERSION, TRUE);
	}

	/**
	 * enqueuing scripts and styles specific to this view
	 * @return void
	 */
	public function load_scripts_styles_create_new() {
		$this->load_scripts_styles_edit();
	}

	/**
	 * enqueuing scripts and styles specific to this view
	 * @return void 
	 */
	public function load_scripts_styles_edit() {
		//styles
		wp_enqueue_style('jquery-ui-style');
		wp_enqueue_style('jquery-ui-style-datepicker-css');

		//scripts
		wp_register_script('event_editor_js', EVENTS_ASSETS_URL . 'event_editor.js', array('ee_admin_js', 'jquery-ui-slider', 'jquery-ui-timepicker-addon', 'jquery-validate'), EVENT_ESPRESSO_VERSION, TRUE);
		wp_enqueue_script('event_editor_js');

		global $eei18n_js_strings;
		$eei18n_js_strings['image_confirm'] = __('Do you really want to delete this image? Please remember to update your event to complete the removal.', 'event_espresso');
		wp_localize_script('event_editor_js', 'eei18n', $eei18n_js_strings);
	}

	//nothing needed for events with these methods.
	public function admin_init() {}
	public function admin_notices() {}
	public function admin_footer_scripts() {}




	protected function _set_list_table_views_default() {
		$this->_views = array(
			'all' => array(
				'slug' => 'all',
				'label' => __('View All Events', 'event_espresso'),
				'count' => 0,
				'bulk_action' => array(
					'export_events' => __('Export Events', 'event_espresso'),
					'restore_events' => __('Restore from Trash', 'event_espresso'),
					'trash_events' => __('Move to Trash', 'event_espresso'),
					'delete_events' => __('Delete Permanently', 'event_espresso'),
//					'export_payments' => __('Export Payments', 'event_espresso')
				)
			),
			'today' => array(
				'slug' => 'today',
				'label' => __('Today', 'event_espresso'),
				'count' => 0,
				'bulk_action' => array(
					'export_events' => __('Export Events', 'event_espresso'),
					'restore_events' => __('Restore from Trash', 'event_espresso'),
					'trash_events' => __('Move to Trash', 'event_espresso'),
					'delete_events' => __('Delete Permanently', 'event_espresso'),
//					'export_payments' => __('Export Payments', 'event_espresso')
				)
			),
			'month' => array(
				'slug' => 'month',
				'label' => __('This Month', 'event_espresso'),
				'count' => 0,
				'bulk_action' => array(
					'export_events' => __('Export Events', 'event_espresso'),
					'restore_events' => __('Restore from Trash', 'event_espresso'),
					'trash_events' => __('Move to Trash', 'event_espresso'),
					'delete_events' => __('Delete Permanently', 'event_espresso'),
//					'export_payments' => __('Export Payments', 'event_espresso')
				)
			)
		);
	}

	protected function _event_legend_items() {
		$items = array(
			'view_details' => array(
				'icon' => EVENT_ESPRESSO_PLUGINFULLURL . 'images/magnifier.png',
				'desc' => __('View Event', 'event_espresso')
			),
			'edit_event' => array(
				'icon' => EVENT_ESPRESSO_PLUGINFULLURL . 'images/calendar_edit.png',
				'desc' => __('Edit Event Details', 'event_espresso')
			),
			'view_attendees' => array(
				'icon' => EVENT_ESPRESSO_PLUGINFULLURL . 'images/group.png',
				'desc' => __('View Registrations for Event', 'event_espresso')
			),
			'event_shortcode' => array(
				'icon' => EVENT_ESPRESSO_PLUGINFULLURL . 'images/tag.png',
				'desc' => __('Get ShortURL/Shortcode for Event', 'event_espresso')
			),
			'excel_export' => array(
				'icon' => EVENT_ESPRESSO_PLUGINFULLURL . 'images/excel_icon.png',
				'desc' => __('Export Event details to excel', 'event_espresso')
			),
			'csv_export' => array(
				'icon' => EVENT_ESPRESSO_PLUGINFULLURL . 'images/csv_icon_sm.gif',
				'desc' => __('Export Event details to csv', 'event_espresso')
			)
		);
		return apply_filters('FHEE_event_legend_items', $items);
	}




	
	/**
	 * Adds extra buttons to the WP CPT permalink field row. 
	 *
	 * Method is called from parent and is hooked into the wp 'get_sample_permalink_html' filter.
	 * @param  string $return    the current html
	 * @param  int    $id        the post id for the page
	 * @param  string $new_title What the title is
	 * @param  string $new_slug  what the slug is
	 * @return string            The new html string for the permalink area
	 */
	public function extra_permalink_field_buttons( $return, $id, $new_title, $new_slug ) {
		//make sure this is only when editing
		if ( !empty( $id ) ) {
			$post = get_post( $id );
			$return .= '<a class="button button-small" onclick="prompt(\'Shortcode:\', jQuery(\'#shortcode\').val()); return false;" href="#"  tabindex="-1">' . __('Shortcode', 'event_espresso') . '</a> ';
			$return .= '<input id="shortcode" type="hidden" value="[SINGLEEVENT single_event_id=\'' . $post->post_name . '\']"">';
		}
		return $return;
	}




	/**
	 * _events_overview_list_table
	 * This contains the logic for showing the events_overview list
	 *
	 * @access protected
	 * @return string html for generated table
	 */
	protected function _events_overview_list_table() {
		do_action('AHEE_log', __FILE__, __FUNCTION__, '');
		$this->_template_args['after_list_table'] = $this->_display_legend($this->_event_legend_items());
		$this->_admin_page_title .= $this->_get_action_link_or_button('create_new', 'add', array(), 'button add-new-h2');
		$this->display_admin_list_table_page_with_no_sidebar();
	}


	/**
	 * this allows for extra misc actions in the default WP publish box
	 * @return string html to add
	 */
	public function extra_misc_actions_publish_box() {
		$this->_generate_publish_box_extra_content();
	}



	
	protected function _insert_update_cpt_item( $post_id, $post ) {

		$wheres = array( $this->_event_model->primary_key_name() => $post_id );

		$event_values = array(
			'EVT_is_active' => isset($this->_req_data['is_active']) ? 1 : 0,
			'EVT_display_desc' => isset( $this->_req_data['display_desc'] ) ? 1 : 0,
			'EVT_display_reg_form' => isset( $this->_req_data['display_reg_form'] ) ? 1 : 0,
			'EVT_reg_limit' => !empty( $this->_req_data['reg_limit'] ) ? $this->_req_data['reg_limit'] : NULL,
			'EVT_allow_multiple' => isset( $this->_req_data['allow_multiple'] ) ? 1 : 0,
			'EVT_additional_limit' => !empty( $this->_req_data['additional_limit'] ) ? $this->_req_data['additional_limit'] : NULL,
			'EVT_require_pre_approval' => isset( $this->_req_data['require_pre_approval'] ) ? 1 : 0,
			'EVT_member_only' => isset( $this->_req_data['member_only'] ) ? 1 : 0,
			'EVT_allow_overflow' => isset( $this->_req_data['EVT_allow_overflow'] ) ? 1 : 0,
			'EVT_timezone_string' => !empty( $this->_req_data['timezone_string'] ) ? $this->_req_data['timezone_string'] : NULL,
			'EVT_external_URL' => !empty( $this->_req_data['externalURL'] ) ? $this->_req_data['externalURL'] : NULL,
			'EVT_phone' => !empty( $this->_req_data['event_phone'] ) ? $this->_req_data['event_phone'] : NULL
			);

		//update event
		$success = $this->_event_model->update( $event_values, array($wheres) );


		//get event_object for other metaboxes... though it would seem to make sense to just use $this->_event_model->get_one_by_ID( $post_id ).. i have to setup where conditions to override the filters in the model that filter out autodraft and inherit statuses so we GET the inherit id!
		$get_one_where = array( $this->_event_model->primary_key_name() => $post_id, 'STS_ID' => $post->post_status );
		$event = $this->_event_model->get_one( array($get_one_where) );


		//the following are default callbacks for event attachment updates that can be overridden by caffeinated functionality and/or addons.
		$event_update_callbacks = apply_filters( 'FHEE_event_editor_update', array( array($this, '_default_venue_update' ), array( $this, '_default_dtt_update' ), array( $this, '_default_prices_update') ) );

		$att_success = TRUE;

		foreach ( $event_update_callbacks as $e_callback ) {
			$_succ = call_user_func_array( $e_callback, array( $event,  $this->_req_data ) );
			$att_success = !$att_success ? $att_success : $_succ; //if ANY of these updates fail then we want the appropriate global error message
		}

		//any errors?
		if ( $success && !$att_success ) {
			EE_Error::add_error( __('Event Details saved successfully but something went wrong with saving attachments.', 'event_espresso'), __FILE__, __FUNCTION__, __LINE__ );
		} else if ( $success === FALSE ) {
			EE_Error::add_error( __('Event Details did not save successfully.', 'event_espresso'), __FILE__, __FUNCTION__, __LINE__ );
		}
	}
	


	
	/**
	 * Attach the venue to the Event
	 * @param  object $evtobj Event Object to add the venue to
	 * @param  array  $data   The request data from the form
	 * @return bool           Success or fail.
	 */
	protected function _default_venue_update( $evtobj, $data ) {
		require_once( 'EEM_Venue.model.php' );
		$venue_model = EEM_Venue::instance();
		$rows_affected = NULL;
		$venue_id = !empty( $data['venue_id'] ) ? $data['venue_id'] : NULL;

		$venue_array = array(
				'VNU_wp_user' => $evtobj->get('EVT_wp_user'), 
				'VNU_name' => !empty( $data['venue_title'] ) ? $data['venue_title'] : NULL,
				'VNU_desc' => !empty( $data['venue_description'] ) ? $data['venue_description'] : NULL,
				'VNU_identifier' => !empty( $data['venue_identifier'] ) ? $data['venue_identifier'] : NULL,
				'VNU_short_desc' => !empty( $data['venue_short_description'] ) ? $data['venue_short_description'] : NULL,
				'VNU_address' => !empty( $data['address'] ) ? $data['address'] : NULL,
				'VNU_address2' => !empty( $data['address2'] ) ? $data['address2'] : NULL,
				'VNU_city' => !empty( $data['city'] ) ? $data['city'] : NULL,
				'STA_ID' => !empty( $data['state'] ) ? $data['state'] : NULL,
				'CNT_ISO' => !empty( $data['countries'] ) ? $data['countries'] : NULL,
				'STS_ID' => $evtobj->status(),
				'VNU_zip' => !empty( $data['zip'] ) ? $data['zip'] : NULL,
				'VNU_phone' => !empty( $data['venue_phone'] ) ? $data['venue_phone'] : NULL,
				'VNU_capacity' => !empty( $data['venue_capacity'] ) ? $data['venue_capacity'] : NULL,
				'VNU_url' => !empty($data['venue_url'] ) ? $data['venue_url'] : NULL,
				'VNU_virtual_phone' => !empty($data['virtual_phone']) ? $data['virtual_phone'] : NULL,
				'VNU_virtual_url' => !empty( $data['virtual_url'] ) ? $data['virtual_url'] : NULL,
				'VNU_enable_for_gmap' => isset( $data['enable_for_gmap'] ) ? 1 : 0,
				'STS_ID' => 'publish'
			);
		

		//if we've got the venue_id then we're just updating the exiting venue so let's do that and then get out.
		if ( !empty( $venue_id ) ) {
			$update_where = array( $venue_model->primary_key_name() => $venue_id );
			$rows_affected = $venue_model->update( $venue_array, array( $update_where ) );
			//we've gotta make sure that the venue is always attached to a revision.. add_relation_to should take care of making sure that the relation is already present.
			$evtobj->_add_relation_to( $venue_id, 'Venue' );
			return $rows_affected > 0 ? TRUE : FALSE;
		} else {	
			//if this is a revision then we are going to handle the initial insert/update and then the add_relation_to which will also automatically add the relation to the parent.  NOTE... we also have to allow for if users have turned OFF revisions!
		
			if ( $evtobj->post_type() == 'revision' || ! WP_POST_REVISIONS ) {
				$venue_id = $venue_model->insert( $venue_array );
				$evtobj->_add_relation_to( $venue_id, 'Venue' );
				return !empty( $venue_id ) ? TRUE : FALSE;
			}
		}
		return TRUE; //when we have the ancestor come in it's already been handled by the revision save.
	}






	/**
	 * Attach the Datetimes to the Event
	 * @param  object $evtobj Event Object to add the datetime(s) to
	 * @param  array  $data   The request data from the form
	 * @return bool           success or fail
	 */
	protected function _default_dtt_update( $evtobj, $data ) {
		$timezone = isset( $data['EVT_timezone_string'] ) ? $data['EVT_timezone_string'] : NULL;
		$success = TRUE;

		$q=1;
		foreach ( $data['event_datetimes'] as $row => $event_datetime ) {
			$event_datetime['evt_end'] = isset($event_datetime['evt_end']) && ! empty( $event_datetime['evt_end'] ) ? $event_datetime['evt_end'] : $event_datetime['evt_start'];
			$event_datetime['reg_end'] = isset($event_datetime['reg_end']) && ! empty( $event_datetime['reg_end'] ) ? $event_datetime['reg_end'] : $event_datetime['reg_start'];
			$DTM = EE_Datetime::new_instance( array(
					'DTT_ID' => isset( $event_datetime['ID'] ) ? absint( $event_datetime['ID'] ) : NULL,
					'DTT_EVT_start' => strtotime( $event_datetime['evt_start'] ),
					'DTT_EVT_end' => strtotime($event_datetime['evt_end']),
					'DTT_REG_start' => strtotime($event_datetime['reg_start']),
					'DTT_REG_end' => strtotime($event_datetime['reg_end']),
						'DTT_is_primary' => $row == 1 ? TRUE : FALSE,
				),
				$timezone);
			$works = $evtobj->_add_relation_to( $DTM, 'Datetime' );
			$success = !$success ? $success : $works; //if ANY of these updates fail then we want the appropriate global error message
		}
		return $success;
	}





	/**
	 * Attach the price(s) to the Event (note decaf only adds one price but we're adding the handling for multiple prices here)
	 * @param  object $evtobj Event Object to add the price(s) to
	 * @param  array  $data   The request data from the form
	 * @return bool           success or fail.
	 */
	protected function _default_prices_update( $evtobj, $data ) {

		$timezone = isset( $data['EVT_timezone_string'] ) ? $data['EVT_timezone_string'] : NULL;
		$success = TRUE;

		$data['price_count'] = 1;
		$ticket_prices_to_save = array();
		$quick_edit_ticket_price = isset($data['quick_edit_ticket_price']) ? $data['quick_edit_ticket_price'] : array();
//			echo printr( $quick_edit_ticket_price, '$quick_edit_ticket_price' );
		if ( isset( $quick_edit_ticket_price['XXXXXX'] )) {
			$new_quick_price = $quick_edit_ticket_price['XXXXXX'];
			if ( isset( $new_quick_price['PRC_name'] ) && ! empty( $new_quick_price['PRC_name'] ) && isset( $new_quick_price['PRC_amount'] )) {
				$ticket_prices_to_save[] = array(
					'PRT_ID' => 2,
					'PRT_is_global' => FALSE,
					'PRC_overrides' => 0,
					'PRC_deleted' => FALSE,
					'PRC_order' => isset( $new_quick_price['PRC_order'] ) && $new_quick_price['PRC_order'] ? $new_quick_price['PRC_order'] : 0,
					'PRC_name' => $new_quick_price['PRC_name'] ? $new_quick_price['PRC_name'] : NULL,
					'PRC_desc' => NULL,
					'PRC_amount' => $new_quick_price['PRC_amount'] ? $new_quick_price['PRC_amount'] : 0,
					'PRC_use_dates' => FALSE,
					'PRC_start_date' => NULL,
					'PRC_end_date' => NULL,
					'PRC_is_active' => TRUE			
				);
			}
		}
		
		// grab list of edited ticket prices
		if ($edited_ticket_price_IDs = isset($data['edited_ticket_price_IDs']) ? $data['edited_ticket_price_IDs'] : FALSE) {
			// remove last comma
			$edited_ticket_price_IDs = trim($edited_ticket_price_IDs, ',');
			// create array of edited ticket prices
			$edited_ticket_price_IDs = explode(',', $edited_ticket_price_IDs);
			// flipper once
			$edited_ticket_price_IDs = array_flip($edited_ticket_price_IDs);
			// flipper twice - hey!?!?! where did all the duplicate entries go???
			$edited_ticket_price_IDs = array_flip($edited_ticket_price_IDs);
//				echo printr( $edited_ticket_price_IDs, '$edited_ticket_price_IDs' );
			// grab existing ticket price data
			if ($edited_ticket_prices = isset($data['edit_ticket_price']) ? $data['edit_ticket_price'] : FALSE) {
//					echo printr( $edited_ticket_prices, '$edited_ticket_prices' );
				// cycle thru list                    
				foreach ($edited_ticket_prices as $PRC_ID => $edited_ticket_price) {
//						echo printr( $edited_ticket_price, '$edited_ticket_price' );	
					// add edited ticket prices to list of ticket prices to save
					if (in_array($PRC_ID, $edited_ticket_price_IDs)) {
//							echo printr( $quick_edit_ticket_price[$PRC_ID], '$quick_edit_ticket_price[$PRC_ID]' );
						if ( isset( $quick_edit_ticket_price[$PRC_ID] ) && is_array( $quick_edit_ticket_price[$PRC_ID] )) {
							$edited_ticket_price = array_merge( $edited_ticket_price, $quick_edit_ticket_price[$PRC_ID] );
//								echo printr( $edited_ticket_price, '$edited_ticket_price' );	
						}
						$ticket_prices_to_save[$PRC_ID] = $edited_ticket_price;
					}
				}
			}
		}
		
//			echo printr( $ticket_prices_to_save, '$ticket_prices_to_save' );	

		// add new tickets if any
		if ($new_ticket_price = isset($data['new_ticket_price']) ? $data['new_ticket_price'] : array('PRC_name' => NULL)) {
			if ( ! empty( $new_ticket_price['PRC_amount'] ) && ! empty( $new_ticket_price['PRC_name'] )) {
				$ticket_prices_to_save[0] = $new_ticket_price;
			} else if ( empty( $new_ticket_price['PRC_amount'] ) && ! empty( $new_ticket_price['PRC_name'] )) {
				$msg = __( 'Event prices require an amount before they can be saved. Please make sure you enter an amount for the new event price before attempting to save it.', 'event_espresso' );
				EE_Error::add_error( $msg, __FILE__, __FUNCTION__, __LINE__ );		
			} else if ( ! empty( $new_ticket_price['PRC_amount'] ) && empty( $new_ticket_price['PRC_name'] )) {
				$msg = __( 'Event prices require a name before they can be saved. Please make sure you enter a name for the new event price before attempting to save it.', 'event_espresso' );
				EE_Error::add_error( $msg, __FILE__, __FUNCTION__, __LINE__ );		
			}

		}
//		printr( $ticket_prices_to_save, '$ticket_prices_to_save  <br /><span style="font-size:10px;font-weight:normal;">' . __FILE__ . '<br />line no: ' . __LINE__ . '</span>', 'auto' );

		// and now we actually save the ticket prices
		if (!empty($ticket_prices_to_save)) {

			//echo printr( $new_ticket_price, '$new_ticket_price' );
			require_once('EE_Price.class.php');

			global $current_user;
			get_currentuserinfo();

			foreach ($ticket_prices_to_save as $PRC_ID => $ticket_price) {


				//determine whether this price overrides an existing global or not
				$overrides = absint($ticket_price['PRT_is_global']) ? $PRC_ID : NULL;
//echo '<br/><br/><h4>$overrides : ' . $overrides . '  <span style="margin:0 0 0 3em;font-size:10px;font-weight:normal;">( file: '. __FILE__ . ' - line no: ' . __LINE__ . ' )</span></h4>';
				// or whether it was already overriding a global from before
				$overrides = $ticket_price['PRC_overrides'] ? absint($ticket_price['PRC_overrides']) : $overrides;
//echo '<h4>$overrides : ' . $overrides . '  <span style="margin:0 0 0 3em;font-size:10px;font-weight:normal;">( file: '. __FILE__ . ' - line no: ' . __LINE__ . ' )</span></h4>';

				$PRC = EE_Price::new_instance( array(
						'PRC_ID' => $PRC_ID,
						'PRT_ID' => $ticket_price['PRT_ID'],
						'PRC_amount' => preg_replace( '/[^0-9,.]/', '', $ticket_price['PRC_amount'] ),
						'PRC_name' => $ticket_price['PRC_name'],
						'PRC_desc' => $ticket_price['PRC_desc'],
						'PRC_reg_limit' => isset( $ticket_price['PRC_reg_limit'] ) ? $ticket_price['PRC_reg_limit'] : NULL,
						'PRC_use_dates' => $ticket_price['PRC_use_dates'] ? TRUE : FALSE,
						'PRC_start_date' => $ticket_price['PRC_start_date'],
						'PRC_end_date' => $ticket_price['PRC_end_date'],
						'PRC_is_active' => $ticket_price['PRC_is_active'] ? TRUE : FALSE,
						'PRC_overrides' => $overrides,
						'PRC_order' => $ticket_price['PRT_ID'] < 3 ? 0 : $ticket_price['PRC_order'],
						'PRC_deleted' => $ticket_price['PRC_deleted']
					), 
					$timezone );
				
				if ( $PRC->deleted()) {
					$data['price_count']--;
				} else {
					$data['price_count']++;
				}
				
				$works = $evtobj->_add_relation_to( $PRC, 'Price' );
				$success = !$success ? $success : $works; //if ANY of these updates fail then we want the appropriate global error message

			}
		}

		if ( isset( $data['price_count'] ) && absint( $data['price_count'] ) < 1 ) {
			$espresso_no_ticket_prices = get_option( 'espresso_no_ticket_prices', array() );
			$espresso_no_ticket_prices[ $evtobj->get('EVT_ID') ] = $evtobj->get('EVT_name');
			update_option( 'espresso_no_ticket_prices', $espresso_no_ticket_prices );
		} 

		return $success;
	}




	/**
	 * 	_generate_publish_box_extra_content
	 * 	@access private
	 * @return void
	 */
	private function _generate_publish_box_extra_content() {

		//load formatter helper
  		require_once EVENT_ESPRESSO_PLUGINFULLPATH . '/helpers/EE_Formatter.helper.php';
		// publish box
		$publish_box_extra_args['view_attendees_url'] = add_query_arg(array('action' => 'default', 'event_id' => $this->_cpt_model_obj->ID() ), REG_ADMIN_URL);
		$publish_box_extra_args['attendees_reg_limit'] = $this->_cpt_model_obj->get_number_of_attendees_reg_limit( 'num_attendees_slash_reg_limit' );
		$publish_box_extra_args['misc_pub_section_class'] = apply_filters('FHEE_event_editor_email_attendees_class', 'misc-pub-section');
		//$publish_box_extra_args['email_attendees_url'] = add_query_arg(array('event_admin_reports' => 'event_newsletter', 'event_id' => $this->_cpt_model_obj->id), 'admin.php?page=espresso_registrations');
		$publish_box_extra_args['event_editor_overview_add'] = do_action('AHEE_cpt_model_obj_editor_overview_add', $this->_cpt_model_obj);
		// load template
		espresso_display_template( EVENTS_TEMPLATE_PATH . 'event_publish_box_extras.template.php', $publish_box_extra_args );
	}





	/**
	 * This just returns whatever is set as the _event object property
	 *
	 * //todo this will become obsolete once the models are in place
	 * @return object
	 */
	public function get_event_object() {
		return $this->_cpt_model_obj;
	}



	/*	 * ************ */
	/** METABOXES * */

	/**
	 * _register_event_editor_meta_boxes
	 * add all metaboxes related to the event_editor
	 * 
	 * @return [type] [description]
	 */
	protected function _register_event_editor_meta_boxes() {


		add_meta_box('espresso_event_editor_date_time', __('Dates &amp; Times', 'event_espresso'), array($this, 'date_time_metabox'), $this->page_slug, 'normal', 'high');

		add_meta_box('espresso_event_editor_pricing', __('Event Pricing', 'event_espresso'), array($this, 'pricing_metabox'), $this->page_slug, 'normal', 'core');

		add_meta_box('espresso_event_editor_event_options', __('Event Registration Options', 'event_espresso'), array($this, 'registration_options_meta_box'), $this->page_slug, 'side', 'default');

		add_meta_box('espresso_event_editor_venue', __('Venue Details', 'event_espresso'), array( $this, 'venue_metabox' ), $this->page_slug, 'normal', 'core');	


		//note if you're looking for other metaboxes in here, where a metabox has a related management page in the admin you will find it setup in the related management page's "_Hooks" file.  i.e. messages metabox is found in "espresso_events_Messages_Hooks.class.php".
	}

	public function date_time_metabox() {
		global $org_options;

		$event_id = is_object( $this->_cpt_model_obj ) ? $this->_cpt_model_obj->ID() : NULL;
		$timezone = is_object( $this->_cpt_model_obj ) ? $this->_cpt_model_obj->timezone_string() : NULL; 

		do_action('AHEE_log', __FILE__, __FUNCTION__, '');

		//	require_once(EVENT_ESPRESSO_INCLUDES_DIR . 'models/EEM_Ticket.model.php');
		//	$TKT_MDL = EEM_Ticket::instance();
		//	
		//	$all_event_tickets = $TKT_MDL->get_all_event_tickets( $event->id );


		require_once(EVENT_ESPRESSO_INCLUDES_DIR . 'models/EEM_Datetime.model.php');
		$DTM_MDL = EEM_Datetime::instance( $timezone );
		require_once EVENT_ESPRESSO_PLUGINFULLPATH . '/helpers/EE_DTT_helper.helper.php';

		global $times;
		// grab event times
		$times = $DTM_MDL->get_all_event_dates($event_id);
		// grab reg times
		//$reg_times = $DTM_MDL->get_all_reg_dates($this->_cpt_model_obj->ID());

		$template_args['datetime_IDs'] = array();
		$template_args['event_date_help_link'] = $this->_get_help_tab_link('event_date_info');
		$template_args['registration_date_help_link'] = $this->_get_help_tab_link('reg_date_info');
		$template_args['times'] = $times;
		$template_args['add_additional_time'] = apply_filters('FHEE_event_datetime_metabox_add_additional_date_time_template', '', $template_args);
		$template_args['org_options'] = $org_options;
		$template_args['current_time_help_link'] = $this->_get_help_tab_link('current_time_info');
		$template_args['current_date'] = date(get_option('date_format')) . ' ' . date(get_option('time_format'));
		$template_args['event_timezone'] = EE_DTT_helper::ddtimezone($this->$event_id);
		$template_args['use_event_timezones_template'] = apply_filters('FHEE_event_datetime_metabox_timezones_template', '', $template_args);
		$template_args['template_args'] = $template_args;

		$template = EVENTS_TEMPLATE_PATH . 'event_datetime_metabox_content.template.php';
		espresso_display_template($template, $template_args);
	}

	public function pricing_metabox() {
		global $org_options;

		$timezone = is_object( $this->_cpt_model_obj ) ? $this->_cpt_model_obj->timezone_string() : NULL; 
		$event_id = is_object( $this->_cpt_model_obj ) ? $this->_cpt_model_obj->ID() : NULL;

		require_once(EVENT_ESPRESSO_INCLUDES_DIR . 'models/EEM_Price_Type.model.php');
		$PRT = EEM_Price_Type::instance();

		require_once(EVENT_ESPRESSO_INCLUDES_DIR . 'models/EEM_Price.model.php');
		$PRC = EEM_Price::instance();

		$show_no_event_price_msg = FALSE;		
		
		global $all_prices;
		
		if ( ! $all_prices = $PRC->get_all_event_prices_for_admin( $event_id )) {
			$all_prices = array();
		}
		
		if ( empty( $all_prices[1] ) && empty( $all_prices[2] )) {
			$show_no_event_price_msg = TRUE;
		}
		

		foreach ($PRT->get_all() as $type) {
			$all_price_types[] = array( 'id' => $type->ID(), 'text' => $type->name(), 'order' => $type->order() );
			if ( $type->is_global() ) {
				$global_price_types[ $type->ID() ] = $type;
			} else {
				$price_types[] = array( 'id' => $type->ID(), 'text' => $type->name(), 'order' => $type->order() );
			}						
		}
		
		$table_class = apply_filters('FHEE_pricing_table_class_filter', 'event_editor_pricing');

		$template_args['show_no_event_price_msg'] = $show_no_event_price_msg;
		$template_args['no_price_message_error'] = $show_no_event_price_msg ? __('There are currently no Prices set for this Event. Please see the Event Pricing section for more details.', 'event_espresso') : '';
		$template_args['no_price_message'] = $show_no_event_price_msg ? apply_filters('FHEE_show_no_event_price_msg', __('Please enter at lease one Event Price for this Event to ensure that this Event displays and functions properly.'), 'event_espresso') : ''; 
		$template_args['PRT'] =  $row_args['PRT'] = $PRT;
		$template_args['org_options'] = $row_args['org_options'] = $org_options;
		$template_args['event'] = $row_args['event'] = $this->_cpt_model_obj;
		$template_args['price_rows'] = array();
		$row_template = apply_filters('FHEE_events_pricing_meta_box_row_template', EVENTS_TEMPLATE_PATH . 'edit_event_price_metabox_content_row.template.php');
		if ( !empty( $all_prices ) ) :
			
			foreach ( $all_prices as $price_type => $prices ) :
				foreach ( $prices as $price ) :
					if ( !$price->deleted() ) :
						$row_args['disabled'] = ! $price->is_active() ? ' disabled="disabled"' : ''; 
						$row_args['disabled_class'] = ! $price->is_active() ? ' input-disabled' : '';
						$row_args['inactive'] = ! $price->is_active() ? '<span class="inactice-price">'.__('inactive price - edit advanced settings to reactivate', 'event_espresso').'</span>' : FALSE;
						if ( $price->use_dates() ){
							$today = time();
							if ( $today < $price->start_date( FALSE ) ){
								$price_date_status = '<a title="'. __('This Event Price option is not yet active', 'event_espresso') . '"><img src="'.EVENT_ESPRESSO_PLUGINFULLURL.'images/timer-pending-16x22.png" width="16" height="22" alt="'. __('This Event Price option is not yet active', 'event_espresso') . '" class="price-date-status-img"/></a>';					
							} elseif ( $today > $price->start_date( FALSE ) && $today < $price->end_date( FALSE ) ) {
								$price_date_status = '<a title="'. __('This Event Price option is currently active', 'event_espresso') . '"><img src="'.EVENT_ESPRESSO_PLUGINFULLURL.'images/timer-active-16x22.png" width="16" height="22" alt="'. __('This Event Price option is currently active', 'event_espresso') . '" class="price-date-status-img"/></a>';					
							} else {
								$price_date_status = '<a title="'. __('This Event Price option has expired', 'event_espresso') . '"><img src="'.EVENT_ESPRESSO_PLUGINFULLURL.'images/timer-expired-16x22.png" width="16" height="22" alt="'. __('This Event Price option has expired', 'event_espresso') . '" class="price-date-status-img"/></a>';
								$row_args['disabled'] = ' disabled="disabled"'; 
								$row_args['disabled_class'] = ' input-disabled'; 
								$row_args['inactive'] = '<span class="inactive-price">'.__('This Event Price option has expired - edit advanced settings to reactivate', 'event_espresso').'</span>';
							}
						} else {
							$price_date_status = '';
						}
						$price_type = $price->type_obj();
						$row_args['type_label'] = $price_type->name() . ' ' . $price_date_status;
						$row_args['price'] = $price;
						$row_args['price_amount'] = $price_type->is_percent() ? number_format( $price->amount(), 1 ) : number_format( $price->amount(), 2 );

						$select_name = 'edit_ticket_price['. $price->ID() .'][PRT_ID]';
						$row_args['edit_ticket_price_select'] =EE_Form_Fields::select_input( $select_name, $all_price_types, $price->type(), 'id="edit-ticket-price-type-ID-'.$price->ID().'" style="width:auto;"', 'edit-ticket-price-input' );
						$row_args['price_type'] = isset( $global_price_types[$price->type()] ) ? $global_price_types[$price->type()]->is_global() : FALSE;
						$row_args['price_amount'] = $price_type->is_percent() ? number_format( $price->amount(), 1 ) : number_format( $price->amount(), 2 );

						$row_args['counter'] = count($prices);
						$template_args['price_rows'][] = espresso_display_template($row_template, $row_args, TRUE);
					endif;
				endforeach;
			endforeach;
			else :
				$template_args['price_rows'][] = espresso_display_template($row_template, $row_args, TRUE);
			endif;
			$price_types = empty( $all_prices ) ? array(  array( 'id' => 2, 'text' => __('Event Price', 'event_espresso'), 'order' => 0 )) : $price_types;
			$template_args['new_ticket_price_selector'] = EE_Form_Fields::select_input( 'new_ticket_price[PRT_ID]', $price_types, 2, 'id="new-ticket-price-type-ID"', 'add-new-ticket-price-input' );
			$template_args['price_types'] = $price_types;

			$main_template = apply_filters('FHEE_events_pricing_meta_box_main_template', EVENTS_TEMPLATE_PATH . 'event_price_metabox_content.template.php' );
			espresso_display_template($main_template, $template_args);
	}




	public function registration_options_meta_box() {

		global $org_options;

		$yes_no_values = array(
			array('id' => true, 'text' => __('Yes', 'event_espresso')),
			array('id' => false, 'text' => __('No', 'event_espresso'))
		);
		$additional_attendee_reg_info_values = EEM_Event::additional_attendee_reg_info_array();
		$default_reg_status_values = EEM_Registration::reg_status_array();
		
		$template_args['is_active_select'] = EE_Form_Fields::select_input('is_active', $yes_no_values, $this->_cpt_model_obj->is_active());
		$template_args['_event'] = $this->_cpt_model_obj;
		$template_args['allow_group_reg_select'] = EE_Form_Fields::select_input('allow_multiple', $yes_no_values, $this->_cpt_model_obj->allow_multiple(), 'id="group-reg"', '', false);
		$template_args['additional_limit'] = $this->_cpt_model_obj->additional_limit();
		$template_args['default_registration_status'] = EE_Form_Fields::select_input('default_reg_status', $default_reg_status_values, $this->_cpt_model_obj->default_registration_status());
		$template_args['display_description'] = EE_Form_Fields::select_input('display_desc', $yes_no_values, $this->_cpt_model_obj->display_description());
		$template_args['display_registration_form'] = EE_Form_Fields::select_input('display_reg_form', $yes_no_values, $this->_cpt_model_obj->display_reg_form(), '', '', false);
		$template_args['additional_registration_options'] = apply_filters('FHEE_additional_registration_options_event_edit_page', '', $template_args, $yes_no_values, $additional_attendee_reg_info_values, $default_reg_status_values);
		$templatepath = EVENTS_TEMPLATE_PATH . 'event_registration_options.template.php';
		espresso_display_template($templatepath, $template_args);
	}



	/**
	 * decaf venue metabox
	 * @return string form for Event Venue
	 */
	public function venue_metabox() {
		global $org_options;
		$values = array(
			array('id' => true, 'text' => __('Yes', 'event_espresso')),
			array('id' => false, 'text' => __('No', 'event_espresso'))
		);

		//states and countries model
		require_once( 'EEM_State.model.php' );
		require_once( 'EEM_Country.model.php');

		$states = EEM_State::instance()->get_all_active_states();
		$countries = EEM_Country::instance()->get_all_active_countries();

		//prepare state/country arrays
		foreach ( $states as $id => $obj ) {
			$st_ary[$id] = $obj->name();
		}

		foreach ( $countries as $id => $obj ) {
			$ctry_ary[$id] = $obj->name();
		}

		require_once( 'EEM_Venue.model.php' );
		//first let's see if we have a venue already
		$evnt_id = $this->_cpt_model_obj->ID();
		$venue = !empty( $evnt_id ) ? $this->_cpt_model_obj->venues() : NULL;
		$venue = empty( $venue ) ? EEM_Venue::instance()->create_default_object() : array_shift( $venue );
		$template_args['_venue'] = $venue;
		$template_args['org_options'] = $org_options;
		$template_args['states_dropdown'] = EE_Form_Fields::select_input('state', $st_ary, $venue->state_ID(), 'id="phys-state"');
		$template_args['countries_dropdown'] = EE_Form_Fields::select_input('countries', $ctry_ary, $venue->country_ID(), 'id="phys-country"');
		$template_args['enable_for_gmap'] = EE_Form_Fields::select_input('enable_for_gmap', $values, $venue->enable_for_gmap(), 'id="enable_for_gmap"');
		$template_path = EVENTS_TEMPLATE_PATH . 'event_venues_metabox_content.template.php';
		espresso_display_template( $template_path, $template_args );
	}

		

	/** end metaboxes * */
	/*	 * **************** *	

	


	/**
	 * _get_events()
	 * This method simply returns all the events (for the given _view and paging)
	 *
	 * @access public
	 *
	 * @param int $per_page count of items per page (20 default);
	 * @param int $current_page what is the current page being viewed.
	 * @param bool $count if TRUE then we just return a count of ALL events matching the given _view.  If FALSE then we return an array of event objects that match the given _view and paging parameters.
	 * @return array an array of event objects.
	 */
	public function get_events($per_page = 10, $current_page = 1, $count = FALSE) {
		global $wpdb, $org_options;

		$EEME = $this->_event_model;



		$offset = ($current_page - 1) * $per_page;
		$limit = $count ? '' : $offset . ',' . $per_page;
		$orderby = isset($this->_req_data['orderby']) ? $this->_req_data['orderby'] : 'EVT_name';
		$order = isset($this->_req_data['order']) ? $this->_req_data['order'] : "DESC";

		if (isset($this->_req_data['month_range'])) {
			$pieces = explode(' ', $this->_req_data['month_range'], 3);
			$month_r = !empty($pieces[0]) ? $pieces[0] : '';
			$year_r = !empty($pieces[1]) ? $pieces[1] : '';
		}

		$where = array(
				'STS_ID' => isset( $this->_req_data['event_status'] ) && $this->_req_data['event_status'] != '' ? $this->_req_data['event_status'] : 'publish',
				//todo add event categories
				'Datetime.DTT_is_primary' => 1,
		);

		//date where conditions
		if (isset($this->_req_data['month_range']) && $this->_req_data['month_range'] != '') {
			$where['DTT_EVT_start'] = array('BETWEEN', array( strtotime($year_r . '-' . $month_r . '-01'), strtotime($year_r . '-' . $month_r . '-31') ) );
		} else if (isset($this->_req_data['status']) && $this->_req_data['status'] == 'today') {
			$where['DTT_EVT_start'] = array('BETWEEN', array( strtotime(date('Y-m-d') . ' 0:00:00'), strtotime(date('Y-m-d') . ' 23:59:59') ) );
		} else if ( isset($this->_req_data['status']) && $this->_req_data['status'] == 'month' ) {
			$this_year_r = date('Y');
			$this_month_r = date('m');
			$days_this_month = date('t');
			$where['DTT_EVT_start'] = array( 'BETWEEN', array( strtotime($this_year_r . '-' . $this_month_r . '-01'), strtotime($this_year_r . '-' . $this_month_r . '-' . $days_this_month) ) );
		}

		$force_join = array('Venue', 'Datetime');

		$events = $count ? $EEME->count( array( $where, 'force_join' => $force_join, ), 'EVT_ID' ) : $EEME->get_all( array( $where, 'force_join' => $force_join, 'limit' => $limit, 'order_by' => $orderby, 'order' => $order, 'group_by' => 'EVT_ID' ) );


		return $events;
	}




	//handling for WordPress CPT actions (trash, restore, delete)
	public function trash_cpt_item( $post_id ) {
		$this->_req_data['EVT_ID'] = $post_id;
		$this->_trash_or_restore_event( 'trash', FALSE );
	}




	public function restore_cpt_item( $post_id ) {
		$this->_req_data['EVT_ID'] = $post_id;
		$this->_trash_or_restore_event( 'draft', FALSE );
	}


	public function delete_cpt_item( $post_id ) {
		$this->_req_data['EVT_ID'] = $post_id;
		$this->_delete_event( FALSE );
	}



	/**
	 * _trash_or_restore_event
	 *
	 * @access protected
	 * @param  string $event_status 
	 * @return void 
	 */
	protected function _trash_or_restore_event($event_status = 'trash', $redirect_after = TRUE ) {
		//determine the event id and set to array.
		$EVT_ID = isset($this->_req_data['EVT_ID']) ? absint($this->_req_data['EVT_ID']) : FALSE;
		// loop thru events
		if ($EVT_ID) {
			// clean status
			$event_status = strtoupper(sanitize_key($event_status));
			// grab status
			if (!empty($event_status)) {
				$success = $this->_change_event_status($EVT_ID, $event_status);
			} else {
				$success = FALSE;
				$msg = __('An error occured. The event could not be moved to the trash because a valid event status was not not supplied.', 'event_espresso');
				EE_Error::add_error($msg, __FILE__, __FUNCTION__, __LINE__);
			}
		} else {
			$success = FALSE;
			$msg = __('An error occured. The event could not be moved to the trash because a valid event ID was not not supplied.', 'event_espresso');
			EE_Error::add_error($msg, __FILE__, __FUNCTION__, __LINE__);
		}
		$action = $event_status == 'trash' ? 'moved to the trash' : 'restored from the trash';

		if ( $redirect_after )
			$this->_redirect_after_action($success, 'Event', $action, array('action' => 'default'));
	}

	/**
	 * _trash_or_restore_events
	 *
	 * @access protected
	 * @param  string $event_status 
	 * @return void 
	 */
	protected function _trash_or_restore_events($event_status = 'trash') {
		// clean status
		$event_status = strtoupper(sanitize_key($event_status));
		// grab status
		if (!empty($event_status)) {
			$success = TRUE;
			//determine the event id and set to array.
			$EVT_IDs = isset($this->_req_data['EVT_IDs']) ? (array) $this->_req_data['EVT_IDs'] : array();
			// loop thru events
			foreach ($EVT_IDs as $EVT_ID) {
				if ($EVT_ID = absint($EVT_ID)) {
					$results = $this->_change_event_status($EVT_ID, $event_status);
					$success = $results !== FALSE ? $success : FALSE;
				} else {
					$msg = sprintf(__('An error occured. Event #%d could not be moved to the trash because a valid event ID was not not supplied.', 'event_espresso'), $EVT_ID);
					EE_Error::add_error($msg, __FILE__, __FUNCTION__, __LINE__);
					$success = FALSE;
				}
			}
		} else {
			$success = FALSE;
			$msg = __('An error occured. The event could not be moved to the trash because a valid event status was not not supplied.', 'event_espresso');
			EE_Error::add_error($msg, __FILE__, __FUNCTION__, __LINE__);
		}
		// in order to force a pluralized result message we need to send back a success status greater than 1
		$success = $success ? 2 : FALSE;
		$action = $event_status == 'trash' ? 'moved to the trash' : 'restored from the trash';
		$this->_redirect_after_action($success, 'Events', $action, array('action' => 'default'));
	}

	/**
	 * _trash_or_restore_events
	 *
	 * @access  private
	 * @param  int $event_id 
	 * @param  string $event_status 
	 * @return void
	 */
	private function _change_event_status($EVT_ID = FALSE, $event_status = FALSE) {
		// grab event id
		if (!$EVT_ID) {
			$msg = __('An error occured. No Event ID or an invalid Event ID was received.', 'event_espresso');
			EE_Error::add_error($msg, __FILE__, __FUNCTION__, __LINE__);
			return FALSE;
		}

		$this->_set_model_object( $EVT_ID );

		// clean status
		$event_status = strtoupper(sanitize_key($event_status));
		// grab status
		if (empty($event_status)) {
			$msg = __('An error occured. No Event Status or an invalid Event Status was received.', 'event_espresso');
			EE_Error::add_error($msg, __FILE__, __FUNCTION__, __LINE__);
			return FALSE;
		}
		
		// was event trashed or restored ?
		switch ($event_status) {
			case 'draft' :
				$action = 'restored from the trash';
				$hook = 'AHEE_event_restored_from_trash';
				break;
			case 'trash' :
				$action = 'moved to the trash';
				$hook = 'AHEE_event_moved_to_trash';
				break;
			default :
				$action = 'updated';
				$hook = FALSE;
		}
		//use class to change status
		$this->_cpt_model_obj->set_status( $event_status );
		$success = $this->_cpt_model_obj->save();
		
		if ($success === FALSE) {
			$msg = sprintf(__('An error occured. The event could not be %s.', 'event_espresso'), $action);
			EE_Error::add_error($msg, __FILE__, __FUNCTION__, __LINE__);
			return FALSE;
		}
		if ($hook) {
			do_action($hook);
		}
		return TRUE;
	}

	/**
	 * _delete_event
	 *
	 * @access protected
	 * @return void 
	 */
	protected function _delete_event( $redirect_after = TRUE ) {
		//determine the event id and set to array.
		$EVT_ID = isset($this->_req_data['EVT_ID']) ? absint($this->_req_data['EVT_ID']) : NULL;
		$EVT_ID = isset( $this->_req_data['id'] ) ? absint( $this->_req_data['id'] ) : NULL;


		// loop thru events
		if ($EVT_ID) {
			$success = $this->_permanently_delete_event( $EVT_ID );
			// get list of events with no prices
			$espresso_no_ticket_prices = get_option('espresso_no_ticket_prices', array());
			// remove this event from the list of events with no prices
			if (isset($espresso_no_ticket_prices[$EVT_ID])) {
				unset($espresso_no_ticket_prices[$EVT_ID]);
			}
			update_option('espresso_no_ticket_prices', $espresso_no_ticket_prices);
		} else {
			$success = FALSE;
			$msg = __('An error occured. An event could not be deleted because a valid event ID was not not supplied.', 'event_espresso');
			EE_Error::add_error($msg, __FILE__, __FUNCTION__, __LINE__);
		}
		if ( $redirect_after )
			$this->_redirect_after_action($success, 'Event', 'deleted', array('action' => 'default'));
	}

	/**
	 * _delete_events
	 *
	 * @access protected
	 * @return void 
	 */
	protected function _delete_events() {
		$succes = TRUE;
		// get list of events with no prices
		$espresso_no_ticket_prices = get_option('espresso_no_ticket_prices', array());
		//determine the event id and set to array.
		$EVT_IDs = isset($this->_req_data['EVT_IDs']) ? (array) $this->_req_data['EVT_IDs'] : array();
		// loop thru events
		foreach ($EVT_IDs as $EVT_ID) {
			if ($EVT_ID = absint($EVT_ID)) {
				$results = $this->_permanently_delete_event($EVT_ID);
				$succes = $results !== FALSE ? $succes : FALSE;
				// remove this event from the list of events with no prices
				if (isset($espresso_no_ticket_prices[$EVT_ID])) {
					unset($espresso_no_ticket_prices[$EVT_ID]);
				}
			} else {
				$succes = FALSE;
				$msg = __('An error occured. An event could not be deleted because a valid event ID was not not supplied.', 'event_espresso');
				EE_Error::add_error($msg, __FILE__, __FUNCTION__, __LINE__);
			}
		}
		update_option('espresso_no_ticket_prices', $espresso_no_ticket_prices);
		// in order to force a pluralized result message we need to send back a success status greater than 1
		$succes = $succes ? 2 : FALSE;
		$this->_redirect_after_action($succes, 'Events', 'deleted', array('action' => 'default'));
	}

	/**
	 * _permanently_delete_event
	 *
	 * @access  private
	 * @param  int $EVT_ID 
	 * @return void
	 */
	private function _permanently_delete_event($EVT_ID = FALSE) {
		// grab event id
		if (!$EVT_ID = absint($EVT_ID)) {
			$msg = __('An error occured. No Event ID or an invalid Event ID was received.', 'event_espresso');
			EE_Error::add_error($msg, __FILE__, __FUNCTION__, __LINE__);
			return FALSE;
		}
		
		
		$this->_set_model_object( $EVT_ID );
		$success = $this->_cpt_model_obj->delete();
		// did it all go as planned ?
		if ($success) {
			$msg = sprintf(__('Event ID # %d has been deleted.', 'event_espresso'), $EVT_ID);
			EE_Error::add_success($msg);
		} else {
			$msg = sprintf(__('An error occured. Event ID # %d could not be deleted.', 'event_espresso'), $EVT_ID);
			EE_Error::add_error($msg, __FILE__, __FUNCTION__, __LINE__);
			return FALSE;
		}
		do_action('AHEE_event_permanently_deleted');
		return TRUE;
	}





	/**
	 * espresso_event_months_dropdown			
	 * This is copied (and slightly modified) from the same named function in EE core legacy.
	 * 
	 * @param  string $current_value current month range value
	 * @return string                dropdown listing month/year selections for events.
	 */
	public function espresso_event_months_dropdown($current_value = '') {
		global $wpdb;
		$SQL = "SELECT DTT_EVT_start as e_date FROM " . $wpdb->prefix . "esp_datetime GROUP BY YEAR(FROM_UNIXTIME(DTT_EVT_start)), MONTH(FROM_UNIXTIME(DTT_EVT_start))";

		$dates = $wpdb->get_results($SQL);

		if ($wpdb->num_rows > 0) {
			echo '<select name="month_range" class="wide">';
			echo '<option value="">' . __('Select a Month/Year', 'event_espresso') . '</option>';
			foreach ($dates as $row) {
				$option_date = date_i18n('M Y', $row->e_date);
				echo '<option value="' . $option_date . '"';
				echo $option_date == $current_value ? ' selected="selected=selected"' : '';
				echo '>' . $option_date . '</option>' . "\n";
			}
			echo "</select>";
		} else {
			_e('No Results', 'event_espresso');
		}
	}

	/**
	 * get tal number of events
	 *
	 * @access public
	 * @return int 
	 */
	public function total_events() {

		global $wpdb;

		//Dates
		$curdate = date('Y-m-d');
		$this_year_r = date('Y');
		$this_month_r = date('m');
		$days_this_month = date('t');

		$group = '';
		if (function_exists('espresso_member_data') && espresso_member_data('role') == 'espresso_group_admin') {
			$group = get_user_meta(espresso_member_data('id'), "espresso_group", true);
			$group = unserialize($group);
			if (!empty($group)) {
				$group = implode(",", $group);
			}
		}

		$sql1 = "(";
		if ($group != '') {
			$sql1 .= "SELECT e.id FROM " . EVENTS_DETAIL_TABLE . " e ";
			$sql1 .= " JOIN " . EVENTS_VENUE_REL_TABLE . " r ON r.event_id = e.id ";
			$sql1 .= " JOIN " . EVENTS_LOCALE_REL_TABLE . " l ON  l.venue_id = r.venue_id ";
			$sql1 .= " WHERE event_status != 'D'";
			$sql1 .=!empty($group) ? " AND l.locale_id IN (" . $group . ") " : '';
			$sql1 .= ") UNION (";
		}
		$sql1 .= "SELECT e.id FROM " . EVENTS_DETAIL_TABLE . " e ";
		$sql1 .= " WHERE event_status != 'D'";
		if (function_exists('espresso_member_data') && ( espresso_member_data('role') == 'espresso_event_manager' || espresso_member_data('role') == 'espresso_group_admin')) {
			$sql1 .= " AND wp_user = '" . espresso_member_data('id') . "' ";
		}
		$sql1 .= ")";
		$total_events = 0;
		if ($wpdb->query($sql1)) {
			$total_events = $wpdb->num_rows;
		}
		return $total_events;
	}

	/**
	 * get total number of events today
	 *
	 * @access public
	 * @return int 
	 */
	public function total_events_today() {
		global $wpdb;

		//Dates
		$curdate = date('Y-m-d');
		$this_year_r = date('Y');
		$this_month_r = date('m');
		$days_this_month = date('t');
		$start = ' 00:00:00';
		$end = ' 23:59:59';

		$sql2 = "(";
		if (!empty($group)) {
			$sql2 .= "SELECT e.id FROM " . EVENTS_DETAIL_TABLE . " e ";
			$sql2 .= " JOIN " . ESP_DATETIME_TABLE . " dtt ON dtt.EVT_ID = e.id ";
			$sql2 .= " JOIN " . EVENTS_VENUE_REL_TABLE . " r ON r.event_id = e.id ";
			$sql2 .= " JOIN " . EVENTS_LOCALE_REL_TABLE . " l ON  l.venue_id = r.venue_id ";
			$sql2 .= " WHERE e.event_status != 'D'";
			$sql2 .= " AND dtt.DTT_EVT_start BETWEEN '" . strtotime(date('Y-m-d') . $start) . "' AND '" . strtotime(date('Y-m-d') . $end) . "' ";
			$sql2 .= $group != '' ? " AND l.locale_id IN (" . $group . ") " : '';
			$sql2 .= ") UNION (";
		}
		$sql2 .= "SELECT e.id FROM " . EVENTS_DETAIL_TABLE . " e ";
		$sql2 .= " JOIN " . ESP_DATETIME_TABLE . " dtt ON dtt.EVT_ID = e.id ";
		$sql2 .= " WHERE e.event_status != 'D'";
		$sql2 .= " AND dtt.DTT_EVT_start BETWEEN '" . strtotime(date('Y-m-d') . $start) . "' AND '" . strtotime(date('Y-m-d') . $end) . "' ";

		if (function_exists('espresso_member_data') && ( espresso_member_data('role') == 'espresso_event_manager' || espresso_member_data('role') == 'espresso_group_admin')) {
			$sql2 .= " AND e.wp_user = '" . espresso_member_data('id') . "' ";
		}
		$sql2 .= ")";
		$total_events_today = 0;
		if ($wpdb->query($sql2)) {
			$total_events_today = $wpdb->num_rows;
		}
		return $total_events_today;
	}

	/**
	 * get total number of events this month
	 *
	 * @access public
	 * @return int 
	 */
	public function total_events_this_month() {
		global $wpdb;

		//Dates
		$curdate = date('Y-m-d');
		$this_year_r = date('Y');
		$this_month_r = date('m');
		$days_this_month = date('t');
		$start = ' 00:00:00';
		$end = ' 23:59:59';

		$sql3 = "(";
		if (!empty($group)) {
			$sql3 .= "SELECT e.id FROM " . EVENTS_DETAIL_TABLE . " e ";
			$sql3 .= " JOIN " . ESP_DATETIME_TABLE . " dtt ON dtt.EVT_ID = e.id ";
			$sql3 .= " JOIN " . EVENTS_VENUE_REL_TABLE . " r ON r.event_id = e.id ";
			$sql3 .= " JOIN " . EVENTS_LOCALE_REL_TABLE . " l ON  l.venue_id = r.venue_id ";
			$sql3 .= " WHERE event_status != 'D'";
			$sql3 .= " AND dtt.DTT_EVT_start BETWEEN '" . strtotime($this_year_r . '-' . $this_month_r . '-01' . $start) . "' AND '" . strtotime($this_year_r . '-' . $this_month_r . '-' . $days_this_month . $end) . "' ";

			$sql3 .= $group != '' ? " AND l.locale_id IN (" . $group . ") " : '';
			$sql3 .= ") UNION (";
		}
		$sql3 .= "SELECT e.id FROM " . EVENTS_DETAIL_TABLE . " e ";
		$sql3 .= " JOIN " . ESP_DATETIME_TABLE . " dtt ON dtt.EVT_ID = e.id ";
		$sql3 .= " WHERE event_status != 'D'";
		$sql3 .= " AND dtt.DTT_EVT_start BETWEEN '" . strtotime($this_year_r . '-' . $this_month_r . '-01' . $start) . "' AND '" . strtotime($this_year_r . '-' . $this_month_r . '-' . $days_this_month . $end) . "' ";

		if (function_exists('espresso_member_data') && ( espresso_member_data('role') == 'espresso_event_manager' || espresso_member_data('role') == 'espresso_group_admin')) {
			$sql3 .= " AND wp_user = '" . espresso_member_data('id') . "' ";
		}
		$sql3 .= ")";
		//echo $sql3;
		$wpdb->query($sql3);
		$total_events_this_month = 0;
		if ($wpdb->query($sql3)) {
			$total_events_this_month = $wpdb->num_rows;
		}
		return $total_events_this_month;
	}




	/**
	 * 	_default_event_settings
	 * 
	 * 	This generates the Default Settings Tab
	 * 
	 * 	@return string html for the settings page
	 */
	protected function _default_event_settings() {

		global $org_options;
		$this->_template_args['values'] = $this->_yes_no_values;

		$this->_template_args['org_options'] = isset($org_options['org_options']) ? maybe_unserialize($org_options['org_options']) : FALSE;
		$this->_template_args['expire_on_registration_end'] = isset($org_options['expire_on_registration_end']) ? absint($org_options['expire_on_registration_end']) : FALSE;

		$this->_template_args['reg_status_array'] = $this->_get_reg_status_array(array('RCN', 'RNA'));
		$this->_template_args['default_reg_status'] = isset($org_options['default_reg_status']) ? sanitize_text_field($org_options['default_reg_status']) : 'RPN';
		$this->_template_args['pending_counts_reg_limit'] = isset($org_options['pending_counts_reg_limit']) ? sanitize_text_field($org_options['pending_counts_reg_limit']) : TRUE;

		$this->_template_args['use_attendee_pre_approval'] = isset($org_options['use_attendee_pre_approval']) ? absint($org_options['use_attendee_pre_approval']) : FALSE;

		$this->_template_args['template_args'] = $this->_template_args;

		$this->_set_add_edit_form_tags('update_default_event_settings');
		$this->_set_publish_post_box_vars(NULL, FALSE, FALSE, NULL, FALSE);
		$this->_template_args['admin_page_content'] = espresso_display_template(EVENTS_TEMPLATE_PATH . 'event_settings.template.php', $this->_template_args, TRUE);
		$this->display_admin_page_with_sidebar();
	}

	/**
	 * 		_update_default_event_settings
	 * 		@access protected
	 * 		@return array
	 */
	protected function _update_default_event_settings() {

		$data = array();
		$data['expire_on_registration_end'] = isset($this->_req_data['expire_on_registration_end']) ? absint($this->_req_data['expire_on_registration_end']) : FALSE;
		$data['default_reg_status'] = isset($this->_req_data['default_reg_status']) ? sanitize_text_field($this->_req_data['default_reg_status']) : 'RPN';
		$data['pending_counts_reg_limit'] = isset($this->_req_data['pending_counts_reg_limit']) ? absint($this->_req_data['pending_counts_reg_limit']) : TRUE;
		$data['use_attendee_pre_approval'] = isset($this->_req_data['use_attendee_pre_approval']) ? absint($this->_req_data['use_attendee_pre_approval']) : TRUE;

		$data = apply_filters('FHEE_default_event_settings_save', $data);

		$what = 'Default Event Settings';
		$success = $this->_update_organization_settings($what, $data, __FILE__, __FUNCTION__, __LINE__);
		$this->_redirect_after_action($success, $what, 'updated', array('action' => 'default_event_settings'));
	}

	

	/**
	 * _events_export
	 * Will export all (or just the given event) to a Excel compatible file.
	 * 
	 * @access protected
	 * @return file 
	 */
	protected function _events_export() {

		//todo: I don't like doing this but it'll do until we modify EE_Export Class.
		$new_request_args = array(
			'export' => 'report',
			'action' => 'all_event_data',
			'event_id' => $this->_req_data['EVT_ID'],
		);
		$this->_req_data = array_merge($this->_req_data, $new_request_args);

		if (file_exists(EVENT_ESPRESSO_INCLUDES_DIR . 'classes/EE_Export.class.php')) {
			require_once(EVENT_ESPRESSO_INCLUDES_DIR . 'classes/EE_Export.class.php');
			$EE_Export = EE_Export::instance($this->_req_data);
			$EE_Export->export();
		}
	}

	/**
	 * _payment_export
	 * Will export payments for events to an excel file (or for given events)
	 * @return file?
	 */
	protected function _payment_export() {

		//todo: I don't like doing this but it'll do until we modify EE_Export Class.
		$new_request_args = array(
			'export' => 'report',
			'action' => 'payment',
			'type' => 'csv',
			'event_id' => $this->_req_data['EVT_ID'],
		);
		$this->_req_data = array_merge($this->_req_data, $new_request_args);
		if (file_exists(EVENT_ESPRESSO_INCLUDES_DIR . 'classes/EE_Export.class.php')) {
			require_once(EVENT_ESPRESSO_INCLUDES_DIR . 'classes/EE_Export.class.php');
			$EE_Export = EE_Export::instance();
			$EE_Export->export();
		}
	}

	/**
	 * _import_events
	 * This handles displaying the screen and running imports for importing events.
	 * 	
	 * @return string html
	 */
	protected function _import_events() {

		require_once(EVENT_ESPRESSO_INCLUDES_DIR . 'classes/EE_Import.class.php');

		//first check if we've got an incoming import
		if (isset($this->_req_data['import']) && $this->_req_data['import'] == 'csv') {
			EE_Import::instance()->import();
		}

		$title = __('Import Events', 'event_espresso');
		$intro = __('If you have a previously exported list of Event Details in a Comma Separated Value (CSV) file format, you can upload the file here: ', 'event_espresso');
		$form_url = EVENTS_ADMIN_URL;
		$action = 'import_events';
		$type = 'csv';
		$content = EE_Import::instance()->upload_form($title, $intro, $form_url, $action, $type);

		$this->_admin_page_title .= $this->_get_action_link_or_button('add_event', 'add', array(), 'button add-new-h2');
		$this->_template_args['admin_page_content'] = $content;
		$this->display_admin_page_with_sidebar();
	}

}
//end class Events_Admin_Page