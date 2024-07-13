<?php

namespace backend\models;

use Yii;
use common\models\GCMUser as GCMUserModel;
use yii\data\ActiveDataProvider;

/**
 * GCMUser represents the model behind the search form about `common\models\GCMUser`.
 */
class GCMUser extends GCMUserModel
{
    const SCENARIO_SEARCH = 'search';
    const IOS_TOKEN_LENGTH = 64;
    const ANDROID_MIN_TOKEN_LENGTH = 140;
    const ANDROID_MAX_TOKEN_LENGTH = 183;
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['gcm_regid', 'idioma', 'plataforma'], 'required', 'on' => self::SCENARIO_DEFAULT],
            [['id', 'test', 'plataforma'], 'integer'],
            [['gcm_regid', 'idioma', 'os'], 'string'],
            ['gcm_regid', 'checkTokenIsValid']
        ];
    }
    
    public function checkTokenIsValid($attributeName)
    {
        $errorMessage =[
            'Token code is incorrect: ',
            $this->gcm_regid,
        ];
        
        $hasError = false;
        
        if($this->plataforma == self::PLATFORM_IOS) {
            if(strlen($this->gcm_regid) != self::IOS_TOKEN_LENGTH) {
                $errorMessage[] = '(Correct token length: '.self::IOS_TOKEN_LENGTH.' symbols)';
                $hasError = true;
            }
            if(!preg_match('~^[a-f0-9]+$~i', $this->gcm_regid)) {
                $errorMessage[] = '(Token should contain only alfabeth symbols and digits)';
                $hasError = true;
            }
        } else {
            if(!in_array(strlen($this->gcm_regid), [self::ANDROID_MIN_TOKEN_LENGTH, self::ANDROID_MAX_TOKEN_LENGTH])) {
                $errorMessage[] = '(Correct token length: '.self::ANDROID_MIN_TOKEN_LENGTH.' or '.self::ANDROID_MAX_TOKEN_LENGTH.' symbols)';
                $hasError = true;
            } elseif(!preg_match('~^[a-z0-9\-_]+$~i', $this->gcm_regid)) {
                $errorMessage[] = '(Token should contain only alfabeth symbols, digits and symbols "-", "_")';
                $hasError = true;
            }
        }
        
        if($hasError) {
            $errorMessage[] = '. Operation aborted';
            $this->addError($attributeName, implode('', $errorMessage));
        }
    }
    
    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        $scenarios = parent::scenarios();
	$scenarios['search'] = ['id', 'gcm_regid', 'idioma', 'plataforma', 'ultimoPush'];
	return $scenarios;
    }
    
    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if($insert) {
            $this->created_at = date('Y-m-d H:i:s');
            $this->os = ($this->plataforma == self::PLATFORM_IOS)? 'ios-7' : 'android-17';
            $this->plataforma = ($this->plataforma == self::PLATFORM_IOS);
            $this->test = 1;
        }
        return parent::beforeSave($insert);
    }
    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = self::find()->where(['test' => 1]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'gcm_regid' => $this->gcm_regid,
            'idioma' => $this->idioma,
            'plataforma' => $this->plataforma,
            'ultimoPush' => $this->ultimoPush,
        ]);

        return $dataProvider;
    }
}
