<?php

namespace backend\models;

use Yii;
use common\models\PushLog as PushLogModel;
use yii\data\ActiveDataProvider;
use yii\db\Expression;

/**
 * PushLog represents the model behind the search form about `common\models\PushLog`.
 */
class PushLog extends PushLogModel
{
	const SCENARIO_SEARCH = 'search';
	
	/**
     * @inheritdoc
     */
    public function scenarios()
    {
        $scenarios = parent::scenarios();
		$scenarios['search'] = ['id', 'notification_id', 'gcm_user_id', 'gcm_regid', 'error_code', 'error_message', 'platform', 'created_at'];
		return $scenarios;
    }
	
	/**
     * @inheritdoc
     */
	public function afterFind()
    {
        $this->created_at = date('Y-m-d H:i:s', $this->created_at);
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
        $query = self::find();

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
            'notification_id' => $this->notification_id,
			'gcm_user_id' => $this->gcm_user_id,
            'gcm_regid' => $this->gcm_regid,
			'error_code' => $this->error_code,
			'platform' => $this->platform,
            'updated_at' => $this->updated_at
        ]);

        $query->andFilterWhere(['like', 'error_message', $this->error_message]);
		$query->andFilterWhere(['like', new Expression("FROM_UNIXTIME(created_at)"), $this->created_at]);

        return $dataProvider;
    }
}
