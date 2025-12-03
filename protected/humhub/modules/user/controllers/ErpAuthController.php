<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2016 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\user\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\behaviors\AccessControl;
use humhub\components\Controller;
use humhub\modules\user\authclient\ErpAuth;
use humhub\modules\user\models\User;
use humhub\modules\user\services\AuthClientService;
use humhub\modules\user\services\ErpAuthService;
use Yii;
use yii\web\HttpException;

/**
 * ErpAuthController handles authentication from external ERP system
 *
 * @since 1.0
 */
class ErpAuthController extends Controller
{
    /**
     * @inheritdoc
     */
    public $layout = '@humhub/modules/user/views/layouts/main';

    /**
     * Allow guest access independently from guest mode setting.
     *
     * @var string
     */
    public $access = ControllerAccess::class;

    /**
     * @inheritdoc
     */
    protected $doNotInterceptActionIds = ['*'];

    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = false;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'acl' => [
                'class' => AccessControl::class,
                'guestAllowedActions' => ['auth-user', 'api-login', 'validate-token'],
            ],
        ];
    }

    /**
     * Authenticate user from ERP system using email
     * 
     * @param string $user_email User's email address
     * @return \yii\web\Response
     */
    public function actionAuthUser($user_email)
    {
        // Emergency debug - write to file to confirm controller is reached
        file_put_contents(
            __DIR__ . '/../../../../../protected/runtime/erp_auth_debug.log',
            date('Y-m-d H:i:s') . " - actionAuthUser called with: {$user_email}\n",
            FILE_APPEND
        );

        try {
            Yii::info("ERP Auth: Starting authentication for - {$user_email}");

            // Validate email
            if (empty($user_email) || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
                Yii::warning("ERP Auth: Invalid email format - {$user_email}");
                Yii::$app->session->setFlash('error', 'Invalid email address');
                return $this->redirect(['/user/auth/login']);
            }

            // Find user by email
            $user = User::findOne(['email' => $user_email]);

            if (!$user) {
                Yii::warning("ERP Auth: User not found - {$user_email}");
                Yii::$app->session->setFlash('error', "User not found with email: {$user_email}");
                return $this->redirect(['/user/auth/login']);
            }

            Yii::info("ERP Auth: User found - ID: {$user->id}, Username: {$user->username}, Status: {$user->status}");

            // Check if user is enabled
            if ($user->status != User::STATUS_ENABLED) {
                Yii::warning("ERP Auth: User not enabled - {$user_email}, Status: {$user->status}, Expected: " . User::STATUS_ENABLED);
                Yii::$app->session->setFlash('error', "Your account is not enabled. Current status: {$user->status}");
                return $this->redirect(['/user/auth/login']);
            }

            // Create ERP auth client
            $erpAuthClient = new ErpAuth();

            // Authenticate the user
            if ($erpAuthClient->authByEmail($user_email)) {
                Yii::info("ERP Auth: ErpAuth client authenticated successfully");

                // Log the user in
                $loginResult = Yii::$app->user->login($user, 0);
                Yii::info("ERP Auth: Login attempt result: " . ($loginResult ? 'success' : 'failed'));

                if ($loginResult) {
                    Yii::info("ERP Auth: User logged in successfully - {$user_email}");

                    // Store login source in session
                    Yii::$app->session->set('erp_login', true);
                    Yii::$app->session->set('erp_login_email', $user_email);

                    // Redirect to dashboard
                    return $this->redirect(['/dashboard/dashboard']);
                } else {
                    Yii::warning("ERP Auth: Yii user login failed - {$user_email}");
                    Yii::$app->session->setFlash('error', 'Failed to create user session');
                    return $this->redirect(['/user/auth/login']);
                }
            }

            Yii::warning("ERP Auth: ErpAuth client authentication failed - {$user_email}");
            Yii::$app->session->setFlash('error', 'Authentication failed');
            return $this->redirect(['/user/auth/login']);

        } catch (\Exception $e) {
            Yii::error("ERP Auth Error: {$e->getMessage()}");
            Yii::error("ERP Auth Stack Trace: " . $e->getTraceAsString());
            Yii::$app->session->setFlash('error', 'An error occurred during authentication: ' . $e->getMessage());
            return $this->redirect(['/user/auth/login']);
        }
    }

    /**
     * API endpoint to authenticate user and return token
     * 
     * @return array
     */
    public function actionApiLogin()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            $email = Yii::$app->request->post('email');

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'status' => false,
                    'message' => 'Invalid email address'
                ];
            }

            // Find user by email
            $user = User::findOne(['email' => $email]);

            if (!$user) {
                return [
                    'status' => false,
                    'message' => 'User not found'
                ];
            }

            // Check if user is enabled
            if ($user->status != User::STATUS_ENABLED) {
                return [
                    'status' => false,
                    'message' => 'User account is not enabled'
                ];
            }

            // Create ERP auth client
            $erpAuthClient = new ErpAuth();

            // Authenticate the user
            if ($erpAuthClient->authByEmail($email)) {
                // Generate authentication URL
                $authUrl = Yii::$app->urlManager->createAbsoluteUrl([
                    '/user/erp-auth/auth-user',
                    'user_email' => $email
                ]);

                return [
                    'status' => true,
                    'message' => 'Authentication successful',
                    'auth_url' => $authUrl,
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'username' => $user->username,
                        'displayName' => $user->displayName
                    ]
                ];
            }

            return [
                'status' => false,
                'message' => 'Authentication failed'
            ];

        } catch (\Exception $e) {
            Yii::error("ERP API Auth Error: {$e->getMessage()}");

            return [
                'status' => false,
                'message' => 'An error occurred during authentication'
            ];
        }
    }

    /**
     * API endpoint to validate SSO token from ERP
     * Called by ERP to verify the token before authenticating user
     * 
     * @return array
     */
    public function actionValidateToken()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            $token = Yii::$app->request->post('token');

            if (empty($token)) {
                return [
                    'status' => false,
                    'message' => 'Token is required'
                ];
            }

            // Use ErpAuthService to validate token
            $erpAuthService = new ErpAuthService();
            $tokenData = $erpAuthService->validateSsoToken($token);

            if (!$tokenData) {
                return [
                    'status' => false,
                    'message' => 'Token invalide ou expiré'
                ];
            }

            // Verify user exists and is enabled
            $user = User::findOne(['email' => $tokenData['email']]);

            if (!$user) {
                Yii::warning("ERP SSO: User not found for validated token", [
                    'email' => $tokenData['email']
                ]);
                return [
                    'status' => false,
                    'message' => 'Utilisateur non trouvé'
                ];
            }

            if ($user->status != User::STATUS_ENABLED) {
                Yii::warning("ERP SSO: User not enabled", [
                    'email' => $tokenData['email'],
                    'status' => $user->status
                ]);
                return [
                    'status' => false,
                    'message' => 'Compte désactivé'
                ];
            }

            Yii::info("ERP SSO: Token validated successfully", [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return [
                'status' => true,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'username' => $user->username,
                    'displayName' => $user->displayName,
                ]
            ];

        } catch (\Exception $e) {
            Yii::error("ERP SSO Token Validation Error: {$e->getMessage()}");

            return [
                'status' => false,
                'message' => 'An error occurred during token validation'
            ];
        }
    }
}

