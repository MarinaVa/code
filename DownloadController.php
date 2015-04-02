<?php

class Merchant_DownloadController extends Merchant_Library_Controller_Action {
	public function getContractFileAction() {
		$redirector = $this->_helper->getHelper('Redirector');
		$contractId = (int)$this->_getParam('contractID');
		$fileId = (int)$this->_getParam('fileID');
		
		if(!$contractId || !$fileId) {
			$redirector->gotoSimple('mycontracts', 'index');
		}
		
		$merchId = $this->checkAuthRedirect('/index/myshops', '/index/login/');
		$contractor = new Merchant_Model_Contractor();
		$contractsNum = $contractor->getContractsNum($merchId, $contractId);

		if($contractsNum['count'] == 0) {
			$redirector->gotoSimple('mycontracts', 'index');
		}
		
		$fileName = $this->_getParam('fileName');
		
		$response = $this->contractsWebServiceSoapRequest(
			'GetContractDocumentFile', 
			array(
			    'contractId' => $contractId,
				'fileId'     => $fileId,
			)
		);
		$result = $response->GetContractDocumentFileResult;
	
		if(empty($result->FileDataB64)) {
            $redirector->gotoSimple('mycontracts', 'index');
		}
		
		$file = $result->FileDataB64;

        header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename='.$fileName);
		header('Pragma: no-cache');
		
		echo gzdecode(base64_decode($file));
		
		$this->_helper->layout()->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);
	}
	
	public function getDocumentFileAction() {
		$redirector = $this->_helper->getHelper('Redirector');
		$contractId = (int)$this->_getParam('contractID');
		$fileId = (int)$this->_getParam('fileID');
		
		if(!$contractId || !$fileId) {
			$redirector->gotoSimple('contractdocuments', 'index', '', array('contractID' => $contractId));
		}

		$merchId = $this->checkAuthRedirect('/index/myshops', '/index/login/');
		$contractor = new Merchant_Model_Contractor();
		$contractsNum = $contractor->getContractsNum($merchId, $contractId);

		if($contractsNum['count'] == 0) {
			$redirector->gotoSimple('contractdocuments', 'index', '', array('contractID' => $contractId));
		}
		
		$response = $this->documentsWebServiceSoapRequest(
			'GetDocumentFileForContract', 
			array(
				'contractID' => $contractId,
				'documentID' => $fileId
			)
		);

		$result = $response->GetDocumentFileForContractResult;

		if(empty($result->FileDataB64)) {
            $redirector->gotoSimple('contractdocuments', 'index', '', array('contractID' => $contractId));
		}
		
		$file = $result->FileDataB64;
		$fileExtension = $result->FileExtension;

        header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename='.$fileId.$fileExtension);
		header('Pragma: no-cache');
		
		echo gzdecode(base64_decode($file));
		
		$this->_helper->layout()->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);
	}
}
