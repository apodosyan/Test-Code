<?php
namespace frontend\models;

use yii\base\Model;
use common\models\User;
use Yii;
/**
 * Signup form
 */
class SignupForm extends Model
{
    public $username;
    public $email;
    public $password;
    public $first_name;
    public $last_name;
    public $birth_year;
    public $phone;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['username', 'trim'],
            ['username', 'required', 'message' => Yii::t('app','Username cannot be blank')],
            ['username', 'unique', 'targetClass' => '\common\models\User', 'message' => 'This username has already been taken.'],
            ['username', 'string', 'min' => 2, 'max' => 255],

            ['email', 'trim'],
            ['email', 'required', 'message' => Yii::t('app','Email cannot be blank.')],
            ['email', 'email'],
            ['email', 'string', 'max' => 255],
            [['phone'], 'string', 'max' => 50],
            ['email', 'unique', 'targetClass' => '\common\models\User', 'message' => 'This email address has already been taken.'],

            ['first_name', 'trim'],
            ['first_name', 'required', 'message' => Yii::t('app','Name cannot be blank.')],
            ['last_name', 'trim'],
            ['last_name', 'required', 'message' => Yii::t('app','Surname cannot be blank.')],
            ['first_name', 'string', 'min' => 2, 'max' => 255],
            ['last_name', 'string', 'min' => 2, 'max' => 255],
            [['birth_year'], 'integer'],
            ['password', 'required', 'message' => Yii::t('app','Password cannot be blank.')],
            ['password', 'string', 'min' => 6],
            [['first_name', 'username', 'last_name', 'email', 'phone'], 'filter', 'filter' => 'strip_tags'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'username' => Yii::t('app','Username'),
            'first_name' => Yii::t('app','Name'),
            'last_name' => Yii::t('app','Surname'),
            'email' => 'Email',
            'phone' => Yii::t('app','Phone number'),
            'password' => Yii::t('app','Password'),
            'rememberMe' => Yii::t('app','Remember Me'),
            'birth_year' => Yii::t('app','Date of birth'),
        ];
    }

    /**
     * Signs user up.
     *
     * @return User|null the saved model or null if saving fails
     */
    public function signup()
    {
        if (!$this->validate()) {
            return null;
        }

        $user = new User();
        $user->username = $this->username;
        $user->first_name = $this->first_name;
        $user->last_name = $this->last_name;
        $user->email = $this->email;
        $user->phone = $this->phone;
        $user->birth_year = $this->birth_year;
        $user->setPassword($this->password);
        $user->generateAuthKey();
        return $user->save(false) ? $user : null;
    }
}
