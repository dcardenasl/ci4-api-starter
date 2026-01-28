# Security Guide

Comprehensive security guidelines for CodeIgniter 4 API Starter project.

## ⚠️ IMPORTANT: Before First Commit

**NEVER commit sensitive credentials to git!** Follow these steps:

### 1. Environment Files Setup

```bash
# Copy example files to actual configuration files
cp .env.example .env
cp .env.docker.example .env.docker

# These files (.env and .env.docker) are already in .gitignore
# and should NEVER be committed to version control
```

### 2. Generate Secure Keys

**JWT Secret Key:**
```bash
# Generate a strong JWT secret (64 bytes base64 encoded)
openssl rand -base64 64

# Add to both .env and .env.docker:
JWT_SECRET_KEY = 'your-generated-key-here'
```

**Encryption Key:**
```bash
# Generate encryption key
php spark key:generate

# Or manually:
openssl rand -hex 32

# Add to both .env and .env.docker:
encryption.key = 'hex:your-generated-key-here'
```

### 3. Database Credentials

**For Local Development (.env):**
```bash
database.default.password = your_local_mysql_password
database.tests.password = your_local_mysql_password
```

**For Docker (.env.docker):**
```bash
# MySQL credentials (used by docker-compose.yml)
MYSQL_ROOT_PASSWORD = strong_random_password_here
MYSQL_PASSWORD = another_strong_password_here

# Application database connection
database.default.password = same_as_MYSQL_PASSWORD_above
database.tests.password = same_as_MYSQL_PASSWORD_above
```

## 📋 Pre-Commit Checklist

Before making your first commit, verify:

- [ ] ✅ `.env` file exists locally but is in `.gitignore`
- [ ] ✅ `.env.docker` file exists locally but is in `.gitignore`
- [ ] ✅ `.env.example` exists and has NO real credentials (placeholders only)
- [ ] ✅ `.env.docker.example` exists and has NO real credentials
- [ ] ✅ `JWT_SECRET_KEY` is set in both `.env` and `.env.docker`
- [ ] ✅ `encryption.key` is set in both `.env` and `.env.docker`
- [ ] ✅ Database passwords are changed from defaults
- [ ] ✅ No hardcoded passwords in `docker-compose.yml` (uses variables)

**Verify what will be committed:**
```bash
# Check what files are staged
git status

# Ensure .env and .env.docker are NOT listed
# Only .env.example and .env.docker.example should be tracked

# Verify no secrets in staged files
git diff --cached | grep -i "password\|secret\|key" | grep -v "CHANGE_THIS\|YOUR_"
```

## 🔒 Security Best Practices

### Credentials Management

#### What to COMMIT (Safe):
- ✅ `.env.example` - Template with placeholders
- ✅ `.env.docker.example` - Docker template with placeholders
- ✅ `docker-compose.yml` - Uses environment variables, no hardcoded passwords
- ✅ `docker-compose.override.yml.example` - Local override template
- ✅ Documentation files (README.md, SECURITY.md, etc.)

#### What to NEVER COMMIT (Sensitive):
- ❌ `.env` - Contains real local credentials
- ❌ `.env.docker` - Contains real Docker credentials
- ❌ `docker-compose.override.yml` - May contain local passwords
- ❌ `.key`, `.pem`, `.p12` files - Encryption keys
- ❌ `*.sql` files - May contain sensitive data
- ❌ Backup files with credentials

### Password Requirements

**Production passwords must be:**
- At least 16 characters long
- Include uppercase and lowercase letters
- Include numbers and special characters
- Not based on dictionary words
- Different for each service/environment

**Generate secure passwords:**
```bash
# Generate random password (32 characters)
openssl rand -base64 32

# Or use a password manager (recommended)
```

### Environment-Specific Configuration

#### Development (.env)
```bash
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080'
database.default.password = dev_password  # Still secure, but different from prod
JWT_SECRET_KEY = 'dev-key-different-from-production'
```

#### Production (.env)
```bash
CI_ENVIRONMENT = production
app.baseURL = 'https://your-domain.com'
app.forceGlobalSecureRequests = true  # Force HTTPS
database.default.password = very_long_secure_production_password
JWT_SECRET_KEY = 'very-long-random-production-key'
```

## 🐳 Docker Security

### Docker Compose Variables

The `docker-compose.yml` uses environment variables from `.env.docker`:

```yaml
# docker-compose.yml reads from .env.docker automatically
MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
MYSQL_PASSWORD: ${MYSQL_PASSWORD}
```

**Never hardcode credentials in docker-compose.yml!**

### Docker Secrets (Production)

For production deployments, use Docker secrets:

```yaml
# docker-compose.production.yml
services:
  db:
    secrets:
      - mysql_root_password
      - mysql_password
    environment:
      MYSQL_ROOT_PASSWORD_FILE: /run/secrets/mysql_root_password

secrets:
  mysql_root_password:
    external: true
  mysql_password:
    external: true
```

Create secrets:
```bash
echo "your_strong_password" | docker secret create mysql_root_password -
```

## 🚨 If Credentials Were Committed

If you accidentally committed sensitive data:

### 1. Immediate Action
```bash
# Remove from latest commit (if not pushed)
git reset --soft HEAD~1
git restore --staged .env

# Or remove specific file from git
git rm --cached .env
git commit -m "Remove sensitive file"
```

### 2. If Already Pushed

**CRITICAL: All committed credentials are compromised!**

1. **Rotate all credentials immediately:**
   - Generate new JWT_SECRET_KEY
   - Generate new encryption.key
   - Change all database passwords
   - Update production servers with new credentials

2. **Remove from git history:**
```bash
# Use BFG Repo-Cleaner (recommended)
brew install bfg  # or download from https://rtyley.github.io/bfg-repo-cleaner/
bfg --delete-files .env
git reflog expire --expire=now --all && git gc --prune=now --aggressive

# Or use git filter-repo
git filter-repo --path .env --invert-paths
```

3. **Force push (WARNING: coordinate with team):**
```bash
git push origin --force --all
```

4. **Notify team to re-clone repository**

## 🔐 Additional Security Measures

### JWT Token Security

```bash
# JWT tokens should:
# - Be long and random (256+ bits)
# - Be different for each environment
# - Be rotated periodically
# - Never be logged or stored in plain text

# Example secure JWT key generation:
node -e "console.log(require('crypto').randomBytes(64).toString('base64'))"
```

### Database Access

```bash
# Restrict database user permissions
# Grant only necessary privileges:
GRANT SELECT, INSERT, UPDATE, DELETE ON ci4_api.* TO 'ci4_user'@'%';
FLUSH PRIVILEGES;

# Don't use root user in application
# Create separate users for:
# - Application (limited permissions)
# - Migrations (DDL permissions)
# - Backups (SELECT only)
```

### File Permissions

```bash
# Set restrictive permissions on config files
chmod 600 .env
chmod 600 .env.docker

# Verify permissions
ls -la .env*
# Should show: -rw------- (600)
```

### HTTPS in Production

```bash
# Always use HTTPS in production
# Update .env:
app.forceGlobalSecureRequests = true
app.baseURL = 'https://your-domain.com'

# Use a reverse proxy (Nginx, Traefik) for SSL termination
```

## 📝 Security Audit Checklist

Regular security audit tasks:

### Weekly
- [ ] Review access logs for suspicious activity
- [ ] Check for failed authentication attempts
- [ ] Monitor database connection attempts

### Monthly
- [ ] Update dependencies: `composer update`
- [ ] Review and rotate API keys
- [ ] Check for security advisories
- [ ] Scan Docker images: `docker scan ci4-api-starter-app`

### Quarterly
- [ ] Rotate JWT secret keys (with migration plan)
- [ ] Change database passwords (coordinate with team)
- [ ] Review and update security policies
- [ ] Penetration testing

### Annually
- [ ] Full security audit
- [ ] Update encryption keys
- [ ] Review all third-party integrations
- [ ] Update disaster recovery plan

## 🛠️ Tools

### Check for Exposed Secrets

```bash
# Install gitleaks
brew install gitleaks

# Scan repository for secrets
gitleaks detect --source . --verbose

# Scan before committing
gitleaks protect --staged
```

### Dependency Scanning

```bash
# Check for vulnerable packages
composer audit

# Update vulnerable packages
composer update --with-dependencies
```

### Docker Security Scanning

```bash
# Scan Docker image
docker scan ci4-api-starter-app

# Use Trivy for comprehensive scanning
trivy image ci4-api-starter-app
```

## 📚 Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [CodeIgniter Security](https://codeigniter.com/user_guide/concepts/security.html)
- [Docker Security](https://docs.docker.com/engine/security/)
- [JWT Best Practices](https://tools.ietf.org/html/rfc8725)

## 🆘 Reporting Security Issues

If you discover a security vulnerability:

1. **DO NOT** open a public GitHub issue
2. Email: security@your-domain.com (if applicable)
3. Include:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)

## 📄 License

This security guide is part of the CI4 API Starter project.
