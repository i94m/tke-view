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

## 项目初始化

运行命令 `docker exec -it local /run/init.sh <你的8ID>`

例如
```shell
docker exec -it local /run/init.sh 80000570
```

8ID用于代码自动登录。

至此本地环境已经搭建好了，尝试访问: [http://hk.local.test](http://hk.local.test)
