AWS Academy 手順書

1.ログイン方法
ssh -i <秘密鍵.pem> ec2-user@<EC2のパブリックIP>でログイン

2. Docker および Docker Compose のインストール
(1)パッケージ更新
sudo yum update -y

(2)Dockerインストール
sudo amazon-linux-extras enable docker
sudo yum install -y docker

(3)Dockerサービス起動＆権限設定
sudo systemctl start docker
sudo systemctl enable docker
sudo usermod -aG docker ec2-user

(4) Docker Compose インストール
sudo curl -SL https://github.com/docker/compose/releases/download/v2.20.2/docker-compose-linux-x86_64 -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

確認：
docker --version
docker-compose --version

3. プロジェクト構成（ソースコード配置）
プロジェクトを /home/ec2-user/app に配置する例。

app/
├── docker-compose.yml
├── web/                  # Webアプリケーション用ソースコード
│   ├── Dockerfile
│   └── src/...
└── db/
    └── schema.sql          # DB初期化スクリプト（テーブル作成用）

4. 環境の起動
(1) ビルド
docker compose build

(2) 起動
docker compose up -d

(3) 状態確認
docker compose ps
docker compose logs -f

5.MySQLコンテナに入る方法
コンテナ名を確認：
docker ps

出力例：
CONTAINER ID   IMAGE       COMMAND                  PORTS                               NAMES
abcd12345678   mysql:8.0   "docker-entrypoint..."   0.0.0.0:3306->3306/tcp              app_db_1

ログインコマンド：
docker exec -it app_db_1 mysql -uroot -p

6. データベース選択
ログインしたら、データベースを選択する。
USE app_db;

7. テーブル作成の例
例：
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(100) NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

