# ghproxy

Github文件下载加速PHP版，基于异步PHP框架 [ReactPHP](https://github.com/reactphp)开发。

本项目参考 [gh-proxy](https://github.com/hunshcn/gh-proxy)，其js版可运行在CloudFflare Worker上，并提供了可运行在docker中的Python版。


## 使用教程

### 1. 安装PHP和composer

如系统已经安装PHP和composer，可略过。

CentOS系统安装最新版PHP可参考：[使用Remi源安装最新版PHP 7和PHP 8](https://tlanyan.me/install-newest-php7-and-php8-with-remi-repo/)，Debian/Ubuntu系统可使用下面命令安装PHP：

````bash
apt update
apt php-cli php-fpm php-bcmath php-gd php-mbstring \
php-mysql php-opcache php-xml php-zip php-json php-imagick
````

安装composer：

````bash
wget https://getcomposer.org/installer
php installer --install-dir=/usr/local/bin --filename=composer
rm -rf installer
````

### 2. 安装composer依赖

下载/克隆本项目代码，进入文件夹内执行安装命令：

````bash
composer install
# 可选，基本没必要
composer dump-autoload -o
````

打开 `index.php` 文件，视自己情况可修改如下几个配置(一般保持默认即可)：

- `ADDR`：程序监听的IP，默认是本机。如果前端不需要web服务器，请改成 `0.0.0.0`；
- `PORT`: 程序监听的端口，默认8080
- `JSDELIVR`：是否使用jsdelivr加速
- `CNPMJS`：是否使用cnpmjs.org加速
- `SIZE_LIMIT`：最大可下载文件大小，默认2GB

最后将项目文件夹放置到web目录，例如将文件夹移动到 `/var/www` 目录内。

### 3. 安装Nginx（可选）

可以选择使用Nginx/Apache httpd/Caddy等中间件在前端接受web请求，也可以让程序直接监听端口处理请求。如果采用https访问，建议使用Nginx等web服务器配置SSL。

安装Nginx:

````bash
# CentOS
yum install -y nginx
systemctl enable nginx

## Debian/Ubuntu
apt install -y nginx
````

修改项目中的 `ghproxy.conf` 文件，把域名等改成自己的，然后复制到Nginx配置目录内。

### 4. 启动程序

进入项目文件夹，执行 `nohup php index.php &`，也可以在 tmux/screen 等终端窗口内执行 `php index.php` 启动代理。

部署了Nginx的前端web服务器的，请重启web服务器。

接下来，浏览器打开网址，输入要加速下载的链接，查看加速效果。

使用中遇到问题欢迎反馈。