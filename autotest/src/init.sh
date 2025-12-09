#!/bin/bash
sed -i "9c define('TK_PATH', '/opt/tk');" /opt/autotest/phpunit/library/BaseBootstrap.php
sed -i "23c \$_SERVER['DOCUMENT_ROOT'] = '/opt/tk/web';" /opt/autotest/phpunit/library/BaseBootstrap.php
echo "success!"