# 本地环境 <Badge type="tip" text="php8.2" />

## 使用说明

::: tip 提示：
当前环境使用了 `apache` + `php-fpm` 的方式，同时每个容器内仅支持运行单个环境的代码，如果需要同时跑多个环境的代码，可以创建不同的容器！
:::

容器中的目录说明：
| 用户名 | 用户目录 |
|------------|-----------------------|
| /opt/sites | 国家配置文件，例如global,hk |
| /opt/tk | View代码目录 |

容器日志默认输出到 stdout / stderr，可直接使用：
```shell
docker logs -f local
```

## 创建容器

### 使用Docker run命令

运行local代码
```shell
docker run -d --name local --restart always -v D:/tke/local:/opt/tk -v D:/tke/sites:/opt/sites -p 80:80 registry.cn-hangzhou.aliyuncs.com/tke-view/view:php8.2
```

:::warning 提示
如果使用的是windows环境，在开始配置环境前，建议设置项目目录为 区分大小写 模式，参考：[https://learn.microsoft.com/zh-cn/windows/wsl/case-sensitivity](https://learn.microsoft.com/zh-cn/windows/wsl/case-sensitivity)。

如果使用的是 [WSL](https://learn.microsoft.com/zh-cn/windows/wsl/) 环境，应该把代码放到linux系统中，同时使用 linux 的项目路径如：`/var/tke/dev`。参考: [Docker Desktop WSL 2 backend on Windows](https://docs.docker.com/desktop/windows/wsl/)
:::

尝试访问：[http://localhost](http://localhost)

::: details 运行Dev/Dev2/RC等环境（可选）
不同环境分配不同的端口号即可
```shell
docker run -d --name dev --restart always -v D:/tke/dev:/opt/tk -v D:/tke/sites:/opt/sites -p 8001:80 registry.cn-hangzhou.aliyuncs.com/tke-view/view:php8.2
```

尝试访问：[http://localhost:8001](http://localhost:8001)
:::

### 使用Docker Compose命令

1.在本地创建一个名为 `docker-compose.yml` 的文件，并复制粘贴以下内容。

```yaml{28,35}
services:
  local:
    image: registry.cn-hangzhou.aliyuncs.com/tke-view/view:php8.2
    container_name: local
    volumes:
      - sites:/opt/sites
      - local:/opt/tk
    networks:
      tke:
        ipv4_address: 172.16.1.80
    ports:
      - "80:80"
    restart: always
networks:
  tke:
    name: tke
    ipam:
      driver: default
      config:
        - subnet: 172.16.1.0/24
volumes:
  sites:
    name: sites
    driver: local
    driver_opts:
      type: none
      o: bind
      device: site站点路径如：D:/tke/sites
  local:
    name: local
    driver: local
    driver_opts:
      type: none
      o: bind
      device: dev代码路径如：D:/tke/local
```
以上配置仅包含 local 环境的容器。完整配置请参考：[View Docker Compose](/compose)

2.打开终端工具，并切换到 `docker-compose.yml` 文件所在的目录。例如：
```shell
cd ~/Desktop/
```

3.创建并启动服务（`-d`参数可以让服务在后台运行）。
```shell
docker-compose -p tke up -d
```

4.验证服务是否创建成功。

访问本地站点: [http://localhost](http://localhost)

::: tip 提示：
如果运行失败，请检查本机的80端口是否被占用。
:::

## 配置站点

打开本机的 hosts 配置文件，并复制粘贴以下内容。
```ini
# Local站点
127.0.0.1       hk.local.test
127.0.0.1       china.local.test
127.0.0.1       global.local.test
# Preview站点
127.0.0.1       hk.preview.test
127.0.0.1       china.preview.test
127.0.0.1       global.preview.test
```

::: details 配置Dev2/RC等环境（可选）:
```ini
# Dev2站点
127.0.0.1       hk.dev2.test
127.0.0.1       china.dev2.test
127.0.0.1       global.dev2.test
# RC站点
127.0.0.1       hk.rc.test
127.0.0.1       china.rc.test
127.0.0.1       global.rc.test
```
:::

## CLI 平台命令

如果本地代码仓库里已经带有宿主机入口 `bin/local`，优先使用它完成初始化、启动、浏览器登录和 TDD：

```shell
bin/local init
bin/local up
bin/local xdebug status
bin/local open
bin/local login --8id 80000570
bin/local test --filter SearchScreenServiceTest --unit tests/Unit/Services/Nps
```

`bin/local` 会把个人配置写到 `~/.tke-local/config.env`，并通过运行时生成的 site `config.php` 注入你的 MySQL 凭据，不会再去修改挂载代码。Xdebug 现在默认以 `VIEW_LOCAL_XDEBUG_MODE=off` 启动；需要断点时用 `bin/local xdebug on`，调试完成后再 `bin/local xdebug off`。

容器启动后，也可以直接使用镜像内置的 `local` 命令执行诊断、Laravel CLI 和单元测试。这些命令会自动补齐：

- `HOME=/opt/tk`
- `DOCUMENT_ROOT=/opt/tk/web`
- `HTTP_HOST=<site>.local.test`
- 站点桥接 `/opt/<site>` -> `/opt/sites/<site>`

其中默认 host 会直接沿用 site 目录名，所以本机已有 `hk.local.test`、`china.local.test`、`global.local.test` 这类 hosts 记录时可以直接使用。

示例：

```shell
docker exec -it local local diagnose --site hk --format text
docker exec -it local local run --site hk -- php sys/lib/test.php
docker exec -it local local artisan --site hk -- list
docker exec -it local local test --site hk --unit tests/Unit/Services/Nps/SearchScreenServiceTest.php
```

`diagnose` 会同时验证实际 PHP bootstrap 结果，输出当前站点对应的 `DATABASE_NAME`、`HTTP_HOST` 等关键信息。`local run` 在直接执行 `php ...` 时会自动注入 CLI bootstrap。镜像内的 Xdebug 改为 `start_with_request=trigger`，所以在 `bin/local xdebug on` 之后，只有带 `XDEBUG_TRIGGER` 的请求才会真正连 IDE。

## 浏览器本地登录

如果使用 `bin/local`，推荐直接执行：

```shell
bin/local open
bin/local open --raw
bin/local login --8id 80000570
```

默认会使用 `http://<site>.local.test:<port>`。如果 `VIEW_LOCAL_DEFAULT_8ID`、`VIEW_LOCAL_LOGIN_SECRET`、`VIEW_LOCAL_LOGIN_ENABLED=1` 已经配置，`bin/local open` 会直接触发本地自动登录；只有 `bin/local open --raw` 才会打开裸 `login.php`。如果你要切换到另一组已有 hosts，例如 `preview`、`dev2`、`rc`，可以把 `VIEW_LOCAL_HOST_TEMPLATE` 改成 `%s.preview.test`、`%s.dev2.test`、`%s.rc.test`。`VIEW_LOCAL_SITE_HOST_ALIASES` 保留为可选项，只有在 host 名和 site 目录名不一致时才需要配置。
