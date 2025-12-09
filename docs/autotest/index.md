# 搭建环境

1.在本地创建一个名为 `autotest.yml` 的文件，并复制粘贴以下内容。
```yaml{49,56,63}
version: "3"
services:
  selenium:
    image: selenium/standalone-edge:4.3.0
    container_name: selenium
    networks:
      tke:
        ipv4_address: 172.16.1.44
    environment:
      - VNC_NO_PASSWORD=1
      - SE_NODE_MAX_SESSIONS=5
    ports:
      - "4444:4444"
      - "7900:7900"
    shm_size: '2gb'
  autotest:
    image: registry.cn-hangzhou.aliyuncs.com/tke-view/view:autotest
    container_name: autotest
    networks:
      - tke
    volumes:
      - autotest:/opt/autotest
      - dev2:/opt/tk
    depends_on:
      - selenium
  autotest-rc:
    container_name: autotest-rc
    image: registry.cn-hangzhou.aliyuncs.com/tke-view/view:autotest
    networks:
      - tke
    volumes:
      - autotest-rc:/opt/autotest
    depends_on:
      - selenium
networks:
  tke:
    name: tke
    ipam:
      driver: default
      config:
        - subnet: 172.16.1.0/24
volumes:
  autotest:
    name: autotest
    driver: local
    driver_opts:
      type: none
      o: bind
      device: Dev2的Autotest目录
  dev2:
    name: dev2
    driver: local
    driver_opts:
      type: none
      o: bind
      device: Dev2的代码目录
  autotest-rc:
    name: autotest-rc
    driver: local
    driver_opts:
      type: none
      o: bind
      device: RC的Autotest目录
```
注意修改 `dev2` 和 `rc` 的代码目录

2.在终端中切换到 `autotest.yml` 文件所在的目录。例如：
```shell
cd ~/Desktop/
```

3.创建并启动服务。
```shell
docker-compose -p autotest -f ./autotest.yml up -d
```

4.使用 `docker ps` 命令验证容器是否创建成功
```shell
docker ps
```
