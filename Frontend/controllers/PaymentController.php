<?php
namespace frontend\controllers;

use common\models\Email;
use common\models\OrderPayment;
use common\models\PaymentMethod;
use Yii;
use yii\base\UserException;
use yii\web\Controller;
use common\models\Order;
use yii\helpers\Url;
/**
 * Payment controller
 */
class PaymentController extends Controller
{

    public $enableCsrfValidation = false;
    private $test = false;

    public function actionIndex()
    {
        $order_ids = Yii::$app->session->get('order_ids', []);
        $id = abs(intval(Yii::$app->request->post('order_id')));

        $user_id = Yii::$app->user->id;

        $order = Order::findOne(['id' => $id]);

        if(!$order) {
            throw new UserException('order not found');
        }


        if ($order->user_id != $user_id && !in_array($id, $order_ids)) {
            throw new UserException('invalid order');
        }


        if ($order->status !== $order::STATUS_PENDING && $order->status !== $order::STATUS_PAY_CASH) {
            throw new UserException('Order already payed or canceled');
        }

        if (Yii::$app->request->post('pay_cash')) {
            $order->status = Order::STATUS_PAY_CASH;
            $order->save();
            $order->notifyCashPayment();
            $order->sendStatusEmail();
            Yii::$app->session->setFlash('success', Yii::t('app', 'Thank you! You choose cash payment method'));
            return $this->redirect(Yii::$app->request->referrer);
        }



        $method_id = Yii::$app->request->post('payment_method');
        if(!$method_id || !isset($order->paymentMethodsList[$method_id])) {
            throw new UserException('Invalid payment method');
        }
        $order->payment_method_id = $method_id;
        $order->save();

        switch ($order->payment_method_id) {

            case PaymentMethod::IDRAM_ID:
                return $this->idram($order);
                break;

            case PaymentMethod::CONVERSE_ID:
                return $this->converse($order);
                break;
        }

        throw new UserException('something went wrong');

    }

    public function actionSuccessConverse($orderId = null)
    {
        if($orderId) {
            $orderPayment = OrderPayment::findOne(['transaction' => $orderId]);
            if($orderPayment) {
                $status = $orderPayment->converseStatus();
                if($status['OrderStatus'] == 2 && ($orderPayment->amount * 100) ==  $status['Amount']) {
                    $orderPayment->status = 1;
                    $orderPayment->save();
                    $orderPayment->order->status = Order::STATUS_PAYED;
                    $orderPayment->order->save();
                    $orderPayment->order->sendStatusEmail();
                    $orderPayment->notifyAdmin();
                    return $this->redirect(Url::toRoute(['success']));
                }
            }
        }
        return $this->redirect(Url::toRoute(['fail']));
    }

    private function converse(Order $order)
    {
        $orderPayment = new OrderPayment();
        $orderPayment->amount = $order->amount;
        $orderPayment->order_id = $order->id;
        $orderPayment->payment_method_id = PaymentMethod::CONVERSE_ID;
        $orderPayment->status = OrderPayment::PENDING;
        $orderPayment->save();


        $data = [
            'orderNumber' => $this->test ? ($orderPayment->id + time()) : $orderPayment->id,
            'amount' => $orderPayment->amount*100,
            'returnUrl' => Url::toRoute('payment/success-converse', true),
            'description' => 'sportsnund.am - Order #' . $order->order_number
        ];


        $result = $orderPayment->converseRequest(PaymentMethod::CONVERSE_REGISTER, $data, false);

        $orderPayment->response_log = $result;
        $orderPayment->save();
        $result = json_decode($result, true);
        if($result && !$result['errorCode']) {
            $orderPayment->transaction = $result['orderId'];
            $orderPayment->save();
            return $this->redirect($result['formUrl']);
        } else {
            echo($result['errorMessage']); die;
        }
    }

    private function idram(Order $order)
    {
        $orderPayment = new OrderPayment();
        $orderPayment->amount = $order->amount;
        $orderPayment->order_id = $order->id;
        $orderPayment->payment_method_id = PaymentMethod::IDRAM_ID;
        $orderPayment->status = OrderPayment::PENDING;
        $orderPayment->save();

        return $this->render('idram', compact('orderPayment'));
    }

    public function actionIdramResultProcess()
    {
        $this->enableCsrfValidation = false;
        $f = fopen(Yii::getAlias('@backend').'/idram.txt', 'a');
        fputs($f, print_r($_REQUEST, true));

        if(isset($_REQUEST['EDP_BILL_NO'])) {
            $orderPayment = OrderPayment::findOne(['id' => $_REQUEST['EDP_BILL_NO']]);
            if($orderPayment) {
                if(!$orderPayment->response_log) {
                    $orderPayment->response_log = '';
                }
                $orderPayment->response_log .= '|'.json_encode($_REQUEST);
                $orderPayment->save();
            }
        }


        if(isset($_REQUEST['EDP_PRECHECK']) && isset($_REQUEST['EDP_BILL_NO']) && isset($_REQUEST['EDP_REC_ACCOUNT']) && isset($_REQUEST['EDP_AMOUNT'])) {
            if($_REQUEST['EDP_PRECHECK'] == "YES") {
                if($_REQUEST['EDP_REC_ACCOUNT'] == PaymentMethod::EDP_REC_ACCOUNT) {
                    // check if $bill_no exists in your system orders if exists then echo OK otherwise nothing
                    $orderPayment = OrderPayment::findOne(['id' => $_REQUEST['EDP_BILL_NO']]);
                    if($orderPayment && intval($orderPayment->amount) == intval($_REQUEST['EDP_AMOUNT'])) {
                        echo("OK");
                    }
                }
            }
        }

        if(isset($_REQUEST['EDP_PAYER_ACCOUNT']) && isset($_REQUEST['EDP_BILL_NO']) && isset($_REQUEST['EDP_REC_ACCOUNT']) && isset($_REQUEST['EDP_AMOUNT'])
            && isset($_REQUEST['EDP_TRANS_ID']) && isset($_REQUEST['EDP_CHECKSUM'])) {
            $txtToHash =
                PaymentMethod::EDP_REC_ACCOUNT . ":" .
                $_REQUEST['EDP_AMOUNT'] . ":" .
                PaymentMethod::SECRET_KEY . ":" .
                $_REQUEST['EDP_BILL_NO'] . ":" .
                $_REQUEST['EDP_PAYER_ACCOUNT'] . ":" .
                $_REQUEST['EDP_TRANS_ID'] . ":" .
                $_REQUEST['EDP_TRANS_DATE'];

            $orderPayment = OrderPayment::findOne(['id' => $_REQUEST['EDP_BILL_NO']]);

            if(strtoupper($_REQUEST['EDP_CHECKSUM']) != strtoupper(md5($txtToHash))){
                $orderPayment->status = OrderPayment::FAILED;

            } else { // code to handling payment success
                echo("OK");
                $orderPayment->status = OrderPayment::COMPLETED;
                $orderPayment->order->status = Order::STATUS_PAYED;
                $orderPayment->order->save();
                $orderPayment->order->sendStatusEmail();
            }
            $orderPayment->transaction = $_REQUEST['EDP_TRANS_ID'];
            $orderPayment->response_log = json_encode($_REQUEST);
            $orderPayment->save();
            $orderPayment->notifyAdmin();
        }
        fclose($f);

    }

    public function actionSuccess()
    {
        return $this->render('success');
    }

    public function actionFail()
    {
        return $this->render('fail');
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
    /**
     * @language
     */



}

