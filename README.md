<?php

class Megastock {
	private $_serviceUrl = 'https://www.megastock.ru/xml/int/';
  private $_integratorNumber = 37421;
  private $_wmid = '166569234283';
	private $_intWMID = '101837328060';
	private $_timeOut = 15;
	private $_webmoneyCACertFile = 'WebMoneyCA.cer';
	
	/**
     * @param  array $params
     * @return SimpleXMLElement|array
    */
	public function addMerchant($params) {
		$url = $this->_serviceUrl.'AddMerchant.ashx';
		$requestNumber = (int)(microtime(true)*10);
		
		$signParams = $requestNumber
			.$this->_wmid
			.$this->_integratorNumber
			.$this->_intWMID
			.$params['url']
			.$params['Name']
			.$params['CategoryMegastockId'];
		
		$data = array(
			'login' => array($this->_wmid, '_attributes' => array('type' => 1)),
			'int_id' => $this->_integratorNumber,
			'int_wmid' => $this->_intWMID,
			'beneficiary' => array(
				'legalname' => $params['legalname'],
				'regcountry' => 'UA',
				'legalnumber' => $params['legalnumber'],
				'_attributes' => array('type' => 1)
			),
			'url' => $params['url'],
			'group' => $params['CategoryMegastockId'],
			'keywords' => '',
			'logourl' => $params['Image'],
			'about' => array(
				'name' => $params['Name'],
				'descr' => $params['Description'],
				'_attributes' => array('lang' => 'ru')
			),
			'nameincomment' => $params['Name'],
			'geobindings' => array(
				'country' => array('Украина', '_attributes' => array('id' => 'UA')),
			),
			'sign' => $this->_sign($signParams),
		);
		
		$xml = $this->_prepare_request_xml($requestNumber, $data);
		$response = $this->_request($url, $xml);
		return $response;
	} 
	
	/**
     * @param  array $params
     * @return SimpleXMLElement|array
    */
	public function getMerchants($params) {
		$url = $this->_serviceUrl.'GetMerchants.ashx';
		$requestNumber = (int)(microtime(true)*10);
		
		$signParams = $requestNumber
			.$this->_wmid
			.$this->_integratorNumber
			.$this->_intWMID
			.$params['startid']
			.$params['itemscount'];
		
		$data = array(
			'login' => array($this->_wmid, '_attributes' => array('type' => 1)),
			'int_id' => $this->_integratorNumber,
			'int_wmid' => $this->_intWMID,
		    'startid' => $params['startid'],
			'itemscount' => $params['itemscount'],
			'sign' => $this->_sign($signParams),
		);
		
		$xml = $this->_prepare_request_xml($requestNumber, $data);
		$response = $this->_request($url, $xml);
		return $response;
	}
	
	/**
     * @param  array $params
     * @return SimpleXMLElement|array
    */
	public function removeMerchant($params) {
		$url = $this->_serviceUrl.'RemoveMerchant.ashx';
		$requestNumber = (int)(microtime(true)*10);
		
		$signParams = $requestNumber
		    .$this->_wmid
			.$this->_integratorNumber
			.$this->_intWMID
			.$params['resourceid'];
		
		$data = array(
			'login' => array($this->_wmid, '_attributes' => array('type' => 1)),
			'int_id' => $this->_integratorNumber,
			'int_wmid' => $this->_intWMID,
		  'resourceid' => $params['resourceid'],
			'sign' => $this->_sign($signParams),
		);
		
		$xml = $this->_prepare_request_xml($requestNumber, $data);
		$response = $this->_request($url, $xml);
		return $response;
	}
	
	/**
     * @param  string  $data
     * @return string
    */
	private function _sign($data) {
		
		$config = Zend_Registry::get('config');
		
		# Подключаем библиотеку, отвечающую за выполнение
		# запросов на сервер и приём ответов
		include_once ($config->path->library . "wmxi/wmxi.php");

		# Создаём объект класса WMXI. Передаваемые параметры:
		# - путь к сертификату, используемому для защиты от атаки с подменой ДНС
		# - кодировка, используемая на сайте. По умолчанию используется UTF-8
	    DEFINE('KWMFILE', $config->path->keys.$config->webmoney->serviceWMID.".kwm");
		
		$wmxi = new WMXI($config->path->keys.$this->_webmoneyCACertFile);
		$wmxi->Classic($config->webmoney->serviceWMID, $config->webmoney->serviceWMIDPassword, KWMFILE);

        return $wmxi->_sign($data);
	}
	
	/**
	 * @param  int    $number
     * @param  array  $xml_data
     * @return string
    */
    private function _prepare_request_xml($number, $xml_data) {
		$xml_obj = new XML_Serializer(array(
			XML_SERIALIZER_OPTION_MODE => XML_SERIALIZER_MODE_SIMPLEXML,
			XML_SERIALIZER_OPTION_ENTITIES => XML_UTIL_ENTITIES_NONE,
			XML_SERIALIZER_OPTION_ROOT_NAME => 'ms.request',
			XML_SERIALIZER_OPTION_ROOT_ATTRIBS => array('number' => $number),
			XML_SERIALIZER_OPTION_ATTRIBUTES_KEY => '_attributes',
		));

    $xml_obj->serialize($xml_data);
    $xml = $xml_obj->getSerializedData();
		return str_replace('<keywords />', '<keywords></keywords>', $xml);
    }
	
	/**
	 * @param  string $url
     * @param  array  $data
     * @return SimpleXMLElement
    */
    private function _request($url, $data) {
        $config = Zend_Registry::get('config');
		$this->_requestData = array(
	    'Body' => 'Destination: '.$url.PHP_EOL.'Data: '.var_export($data, TRUE),
			'Time' => date('Y-m-d H:i:s'),
		);

		$preparedData = iconv("UTF-8", "CP1251//TRANSLIT", $data);
		$ch = curl_init($url);
 
    curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $preparedData);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
    curl_setopt($ch, CURLOPT_CAINFO, $config->path->keys.$this->_webmoneyCACertFile);
	  curl_setopt($ch, CURLOPT_TIMEOUT, $this->_timeOut);
       
		$result = curl_exec($ch);
    $response = iconv("CP1251", "UTF-8//TRANSLIT", $result);
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
        
		return $result_xml;
    }
}
