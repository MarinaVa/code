<?php

class Merchant_StatisticController extends Merchant_Library_Controller_Action {
	
	public function init() {
		parent::init();
		$this->_api = new GC_API();
		$this->_filters = array(
			'SHOP_NAME' => 'ServiceId', 
			'USER' => 'UserId', 
			'CONTRACT' => 'Contract', 
			'BILL_PAID_PAY_WAY' => 'PaysystemTool', 
			'BILL_PAID_STATUS' => 'Paid'
		);
		$this->_paramFilters = array();
		$this->_search = array();
		$this->_breadcrumbs = array();
		$this->_statisticInfo = array();
	}
	
	public function indexAction() {
		$this->_merchId = $this->checkAuthRedirect('/statistic', '/index/login/');
		$this->_vocabulary = $this->_getVocabulary();
		
		if(($type = (int)$this->getRequest()->getParam('type'))) {
			$this->statisticsType = ($type == 2)? 'manual' : 'total';
			$period = $this->getRequest()->getParam('period')? : 'daily';
			$paramFrom = $this->getRequest()->getParam('fromDate');
			$paramTo = $this->getRequest()->getParam('toDate');
			
			$this->_setSearchParams();
			
			$this->view->statistics = 1;
			
			$viewParams = $this->_getViewParams($type, $period, $paramFrom, $paramTo);
			
			if(!$viewParams) {
				if(!$paramFrom) {
					$interval = ($period == 'daily' || $period == 'monthly')? 30 : 7;
					$paramFrom = preg_replace('/(\d{4})-(\d{2})-(\d{2})/', '$3.$2.$1', 
						$this->_getDateFromParam($interval));
				}
				
				$this->view->statParam = array(
					'type' => $type,
					'period' => $period,
					'fromDate' => $paramFrom,
					'toDate' => $paramTo? : date('d.m.Y')
				);
				return;
			}
			$this->_getAvailableFilters();
			
			$this->view->param = $viewParams;
			$this->view->filters = $this->_paramFilters;
			$this->view->pieData = $this->_getPieData();
			$this->view->breadcrumbs = $this->_breadcrumbs;
		}

		$this->renderScript('/statistic/index.phtml');
	}
	
	private function _getViewParams($type, $period, $paramFrom, $paramTo) {
		$fromData = DateTime::createFromFormat('d.m.Y', $paramFrom);
		$toData = DateTime::createFromFormat('d.m.Y', $paramTo);
			
		switch($period) {
			case 'daily':
				$tableSuffix = '_Daily';
				$defaultParamFrom = 30;
				$this->statInterval = '1 day';
				break;
			case 'weekly':
				$tableSuffix = '_Weekly';
				$defaultParamFrom = 7;
				$this->statInterval = '7 day';
				break;
			case 'monthly':
				$tableSuffix = '_Monthly';
				$defaultParamFrom = 30;
				$this->statInterval = '1 month';
				break;
			default:
				return false;
		}

		$fromDate = $fromData? $fromData->format('Y-m-d') : $this->_getDateFromParam($defaultParamFrom);
		
		if($period == 'monthly') {
			$fromDate = Merchant_Library_Common_Statistics::getMonthFirstDay($fromDate);
		} elseif($period == 'weekly') {
			$fromDate = Merchant_Library_Common_Statistics::getWeekFirstDay($fromDate);
		}
		
		$toDate = $toData? $toData->format('Y-m-d') : date('Y-m-d');
		
		$searchCondition = '';
		foreach($this->_search as $param => $value) {
			$searchCondition .= ' AND ' . $param . ' = ' .$value;
		}
			
		$query = 'SELECT * FROM Statistics'.$tableSuffix.' WHERE Type = '.$type 
			.' AND Date BETWEEN "'.$fromDate.'" AND "'.$toDate.'"' . $searchCondition;
		
		if($this->hasShopAccessRestrictions && isset($this->availableShops)) {
			if(empty($this->availableShops)) {
				$this->view->errorMsg .= $this->_('RESOURCE_NO_SHOP_ERR');
				return;
			}
			$query .= ' AND ServiceId IN ('.implode(',', $this->availableShops).')';
		}

		$res = $this->db->fetchAll($query);
	
		if(empty($res)) {
			return false;
		}
		return $this->_prepareStatistics($res, $type, $period, $fromDate, $toDate);
	}
	
	private function _prepareStatistics($data, $type, $period, $fromDate, $toDate) {
		$paramFrom = DateTime::createFromFormat('Y-m-d', $fromDate);
		$paramTo = DateTime::createFromFormat('Y-m-d', $toDate);
		
		$statisticsData = $this->_parseStatisticsData($data, $fromDate, $toDate);
	
		return array(
			'type' => $type,
			'period' => $period,
			'fromDate' => $paramFrom->format('d.m.Y'),
			'toDate' => $paramTo->format('d.m.Y'),
			'totalSum' => $statisticsData['totalSum'],
			'totalQuantity' => $statisticsData['totalQuantity'],
			'graphDate' => $statisticsData['graphDate'],
			'graphSum' => $statisticsData['graphSum'],
			'graphNum' => $statisticsData['graphNum']
		);
	}
	
	private function _parseStatisticsData($data, $fromDate, $toDate) {
		$totalSum = 0.00;
		$totalQuantity = 0.00;
		$graphDate = array();
		$graphSum = array();
		$graphNum = array();
		
		$statisticInfo = array(
			'Contract' => array(),
			'UserId' => array(),
			'ServiceId' => array(),
			'PaysystemTool' => array()
		);
		
		$statResults = array(
			'CONTRACT' => array(),
			'SHOP_NAME' => array(),
			'USER' => array(),
			'BILL_PAID_PAY_WAY' => array(),
			'BILL_PAID_STATUS' => array()
		);

		foreach($data as $item) {
			if(!$this->_checkVocabulary($item)) {
				continue;
			}
			$statisticInfo['Contract'][(int)$item['Contract']] = (int)$item['Contract'];
			$statisticInfo['UserId'][] = (int)$item['UserId'];
			$statisticInfo['ServiceId'][] = (int)$item['ServiceId'];
			$statisticInfo['PaysystemTool'][] = (int)$item['PaysystemTool'];
			
			$statResults['CONTRACT'][(int)$item['Contract']][] = (float)$item['Price'];
			$statResults['SHOP_NAME'][(int)$item['ServiceId']][] = (float)$item['Price'];
			$statResults['USER'][(int)$item['UserId']][] = (float)$item['Price'];
			$statResults['BILL_PAID_PAY_WAY'][(int)$item['PaysystemTool']][] = (float)$item['Price'];
			$statResults['BILL_PAID_STATUS'][(int)$item['Paid']][] = (float)$item['Price'];
			
			$totalSum += round($item['Price'], 2);
			$totalQuantity += (int)$item['Quantity'];
			$graphDate[] = strtotime($item['Date']) * 1000;
			$graphSum[] = round($item['Price'], 2);
			$graphNum[] = (int)$item['Quantity'];
		}

		$this->_getGraphParams($statResults, $graphSum, $graphNum, $graphDate, $fromDate, $toDate);
		$this->_statisticInfo = $statisticInfo;
		$graphData = $this->_getGraphData($graphDate, $graphSum, $graphNum);

		return array(
			'totalSum' => $totalSum,
			'totalQuantity' => $totalQuantity,
			'graphDate' => $graphData['dates'],
			'graphSum' => $graphData['amount'],
			'graphNum' => $graphData['num']
		);
	}
	
	private function _getGraphData($dates, $sum, $num) {
		$data = array(
			'dates' => array(),
			'amount' => array(),
			'num' => array()
		);
	
		foreach($dates as $key => $date) {
			if(!in_array($date, $data['dates'])) {
				$data['dates'][] = $date;
				$data['amount'][] = $sum[$key];
				$data['num'][] = $num[$key];
			} else {
				$existingKey = array_search($date, $data['dates']);
				$data['amount'][$existingKey] += $sum[$key];
				$data['num'][$existingKey] += $num[$key];
			}
		}
		
		$unsortedDates = $data['dates'];
		sort($data['dates']);
		
		foreach($data['dates'] as $key => $date) {
			$oldKey = array_search($date, $unsortedDates);
			$sortedAmount[$key] = $data['amount'][$oldKey];
			$sortedNum[$key] = $data['num'][$oldKey];
		}
		$data['amount'] = $sortedAmount;
		$data['num'] = $sortedNum;
		
		return $data;
	}
	
	private function _getPieData() {
		$pieData = array();
		
		if(empty($this->_paramFilters)) {
			return $pieData;
		}
			
		foreach($this->_paramFilters as $filterName => $data) {
			foreach($data as $item) {
				$pieData[$this->view->translate($filterName)][] = array(
					'filter' => $item['name'],
					'sum' => $item['sum']
				);
			}	
		}
		return $pieData;
	}
	
	private function _getGraphParams($statResults, &$sum, &$num, &$dates, $fromDate, $toDate) {
		$filterSum = array();
		$statisticDates = array();
		
		foreach($sum as $item) {
			$statisticDates[] = date('Y-m-d', $item[0]/1000);
		}
		
		$date = $fromDate;

		while($date <= $toDate) {
			if(!in_array($date, $statisticDates)) {
				$dates[] = strtotime($date) * 1000;
				$num[] = 0;
				$sum[] = 0;
			}
			
			$currentDay = new DateTime($date);
			$next = $currentDay->modify('+'.$this->statInterval);
			$date = $next->format('Y-m-d');
		}

		foreach($statResults as $param => $results) {
			foreach($results as $id => $amount) {
				$filterSum[$param][$id] = array_sum($amount);
			}
			if(!isset($filterSum[$param])) {
				continue;
			}
			arsort($filterSum[$param]);
			foreach($filterSum[$param] as $id => $value) {
				$this->_paramFilters[$param][$id]['sum'] = $value;
			}
		}
	}
	
	private function _getDateFromParam($period) {
		$date = new DateTime();
		$date->modify("-$period day");
		return $date->format('Y-m-d');
	}
	
	private function _getAvailableFilters() {
		$this->_setComplexFilter('ServiceId', 'SHOP_NAME');
		$this->_setComplexFilter('UserId', 'USER');
		$this->_setComplexFilter('Contract', 'CONTRACT');
		
		$paysystemTools = $this->_getPaysystemTools();
		
		foreach($paysystemTools as $id => $tool) {
			if(empty($this->_vocabulary['PaysystemTool']) 
				|| !in_array($id, $this->_vocabulary['PaysystemTool'])
				|| !in_array($id, $this->_statisticInfo['PaysystemTool'])
				|| $this->_addBreadcrumbs('BILL_PAID_PAY_WAY', $id, $tool)	) {
					continue;
			}	
			if(!isset($this->_search['PaysystemTool'])) {
				$this->_paramFilters['BILL_PAID_PAY_WAY'][$id]['name'] = $tool; 
			}
		}

		if($this->statisticsType == 'manual') {
			$statuses = array('2' => 'Не оплачен', '1' => 'Оплачен');
			$this->_setSimpleFilter($statuses,'BILL_PAID_STATUS');
		}
	
		$this->_unsetNeedlessFilters();
	}
	
	private function _unsetNeedlessFilters() {
		foreach($this->_paramFilters as $filterName => $filter) {
			$filterAvailable = false;
			foreach($filter as $id => $item) {
				if(isset($item['name']) && isset($item['sum'])) {
					$filterAvailable = true;
				} else {
					unset($this->_paramFilters[$filterName][$id]);
				}
			}
			
			if(!$filterAvailable) {
				unset($this->_paramFilters[$filterName]);
			}
		}
		foreach($this->_paramFilters as $filterName => $filter) {
			if(count($filter) < 2) {
				if(key($this->_paramFilters[$filterName])) {
					$data = current($this->_paramFilters[$filterName]);
					$this->_addBreadcrumbs(
						$filterName, 
						key($this->_paramFilters[$filterName]), 
						$data['name'], 
						false
					);
				}
				unset($this->_paramFilters[$filterName]);
			}
		}
	}
	
	private function _getPaysystemTools() {
		$paysystemsInfo = $this->_api->getPaysystems();
		$paysystemTools = array();
		
		if(empty($paysystemsInfo['retdata']->Paysystems)) {
			return $paysystemTools;
		}
			
		foreach($paysystemsInfo['retdata']->Paysystems as $paysystem) {
			foreach($paysystem as $p) {
				foreach($p->PaysystemTools as $tool) {
					foreach($tool as $item) {
						$paysystemTools[(int)$item->Id] = (string)$item->Description;
					}
				}
			}
		}
		return $paysystemTools;
	}
	
	private function _getVocabulary() {
		$vocabulary = array();
		
		$merchServices = Merchant_Model_Vocabulary::getMerchantServices($this->_merchId);
		foreach($merchServices as $service) {
			$vocabulary['ServiceId'][$service['ServiceId']] = $service['MerchantName'];
			
			$serviceInfo = $this->_api->getServiceInfo($service['ServiceId']);
			
			if(empty($serviceInfo['retdata']->PaysystemToolIds)) {
				continue;
			}
			
			foreach($serviceInfo['retdata']->PaysystemToolIds as $tool) {
				foreach($tool as $item) {
					$vocabulary['PaysystemTool'][(int)$item] = (int)$item;
				}
			}	
		}
		
		$merchUsers = Merchant_Model_Vocabulary::getMerchantUsers($this->_merchId);

		foreach($merchUsers as $user) {
			$vocabulary['UserId'][$user['id']] = $user['fio'];
		}
		
		$merchContracts = Merchant_Model_Vocabulary::getMerchantContracts($this->_merchId);
		foreach($merchContracts as $contract) {
			$vocabulary['Contract'][$contract['Contract']] = $contract['ContractAlias'];
		}

		return $vocabulary;
	}
	
	private function _checkVocabulary($data) {
		$checkParams = array('PaysystemTool', 'ServiceId', 'UserId', 'Contract');
	
		foreach($checkParams as $param) {
			if(($param == 'UserId' && $data[$param] == 0) 
				|| ($param == 'PaysystemTool' && $data[$param] == null)) {
				continue;
			}
			if(!isset($this->_vocabulary[$param][$data[$param]])) {
				return false;
			}
		}
		return true;
	}
	
	private function _setSearchParams() {
		foreach($this->_filters as $filterName => $column) {
			foreach($this->getRequest()->getParams() as $param => $value) {
				if(!preg_match('/filter\['.$filterName.'\]/', $param)) {
					continue;
				}

				$this->_search[$column] = $value;
			}
		}
	}
	
	private function _setComplexFilter($param, $filterName) {
		if(empty($this->_vocabulary[$param])) {
			return;
		}
		
		foreach($this->_vocabulary[$param] as $id => $value) {
			if(!in_array($id, $this->_statisticInfo[$param]) 
				|| $this->_addBreadcrumbs($filterName, $id, $value)
				|| isset($this->_search[$param])) {
				continue;
			}
					
			$this->_paramFilters[$filterName][$id]['name'] = $value;
		}
	}
	
	private function _setSimpleFilter($data, $filterName) {
		foreach($data as $id => $value) {
			if($this->_addBreadcrumbs($filterName, $id, $value)) {
				continue;
			}
			$this->_paramFilters[$filterName][$id]['name'] = $value;
		} 
	}
	
	private function _addBreadcrumbs($filterName, $id, $value, $check = true) {
		$searchParam = $this->_filters[$filterName];
		
		if($check && (empty($this->_search[$searchParam]) 
			|| ($id != $this->_search[$searchParam]))) {
				return false;
		}
		
		$this->_breadcrumbs[$filterName][$id] = $value;
		return true;
	}
}
