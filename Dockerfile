# PHPコンテナのベースイメージとしてPHP 8.4 FPM Alpine版を使用
FROM php:8.4-fpm-alpine AS php

# Redisエクステンションのインストール
# - apk: Alpine Linuxのパッケージマネージャーでビルドツールをインストール
# - pecl: PHPエクステンションをインストール（Redis PHPクライアント）
# - docker-php-ext-enable: インストールしたRedisエクステンションを有効化
RUN apk add --no-cache autoconf build-base \
    && yes '' | pecl install redis \
    && docker-php-ext-enable redis

# MySQL用のPDOエクステンションをインストール（データベース接続に必要）
RUN docker-php-ext-install pdo_mysql

# 画像アップロード用のディレクトリを作成
# - www-dataユーザー（Webサーバーの実行ユーザー）が書き込み可能な状態で作成
RUN install -o www-data -g www-data -d /var/www/upload/image/

# カスタムPHP設定ファイル（php.ini）をコンテナ内にコピー
COPY ./php.ini ${PHP_INI_DIR}/php.ini
