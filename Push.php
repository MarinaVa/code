<?php

namespace console\models;
use common\models\GCMUser;
use common\models\PushUser;
use common\models\NotificationMessage;
use common\models\PushLog;
use common\models\Notification;
use yii\helpers\ArrayHelper;
use yii\base\Exception;
use yii\helpers\Url;
use yii;

class Push 
{
	const PUSHES_PER_ITERATION = 100;
	
        private static $language;
	private static $tokensInfo = [];
	private $users = [];
	private $usersList = [];
	
	/**
     * For sending notification to users
     *
     * @param Notification $notification
     *
    */
	public function notifyUsers($notification)
	{	
		do {
                        if(!$notification->run_time) {
                            $notification->run_time = time();
                            $notification->save(false);
                        }
			$query = GCMUser::find()
				->select(['gcm_users.id', 'gcm_regid', 'idioma'])
				->leftJoin('push_users', 'push_users.gcm_user_id = gcm_users.id and push_users.notification_id = ' . $notification->id)
				->where(['push_users.gcm_user_id' => null])
				->andWhere(['not', ['gcm_users.gcm_regid' => null]])
				->andWhere(['plataforma' => ($notification->platform == 'ios'? GCMUser::PLATFORM_IOS : GCMUser::PLATFORM_ANDROID)])
                                ->andWhere(['test' => 1]);

			$result = $query->limit(self::PUSHES_PER_ITERATION)->all();
			
                        if(empty($result)) {
                            break;
			}
                        
			$this->removeDuplicateTokens($result);
                        
                        $userGroups = [];
                        foreach($result as $item) {
                            $language = GCMUser::getLangEquivalent($item['idioma']);
                            $userGroups[$language][] = $item;
                        }
                        
                        foreach($userGroups as $language => $currentGroup) {
                           
                            self::$tokensInfo = ArrayHelper::map($currentGroup, 'id', 'gcm_regid');
                            self::$language = $language;
                            $this->users = ArrayHelper::getColumn($currentGroup, 'id');
                            $this->usersList = $this->users;
			
                            $tokens = ArrayHelper::getColumn($currentGroup, 'gcm_regid');
		
                            if(PushUser::updateStatus($notification, $this->users, PushUser::STATUS_PROCESS)) {
                                $message = NotificationMessage::find()
                                    ->where(['notification_id' => $notification->id])
                                    ->andWhere(['lang' => $language])
                                    ->select('message')
                                    ->scalar();
            
                                if(!$message) {
                                    continue;
                                }
                               
				try {
                                    $this->send($notification, $message, $tokens);
                                    PushUser::updateStatus($notification, $this->users, PushUser::STATUS_SENT);
				} catch(\Exception $e) {
                                    PushUser::removeFromSendQueue($notification->id, $this->users);
				}
                            }
                        }			
			$notificationIsAvailable = (bool)Notification::find()->where(['id' => $notification->id])->available()->one();
		} while($notificationIsAvailable);
	}
	
	/**
     * Sends notification using users' tokens list
     *
     * @param Notification $notification
     * @param string $message
     * @param array $tokens
     *
     * @return \ApnsPHP_Message | \PHP_GCM\Message
     */
	private function send($notification, $message, $tokens)
	{   
            try {
		if($notification->platform == 'ios') {
                    return $this->sendIos($notification, $message, $tokens);
		} elseif($notification->platform == 'android') {
                    return $this->sendAndroid($notification, $message, $tokens);
		}
            } catch(\Exception $e) {
		throw new Exception($e->getMessage(), $e->getCode());
            }
	}
	
	/**
     * Sends IOS notification using users' tokens list
     *
     * @param Notification $notification
     * @param string $message
     * @param array $tokens
     *
     * @return \ApnsPHP_Message
     */
	private function sendIos($notification, $message, $tokens)
	{
		/* @var $apnsGcm \bryglen\apnsgcm\Apns */
		$apns = Yii::$app->apns;
                
		try {
			$result = $apns->sendMulti($tokens, $message,
				[
                                    'link' => $notification->link,
                                    'icon' => Url::toRoute('/').'images/icons/'.$notification->icon_file,
                                    'image' => $notification->image_file? 
                                        (Url::toRoute('/').'images/uploads/'.$notification->image_file) : null
                                ],
				[
                                    'sound' => 'default',
                                    'badge' => 1
				]
			);
			if(!empty($apns->errors)) {
				$this->processApnsErrors($notification, $apns->errors);
			}
			return $result;
		} catch (\Exception $e) {
			switch($e->getCode()) {
				case 0:
					preg_match('/Invalid device token \'(.+)\'/', $e->getMessage(), $matches);
					if(isset($matches[1]) && $userId = array_search($matches[1], $this->getTokensInfo())) {
						PushUser::updateStatus($notification, [$userId], PushUser::STATUS_ERROR, $e->getCode(), $e->getMessage());
						$this->unsetUser($userId);
					}
					break;
				case 40001:
					$query = $this->getQueryFromErrorMessage($e->getMessage());
					Yii::$app->db->createCommand($query)->execute();
					break;
				default:
					$this->log($notification, null, $e->getCode(), $e->getMessage());
					$notification->is_active = Notification::STATUS_NOT_ACTIVE;
					$notification->save(false);
                                        break;
			}
			
			throw new Exception($e->getMessage(), $e->getCode());
		}
	}
	
	/**
     * Sends Android notification using users' tokens list
     *
     * @param Notification $notification
     * @param string $message
     * @param array $tokens
     *
     * @return \PHP_GCM\Message
     */
	private function sendAndroid($notification, $message, $tokens)
	{
		/* @var $apnsGcm \bryglen\apnsgcm\Gcm */
		$gcm = Yii::$app->gcm;
                
		try {
			$result = $gcm->sendMulti($tokens, $message,
				[
                                    'content' => $message.': '.$notification->link,
                                    'title' => $message,
                                    'link' => $notification->link,
                                    'icon' => Url::toRoute('/').'images/icons/'.$notification->icon_file,
                                    'image' => $notification->image_file? 
                                        (Url::toRoute('/').'images/uploads/'.$notification->image_file) : null
                                ],
				[
				    'timeToLive' => 3
				]
			);
			
			if(!empty($gcm->errors)) {
				$this->processGcmErrors($notification, $gcm->errors);
			}
			
			if(!empty($gcm->results)) {
				$this->processGcmResults($notification, $gcm->results);
			}
			return $result;
		} catch (\Exception $e) {
			switch($e->getCode()) {
				case 40001:
					$query = $this->getQueryFromErrorMessage($e->getMessage());
					Yii::$app->db->createCommand($query)->execute();
					break;
				default:
					$this->log($notification, null, $e->getCode(), $e->getMessage());
                                        $notification->is_active = Notification::STATUS_NOT_ACTIVE;
					$notification->save(false);
					break;
			}
			
			throw new Exception($e->getMessage(), $e->getCode());
		}
	}
	
	/**
     * For processing APNS errors
     *
     * @param Notification $notification
	 * @param array $errors
     *
     */
	private function processApnsErrors($notification, $errors)
	{
		foreach($errors as $errorInfo) {
			foreach($errorInfo['ERRORS'] as $error) {
				
				$userId = $this->usersList[($error['identifier'] - 1)];
				
				switch($error['statusCode']) {
					case 2:
					case 5:
					case 8:
						PushUser::updateStatus($notification, [$userId], PushUser::STATUS_ERROR, $error['statusCode'], $error['statusMessage']);
						break;
					case 3:
					case 4:
					case 6:
					case 7:
						$this->log($notification, $userId, $error['statusCode'], $error['statusMessage']);
						$notification->is_active = Notification::STATUS_NOT_ACTIVE;
						$notification->save(false);
						break;
					case 0:
						PushUser::updateStatus($notification, [$userId], PushUser::STATUS_SENT);
						break;
					default:
						$this->log($notification, $userId, $error['statusCode'], $error['statusMessage']);
						
						PushUser::removeFromSendQueue($notification->id, [$userId]);
						break;
				}
				$this->unsetUser($userId);
			}
		}
	}
	
	/**
     * For processing GCM errors
     *
     * @param Notification $notification
	 * @param array $errors
     *
     */
	private function processGcmErrors($notification, $errors)
	{
		PushUser::removeFromSendQueue($notification->id, $this->users);
		
		$this->users = [];
		foreach($errors as $error) {
			if(strip_tags($error) != $error) {
				preg_match('/\<H1\>(.+)<\/H1\>/', $error, $errorMessage);
				preg_match('/\<H2\>Error (\d{3})<\/H2\>/', $error, $errorCode);
				$errMessage = isset($errorMessage[1])? $errorMessage[1] : null;
			} else {
				$errMessage = $error;
			}
			
			$errCode = isset($errorCode[1])? $errorCode[1] : null;
			
			if($errMessage) {
				$this->log($notification, null, $errCode, $errMessage);
			}
		}
		$notification->is_active = Notification::STATUS_NOT_ACTIVE;
		$notification->save(false);
	}
	
	/**
     * For processing GCM results
     *
     * @param Notification $notification
	 * @param array $results
     *
     */
	private function processGcmResults($notification, $results)
	{
		foreach($results as $id => $result) {
			$userId = $this->usersList[$id];
			
			if($errorCode = $result->getErrorCode()) {
				switch($errorCode) {
					case 'Unavailable':
					case 'InternalServerError':
						PushUser::removeFromSendQueue($notification->id, [$userId]);
						break;
					case 'InvalidRegistration':
					case 'NotRegistered':
					case 'MissingRegistration':
                                        case 'MismatchSenderId':
						PushUser::updateStatus($notification, [$userId], PushUser::STATUS_ERROR, null, $errorCode);
						break;
					default:
						$this->log($notification, $userId, null, $errorCode);
						$notification->is_active = Notification::STATUS_NOT_ACTIVE;
						$notification->save(false);
						break;
				}
				$this->unsetUser($userId);
			} elseif($registrationId = $result->getCanonicalRegistrationId()) {
				PushUser::updateStatus($notification, [$userId], PushUser::STATUS_SENT);
				$this->unsetUser($userId);
				GCMUser::setPushToken($userId, $registrationId);
			}
		}
	}
	
	/**
     * Unsetting user from the list after changing send status
     *
     * @param int $userId
	 * 
     */
	private function unsetUser($userId)
	{
		if(isset($this->users[array_search($userId, $this->users)])) {
			unset($this->users[array_search($userId, $this->users)]);
		}
	}
	
	/**
     * Getting tokens info
     *
     * @param int $userId
	 * 
	 * @return array|string
     */
	public static function getTokensInfo($userId = null)
	{
		$tokensInfo = self::$tokensInfo;
		if($userId) {
			return isset($tokensInfo[$userId])? $tokensInfo[$userId] : null;
		}
		return $tokensInfo;
	}
	
	/**
     * Logging errors
     *
     * @param Notification $notification
	 * @param int $userId
	 * @param int $errCode
	 * @param string $errMsg
	 * 
     */
	public static function log($notification, $userId, $errCode, $errMsg)
	{
		$log = new PushLog();
		$log->notification_id = $notification->id;
		if($userId) {
			$log->gcm_user_id = $userId;
			$log->gcm_regid = self::getTokensInfo($userId);
		}
		$log->error_code = $errCode;
		$log->error_message = $errMsg;
		$log->platform = $notification->platform;
		$log->save();
	}
	
	/**
     * Getting sql query text from error message
     *
     * @param string $errorMessage
	 * 
	 * @return string|bool
	 * 
     */
	public function getQueryFromErrorMessage($errorMessage)
	{
		preg_match('/The SQL being executed was:(.+)/', $errorMessage, $matches);
		return isset($matches[1])? trim($matches[1]) : false;
	}
        
        public static function getCurrentLanguage()
        {
            return self::$language;
        }
        
        /**
         * Removes duplicate tokens before sending pushes
         *
         * @param array $tokensData
	 * 
        */
        private function removeDuplicateTokens(&$tokensData)
        {
            $data = ArrayHelper::toArray($tokensData);
            $tokens = ArrayHelper::getColumn($data, 'gcm_regid');
            $countTokensDuplicates = array_count_values($tokens);
            
            foreach($countTokensDuplicates as $token => $count) {
                if ($count > 1) {
                    foreach($data as $key => $item) {
                        if($count == 1) {
                            break;
                        }
                        
                        if($item['gcm_regid'] == $token) {
                            unset($data[$key]);
                            --$count;
                        }
                    } 
                }
            }
            sort($data);
            $tokensData = (object) $data;
        }
}
