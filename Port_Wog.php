<?php

final class GC_Processor_Port_Wog 
{
	public $requestData = '';
	public $responseData = '';
	
	private $_soapClient;
	/**
     * @var string
    */
	private $_serviceUrl      = 'http://10.254.4.21:80/LoyaltyCopy/ws/Site?WSDL';
	
	/**
     * @var string
    */
	private $_serviceLogin    = 'WOGWEB';
	
	/**
     * @var string
    */
	private $_servicePassword = 'WEBWOG';
	
	
	public function __construct() {
    	try {
    		ini_set('default_socket_timeout', 120);
        	ini_set('soap.wsdl_cache_enabled',1);
			
			$this->_soapClient = new SoapClient($this->_serviceUrl, array(
				'login'      => $this->_serviceLogin,
				'password'   => $this->_servicePassword,
				'exceptions' => true,
				'cache_wsdl' => WSDL_CACHE_BOTH,
				'connection_timeout' => 40,
				'trace' => true
			));
		} catch(Exception $ex) {}
		
        if(!($this->_soapClient instanceof SoapClient)) {
        	throw new GC_Processor_Exception(GC_Result::CORE_COMMAND_ERROR, new GC_Processor_Exception('Ошибка соединения с сервером процессора, попробуйте еще раз.'));
        }
	}
	
	/**
     * @param  array $data
     * @return stdClass|array
    */
	public function test($data) {
		$response = $this->_soapRequest(
			'Test', 
			array(
			    'CARDNUMBER' => $data['cardNumber'],
			)
		);
		return $response;
	}
	
	/**
     * @param  array $data
     * @return stdClass|array
    */
	public function putBalance($data) {
		
		$response = $this->_soapRequest(
			'PutBalance', 
			array(
			    'CARDNUMBER' => $data['cardNumber'],
				'INFO' => json_encode(
					array(
						'Type' => 'web',
						'Good' => array(
							array(
								'DATE' => $data['date'],
								'GOODS' => $data['productCode'],
								'COUNT' => $data['count'],
								'PRICE' => $data['price'],
								'SUMM'   => $data['sum'],
								'TRANSACT_ID' => $data['transactionID']
							)
						)
					)
				)
			)
		);
		return $response;
	}
	
	/**
     * @param  array $data
     * @return stdClass|array
    */
	public function getDiscountValues($data) {
		$response = $this->_soapRequest(
			'GetDiscountValues', 
			array(
			    'CARDNUMBER' => $data['cardNumber'],
			)
		);
		return $response;
	}
	
	/**
     * @return stdClass|array
    */
	public function getPrices() {
		$response = $this->_soapRequest(
			'GetPrices', 
			array()
		);
		return $response;
	}
	
	public function checkProductSum($data) {
		$response = $this->_soapRequest(
			'GetSumm',
			array(
				'CARDNUMBER' => $data['cardNumber'],
				'INFO' => json_encode(
					array(
						'GOOD' => $data['GUID'],
						'GOODDESC' => $data['description'],
						'COUNT' => $data['count']
					)
				)
			)
		);
		return $response;
	}
	
	/**
     * @param  string $func
	 * @param  array  $data
     * @return stdClass|array
    */
	private function _soapRequest($func, $data) {
		$param = new stdClass();

		foreach($data as $k => $v) {
            $param->$k = $v;
		}
		
		$response = $this->_soapClient->$func($param);
		$this->requestData = $this->_soapClient->__getLastRequest();
		$this->responseData = $this->_soapClient->__getLastResponse();

		if(!isset($response->return)) {
			return false;
		}
	
		$result = json_decode($response->return);

		if(!$result) {
			return false;
		}
		
        return $result;
	}
}
