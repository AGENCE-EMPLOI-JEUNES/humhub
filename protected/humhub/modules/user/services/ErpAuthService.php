<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2025 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\user\services;

use humhub\modules\user\models\User;
use Yii;
use yii\base\Security;

/**
 * ErpAuthService handles SSO token generation and validation for ERP system
 * 
 * This service enables Two-Way SSO: users can navigate from HumHub to ERP
 * with secure token-based authentication.
 * 
 * @since 1.0
 */
class ErpAuthService
{
    /**
     * Token expiration time in seconds (5 minutes)
     */
    protected const TOKEN_EXPIRATION = 300;

    /**
     * Generate authentication URL for redirecting user to ERP
     * Creates a secure token instead of exposing email in URL
     *
     * @param User $user
     * @return string
     */
    public function generateAuthUrl(User $user): string
    {
        // Generate a secure random token (64 characters)
        $security = new Security();
        $token = $security->generateRandomString(64);

        // Store token with user data in cache for 5 minutes
        $cacheKey = "erp_sso_token:{$token}";
        $tokenData = [
            'email' => $user->email,
            'user_id' => $user->id,
            'username' => $user->username,
            'displayName' => $user->displayName,
            'created_at' => time(),
        ];

        Yii::$app->cache->set($cacheKey, $tokenData, self::TOKEN_EXPIRATION);

        Yii::info("ERP SSO: Token generated for user - ID: {$user->id}, Email: {$user->email}, Token: " . substr($token, 0, 10) . '...');

        // Get ERP base URL from config
        $erpBaseUrl = Yii::$app->params['erpBaseUrl'] ?? 'http://localhost:8000';

        // Return URL with secure token
        return $erpBaseUrl . '/auth_user?humhub_token=' . $token;
    }

    /**
     * Validate SSO token and return user data
     * Called by ERP to verify the token
     *
     * @param string $token
     * @return array|null User data if token is valid, null otherwise
     */
    public function validateSsoToken(string $token): ?array
    {
        $cacheKey = "erp_sso_token:{$token}";

        // Retrieve token data from cache
        $tokenData = Yii::$app->cache->get($cacheKey);

        if (!$tokenData) {
            Yii::warning("ERP SSO: Invalid or expired token - Token: " . substr($token, 0, 10) . '...');
            return null;
        }

        // Delete token after use (single-use token)
        Yii::$app->cache->delete($cacheKey);

        Yii::info("ERP SSO: Token validated and consumed - Email: {$tokenData['email']}, Token: " . substr($token, 0, 10) . '...');

        return $tokenData;
    }
}

