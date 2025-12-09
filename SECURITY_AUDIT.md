# Security Audit Report: HumHub

**Date:** December 4, 2025  
**Auditor:** AI Security Agent  
**Scope:** Full whitebox security audit of HumHub codebase (custom fork with ERP integration)

---

## 1. Executive Summary

- **Overall Risk Level:** **CRITICAL**
- **Deployment Verdict:** **NO-GO** 🛑
- **Summary:** This codebase contains **critical authentication bypass vulnerabilities** introduced via custom ERP integration that allow any unauthenticated attacker to log in as any registered user simply by knowing their email address. Additionally, multiple high-severity vulnerabilities exist including XSS, command injection risks, and insecure deserialization patterns. The application **MUST NOT** be deployed in an enterprise environment until all critical and high-severity issues are remediated.

---

## 2. Architecture Overview

### 2.1 Technology Stack

| Component | Technology |
|-----------|------------|
| **Language** | PHP 8.1+ |
| **Framework** | Yii2 (dev-master branch) |
| **Database** | MySQL/MariaDB (InnoDB) |
| **Frontend** | jQuery 3.6.3, Bootstrap, Custom JS |
| **Authentication** | Yii2 AuthClient + Custom ERP SSO |
| **Session** | Database-backed (Yii2 Session) |
| **Caching** | File/APC/Redis supported |
| **Dependencies** | Composer with NPM assets |

### 2.2 Entry Points & Attack Surface

| Entry Point | Path/Route | Description |
|-------------|------------|-------------|
| **Web Interface** | `index.php` | Main application entry |
| **ERP Auth Endpoint** | `/auth_user/<email>` | **CRITICAL** - Unauthenticated SSO |
| **ERP API Login** | `/api/auth/login` | **CRITICAL** - API authentication |
| **ERP Token Validation** | `/api/erp/validate-token` | Token validation endpoint |
| **Session Info API** | `/user/auth/get-session-user-json` | Session enumeration risk |
| **OEmbed Controller** | `/oembed/*` | External URL fetching (SSRF surface) |
| **File Upload** | Multiple upload actions | File handling |
| **CLI Commands** | `protected/yii` | Console commands |
| **Live Polling** | `/live/poll/index` | WebSocket fallback |

### 2.3 Authentication Flow

The application supports multiple authentication methods:
1. **Standard Login** - Username/password via `AuthController::actionLogin()`
2. **OAuth/Social** - Via Yii2 AuthClient
3. **LDAP** - Laminas LDAP integration
4. **ERP SSO** - **VULNERABLE** Custom implementation

---

## 3. Findings & Vulnerabilities

| Severity | Category | File Path | Description | Remediation |
|----------|----------|-----------|-------------|-------------|
| **CRITICAL** | Authentication Bypass | `protected/humhub/modules/user/controllers/ErpAuthController.php` | ERP authentication allows login via email only without any password, token, or signature verification. CSRF disabled. | Remove or completely rewrite with proper cryptographic token validation |
| **CRITICAL** | Authentication Bypass | `protected/humhub/modules/user/authclient/ErpAuth.php` | Auth client accepts any email and returns true without verification | Implement proper OAuth2/JWT verification |
| **HIGH** | Information Disclosure | `protected/humhub/modules/user/controllers/AuthController.php:441-458` | `actionGetSessionUserJson` exposes user info (email, admin status) by session ID | Add access control, rate limiting, or remove endpoint |
| **HIGH** | Reflected XSS | `protected/humhub/views/htmlRedirect.php` | URL parameter echoed in JavaScript without escaping | Use `Json::htmlEncode()` or proper JS escaping |
| **HIGH** | Command Injection Risk | `protected/humhub/modules/file/converter/TextConverter.php:94-99` | `shell_exec()` with configuration-defined commands; if config writable = RCE | Validate/sanitize command inputs, use allowlist |
| **HIGH** | Code Execution | `protected/humhub/libs/DynamicConfig.php:50` | Uses `eval()` to parse configuration file | Replace with proper JSON/PHP include |
| **HIGH** | Insecure Deserialization | `protected/humhub/modules/live/controllers/PollController.php:122` | `unserialize()` on database data without class restrictions | Use JSON or add allowed_classes filter |
| **MEDIUM** | File Upload Bypass | `protected/humhub/modules/file/Module.php:61` | `denyDoubleFileExtensions = false` by default | Enable by default; add server config hardening |
| **MEDIUM** | Weak CSP | `protected/config/web.php:62` | CSP allows `unsafe-inline`, `*` sources | Implement strict CSP with nonce-only scripts |
| **MEDIUM** | Debug Logging | `protected/humhub/modules/user/controllers/ErpAuthController.php:72-76` | Writes debug data to file including email addresses | Remove debug code in production |
| **MEDIUM** | SSRF Risk | `protected/humhub/libs/UrlOembedHttpClient.php` | External URL fetching without IP/scheme validation | Add URL validation, block internal IPs |
| **LOW** | Open Redirect | `protected/humhub/modules/user/controllers/AuthController.php:426-429` | Logout redirects to `erpBaseUrl` from config | Validate redirect URL against allowlist |
| **LOW** | Session Fixation Risk | `protected/humhub/modules/user/controllers/AuthController.php:120-153` | ERP SSO does not regenerate session | Add session regeneration after SSO login |

---

## 4. Deep Dive Evidence

### 4.1 CRITICAL: Authentication Bypass via ERP SSO

- **File:** `protected/humhub/modules/user/controllers/ErpAuthController.php`
- **Lines:** 48-142

**Vulnerable Code:**

```php
/**
 * @inheritdoc
 */
public $enableCsrfValidation = false;  // CSRF DISABLED!

/**
 * @inheritdoc
 */
public function behaviors()
{
    return [
        'acl' => [
            'class' => AccessControl::class,
            'guestAllowedActions' => ['auth-user', 'api-login', 'validate-token'],  // GUEST ACCESS!
        ],
    ];
}

/**
 * Authenticate user from ERP system using email
 */
public function actionAuthUser($user_email)
{
    // ... validation of email format ...
    
    // Find user by email
    $user = User::findOne(['email' => $user_email]);
    
    // ... status check ...
    
    // Create ERP auth client
    $erpAuthClient = new ErpAuth();
    
    // Authenticate the user - NO REAL VERIFICATION!
    if ($erpAuthClient->authByEmail($user_email)) {
        // Log the user in - DIRECTLY LOGS IN WITHOUT PASSWORD!
        $loginResult = Yii::$app->user->login($user, 0);
        // ...
    }
}
```

**The ErpAuth client (equally vulnerable):**

```php
// protected/humhub/modules/user/authclient/ErpAuth.php
public function authByEmail($email)
{
    $user = User::findOne(['email' => $email]);
    
    if ($user !== null) {
        $this->setUserAttributes(['id' => $user->id, 'email' => $email]);
        return true;  // ALWAYS RETURNS TRUE IF USER EXISTS!
    }
    
    return false;
}
```

**Exploitation:**
```bash
# Any attacker can login as admin@example.com simply by visiting:
curl "https://vulnerable-site.com/auth_user/admin@example.com"
# User is now logged in as admin!

# Or via the API:
curl -X POST "https://vulnerable-site.com/api/auth/login" \
  -d "email=admin@example.com"
# Returns auth URL that logs in the user
```

**Impact:** Complete authentication bypass. Any attacker knowing a valid email can impersonate any user including system administrators.

---

### 4.2 HIGH: Session Information Disclosure

- **File:** `protected/humhub/modules/user/controllers/AuthController.php`
- **Lines:** 441-458

```php
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
        $output['superadmin'] = $httpSession->user->isSystemAdmin();  // LEAKS ADMIN STATUS!
    }

    return $output;
}
```

**Exploitation:**
```bash
# Enumerate session IDs to find admin sessions
for sid in $(generate_session_ids); do
  result=$(curl -s "https://site.com/user/auth/get-session-user-json?sessionId=$sid")
  if echo "$result" | grep -q '"superadmin":true'; then
    echo "Found admin session: $sid"
  fi
done
```

**Impact:** Session enumeration, PII disclosure, identification of admin accounts.

---

### 4.3 HIGH: Reflected XSS in Redirect Handler

- **File:** `protected/humhub/views/htmlRedirect.php`
- **Lines:** 1-22

```php
<script <?= \humhub\libs\Html::nonce() ?>>
    if (window.location.pathname + window.location.search + window.location.hash == '<?php echo $url; ?>' || '<?php echo $url; ?>' == window.location.href) {
        // $url NOT ESCAPED - XSS!
        window.location.href = '<?php echo $url; ?>';
    } else {
        window.location.href = '<?php echo $url; ?>';
    }
</script>
```

**Exploitation:**
```
https://site.com/redirect?url=';alert(document.cookie);//
```

**Impact:** Session hijacking, credential theft, phishing within trusted domain.

---

### 4.4 HIGH: Command Injection Risk

- **File:** `protected/humhub/modules/file/converter/TextConverter.php`
- **Lines:** 89-101

```php
$converter = $this->getConverter();

if ($converter !== null) {
    $command = str_replace('{fileName}', $this->file->store->get(), $converter['cmd']);
    if (strpos($command, "{outputFileName}") !== false) {
        $command = str_replace('{outputFileName}', $convertedFile, $command);
        shell_exec($command);  // COMMAND FROM CONFIG EXECUTED!
    } else {
        $textContent = shell_exec($command) . "\n";
        file_put_contents($convertedFile, $textContent);
    }
}
```

**Impact:** If an attacker can modify configuration or control file paths, arbitrary command execution is possible.

---

### 4.5 HIGH: Dangerous eval() Usage

- **File:** `protected/humhub/libs/DynamicConfig.php`
- **Lines:** 47-56

```php
public static function load()
{
    $configFile = self::getConfigFilePath();

    // Load config file with 'file_get_contents' and 'eval'
    $configContent = str_replace(['<' . '?php', '<' . '?', '?' . '>'], '', file_get_contents($configFile));
    $config = eval($configContent);  // EVAL ON FILE CONTENTS!

    if (!is_array($config)) {
        return [];
    }

    return $config;
}
```

**Impact:** If config file is writable (e.g., via file upload vulnerability, LFI, or misconfiguration), immediate remote code execution.

---

### 4.6 HIGH: Insecure Deserialization

- **File:** `protected/humhub/modules/live/controllers/PollController.php`
- **Lines:** 118-133

```php
protected function unserializeEvent($serializedEvent)
{
    try {
        /* @var $liveEvent LiveEvent */
        $liveEvent = unserialize($serializedEvent);  // NO allowed_classes!

        if (!$liveEvent instanceof LiveEvent) {
            throw new Exception('Invalid live event class after unserialize!');
        }
    } catch (\Exception $ex) {
        Yii::error('Could not unserialize live event! ' . $ex->getMessage(), 'live');
        return null;
    }

    return $liveEvent;
}
```

**Impact:** If an attacker can inject malicious serialized data into the database (via SQL injection or direct DB access), PHP Object Injection leading to RCE.

---

## 5. Supply Chain & Dependencies

### 5.1 Composer Dependencies Analysis

| Package | Status | Notes |
|---------|--------|-------|
| `yiisoft/yii2: dev-master` | ⚠️ **WARNING** | Using dev-master in production is risky |
| `roave/security-advisories: dev-latest` | ✅ Good | Blocks known vulnerable packages |
| `firebase/php-jwt: ^6.0` | ✅ Clean | No known vulnerabilities |
| `laminas/laminas-ldap: ^2.10` | ✅ Clean | Recent version |
| `phpoffice/phpspreadsheet: ^2.2` | ⚠️ Review | Check for recent CVEs |
| `symfony/mailer: ^5.4` | ✅ Clean | No known vulnerabilities |

### 5.2 NPM Dependencies

| Package | Status | Notes |
|---------|--------|-------|
| `grunt: ^1.6.1` | ⚠️ Dev only | Ensure not deployed to prod |
| Build tools only | ✅ | No runtime dependencies |

### 5.3 Recommendations

- **Replace `yiisoft/yii2: dev-master`** with a stable release version
- Run `composer audit` before each deployment
- Implement dependency scanning in CI/CD pipeline

---

## 6. Recommendations & Next Steps

### 🔴 IMMEDIATE ACTIONS (Before Any Deployment)

1. **Remove or Disable ErpAuthController Entirely**
   ```php
   // Delete or comment out the ERP routes in protected/humhub/modules/user/config.php:
   // 'pattern' => 'auth_user/<user_email:[^/]+>',
   // 'pattern' => 'api/auth/login',
   // 'pattern' => 'api/erp/validate-token',
   ```

2. **If ERP SSO is Required, Implement Proper Security:**
   - Use cryptographically signed JWT tokens
   - Implement HMAC signature verification
   - Add timestamp/nonce to prevent replay attacks
   - Enable CSRF protection
   - Add IP whitelisting for ERP servers
   
   ```php
   // Example secure implementation
   public function actionAuthUser()
   {
       // Require POST with CSRF
       $this->forcePostRequest();
       
       $token = Yii::$app->request->post('token');
       $signature = Yii::$app->request->post('signature');
       $timestamp = Yii::$app->request->post('timestamp');
       
       // Verify timestamp (5 min window)
       if (abs(time() - $timestamp) > 300) {
           throw new HttpException(401, 'Token expired');
       }
       
       // Verify HMAC signature
       $expected = hash_hmac('sha256', $token . $timestamp, $secretKey);
       if (!hash_equals($expected, $signature)) {
           throw new HttpException(401, 'Invalid signature');
       }
       
       // Verify JWT token
       $payload = JWT::decode($token, new Key($publicKey, 'RS256'));
       // ... continue with authentication
   }
   ```

3. **Remove Session Enumeration Endpoint**
   ```php
   // Remove or protect actionGetSessionUserJson()
   // Add strict access control if needed for legitimate use
   ```

4. **Fix XSS in htmlRedirect.php**
   ```php
   <script <?= \humhub\libs\Html::nonce() ?>>
       window.location.href = <?= \yii\helpers\Json::htmlEncode($url) ?>;
   </script>
   ```

5. **Remove Debug Code**
   ```php
   // Remove file_put_contents debug logging in ErpAuthController
   ```

### 🟡 SHORT-TERM ACTIONS (Before Production)

1. **Fix Insecure Deserialization**
   ```php
   $liveEvent = unserialize($serializedEvent, ['allowed_classes' => [LiveEvent::class]]);
   ```

2. **Replace eval() in DynamicConfig**
   ```php
   // Use standard PHP include or JSON
   $config = require $configFile;
   // OR
   $config = json_decode(file_get_contents($configFile), true);
   ```

3. **Strengthen CSP**
   ```php
   'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'nonce-{{nonce}}'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; frame-ancestors 'self';"
   ```

4. **Enable Double Extension Blocking**
   ```php
   // In file module config
   public $denyDoubleFileExtensions = true;
   ```

5. **Add SSRF Protections to OEmbed**
   ```php
   // Validate URLs against internal network ranges
   // Block file://, gopher://, etc.
   ```

### 🟢 LONG-TERM RECOMMENDATIONS

1. Implement Web Application Firewall (WAF)
2. Add security headers via reverse proxy
3. Enable rate limiting on authentication endpoints
4. Implement security logging and alerting
5. Conduct penetration testing after remediation
6. Lock Yii2 to a stable release version
7. Set up automated dependency vulnerability scanning

---

## 7. Appendix: Files Requiring Immediate Attention

| Priority | File | Action Required |
|----------|------|-----------------|
| P0 | `protected/humhub/modules/user/controllers/ErpAuthController.php` | Remove or rewrite |
| P0 | `protected/humhub/modules/user/authclient/ErpAuth.php` | Remove or rewrite |
| P0 | `protected/humhub/modules/user/config.php` | Remove ERP routes |
| P1 | `protected/humhub/modules/user/controllers/AuthController.php` | Fix session endpoint |
| P1 | `protected/humhub/views/htmlRedirect.php` | Fix XSS |
| P2 | `protected/humhub/libs/DynamicConfig.php` | Replace eval() |
| P2 | `protected/humhub/modules/live/controllers/PollController.php` | Fix unserialize() |
| P2 | `protected/humhub/modules/file/converter/TextConverter.php` | Validate commands |
| P3 | `protected/config/web.php` | Strengthen CSP |

---

**Report Generated:** December 4, 2025  
**Classification:** CONFIDENTIAL - Internal Use Only  
**Next Review Date:** After remediation of Critical/High issues

