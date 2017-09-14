<?php

namespace frontend\models;

use common\models\Product;
use common\models\ProductProperty;
use Yii;
use yii\base\Model;

/**
 * Cart is the model behind the cart form.
 */
class Cart extends Model
{
    public $qty;
    public $product_id;
    public $product_properties;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['qty', 'product_id'], 'required'],
            ['qty', 'compare', 'compareValue' => 0, 'operator' => '>'],
            [['qty', 'product_id'], 'integer'],
            ['product_properties', 'each', 'rule' => ['integer']],
            [['product_id'], 'exist', 'skipOnError' => true, 'targetClass' => Product::className(), 'targetAttribute' => ['product_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'qty' => Yii::t('app', 'Quantity'),
            'product_id' => Yii::t('app', 'Product id'),
        ];
    }

    public static function getCartInfo($cartData = null)
    {
        if (!$cartData) {
            $cookies = Yii::$app->request->cookies;
            $cartData = $cookies->getValue('cart', []);
        }

        if ($cartData) {
            $total = 0;
            $price = 0;
            foreach ($cartData as $product_id => $data) {
                $product = Product::find()->select(['price'])->where(['id' => $product_id])->limit(1)->one();
                /** @var \common\models\Product $product */
                if ($product) {
                    $qty = 0;
                    foreach ($data as $details) {
                        $qty += intval($details['qty']);
                    }
                    $price += $qty * $product->price;
                    $total += $qty;
                }
            }

            return [
                'total' => $total,
                'price' => $price . ' ' . Yii::t('app', 'dram'),
            ];
        }
        return [
            'total' => 0,
            'price' => ''
        ];
    }

    public static function getCartDetails()
    {
        $cookies = Yii::$app->request->cookies;
        $cartData = $cookies->getValue('cart', []);
        $tableData = [];
        foreach ($cartData as $product_id => $productDetails) {
            $product = Product::findOne(['id' => $product_id]);
            if ($product) {
                foreach ($productDetails as $key => $details) {
                    $data = [
                        'url' => $product->viewUrl,
                        'img' => $product->imageUrl,
                        'product' => $product->name,
                        'key' => $key,
                        'product_id' => $product->id,
                        'product_properties' => [],
                        'price' => $product->price,
                        'qty' => $details['qty'],
                        'total' => $product->price * $details['qty']
                    ];
//                    if ($details['product_properties']) {
//                        foreach ($details['product_properties'] as $productPropertyId) {
//                            $productProperty = ProductProperty::findOne(['id' => $productPropertyId, 'product_id' => $product->id]);
//                            if ($productProperty) {
//                                $data['product_properties'][] = [
//                                    'property' => $productProperty->property->name,
//                                    'value' => $productProperty->propertyValue->value,
//                                    'id' => $productProperty->id
//                                ];
//                            }
//                        }
//                    }
                    $tableData[] = $data;
                }
            }
        }
        return $tableData;
    }

}
