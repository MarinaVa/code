<?php

namespace common\models;

use Yii;
use common\models\scopes\Notification as NotificationScope;
use common\models\NotificationMessage;
use common\models\PushUser;
use common\models\GCMUser;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "notification".
 *
 * @property integer $id
 * @property string $start_time
 * @property string $run_time
 * @property string $link
 * @property string $icon_file
 * @property string $image_file
 * @property string $platform
 * @property integer $is_active
 * @property integer $created_at
 * @property integer $updated_at
 */
class Notification extends \yii\db\ActiveRecord
{

    const STATUS_NOT_ACTIVE = 0;
    const STATUS_ACTIVE = 1;
    
    public $message = [];
    public $elapsed_time;
	
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
            [['start_time', 'link', 'icon_file', 'platform', 'is_active'], 'required', 'on' => self::SCENARIO_DEFAULT],
            [['id', 'run_time', 'is_active', 'created_at', 'updated_at'], 'integer'],
            [['start_time', 'link', 'icon_file', 'platform'], 'string'],
            [['start_time'], 'date', 'format' => 'php:H:i'],
            [['link'], 'url'],
            [['image_file'], 'file', 'extensions' => 'png, jpg, gif'],
            ['platform', 'in', 'range' => ['ios', 'android']],
            ['message', 'each', 'rule' => ['string']],
        ];
    }
    
    /**
     * @inheritdoc
     */
	public function afterFind()
    {
        $this->message = NotificationMessage::getListByNotificationId($this->id);
        parent::afterFind();
    }
	
	public static function find()
    {
        return new NotificationScope(get_called_class());
    }
	
	public function getPushUsers()
    {
        return $this->hasMany(PushUser::className(), ['notification_id' => 'id'])
			->andOnCondition(['in', 'push_users.status', ['sent', 'error']]);
    }
	
	public static function getCountRecepients($notification)
    {
		$query = GCMUser::find()
			->select('COUNT(*)')
			->where(['plataforma' => ($notification->platform == 'ios'? GCMUser::PLATFORM_IOS : GCMUser::PLATFORM_ANDROID)])
                        ->andWhere(['test' => 1]);
		return $query->scalar();
    }
	
	public static function getPlatformsList()
	{
		$platforms = ['', 'ios', 'android'];
		return array_combine($platforms, $platforms);
	}
}


