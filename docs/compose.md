# Docker Compose

[Docker Compose](https://docs.docker.com/compose/) 是用于定义和运行多容器 Docker 应用程序的工具。通过 Compose，您可以使用 YML 文件来配置应用程序需要的所有服务。使用一个命令，就可以创建并启动所有服务。
<br>
如果你还不了解 YML 文件配置，可以先阅读 [YAML 入门教程](https://www.runoob.com/w3cnote/yaml-intro.html)。

## 创建环境

1.在本地创建一个名为 `docker-compose.yml` 的文件，并复制粘贴以下内容。

::: tip 温馨提示
1.以下配置仅作参考，请根据业务需求修改配置，不需要的服务，在配置项 `service` 中去删除即可。<br>
2.在 `volumes` 配置项中修改代码目录。
:::

```yaml{2}
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
  dev:
    image: registry.cn-hangzhou.aliyuncs.com/tke-view/view:php8.2
    container_name: dev
    volumes:
      - sites:/opt/sites
      - dev:/opt/tk
    networks:
      tke:
        ipv4_address: 172.16.1.81
    ports:
      - "8000:80"
    restart: always
  # RabbitMQ
  rabbitmq:
    image: rabbitmq:3.9-management-alpine
    container_name: rabbitmq
    networks:
      tke:
        ipv4_address: 172.16.1.56
    ports:
      - "5672:5672"
      - "15672:15672"
  # Solr
  solr:
    image: solr
    container_name: solr
    command: solr-precreate view
    networks:
      tke:
        ipv4_address: 172.16.1.89
    ports:
      - "8983:8983"
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
      device: local代码路径如：D:/tke/local
  dev:
    name: dev
    driver: local
    driver_opts:
      type: none
      o: bind
      device: dev代码路径如：D:/tke/dev
```

2.在终端中切换到 `docker-compose.yml` 文件所在的目录。例如：
```shell
cd ~/Desktop/
```

3.运行命令（创建容器同时在后台运行）
```shell
docker-compose -p tke up -d
```

4.使用 `docker ps` 命令验证容器是否创建成功
```shell
docker ps
```

## 常用命令

查看正在运行的容器

```shell
docker compose ps
```

停止服务
```shell
docker compose stop
```

删除容器
```shell 
docker compose down
```

删除容器同时清除数据
```shell 
docker compose down --volumes
```

查看更多可用命令
```shell
docker compose --help
```
