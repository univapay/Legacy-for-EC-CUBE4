# UnivaPay-for-EC4-Legacy

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)  
UnivaPay旧ペイメントゲートウェイプラグイン  
新規案内は終了しています。新ゲートウェイ用のものは下記を参照ください。  
<https://github.com/univapay/UnivaPay-for-EC-CUBE4>  
最新のリリースは下記から  
<https://github.com/univapay/UnivaPay-for-EC4-Legacy/releases>

## 開発環境

[プラグインの実装に関するドキュメント](UpcPaymentPlugin/README.md)

### 管理者向け

```sh
git clone https://github.com/univapaycast/UnivaPay-for-EC4-Legacy.git
cd UnivaPay-for-EC4-Legacy
cp docker-compose.sample.yml docker-compose.yml
docker compose up -d
docker compose exec web sh -c "composer run-script compile && bin/console eccube:install -n"
docker compose exec web sh -c "bin/console eccube:plugin:install --code=UpcPaymentPlugin && bin/console eccube:plugin:enable --code=UpcPaymentPlugin"
```

#### データベース更新したとき

```sh
docker compose exec web sh -c "bin/console eccube:install -n && bin/console eccube:plugin:install --code=UpcPaymentPlugin && bin/console eccube:plugin:enable --code=UpcPaymentPlugin"
```

#### アップデート手順

1. composer.json内のversionを上げる
2. masterにコミット後github内でバージョンタグの作成
