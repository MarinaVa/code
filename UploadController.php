<?php

class Merchant_UploadController extends Merchant_Library_Controller_Action {
	public function getCsvFileAction() {
		ignore_user_abort(true);
		set_time_limit(0);
		
		DEFINE('ROOT_PATH', $_SERVER['DOCUMENT_ROOT']);
		$uploads_dir = ROOT_PATH.'../registers';
		
		$error = $_FILES["csv_file"]["error"];
		
		if($error != UPLOAD_ERR_OK) {
			$this->_createResultMessage('error', $this->view->translate('ERROR_UPLOAD_FILE'));
			exit();
		}
		
		$tmp_name = $_FILES["csv_file"]["tmp_name"];
		$pathinfo = pathinfo($_FILES["csv_file"]["name"]);
		if($pathinfo['extension'] != 'csv') {
			$this->_createResultMessage('error', $this->view->translate('ERROR_INCORRECT_FILE_EXTENSION'));
			exit();
		}
		$file_name = $uploads_dir . '/ndsregistry_'. date('Y_m_d') . '.' . $pathinfo['extension'];
		move_uploaded_file($tmp_name, $file_name);
		$this->_updateTransactionsStatus($file_name);

		exit();
	}
	
	private function _updateTransactionsStatus($file_name) {
		$transactionsList = $this->_getTransactionsList($file_name);
		
		$api = new GC_API();
		$res = $api->changeProcessorService($transactionsList);
		
		$status = isset($res['retdata']->status)? (string)$res['retdata']->status : 'error';
		$message = ($status == 'ok')? 
			$this->view->translate('SAVED_SUCCESS') : $this->view->translate('GC_FAILURE');
		$this->_createResultMessage($status, $message);
	}
	
	private function _getTransactionsList($file_name) {
		try {
			$fop = fopen($file_name, 'r');
			$transactionsList = array();
			while($line = fgetcsv($fop, filesize($file_name), ';')) {
				$transactionsList['Transaction'][] = (int)$line[0];
			}
			return $transactionsList;
			
		} catch(Exception $e) {
			$this->_createResultMessage('error', $this->view->translate('ERROR_PROCESSING_FILE'));
			exit();
		}	
	}
	
	private function _createResultMessage($status, $message) {
		echo json_encode(array(
			'status'=> $status, 
			'message' => $message
		));
	}
}
