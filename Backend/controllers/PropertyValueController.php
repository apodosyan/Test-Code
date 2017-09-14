<?php

namespace backend\controllers;

use common\models\ProductProperty;
use common\models\Property;
use common\models\PropertyCategory;
use kartik\select2\Select2;
use Yii;
use common\models\PropertyValue;
use backend\models\PropertyValueSearch;
use yii\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * PropertyValueController implements the CRUD actions for PropertyValue model.
 */
class PropertyValueController extends AdminController
{

    public function actionGetValues($property_id = null)
    {
        $property_id = intval($property_id);
        $result = '';
        if($property_id && $property = Property::findOne(['id' => $property_id])) {
            $model = new ProductProperty();
            $form = new ActiveForm();
            $data = ArrayHelper::map(PropertyValue::find()->where(['property_id' => $property_id])->asArray()->all(), 'id', 'value_am');
            $result .= $form->field($model, 'property_value_id')->dropDownList($data);
            if($property->type) {
                $result .= $form->field($model, 'quantity')->input('number', ['min' => 0]);
            } else {
                $data = ArrayHelper::map(PropertyCategory::find()->asArray()->all(), 'id', 'name_am');
                $result .= $form->field($model, 'property_category_id')->dropDownList($data);
            }
        }
        return $result;
    }

    /**
     * Lists all PropertyValue models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new PropertyValueSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single PropertyValue model.
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
     * Creates a new PropertyValue model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($property_id = null)
    {
        $model = new PropertyValue();
        if($property_id) {
            $model->property_id = intval($property_id);
        }
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['/property/view', 'id' => $model->property_id]);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing PropertyValue model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['/property/view', 'id' => $model->property_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing PropertyValue model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect($_SERVER['HTTP_REFERER']);
    }

    /**
     * Finds the PropertyValue model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return PropertyValue the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = PropertyValue::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
