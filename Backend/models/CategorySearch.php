<?php

namespace backend\models;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Category;

/**
 * CategorySearch represents the model behind the search form about `common\models\Category`.
 */
class CategorySearch extends Category
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'sort', 'is_active', 'created_at', 'updated_at'], 'integer'],
            [['slug', 'name_am', 'name_ru', 'name_en', 'description_am', 'description_ru', 'description_en', 'meta_keywords_am', 'meta_keywords_ru', 'meta_keywords_en', 'meta_description_am', 'meta_description_ru', 'meta_description_en', 'hidden_keywords_am', 'hidden_keywords_ru', 'hidden_keywords_en'], 'safe'],
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
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = Category::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'sort' => $this->sort,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ]);

        $query->andFilterWhere(['like', 'slug', $this->slug])
            ->andFilterWhere(['like', 'name_am', $this->name_am])
            ->andFilterWhere(['like', 'name_ru', $this->name_ru])
            ->andFilterWhere(['like', 'name_en', $this->name_en])
            ->andFilterWhere(['like', 'description_am', $this->description_am])
            ->andFilterWhere(['like', 'description_ru', $this->description_ru])
            ->andFilterWhere(['like', 'description_en', $this->description_en])
            ->andFilterWhere(['like', 'meta_keywords_am', $this->meta_keywords_am])
            ->andFilterWhere(['like', 'meta_keywords_ru', $this->meta_keywords_ru])
            ->andFilterWhere(['like', 'meta_keywords_en', $this->meta_keywords_en])
            ->andFilterWhere(['like', 'meta_description_am', $this->meta_description_am])
            ->andFilterWhere(['like', 'meta_description_ru', $this->meta_description_ru])
            ->andFilterWhere(['like', 'meta_description_en', $this->meta_description_en])
            ->andFilterWhere(['like', 'hidden_keywords_am', $this->hidden_keywords_am])
            ->andFilterWhere(['like', 'hidden_keywords_ru', $this->hidden_keywords_ru])
            ->andFilterWhere(['like', 'hidden_keywords_en', $this->hidden_keywords_en]);

        return $dataProvider;
    }
}
