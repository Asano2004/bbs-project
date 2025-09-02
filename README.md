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




次に.envを作成
cat > .env <<'EOF'
MYSQL_ROOT_PASSWORD=changeme_root
MYSQL_DATABASE=example_db
MYSQL_USER=bbs_user
MYSQL_PASSWORD=changeme_app
EOF

アップロード先を用意する
mkdir -p public/uploads
chmod 777 public/uploads


# ビルド
docker compose build

# 起動
docker compose up


テーブルの作成方法
MySQL コンテナに入ってテーブルを作成

docker compose exec mysql mysql example_db

sql文を追加
CREATE TABLE `bbs_entries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `body` TEXT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE bbs_entries ADD COLUMN image_filename VARCHAR(255) NULL AFTER body;

# ビルド
docker compose build

# 起動
docker compose up

