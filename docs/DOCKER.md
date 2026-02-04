# Docker Setup Guide

Complete Docker configuration for running the CodeIgniter 4 API Starter in containers.

## Quick Start

```bash
# Start all services
docker compose up -d

# Run migrations
docker exec ci4-api-app php spark migrate

# Check services status
docker compose ps

# View logs
docker compose logs -f app
```

The API will be available at: http://localhost:8080

## Services

### Application Container (`app`)
- **Image**: Custom built from Dockerfile
- **Base**: PHP 8.2-Apache
- **Port**: 8080 (mapped to 80 in container)
- **Health Check**: HTTP request to localhost every 30s
- **User**: www-data (non-root for security)

### MySQL Database (`db`)
- **Image**: MySQL 8.0
- **Port**: 3307 (mapped to 3306 in container)
- **Database**: ci4_api
- **Username**: ci4_user
- **Password**: ci4_password (change in production!)
- **Root Password**: root_password
- **Health Check**: mysqladmin ping every 10s

### phpMyAdmin (optional, `phpmyadmin`)
- **Image**: phpMyAdmin latest
- **Port**: 8081
- **Profile**: tools (not started by default)
- **Usage**: `docker compose --profile tools up -d`

## Docker Architecture

```
┌─────────────────────────────────────┐
│  Host Machine                       │
│                                     │
│  Port 8080 ──> app:80              │
│  Port 3307 ──> db:3306             │
│  Port 8081 ──> phpmyadmin:80       │
└─────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│  Docker Network: ci4-api-network    │
│                                     │
│  ┌──────────────┐  ┌─────────────┐ │
│  │ ci4-api-app  │  │ ci4-api-db  │ │
│  │              │──┤             │ │
│  │ PHP 8.2      │  │ MySQL 8.0   │ │
│  │ Apache       │  │             │ │
│  └──────────────┘  └─────────────┘ │
│                                     │
│  Volume: ci4-mysql-data             │
└─────────────────────────────────────┘
```

## Commands

### Service Management

```bash
# Start services
docker compose up -d

# Stop services
docker compose down

# Restart services
docker compose restart

# View logs
docker compose logs -f

# View specific service logs
docker compose logs -f app
docker compose logs -f db
```

### Application Commands

```bash
# Run migrations
docker exec ci4-api-app php spark migrate

# Generate Swagger docs
docker exec ci4-api-app php spark swagger:generate

# Run seeders
docker exec ci4-api-app php spark db:seed UserSeeder

# View routes
docker exec ci4-api-app php spark routes

# Access container shell
docker exec -it ci4-api-app bash
```

### Database Commands

```bash
# Access MySQL CLI
docker exec -it ci4-api-db mysql -u ci4_user -pci4_password ci4_api

# Run SQL file
docker exec -i ci4-api-db mysql -u ci4_user -pci4_password ci4_api < backup.sql

# Create database backup
docker exec ci4-api-db mysqldump -u ci4_user -pci4_password ci4_api > backup.sql

# Check database status
docker exec ci4-api-db mysql -u root -proot_password -e "SHOW DATABASES;"
```

### Development

```bash
# Rebuild after code changes
docker compose build app

# Rebuild without cache
docker compose build --no-cache app

# Update dependencies
docker compose run --rm app composer update

# Run tests (when configured)
docker exec ci4-api-app vendor/bin/phpunit
```

## Configuration Files

### Dockerfile
Multi-stage build for optimized production image:
- **Stage 1**: Installs Composer dependencies
- **Stage 2**: Creates production image with PHP 8.2, Apache, and extensions

**Key Features**:
- Production PHP configuration
- OPcache enabled
- Security headers configured
- Non-root user (www-data)
- Health checks
- Minimal image size

### docker-compose.yml
Orchestrates multiple services:
- Application container
- MySQL database
- phpMyAdmin (optional)
- Custom network for service communication
- Volume for database persistence

### .env.docker
Environment configuration for Docker:
- Database connection settings
- JWT secret key
- Application settings

### .dockerignore
Excludes unnecessary files from build context:
- Git files
- Documentation
- Tests
- Development tools
- Local environment files

## Volumes

### MySQL Data (`ci4-mysql-data`)
- **Purpose**: Persists database data between container restarts
- **Location**: Docker-managed volume
- **View**: `docker volume inspect ci4-mysql-data`
- **Remove**: `docker compose down -v` (WARNING: deletes all data)

### Application Writable Directory
- **Mount**: `./writable` mapped to `/var/www/html/writable`
- **Purpose**: Persist logs, cache, and uploads
- **Permissions**: 777 (writable by www-data)

## Networking

### Bridge Network (`ci4-api-network`)
- **Type**: Bridge
- **Purpose**: Isolate services and enable name-based discovery
- **DNS**: Services can reach each other by container name
  - App can connect to MySQL at `db:3306`
  - No need for localhost or IP addresses

## Health Checks

### Application
```yaml
test: curl -f http://localhost/ || exit 1
interval: 30s
timeout: 3s
retries: 3
start_period: 40s
```

### Database
```yaml
test: mysqladmin ping -h localhost -u root -proot_password
interval: 10s
timeout: 5s
retries: 10
start_period: 60s
```

## Security Considerations

### Production Checklist

- [ ] Change `JWT_SECRET_KEY` in .env.docker
- [ ] Change database passwords
- [ ] Change MySQL root password
- [ ] Use environment variables for secrets (not .env file)
- [ ] Enable HTTPS/SSL (use reverse proxy like Nginx/Traefik)
- [ ] Set `CI_ENVIRONMENT=production` in .env.docker
- [ ] Remove phpMyAdmin in production
- [ ] Implement rate limiting
- [ ] Configure firewall rules
- [ ] Use Docker secrets for sensitive data
- [ ] Scan image for vulnerabilities: `docker scan ci4-api-starter-app`

### Security Headers
Configured in Apache virtual host:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`

## Troubleshooting

### Container won't start
```bash
# Check logs
docker compose logs app

# Check for port conflicts
lsof -i :8080
lsof -i :3307

# Remove all containers and volumes
docker compose down -v
docker compose up -d
```

### Database connection failed
```bash
# Check if database is healthy
docker compose ps

# Test connection from app container
docker exec ci4-api-app php -r "echo mysqli_connect('db', 'ci4_user', 'ci4_password', 'ci4_api') ? 'Connected' : 'Failed';"

# Check MySQL logs
docker compose logs db
```

### Permission errors
```bash
# Fix writable directory permissions
docker exec -u root ci4-api-app chmod -R 777 /var/www/html/writable

# Reset ownership
docker exec -u root ci4-api-app chown -R www-data:www-data /var/www/html
```

### Can't access API
```bash
# Check if container is running
docker compose ps

# Check health status
docker inspect ci4-api-app --format='{{.State.Health.Status}}'

# Check Apache logs
docker exec ci4-api-app tail -f /var/log/apache2/error.log
```

### Migrations fail
```bash
# Check database connectivity
docker exec ci4-api-app php spark db:table users

# Run migrations with verbose output
docker exec ci4-api-app php spark migrate -vvv

# Reset migrations (WARNING: drops all tables)
docker exec ci4-api-app php spark migrate:rollback
docker exec ci4-api-app php spark migrate
```

## Performance Optimization

### Build Time
```bash
# Use BuildKit for faster builds
DOCKER_BUILDKIT=1 docker compose build

# Parallel builds
docker compose build --parallel
```

### Runtime
- OPcache is enabled by default
- Production PHP settings applied
- Apache modules optimized (gzip, expires, headers)

### Image Size
- Multi-stage build reduces final image size
- Only production dependencies included
- Build artifacts cleaned up
- Current size: ~676MB

## Development vs Production

### Development
```bash
# Use local .env file
docker compose --env-file .env up -d

# Mount source code for live reload (add to docker-compose)
volumes:
  - ./app:/var/www/html/app
  - ./public:/var/www/html/public
```

### Production
```bash
# Use production environment file
docker compose --env-file .env.docker up -d

# Don't mount source code (use baked-in files)
# Enable all security features
# Use orchestration (Kubernetes, Docker Swarm)
```

## CI/CD Integration

### Build in CI Pipeline
```yaml
# Example GitHub Actions
- name: Build Docker image
  run: docker build -t myapp:${{ github.sha }} .

- name: Push to registry
  run: docker push myapp:${{ github.sha }}
```

### Deploy to Production
```bash
# Pull latest image
docker pull myregistry/ci4-api-starter:latest

# Update and restart
docker compose pull
docker compose up -d
```

## Useful Commands Reference

```bash
# Clean up everything
docker compose down -v --rmi all --remove-orphans

# View container resource usage
docker stats ci4-api-app ci4-api-db

# Export database
docker exec ci4-api-db mysqldump -u root -proot_password ci4_api > backup_$(date +%Y%m%d).sql

# Import database
docker exec -i ci4-api-db mysql -u root -proot_password ci4_api < backup.sql

# View Apache access logs
docker exec ci4-api-app tail -f /var/log/apache2/access.log

# Test API endpoint
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"test","email":"test@example.com","password":"pass123"}'
```

## Support

For issues or questions:
- Check logs: `docker compose logs -f`
- Verify health: `docker compose ps`
- Rebuild: `docker compose build --no-cache`
- Report issues: GitHub Issues

## Additional Resources

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Reference](https://docs.docker.com/compose/)
- [PHP Docker Images](https://hub.docker.com/_/php)
- [MySQL Docker Images](https://hub.docker.com/_/mysql)
