# HumHub ERP Integration Guide

## Overview

This guide explains how to set up and use the ERP authentication integration in HumHub. This integration allows users to seamlessly login from the ERP system (erpaej) to HumHub without re-entering credentials.

## Files Added

1. **ErpAuth.php** (`protected/humhub/modules/user/authclient/ErpAuth.php`)
   - Custom authentication client for ERP SSO
   - Implements authentication by email

2. **ErpAuthController.php** (`protected/humhub/modules/user/controllers/ErpAuthController.php`)
   - Handles incoming authentication requests from ERP
   - Provides API endpoints for authentication

3. **config.php** (Updated: `protected/humhub/modules/user/config.php`)
   - Added URL rules for ERP authentication endpoints

## Installation

### Step 1: Copy Files

Copy the following files to your HumHub installation:

```bash
# Copy ErpAuth authentication client
cp ErpAuth.php protected/humhub/modules/user/authclient/

# Copy ErpAuthController
cp ErpAuthController.php protected/humhub/modules/user/controllers/
```

### Step 2: Update Configuration

The `config.php` file has been updated with the following URL rules:

```php
'urlManagerRules' => [
    // ... existing rules ...
    'auth_user/<user_email>' => 'user/erp-auth/auth-user',
    'api/auth/login' => 'user/erp-auth/api-login',
],
```

### Step 3: Clear Cache

After copying files, clear HumHub cache:

```bash
cd /path/to/humhub
php yii cache/flush-all
```

## Usage

### Authentication Endpoints

#### 1. Web Authentication Endpoint

**URL:** `https://your-humhub.com/auth_user/{email}`

**Method:** GET

**Description:** Authenticates a user from the ERP system and redirects to dashboard

**Example:**
```
https://humhub.agenceemploijeunes.ci/auth_user/john.doe@example.com
```

**Response:**
- Success: Redirects to `/dashboard/dashboard`
- Failure: Redirects to `/user/auth/login` with error message

#### 2. API Authentication Endpoint

**URL:** `https://your-humhub.com/api/auth/login`

**Method:** POST

**Content-Type:** application/json

**Request Body:**
```json
{
    "email": "user@example.com"
}
```

**Response:**
```json
{
    "status": true,
    "message": "Authentication successful",
    "auth_url": "https://humhub.agenceemploijeunes.ci/auth_user/user@example.com",
    "user": {
        "id": 123,
        "email": "user@example.com",
        "username": "johndoe",
        "displayName": "John Doe"
    }
}
```

## How It Works

### Authentication Flow

1. **User Login in ERP:**
   - User logs into ERP system (erpaej)
   - User sees HumHub in the platform menu

2. **Click HumHub Platform:**
   - Menu generates URL: `https://humhub.site/auth_user/{user_email}`
   - Opens in new tab/window

3. **HumHub Receives Request:**
   - `ErpAuthController::actionAuthUser()` is triggered
   - Validates email format
   - Finds user by email
   - Checks if user is enabled

4. **Authentication:**
   - Creates `ErpAuth` client instance
   - Authenticates user by email
   - Logs user into HumHub session

5. **Redirect:**
   - User is redirected to HumHub dashboard
   - Session is established
   - User can navigate HumHub normally

## Security Features

### Email Validation
- All emails are validated using PHP's `filter_var()`
- Invalid emails are rejected immediately

### User Status Check
- Only users with `status = User::STATUS_ENABLED` can authenticate
- Disabled or unapproved users are rejected

### Session Tracking
- ERP logins are tracked in session:
  - `erp_login = true`
  - `erp_login_email = user@example.com`

### Error Logging
- All authentication attempts are logged
- Failed attempts include reason (user not found, disabled, etc.)

## Testing

### Manual Testing

1. **Test Web Authentication:**
   ```
   Navigate to: https://your-humhub.com/auth_user/test@example.com
   Expected: Redirect to dashboard if user exists and is enabled
   ```

2. **Test API Endpoint:**
   ```bash
   curl -X POST https://your-humhub.com/api/auth/login \
        -H "Content-Type: application/json" \
        -d '{"email":"test@example.com"}'
   ```

3. **Test from ERP:**
   - Login to ERP system
   - Click HumHub platform in menu
   - Verify automatic login to HumHub

### Testing Scenarios

| Scenario | Expected Result |
|----------|----------------|
| Valid user, enabled | Successful login, redirect to dashboard |
| Valid user, disabled | Redirect to login with error message |
| Invalid email format | Redirect to login with error message |
| User not found | Redirect to login with error message |
| Empty email | Redirect to login with error message |

## Troubleshooting

### Common Issues

#### 1. "User not found" Error

**Cause:** User doesn't exist in HumHub database

**Solution:**
- Verify user exists in HumHub
- Check email matches exactly
- Ensure user was created/synced from ERP

#### 2. "Your account is not enabled" Error

**Cause:** User status is not `STATUS_ENABLED`

**Solution:**
- Check user status in database: `SELECT status FROM user WHERE email = 'user@example.com'`
- Enable user: `UPDATE user SET status = 1 WHERE email = 'user@example.com'`
- Or enable via admin panel

#### 3. "Authentication failed" Error

**Cause:** Authentication logic failed

**Solution:**
- Check HumHub logs: `protected/runtime/logs/app.log`
- Verify `ErpAuth` class is loaded correctly
- Clear cache: `php yii cache/flush-all`

#### 4. 404 Error on `/auth_user/email`

**Cause:** URL rules not loaded

**Solution:**
- Verify `config.php` has been updated
- Clear cache
- Check `.htaccess` or nginx config for URL rewriting

#### 5. Session Not Persisting

**Cause:** Session configuration issue

**Solution:**
- Check session configuration in HumHub
- Verify cookies are being set
- Check browser cookie settings

### Debug Mode

Enable debug mode in HumHub to see detailed error messages:

1. Edit `protected/config/common.php`:
   ```php
   return [
       'params' => [
           'enableDebugMode' => true,
       ],
   ];
   ```

2. Check logs in `protected/runtime/logs/`

## Logs

### Log Entries

Successful authentication:
```
2025-12-02 10:30:00 [info] ERP Auth: User logged in successfully - user@example.com
```

Failed authentication - User not found:
```
2025-12-02 10:30:00 [warning] ERP Auth: User not found - user@example.com
```

Failed authentication - User disabled:
```
2025-12-02 10:30:00 [warning] ERP Auth: User not enabled - user@example.com
```

Error:
```
2025-12-02 10:30:00 [error] ERP Auth Error: {error message}
```

## Configuration Options

### Disable CSRF Validation (Already Set)

The `ErpAuthController` disables CSRF validation for external requests:
```php
public $enableCsrfValidation = false;
```

### Custom Redirect URL

To change redirect URL after login, modify `ErpAuthController::actionAuthUser()`:

```php
// Current
return $this->redirect(['/dashboard/dashboard']);

// Custom
return $this->redirect(['/space/space', 'sguid' => 'custom-space']);
```

### Session Configuration

To customize session data stored during ERP login:

```php
// In actionAuthUser() method
Yii::$app->session->set('erp_login', true);
Yii::$app->session->set('erp_login_email', $user_email);
Yii::$app->session->set('custom_key', 'custom_value'); // Add custom data
```

## API Integration

### From External Systems

External systems can use the API endpoint to authenticate users:

```javascript
// JavaScript example
async function authenticateToHumHub(email) {
    const response = await fetch('https://humhub.site/api/auth/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ email: email })
    });
    
    const data = await response.json();
    
    if (data.status) {
        // Redirect user to auth URL
        window.open(data.auth_url, '_blank');
    } else {
        console.error('Authentication failed:', data.message);
    }
}
```

### From PHP

```php
// PHP example
$ch = curl_init('https://humhub.site/api/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => 'user@example.com']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$data = json_decode($response, true);

if ($data['status']) {
    header('Location: ' . $data['auth_url']);
    exit;
}
```

## Maintenance

### Regular Tasks

1. **Monitor Logs:**
   - Check for failed authentication attempts
   - Review error logs weekly

2. **User Synchronization:**
   - Ensure users in ERP exist in HumHub
   - Sync user data periodically

3. **Security Audit:**
   - Review authentication logs
   - Check for suspicious activity

## Future Enhancements

Possible improvements:

1. **Token-based Authentication:**
   - Implement JWT tokens for API
   - Add token expiration

2. **Single Sign-Out:**
   - Logout from HumHub when logging out of ERP

3. **User Synchronization:**
   - Automatic user creation from ERP
   - Sync profile updates

4. **Role Mapping:**
   - Map ERP roles to HumHub groups
   - Sync permissions

## Support

For technical support:
- Documentation: This file
- Logs: `protected/runtime/logs/app.log`
- HumHub Community: https://community.humhub.com/

## Version

- **Version:** 1.0.0
- **Date:** December 2, 2025
- **Compatibility:** HumHub 1.x+

