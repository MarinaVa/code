<?php

class GC_Processor_Adapter_Wog extends GC_Processor_Adapter_Abstract
{
	/**
     * @see GC_Processor_Adapter_Abstract::invoke()
     *
     * @param GC_Action $action
    */
	public function invoke(GC_Action $action) {
        $params = GC_Params::parse($action->getContractorAction()->Params);
		
	    $res = $this->_port->test(array('cardNumber' => $params[0]));
	
		try {
			$ProcessorAction = $this->_initProcessorAction($action);
			$action->getProcessorAction()->setFromRow($ProcessorAction);
        } catch (Exception $ex) {
            throw new GC_Processor_Exception(GC_Result::CORE_COMMAND_FAIL, $ex);
        }
		
		if($res === false) {
			throw new GC_Processor_Exception(GC_Result::CORE_OPERATION_ERROR);
		}
		
		$result[] = $this->_addClientInfo($res);   

		$prices = $this->_port->getPrices();
		
		try {
			$this->_saveLogInfo($ProcessorAction);
        } catch (Exception $ex) {
            throw new GC_Processor_Exception(GC_Result::CORE_COMMAND_FAIL, $ex);
        }
		
		if($res === false) {
			throw new GC_Processor_Exception(GC_Result::CORE_OPERATION_ERROR);
		}
		

		$priceParam = $this->_addPrices($prices);
	
		
		$discounts = $this->_port->getDiscountValues(array('cardNumber' => $params[0]));

		try {
			$this->_saveLogInfo($ProcessorAction);
        } catch (Exception $ex) {
            throw new GC_Processor_Exception(GC_Result::CORE_COMMAND_FAIL, $ex);
        }
		
		if($res === false) {
			throw new GC_Processor_Exception(GC_Result::CORE_OPERATION_ERROR);
		}

		$this->_getDiscounts($discounts, $priceParam);
		
		if(isset($discounts->Good)) {
			foreach($discounts->Good as $item) {
				if($item->GOODS == 0) {
					$commonDiscountValue = str_replace(',', '.', $item->DISCOUNT);
					$commonDiscountLimit = str_replace(',', '.', $item->CURRENTLIMIT);
					break;
				}
			}
		}
		
        $result[]=$priceParam;
		
		$param = new stdClass();
		$param->Id = 'Liters';
        $param->Type = 'WogSelectLiters';
		$param->Name = 'Кількість літрів';
		$param->Name_UA = 'Кількість літрів';
		$param->Name_RU = 'Количество литров';
		$param->Name_EN = 'Number of liters';
		$param->DiscountValue = isset($commonDiscountValue)? $commonDiscountValue : null;
		$param->DiscountLimit = isset($commonDiscountLimit)? $commonDiscountLimit : null;
        $result[] = $param;
         
		$action->getContractorAction()->OperationWaitData=serialize($result);
		$action->getContractorAction()->save();
		return true;	
	}
	
	/**
     * @see GC_Processor_Adapter_Abstract::commit()
     *
     * @param GC_Action $action
    */
	public function commit(GC_Action $action) {
		$params = GC_Params::parse($action->getContractorAction()->Params);
		Zend_Registry::get('logger_debug')->info('commit:params:'.var_export($params, true));
		
		$OperationData=$action->getContractorAction()->OperationData;
		$payParams = unserialize($OperationData);
		$transactionId = $action->getProcessorAction()->TransactionId;
	
		if ($OperationData === null) {
			Zend_Registry::get('logger_errors')->err('WOG: Commit: Empty Operation data');
			return false;
		}
	
		foreach ($payParams as $p) {	
			if($p['Id'] == 'Type') {
				$data['productCode'] = $p['Value'];
			} elseif($p['Id'] == 'Liters') {
				$data['count'] = $p['Value'];
			}
		}
		
		$date = date('Y-m-d H:i:s');
		
		$data['cardNumber'] = $params[0];
		$data['sum'] = $action->getContractorAction()->Volume;
		$data['transactionID'] = $transactionId;
		$data['date'] = $date;
		
		$prices = $this->_port->getPrices();
		
		if($prices===false) {
			throw new GC_Processor_Exception(GC_Result::CORE_OPERATION_ERROR);
		}
		
		$ProcessorAction = $action->getProcessorAction();
		$this->_saveLogInfo($ProcessorAction);
		
		if(!isset($prices->Info)) {
			throw new GC_Processor_Exception(GC_Result::CORE_OPERATION_ERROR);
		}
		
		foreach($prices->Info as $item) {
			if($item->Code == $data['productCode']) {
				$data['price'] = (string)$item->Price;
			}
		}
	
		if(!isset($data['price'])) {
			throw new GC_Processor_Exception(GC_Result::CORE_OPERATION_ERROR);
		}
		
		Zend_Registry::get('logger_debug')->info('commit:operationData:'.var_export($payParams, true));
		
		Zend_Registry::get('logger_debug')->info('commit:volume:'.var_export($data['sum'], true));

		$res = $this->_port->putBalance($data);
		if($res===false) {
			throw new GC_Processor_Exception(GC_Result::CORE_OPERATION_ERROR);
		}
		
		$this->_saveLogInfo($ProcessorAction);
		
        switch ($res->Status) {
			case 0:
				break;
			case 1:
				throw new GC_Processor_Exception(GC_Result::INVALID_PARAMS);
			case 3:
				throw new GC_Processor_Exception(GC_Result::SERVER_ERROR);
			default:
				throw new GC_Processor_Exception(GC_Result::CORE_COMMAND_FAIL);
        }
		
		$ProcessorAction->Reported = $date; 
		$ProcessorAction->Status = 1;
		$ProcessorAction->HistoryConfirmed = 1;
		$ProcessorAction->save();
	
		Zend_Registry::get('logger_debug')->info('commit:_port->putBalance:'.var_export($res, true));
		return true;
	} 
	
	
	public function cancel(GC_Action $action) {
	
	}
	public function check(GC_Action $action) {
		return true;
	}
	
	/**
     * @see GC_Processor_Adapter_Abstract::isEnoughData()
     *
     * @param GC_Action $action
    */
	public function isEnoughData(GC_Action $action) {
		Zend_Registry::get('logger_debug')->info('isEnoughData started');
		$volume = $action->getContractorAction()->Volume;

		$params=$action->getContractorAction()->OperationData;
        $waitParams = $action->getContractorAction()->OperationWaitData;
	
        $services = array();
		$discounts = array();

        foreach(unserialize($waitParams) as $p){
			if(empty($p->Id)) {
				continue;
			}
			switch($p->Id) {
				case 'Type':
					foreach($p->Options as $option) {
						if(isset($option->DiscountValue) && isset($option->DiscountLimit)) {
					
							$discounts[$option->Id] = array(
								'DiscountValue' => $option->DiscountValue,
								'DiscountLimit' => $option->DiscountLimit
							);
						}
						$services[$option->Id] = array(
							'GUID' => $option->GUID,
							'Name' => $option->Name,
							'Price' => $option->Price
						);
					}
					break;
				case 'Liters':
					if(!empty($p->DiscountValue) && !empty($p->DiscountLimit)) {
						$discounts[0] = array(
							'DiscountValue' => $p->DiscountValue,
							'DiscountLimit' => $p->DiscountLimit
						);
					}
					break;
			}	
		}
		
		if ($params === null) {
			Zend_Registry::get('logger_debug')->info('params empty');
			return false;
		}
		
		$params=unserialize($params);

		$PaymentCounterSum = 0;
		$inputParams = GC_Params::parse($action->getContractorAction()->Params);

		foreach ($params as $param) {
			if($param['Id'] == 'Type') {
				$calculateData['productCode'] = $param['Value'];
			} elseif($param['Id'] == 'Liters') {
				$calculateData['productCount'] = $param['Value'];
			}
		}
		
		$paymentData = $this->_calculateTotalAmount($calculateData, $services, $discounts, $inputParams);
		
		$ProcessorAction = $action->getProcessorAction();
	
		$productSum = round($this->_checkProductSum($paymentData['data'], $ProcessorAction), 2);				
		$sumToPay = round($paymentData['paymentCounterSum'], 2);

		// проверка на соответствие цены Wog и просчитанной суммы
		if($productSum != $sumToPay) {
			throw new GC_Processor_Exception(GC_Result::INVALID_PARAMS);
		}
	
		$volume = round($volume, 2);
		
		if($productSum != $volume) {
			Zend_Registry::get('logger_debug')->info('Incorrect total sum. Sum to pay: '.$productSum.', input value: '. $volume);
			return false;
		}
		
		Zend_Registry::get('logger_debug')->info('params:'.var_export($params, true));
		Zend_Registry::get('logger_debug')->info('volume:'.var_export($volume, true));
		Zend_Registry::get('logger_debug')->info('CounterSum:'.var_export($PaymentCounterSum, true));
		return true;	
	}
	
	/**
     * @see GC_Processor_Adapter_Abstract::validate()
     *
     * @param string $params
	 * @param string $amount
    */
	public function validate($params, $amount) {

        $params = GC_Params::parse($params);

        $res = $this->_port->test(array('cardNumber' => $params[0]));

		if($res === false) {
			throw new GC_Processor_Exception(GC_Result::CORE_OPERATION_ERROR);
		}
            
		switch ($res->Status) {
			case 0:
				return true;
			case 1:
			case 2:
			case 3:
				throw new GC_Processor_Exception(GC_Result::INVALID_CARDNUM);
			default:
				throw new GC_Processor_Exception(GC_Result::CORE_COMMAND_FAIL);
        }
    }
	
	private function _initProcessorAction($action) {
		$ProcessorAction = $action->getProcessorAction()->getTable()->createRow();
		$ProcessorAction->ProcessorServiceId = $action->ProcessorServiceId;
        $ProcessorAction->ActionId = $action->Id;
		$ProcessorAction->TransactionId = $action->Id;
			
        $ProcessorAction->ResponseData = date("Y-m-d H:i:s") . ' ' . $this->_port->responseData;
        $ProcessorAction->RequestData = date("Y-m-d H:i:s") . ' ' . $this->_port->requestData;
        $ProcessorAction->save();
		return $ProcessorAction;
	}
	
	private function _calculateTotalAmount($calculateData, $services, $discounts, $inputParams) {
		$service = $services[$calculateData['productCode']];
		$service['Counter'] = $calculateData['productCount'];
		
		$data['cardNumber'] = $inputParams[0];
		$data['GUID'] = $service['GUID'];
		$data['description'] = $service['Name'];
		$data['count'] = $calculateData['productCount'];
		
		$globalDiscountData = isset($discounts[0])? $discounts[0] : null;
		$discountData = null;
		
		if(isset($discounts[$calculateData['productCode']]) && ($data['count'] <= $discounts[$calculateData['productCode']]['DiscountLimit'])) {
			$discountData = $discounts[$calculateData['productCode']];
			$paymentCounterSum = $this->_calculateSimpleDiscount($service, $discountData);
		} elseif(isset($globalDiscountData)) {
			$paymentCounterSum = $this->_calculateComplexDiscount($service, $discountData, $globalDiscountData);	
		} else {
			$paymentCounterSum = $service['Price'] * $data['count'];
		}
	
		return array('data' => $data, 'paymentCounterSum' => $paymentCounterSum);
	}
	
	private function _getDiscounts($res, &$priceParam) {
		switch ($res->Status) {
			case 0:
				if(empty($res->Good)) {
					break;
				}
					
				foreach($res->Good as $product) {
					$this->_addDiscount($product,$priceParam);
				}
			
				return;
			case 1:
			case 2:
			case 4:
				return;
			case 3:
				throw new GC_Processor_Exception(GC_Result::SERVER_ERROR);
			default:
				throw new GC_Processor_Exception(GC_Result::CORE_COMMAND_FAIL);
        }
	}
	
	private function _calculateSimpleDiscount($service, $discountData) {
		$service['Price'] = $service['Price'] - $discountData['DiscountValue'];
		return $service['Price'] * $service['Counter'];
	}
	
	private function _calculateComplexDiscount($service, $discountData, $globalDiscountData) {
		
		$globalDiscountAmount = $service['Price'] - $globalDiscountData['DiscountValue'];
	
		if(!$discountData) {
			$itemDiscountQuantity = 0.00;
			$itemDiscountAmount = 0.00;
			$globalDiscountQuantity = ($service['Counter'] <= $globalDiscountData['DiscountLimit'])?
				$service['Counter'] : $globalDiscountData['DiscountLimit'];
		} else {
			$itemDiscountQuantity = $discountData['DiscountLimit'];
			$itemDiscountAmount = $service['Price'] - $discountData->Value();
			$leftoverQuantity = $service['Counter'] - $itemDiscountQuantity;
			$globalDiscountQuantity = ($leftoverQuantity <= $globalDiscountData['DiscountLimit'])?
				$leftoverQuantity : $globalDiscountData['DiscountLimit'];
		}
					
		$noDiscountQuantity = $service['Counter'] - $itemDiscountQuantity - $globalDiscountQuantity;
		
		$paymentCounterSum = ($itemDiscountAmount * $itemDiscountQuantity)
			+ ($globalDiscountAmount * $globalDiscountQuantity)
			+ ($service['Price'] * $noDiscountQuantity);
		
		return $paymentCounterSum;
	}
	
	private function _addPrice($item) {
		$param = new stdClass();
		$param->Id = $item->Code;
		$param->GUID = $item->GUID;
		$param->Name = $item->Description;
		$param->Price = $item->Price;
		return $param;
	}
	

	private function _addDiscount($item,&$result) {
		
		if(empty($result->Options[$item->GOODS]))
		{
			return false;
		}
		
		$param = $result->Options[$item->GOODS];
		
		$param->DiscountValue = (float)str_replace(',', '.', $item->DISCOUNT);
		$param->DiscountLimit = (float)str_replace(',', '.', $item->CURRENTLIMIT);
		return $param;
	}
	
	private function _addClientInfo($res) {
		switch ($res->Status) {
			case 0:
				break;
			case 1:
			case 2:
			case 3:
				throw new GC_Processor_Exception(GC_Result::INVALID_CARDNUM);
			default:
				throw new GC_Processor_Exception(GC_Result::CORE_COMMAND_FAIL);
        }
		$param = new stdClass();
		$param->Type = 'Info';				
		$param->Readonly = 1;
		$param->Name = 'Власник картки';	
		$param->Name_UA = 'Власник картки';
		$param->Name_RU = 'Владелец карты';
		$param->Name_EN = 'Card holder';
		$param->Value = $res->Info->LASTNAME . ' ' .$res->Info->FIRSTNAME . ' ' . $res->Info->MIDDLENAME;	
		return $param;
	}
	
	private function _addPrices($res) {
		switch ($res->Status) {
			case 0:
				if(empty($res->Info)) {
					throw new GC_Processor_Exception(GC_Result::CORE_OPERATION_ERROR);
				}
				
				$param = new stdClass();
				$param->Type = 'WogSelect';
				$param->Id = 'Type';
				$param->Name = 'Тип палива';
				$param->Name_UA = 'Тип палива';
				$param->Name_RU = 'Тип топлива';
				$param->Name_EN = 'Type of fuel';
				$products = $res->Info;

				$productsResult = array();
				foreach($products as $item) {
					if($item->Disabled or $item->Price<=0) {
						continue;
					}
							
					$productsResult[$item->Code] = $this->_addPrice($item);
				}
				
				$param->Options = $productsResult;
				
				
				break;
				
			default:
				throw new GC_Processor_Exception(GC_Result::CORE_COMMAND_FAIL);
        }
        return $param;
	}
	
	private function _checkProductSum($data, $ProcessorAction) {
		$res = $this->_port->checkProductSum($data);
		
		$this->_saveLogInfo($ProcessorAction);
		
		if($res === false) {
			throw new GC_Processor_Exception(GC_Result::CORE_OPERATION_ERROR);
		}
		
		switch($res->Status) {
			case 0:
				break;
			case 1:
			case 2:
			case 3:
			case 4:
				throw new GC_Processor_Exception(GC_Result::INVALID_PARAMS);
			default:
				throw new GC_Processor_Exception(GC_Result::CORE_COMMAND_FAIL);
		} 
				
		if(empty($res->Summ)) {
			throw new GC_Processor_Exception(GC_Result::CORE_COMMAND_FAIL);
		}
		
		$productSum = (float) str_replace(',', '.', $res->Summ);
		return $productSum;
	}
	
	private function _saveLogInfo($ProcessorAction) {
		$ProcessorAction->ResponseData = $ProcessorAction->ResponseData . PHP_EOL . date("Y-m-d H:i:s") . ' ' . $this->_port->responseData;
		$ProcessorAction->RequestData = $ProcessorAction->RequestData . PHP_EOL . date("Y-m-d H:i:s") . ' ' . $this->_port->requestData;
		$ProcessorAction->save();
	}
}
