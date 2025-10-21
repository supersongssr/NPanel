更新方式:
===


```shell
# 已经绑定分支的情况
git pull 

# 下载指定分支
git pull origin NPanel 

# 强制放弃本地更新,然后 拉取
git reset --hard HEAD
git pull

# 强制更新 并覆盖本地文件
git reset --hard origin/NPanel

```