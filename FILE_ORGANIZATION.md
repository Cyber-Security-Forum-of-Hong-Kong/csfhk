# File Organization Structure

This document describes the organized file structure of the application.

## Directory Structure

```
/
├── security/          # All security plugin files
├── docs/              # Documentation files
├── assets/            # CSS, JavaScript, and static assets
├── config/            # Configuration files
├── sql/               # SQL schema files
├── logs/              # Log files (created at runtime)
├── index.php          # Main entry point
├── discuss.php        # Discussion forum page
├── resource.php       # Resources page
├── ctfquestion.php    # CTF challenge page
├── login.php          # Login handler
├── signup.php         # Signup handler
├── logout.php         # Logout handler
├── auth.php           # Authentication functions
├── db.php             # Database connection
├── discussions_api.php # Discussions API endpoint
├── ctf_check.php      # CTF flag checker
├── hard_crypto_challenge.php # Crypto challenge
├── .htaccess          # Apache configuration
├── web.config         # IIS configuration
├── robots.txt         # Search engine directives
└── .env               # Environment variables (not in repo)
```

## Security Plugins (`security/`)

All 32 security plugin files are located in the `security/` directory:

- Core: `waf.php`, `security.php`, `advanced_security.php`
- Monitoring: `security_monitor.php`, `security_audit.php`
- Detection: `bot_detection.php`, `intrusion_detection.php`
- Reputation: `ip_reputation.php`, `threat_intelligence.php`
- Validation: `input_validator.php`, `request_validator.php`, `password_policy.php`
- Protection: `ddos_protection.php`, `session_security.php`, `session_fixation_protection.php`
- Analysis: `behavioral_analysis.php`, `timing_analysis.php`, `device_fingerprinting.php`
- Correlation: `security_correlation.php`
- Response: `threat_response.php`, `automated_response.php`
- And more...

## Documentation (`docs/`)

All markdown documentation files:

- `ULTIMATE_DEFENSE_SUMMARY.md` - Complete defense system overview
- `ADVANCED_DEFENSE_FEATURES.md` - Advanced defense features
- `SECURITY_PLUGINS.md` - Security plugin documentation
- `SECURITY_CHECKLIST.md` - Security checklist
- `DEPLOYMENT_SECURITY.md` - Deployment security guide
- And more...

## Assets (`assets/`)

Static files:

- `styles.css` - Main stylesheet
- `script.js` - Main JavaScript
- `ctf.js` - CTF challenge JavaScript

## Configuration (`config/`)

- `config.php` - Configuration loader (loads from `.env`)

## SQL (`sql/`)

- `create_users_table.sql` - User table schema

## Path Updates

All PHP files have been updated to use the new paths:

- Security plugins: `require __DIR__ . '/security/filename.php';`
- Config: `require __DIR__ . '/config/config.php';`
- Assets: `<link rel="stylesheet" href="assets/styles.css">`
- Scripts: `<script src="assets/script.js"></script>`

## Access Protection

The `.htaccess` file has been updated to:
- Block direct access to `security/`, `config/`, `logs/`, `docs/`, `sql/` directories
- Protect sensitive files in root directory

## Benefits

1. **Organization**: Clear separation of concerns
2. **Security**: Easier to protect sensitive directories
3. **Maintainability**: Easier to find and update files
4. **Scalability**: Easy to add new plugins or documentation
5. **Professional**: Industry-standard directory structure

