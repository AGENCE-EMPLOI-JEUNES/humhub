# ERP to HumHub SSO Integration

## Overview
This document describes the Single Sign-On (SSO) integration between the ERP system (erpaej) and HumHub social network platform.

## How It Works

### User Flow
1. User logs into the ERP system (erpaej)
2. User clicks on the HumHub module in the ERP menu
3. ERP generates a **secure, single-use token** and stores it in cache
4. User is redirected to HumHub with the token (email is NOT visible in URL)
5. HumHub validates the token by calling ERP's API
6. ERP verifies the token and returns user data
7. HumHub logs the user in automatically
8. User is redirected to the HumHub dashboard

### Authentication Process
- **Token-based**: Uses secure, randomly-generated 64-character tokens
- **Single-use**: Each token can only be used once and is deleted after validation
- **Time-limited**: Tokens expire after 5 minutes
- **No password required**: SSO authentication, no credentials needed
- **Secure**: Email is never exposed in URLs
- **API-verified**: HumHub validates tokens with ERP API before authenticating

## Implementation Details

### ERP Side (erpaej/Laravel)

#### Files Modified

1. **`app/Services/HumHubAuthService.php`**
   - `generateAuthUrl()` method generates secure SSO URL with token
   - Creates 64-character random token using `Str::random(64)`
   - Stores token in cache with user data for 5 minutes
   - URL format: `{humhub_base_url}/user/auth/login?erp_token={secure_token}`
   - `validateSsoToken()` method validates tokens and returns user data (single-use)

2. **`resources/views/menu.blade.php`**
   - HumHub platform integrated in the menu
   - Uses `HumHubAuthService` to generate the SSO URL
   - URL opens in a new tab/window

3. **`app/Http/Controllers/Erp/AuthErpController.php`**
   - `validateHumHubToken()` method - API endpoint for token validation
   - Called by HumHub to verify tokens
   - Returns user data if token is valid

4. **`routes/api.php`**
   - Added route: `POST /api/humhub/validate-token`
   - No authentication required (token validated internally)

5. **`config/siga.php`**
   ```php
   'urls' => [
       'humhub' => env('APP_URL_HUMHUB', 'https://humhub.agenceemploijeunes.ci'),
   ]
   ```

4. **`database/seeders/PlateformeSeeder.php`**
   - Added HumHub platform entry to the database
   ```php
   [
       'name' => 'HUMHUB',
       'slug' => 'humhub',
       'icon' => 'fas fa-users',
       'description' => 'Plateforme de réseau social interne',
   ]
   ```

### HumHub Side (Yii2)

#### Files Modified

1. **`protected/humhub/modules/user/controllers/AuthController.php`**
   - Added ERP SSO logic at the beginning of `actionLogin()`
   - Checks for `erp_token` GET parameter
   - If present, validates token via ERP API
   - Retrieves user email from API response
   - Authenticates user without password
   - On success, redirects to dashboard
   - On failure, shows error message

   ```php
   // ERP SSO AUTHENTICATION
   $erp_token = Yii::$app->request->get('erp_token');
   if ($erp_token) {
       // Call ERP API to validate token
       $erpApiUrl = Yii::$app->params['erpApiUrl'];
       $response = curl_post($erpApiUrl . '/humhub/validate-token', [
           'token' => $erp_token
       ]);
       
       if ($response['status'] && isset($response['user']['email'])) {
           $email = $response['user']['email'];
           $user = User::findOne(['email' => $email]);
           if ($user && $user->status == User::STATUS_ENABLED) {
               Yii::$app->user->login($user, 0);
               // ... redirect to dashboard
           }
       }
   }
   ```

2. **`protected/config/web.php`**
   - Added ERP API URL and base URL configuration
   ```php
   'params' => [
       'erpApiUrl' => getenv('ERP_API_URL') ?: 'http://localhost:8000/api',
       'erpBaseUrl' => getenv('ERP_BASE_URL') ?: 'http://localhost:8000',
   ]
   ```

3. **`protected/humhub/modules/user/services/ErpAuthService.php`** (NEW - Two-Way SSO)
   - `generateAuthUrl()` method generates secure SSO URL with token for ERP
   - Creates 64-character random token using `Security::generateRandomString(64)`
   - Stores token in cache with user data for 5 minutes
   - URL format: `{erp_base_url}/auth_user?humhub_token={secure_token}`
   - `validateSsoToken()` method validates tokens and returns user data (single-use)

4. **`protected/humhub/modules/user/controllers/ErpAuthController.php`**
   - Added `actionValidateToken()` method - API endpoint for token validation
   - Called by ERP to verify tokens from HumHub
   - Returns user data if token is valid

5. **`protected/humhub/modules/user/config.php`**
   - Added route: `api/erp/validate-token` → `user/erp-auth/validate-token`

6. **`protected/humhub/modules/user/Events.php`** (UI Component)
   - Added ERP SSO menu item to top navigation
   - Shows "SIGA-AEJ" link in the main navigation bar (between SPACES and user profile)
   - Uses ErpAuthService to generate secure SSO URL
   - Opens in new tab with `target="_blank"`
   - Automatically visible to all logged-in users when `erpBaseUrl` is configured

7. **`protected/humhub/modules/user/controllers/AuthController.php`** (Logout Redirect)
   - Modified `actionLogout()` to redirect to ERP after logout
   - When `erpBaseUrl` is configured, users are redirected to ERP instead of HumHub home
   - Provides seamless integration - users return to ERP after logging out

## Security Features

### Implemented Security Measures
- ✅ **Secure Token Generation**: 64-character random tokens using cryptographically secure methods
- ✅ **Single-Use Tokens**: Each token is deleted after validation, preventing replay attacks
- ✅ **Time-Limited Tokens**: Tokens expire after 5 minutes (300 seconds)
- ✅ **No Email in URL**: Email addresses are never exposed in URLs
- ✅ **API Validation**: HumHub validates tokens with ERP API before authentication
- ✅ **User Status Check**: Only enabled users can authenticate
- ✅ **Session Tracking**: Sessions are marked with `erp_login` flag for audit
- ✅ **Comprehensive Logging**: All SSO attempts are logged in both systems
- ✅ **Error Handling**: Graceful handling of expired, invalid, or missing tokens

### Additional Recommendations for Production

1. **IP Whitelisting** (Optional)
   - Restrict ERP SSO API endpoints to HumHub server IPs
   - Configure in web server or application firewall

2. **SSL/TLS** (Required)
   - Always use HTTPS for SSO URLs and API calls
   - Prevents token interception

3. **Rate Limiting** (Recommended)
   - Limit SSO authentication attempts per IP
   - Prevent brute force attacks on token validation endpoint

4. **Token Storage** (Current: Cache)
   - Current implementation uses Laravel cache
   - For production, ensure cache is persistent (Redis/Memcached)
   - Do not use file-based cache in load-balanced environments

## Testing

### Test SSO Token Generation

1. **Test in ERP**
   ```bash
   cd /path/to/erpaej
   php test_secure_sso.php
   ```
   Expected output:
   ```
   ✅ Token generated successfully!
   ✅ Token validation successful!
   ✅ Token cannot be reused (correct!)
   ✅ Email is NOT visible in URL (secure!)
   ```

2. **Manual API Test**
   ```bash
   # Generate a token by visiting ERP and clicking HumHub
   # Extract the token from URL, then test validation:
   curl -X POST http://erp.test/api/humhub/validate-token \
     -H "Content-Type: application/json" \
     -d '{"token":"YOUR_TOKEN_HERE"}'
   ```
   Expected response:
   ```json
   {
     "status": true,
     "user": {
       "id": 1,
       "email": "user@example.com",
       "name": "User Name"
     }
   }
   ```

3. **End-to-End Test**
   - Log into ERP
   - Click HumHub module
   - Verify URL contains `erp_token=` (NOT `erp_email=`)
   - Should be logged into HumHub automatically

2. **From ERP**
   - Log into ERP
   - Click HumHub module in menu
   - Should be automatically logged into HumHub

### Verify User in HumHub

```bash
# Check if user exists and is enabled
cd /path/to/humhub
php protected/yii

# In Yii console
$user = \humhub\modules\user\models\User::findOne(['email' => 'test@example.com']);
echo "Status: " . $user->status . "\n"; // Should be 1 (enabled)
```

## Troubleshooting

### User Not Found Error
- **Cause**: User doesn't exist in HumHub database
- **Solution**: Create user in HumHub or sync users from ERP

### Account Not Enabled
- **Cause**: User status is not `STATUS_ENABLED` (1)
- **Solution**: Enable user in HumHub admin panel

### Redirect to Login Page
- **Cause**: Authentication failed for some reason
- **Solution**: Check HumHub logs at `protected/runtime/logs/app.log` for "ERP SSO" entries

### Check Logs
```bash
# Watch HumHub logs
cd /path/to/humhub
tail -f protected/runtime/logs/app.log | grep "ERP SSO"
```

## Two-Way SSO Implementation

### Overview
Two-Way SSO allows users to navigate seamlessly between ERP and HumHub in both directions:
- **ERP → HumHub**: Already implemented (see main documentation)
- **HumHub → ERP**: ✅ **NEWLY IMPLEMENTED**

### User Flow (HumHub → ERP)
1. User is logged into HumHub
2. User clicks on ERP link/widget (can be added to HumHub UI)
3. HumHub generates a **secure, single-use token** and stores it in cache
4. User is redirected to ERP with the token (email is NOT visible in URL)
5. ERP validates the token by calling HumHub's API
6. HumHub verifies the token and returns user data
7. ERP logs the user in automatically
8. User is redirected to the ERP dashboard

### Logout Behavior
- When user logs out of HumHub, they are automatically redirected to ERP
- This provides a seamless user experience
- Users can then log back into ERP or HumHub as needed

### Implementation Details

#### HumHub Side

**ErpAuthService** (`protected/humhub/modules/user/services/ErpAuthService.php`):
```php
$erpAuthService = new \humhub\modules\user\services\ErpAuthService();
$authUrl = $erpAuthService->generateAuthUrl($user);
// Returns: https://erp.example.com/auth_user?humhub_token=ABC123...
```

**API Endpoint** (`/api/erp/validate-token`):
- Validates tokens from ERP
- Returns user data if token is valid
- Deletes token after validation (single-use)

#### ERP Side

**AuthErpController** (`app/Http/Controllers/Erp/AuthErpController.php`):
- Updated `auth_user()` method to handle `humhub_token` parameter
- Validates token via HumHub API before authenticating
- Falls back to legacy email-based auth if no token provided

**URL Format**:
```
https://erp.example.com/auth_user?humhub_token={secure_token}
```

### Security Features (Same as ERP → HumHub)
- ✅ **Secure Token Generation**: 64-character random tokens
- ✅ **Single-Use Tokens**: Each token deleted after validation
- ✅ **Time-Limited**: Tokens expire after 5 minutes
- ✅ **No Email in URL**: Email addresses never exposed
- ✅ **API Validation**: ERP validates tokens with HumHub API
- ✅ **User Status Check**: Only enabled users can authenticate

### UI Component

**Top Navigation Menu Item**:
- A "SIGA-AEJ" link is automatically added to the HumHub top navigation bar
- Appears after Dashboard, People, and Spaces menu items
- Clicking the link generates a secure SSO token and redirects to ERP
- Opens in a new tab
- Visible to all logged-in users when `erpBaseUrl` is configured

**Location**: You'll see it in the main navigation:
```
MY SPACES | DASHBOARD | PEOPLE | SPACES | SIGA-AEJ
```

### Usage Example

**Automatic (Recommended)**:
The ERP link is automatically added to the top navigation when configured. No code changes needed!

**Manual Implementation (Optional)**:
If you want to add ERP links elsewhere in HumHub:
```php
use humhub\modules\user\services\ErpAuthService;

// Get current user
$user = Yii::$app->user->identity;

// Generate ERP SSO URL
$erpAuthService = new ErpAuthService();
$erpUrl = $erpAuthService->generateAuthUrl($user);

// Use in view/widget
echo '<a href="' . $erpUrl . '" target="_blank">Go to ERP</a>';
```

### Configuration

**HumHub** (`protected/config/web.php` or environment variable):
```php
'params' => [
    'erpBaseUrl' => 'https://erp.agenceemploijeunes.ci',
]
```

Or set environment variable:
```env
ERP_BASE_URL=https://erp.agenceemploijeunes.ci
```

**ERP** (`config/siga.php`):
```php
'urls' => [
    'humhub' => 'https://humhub.agenceemploijeunes.ci',
]
```

### Testing Two-Way SSO

1. **Test Token Generation**:
   ```php
   // In HumHub
   $user = User::findOne(['email' => 'test@example.com']);
   $service = new ErpAuthService();
   $url = $service->generateAuthUrl($user);
   echo $url; // Should contain humhub_token parameter
   ```

2. **Test Token Validation**:
   ```bash
   # Extract token from URL, then test validation:
   curl -X POST https://humhub.test/api/erp/validate-token \
     -H "Content-Type: application/json" \
     -d '{"token":"YOUR_TOKEN_HERE"}'
   ```

3. **End-to-End Test**:
   - Log into HumHub
   - Generate ERP SSO URL using ErpAuthService
   - Click the link
   - Verify URL contains `humhub_token=` (NOT email)
   - Should be logged into ERP automatically

## Future Enhancements

1. **Automatic User Provisioning**
   - Create HumHub users automatically when they don't exist
   - Sync user profile data from ERP to HumHub

3. **Token-Based Security**
   - Implement JWT or signed tokens for secure SSO

4. **User Attribute Mapping**
   - Sync user roles, departments, and other attributes
   - Apply permissions based on ERP roles

5. **Logout Synchronization**
   - When user logs out of ERP, also log them out of HumHub
   - Implement global logout across all integrated systems

## Configuration

### Environment Variables

**ERP (.env):**
```env
# HumHub Configuration
APP_URL_HUMHUB=https://humhub.agenceemploijeunes.ci

# Cache Configuration (Required for SSO tokens)
CACHE_DRIVER=redis  # or memcached (recommended for production)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**HumHub (environment variable or config):**
```env
# ERP API URL for token validation (ERP → HumHub)
ERP_API_URL=https://erp.agenceemploijeunes.ci/api

# ERP Base URL for SSO links (HumHub → ERP)
ERP_BASE_URL=https://erp.agenceemploijeunes.ci
```

Or in `protected/config/web.php`:
```php
'params' => [
    'erpApiUrl' => 'https://erp.agenceemploijeunes.ci/api',
    'erpBaseUrl' => 'https://erp.agenceemploijeunes.ci',
]
```

### Requirements

**ERP:**
- Laravel Cache configured (Redis/Memcached recommended)
- HumHub URL configured in `config/siga.php`
- API endpoint accessible from HumHub server

**HumHub:**
- User must exist with same email as in ERP
- User status must be "Enabled" (status = 1)
- cURL extension enabled in PHP
- ERP API URL configured (for ERP → HumHub)
- ERP Base URL configured (for HumHub → ERP)
- Network access to ERP API endpoint
- Cache configured (for token storage)

## Support

For issues or questions:
1. Check logs in both ERP and HumHub
2. Verify user exists and is enabled in HumHub
3. Test SSO URL manually with curl
4. Check network connectivity between ERP and HumHub servers

---

**Implementation Date**: December 2, 2025
**Last Updated**: December 3, 2025
**Status**: ✅ Working (One-Way: ERP → HumHub)
**Two-Way SSO**: ✅ Implemented with UI (December 3, 2025)

