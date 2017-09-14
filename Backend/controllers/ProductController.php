<?php

namespace backend\controllers;

use common\models\ProductProperty;
use Yii;
use common\models\Product;
use backend\models\ProductSearch;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;
use zxbodya\yii2\galleryManager\GalleryManagerAction;

/**
 * ProductController implements the CRUD actions for Product model.
 */
class ProductController extends AdminController
{
    /**
     * @return array
     */
    public function actions()
    {
        $actions = parent::actions();
        $actions['galleryApi'] = [
            'class' => GalleryManagerAction::className(),
            // mappings between type names and model classes (should be the same as in behaviour)
            'types' => [
                'product' => Product::className()
            ]
        ];
        return $actions;
    }


    /**
     * Copy product
     * @param $id
     * @return mixed
     * @throws NotFoundHttpException
     */
    public function actionCopy($id)
    {
        $product = $this->findModel($id);
        if(!$product) {
            throw new NotFoundHttpException(Yii::t('app', 'Product not found'));
        }
        $copy = new Product();
        $attributes = $product->attributes;
        unset($attributes['id']);
        unset($attributes['created_at']);
        unset($attributes['updated_at']);
        unset($attributes['img']);

        $copy->setAttributes($attributes);
        $copy->name_am .= ' copy';
        if(!$copy->save()) {
            print_r($copy->getErrors());
            return false;
        }
        if($product->img) {
            copy($product->imagePath, $copy->imagePath);
            $copy->save();
        }
        if($product->productProperty) {
            foreach ($product->productProperty as $productProperty) {
                $copyProperty = new ProductProperty();
                $attributes = $productProperty->attributes;
                unset($attributes['id']);
                unset($attributes['created_at']);
                unset($attributes['updated_at']);
                $attributes['product_id'] = $copy->id;
                $copyProperty->setAttributes($attributes);
                $copyProperty->save();
            }
        }
        return $this->redirect(Url::toRoute(['product/view', 'id' => $copy->id]));
    }

    /**
     * Lists all Product models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new ProductSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Product model.
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
     * Creates a new Product model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Product();
        $model->is_available = 1;

        if ($model->load(Yii::$app->request->post())) {
            $model->imageFile = UploadedFile::getInstance($model, 'imageFile');
            if( $model->save()) {
                $model->upload();
                Yii::$app->session->setFlash('success',  Yii::t('app', 'Product added. Now you can add product images'));
                return $this->redirect(['update', 'id' => $model->id]);
            }
        }
        return $this->render('create', [
            'model' => $model,
        ]);

    }

    /**
     * Updates an existing Product model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
            if(!$model->img)
                $model->img = md5($model->id.'_'.time());
            $model->imageFile = UploadedFile::getInstance($model, 'imageFile');
            if( $model->save()) {
                $model->upload();
                return $this->redirect(['update', 'id' => $model->id]);
            }
            return $this->redirect(['update', 'id' => $model->id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Product model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        $model->delete();
        return $this->redirect(['index']);
    }

    /**
     * Finds the Product model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Product the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Product::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
