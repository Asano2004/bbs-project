sudo dnf update -y
sudo dnf install -y git
sudo dnf install -y docker
sudo systemctl enable docker
sudo systemctl start docker
sudo mkdir -p /usr/local/lib/docker/cli-plugins/
sudo curl -SL https://github.com/docker/compose/releases/download/v2.36.0/docker-compose-linux-x86_64 -o /usr/local/lib/docker/cli-plugins/docker-compose
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
sudo usermod -aG docker ec2-user
再ログイン後に確認:

docker --version
docker compose version

gitからソースコードを取得

git clone https://github.com/Asano2004/bbs-project.git
cd bbs-project

1. Docker コンテナのビルド・起動
docker compose up --build 

正常に起動すると、以下のようなコンテナが起動する。
docker compose ps

vim php.ini

post_max_size = 5M
upload_max_filesize = 5M

session.save_handler = redis
session.save_path = "tcp://redis:6379"
session.gc_maxlifetime = 86400

2. データベースの初期化（初回のみ）とSQL文追加
docker compose exec web bash

SQL ファイルを使用して初期化。
コンテナ内で以下を実行。
mysql -u root -p example_db < init.sql
（※ パスワードは docker-compose.yml に記載のものを使用）

SQL追記

CREATE TABLE `access_logs` (
  `id` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user_agent` TEXT NOT NULL,
  `remote_ip` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `bbs_entries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `body` TEXT NOT NULL,
  `image_filename` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `user_relationships` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `followee_user_id` INT UNSIGNED NOT NULL,
  `follower_user_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `user_relationships` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `followee_user_id` INT UNSIGNED NOT NULL,
  `follower_user_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

3. ブラウザからアクセス
EC2 インスタンスの パブリックIPアドレス を確認し、
ブラウザから以下にアクセス
例：http://<EC2のパブリックIPアドレス>/bbs.php
