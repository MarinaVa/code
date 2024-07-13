<?php
namespace backend\controllers;

use Yii;
use backend\models\Notification;
use common\models\NotificationMessage;

/**
 * Notification controller
 */
class NotificationController extends BackendController
{
     /**
     * Lists all Notification models.
     * @return mixed
     */
    public function actionIndex()
    {
		$searchModel = new Notification();
		$searchModel->setScenario(Notification::SCENARIO_SEARCH);
		$queryParams = Yii::$app->request->queryParams;

		$dataProvider = $searchModel->search($queryParams);

        return $this->render('index', [
			'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Notification model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Notification model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Notification();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $model->saveMessage();
            return $this->redirect(['view', 'id' => $model->id]);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing Notification model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $model->saveMessage();
            return $this->redirect(['view', 'id' => $model->id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Notification model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }
    
    /**
     * Updates status to active in Notification model.
     * The browser will be redirected to the 'index' page.
     *
     * @return mixed
     */
    public function actionStart($id)
    {
	$model = Notification::findOne($id);
        $model->is_active = Notification::STATUS_ACTIVE;
        $model->save();
        return $this->redirect(['index']);
    }
    
    /**
     * Updates status to not active in Notification model.
     * The browser will be redirected to the 'index' page.
     *
     * @return mixed
     */
    public function actionStop($id)
    {
	$model = Notification::findOne($id);
        $model->is_active = Notification::STATUS_NOT_ACTIVE;
        $model->save();
        return $this->redirect(['index']);
    }
	
    /**
     * Updates status in Notification model.
     * The browser will be redirected to the 'index' page.
     *
     * @return mixed
     */
    public function actionManage()
    {
		$params = Yii::$app->request->post();
		if(isset($params['selection'])) {
			$connection = Yii::$app->db;
			$connection->createCommand()
				->update(
					Notification::tableName(), 
					['is_active' => $params['active']], 
					['in', 'id', $params['selection']]
				)
				->execute();
		}
		
        return $this->redirect(['index']);
    }
	
	/**
     * Finds the Notification model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Notification the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Notification::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}


