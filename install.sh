برای تغییر نام نسخه از "Mirza Bot" به "DDKate" و ساخت نسخه‌ی جدید، باید تمام ارجاعات به "Mirza" یا "mirzabot" را در اسکریپت با "DDKate" یا "ddkate" جایگزین کنیم. این شامل نام‌های فایل‌ها، مسیرها، متغیرها، پیام‌ها و موارد دیگر است. در ادامه، من مراحل انجام این کار را توضیح می‌دهم و یک نسخه‌ی اصلاح‌شده از اسکریپت ارائه می‌کنم.

### **مراحل انجام تغییرات**
1. **جایگزینی نام‌ها**:
   - تمام ارجاعات به "Mirza" یا "mirzabot" با "DDKate" یا "ddkate" (بسته به مورد) جایگزین می‌شود.
   - مسیرهای پیش‌فرض مانند `/var/www/html/mirzabotconfig` به `/var/www/html/ddkateconfig` تغییر می‌کند.
   - نام‌های دیتابیس، فایل‌های تنظیمات و پیام‌های خروجی به‌روزرسانی می‌شوند.
2. **حفظ ساختار و عملکرد**:
   - اطمینان از اینکه عملکرد اسکریپت (نصب، به‌روزرسانی، حذف و غیره) بدون تغییر باقی می‌ماند.
   - بررسی متغیرها و مسیرهای جدید برای جلوگیری از خطا.
3. **به‌روزرسانی لینک‌های GitHub**:
   - لینک مخزن GitHub به `https://github.com/mahdiMGF2/ddkatepanel` تغییر می‌کند (با فرض اینکه شما یک مخزن جدید برای DDKate ایجاد کرده‌اید).
4. **ایجاد فایل جدید**:
   - اسکریپت اصلاح‌شده با نام `ddkate_install.sh` ذخیره می‌شود.

### **توضیحات مهم**
- فرض می‌کنم که شما یک مخزن جدید به نام `ddkatepanel` در GitHub دارید یا قصد دارید از همان مخزن `botmirzapanel` استفاده کنید. اگر مخزن جدیدی دارید، لطفاً آدرس دقیق آن را تأیید کنید.
- متغیر `$RANDOM_NUMBER` که در اسکریپت اصلی استفاده شده بود، به دلیل تعریف نشدن می‌تواند خطا ایجاد کند. این مشکل در نسخه‌ی اصلاح‌شده برطرف خواهد شد.
- برای ساده‌سازی، فقط تغییرات مربوط به نام‌گذاری و مسیرها اعمال می‌شود، و سایر عملکردها دست‌نخورده باقی می‌مانند.

### **اسکریپت اصلاح‌شده**
در زیر نسخه‌ی اصلاح‌شده اسکریپت با نام "DDKate" ارائه شده است. به دلیل محدودیت فضا، بخش‌های اصلی که تغییر کرده‌اند یا نیاز به توجه دارند، به‌صورت خلاصه نشان داده می‌شوند، و بخش‌های تکراری که فقط نام تغییر کرده است، فشرده شده‌اند. فایل کامل را می‌توانید از طریق لینکی که در انتها ارائه می‌شود، دریافت کنید.

```bash
#!/bin/bash

# Checking Root Access
if [[ $EUID -ne 0 ]]; then
    echo -e "\033[31m[ERROR]\033[0m Please run this script as \033[1mroot\033[0m."
    exit 1
fi

# Display Logo
function show_logo() {
    clear
    echo -e "\033[1;34m"
    echo "========================================"
    echo "           DDKATE INSTALL SCRIPT        "
    echo "========================================"
    echo -e "\033[0m"
    echo ""
}

# Display Menu
function show_menu() {
    show_logo
    echo -e "\033[1;36m1)\033[0m Install DDKate Bot"
    echo -e "\033[1;36m2)\033[0m Update DDKate Bot"
    echo -e "\033[1;36m3)\033[0m Remove DDKate Bot"
    echo -e "\033[1;36m4)\033[0m Export Database"
    echo -e "\033[1;36m5)\033[0m Import Database"
    echo -e "\033[1;36m6)\033[0m Configure Automated Backup"
    echo -e "\033[1;36m7)\033[0m Renew SSL Certificates"
    echo -e "\033[1;36m8)\033[0m Change Domain"
    echo -e "\033[1;36m9)\033[0m Additional Bot Management"
    echo -e "\033[1;36m10)\033[0m Exit"
    echo ""
    read -p "Select an option [1-10]: " option
    case $option in
        1) install_bot ;;
        2) update_bot ;;
        3) remove_bot ;;
        4) export_database ;;
        5) import_database ;;
        6) auto_backup ;;
        7) renew_ssl ;;
        8) change_domain ;;
        9) manage_additional_bots ;;
        10)
            echo -e "\033[32mExiting...\033[0m"
            exit 0
            ;;
        *)
            echo -e "\033[31mInvalid option. Please try again.\033[0m"
            show_menu
            ;;
    esac
}

# Install Function
function install_bot() {
    echo -e "\e[32mInstalling DDKate script ... \033[0m\n"

    # Function to add the Ondřej Surý PPA for PHP
    add_php_ppa() {
        sudo add-apt-repository -y ppa:ondrej/php || {
            echo -e "\e[91mError: Failed to add PPA ondrej/php.\033[0m"
            return 1
        }
    }

    # Function to add the Ondřej Surý PPA for PHP with locale override
    add_php_ppa_with_locale() {
        sudo LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php || {
            echo -e "\e[91mError: Failed to add PPA ondrej/php with locale override.\033[0m"
            return 1
        }
    }

    # Try adding the PPA with the system's default locale settings
    if ! add_php_ppa; then
        echo "Failed to add PPA with default locale, retrying with locale override..."
        if ! add_php_ppa_with_locale; then
            echo "Failed to add PPA even with locale override. Exiting..."
            exit 1
        }
    fi

    sudo apt update && sudo apt upgrade -y || {
        echo -e "\e[91mError: Failed to update and upgrade packages.\033[0m"
        exit 1
    }
    echo -e "\e[92mThe server was successfully updated ...\033[0m\n"

    sudo apt install -y git unzip curl || {
        echo -e "\e[91mError: Failed to install required packages.\033[0m"
        exit 1
    }

    DEBIAN_FRONTEND=noninteractive sudo apt install -y php8.2 php8.2-fpm php8.2-mysql || {
        echo -e "\e[91mError: Failed to install PHP 8.2 and related packages.\033[0m"
        exit 1
    }

    # List of required packages
    PKG=(
        lamp-server^
        libapache2-mod-php
        mysql-server
        apache2
        php-mbstring
        php-zip
        php-gd
        php-json
        php-curl
    )

    # Installing required packages with error handling
    for i in "${PKG[@]}"; do
        dpkg -s $i &>/dev/null
        if [ $? -eq 0 ]; then
            echo "$i is already installed"
        else
            if ! DEBIAN_FRONTEND=noninteractive sudo apt install -y $i; then
                echo -e "\e[91mError installing $i. Exiting...\033[0m"
                exit 1
            fi
        fi
    done

    echo -e "\n\e[92mPackages Installed, Continuing ...\033[0m\n"

    # phpMyAdmin Configuration
    echo 'phpmyadmin phpmyadmin/dbconfig-install boolean true' | sudo debconf-set-selections
    echo 'phpmyadmin phpmyadmin/app-password-confirm password ddkatepass' | sudo debconf-set-selections
    echo 'phpmyadmin phpmyadmin/mysql/admin-pass password ddkatepass' | sudo debconf-set-selections
    echo 'phpmyadmin phpmyadmin/mysql/app-pass password ddkatepass' | sudo debconf-set-selections
    echo 'phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2' | sudo debconf-set-selections

    sudo apt-get install phpmyadmin -y || {
        echo -e "\e[91mError: Failed to install phpMyAdmin.\033[0m"
        exit 1
    }

    # Check and remove existing phpMyAdmin configuration
    if [ -f /etc/apache2/conf-available/phpmyadmin.conf ]; then
        sudo rm -f /etc/apache2/conf-available/phpmyadmin.conf && echo -e "\e[92mRemoved existing phpMyAdmin configuration.\033[0m"
    fi

    # Create symbolic link for phpMyAdmin configuration
    sudo ln -s /etc/phpmyadmin/apache.conf /etc/apache2/conf-available/phpmyadmin.conf || {
        echo -e "\e[91mError: Failed to create symbolic link for phpMyAdmin configuration.\033[0m"
        exit 1
    }

    sudo a2enconf phpmyadmin.conf || {
        echo -e "\e[91mError: Failed to enable phpMyAdmin configuration.\033[0m"
        exit 1
    }
    sudo systemctl restart apache2 || {
        echo -e "\e[91mError: Failed to restart Apache2 service.\033[0m"
        exit 1
    }

    # Additional package installations with error handling
    sudo apt-get install -y php-soap || {
        echo -e "\e[91mError: Failed to install php-soap.\033[0m"
        exit 1
    }

    sudo apt-get install libapache2-mod-php || {
        echo -e "\e[91mError: Failed to install libapache2-mod-php.\033[0m"
        exit 1
    }

    sudo systemctl enable mysql.service || {
        echo -e "\e[91mError: Failed to enable MySQL service.\033[0m"
        exit 1
    }
    sudo systemctl start mysql.service || {
        echo -e "\e[91mError: Failed to start MySQL service.\033[0m"
        exit 1
    }
    sudo systemctl enable apache2 || {
        echo -e "\e[91mError: Failed to enable Apache2 service.\033[0m"
        exit 1
    }
    sudo systemctl start apache2 || {
        echo -e "\e[91mError: Failed to start Apache2 service.\033[0m"
        exit 1
    }

    sudo apt-get install ufw -y || {
        echo -e "\e[91mError: Failed to install UFW.\033[0m"
        exit 1
    }
    ufw allow 'Apache' || {
        echo -e "\e[91mError: Failed to allow Apache in UFW.\033[0m"
        exit 1
    }
    sudo systemctl restart apache2 || {
        echo -e "\e[91mError: Failed to restart Apache2 service after UFW update.\033[0m"
        exit 1
    }

    sudo apt-get install -y git || {
        echo -e "\e[91mError: Failed to install Git.\033[0m"
        exit 1
    }
    sudo apt-get install -y wget || {
        echo -e "\e[91mError: Failed to install Wget.\033[0m"
        exit 1
    }
    sudo apt-get install -y unzip || {
        echo -e "\e[91mError: Failed to install Unzip.\033[0m"
        exit 1
    }
    sudo apt install curl -y || {
        echo -e "\e[91mError: Failed to install cURL.\033[0m"
        exit 1
    }
    sudo apt-get install -y php-ssh2 || {
        echo -e "\e[91mError: Failed to install php-ssh2.\033[0m"
        exit 1
    }
    sudo apt-get install -y libssh2-1-dev libssh2-1 || {
        echo -e "\e[91mError: Failed to install libssh2.\033[0m"
        exit 1
    }
    sudo apt install jq -y || {
        echo -e "\e[91mError: Failed to install jq.\033[0m"
        exit 1
    }

    sudo systemctl restart apache2.service || {
        echo -e "\e[91mError: Failed to restart Apache2 service.\033[0m"
        exit 1
    }

    # Check and remove existing directory before cloning Git repository
    BOT_DIR="/var/www/html/ddkateconfig"
    if [ -d "$BOT_DIR" ]; then
        echo -e "\e[93mDirectory $BOT_DIR already exists. Removing...\033[0m"
        sudo rm -rf "$BOT_DIR" || {
            echo -e "\e[91mError: Failed to remove existing directory $BOT_DIR.\033[0m"
            exit 1
        }
    fi

    # Create bot directory
    sudo mkdir -p "$BOT_DIR"
    if [ ! -d "$BOT_DIR" ]; then
        echo -e "\e[91mError: Failed to create directory $BOT_DIR.\033[0m"
        exit 1
    fi

    # Default to latest release
    ZIP_URL=$(curl -s https://api.github.com/repos/mahdiMGF2/ddkatepanel/releases/latest | grep "zipball_url" | cut -d '"' -f 4)

    # Check for version flag
    if [[ "$1" == "-v" && "$2" == "beta" ]] || [[ "$1" == "-beta" ]] || [[ "$1" == "-" && "$2" == "beta" ]]; then
        ZIP_URL="https://github.com/mahdiMGF2/ddkatepanel/archive/refs/heads/main.zip"
    elif [[ "$1" == "-v" && -n "$2" ]]; then
        ZIP_URL="https://github.com/mahdiMGF2/ddkatepanel/archive/refs/tags/$2.zip"
    fi

    # Download and extract the repository
    TEMP_DIR="/tmp/ddkate"
    mkdir -p "$TEMP_DIR"
    wget -O "$TEMP_DIR/bot.zip" "$ZIP_URL" || {
        echo -e "\e[91mError: Failed to download the specified version.\033[0m"
        exit 1
    }

    unzip "$TEMP_DIR/bot.zip" -d "$TEMP_DIR"
    EXTRACTED_DIR=$(find "$TEMP_DIR" -mindepth 1 -maxdepth 1 -type d)
    mv "$EXTRACTED_DIR"/* "$BOT_DIR" || {
        echo -e "\e[91mError: Failed to move extracted files.\033[0m"
        exit 1
    }
    rm -rf "$TEMP_DIR"

    sudo chown -R www-data:www-data "$BOT_DIR"
    sudo chmod -R 755 "$BOT_DIR"

    echo -e "\n\033[33mDDKate config and script have been installed successfully.\033[0m"

    wait
    if [ ! -d "/root/confddkate" ]; then
        sudo mkdir /root/confddkate || {
            echo -e "\e[91mError: Failed to create /root/confddkate directory.\033[0m"
            exit 1
        }

        sleep 1

        touch /root/confddkate/dbrootddkate.txt || {
            echo -e "\e[91mError: Failed to create dbrootddkate.txt.\033[0m"
            exit 1
        }
        sudo chmod -R 777 /root/confddkate/dbrootddkate.txt || {
            echo -e "\e[91mError: Failed to set permissions for dbrootddkate.txt.\033[0m"
            exit 1
        }
        sleep 1

        randomdbpasstxt=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)

        ASAS="$"

        echo "${ASAS}user = 'root';" >> /root/confddkate/dbrootddkate.txt
        echo "${ASAS}pass = '${randomdbpasstxt}';" >> /root/confddkate/dbrootddkate.txt
        echo "${ASAS}path = '/var/www/html/ddkateconfig';" >> /root/confddkate/dbrootddkate.txt

        sleep 1

        passs=$(cat /root/confddkate/dbrootddkate.txt | grep '$pass' | cut -d"'" -f2)
        userrr=$(cat /root/confddkate/dbrootddkate.txt | grep '$user' | cut -d"'" -f2)

        sudo mysql -u $userrr -p$passs -e "alter user '$userrr'@'localhost' identified with mysql_native_password by '$passs';FLUSH PRIVILEGES;" || {
            echo -e "\e[91mError: Failed to alter MySQL user. Attempting recovery...\033[0m"

            # Enable skip-grant-tables at the end of the file
            sudo sed -i '$ a skip-grant-tables' /etc/mysql/mysql.conf.d/mysqld.cnf
            sudo systemctl restart mysql

            # Access MySQL to reset the root user
            sudo mysql <<EOF
DROP USER IF EXISTS 'root'@'localhost';
CREATE USER 'root'@'localhost' IDENTIFIED BY '${passs}';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF

            # Disable skip-grant-tables
            sudo sed -i '/skip-grant-tables/d' /etc/mysql/mysql.conf.d/mysqld.cnf
            sudo systemctl restart mysql

            # Retry MySQL login with the new credentials
            echo "SELECT 1" | mysql -u$userrr -p$passs 2>/dev/null || {
                echo -e "\e[91mError: Recovery failed. MySQL login still not working.\033[0m"
                exit 1
            }
        }

        echo "Folder created successfully!"
    else
        echo "Folder already exists."
    fi

    clear

    echo " "
    echo -e "\e[32m SSL \033[0m\n"

    read -p "Enter the domain: " domainname
    while [[ ! "$domainname" =~ ^[a-zA-Z0-9.-]+$ ]]; do
        echo -e "\e[91mInvalid domain format. Please try again.\033[0m"
        read -p "Enter the domain: " domainname
    done
    DOMAIN_NAME="$domainname"
    PATHS=$(cat /root/confddkate/dbrootddkate.txt | grep '$path' | cut -d"'" -f2)
    sudo ufw allow 80 || {
        echo -e "\e[91mError: Failed to allow port 80 in UFW.\033[0m"
        exit 1
    }
    sudo ufw allow 443 || {
        echo -e "\e[91mError: Failed to allow port 443 in UFW.\033[0m"
        exit 1
    }

    echo -e "\033[33mDisable apache2\033[0m"
    wait

    sudo systemctl stop apache2 || {
        echo -e "\e[91mError: Failed to stop Apache2.\033[0m"
        exit 1
    }
    sudo systemctl disable apache2 || {
        echo -e "\e[91mError: Failed to disable Apache2.\033[0m"
        exit 1
    }
    sudo apt install letsencrypt -y || {
        echo -e "\e[91mError: Failed to install letsencrypt.\033[0m"
        exit 1
    }
    sudo systemctl enable certbot.timer || {
        echo -e "\e[91mError: Failed to enable certbot timer.\033[0m"
        exit 1
    }
    sudo certbot certonly --standalone --agree-tos --preferred-challenges http -d $DOMAIN_NAME || {
        echo -e "\e[91mError: Failed to generate SSL certificate.\033[0m"
        exit 1
    }
    sudo apt install python3-certbot-apache -y || {
        echo -e "\e[91mError: Failed to install python3-certbot-apache.\033[0m"
        exit 1
    }
    sudo certbot --apache --agree-tos --preferred-challenges http -d $DOMAIN_NAME || {
        echo -e "\e[91mError: Failed to configure SSL with Certbot.\033[0m"
        exit 1
    }

    echo " "
    echo -e "\033[33mEnable apache2\033[0m"
    wait
    sudo systemctl enable apache2 || {
        echo -e "\e[91mError: Failed to enable Apache2.\033[0m"
        exit 1
    }
    sudo systemctl start apache2 || {
        echo -e "\e[91mError: Failed to start Apache2.\033[0m"
        exit 1
    }
    clear

    printf "\e[33m[+] \e[36mBot Token: \033[0m"
    read YOUR_BOT_TOKEN
    while [[ ! "$YOUR_BOT_TOKEN" =~ ^[0-9]{8,10}:[a-zA-Z0-9_-]{35}$ ]]; do
        echo -e "\e[91mInvalid bot token format. Please try again.\033[0m"
        printf "\e[33m[+] \e[36mBot Token: \033[0m"
        read YOUR_BOT_TOKEN
    done

    printf "\e[33m[+] \e[36mChat id: \033[0m"
    read YOUR_CHAT_ID
    while [[ ! "$YOUR_CHAT_ID" =~ ^-?[0-9]+$ ]]; do
        echo -e "\e[91mInvalid chat ID format. Please try again.\033[0m"
        printf "\e[33m[+] \e[36mChat id: \033[0m"
        read YOUR_CHAT_ID
    done

    YOUR_DOMAIN="$DOMAIN_NAME"

    while true; do
        printf "\e[33m[+] \e[36musernamebot: \033[0m"
        read YOUR_BOTNAME
        if [ "$YOUR_BOTNAME" != "" ]; then
            break
        else
            echo -e "\e[91mError: Bot username cannot be empty. Please enter a valid username.\033[0m"
        fi
    done

    ROOT_PASSWORD=$(cat /root/confddkate/dbrootddkate.txt | grep '$pass' | cut -d"'" -f2)
    ROOT_USER="root"
    echo "SELECT 1" | mysql -u$ROOT_USER -p$ROOT_PASSWORD 2>/dev/null || {
        echo -e "\e[91mError: MySQL connection failed.\033[0m"
        exit 1
    }

    if [ $? -eq 0 ]; then
        wait

        randomdbpass=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)
        randomdbdb=$(openssl rand -base64 10 | tr -dc 'a-zA-Z' | cut -c1-8)

        if [[ $(mysql -u root -p$ROOT_PASSWORD -e "SHOW DATABASES LIKE 'ddkate'") ]]; then
            clear
            echo -e "\n\e[91mYou have already created the database\033[0m\n"
        else
            dbname=ddkate
            clear
            echo -e "\n\e[32mPlease enter the database username!\033[0m"
            printf "[+] Default user name is \e[91m${randomdbdb}\e[0m ( let it blank to use this user name ): "
            read dbuser
            if [ "$dbuser" = "" ]; then
                dbuser=$randomdbdb
            fi

            echo -e "\n\e[32mPlease enter the database password!\033[0m"
            printf "[+] Default password is \e[91m${randomdbpass}\e[0m ( let it blank to use this password ): "
            read dbpass
            if [ "$dbpass" = "" ]; then
                dbpass=$randomdbpass
            fi

            mysql -u root -p$ROOT_PASSWORD -e "CREATE DATABASE $dbname;" -e "CREATE USER '$dbuser'@'%' IDENTIFIED WITH mysql_native_password BY '$dbpass';GRANT ALL PRIVILEGES ON * . * TO '$dbuser'@'%';FLUSH PRIVILEGES;" -e "CREATE USER '$dbuser'@'localhost' IDENTIFIED WITH mysql_native_password BY '$dbpass';GRANT ALL PRIVILEGES ON * . * TO '$dbuser'@'localhost';FLUSH PRIVILEGES;" || {
                echo -e "\e[91mError: Failed to create database or user.\033[0m"
                exit 1
            }

            echo -e "\n\e[95mDatabase Created.\033[0m"

            clear

            ASAS="$"

            wait

            sleep 1

            file_path="/var/www/html/ddkateconfig/config.php"

            if [ -f "$file_path" ]; then
                rm "$file_path" || {
                    echo -e "\e[91mError: Failed to delete old config.php.\033[0m"
                    exit 1
                }
                echo -e "File deleted successfully."
            else
                echo -e "File not found."
            fi

            sleep 1

            secrettoken=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)

            echo -e "<?php" >> /var/www/html/ddkateconfig/config.php
            echo -e "${ASAS}APIKEY = '${YOUR_BOT_TOKEN}';" >> /var/www/html/ddkateconfig/config.php
            echo -e "${ASAS}usernamedb = '${dbuser}';" >> /var/www/html/ddkateconfig/config.php
            echo -e "${ASAS}passworddb = '${dbpass}';" >> /var/www/html/ddkateconfig/config.php
            echo -e "${ASAS}dbname = '${dbname}';" >> /var/www/html/ddkateconfig/config.php
            echo -e "${ASAS}domainhosts = '${YOUR_DOMAIN}/ddkateconfig';" >> /var/www/html/ddkateconfig/config.php
            echo -e "${ASAS}adminnumber = '${YOUR_CHAT_ID}';" >> /var/www/html/ddkateconfig/config.php
            echo -e "${ASAS}usernamebot = '${YOUR_BOTNAME}';" >> /var/www/html/ddkateconfig/config.php
            echo -e "${ASAS}secrettoken = '${secrettoken}';" >> /var/www/html/ddkateconfig/config.php
            echo -e "${ASAS}connect = mysqli_connect('localhost', \$usernamedb, \$passworddb, \$dbname);" >> /var/www/html/ddkateconfig/config.php
            echo -e "if (${ASAS}connect->connect_error) {" >> /var/www/html/ddkateconfig/config.php
            echo -e "die(' The connection to the database failed:' . ${ASAS}connect->connect_error);" >> /var/www/html/ddkateconfig/config.php
            echo -e "}" >> /var/www/html/ddkateconfig/config.php
            echo -e "mysqli_set_charset(${ASAS}connect, 'utf8mb4');" >> /var/www/html/ddkateconfig/config.php
            text_to_save=$(cat <<EOF
\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
\$dsn = "mysql:host=localhost;dbname=${ASAS}dbname;charset=utf8mb4";
try {
     \$pdo = new PDO(\$dsn, \$usernamedb, \$passworddb, \$options);
} catch (\PDOException \$e) {
     throw new \PDOException(\$e->getMessage(), (int)\$e->getCode());
}
EOF
)
            echo -e "$text_to_save" >> /var/www/html/ddkateconfig/config.php
            echo -e "?>" >> /var/www/html/ddkateconfig/config.php

            sleep 1

            curl -F "url=https://${YOUR_DOMAIN}/ddkateconfig/index.php" \
                 -F "secret_token=${secrettoken}" \
                 "https://api.telegram.org/bot${YOUR_BOT_TOKEN}/setWebhook" || {
                echo -e "\e[91mError: Failed to set webhook for bot.\033[0m"
                exit 1
            }
            MESSAGE="✅ The bot is installed! for start the bot send /start command."
            curl -s -X POST "https://api.telegram.org/bot${YOUR_BOT_TOKEN}/sendMessage" -d chat_id="${YOUR_CHAT_ID}" -d text="$MESSAGE" || {
                echo -e "\e[91mError: Failed to send message to Telegram.\033[0m"
                exit 1
            }

            sleep 1
            sudo systemctl start apache2 || {
                echo -e "\e[91mError: Failed to start Apache2.\033[0m"
                exit 1
            }
            url="https://${YOUR_DOMAIN}/ddkateconfig/table.php"
            curl $url || {
                echo -e "\e[91mError: Failed to fetch URL from domain.\033[0m"
                exit 1
            }

            clear

            echo " "

            echo -e "\e[102mDomain Bot: https://${YOUR_DOMAIN}\033[0m"
            echo -e "\e[104mDatabase address: https://${YOUR_DOMAIN}/phpmyadmin\033[0m"
            echo -e "\e[33mDatabase name: \e[36m${dbname}\033[0m"
            echo -e "\e[33mDatabase username: \e[36m${dbuser}\033[0m"
            echo -e "\e[33mDatabase password: \e[36m${dbpass}\033[0m"
            echo " "
            echo -e "DDKate Bot"
        fi

    elif [ "$ROOT_PASSWORD" = "" ] || [ "$ROOT_USER" = "" ]; then
        echo -e "\n\e[36mThe password is empty.\033[0m\n"
    else
        echo -e "\n\e[36mThe password is not correct.\033[0m\n"
    fi
}

# Update Function
function update_bot() {
    echo "Updating DDKate Bot..."

    # Update server packages
    if ! sudo apt update && sudo apt upgrade -y; then
        echo -e "\e[91mError updating the server. Exiting...\033[0m"
        exit 1
    fi
    echo -e "\e[92mServer packages updated successfully...\033[0m\n"

    # Check if bot is already installed
    BOT_DIR="/var/www/html/ddkateconfig"
    if [ ! -d "$BOT_DIR" ]; then
        echo -e "\e[91mError: DDKate Bot is not installed. Please install it first.\033[0m"
        exit 1
    fi

    # Fetch latest release from GitHub
    if [[ "$1" == "-beta" ]] || [[ "$1" == "-v" && "$2" == "beta" ]]; then
        ZIP_URL="https://github.com/mahdiMGF2/ddkatepanel/archive/refs/heads/main.zip"
    else
        ZIP_URL=$(curl -s https://api.github.com/repos/mahdiMGF2/ddkatepanel/releases/latest | grep "zipball_url" | cut -d '"' -f4)
    fi

    # Create temporary directory
    TEMP_DIR="/tmp/ddkate_update"
    mkdir -p "$TEMP_DIR"

    # Download and extract
    wget -O "$TEMP_DIR/bot.zip" "$ZIP_URL" || {
        echo -e "\e[91mError: Failed to download update package.\033[0m"
        exit 1
    }
    unzip "$TEMP_DIR/bot.zip" -d "$TEMP_DIR"

    # Find extracted directory
    EXTRACTED_DIR=$(find "$TEMP_DIR" -mindepth 1 -maxdepth 1 -type d)

    # Backup config file
    CONFIG_PATH="/var/www/html/ddkateconfig/config.php"
    TEMP_CONFIG="/root/ddkate_config_backup.php"
    if [ -f "$CONFIG_PATH" ]; then
        cp "$CONFIG_PATH" "$TEMP_CONFIG" || {
            echo -e "\e[91mConfig file backup failed!\033[0m"
            exit 1
        }
    fi

    # Remove old version
    sudo rm -rf /var/www/html/ddkateconfig || {
        echo -e "\e[91mFailed to remove old bot files!\033[0m"
        exit 1
    }

    # Move new files
    sudo mkdir -p /var/www/html/ddkateconfig
    sudo mv "$EXTRACTED_DIR"/* /var/www/html/ddkateconfig/ || {
        echo -e "\e[91mFile transfer failed!\033[0m"
        exit 1
    }

    # Restore config file
    if [ -f "$TEMP_CONFIG" ]; then
        sudo mv "$TEMP_CONFIG" "$CONFIG_PATH" || {
            echo -e "\e[91mConfig file restore failed!\033[0m"
            exit 1
        }
    fi

    # Set permissions
    sudo chown -R www-data:www-data /var/www/html/ddkateconfig/
    sudo chmod -R 755 /var/www/html/ddkateconfig/

    # Run setup script
    URL=$(grep '\$domainhosts' "$CONFIG_PATH" | cut -d"'" -f2)
    curl -s "https://$URL/table.php" || {
        echo -e "\e[91mSetup script execution failed!\033[0m"
    }

    # Cleanup
    rm -rf "$TEMP_DIR"

    echo -e "\n–

System: * Today's date and time is 07:20 PM EDT on Sunday, June 29, 2025.
