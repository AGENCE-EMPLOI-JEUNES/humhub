<?php
/**
 * This file provides to overwrite the default HumHub / Yii configuration by your local Web environments
 * @see http://www.yiiframework.com/doc-2.0/guide-concept-configurations.html
 * @see https://docs.humhub.org/docs/admin/advanced-configuration
 */
return [
    'params' => [
        // ERP SSO Configuration
        'erpApiUrl' => $_ENV['ERP_API_URL'] ?? 'http://localhost:8000/api',
        'erpBaseUrl' => $_ENV['ERP_BASE_URL'] ?? 'http://localhost:8000',

        // Hide "Powered by HumHub" footer
        'hidePoweredBy' => true,
    ],
];
