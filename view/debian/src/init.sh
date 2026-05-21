#!/bin/sh

if [ ! "$1" ]; then
    echo '缺少8ID参数，例如: /run/init.sh 80000888';
    exit 1;
fi

cp /opt/local/log4php_config.xml /opt/tk/log4php_config.xml
cp /opt/local/redisconfig /opt/tk/redisconfig

corePath=/opt/tk
user8ID=${1}

if [ ! "$(ls -A $corePath)" ]; then
    echo "${corePath}目录不存在，请确认是否已经映射代码到容器中";
    exit 1;
fi

cd ${corePath} || exit

# tke_config.php
configCode='$sPreUrl = substr($_SERVER["HTTP_HOST"], 0, strpos($_SERVER["HTTP_HOST"], "."));\n    $sPreUrl = $sPreUrl ?: "hk";\n    require_once(BASE_DIR . "\/..\/sites\/" . $sPreUrl . "\/config.php");'
sed -i "s/require_once(BASE_DIR . \"\/config.php\");/${configCode}/g" ${corePath}/sys/includes/tke_config.php
sed -i '$s/?>/''/' sys/includes/tke_config.php
printf "\033[32m Updated sys/includes/tke_config.php \033[0m\n"

# http.lib
rm sys/libs/http.lib
echo "<?php include('./ngcore/http.lib');" >> sys/libs/http.lib
printf "\033[32m Updated sys/libs/http.lib \033[0m\n"

# login.php
loginCode="\    // Use custom 8id automatic login\n    \$user = \$db->get(\"SELECT id FROM \`user\` WHERE \`ActiveDirectoryID\` = '${user8ID}' LIMIT 1\");\n    if (empty(\$user))die('Incorrect 8ID.');userLogin(\$user->id);\n    die();"
sed -i "/UserInformationService as UserInformationService;/a\ \n${loginCode}" web/login.php
sed -i 's/use SystemAdmin\\User\\Service\\UserInformationService as UserInformationService;/use SystemAdmin\\User\\Service\\UserInformationService as UserInformationService ;/' web/login.php
printf "\033[32m Updated web/login.php \033[0m\n"

# multilingual.lib
sed -i.bak 's#function _xlate($source,$moduleid = null,$dbx = null) {#function _xlate($source,$moduleid = null,$dbx = null) {return $source;#' sys/libs/multilingual.lib
printf "\033[32m Updated sys/libs/multilingual.lib \033[0m\n"

sed -i "s/'options' => \$redisSentinel/\/\/ 'options' => \$redisSentinel/" vivid/config/10.x/database.php
sed -i "s/'default' => \$redisSentinel/\/\/ 'default' => \$redisSentinel/" vivid/config/10.x/database.php
sed -i "s/'cache' => \$redisSentinel/\/\/ 'cache' => \$redisSentinel/" vivid/config/10.x/database.php
printf "\033[32m Updated vivid/config/10.x/database.php \033[0m\n"

sed -i "s/'options' => array_merge/\/\/ 'options' => array_merge/" vivid/config/12.x/database.php
sed -i "s/'default' => \$redisSentinel/\/\/ 'default' => \$redisSentinel/" vivid/config/12.x/database.php
sed -i "s/'cache' => \$redisSentinel/\/\/ 'cache' => \$redisSentinel/" vivid/config/12.x/database.php
printf "\033[32m Updated vivid/config/12.x/database.php \033[0m\n"

# Handler.php
sed -i "s/function render(/function renderBackup(/g" vivid/app/Exceptions/Handler.php
printf "\033[32m Updated vivid/app/Exceptions/Handler.php \033[0m\n"

printf "\033[32m 初始化操作完成 \033[0m\n"
printf "\033[33m 警告，以上文件仅用于本地开发，请勿提交到Git仓库!\033[0m\n"
