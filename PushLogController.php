<?php
namespace backend\controllers;

use Yii;
use backend\models\PushLog;

/**
 * PushLog controller
 */
class PushLogController extends BackendController
{
     /**
     * Lists all PushLog models.
     * @return mixed
     */
    public function actionIndex()
    {
		$searchModel = new PushLog();
		$searchModel->setScenario(PushLog::SCENARIO_SEARCH);
		$queryParams = Yii::$app->request->queryParams;

		$dataProvider = $searchModel->search($queryParams);

        return $this->render('index', [
			'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single PushLog model.
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
     * Deletes an existing PushLog model.
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
     * Finds the PushLog model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return PushLog the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = PushLog::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
