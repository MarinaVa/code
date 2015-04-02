<?php

class GC_Paysystem_Adapter_KyivstarMoney extends GC_Paysystem_Adapter {
    
    /**
     * @var string
    */
    protected $_gatewayCACertFile       = 'KyivstarMoney/easypay.cer';
    
    /**
     * @var string
    */
    protected $_gatewayCertFile         = 'KyivstarMoney/client.1108.pem';
    
    /**
     * @var string
    */
    protected $_gatewayKeyFile          = 'KyivstarMoney/client.1108.pem';
    
    /**
     * @var string
    */
    protected $_privateSSLFile          = 'KyivstarMoney/private1108.key';
    
    /**
     * @var string
    */
    protected $_publicSSLFile           = 'KyivstarMoney/GateSign-EasyPay.cer';
    
    /**
     * @var string
    */
    protected $_providerPrivateSSLFile  = 'KyivstarMoney/provider.key';
    
    /**
     * @var string
    */
    protected $_providerPublicSSLFile   = 'KyivstarMoney/EasySoftProviderPublicKey.pem';
    
    /**
     *  Connection timeout
     *
     * @var int
    */
    protected $_timeOut           = 15;
    
    /**
     * @param  array $request_data
     * @return SimpleXMLElement|array
    */
    public function check($request_data) {
        return $this->_request('Check', $request_data);
    }
    
    /**
     * @param  array $request_data
     * @return SimpleXMLElement|array
    */
    public function payment($request_data) {
        return $this->_request('Payment', $request_data);
    }
    
    /**
     * @param  array $request_data
     * @return SimpleXMLElement|array
    */
    public function status($request_data) {
        return $this->_request('Status', $request_data);
    }
    
    /**
     * @param  string $data
     * @return string
    */
    public function providerSign($data) {
        $config = Zend_Registry::get('config');
        
        $fp = fopen($config->path->keys.$this->_providerPrivateSSLFile, 'r');
        $privKey = fread($fp, 8192);
        fclose($fp);
        
        $signature = $this->_usePrivateKey($privKey, $data);
        return $this->_addSign(bin2hex($signature), $data);
    }
    
    /**
     * @param  string $data
     * @return boolean
    */
    public function providerCheckSign($data) {
        preg_match('/<Sign>(\w+)<\/Sign>/', $data, $sign);
        $data = str_replace('<Sign>'.$sign[1].'</Sign>', '<Sign></Sign>', $data);
        $sign = pack("H*", $sign[1]);
     
        $config = Zend_Registry::get('config');

        $fp = fopen($config->path->keys.$this->_providerPublicSSLFile, 'r');
        $pubKey = fread($fp, 8192);
        fclose($fp);

        return $this->_usePublicKey($data, $sign, $pubKey);
    }
    
    /**
     * @param  string $phonenumber
     * @param  int    $code
     * @return SimpleXMLElement|array
    */ 
    public function smsConfirm($phonenumber, $code) {
        $params = '?ACTION=CONFIRM&MSISDN='.$phonenumber.'&CONFIRM_CODE='.$code;
        return $this->_requestEmoney($params);
    }
    
    /**
     * @param  string $sign
     * @param  string $data
     * @return string
    */
    protected function _addSign($sign, $data) {
        return str_replace('<Sign></Sign>', '<Sign>'.$sign.'</Sign>', $data);
    }
    
    /**
     * @param  string $data
     * @return string
    */
    protected function _sign($data) {
        $config = Zend_Registry::get('config');
        $fp = fopen($config->path->keys.$this->_privateSSLFile, 'r');
        $privKey = fread($fp, 8192);
        fclose($fp);
        $signature = $this->_usePrivateKey($privKey, $data);
      
        return $this->_addSign(bin2hex($signature), $data);
    }
    
    /**
     * @param  string $sign
     * @param  string $data
     * @return boolean
    */
    protected function _checkSign($sign, $data) {
        $config = Zend_Registry::get('config');
        $sign = pack("H*", $sign);

        $fp = fopen($config->path->keys.$this->_publicSSLFile, 'r');
        $pubKey = fread($fp, 8192);
        fclose($fp);

        return $this->_usePublicKey($data, $sign, $pubKey);
    }
    
    /**
     * @param  string $privKey
     * @param  string $data
    */
    protected function _usePrivateKey($privKey, $data) {
        $signature = '';
        $pKeyId = openssl_get_privatekey($privKey);
        openssl_sign($data, $signature, $pKeyId);
        openssl_free_key($pKeyId);
        return $signature;
    }
    
    /**
     * @param  string $pubKey
     * @param  string $data
    */
    protected function _usePublicKey($data, $sign, $pubKey) {
        $pKeyId = openssl_get_publickey($pubKey);
        $verify = openssl_verify($data, $sign, $pKeyId);
        openssl_free_key($pKeyId);
   
        return ($verify == 1)? true : false;
    }
    
    /**
     * @param  string $params
     * @return SimpleXMLElement|array
    */ 
    protected function _requestEmoney($params) {
        
        $this->_requestData = array(
	    'Body' => 'Destination: '.$this->config->SMSConfirmURL.PHP_EOL.'Data: '.var_export($params, TRUE),
	    'Time' => date('Y-m-d H:i:s'),
	);

        $ch = curl_init($this->config->SMSConfirmURL.$params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->_timeOut);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $this->_responseData = array(
	    'Body' => var_export($response, TRUE),
	    'Time' => date('Y-m-d H:i:s'),
	);
        return simplexml_load_string($response); 
    }
    
    /**
     * @param  string $OperationType
     * @param  array  $request_data
     * @return string
    */
    protected function _prepare_request_xml($OperationType, $request_data) {
		$xml_obj = new XML_Serializer(array(
			XML_SERIALIZER_OPTION_MODE => XML_SERIALIZER_MODE_SIMPLEXML,
			XML_SERIALIZER_OPTION_ENTITIES => XML_UTIL_ENTITIES_NONE,
			XML_SERIALIZER_OPTION_ROOT_NAME => 'Request',
			XML_SERIALIZER_OPTION_ATTRIBUTES_KEY => '_attributes',
        ));

        $xml_data = array(
	    'DateTime' => date('Y-m-d\TH:i:s'),
	    'Sign' => '',
	    $OperationType => $request_data,
        );

        $xml_obj->serialize($xml_data);
        $xml = $xml_obj->getSerializedData();
        return str_replace('<Sign />', '<Sign></Sign>', $xml);
    }
    
    /**
     * @param  string $OperationType
     * @param  array $request_data
     * @return SimpleXMLElement
    */
    protected function _request($OperationType, $request_data) {
        $config = Zend_Registry::get('config');
        $preparedData = $this->_prepare_request_xml($OperationType, $request_data);
        $data = $this->_sign($preparedData);
    
        $this->_requestData = array(
			'Body' => 'Destination: '.$this->config->PaymentURL.PHP_EOL.'Data: '.var_export($data, TRUE),
			'Time' => date('Y-m-d H:i:s'),
		);

		$ch = curl_init($this->config->PaymentURL);

		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
		curl_setopt($ch, CURLOPT_CAINFO, $config->path->keys.$this->_gatewayCACertFile);
		curl_setopt($ch, CURLOPT_SSLCERT, $config->path->keys.$this->_gatewayCertFile);
		curl_setopt($ch, CURLOPT_SSLKEY, $config->path->keys.$this->_gatewayKeyFile);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->_timeOut);
       
		$response = curl_exec($ch);
        
        $this->_responseData = array(
			'Body' => var_export($response, TRUE),
			'Time' => date('Y-m-d H:i:s'),
		);

		if(curl_error($ch) != '') {
			return array('response_error' => curl_error($ch));
		}

		if(empty($response)) {
			return array('response_error' => 'The server answered nothing.');
		}
        
		$result_xml = simplexml_load_string($response);

		if($result_xml === FALSE) {
			return array('response_error' => 'Not an XML received from the server.');
		}
        
        if(!$this->_checkResponse($response)) {
            return array('response_error' => 'Error during checking response');
        }
        
		return $result_xml;
    }
    
    /**
     * @param  string $data
     * @return boolean
    */
    protected function _checkResponse($data) {
        preg_match('/<Sign>(\w+)<\/Sign>/', $data, $sign);
        $preparedData = str_replace('<Sign>'.$sign[1].'</Sign>', '<Sign></Sign>', $data);
        return $this->_checkSign($sign[1], $preparedData);
    }
}
