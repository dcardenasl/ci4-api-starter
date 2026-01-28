# CI/CD Guide

## Overview

This project uses GitHub Actions for continuous integration and deployment. The CI pipeline automatically runs tests and checks on every push and pull request.

## Workflows

### 1. CI Workflow (`.github/workflows/ci.yml`)

Runs automated tests on every push and pull request.

**Triggers:**
- Push to `main` or `cc` branches
- Pull requests to `main` branch

**Matrix Strategy:**
- PHP 8.2
- PHP 8.3

**Services:**
- MySQL 8.0 (test database)

**Steps:**

1. **Checkout Code** - Pulls the repository code
2. **Setup PHP** - Configures PHP with required extensions (mysqli, mbstring, intl, json)
3. **Validate Composer** - Ensures composer.json is valid
4. **Cache Dependencies** - Caches composer packages for faster builds
5. **Install Dependencies** - Runs `composer install`
6. **Configure Environment** - Creates .env from .env.example and generates secure keys
7. **Run Tests** - Executes PHPUnit test suite with testdox output
8. **Code Style Check** - Optional PHP CS Fixer check (if configured)

**Environment Configuration:**

The CI automatically generates:
- JWT secret key using `openssl rand -base64 64`
- Encryption key using `php spark key:generate`
- Test database credentials (MySQL service)

**Test Database:**
- Host: 127.0.0.1
- Database: ci4_test
- Username: root
- Password: root
- Port: 3306

## Workflow Status

You can check the status of your workflows in the GitHub Actions tab of your repository.

### Adding Status Badge to README

Add this to your README.md to show CI status:

```markdown
![CI Status](https://github.com/YOUR_USERNAME/YOUR_REPO/actions/workflows/ci.yml/badge.svg)
```

Replace `YOUR_USERNAME` and `YOUR_REPO` with your actual GitHub username and repository name.

## Local Testing Before Push

Before pushing code, ensure all tests pass locally:

```bash
# Run tests
vendor/bin/phpunit --no-coverage

# Check if all tests pass
vendor/bin/phpunit --testdox

# Validate composer.json
composer validate --strict
```

## Troubleshooting CI Failures

### 1. Tests Fail in CI but Pass Locally

**Common Causes:**
- Different PHP versions (CI tests on PHP 8.2 and 8.3)
- Database configuration differences
- Missing environment variables
- Timezone differences

**Solutions:**
- Test locally with multiple PHP versions using Docker
- Check workflow logs for specific error messages
- Ensure .env.example contains all required variables

### 2. Composer Install Fails

**Common Causes:**
- Invalid composer.json
- Version conflicts
- Missing extensions

**Solutions:**
- Run `composer validate --strict` locally
- Update composer.lock: `composer update`
- Check required PHP extensions in composer.json

### 3. Database Connection Fails

**Common Causes:**
- MySQL service not ready
- Wrong credentials
- Database not created

**Solutions:**
- Check MySQL service health check in workflow
- Verify database credentials in workflow
- Ensure MYSQL_DATABASE is set in service configuration

### 4. Environment Configuration Issues

**Common Causes:**
- Missing JWT_SECRET_KEY
- Missing encryption key
- .env.example not up to date

**Solutions:**
- Update .env.example with all required variables
- Check workflow step "Configure testing environment"
- Ensure key generation commands work in CI environment

### 5. PHPUnit Shows Options Instead of Running Tests

**Symptoms:**
- CI job shows PHPUnit help/options text
- Exit code 1 with no actual test output
- Error: "process completed with exit code 1"

**Common Causes:**
- `phpunit.xml` file not committed to repository
- `phpunit.xml` excluded in `.gitignore`
- Missing or incorrect PHPUnit configuration

**Solutions:**
1. **Check if phpunit.xml is ignored:**
   ```bash
   git status phpunit.xml
   ```

2. **Fix .gitignore if needed:**
   Remove `/phpunit*.xml` and replace with:
   ```
   .phpunit.result.cache
   .phpunit.cache
   ```

3. **Commit phpunit.xml:**
   ```bash
   git add phpunit.xml
   git commit -m "Add phpunit.xml configuration"
   git push
   ```

4. **Update CI workflow to verify configuration:**
   ```yaml
   - name: Verify PHPUnit configuration
     run: |
       if [ ! -f "phpunit.xml" ]; then
         echo "❌ phpunit.xml not found!"
         exit 1
       fi
       echo "✓ phpunit.xml found"
   ```

5. **Use explicit configuration path:**
   ```yaml
   - name: Run tests
     run: vendor/bin/phpunit --configuration phpunit.xml --no-coverage
   ```

**Prevention:**
- Always commit `phpunit.xml` to version control
- Only ignore cache files (`.phpunit.result.cache`)
- Test CI locally with `act` before pushing

### 6. Invalid .env Values (Spaces/Special Characters)

**Symptoms:**
- Error: "InvalidArgumentException"
- Message about environment configuration
- Tests fail to run in CI

**Common Causes:**
- Environment variables with spaces not quoted
- Empty values without quotes
- Special characters in generated keys (base64, encryption keys)

**Solutions:**
1. **Quote all values with spaces or special characters:**
   ```bash
   # Wrong
   JWT_SECRET_KEY = abc123+/=xyz

   # Correct
   JWT_SECRET_KEY = "abc123+/=xyz"
   ```

2. **Quote empty values:**
   ```bash
   # Wrong
   database.tests.DBPrefix =

   # Correct
   database.tests.DBPrefix = ""
   ```

3. **Update CI workflow to properly quote generated values:**
   ```yaml
   - name: Configure testing environment
     run: |
       # Generate and quote JWT key
       JWT_KEY=$(openssl rand -base64 64 | tr -d '\n')
       echo "JWT_SECRET_KEY = \"${JWT_KEY}\"" >> .env

       # Generate and quote encryption key
       ENC_KEY=$(php spark key:generate --show | sed 's/.*: //')
       echo "encryption.key = \"${ENC_KEY}\"" >> .env

       # Empty value with quotes
       echo 'database.tests.DBPrefix = ""' >> .env
   ```

**Prevention:**
- Always use double quotes for string values in .env
- Test .env generation locally before pushing
- Use single quotes in echo to prevent shell expansion
- Store generated values in variables before echoing

## Extending CI/CD

### Adding Code Coverage

To add code coverage reporting:

1. Install Xdebug in workflow:
```yaml
- name: Setup PHP
  uses: shivammathur/setup-php@v2
  with:
    php-version: ${{ matrix.php-version }}
    extensions: mysqli, mbstring, intl, json, xdebug
    coverage: xdebug
```

2. Run tests with coverage:
```yaml
- name: Run tests
  run: vendor/bin/phpunit --coverage-clover coverage.xml
```

3. Upload to Codecov:
```yaml
- name: Upload coverage to Codecov
  uses: codecov/codecov-action@v3
  with:
    files: ./coverage.xml
```

### Adding Static Analysis

Add PHPStan for static analysis:

1. Install PHPStan:
```bash
composer require --dev phpstan/phpstan
```

2. Create `phpstan.neon`:
```neon
parameters:
    level: 5
    paths:
        - app
```

3. Add step to workflow:
```yaml
- name: Run static analysis
  run: vendor/bin/phpstan analyze
```

### Adding Security Checks

Add security vulnerability scanning:

```yaml
- name: Check security vulnerabilities
  run: composer audit
```

### Adding Docker Build Test

Test Docker image builds in CI:

```yaml
- name: Build Docker image
  run: docker build -t ci4-api-starter:test .

- name: Test Docker image
  run: |
    docker run -d --name test-container ci4-api-starter:test
    docker ps | grep test-container
    docker stop test-container
```

## Deployment Workflows

### Manual Deployment to Staging

Create `.github/workflows/deploy-staging.yml`:

```yaml
name: Deploy to Staging

on:
  workflow_dispatch:

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Deploy to staging
        run: |
          echo "Deploy to staging server"
          # Add your deployment commands here
```

### Automatic Deployment to Production

Create `.github/workflows/deploy-production.yml`:

```yaml
name: Deploy to Production

on:
  release:
    types: [published]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Deploy to production
        run: |
          echo "Deploy to production server"
          # Add your deployment commands here
```

## Environment Secrets

For deployment, add secrets in GitHub repository settings:

1. Go to Settings → Secrets and variables → Actions
2. Add repository secrets:
   - `PRODUCTION_HOST` - Production server address
   - `PRODUCTION_USER` - SSH username
   - `PRODUCTION_KEY` - SSH private key
   - `JWT_SECRET_PROD` - Production JWT secret
   - `DB_PASSWORD_PROD` - Production database password

Use secrets in workflows:

```yaml
- name: Deploy
  run: |
    echo "${{ secrets.PRODUCTION_KEY }}" > deploy_key
    chmod 600 deploy_key
    ssh -i deploy_key ${{ secrets.PRODUCTION_USER }}@${{ secrets.PRODUCTION_HOST }} "cd /var/www && git pull"
```

## Best Practices

1. **Always run tests locally before pushing**
   ```bash
   vendor/bin/phpunit
   ```

2. **Keep .env.example updated**
   - Add all new environment variables to .env.example
   - Use placeholder values, never real credentials

3. **Use branch protection rules**
   - Require PR reviews before merging
   - Require CI checks to pass
   - Restrict who can push to main

4. **Monitor workflow execution times**
   - Optimize slow tests
   - Use caching effectively
   - Consider parallel test execution

5. **Keep dependencies updated**
   ```bash
   composer update
   ```

6. **Security scanning**
   - Run `composer audit` regularly
   - Keep PHP and dependencies updated
   - Review security advisories

## Performance Optimization

### Cache Optimization

The workflow caches composer packages based on:
- Operating system
- PHP version
- composer.lock hash

To clear cache:
1. Go to GitHub Actions → Caches
2. Delete old caches

### Parallel Testing

To speed up tests, consider using parallel execution:

```bash
# Install paratest
composer require --dev brianium/paratest

# Run tests in parallel
vendor/bin/paratest --processes=4
```

Update workflow:
```yaml
- name: Run tests
  run: vendor/bin/paratest --processes=4 --no-coverage
```

## Monitoring and Notifications

### Slack Notifications

Add Slack notifications for CI failures:

```yaml
- name: Notify Slack on failure
  if: failure()
  uses: 8398a7/action-slack@v3
  with:
    status: ${{ job.status }}
    webhook_url: ${{ secrets.SLACK_WEBHOOK }}
```

### Email Notifications

GitHub automatically sends email notifications for workflow failures to repository watchers.

## Resources

- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [CodeIgniter 4 Testing Guide](https://codeigniter.com/user_guide/testing/index.html)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Composer Documentation](https://getcomposer.org/doc/)
