<?php
namespace frontend\controllers;

use common\models\Brand;
use common\models\Category;
use common\models\City;
use common\models\EmailsTemplate;
use common\models\Order;
use common\models\Page;
use common\models\PhoneCall;
use common\models\Product;
use common\models\ShippingAddress;
use common\models\User;
use frontend\models\ProductSearch;
use Yii;
use yii\base\InvalidParamException;
use yii\data\ActiveDataProvider;
use yii\helpers\Url;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use frontend\models\LoginForm;
use frontend\models\PasswordResetRequestForm;
use frontend\models\ResetPasswordForm;
use frontend\models\SignupForm;
use frontend\models\ContactForm;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\widgets\ActiveForm;

/**
 * Site controller
 */
class SiteController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout', 'signup'],
                'rules' => [
                    [
                        'actions' => ['signup'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * This function will be triggered when user is successfuly authenticated using some oAuth client.
     *
     * @param Yii\authclient\ClientInterface $client
     * @return boolean|Yii\web\Response
     */
    public function oAuthSuccess($client)
    {
        // get user data from client
        $userAttributes = $client->getUserAttributes();
        if (isset($userAttributes['id']) && $userAttributes['id']) {
            $user = User::findOne(['fb_id' => $userAttributes['id']]);
            if (!$user) {
                if (isset($userAttributes['email']) && $userAttributes['email'] && $user = User::findByEmail($userAttributes['email'])) {
                    $user->fb_id = $userAttributes['id'];
                    $user->save();
                } else {
                    $user = new User();
                    $user->fb_id = $userAttributes['id'];
                    unset($userAttributes['id']);
                    $user->setAttributes($userAttributes);
                    if (!$user->email) {
                        $user->email = '-';
                    } else {
                    }
                    $user->username = 'fb_' . $user->fb_id;

                    $user->save();
                }
            }
            if ($user->id && $user->fb_id) {
                Yii::$app->user->login($user);
            }
        }

        // do some thing with user data. for example with $userAttributes['email']
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'auth' => [
                'class' => 'yii\authclient\AuthAction',
                'successCallback' => [$this, 'oAuthSuccess'],
                'successUrl' => Url::toRoute(['user-profile'])
            ],
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return mixed
     */
    public function actionIndex()
    {
        Yii::$app->view->params['activeMenu'] = Yii::t('app', 'Home');
        return $this->render('index');
    }


    /**
     * Displays shop page
     * @param string $category
     * @return mixed
     */
    public function actionShop($category = '')
    {
        Yii::$app->view->params['activeMenu'] = Yii::t('app', 'Products');
        $searchModel = new ProductSearch();
        $search = $searchModel->search($category, $queryParams = Yii::$app->request->queryParams);
        $dataProvider = $search['dataProvider'];
        $categoryDescription = $search['categoryDescription'];
        $title = $search['title'];
        $hiddenKeywords = $search['hiddenKeywords'];

        $categoryProduct = Category::find()->where(['parent_id' => 0])->all();
        $newProduct = new Product();
        $types = $newProduct->types;
        $brands = Brand::find()->all();

        return $this->render('shop', compact('dataProvider', 'queryParams', 'brands', 'types', 'categoryProduct', 'title', 'hiddenKeywords', 'categoryDescription'));
    }

    public function actionPage($slug)
    {
        $page = Page::find()->where(['slug' => $slug])->one();
        if($page){
            return $this->render('page', compact('page'));
        }
        throw new NotFoundHttpException('Page not exist');
    }


    public function actionOrdersHistory(){
        $user_id = \Yii::$app->user->identity->id;
        $ordersHistory = Order::find()->where(['user_id' => $user_id])->orderBy(['id' => SORT_DESC])->all();;
        return $this->render('orders_history', compact('ordersHistory'));
    }
    /**
     * Displays product page
     * @param integer $product_id
     * @return mixed
     * @throws NotFoundHttpException
     */
    public function actionProduct($product_id = null)
    {
        $product_id = abs(intval($product_id));
        if($product_id && $product = Product::find()->where(['id' => $product_id])->andWhere(['is_available' => 1])->with(['category'])->one()) {
            \Yii::$app->view->registerMetaTag([
                'name' => 'description',
                'content' =>  $product->metaDescription,
            ]);
            \Yii::$app->view->registerMetaTag([
                'name' => 'keywords',
                'content' => $product->metaKeywords,
            ]);
            $images = [];
            foreach ($product->getBehavior('galleryBehavior')->getImages() as $image) {
                $images[] = [
                    'url' => $image->getUrl('original'),
                    'src' => $image->getUrl('preview'),
                    'name' => $image->name
                ];
            }

            $firstImg = $images ? $images[0]['url'] : $product->imageUrl;

            return $this->render('product', compact('product', 'images', 'firstImg'));
        }
        throw new NotFoundHttpException('Product not exist');


    }

    /**
     * User profile
     *
     *
     */
    public function actionUserProfile(){
        if (!Yii::$app->user->isGuest) {
            $newShippingAAddress = new ShippingAddress();
            $user_id = \Yii::$app->user->identity->id;
            $user = User::findOne(['id' => $user_id]);
            $cities = City::find()->all();
            $shipping_addresses = ShippingAddress::find()->where(['user_id' => $user_id])->all();
            if (Yii::$app->request->isAjax && $user->load(Yii::$app->request->post()))
            {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ActiveForm::validate($user);
            }
            if ($user->load(Yii::$app->request->post()) && $user->validate()) {
                $user->save();
                return $this->redirect(Url::current());
            } else {
                return $this->render('profile_page', compact('user', 'shipping_addresses', 'cities', 'newShippingAAddress'));
            }

        }
        return $this->redirect('index');
    }

    public function actionChangePass(){
        if (!Yii::$app->user->isGuest) {
            $user_id = \Yii::$app->user->identity->id;
            $user = User::findOne(['id' => $user_id]);
            if($user && Yii::$app->request->post('old_pass') && Yii::$app->request->post('new_pass')){
                if(password_verify(Yii::$app->request->post('old_pass'), $user->password_hash)){
                    $hash = Yii::$app->getSecurity()->generatePasswordHash(Yii::$app->request->post('new_pass'));
                    Yii::$app->session->setFlash('success',Yii::t('app','Password changed successfully'));
                    $user->password_hash = $hash;

                }else{
                    Yii::$app->session->setFlash('warning',Yii::t('app','Wrong password'));
                }
                $user->save();
            }
            return $this->redirect(['user-profile']);
        }
        return $this->redirect('index');
    }


    public function actionShippingAddress(){
        $shipping_address = new ShippingAddress();
        if($shipping_address->load(Yii::$app->request->post())) {
            $shipping_address->user_id = \Yii::$app->user->identity->id;
            $shipping_address->save();
        }
        return $this->redirect(Url::to(['site/user-profile']));
    }
    
    public function actionDelShippingAddress($id)
    {
        ShippingAddress::findOne(['id' => $id])->delete();
        return $this->redirect(Url::to(['site/user-profile']));
    }


    /**
     * Update ShippingAddres
     */
    public function actionShippingAddressUpdate()
    {
        $shipping_address = ShippingAddress::findOne(['id' => Yii::$app->request->post('shipping_address_id')]);
        if($shipping_address->load(Yii::$app->request->post())) {
            $shipping_address->user_id = \Yii::$app->user->identity->id;
            $shipping_address->save();
        }
        return $this->redirect(Url::to(['site/user-profile']));
    }
    
    public function actionOrderCall()
    { $phoneCall = new PhoneCall();
        if($phoneCall->load(Yii::$app->request->post())) {
            if ($phoneCall->call_time) {
                $phoneCall->call_time = date('Y-m-d H:i:00', strtotime($phoneCall->call_time.' 00:00:00'));
            }
            $phoneCall->status = PhoneCall::STATUS_PENDING;
            if($phoneCall->save()){

                Yii::$app->session->setFlash('success',Yii::t('app','Your Call Order saved') );
            } else {
                Yii::$app->session->setFlash('error', Yii::t('app','There was an error saving Call Order.'));
            }
        }
       $this->redirect('index');
    }
    
    /**
     * Displays shop.
     *
     * @return mixed
     */
    public function actionCheckout()
    {
        return $this->render('checkout');
    }


    /**
     * Displays shop.
     *
     * @return mixed
     */
    public function actionBrandView($id)
    {

        return $id;
    }


    /**
     * Logs in a user.
     *
     * @return mixed
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        $signupModel = new SignupForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        } else {
            return $this->render('login', [
                'model' => $model,
                'signupModel' => $signupModel
            ]);
        }
    }

    /**
     * Logs out the current user.
     *
     * @return mixed
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Displays contact page.
     *
     * @return mixed
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail(Yii::$app->params['adminEmail'])) {
                Yii::$app->session->setFlash('success', 'Thank you for contacting us. We will respond to you as soon as possible.');
            } else {
                Yii::$app->session->setFlash('error', 'There was an error sending email.');
            }

            return $this->refresh();
        } else {
            return $this->render('contact', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Displays about page.
     *
     * @return mixed
     */
    public function actionAbout()
    {
        return $this->render('about');
    }

    /**
     * Signs user up.
     *
     * @return mixed
     */
    public function actionSignup()
    {
        $model = new LoginForm();
        $signupModel = new SignupForm();
        if ($signupModel->load(Yii::$app->request->post())) {
            if ($user = $signupModel->signup()) {
                if (Yii::$app->getUser()->login($user)) {
                    $emailTemplate = EmailsTemplate::findOne(['id' => EmailsTemplate::SIGN_UP, 'enabled' => 1]);
                    if($emailTemplate) {
                        $emailTemplate->sendMail($user);
                    }
                    return $this->goHome();
                }
            }
        }

        return $this->render('login', [
            'model' => $model,
            'signupModel' => $signupModel,
        ]);
    }

    /**
     * Requests password reset.
     *
     * @return mixed
     */
    public function actionRequestPasswordReset()
    {
        $model = new PasswordResetRequestForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail()) {
                Yii::$app->session->setFlash('success', 'Check your email for further instructions.');

                return $this->goHome();
            } else {
                Yii::$app->session->setFlash('error', 'Sorry, we are unable to reset password for email provided.');
            }
        }

        return $this->render('requestPasswordResetToken', [
            'model' => $model,
        ]);
    }

    /**
     * Resets password.
     *
     * @param string $token
     * @return mixed
     * @throws BadRequestHttpException
     */
    public function actionResetPassword($token)
    {
        try {
            $model = new ResetPasswordForm($token);
        } catch (InvalidParamException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->resetPassword()) {
            Yii::$app->session->setFlash('success', 'New password was saved.');

            return $this->goHome();
        }

        return $this->render('resetPassword', [
            'model' => $model,
        ]);
    }
}
