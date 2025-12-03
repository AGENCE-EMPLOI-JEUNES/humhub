<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2015 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\user\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use humhub\components\Response;
use humhub\helpers\DeviceDetectorHelper;
use humhub\modules\user\authclient\AuthAction;
use humhub\modules\user\authclient\BaseFormAuth;
use humhub\modules\user\events\UserEvent;
use humhub\modules\user\models\forms\Login;
use humhub\modules\user\models\Invite;
use humhub\modules\user\models\Session;
use humhub\modules\user\models\User;
use humhub\modules\user\Module;
use humhub\modules\user\services\AuthClientService;
use humhub\modules\user\services\InviteRegistrationService;
use humhub\modules\user\services\LinkRegistrationService;
use Throwable;
use Yii;
use yii\authclient\BaseClient;
use yii\base\Exception;
use yii\captcha\CaptchaAction;
use yii\web\Cookie;
use yii\web\HttpException;

/**
 * AuthController handles login and logout
 *
 * @since 0.5
 *
 * @property Module $module
 */
class AuthController extends Controller
{
    /**
     * @event Triggered after an successful login. Note: In contrast to User::EVENT_AFTER_LOGIN, this event is triggered
     * after the response is generated.
     */
    public const EVENT_AFTER_LOGIN = 'afterLogin';

    /**
     * @event Triggered after an successful login but before checking user status
     */
    public const EVENT_BEFORE_CHECKING_USER_STATUS = 'beforeCheckingUserStatus';

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
    public function actions()
    {
        return [
            'captcha' => [
                'class' => CaptchaAction::class,
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
            'external' => [
                'class' => AuthAction::class,
                'successCallback' => [$this, 'onAuthSuccess'],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        // Allow automated logout requests from mobile app
        if ($action->id === 'logout' && DeviceDetectorHelper::isAppRequest()) {
            $this->enableCsrfValidation = false;
        }

        // Remove authClient from session - if already exists
        Yii::$app->session->remove('authClient');

        return parent::beforeAction($action);
    }

    /**
     * Displays the login page
     */
    public function actionLogin()
    {
        // ==== ERP SSO AUTHENTICATION ====
        $erp_token = Yii::$app->request->get('erp_token');
        if ($erp_token) {
            Yii::info("ERP SSO: Token-based authentication attempt", [
                'token_preview' => substr($erp_token, 0, 10) . '...'
            ]);

            try {
                // Validate token with ERP API
                $erpApiUrl = Yii::$app->params['erpApiUrl'] ?? 'http://localhost:8000/api';

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $erpApiUrl . '/humhub/validate-token');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['token' => $erp_token]));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($curlError) {
                    Yii::error("ERP SSO: cURL error - {$curlError}");
                    Yii::$app->session->setFlash('error', Yii::t('UserModule.base', 'Authentication service unavailable.'));
                } elseif ($httpCode === 200) {
                    $data = json_decode($response, true);

                    if ($data && isset($data['status']) && $data['status'] === true && isset($data['user']['email'])) {
                        $email = $data['user']['email'];
                        Yii::info("ERP SSO: Token validated successfully for {$email}");

                        $user = User::findOne(['email' => $email]);
                        if ($user && $user->status == User::STATUS_ENABLED) {
                            if (Yii::$app->user->login($user, 0)) {
                                Yii::info("ERP SSO: User {$email} logged in successfully");
                                Yii::$app->session->set('erp_login', true);
                                Yii::$app->session->set('erp_login_time', time());
                                Yii::$app->session->set('erp_user_data', $data['user']);
                                return $this->redirect(['/dashboard/dashboard']);
                            } else {
                                Yii::warning("ERP SSO: Login failed for enabled user {$email}");
                            }
                        } else {
                            Yii::warning("ERP SSO: User {$email} not found or not enabled in HumHub");
                        }
                    } else {
                        Yii::warning("ERP SSO: Invalid response from ERP API", ['response' => $response]);
                    }
                } else {
                    Yii::warning("ERP SSO: Token validation failed", [
                        'http_code' => $httpCode,
                        'response' => $response
                    ]);
                }

            } catch (\Exception $e) {
                Yii::error("ERP SSO: Exception during token validation - {$e->getMessage()}");
            }

            Yii::$app->session->setFlash('error', Yii::t('UserModule.base', 'Invalid login credentials.'));
        }
        // ==== END ERP SSO ====

        // If user is already logged in, redirect him to the dashboard
        if (!Yii::$app->user->isGuest) {
            return $this->goBack();
        }

        // Login Form Handling
        $login = new Login();
        if ($login->load(Yii::$app->request->post()) && $login->validate()) {
            return $this->onAuthSuccess($login->authClient);
        }

        // Self Invite
        $invite = new Invite();
        $invite->scenario = Invite::SCENARIO_INVITE;
        if ($invite->load(Yii::$app->request->post()) && $invite->selfInvite()) {
            if (Yii::$app->request->isAjax) {
                return $this->renderAjax('register_success_modal', ['model' => $invite]);
            } else {
                return $this->render('register_success', ['model' => $invite]);
            }
        }

        $loginParams = [
            'model' => $login,
            'invite' => $invite,
            'canRegister' => $invite->allowSelfInvite(),
            'passwordRecoveryRoute' => $this->module->passwordRecoveryRoute,
            'showLoginForm' => $this->module->showLoginForm || Yii::$app->request->get('showLoginForm', false),
            'showRegistrationForm' => $this->module->showRegistrationForm,
        ];

        if (Yii::$app->settings->get('maintenanceMode')) {
            Yii::$app->session->setFlash('error', ControllerAccess::getMaintenanceModeWarningText());
        }

        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('login_modal', $loginParams);
        }

        return $this->render('login', $loginParams);
    }

    /**
     * Handle successful authentication
     *
     * @param BaseClient $authClient
     * @return Response
     * @throws Throwable
     */
    public function onAuthSuccess(BaseClient $authClient)
    {
        // User already logged in - Add new authclient to existing user
        if (!Yii::$app->user->isGuest) {
            Yii::$app->user->getAuthClientUserService()->add($authClient);
            return $this->redirect(['/user/account/connected-accounts']);
        }

        $authClientService = new AuthClientService($authClient);
        $authClientService->autoMapToExistingUser();

        $user = $authClientService->getUser();

        if (Yii::$app->settings->get('maintenanceMode') && !($user && $user->isSystemAdmin())) {
            Yii::$app->getView()->warn(ControllerAccess::getMaintenanceModeWarningText());
            return $this->redirect(['/user/auth/login']);
        }


        if ($user !== null) {
            return $this->login($user, $authClient);
        }

        return $this->register($authClient);
    }


    /**
     * Try to register (automatic user creation or start the registration process) after successful authentication
     * without found related user account
     *
     * @param BaseClient $authClient
     * @return Response|\yii\console\Response|\yii\web\Response
     * @throws HttpException
     * @throws Exception
     */
    private function register(BaseClient $authClient)
    {
        $attributes = $authClient->getUserAttributes();

        // Check if E-Mail is given by the AuthClient
        if (!isset($attributes['email']) && $this->module->emailRequired) {
            Yii::warning('Could not register user automatically: AuthClient ' . get_class($authClient) . ' provided no E-Mail attribute.', 'user');
            Yii::$app->session->setFlash('error', Yii::t('UserModule.base', 'Missing E-Mail Attribute from AuthClient.'));
            return $this->redirect(['/user/auth/login']);
        }

        // Check that AuthClient provides an ID for the user (mandatory)
        if (!isset($attributes['id'])) {
            Yii::warning('Could not register user automatically: AuthClient ' . get_class($authClient) . ' provided no ID attribute.', 'user');
            Yii::$app->session->setFlash('error', Yii::t('UserModule.base', 'Missing ID AuthClient Attribute from AuthClient.'));
            return $this->redirect(['/user/auth/login']);
        }

        $authClientService = new AuthClientService($authClient);
        $inviteRegistrationService = InviteRegistrationService::createFromRequestOrEmail($attributes['email'] ?? null);
        $linkRegistrationService = LinkRegistrationService::createFromRequest();

        if (
            !$inviteRegistrationService->isValid()
            && !$linkRegistrationService->isValid()
            && (!$authClientService->allowSelfRegistration() && !in_array($authClient->id, $this->module->allowUserRegistrationFromAuthClientIds))
        ) {
            Yii::warning('Could not register user automatically: Anonymous registration disabled. AuthClient: ' . get_class($authClient), 'user');
            Yii::$app->session->setFlash('error', Yii::t('UserModule.base', 'You\'re not registered.'));
            return $this->redirect(['/user/auth/login']);
        }

        if (!empty($attributes['email']) && $linkRegistrationService->isValid()) {
            $linkRegistrationService->convertToInvite($attributes['email']);
        }

        // Try automatic user creation
        $user = $authClientService->createUser();
        if ($user !== null) {
            return $this->login($user, $authClient);
        }

        // Start Registration
        return $this->redirectToRegistration($authClient);
    }


    private function redirectToRegistration(BaseClient $authClient)
    {
        if ($authClient instanceof \humhub\modules\user\authclient\BaseClient) {
            /** @var \humhub\modules\user\authclient\BaseClient $authClient */
            $authClient->beforeSerialize();
        }

        // Store authclient in session - for registration controller
        Yii::$app->session->set('authClient', $authClient);

        return $this->redirect(['/user/registration']);
    }


    /**
     * Do log in user
     *
     * @param User $user
     * @param BaseClient $authClient
     * @param array $redirectUrl
     * @return array
     */
    private function doLogin($user, $authClient, $redirectUrl)
    {
        $duration = 0;

        if (
            ($authClient instanceof BaseFormAuth && $authClient->login->rememberMe)
            || !empty(Yii::$app->session->get('loginRememberMe'))
        ) {
            $duration = Yii::$app->getModule('user')->loginRememberMeDuration;
        }

        (new AuthClientService($authClient))->updateUser($user);

        if ($success = Yii::$app->user->login($user, $duration)) {
            Yii::$app->user->setCurrentAuthClient($authClient);
            $redirectUrl = Yii::$app->user->returnUrl;
        }

        return [$success, $redirectUrl];
    }

    /**
     * Login user
     *
     * @param User $user
     * @param BaseClient $authClient
     * @return Response the current response object
     */
    protected function login($user, $authClient)
    {
        $redirectUrl = ['/user/auth/login'];
        $success = false;
        $this->trigger(static::EVENT_BEFORE_CHECKING_USER_STATUS, new UserEvent(['user' => $user]));

        if ($user->status == User::STATUS_ENABLED) {
            [$success, $redirectUrl] = $this->doLogin($user, $authClient, $redirectUrl);
        } elseif ($user->status == User::STATUS_DISABLED) {
            Yii::$app->session->setFlash('error', Yii::t('UserModule.base', 'Your account is disabled!'));
        } elseif ($user->status == User::STATUS_NEED_APPROVAL) {
            Yii::$app->session->setFlash('error', Yii::t('UserModule.base', 'Your account is not approved yet!'));
        } else {
            Yii::$app->session->setFlash('error', Yii::t('UserModule.base', 'Unknown user status!'));
        }

        if ($success) {
            // Add space invite
            $linkRegistrationService = LinkRegistrationService::createFromRequest();
            if (
                $linkRegistrationService->isValid()
                && $linkRegistrationService->inviteToSpace(Yii::$app->user->identity)
            ) {
                $redirectUrl = $linkRegistrationService->getSpace()->getUrl();
            }
        }

        // NOTE: The method `htmlRedirect` renders `Html::nonce()`, so it must be run before
        //       a resetting of nonce on the event `humhub\modules\web\Events\onAfterLogin`
        $result = Yii::$app->request->getIsAjax()
            ? $this->htmlRedirect($redirectUrl)
            : $this->redirect($redirectUrl);

        if ($success) {
            $this->trigger(static::EVENT_AFTER_LOGIN, new UserEvent(['user' => Yii::$app->user->identity]));
            if (method_exists($authClient, 'onSuccessLogin')) {
                $authClient->onSuccessLogin();
            }
        }

        return $result;
    }

    /**
     * Logouts a User
     * @throws HttpException
     */
    public function actionLogout()
    {
        $this->forcePostRequest();

        $language = Yii::$app->user->language;

        Yii::$app->user->logout();

        // Store users language in session
        if ($language !== '') {
            $cookie = new Cookie([
                'name' => 'language',
                'value' => $language,
                'expire' => time() + 86400 * 365,
            ]);
            Yii::$app->getResponse()->getCookies()->add($cookie);
        }

        return $this->redirect(($this->module->logoutUrl) ? $this->module->logoutUrl : Yii::$app->homeUrl);
    }

    /**
     * Allows third party applications
     * to convert a valid sessionId
     * into a username.
     */
    public function actionGetSessionUserJson()
    {
        Yii::$app->response->format = 'json';

        $sessionId = Yii::$app->request->get('sessionId');

        $output = [];
        $output['valid'] = false;
        $httpSession = Session::findOne(['id' => $sessionId]);
        if ($httpSession != null && $httpSession->user != null) {
            $output['valid'] = true;
            $output['userName'] = $httpSession->user->username;
            $output['fullName'] = $httpSession->user->displayName;
            $output['email'] = $httpSession->user->email;
            $output['superadmin'] = $httpSession->user->isSystemAdmin();
        }

        return $output;
    }

    /**
     * Sign in back to admin User who impersonated the current User
     *
     * @return \yii\console\Response|\yii\web\Response
     * @throws HttpException
     */
    public function actionStopImpersonation()
    {
        $this->forcePostRequest();

        if (Yii::$app->user->restoreImpersonator()) {
            return $this->redirect(['/admin/user/list']);
        }

        return $this->goBack();
    }
}
