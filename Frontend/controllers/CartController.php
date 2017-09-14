<?php
namespace frontend\controllers;


use common\models\Coupon;
use common\models\Guest;
use common\models\Order;
use common\models\OrderAddress;
use common\models\OrderProduct;
use common\models\OrderProductProperty;
use common\models\Product;
use common\models\ProductProperty;
use common\models\ShippingAddress;
use Yii;

use yii\base\UserException;
use yii\bootstrap\Alert;
use yii\helpers\Url;
use yii\web\Controller;
use yii\filters\VerbFilter;

use yii\web\Cookie;
use yii\web\MethodNotAllowedHttpException;
use frontend\models\Cart;
use yii\web\NotFoundHttpException;

/**
 * Site controller
 */
class CartController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'add' => ['post'],
                    'remove' => ['get'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $cartDetails = Cart::getCartDetails();
        $orderAddress = new OrderAddress();
        if (!Yii::$app->user->isGuest) {
            $orderAddress->email = Yii::$app->user->identity->email;
            $orderAddress->first_name = Yii::$app->user->identity->first_name;
            $orderAddress->last_name = Yii::$app->user->identity->last_name;
            $orderAddress->phone = Yii::$app->user->identity->phone;

        }
        if (Yii::$app->request->post()) {

            if ($cartDetails && $orderAddress->load(Yii::$app->request->post()) && $orderAddress->validate()) {

                if ($orderAddress->date) {
                    $orderAddress->date = date('Y-m-d H:i:s', strtotime($orderAddress->date));
                }
                $order = new Order();
                $order->status = Order::STATUS_PENDING;
                if (Yii::$app->request->post('user_notes')) {
                    $order->user_notes = Yii::$app->request->post('user_notes');
                }
                $order->user_id = Yii::$app->user->id;
                if (!$order->user_id) {
                    $guest = new Guest();
                    $guest->email = $orderAddress->email;
                    $guest->first_name = $orderAddress->first_name;
                    $guest->last_name = $orderAddress->last_name;
                    $guest->phone = $orderAddress->phone;
                    if (!$guest->save()) {
                        throw new UserException(Yii::t('app', 'Something went wrong'));
                    }
                    $order->guest_id = $guest->id;
                }

                $cartInfo = Cart::getCartInfo();

                if ($cartInfo['price']) {
                    $order->amount = abs(intval($cartInfo['price']));
                    $order->ip_address = Yii::$app->request->userIP;
                    if ($order->save()) {
                        foreach ($cartDetails as $product_details) {
                            $product = Product::findOne(['id' => $product_details['product_id'], 'is_available' => 1]);
                            if ($product) {

                                $orderProduct = new OrderProduct();
                                $orderProduct->order_id = $order->id;
                                $orderProduct->product_id = $product->id;
                                $orderProduct->qty = abs(intval($product_details['qty']));

                                $orderProduct->price = $product->price;

                                if ($orderProduct->save()) {
                                    if ($product_details['product_properties']) {
                                        foreach ($product_details['product_properties'] as $propertyDetail) {
                                            $productProperty = ProductProperty::findOne(['id' => $propertyDetail['id']]);
                                            if ($productProperty) {
                                                if (!$productProperty->quantity) {
                                                    continue;
                                                }
                                                if ($orderProduct->qty > $productProperty->quantity) {
                                                    $orderProduct->qty = $productProperty->quantity;
                                                }

                                                $orderProductProperty = new OrderProductProperty();
                                                $orderProductProperty->order_product_id = $orderProduct->id;
                                                $orderProductProperty->product_property_id = $productProperty->id;
                                                $orderProductProperty->save();
                                            }
                                        }
                                        $orderProduct->save();
                                    }

//                                    $product->quantity -= $orderProduct->qty;
                                    $product->save();
                                }


                            }
                        }
                        $amount = 0;
                        foreach ($order->orderProducts as $orderProduct) {
                            $amount += $orderProduct->qty * $orderProduct->price;
                        }
                        if ($coupon_code = Yii::$app->request->post('coupon')) {
                            $coupon = Coupon::findOne(['code' => $coupon_code, 'is_used' => 0]);
                            if ($coupon) {
                                $order->coupon_id = $coupon->id;
                                $coupon->is_used = 1;
                                $coupon->save();
                                $amount -= $coupon->amount;
                            }
                        }
                        $order->amount = $amount;
                        $order->save();
                        $orderAddress->order_id = $order->id;
                        $orderAddress->save();

                        $order->amount += $order->orderAddress->shippingMethod->price;
                        $order->save();
                        if ($order->user_id) {
                            if (!ShippingAddress::find()->where([
                                'city_id' => $orderAddress->city_id,
                                'address' => $orderAddress->address,
                                'postal_code' => $orderAddress->postal_code,
                                'phone' => $orderAddress->phone,
                                'first_name' => $orderAddress->first_name,
                                'last_name' => $orderAddress->last_name
                            ])->count()
                            ) {
                                $shipping_address = new ShippingAddress();
                                $shipping_address->city_id = $orderAddress->city_id;
                                $shipping_address->postal_code = $orderAddress->postal_code;
                                $shipping_address->address = $orderAddress->address;
                                $shipping_address->user_id = $order->user_id;
                                $shipping_address->phone = $orderAddress->phone;
                                $shipping_address->first_name = $orderAddress->first_name;
                                $shipping_address->last_name = $orderAddress->last_name;
                                $shipping_address->save();
                            }
                        }
                        $session = Yii::$app->session;
                        $order_ids = $session->get('order_ids', []);
                        $order_ids[] = $order->id;
                        Yii::$app->session->set('order_ids', $order_ids);
                        Yii::$app->response->cookies->remove('cart');

                        $order->notifyNewOrder();

                        return $this->redirect(Url::toRoute(['cart/checkout/' . $order->id]));

                    }
                }
            }
            throw new UserException(Yii::t('app', 'Something went wrong'));
        }

        return $this->render('cart', compact('cartDetails', 'orderAddress'));
    }

    public function actionCheckout($order_id = 0)
    {
        $order_id = abs(intval($order_id));
        $order_ids = Yii::$app->session->get('order_ids', []);
        $order = null;
        if ($order_ids && in_array($order_id, $order_ids)) {
            $order = Order::findOne(['id' => $order_id]);
        } else {
            $user_id = Yii::$app->user->id;
            if ($user_id) {
                $order = Order::findOne(['id' => $order_id, 'user_id' => $user_id]);
            }
        }
        if (!$order) {
            throw new NotFoundHttpException();
        }
        // echo '<pre>';
        //print_r($order->attributes);
        //print_r($order->orderProducts);
        return $this->render('checkout', compact('order'));

    }

    /**
     * Adding product to cart cookie
     * @return string Json response with data
     * 'status' => 1,
     * 'total' => $total,
     * 'price' => $price,
     * 'message' => Yii::t('app', 'Cart updated')
     * @throws MethodNotAllowedHttpException
     */
    public function actionAdd()
    {

        if (!Yii::$app->request->isAjax) {
            throw new MethodNotAllowedHttpException();
        }
        $cart = new Cart();


        if ($cart->load(Yii::$app->request->post()) && $cart->validate()) {
            $product = Product::find()->where(['id' => $cart->product_id, 'is_available' => 1])->one();
            if (!$product) {
                $result = [
                    'status' => 0,
                    'message' => Alert::widget([
                        'body' => Yii::t('app', 'Product is not available'),
                        'options' => [
                            'class' => 'alert-danger'
                        ]
                    ])
                ];
                return json_encode($result);
            }
            $cookies = Yii::$app->request->cookies;
            $cartData = $cookies->getValue('cart', []);


            $attributes = $cart->attributes;
            unset($attributes['product_id']);
            if (!isset($cartData[$cart->product_id])) { // if we don't have current product in cart
                $cartData[$cart->product_id] = [$attributes]; // adding that product to cart with key product_id
            } else {

                /* ---- if we already have current product in cart --- */
                $qty = 0;
                foreach ($cartData[$cart->product_id] as $data) {
                    $qty += $data['qty'];
                }


                $attributes = $cart->attributes;
                unset($attributes['product_id']);

                // checking if we have same product with same properties in cart
                foreach ($cartData[$cart->product_id] as &$existDataArray) { // checking current product chosen properties in cart
                    $existData = &$existDataArray['product_properties'];
                    $newData = &$attributes['product_properties'];
                    if ((!$existData && !$newData) // if current product don't have chosen properties and now also we didn't choose properties
                        || ($existData && $newData // or we have chosen properties in cart and now we also choose properties
                            && !array_diff($existData, $newData) && !array_diff($newData, $existData)) // and chosen properties are same (in existing cart properties, and properties which we adding now)
                    ) {
                        // this mean they are same products with same chosen properties or both don't have chosen properties (properties like color, weight etc)
//                        foreach ($existData as $key => $productPropertyId) {
//                            if ($productPropertyId) {
//                                $productProperty = ProductProperty::findOne(['id' => $productPropertyId, 'product_id' => $cart->product_id]);
//                                if (!$productProperty) {
//                                    unset($existData[$key]);
//                                    continue;
//                                }
//                                if ($productProperty->quantity !== null) {
//                                    if ($cart->qty + $existDataArray['qty'] > $productProperty->quantity) {
//                                        $cart->qty = $productProperty->quantity - $existDataArray['qty'];
//                                        if ($cart->qty < 0) {
//                                            $cart->qty = 0;
//                                        }
//                                    }
//                                }
//                            }
//
//                        }
                        $existDataArray['qty'] += $cart->qty; // adding new quantity in existing
                        $exist = true; // same product with same properties already exist in cart
                        break; // don't need check for others properties for current product
                    }
                }
                if (!isset($exist)) { // if we don't have this product in cart with same properties

//                    foreach ($newData as $key => $productPropertyId) {
//                        if ($productPropertyId) {
//                            $productProperty = ProductProperty::findOne(['id' => $productPropertyId, 'product_id' => $cart->product_id]);
//                            if (!$productProperty) {
//                                unset($newData[$key]);
//                                continue;
//                            }
//                            if ($productProperty->quantity !== null) {
//                                if ($cart->qty > $productProperty->quantity) {
//                                    $cart->qty = $productProperty->quantity;
//                                }
//                            }
//                        }
//                    }

                    $attributes = $cart->attributes;
                    unset($attributes['product_id']);

                    if ($attributes['qty']) {
                        $cartData[$cart->product_id][] = $attributes; // adding new properties for current product in cart. using product_id key
                    }
                }
            }

            $cookies = Yii::$app->response->cookies;

            $cookies->add(new Cookie([
                'name' => 'cart',
                'value' => $cartData,
                'expire' => time() + 2 * 24 * 60 * 60
            ]));

            $result = Cart::getCartInfo($cartData);
            $result['status'] = 1;
            $result['message'] = Alert::widget([
                'body' => Yii::t('app', 'Cart updated'),
                'options' => [
                    'class' => 'alert-info'
                ]
            ]);


        } else {
            $result = [
                'status' => 0,
                'message' => Alert::widget([
                    'body' => Yii::t('app', 'Something went wrong'),
                    'options' => [
                        'class' => 'alert-danger'
                    ]
                ])
            ];
        }
        return json_encode($result);
    }

    public function actionRemove()
    {
        $product_id = abs(intval(Yii::$app->request->get('product_id')));
        $key = abs(intval(Yii::$app->request->get('key')));
        if ($product_id) {
            $cookies = Yii::$app->request->cookies;
            $cartData = $cookies->getValue('cart', []);
            if ($cartData) {
                if (isset($cartData[$product_id][$key])) {
                    unset($cartData[$product_id][$key]);
                    if (!$cartData[$product_id]) {
                        unset($cartData[$product_id]);
                    }
                    $cookies = Yii::$app->response->cookies;

                    $cookies->add(new Cookie([
                        'name' => 'cart',
                        'value' => $cartData,
                        'expire' => time() + 2 * 24 * 60 * 60
                    ]));
                }
            }
        }
        return $this->redirect(['index']);
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }


}
