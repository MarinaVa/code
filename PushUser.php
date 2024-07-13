<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use common\models\Notification;
use common\models\GCMUser;
use common\models\PushLog;
use console\models\Push;

/**
 * This is the model class for table "push_users".
 */
class PushUser extends \yii\db\ActiveRecord
{
	const STATUS_PROCESS = 'process';
	const STATUS_SENT    = 'sent';
	const STATUS_ERROR   = 'error';
	
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'push_users';
    }
	
	/**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }
	
	/**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['gcm_user_id', 'notification_id', 'lang'], 'required'],
            [['gcm_user_id', 'notification_id'], 'integer'],
            [['status', 'lang'], 'string'],
            ['status', 'in', 'range' => ['process', 'error', 'sent']],
            ['gcm_user_id', 'exist', 'targetClass' => GCMUser::className(), 'targetAttribute' => 'id'],
            ['notification_id', 'exist', 'targetClass' => Notification::className(), 'targetAttribute' => 'id'],
            ['gcm_user_id', 'unique', 'targetAttribute' => ['gcm_user_id', 'notification_id']]
        ];
    }
	
	public function getUser()
    {
        return $this->hasOne(GCMUser::className(), ['id' => 'gcm_user_id']);
    }
	
	public function getNotification()
    {
        return $this->hasOne(Notification::className(), ['id' => 'notification_id']);
    }
	
	/**
     * Updates send status for users
     *
     * @param Notification $notification
	 * @param array $users
	 * @param string $status
	 * @param int $errorCode
	 * @param string $errorMessage
	 * 
	 * @return bool
     */
	public static function updateStatus($notification, $users, $status, $errorCode = null, $errorMessage = null)
	{
		if(empty($users)) {
			return true;
		}
	
		$command = Yii::$app->db->createCommand();
		
		if($status == PushUser::STATUS_PROCESS) {
			
			$connection = \Yii::$app->db;
			$transaction = $connection->beginTransaction(); 
			foreach($users as $userId) {
				$pushUser = new PushUser();
				$pushUser->gcm_user_id = $userId;
				$pushUser->notification_id = $notification->id;
                                $pushUser->lang = Push::getCurrentLanguage();
				if(!$pushUser->save()) {
					$transaction->rollback();
					return false;
				}
			}
			$transaction->commit();
		} else {
			$command->update('push_users', 
				['status' => $status], 
				['and', 'notification_id = :notification_id', ['in', 'gcm_user_id', $users]],
				[':notification_id' => $notification->id]
			)->execute();
                        
                        self::savePushInfoForDuplicateTokens($notification, $users, $status);
		}
		
		foreach($users as $userId) {
			if($status == self::STATUS_ERROR) {
				Push::log($notification, $userId, $errorCode, $errorMessage);
			} elseif($status == self::STATUS_SENT) {
				$user = GCMUser::findOne($userId);
				$user->ultimoPush = date('Y-m-d H:i:s');
				$user->save();
			}
		}
		return true;
	}
	
	/**
     * Removes users from send queue
     *
     * @param int $notificationId
	 * @param array $users
	 * 
	 * @return bool
     */
	public static function removeFromSendQueue($notificationId, $users) 
        {
		return PushUser::deleteAll(
			['and', 'notification_id = :notification_id', ['in', 'gcm_user_id', $users]],
			[':notification_id' => $notificationId]
		);
	}
        
        /**
         * Saves push info for duplicate tokens
         *
         * @param Notification $notification
	 * @param array $users
         * @param string $status
         * 
        */
        private static function savePushInfoForDuplicateTokens($notification, $users, $status) 
        {
            $tokensInfo = Push::getTokensInfo();
            $currentUsersTokens = [];
                        
            foreach($tokensInfo as $userId => $token) {
                if(!in_array($userId, $users)) {
                    continue;
                }
                $currentUsersTokens[] = $token;
            }
                        
            $usersWithPushedTokens = GCMUser::find()
                ->select('id')
                ->where(['in', 'gcm_regid', $currentUsersTokens])
                ->column();
                        
            $duplicateUserIds = array_diff($usersWithPushedTokens, $users);
                      
            foreach($duplicateUserIds as $userId) {
                $pushUser = new PushUser();
                $pushUser->gcm_user_id = $userId;
                $pushUser->notification_id = $notification->id;
                $pushUser->lang = Push::getCurrentLanguage();
                $pushUser->status = $status;
                $pushUser->save();
            }
        }   
}