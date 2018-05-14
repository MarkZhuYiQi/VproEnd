<?php
/**
 * Created by PhpStorm.
 * User: Red
 * Date: 2017/12/28
 * Time: 20:42
 */

namespace app\common;

use yii\filters\auth\AuthMethod;


/**
 * HttpBearerAuth is an action filter that supports the authentication method based on HTTP Bearer token.
 *
 * You may use HttpBearerAuth by attaching it as a behavior to a controller or module, like the following:
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         'bearerAuth' => [
 *             'class' => \yii\filters\auth\HttpBearerAuth::className(),
 *         ],
 *     ];
 * }
 * ```
 *
 */
class JwtAuth extends AuthMethod
{
    /**
     * @inheritdoc
     */
    public function authenticate($user, $request, $response)
    {
        $authHeader = $request->getHeaders()->get('X-Token');
        if ($authHeader !== null) {
            $identity = $user->loginByAccessToken($authHeader, get_class($this));
            if ($identity === null) {
                $this->handleFailure($response);
            }
            return $identity;
        }
        return null;
    }
    public function beforeAction($action)
    {
        if ($this->isPreFligt(\Yii::$app->request)) {
            return true;
        }

        return parent::beforeAction($action);
    }

    protected function isPreFligt($request)
    {
        return $request->isOptions;
    }
}