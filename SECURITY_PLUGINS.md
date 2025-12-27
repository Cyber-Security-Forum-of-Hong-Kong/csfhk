# 🛡️ Security Plugins Documentation

## Overview

This application now includes multiple security plugins that work together to provide comprehensive protection against various threats.

## Installed Security Plugins

### 1. **Security Monitor** (`security_monitor.php`)
**Purpose**: Monitors and logs security events, tracks statistics, and triggers alerts.

**Features**:
- Real-time security event logging
- Statistics tracking (failed logins, blocked requests, etc.)
- Threshold monitoring and alerting
- Security event history

**Usage**:
```php
SecurityMonitor::logEvent('FAILED_LOGIN', 'medium', 'Failed login attempt', ['email' => $email]);
$stats = SecurityMonitor::getStats(24); // Get last 24 hours stats
$alerts = SecurityMonitor::getRecentAlerts(50); // Get recent alerts
```

**Log Files**:
- `logs/security_alerts.json` - Security alerts
- `logs/security_stats.json` - Statistics

---

### 2. **Bot Detection** (`bot_detection.php`)
**Purpose**: Detects and blocks automated bots, scrapers, and malicious crawlers.

**Features**:
- User-Agent pattern matching
- Honeypot field detection
- Request rate analysis
- Legitimate bot verification

**Usage**:
```php
// Check if request is from bot
if (BotDetection::isBot(true)) {
    // Block request
}

// Add honeypot field to form
echo BotDetection::generateHoneypotField('website', 'Website');

// Check honeypot
if (!BotDetection::checkHoneypot('website')) {
    // Bot detected
}

// Check request rate
if (!BotDetection::checkRequestRate($ip, 30, 60)) {
    // Rate exceeded
}
```

**Detection Methods**:
- User-Agent analysis
- Missing headers detection
- Honeypot field filling
- Request rate patterns

---

### 3. **IP Reputation** (`ip_reputation.php`)
**Purpose**: Tracks IP reputation, maintains blacklist/whitelist, and blocks malicious IPs.

**Features**:
- IP blacklisting/whitelisting
- Reputation scoring system
- Automatic blacklisting based on reputation
- CIDR range support

**Usage**:
```php
// Check if IP is blacklisted
if (IPReputation::isBlacklisted($ip)) {
    // Block request
}

// Blacklist an IP
IPReputation::blacklistIP($ip, 'Repeated attacks', 86400); // 24 hours

// Update reputation
IPReputation::updateReputation($ip, -10); // Negative score
IPReputation::updateReputation($ip, 5);   // Positive score

// Get reputation
$score = IPReputation::getReputation($ip);
```

**Reputation System**:
- Starts at 0
- Negative actions decrease score
- Positive actions increase score
- Auto-blacklist at -100

**Log Files**:
- `logs/ip_blacklist.json` - Blacklisted IPs
- `logs/ip_whitelist.json` - Whitelisted IPs
- `logs/ip_reputation.json` - IP reputation scores

---

### 4. **Request Signature** (`request_signature.php`)
**Purpose**: Validates request signatures to prevent tampering and replay attacks.

**Features**:
- HMAC-based request signing
- Nonce generation and validation
- Replay attack prevention
- Form data integrity verification

**Usage**:
```php
// Generate signature for form data
$signedData = RequestSignature::signFormData($formData);

// Verify form data signature
if (!RequestSignature::verifyFormData($_POST)) {
    // Invalid signature
}

// Generate nonce
$nonce = RequestSignature::generateNonce();

// Validate nonce
if (!RequestSignature::validateNonce($nonce)) {
    // Nonce already used or expired
}
```

**Security Features**:
- HMAC-SHA256 signatures
- Nonce expiration (default 5 minutes)
- Replay attack prevention

---

## Integration Points

### Entry Points Protected:
1. **discussions_api.php**
   - IP reputation checking
   - Bot detection
   - Security monitoring
   - Request rate limiting

2. **login.php**
   - IP reputation checking
   - Bot detection
   - Honeypot validation
   - Security event logging

3. **signup.php**
   - IP reputation checking
   - Bot detection
   - Honeypot validation
   - Security event logging

## Security Event Types

### Event Severities:
- **low**: Informational events
- **medium**: Suspicious activity
- **high**: Security threats
- **critical**: Immediate action required

### Event Types Logged:
- `FAILED_LOGIN` - Failed login attempts
- `SUCCESSFUL_LOGIN` - Successful logins
- `BLOCKED_REQUEST` - Blocked requests
- `SUSPICIOUS_PATTERN` - Suspicious patterns detected
- `BOT_DETECTED` - Bot detection
- `HONEYPOT_CATCH` - Honeypot triggered
- `BLACKLISTED_IP` - Blacklisted IP access attempt
- `WAF_BLOCKED` - WAF blocked request
- `BOT_RATE_EXCEEDED` - Bot rate limit exceeded

## Configuration

### Environment Variables:
Add to `.env`:
```
REQUEST_SIGNATURE_KEY=your_secret_key_here
```

### Thresholds (in security_monitor.php):
```php
'thresholds' => [
    'failed_logins_per_hour' => 10,
    'blocked_requests_per_hour' => 50,
    'suspicious_patterns_per_hour' => 20,
]
```

## Monitoring & Alerts

### View Security Statistics:
```php
$stats = SecurityMonitor::getStats(24); // Last 24 hours
```

### View Recent Alerts:
```php
$alerts = SecurityMonitor::getRecentAlerts(50);
```

### Check IP Reputation:
```php
$score = IPReputation::getReputation($ip);
```

## Best Practices

1. **Regular Monitoring**: Review security logs regularly
2. **IP Management**: Periodically review blacklist/whitelist
3. **Reputation Tuning**: Adjust reputation scores based on your needs
4. **Honeypot Fields**: Add to all forms (hidden from users)
5. **Nonce Usage**: Use nonces for critical operations
6. **Alert Thresholds**: Adjust based on your traffic patterns

## Log Files Location

All security logs are stored in `logs/` directory:
- `security_alerts.json` - Security alerts
- `security_stats.json` - Statistics
- `honeypot_catches.json` - Honeypot catches
- `ip_blacklist.json` - Blacklisted IPs
- `ip_whitelist.json` - Whitelisted IPs
- `ip_reputation.json` - IP reputation
- `signature_key.txt` - Request signature key
- `nonces.json` - Used nonces

## Performance Impact

All plugins are optimized for performance:
- Minimal database/file I/O
- Efficient pattern matching
- Cached reputation scores
- Asynchronous logging where possible

## Security Benefits

✅ **Bot Protection**: Blocks automated attacks
✅ **IP Management**: Tracks and blocks malicious IPs
✅ **Request Validation**: Prevents tampering and replay attacks
✅ **Monitoring**: Real-time security event tracking
✅ **Alerting**: Automatic alerts on threshold breaches
✅ **Reputation System**: Adaptive blocking based on behavior

## Future Enhancements

Potential additions:
- Integration with threat intelligence feeds
- Machine learning-based anomaly detection
- Real-time dashboard for security monitoring
- Email/SMS alerting
- Integration with external security services

