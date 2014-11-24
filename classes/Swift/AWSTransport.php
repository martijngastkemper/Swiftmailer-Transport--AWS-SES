<?php
	/*
	* This file requires SwiftMailer.
	* (c) 2011 John Hobbs
	*
	* For the full copyright and license information, please view the LICENSE
	* file that was distributed with this source code.
	*/

	/**
	* Sends Messages over AWS.
	* @package Swift
	* @subpackage Transport
	* @author John Hobbs
	*/
	class Swift_AWSTransport extends Swift_Transport_AWSTransport {

		private $SESClient;

		/**
		 * Debugging helper.
		 *
		 * If false, no debugging will be done.
		 * If true, debugging will be done with error_log.
		 * Otherwise, this should be a callable, and will recieve the debug message as the first argument.
		 *
		 * @seealso Swift_AWSTransport::setDebug()
		 */
		private $debug;
		/** the response */
		private $response;

		/**
		* Create a new AWSTransport.
		* @param string $AWSAccessKeyId Your access key.
		* @param string $AWSSecretKey Your secret key.
		* @param boolean $debug Set to true to enable debug messages in error log.
		* @param string $endpoint The AWS endpoint to use.
		*/
		public function __construct( $SESClient, $debug = false ) {
			call_user_func_array(
				array($this, 'Swift_Transport_AWSTransport::__construct'),
				Swift_DependencyContainer::getInstance()
					->createDependenciesFor('transport.aws')
				);
			
			$this->SESClient = $SESClient;
			$this->debug = $debug;
		}

		/**
		* Create a new AWSTransport.
		* @param string $AWSAccessKeyId Your access key.
		* @param string $AWSSecretKey Your secret key.
		*/
		public static function newInstance( $SESClient ) {
			return new Swift_AWSTransport( $SESClient );
		}

		public function setDebug($val) {
			$this->debug = $val;
		}

		public function getResponse() {
			return $this->response;
		}

		protected function _debug ( $message ) {
			if ( true === $this->debug ) {
				error_log( $message );
			} elseif ( is_callable($this->debug) ) {
				call_user_func( $this->debug, $message );
			}
		}

		/**
		* Send the given Message.
		*
		* Recipient/sender data will be retreived from the Message API.
		* The return value is the number of recipients who were accepted for delivery.
		*
		* @param Swift_Mime_Message $message
		* @param string[] &$failedRecipients to collect failures by-reference
		* @return int
		* @throws AWSConnectionError
		*/
		public function send( Swift_Mime_Message $message, &$failedRecipients = null ) {

			if ($evt = $this->_eventDispatcher->createSendEvent($this, $message))
			{
				$this->_eventDispatcher->dispatchEvent($evt, 'beforeSendPerformed');
				if ($evt->bubbleCancelled())
				{
					return 0;
				}
			}

			$success = true;
			
			try
			{
				$this->response = $this->_doSend($message, $failedRecipients);	
			}
			catch (Exception $ex) 
			{
				$success = false;
				$this->_debug( var_export( $ex, true ) );
			}

			if ($respEvent = $this->_eventDispatcher->createResponseEvent($this, new Swift_Response_AWSResponse( $message, $this->response ), $success))
				$this->_eventDispatcher->dispatchEvent($respEvent, 'responseReceived');

			if ($evt)
			{
				$evt->setResult($success ? Swift_Events_SendEvent::RESULT_SUCCESS : Swift_Events_SendEvent::RESULT_FAILED);
				$this->_eventDispatcher->dispatchEvent($evt, 'sendPerformed');
			}

			if( $success ) {
				return count((array) $message->getTo());
			}
			else {
				return 0;
			}
		}

		/**
		 * do send through the API
		 *
		 * @param Swift_Mime_Message $message
		 * @param string[] &$failedRecipients to collect failures by-reference
		 * @return AWSResponse
		 */
		protected function _doSend( Swift_Mime_Message $message, &$failedRecipients = null )
		{
			foreach( $message->getFrom() as $address => $name )
			{
				$source = sprintf( '%s <%s>', $name, $address );
				break;
			}
			
			$destinations = [];
			foreach( $message->getTo() as $address => $name )
			{
				$destinations[] = sprintf( '%s <%s>', $name, $address );
			}

			return $this->SESClient->sendRawEmail([
				'Source' => $source,
				'Destinations' => $destinations,
				'RawMessage' => [
					'Data' => sprintf( "%s\n%s", $message->getHeaders()->toString(), $message->getBody() )
				]
			]);
		}

		public function isStarted() {}
		public function start() {}
		public function stop() {}

		/**
		 * Register a plugin.
		 *
		 * @param Swift_Events_EventListener $plugin
		 */
		public function registerPlugin(Swift_Events_EventListener $plugin)
		{
			$this->_eventDispatcher->bindEventListener($plugin);
		}

	} // AWSTransport
