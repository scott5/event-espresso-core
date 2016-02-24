<?php if ( ! defined('EVENT_ESPRESSO_VERSION')) { exit('No direct script access allowed'); }

/**
 * This class is used for managing and interacting with the EE_messages Queue.  An instance
 * of this object is used for interacting with a specific batch of EE_Message objects.
 *
 * @package    Event Espresso
 * @subpackage messages
 * @author     Darren Ethier
 * @since      4.9.0
 */
class EE_Messages_Queue {


	/**
	 * @type    string  reference for sending action
	 */
	const action_sending = 'sending';

	/**
	 * @type    string  reference for generation action
	 */
	const action_generating = 'generation';



	/**
	 * @type EE_Message_Repository $_queue
	 */
	protected $_queue;

	/**
	 * Sets the limit of how many messages are generated per process.
	 * @type int
	 */
	protected $_batch_count;

	/**
	 * Sets the limit of how many messages can be sent per hour.
	 * @type int
	 */
	protected $_rate_limit;

	/**
	 * This is an array of cached queue items being stored in this object.
	 * The array keys will be the ID of the EE_Message in the db if saved.  If the EE_Message
	 * is not saved to the db then its key will be an increment of "UNS" (i.e. UNS1, UNS2 etc.)
	 * @type EE_Message[]
	 */
	protected $_cached_queue_items;

	/**
	 * Tracks the number of unsaved queue items.
	 * @type int
	 */
	protected $_unsaved_count = 0;

	/**
	 * used to record if a do_messenger_hooks has already been called for a message type.  This prevents multiple
	 * hooks getting fired if users have setup their action/filter hooks to prevent duplicate calls.
	 *
	 * @type array
	 */
	protected $_did_hook = array();



	/**
	 * Constructor.
	 * Setup all the initial properties and load a EE_Message_Repository.
	 *
	 * @param \EE_Message_Repository       $message_repository
	 */
	public function __construct( EE_Message_Repository $message_repository ) {
		$this->_batch_count = apply_filters( 'FHEE__EE_Messages_Queue___batch_count', 50 );
		$this->_rate_limit = $this->get_rate_limit();
		$this->_queue = $message_repository;
	}



	/**
	 * Add a EE_Message object to the queue
	 *
	 * @param EE_Message    $message
	 * @param array         $data     This will be an array of data to attach to the object in the repository.  If the
	 *                                object is persisted, this data will be saved on an extra_meta object related to
	 *                                EE_Message.
	 * @param  bool         $preview  Whether this EE_Message represents a preview or not.
	 * @param  bool         $test_send This indicates whether to do a test send instead of actual send. A test send will
	 *                                 use the messenger send method but typically is based on preview data.
	 * @return bool          Whether the message was successfully added to the repository or not.
	 */
	public function add( EE_Message $message, $data = array(), $preview = false, $test_send = false ) {
		$data['preview'] = $preview;
		$data['test_send'] = $test_send;
		return $this->_queue->add( $message, $data );
	}




	/**
	 * Removes EE_Message from _queue that matches the given EE_Message if the pointer is on a matching EE_Message
	 * @param EE_Message    $message    The message to detach from the queue
	 * @param bool          $persist    This flag indicates whether to attempt to delete the object from the db as well.
	 * @return bool
	 */
	public function remove( EE_Message $message, $persist = false ) {
		if ( $persist && $this->_queue->current() !== $message ) {
			//get pointer on right message
			if ( $this->_queue->has( $message ) ) {
				$this->_queue->rewind();
				while( $this->_queue->valid() ) {
					if ( $this->_queue->current() === $message ) {
						break;
					}
					$this->_queue->next();
				}
			} else {
				return false;
			}
		}
		return $persist ? $this->_queue->delete() : $this->_queue->remove( $message );
	}




	/**
	 * Persists all queued EE_Message objects to the db.
	 * @return array()  @see EE_Messages_Repository::saveAll() for return values.
	 */
	public function save() {
		return $this->_queue->saveAll();
	}





	/**
	 * @return EE_Message_Repository
	 */
	public function get_queue() {
		return $this->_queue;
	}




	/**
	 * This does the following things:
	 * 1. Checks if there is a lock on generation (prevents race conditions).  If there is a lock then exits (return false).
	 * 2. If no lock, sets lock, then retrieves a batch of non-generated EE_Message objects and adds to queue
	 * 3. Returns bool.  True = batch ready.  False = no batch ready (or nothing available for generation).
	 *
	 * Note: Callers should make sure they release the lock otherwise batch generation will be prevented from continuing.
	 *       The lock is on a transient that is set to expire after one hour as a fallback in case locks are not removed.
	 *
	 * @return bool  true if successfully retrieved batch, false no batch ready.
	 */
	public function get_batch_to_generate() {
		if ( $this->is_locked( EE_Messages_Queue::action_generating ) ) {
			return false;
		}

		//lock batch generation to prevent race conditions.
		$this->lock_queue( EE_Messages_Queue::action_generating );

		$query_args = array(
			// key 0 = where conditions
			0 => array( 'STS_ID' => EEM_Message::status_incomplete ),
			'order_by' => $this->_get_priority_orderby(),
			'limit' => $this->_batch_count
		);
		$messages = EEM_Message::instance()->get_all( $query_args );

		if ( ! $messages ) {
			return false; //nothing to generate
		}

		foreach ( $messages as $message ) {
			if ( $message instanceof EE_Message ) {
				$data = $message->all_extra_meta_array();
				$this->add( $message, $data );
			}
		}
		return true;
	}


	/**
	 * This does the following things:
	 * 1. Checks if there is a lock on sending (prevents race conditions).  If there is a lock then exits (return false).
	 * 2. Grabs the allowed number of messages to send for the rate_limit.  If cannot send any more messages, then return false.
	 * 2. If no lock, sets lock, then retrieves a batch of EE_Message objects, adds to queue and triggers execution.
	 * 3. On success or unsuccessful send, sets status appropriately.
	 * 4. Saves messages via the queue
	 * 5. Releases lock.
	 *
	 * @return bool  true on success, false if something preventing sending (i.e. lock set).  Note: true does not necessarily
	 *               mean that all messages were successfully sent.  It just means that this method successfully completed.
	 *               On true, client may want to call $this->count_STS_in_queue( EEM_Message::status_failed ) to see if
	 *               any failed EE_Message objects.  Each failed message object will also have a saved error message on it
	 *               to assist with notifying user.
	 */
	public function get_to_send_batch_and_send() {
		if ( $this->is_locked( EE_Messages_Queue::action_sending ) || $this->_rate_limit < 1 ) {
			return false;
		}

		$this->lock_queue( EE_Messages_Queue::action_sending );

		$batch = $this->_batch_count < $this->_rate_limit ? $this->_batch_count : $this->_rate_limit;

		$query_args = array(
			// key 0 = where conditions
			0 => array( 'STS_ID' => array( 'IN', EEM_Message::instance()->stati_indicating_to_send() ) ),
			'order_by' => $this->_get_priority_orderby(),
			'limit' => $batch
		);

		$messages_to_send = EEM_Message::instance()->get_all( $query_args );


		//any to send?
		if ( ! $messages_to_send ) {
			$this->unlock_queue( EE_Messages_Queue::action_sending );
			return false;
		}

		//add to queue.
		foreach ( $messages_to_send as $message ) {
			if ( $message instanceof EE_Message ) {
				$this->add( $message );
			}
		}

		//send messages  (this also updates the rate limit)
		$this->execute();

		//release lock
		$this->unlock_queue( EE_Messages_Queue::action_sending );
		return true;
	}




	/**
	 * Locks the queue so that no other queues can call the "batch" methods.
	 *
	 * @param   string  $type   The type of queue being locked.
	 */
	public function lock_queue( $type = EE_Messages_Queue::action_generating ) {
		set_transient( $this->_get_lock_key( $type ), 1, $this->_get_lock_expiry( $type ) );
	}




	/**
	 * Unlocks the queue so that batch methods can be used.
	 *
	 * @param   string  $type   The type of queue being unlocked.
	 */
	public function unlock_queue( $type = EE_Messages_Queue::action_generating ) {
		delete_transient( $this->_get_lock_key( $type ) );
	}




	/**
	 * Retrieve the key used for the lock transient.
	 * @param string $type  The type of lock.
	 * @return string
	 */
	protected function _get_lock_key( $type = EE_Messages_Queue::action_generating ) {
		return '_ee_lock_' . $type;
	}




	/**
	 * Retrieve the expiry time for the lock transient.
	 * @param string $type  The type of lock
	 * @return int   time to expiry in seconds.
	 */
	protected function _get_lock_expiry( $type = EE_Messages_Queue::action_generating ) {
		return (int) apply_filters( 'FHEE__EE_Messages_Queue__lock_expiry', HOUR_IN_SECONDS, $type );
	}


	/**
	 * Returns the key used for rate limit transient.
	 * @return string
	 */
	protected function _get_rate_limit_key() {
		return '_ee_rate_limit';
	}


	/**
	 * Returns the rate limit expiry time.
	 * @return int
	 */
	protected function _get_rate_limit_expiry() {
		return (int) apply_filters( 'FHEE__EE_Messages_Queue__rate_limit_expiry', HOUR_IN_SECONDS );
	}




	/**
	 * Returns the default rate limit for sending messages.
	 * @return int
	 */
	protected function _default_rate_limit() {
		return (int) apply_filters( 'FHEE__EE_Messages_Queue___rate_limit', 200 );
	}




	/**
	 * Return the orderby array for priority.
	 * @return array
	 */
	protected function _get_priority_orderby() {
		return array(
			'MSG_priority' => 'ASC',
			'MSG_modified' => 'DESC'
		);
	}




	/**
	 * Returns whether batch methods are "locked" or not.
	 *
	 * @param  string $type The type of lock being checked for.
	 * @return bool
	 */
	public function is_locked( $type = EE_Messages_Queue::action_generating ) {
		return (bool) get_transient( $this->_get_lock_key( $type ) );
	}







	/**
	 * Retrieves the rate limit that may be cached as a transient.
	 * If the rate limit is not set, then this sets the default rate limit and expiry and returns it.
	 * @return int
	 */
	public function get_rate_limit() {
		if ( ! $rate_limit = get_transient( $this->_get_rate_limit_key() ) ) {
			$rate_limit = $this->_default_rate_limit();
			set_transient( $this->_get_rate_limit_key(), $rate_limit, $this->_get_rate_limit_key() );
		}
		return $rate_limit;
	}




	/**
	 * This updates existing rate limit with the new limit which is the old minus the batch.
	 * @param int $batch_completed  This sets the new rate limit based on the given batch that was completed.
	 */
	public function set_rate_limit( $batch_completed ) {
		//first get the most up to date rate limit (in case its expired and reset)
		$rate_limit = $this->get_rate_limit();
		$new_limit = $rate_limit - $batch_completed;
		//updating the transient option directly to avoid resetting the expiry.
		update_option( '_transient_' . $this->_get_rate_limit_key(), $new_limit );
	}


	/**
	 * This method checks the queue for ANY EE_Message objects with a priority matching the given priority passed in.
	 * If that exists, then we immediately initiate a non-blocking request to do the requested action type.
	 *
	 * Note: Keep in mind that there is the possibility that the request will not execute if there is already another request
	 * running on a queue for the given task.
	 * @param string $task This indicates what type of request is going to be initiated.
	 * @param int    $priority  This indicates the priority that triggers initiating the request.
	 */
	public function initiate_request_by_priority( $task = 'generate', $priority = EEM_Message::priority_high ) {
		//determine what status is matched with the priority as part of the trigger conditions.
		$status = $task == 'generate'
			? EEM_Message::status_incomplete
			: EEM_Message::instance()->stati_indicating_to_send();
		// always make sure we save because either this will get executed immediately on a separate request
		// or remains in the queue for the regularly scheduled queue batch.
		$this->save();
		if ( $this->_queue->count_by_priority_and_status( $priority, $status ) ) {
			EE_Messages_Scheduler::initiate_scheduled_non_blocking_request( $task );
		}
	}



	/**
	 *  Loops through the EE_Message objects in the _queue and calls the messenger send methods for each message.
	 *
	 * @param   bool $save                      Used to indicate whether to save the message queue after sending
	 *                                          (default will save).
	 * @param   mixed $sending_messenger 		(optional) When the sending messenger is different than
	 *                                          what is on the EE_Message object in the queue.
	 *                                          For instance, showing the browser view of an email message,
	 *                                          or giving a pdf generated view of an html document.
	 *                                     		This should be an instance of EE_messenger but if you call this method
	 *                                          intending it to be a sending messenger but a valid one could not be retrieved
	 *                                          then send in an instance of EE_Error that contains the related error message.
	 * @param   bool|int $by_priority           When set, this indicates that only messages
	 *                                          matching the given priority should be executed.
	 *
	 * @return int        Number of messages sent.  Note, 0 does not mean that no messages were processed.
	 *                    Also, if the messenger is an request type messenger (or a preview),
	 * 					  its entirely possible that the messenger will exit before
	 */
	public function execute( $save = true, $sending_messenger = null, $by_priority = false ) {
		$messages_sent = 0;
		$this->_did_hook = array();
		$this->_queue->rewind();
		while ( $this->_queue->valid() ) {
			$error_messages = array();
			/** @type EE_Message $message */
			$message = $this->_queue->current();
			//if the message in the queue has a sent status, then skip
			if ( in_array( $message->STS_ID(), EEM_Message::instance()->stati_indicating_sent() ) ) {
				continue;
			}
			//if $by_priority is set and does not match then continue;
			if ( $by_priority && $by_priority != $message->priority() ) {
				continue;
			}
			//error checking
			if ( ! $message->valid_messenger() ) {
				$error_messages[] = sprintf(
					__( 'The %s messenger is not active at time of sending.', 'event_espresso' ),
					$message->messenger()
				);
			}
			if ( ! $message->valid_message_type() ) {
				$error_messages[] = sprintf(
					__( 'The %s message type is not active at the time of sending.', 'event_espresso' ),
					$message->message_type()
				);
			}
			// if there was supposed to be a sending messenger for this message, but it was invalid/inactive,
			// then it will instead be an EE_Error object, so let's check for that
			if ( $sending_messenger instanceof EE_Error ) {
				$error_messages[] = $sending_messenger->getMessage();
			}
			// if there are no errors, then let's process the message
			if ( empty( $error_messages ) && $this->_process_message( $message, $sending_messenger ) ) {
				$messages_sent++;
			}
			$this->_set_error_message( $message, $error_messages );
			//add modified time
			$message->set_modified( time() );
			$this->_queue->next();
		}
		if ( $save ) {
			$this->save();
		}
		return $messages_sent;
	}



	/**
	 * _process_message
	 *
	 * @param EE_Message $message
	 * @param mixed 	 $sending_messenger (optional)
	 * @return bool
	 */
	protected function _process_message( EE_Message $message, $sending_messenger = null ) {
		// these *should* have been validated in the execute() method above
		$messenger = $message->messenger_object();
		$message_type = $message->message_type_object();
		//do actions for sending messenger if it differs from generating messenger and swap values.
		if (
			$sending_messenger instanceof EE_messenger
			&& $messenger instanceof EE_messenger
			&& $sending_messenger->name != $messenger->name
		) {
			$messenger->do_secondary_messenger_hooks( $sending_messenger->name );
			$messenger = $sending_messenger;
		}
		// send using messenger, but double check objects
		if ( $messenger instanceof EE_messenger && $message_type instanceof EE_message_type ) {
			//set hook for message type (but only if not using another messenger to send).
			if ( ! isset( $this->_did_hook[ $message_type->name ] ) ) {
				$message_type->do_messenger_hooks( $messenger );
				$this->_did_hook[ $message_type->name ] = 1;
			}
			//if preview then use preview method
			return $this->_queue->is_preview()
				? $this->_do_preview( $message, $messenger, $message_type, $this->_queue->is_test_send() )
				: $this->_do_send( $message, $messenger, $message_type );
		}
		return false;
	}



	/**
	 * The intention of this method is to count how many EE_Message objects
	 * are in the queue with a given status.
	 *
	 * Example usage:
	 * After a caller calls the "EE_Message_Queue::execute()" method, the caller can check if there were any failed sends
	 * by calling $queue->count_STS_in_queue( EEM_Message_Queue::status_failed ).
	 *
	 * @param array $status  Stati to check for in queue
	 * @return int  Count of EE_Message's matching the given status.
	 */
	public function count_STS_in_queue( $status ) {
		$count = 0;
		$status = is_array( $status ) ? $status : array( $status );
		foreach( $this->_queue as $message ) {
			if ( in_array( $message->STS_ID(), $status ) ) {
				$count++;
			}
		}
		return $count;
	}


	/**
	 * Executes the get_preview method on the provided messenger.
	 *
*@param EE_Message            $message
	 * @param EE_messenger    $messenger
	 * @param EE_message_type $message_type
	 * @param $test_send
	 * @return bool   true means all went well, false means, not so much.
	 */
	protected function _do_preview( EE_Message $message, EE_messenger $messenger, EE_message_type $message_type, $test_send ) {
		if ( $preview = $messenger->get_preview( $message, $message_type, $test_send ) ) {
			if ( ! $test_send ) {
				$message->set_content( $preview );
			}
			$message->set_STS_ID( EEM_Message::status_sent );
			return true;
		} else {
			$message->set_STS_ID( EEM_Message::status_failed );
			return false;
		}
	}




	/**
	 * Executes the send method on the provided messenger
	 *
*@param EE_Message            $message
	 * @param EE_messenger    $messenger
	 * @param EE_message_type $message_type
	 * @return bool true means all went well, false means, not so much.
	 */
	protected function _do_send( EE_Message $message, EE_messenger $messenger, EE_message_type $message_type ) {
		if ( $messenger->send_message( $message, $message_type ) ) {
			$message->set_STS_ID( EEM_Message::status_sent );
			return true;
		} else {
			$message->set_STS_ID( EEM_Message::status_retry );
			return false;
		}
	}





	/**
	 * This sets any necessary error messages on the message object and its status to failed.
	 * @param EE_Message $message
	 * @param array      $error_messages the response from the messenger.
	 */
	protected function _set_error_message( EE_Message $message, $error_messages ) {
		$error_messages = (array) $error_messages;
		if ( $message->STS_ID() === EEM_Message::status_failed || $message->STS_ID() === EEM_Message::status_retry ) {
			$notices = EE_Error::has_notices();
			$error_messages[] = __( 'Messenger and Message Type were valid and active, but the messenger send method failed.', 'event_espresso' );
			if ( $notices === 1 ) {
				$notices = EE_Error::get_vanilla_notices();
				$notices['errors'] = isset( $notices['errors'] ) ? $notices['errors'] : array();
				$error_messages[] = implode( "\n", $notices['errors'] );
			}
		}
		if ( count( $error_messages ) > 0 ) {
			$msg = __( 'Message was not executed successfully.', 'event_espresso' );
			$msg = $msg . "\n" . implode( "\n", $error_messages );
			$message->set_error_message( $msg );
		}
	}

} //end EE_Messages_Queue class