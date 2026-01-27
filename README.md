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
docker compose build
docker compose up -d

正常に起動すると、以下のようなコンテナが起動する。
docker compose ps

2. データベースの初期化（初回のみ）
docker compose exec web bash

SQL ファイルを使用して初期化。
コンテナ内で以下を実行。
mysql -u root -p example_db < init.sql
（※ パスワードは docker-compose.yml に記載のものを使用）

3. ブラウザからアクセス
EC2 インスタンスの パブリックIPアドレス を確認し、
ブラウザから以下にアクセス
例：http://<EC2のパブリックIPアドレス>/bbs.php
