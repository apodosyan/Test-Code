<?php

namespace frontend\models;

use common\models\Category;
use kcfinder\text;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Product;
use yii\web\NotFoundHttpException;

/**
 * ProductSearch represents the model behind the search form about `common\models\Product`.
 */
class ProductSearch extends Product
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'price', 'category_id', 'quantity', 'is_available', 'created_at', 'updated_at'], 'integer'],
            [['name_am', 'name_ru', 'name_en', 'description_am', 'description_ru', 'description_en', 'meta_keywords_am', 'meta_keywords_ru', 'meta_keywords_en', 'meta_description_am', 'meta_description_ru', 'meta_description_en', 'hidden_keywords_am', 'hidden_keywords_ru', 'hidden_keywords_en'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param string $category
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($category, $params)
    {
        $query = Product::find()->where(['is_available' => 1])->orderBy('sort DESC');
        $title = $hiddenKeywords = $categoryDescription = '';
        if ($category) {
            $categoryModel = Category::find()->where(['slug' => $category])->one();
            /* @var $categoryModel Category */
            if (!$categoryModel) {
                throw new NotFoundHttpException('Category not found');
            }
            if ($categoryModel) {
                $categoryDescription = $categoryModel->description;
                $categoryIds = $categoryModel->allChildsIds;
                $categoryIds[] = $categoryModel->id;
                $query->andWhere(['IN', 'category_id', $categoryIds]);
                $title = $categoryModel->name;
                $hiddenKeywords = $categoryModel->hiddenKeywords;
                Yii::$app->view->params['activeMenu'] = $categoryModel->name;
                \Yii::$app->view->registerMetaTag([
                    'name' => 'description',
                    'content' => $categoryModel->metaDescription,
                ]);
                \Yii::$app->view->registerMetaTag([
                    'name' => 'keywords',
                    'content' => $categoryModel->metaKeywords,
                ]);
            }
        }
        if (isset($params['weight']) && $params['weight'] != '') {
            $query->andWhere(['weight' => $params['weight']]);
        }
        if (isset($params['taste']) && $params['taste'] != '') {
            $query->andWhere(['taste' => $params['taste']]);
        }
        if (isset($params['servings']) && $params['servings'] != '') {
            $query->andWhere(['servings' => $params['servings']]);
        }
        if (isset($params['price']) && $params['price'] != '') {
            switch ($params['price']) {
                case '25000' :
                    $query->andWhere(['<=', 'price', 25000]);
                    break;
                case '50000' :
                    $query->andWhere(['>=', 'price', 25000]);
                    $query->andWhere(['<=', 'price', 50000]);
                    break;
                case '50001' :
                    $query->andWhere(['>=', 'price', 50000]);
                    break;
            }
        }
        if (isset($params['type'])) {
            $query->andWhere(['IN', 'type', $params['type']]);
        }
        if (isset($params['brand'])) {
            $query->andWhere(['IN', 'brand_id', $params['brand']]);
        }
        if (isset($params['s']) && $params['s'] != '') {
            $s = $params['s'];
            $query->andWhere(['LIKE', 'description_am', $s])
                ->orWhere(['LIKE', 'description_ru', $s])
                ->orWhere(['LIKE', 'description_en', $s])
                ->orWhere(['LIKE', 'name_am', $s])
                ->orWhere(['LIKE', 'name_ru', $s])
                ->orWhere(['LIKE', 'name_en', $s])
                ->orWhere(['LIKE', 'meta_keywords_am', $s])
                ->orWhere(['LIKE', 'meta_keywords_ru', $s])
                ->orWhere(['LIKE', 'meta_keywords_en', $s])
                ->orWhere(['LIKE', 'meta_description_am', $s])
                ->orWhere(['LIKE', 'meta_description_ru', $s])
                ->orWhere(['LIKE', 'meta_description_en', $s])
                ->orWhere(['LIKE', 'hidden_keywords_am', $s])
                ->orWhere(['LIKE', 'hidden_keywords_ru', $s])
                ->orWhere(['LIKE', 'hidden_keywords_en', $s])
                ->orderBy('sort ASC');
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 9,
                'pageSizeParam' => false,
            ],
        ]);

        return compact('dataProvider', 'categoryDescription', 'title', 'hiddenKeywords');
    }
}
