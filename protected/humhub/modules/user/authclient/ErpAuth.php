<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2016 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\user\authclient;

use humhub\modules\user\models\User;

/**
 * ERP authentication client for single sign-on from ERP system
 *
 * This authentication client allows users to authenticate to HumHub
 * directly from the ERP system without entering credentials.
 *
 * @since 1.0
 */
class ErpAuth extends BaseClient implements interfaces\PrimaryClient
{
    /**
     * @inheritdoc
     */
    public function getId()
    {
        return 'erp';
    }

    /**
     * @inheritdoc
     */
    protected function defaultName()
    {
        return 'ERP SSO';
    }

    /**
     * @inheritdoc
     */
    protected function defaultTitle()
    {
        return 'ERP System';
    }

    /**
     * Authenticate user by email from ERP system
     * 
     * @param string $email User's email address
     * @return bool Authentication result
     */
    public function authByEmail($email)
    {
        $user = User::findOne(['email' => $email]);

        if ($user !== null) {
            $this->setUserAttributes(['id' => $user->id, 'email' => $email]);
            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function auth()
    {
        // This method is not used for ERP authentication
        // Authentication is handled by authByEmail() method
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getUser()
    {
        $attributes = $this->getUserAttributes();

        if (isset($attributes['id'])) {
            return User::findOne(['id' => $attributes['id']]);
        }

        if (isset($attributes['email'])) {
            return User::findOne(['email' => $attributes['email']]);
        }

        return null;
    }
}

