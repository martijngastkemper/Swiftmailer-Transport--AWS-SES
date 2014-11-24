<?php

class Swift_Response_AWSResponse {
	
	protected $message;
	
	protected $awsResponse;
	
	public function __construct( Swift_Mime_Message $message, $awsResponse = null )
	{
		$this->message = $message;
		$this->awsResponse = $awsResponse;
	}
	
	function getMessage()
	{
		return $this->message;
	}

	function getAwsResponse()
	{
		return $this->awsResponse;
	}

	function setMessage( $message )
	{
		$this->message = $message;
		return $this;
	}

	function setAwsResponse( $awsResponse )
	{
		$this->awsResponse = $awsResponse;
		return $this;
	}
}
