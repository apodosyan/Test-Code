<?php

namespace backend\models;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Order;

/**
 * OrderSearch represents the model behind the search form about `common\models\Order`.
 */
class OrderSearch extends Order
{
    public $customer_first_name;
    public $customer_last_name;
    public $email;
    public $orderDate;
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'amount', 'coupon_id', 'user_id', 'guest_id', 'created_at', 'updated_at', 'order_number', 'email_is_send', 'is_order_new', 'payment_method_id'], 'integer'],
            [['ip_address', 'user_notes', 'admin_notes', 'customer_first_name', 'customer_last_name', 'email'], 'safe'],
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
        $query = Order::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort'=> ['defaultOrder' => ['id'=>SORT_DESC]]
        ]);

        $this->load($params);
        if ($this->created_at) {
            $start = strtotime($this->created_at);
            $end = strtotime($this->created_at . ' 23:59:59');
            $this->created_at = $start;
        }



        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }
        $query->joinWith('guest');
        $query->joinWith('user');
        $table = Order::tableName();
        // grid filtering conditions
        $query->andFilterWhere([
            $table.'.id' => $this->id,
            $table.'.amount' => $this->amount,
            $table.'.coupon_id' => $this->coupon_id,
            $table.'.user_id' => $this->user_id,
            $table.'.guest_id' => $this->guest_id,
            $table.'.updated_at' => $this->updated_at,
            $table.'.order_number' => $this->order_number,
            $table.'.email_is_send' => $this->email_is_send,
            $table.'.is_order_new' => $this->is_order_new,
            $table.'.payment_method_id' => $this->payment_method_id,
        ]);
        if($this->created_at)
        {
            $query->andFilterWhere(['between', $table.'.created_at',$start, $end]);
        }
        $query->andFilterWhere(['like', 'ip_address', $this->ip_address])
            ->andFilterWhere(['like', 'user_notes', $this->user_notes])
            ->andFilterWhere(['like', 'admin_notes', $this->admin_notes])
            ->andFilterWhere([
            'or',
            ['like', '{{%guests}}.first_name', $this->customer_first_name],
            ['like', '{{%users}}.first_name', $this->customer_first_name],

            ])
            ->andFilterWhere([
                'or',
                ['like', '{{%guests}}.last_name', $this->customer_last_name],
                ['like', '{{%users}}.last_name', $this->customer_last_name],

            ])
            ->andFilterWhere([
                'or',
                ['like', '{{%guests}}.email', $this->email],
                ['like', '{{%users}}.email', $this->email],

            ]);
        if ($this->created_at) {
            $this->created_at = date('d.m.Y', $this->created_at);
        }
        return $dataProvider;
    }
}
