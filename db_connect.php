<?php

// エラー表示設定 (開発時のみONにすることを強く推奨)
// 本番環境では 'display_errors' を 'Off' に設定し、エラーはログに記録するようにしてください。
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 環境ごとのデータベース接続設定を定義
// 本番環境 (さくらインターネットなど) の設定
// さくらインターネットの場合、DB_HOSTは 'mysqlXXX.db.sakura.ne.jp' のような形式になります。
// DB_USERとDB_PASSは、さくらインターネットのデータベース設定で確認してください。
// DB_NAMEは、さくらインターネットで作成したデータベース名です。
define('SAKURA_DB_HOST', '*****'); // ★★★ あなたのさくらサーバーのホスト名に置き換えてください ★★★
define('SAKURA_DB_NAME', '*****');      // ★★★ あなたのさくらサーバーのデータベース名に置き換えてください ★★★
define('SAKURA_DB_USER', '*****');      // ★★★ あなたのさくらサーバーのユーザー名に置き換えてください ★★★
define('SAKURA_DB_PASS', '*****');  // ★★★ あなたのさくらサーバーのパスワードに置き換えてください ★★★

// ローカル環境 (XAMPPなど) の設定
define('LOCAL_DB_HOST', 'localhost');
define('LOCAL_DB_NAME', 'bookmark_app'); // XAMPPで作成したデータベース名
define('LOCAL_DB_USER', 'root');         // XAMPPのMySQLユーザー名 (通常はroot)
define('LOCAL_DB_PASS', '');             // XAMPPのMySQLパスワード (通常は空)
// Unixソケットファイルのパス (XAMPPの場合)
define('LOCAL_DB_SOCKET', '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock');

// 文字コードは両環境で共通とします
define('DB_CHARSET', 'utf8mb4');

// 環境判定ロジック
// サーバーのホスト名や特定の環境変数などで判断するのが一般的です。
// ここでは、現在のスクリプトがローカルのXAMPPで実行されているかどうかを簡易的に判断します。
// さくらインターネットでは 'localhost' ではないことがほとんどなので、この方法が使えます。
// もし、さくらインターネットでも 'localhost' となるような特殊な設定の場合は、別の方法を検討する必要があります。
if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1' || strpos($_SERVER['HTTP_HOST'], '::1') !== false) {
    // ローカル環境 (XAMPP)
    $db_host    = LOCAL_DB_HOST;
    $db_name    = LOCAL_DB_NAME;
    $db_user    = LOCAL_DB_USER;
    $db_pass    = LOCAL_DB_PASS;
    $db_socket  = LOCAL_DB_SOCKET;
    $is_local   = true;
} else {
    // 本番環境 (さくらインターネットなど)
    $db_host    = SAKURA_DB_HOST;
    $db_name    = SAKURA_DB_NAME;
    $db_user    = SAKURA_DB_USER;
    $db_pass    = SAKURA_DB_PASS;
    $db_socket  = null; // 本番環境ではソケット接続は使用しない
    $is_local   = false;
}

try {
    // PDO接続文字列 (DSN) の構築
    if ($is_local && $db_socket) {
        // ローカル環境 (Unixソケット接続)
        $dsn = 'mysql:unix_socket=' . $db_socket . ';dbname=' . $db_name . ';charset=' . DB_CHARSET;
    } else {
        // 本番環境 (TCP/IP接続)
        $dsn = 'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=' . DB_CHARSET;
    }

    // PDOオプション
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,      // エラー時に例外をスロー
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,            // デフォルトのフェッチモードを連想配列に
        PDO::ATTR_EMULATE_PREPARES   => false,                       // プリペアドステートメントのエミュレーションを無効にする
    ];

    // PDOインスタンスの作成
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

} catch (PDOException $e) {
    // 接続エラーが発生した場合
    // 本番環境では詳細なエラーメッセージをユーザーに見せず、ログに記録するようにします。
    error_log('DB Connection Error: ' . $e->getMessage() . ' (DSN used: ' . $dsn . ')');
    if ($is_local) {
        // 開発環境では詳細なエラーを表示
        exit('データベース接続エラーが発生しました: ' . $e->getMessage() . '<br>DSN: ' . $dsn);
    } else {
        // 本番環境では一般的なエラーメッセージを表示
        exit('データベース接続エラーが発生しました。しばらくしてから再度お試しください。');
    }
}


?>