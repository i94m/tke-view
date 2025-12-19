#!/bin/bash

# 输出架构信息以便调试
echo "Running on architecture: $(uname -m)"

tail -f /var/log/error.log