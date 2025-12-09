# View本地开发环境

## 创建镜像

### Ubuntu

```shell
# 构建多架构镜像（推荐）
docker buildx build --platform linux/amd64,linux/arm64 -t registry.cn-hangzhou.aliyuncs.com/tke-view/view:php8.2 --push view/debian

# 构建单架构镜像
# 仅构建 amd64 版本
docker buildx build --platform linux/amd64 -t registry.cn-hangzhou.aliyuncs.com/tke-view/view:php8.2-amd64 view/debian

# 仅构建 arm64 版本
docker buildx build --platform linux/arm64 -t registry.cn-hangzhou.aliyuncs.com/tke-view/view:php8.2-arm64 view/debian
```

### Autotest

```shell
yarn build:autotest
```

## 文档

本地预览
```shell
yarn docs:dev
```

## 使用方法

参考文档: [https://wqf100124.github.io/tke-view](https://wqf100124.github.io/tke-view/)