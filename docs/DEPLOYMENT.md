# DigitalEdgeSolutions - Deployment Guide

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [Server Requirements](#server-requirements)
3. [Installation](#installation)
4. [Database Setup](#database-setup)
5. [Web Server Configuration](#web-server-configuration)
6. [SSL Certificate Setup](#ssl-certificate-setup)
7. [Real-time Server Setup](#real-time-server-setup)
8. [Cron Jobs Setup](#cron-jobs-setup)
9. [Monitoring & Maintenance](#monitoring--maintenance)

## Prerequisites

- Linux-based server (Ubuntu 22.04 LTS recommended)
- Root or sudo access
- Domain name pointed to server IP
- Basic knowledge of Linux command line

## Server Requirements

### Minimum Requirements
- **CPU**: 2 cores
- **RAM**: 4 GB
- **Storage**: 50 GB SSD
- **Bandwidth**: 1 TB/month

### Recommended Requirements
- **CPU**: 4+ cores
- **RAM**: 8+ GB
- **Storage**: 100 GB SSD
- **Bandwidth**: Unlimited

### Software Requirements
- PHP 8.2+
- MySQL 8.0+ or MariaDB 10.6+
- Node.js 18+
- Redis 7+
- Apache 2.4+ or Nginx 1.20+
- Composer 2+
- Git

## Installation

### 1. Update System
```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Install PHP and Extensions
```bash
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-curl php8.2-gd \
    php8.2-mbstring php8.2-xml php8.2-zip php8.2-bcmath php8.2-json \
    php8.2-opcache php8.2-intl php8.2-redis
```

### 3. Install MySQL
```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

### 4. Install Node.js
```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

### 5. Install Redis
```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
```

### 6. Install Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 7. Clone Repository
```bash
cd /var/www
git clone https://github.com/your-repo/digitaledgesolutions.git
cd digitaledgesolutions
```

### 8. Install PHP Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

### 9. Install Node.js Dependencies
```bash
cd realtime-server
npm install --production
cd ..
```

### 10. Set Environment Variables
```bash
cp .env.example .env
nano .env
# Edit the .env file with your configuration
```

### 11. Set Permissions
```bash
sudo chown -R www-data:www-data /var/www/digitaledgesolutions
sudo chmod -R 755 /var/www/digitaledgesolutions
sudo chmod -R 775 /var/www/digitaledgesolutions/frontend/public/uploads
sudo chmod -R 775 /var/www/digitaledgesolutions/logs
```

## Database Setup

### 1. Create Database
```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE digitaledgesolutions CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'des_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON digitaledgesolutions.* TO 'des_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 2. Import Schema
```bash
mysql -u des_user -p digitaledgesolutions < database/migrations/001_complete_schema.sql
```

## Web Server Configuration

### Apache Configuration

Create a new virtual host file:
```bash
sudo nano /etc/apache2/sites-available/digitaledgesolutions.conf
```

Add the following configuration:
```apache
<VirtualHost *:80>
    ServerName digitaledgesolutions.com
    ServerAlias www.digitaledgesolutions.com
    DocumentRoot /var/www/digitaledgesolutions/frontend/public
    
    <Directory /var/www/digitaledgesolutions/frontend/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # API Directory
    Alias /api /var/www/digitaledgesolutions/backend/api
    <Directory /var/www/digitaledgesolutions/backend/api>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # PHP Configuration
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php8.2-fpm.sock|fcgi://localhost"
    </FilesMatch>
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/digitaledgesolutions-error.log
    CustomLog ${APACHE_LOG_DIR}/digitaledgesolutions-access.log combined
</VirtualHost>
```

Enable the site and required modules:
```bash
sudo a2ensite digitaledgesolutions.conf
sudo a2enmod rewrite headers ssl proxy proxy_fcgi
sudo systemctl restart apache2
```

### Nginx Configuration

Create a new server block:
```bash
sudo nano /etc/nginx/sites-available/digitaledgesolutions
```

Add the following configuration:
```nginx
server {
    listen 80;
    server_name digitaledgesolutions.com www.digitaledgesolutions.com;
    root /var/www/digitaledgesolutions/frontend/public;
    index index.html index.php;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/rss+xml application/atom+xml image/svg+xml;
    
    # Static Files Caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # API Location
    location /api/ {
        alias /var/www/digitaledgesolutions/backend/api/;
        try_files $uri $uri/ /api/index.php?$query_string;
        
        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
            fastcgi_param SCRIPT_FILENAME $request_filename;
        }
    }
    
    # PHP Processing
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
    
    # Logs
    access_log /var/log/nginx/digitaledgesolutions-access.log;
    error_log /var/log/nginx/digitaledgesolutions-error.log;
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/digitaledgesolutions /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## SSL Certificate Setup

### Using Let's Encrypt (Certbot)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d digitaledgesolutions.com -d www.digitaledgesolutions.com
```

### Auto-renewal
```bash
sudo certbot renew --dry-run
```

Add to crontab:
```bash
sudo crontab -e
```

Add line:
```
0 12 * * * /usr/bin/certbot renew --quiet
```

## Real-time Server Setup

### 1. Create Systemd Service
```bash
sudo nano /etc/systemd/system/des-realtime.service
```

Add:
```ini
[Unit]
Description=DigitalEdgeSolutions Real-time Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/digitaledgesolutions/realtime-server
ExecStart=/usr/bin/node server.js
Restart=on-failure
RestartSec=10
Environment=NODE_ENV=production
Environment=PORT=3001

[Install]
WantedBy=multi-user.target
```

### 2. Start and Enable Service
```bash
sudo systemctl daemon-reload
sudo systemctl start des-realtime
sudo systemctl enable des-realtime
sudo systemctl status des-realtime
```

### 3. Configure Nginx for WebSocket (if using Nginx)
```nginx
location /socket.io/ {
    proxy_pass http://localhost:3001;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

## Cron Jobs Setup

### 1. Edit Crontab
```bash
sudo crontab -e
```

### 2. Add Scheduled Tasks
```bash
# Certificate cleanup (daily at 2 AM)
0 2 * * * /usr/bin/php /var/www/digitaledgesolutions/backend/cron/cleanup.php >> /var/log/des-cleanup.log 2>&1

# Session cleanup (hourly)
0 * * * * /usr/bin/php /var/www/digitaledgesolutions/backend/cron/session-cleanup.php >> /var/log/des-session.log 2>&1

# Email queue processing (every 5 minutes)
*/5 * * * * /usr/bin/php /var/www/digitaledgesolutions/backend/cron/process-emails.php >> /var/log/des-emails.log 2>&1

# Backup database (daily at 3 AM)
0 3 * * * /var/www/digitaledgesolutions/scripts/backup-database.sh >> /var/log/des-backup.log 2>&1

# Generate pending certificates (every hour)
0 * * * * /usr/bin/php /var/www/digitaledgesolutions/backend/cron/generate-certificates.php >> /var/log/des-certificates.log 2>&1

# Process payroll (1st of every month at 9 AM)
0 9 1 * * /usr/bin/php /var/www/digitaledgesolutions/backend/cron/process-payroll.php >> /var/log/des-payroll.log 2>&1

# Attendance auto-mark (daily at 9:30 AM)
30 9 * * * /usr/bin/php /var/www/digitaledgesolutions/backend/cron/auto-attendance.php >> /var/log/des-attendance.log 2>&1
```

## Monitoring & Maintenance

### 1. Install Monitoring Tools
```bash
sudo apt install -y htop iotop nethogs
```

### 2. Log Rotation
```bash
sudo nano /etc/logrotate.d/digitaledgesolutions
```

Add:
```
/var/www/digitaledgesolutions/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    sharedscripts
    postrotate
        /bin/kill -HUP `cat /var/run/syslogd.pid 2> /dev/null` 2> /dev/null || true
    endscript
}
```

### 3. Health Check Script
Create `/var/www/digitaledgesolutions/scripts/health-check.sh`:
```bash
#!/bin/bash

# Check if web server is running
if ! systemctl is-active --quiet apache2 && ! systemctl is-active --quiet nginx; then
    echo "$(date): Web server is down!" >> /var/log/des-health.log
    # Send alert email or notification
fi

# Check if real-time server is running
if ! systemctl is-active --quiet des-realtime; then
    echo "$(date): Real-time server is down!" >> /var/log/des-health.log
    sudo systemctl restart des-realtime
fi

# Check database connection
if ! mysql -u des_user -p'your_password' -e "SELECT 1" digitaledgesolutions > /dev/null 2>&1; then
    echo "$(date): Database connection failed!" >> /var/log/des-health.log
fi

# Check disk space
DISK_USAGE=$(df / | tail -1 | awk '{print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 90 ]; then
    echo "$(date): Disk usage is at ${DISK_USAGE}%!" >> /var/log/des-health.log
fi
```

Make executable:
```bash
chmod +x /var/www/digitaledgesolutions/scripts/health-check.sh
```

Add to crontab:
```bash
*/5 * * * * /var/www/digitaledgesolutions/scripts/health-check.sh
```

### 4. Performance Optimization

#### PHP OPcache
Edit `/etc/php/8.2/fpm/php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

#### MySQL Optimization
Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:
```ini
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
query_cache_size = 64M
query_cache_type = 1
max_connections = 200
```

Restart services:
```bash
sudo systemctl restart php8.2-fpm
sudo systemctl restart mysql
```

## Troubleshooting

### Check Logs
```bash
# Apache logs
sudo tail -f /var/log/apache2/digitaledgesolutions-error.log

# Nginx logs
sudo tail -f /var/log/nginx/digitaledgesolutions-error.log

# PHP logs
sudo tail -f /var/log/php8.2-fpm.log

# Application logs
sudo tail -f /var/www/digitaledgesolutions/logs/error.log

# Real-time server logs
sudo journalctl -u des-realtime -f
```

### Common Issues

1. **Permission Denied Errors**
   ```bash
   sudo chown -R www-data:www-data /var/www/digitaledgesolutions
   sudo chmod -R 755 /var/www/digitaledgesolutions
   ```

2. **Database Connection Errors**
   - Check MySQL is running: `sudo systemctl status mysql`
   - Verify credentials in .env file
   - Check MySQL user permissions

3. **Real-time Server Not Working**
   - Check port 3001 is open: `sudo ufw allow 3001`
   - Verify Node.js is installed: `node --version`
   - Check service status: `sudo systemctl status des-realtime`

4. **SSL Certificate Issues**
   - Renew certificate: `sudo certbot renew`
   - Check certificate expiry: `sudo certbot certificates`

## Backup Strategy

### Database Backup
```bash
#!/bin/bash
BACKUP_DIR="/var/backups/digitaledgesolutions"
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u des_user -p'your_password' digitaledgesolutions > $BACKUP_DIR/db_backup_$DATE.sql
gzip $BACKUP_DIR/db_backup_$DATE.sql
# Keep only last 30 backups
ls -t $BACKUP_DIR/db_backup_*.sql.gz | tail -n +31 | xargs rm -f
```

### File Backup
```bash
#!/bin/bash
BACKUP_DIR="/var/backups/digitaledgesolutions"
DATE=$(date +%Y%m%d_%H%M%S)
tar -czf $BACKUP_DIR/files_backup_$DATE.tar.gz /var/www/digitaledgesolutions
# Sync to remote storage (optional)
# rsync -avz $BACKUP_DIR/ user@remote-server:/backups/
```

## Security Checklist

- [ ] Change all default passwords
- [ ] Enable firewall (UFW)
- [ ] Configure fail2ban
- [ ] Disable root SSH login
- [ ] Use SSH key authentication
- [ ] Keep all software updated
- [ ] Enable automatic security updates
- [ ] Configure regular backups
- [ ] Set up monitoring and alerts
- [ ] Review logs regularly
- [ ] Implement rate limiting
- [ ] Use HTTPS only
- [ ] Configure security headers
- [ ] Disable unused services
- [ ] Set up log rotation

---

For support, contact: support@digitaledgesolutions.com
