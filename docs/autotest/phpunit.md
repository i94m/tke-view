# PHPUnit

> [PHPUnit](https://phpunit.de/) 是一个面向PHP程序员的测试框架，这是一个xUnit的体系结构的单元测试框架。

## 准备工作

1.修改 `dev2` 代码的路径。

2.执行初始化脚本。

```shell
docker exec -it autotest /run/init.sh
```
::: warning 注意：
上面的脚本会修改底层文件 `autotest/phpunit/library/BaseBootstrap.php`，切勿提交到SVN!
:::

## 测试命令

1.进入容器
```shell
docker exec -it -w /opt/autotest/phpunit autotest sh
```

2.执行测试命令

以 `sharp/phpunit.xml` 配置为例：
```shell
phpunit --colors=auto -c sharp/phpunit.xml --filter "ExampleTest"
```
