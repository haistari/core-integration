<?php

namespace Haistar;

class Curl
{
	private $curl;
	private $returntransfer;
	private $encoding;
	private $maxredirs;
	private $timeout;
	private $http_version;
	private $method;
	private $post_state;
	private $body;
	private $header;
	private $headerfunction;
	private $ssl_verifypeer;
	private $ssl_verifyhost;
	private $login;
	
	public function __construct($url)
	{
		$this->curl           = curl_init();
		$this->returntransfer = true;
		$this->encoding       = "";
		$this->maxredirs      = 10;
		$this->timeout        = 0;
		$this->http_version   = "CURL_HTTP_VERSION_1_1";
		$this->post_state     = true;
		$this->ssl_verifypeer = false;
		$this->ssl_verifyhost = false;
		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, $this->returntransfer);
		curl_setopt($this->curl, CURLOPT_ENCODING, $this->encoding);
		curl_setopt($this->curl, CURLOPT_MAXREDIRS, $this->maxredirs);
		curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($this->curl, CURLOPT_HTTP_VERSION, $this->http_version);
		curl_setopt($this->curl, CURLOPT_POST, $this->post_state);
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer);
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, $this->ssl_verifyhost);
		return $this->curl;
	}

	public function setReturnTransfer($val)
	{
		$this->returntransfer = $val;
		return curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, $this->returntransfer);
	}

	public function setEncoding($val)
	{
		$this->encoding = $val;
		return curl_setopt($this->curl, CURLOPT_ENCODING, $this->encoding);
	}

	public function setMaxRedirs($val)
	{
		$this->maxredirs = $val;
		return curl_setopt($this->curl, CURLOPT_MAXREDIRS, $this->maxredirs);
	}

	public function setTimeout($val)
	{
		$this->timeout = $val;
		return curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeout);
	} 

	public function setHttpVersion($val)
	{
		$this->http_version = $val;
		return curl_setopt($this->curl, CURLOPT_HTTP_VERSION, $this->http_version);
	}

	public function setPostState($val)
	{
		$this->post_state = $val;
		return curl_setopt($this->curl, CURLOPT_POST, $this->post_state);
	}

	public function setSSLVerifyPeer($val)
	{
		$this->ssl_verifypeer = $val;
		return curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer);
	}

	public function setSSLVerifyHost($val)
	{
		$this->ssl_verifyhost = $val;
		return curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, $this->ssl_verifyhost);
	}

	public function Login($val)
	{
		$this->login = $val;
		return curl_setopt($this->curl, CURLOPT_USERPWD, $this->login); 
	}

	public function setBody($val)
	{
		$this->body = $val;
		return curl_setopt($this->curl, CURLOPT_POSTFIELDS, $this->body);
	}

	public function setMethod($val)
	{
		$this->method = $val;
		return curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $this->method);
	}

	public function setHeader($val)
	{
		$this->header = $val;
		return curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->header);
	}

	public function setHeaderFunction($val)
	{
		$this->headerfunction = $val;
		return curl_setopt($this->curl, CURLOPT_HEADERFUNCTION, $this->headerfunction);
	}
	public function getInfo()
	{
		return curl_getinfo($this->curl);
	}

	public function exec(){
		$res = curl_exec($this->curl);
		return $res;
	}

	public function error(){
		$err = curl_error($this->curl);
		curl_close($this->curl);
		return $err;
	}
}