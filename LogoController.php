<?php

class Merchant_LogoController extends Merchant_Library_Controller_Action {

	public function indexAction() {
		$serviceId = (int)$this->_getParam('id');
		$catalog = $this->_getCatalog();
		$serviceLogoUrl = $this->_getServiceLogoUrl($serviceId, $catalog);
		
		if(!$serviceLogoUrl) {
			$this->getResponse()->setHttpResponseCode(404);
			$this->view->message = $this->view->translate('ERROR_PAGE_NOT_FOUND');
			return;
		}
		
		$logoWigth  = 88;
		$logoHeight = 31;
		
		if(!$this->_resizeImage($serviceLogoUrl, $logoWigth, $logoHeight)) {
			$this->getResponse()->setHttpResponseCode(404);
			$this->view->message = $this->view->translate('ERROR_PAGE_NOT_FOUND');
			return;
		}
		
		exit();
	}
	
	private function _getCatalog() {
		$gcConfig = Zend_Registry::get('config')->premerchant->gc;
		$client = new GC_Client_MerchAPI($gcConfig->contractor_api, $gcConfig->merchantcp->id);
		$client->cacheDir = $gcConfig->merchantcp->cache_dir . $gcConfig->merchantcp->id . '/';

		if ('login' == $gcConfig->merchantcp->login_by) {
			$client->loginAuth($gcConfig->merchantcp->login, $gcConfig->merchantcp->password);
		} else {
			$client->signAuth($gcConfig->merchantcp->key_path . $gcConfig->merchantcp->id . '_private.key');
		}

		$catalog = $client->catalog(FALSE,TRUE,FALSE);
		return simplexml_load_string($catalog);
	}
	
	private function _resizeImage($imageUrl, $requiredWidth, $requiredHight) {
		$img = getimagesize($imageUrl);
		if(!$img) {
			return false;
		}
		
		$imgFormat = basename($img['mime']);
		$createImg = 'imagecreatefrom' . $imgFormat;
		$imageSrc = $createImg($imageUrl);
		
		$imageWidth = $img[0];
		$imageHight = $img[1];
		$coefficient = $imageHight / $requiredHight;
		$newImageWidth = ceil($imageWidth / $coefficient);
		
		$finalImage = ImageCreateTrueColor ($requiredWidth, $requiredHight);
		$whiteBackground = imagecolorallocate($finalImage, 255, 255, 255);
		imagefill($finalImage, 0, 0, $whiteBackground);
		$coordXStart = ($requiredWidth - $newImageWidth) / 2;

		ImageCopyResampled ($finalImage, $imageSrc, $coordXStart, 0, 0, 0, $newImageWidth, $requiredHight, $imageWidth, $imageHight);
	
		header("content-type: image/".$imgFormat);
		$drawImage = 'Image' . $imgFormat;
		$drawImage($finalImage);
		imagedestroy($imageSrc);
	
		return true;
	}
	
	private function _getServiceLogoUrl($serviceId, $data) {
		if(!isset($data->Commands->CatalogResponseCommand)) {
			return false;
		}
		
		$catalogData = $data->Commands->CatalogResponseCommand->Catalog;
		
		for($i = 0; $i<count($catalogData); $i++) {
			$currentService = $catalogData[$i];
			$currentServiceId = (int)$currentService->Service->Id;
			if($currentServiceId == $serviceId) {
				return (string)$currentService->Service->Image;
			}
		}
		return false;
	}
}
