#!/bin/bash

# =========================================================================================
# 🚀 ddkate Panel - Smart Installation Script
# Description: This script automates the full installation of the ddkate Telegram bot,
# including server dependencies, database setup, project cloning, and configuration.
# Author: ddkate (Parsa Moradi) & Gemini
# Version: 2.0.0
# =========================================================================================


# --- Configuration & Colors ---
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# --- Logo Display ---
show_logo() {
    clear
    echo -e "${YELLOW}"
    echo "=================================================================="
    echo "  _____  _    _  ____          _    _____ _______ _____ "
    echo " |  __ \| |  | |/ __ \   /\   | |  |_   _|__   __/ ____|"
    echo " | |  | | |  | | |  | | /  \  | |    | |    | | | (___  "
    echo " | |  | | |  | | |  | |/ /\ \ | |    | |    | |  \___ \ "
    echo " | |__| | |__| | |__| / ____ \| |____| |    | |  ____) |"
    echo " |_____/ \____/ \____/_/    \_\______|_|    |_| |_____/ "
    echo "=================================================================="
    echo -e "${NC}"
    echo -e "        ${GREEN}A Powerful Bot for Selling VPN Services${NC}"
    echo -e "        ${BLUE}Telegram: @parsamoradi199${NC}"
    echo ""
}

# --- Helper Functions ---
check_root() {
    if [[ $EUID -ne 0 ]]; then
       echo -e "${RED}❌ خطا: این اسکریپت باید با دسترسی root یا sudo اجرا شود.${NC}" 
       exit 1
    fi
}

print_step() {
    echo -e "\n${YELLOW}▶ $1...${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ خطا: $1${NC}"
    exit 1
}

# --- Installation Steps ---

install_dependencies() {
    print_step "به‌روزرسانی پکیج‌های سرور و نصب نیازمندی‌ها"
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y > /dev/null 2>&1 || print_error "آپدیت پکیج‌ها با مشکل مواجه شد."
    apt-get upgrade -y > /dev/null 2>&1
    
    # نصب PHP PPA برای اطمینان از وجود نسخه 8.2
    if ! command -v add-apt-repository &> /dev/null; then
        apt-get install -y software-properties-common > /dev/null 2>&1
    fi
    add-apt-repository -y ppa:ondrej/php > /dev/null 2>&1
    apt-get update -y > /dev/null 2>&1
    
    # نصب بسته‌های اصلی با مدیریت خطا
    apt-get install -y apache2 php8.2 php8.2-{fpm,mysql,curl,mbstring,zip,gd,xml} mariadb-server git unzip composer certbot python3-certbot-apache > /dev/null 2>&1 || print_error "نصب بسته‌های اصلی (Apache, PHP, MariaDB, Composer) با شکست مواجه شد."
    
    print_success "تمام نیازمندی‌های سرور با موفقیت نصب شدند."
}

setup_project() {
    print_step "راه‌اندازی پروژه از گیت‌هاب"
    read -p "لطفاً نام کاربری گیت‌هاب خود را وارد کنید (مثال: Parsa2769): " GITHUB_USER
    read -p "لطفاً نام ریپازیتوری پروژه را وارد کنید (مثال: DDKATE2): " GITHUB_REPO
    
    PROJECT_DIR="/var/www/html/ddkatebot"
    GIT_URL="https://github.com/${GITHUB_USER}/${GITHUB_REPO}.git"
    
    echo "در حال دریافت سورس از: $GIT_URL"
    rm -rf $PROJECT_DIR
    git clone $GIT_URL $PROJECT_DIR > /dev/null 2>&1 || print_error "دانلود پروژه از گیت‌هاب با شکست مواجه شد. لطفاً نام کاربری و ریپازیتوری را بررسی کنید."

    cd $PROJECT_DIR || print_error "ورود به پوشه پروژه ناموفق بود."
    
    echo "در حال نصب وابستگی‌های PHP با Composer..."
    composer install --no-dev --optimize-autoloader > /dev/null 2>&1 || print_error "نصب وابستگی‌های Composer با شکست مواجه شد."

    chown -R www-data:www-data $PROJECT_DIR
    chmod -R 755 $PROJECT_DIR
    
    print_success "پروژه با موفقیت راه‌اندازی شد."
}

setup_database() {
    print_step "پیکربندی دیتابیس"
    systemctl start mariadb
    systemctl enable mariadb
    
    DB_NAME="ddkate_bot_db"
    DB_USER="ddkate_user"
    DB_PASS=$(openssl rand -base64 12)
    
    # ایجاد دیتابیس و کاربر به صورت امن
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;" || print_error "ساخت دیتابیس ناموفق بود."
    mysql -u root -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';" || print_error "ساخت کاربر دیتابیس ناموفق بود."
    mysql -u root -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';" || print_error "اعطای دسترسی‌ها ناموفق بود."
    mysql -u root -e "FLUSH PRIVILEGES;"
    
    # Import کردن ساختار جداول از فایل db_schema.sql
    if [ -f "$PROJECT_DIR/db_schema.sql" ]; then
        mysql $DB_NAME < "$PROJECT_DIR/db_schema.sql" || print_error "ایجاد جداول از فایل db_schema.sql ناموفق بود."
        print_success "جداول دیتابیس با موفقیت ساخته شدند."
    else
        echo -e "${YELLOW}⚠️ هشدار: فایل db_schema.sql یافت نشد. جداول به صورت خودکار ساخته نشدند.${NC}"
    fi

    # ذخیره اطلاعات برای استفاده در مرحله بعد
    export DB_NAME DB_USER DB_PASS
}

setup_webserver() {
    print_step "پیکربندی وب‌سرور و گواهی SSL"
    read -p "لطفاً دامنه یا زیردامنه خود را وارد کنید (مثال: bot.ddkate.com): " DOMAIN
    
    # ساخت فایل کانفیگ آپاچی
    APACHE_CONF="/etc/apache2/sites-available/$DOMAIN.conf"
    cat > $APACHE_CONF <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot $PROJECT_DIR
    <Directory $PROJECT_DIR>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF
    
    a2ensite $DOMAIN.conf > /dev/null 2>&1
    systemctl restart apache2
    
    echo "در حال دریافت گواهی SSL برای دامنه $DOMAIN..."
    certbot --apache -d $DOMAIN --redirect --agree-tos --email webmaster@$DOMAIN --non-interactive || print_error "دریافت گواهی SSL با شکست مواجه شد."
    
    print_success "وب‌سرور و SSL با موفقیت پیکربندی شدند."
    export DOMAIN
}

configure_bot() {
    print_step "پیکربندی نهایی ربات"
    read -p "🔑 توکن ربات خود را از BotFather وارد کنید: " BOT_TOKEN
    read -p "👤 آیدی عددی ادمین اصلی را وارد کنید: " ADMIN_ID
    read -p "🤖 نام کاربری ربات را (بدون @) وارد کنید: " BOT_USERNAME
    
    CONFIG_FILE="$PROJECT_DIR/config.php"
    cat > $CONFIG_FILE <<EOF
<?php
// Main Configuration for ddkate Panel - Generated by install.sh
define('DEBUG_MODE', false); // For production, set to false
define('CHECK_TELEGRAM_IP', true);

// --- Database Configuration ---
define('DB_HOST', 'localhost');
define('DB_NAME', '$DB_NAME');
define('DB_USER', '$DB_USER');
define('DB_PASS', '$DB_PASS');

// --- Telegram Bot Configuration ---
define('BOT_TOKEN', '$BOT_TOKEN');
define('ADMIN_ID', '$ADMIN_ID');
define('BOT_USERNAME', '$BOT_USERNAME');

// --- Webhook and Domain ---
define('WEBHOOK_URL', 'https://$DOMAIN/index.php');

// --- General Settings ---
define('SUPPORT_ID', 'parsamoradi199');
?>
EOF

    # تنظیم وبهوک
    echo "در حال تنظیم وبهوک..."
    WEBHOOK_SET_URL="https://api.telegram.org/bot${BOT_TOKEN}/setWebhook?url=https://${DOMAIN}/index.php"
    response=$(curl -s $WEBHOOK_SET_URL)
    if [[ "$response" != *"Webhook was set"* ]]; then
        echo -e "${YELLOW}⚠️ هشدار: تنظیم وبهوک ممکن است با خطا مواجه شده باشد. پاسخ تلگرام: $response${NC}"
    fi
    
    # ارسال پیام به ادمین
    MESSAGE="✅ ربات ddkate شما با موفقیت نصب و فعال شد! برای شروع دستور /start را ارسال کنید."
    curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/sendMessage" -d chat_id="${ADMIN_ID}" -d text="$MESSAGE" > /dev/null
    
    print_success "فایل config.php ساخته شد و وبهوک تنظیم گردید."
}

# --- Main Execution ---
main() {
    show_logo
    check_root
    install_dependencies
    setup_project
    setup_database
    setup_webserver
    configure_bot

    echo -e "\n\n${GREEN}🎉🎉 تبریک! نصب ربات ddkate با موفقیت به پایان رسید. 🎉🎉${NC}"
    echo "=================================================================="
    echo "اطلاعات مهم شما (لطفاً این اطلاعات را در جای امنی ذخیره کنید):"
    echo -e "🔗 دامنه ربات: ${BLUE}https://$DOMAIN${NC}"
    echo -e "🗃 نام دیتابیس: ${BLUE}$DB_NAME${NC}"
    echo -e "👤 نام کاربری دیتابیس: ${BLUE}$DB_USER${NC}"
    echo -e "🔑 رمز عبور دیتابیس: ${RED}$DB_PASS${NC}"
    echo "=================================================================="
    echo "ربات شما اکنون فعال است. می‌توانید با ارسال دستور /start از آن استفاده کنید."
}

main
