<?php
namespace frontend\controllers;

use common\models\Coupon;
use common\models\ShippingAddress;
use Yii;
use yii\web\Controller;
use common\models\City;

/**
 * Site controller
 */
class AjaxController extends Controller
{

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionGetShippingAddresses($shipping_address_id)
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $shipping_address = ShippingAddress::findOne(['id' => $shipping_address_id]);
        $cities = City::find()->all();
        $address = $shipping_address->address;
        $postal_code = $shipping_address->postal_code;
        $first_name = $shipping_address->first_name;
        $last_name = $shipping_address->last_name;
        $phone = $shipping_address->phone;
        $city_options = [];
        foreach ($cities as $key => $city) {
            if ($city->id == $shipping_address->city_id) {
                $city_options[$key] = '<option value="' . $city->id . '" selected>' . $city->name . '</option>';
            } else {
                $city_options[$key] = '<option value="' . $city->id . '">' . $city->name . '</option>';
            }
        }
       
        return [
            'city_options' => $city_options,
            'address' => $address,
            'postal_code' => $postal_code,
            'shipping_address_id' =>  $shipping_address_id,
            'first_name' =>  $first_name,
            'last_name' =>  $last_name,
            'phone' =>  $phone,
        ];
    }

    public function actionCheckCoupon($coupon='')
    {
        $coupon = Coupon::findOne(['code' => $coupon]);
        $result = [
            'result' => true
        ];
        if($coupon) {
            if($coupon->is_used) {
                $result['message'] = 'Կտրոնը օգտագործված է';
            } else {
                $result['amount'] = $coupon->amount;
                $result['message'] = 'Կտրոնի զեղչի չափն է ' . $coupon->amount . ' դրամ';
            }
        } else {
            $result['message'] = 'Այդպիսի Կտրոն գոյություն չունի';
        }
        return json_encode($result);
    }

    

}
