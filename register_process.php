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
    // ローカル環境でソケット接続を使用する場合は、dsnにsocketパラメータを追加
    if ($is_local && $db_socket && file_exists($db_socket)) {
        $dsn = "mysql:unix_socket=$db_socket;dbname=$db_name;charset=" . DB_CHARSET;
    } else {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=" . DB_CHARSET;
    }

    $pdo = new PDO($dsn, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "データベース接続エラー: " . $e->getMessage();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // 入力値の基本的なバリデーション
    if (empty($username) || empty($email) || empty($password)) {
        echo "<p class='error'>全ての項目を入力してください。</p>";
        // 実際には登録フォームにリダイレクトし、エラーメッセージを表示すると良いでしょう
        exit();
    }

    // メールアドレスの形式チェック
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<p class='error'>有効なメールアドレスを入力してください。</p>";
        exit();
    }

    // パスワードのハッシュ化
    // PASSWORD_DEFAULT は現在最も推奨されるアルゴリズム (bcrypt) を使用します
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        // ユーザー名とメールアドレスの重複チェック
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmtCheck->execute([$username, $email]);
        if ($stmtCheck->fetchColumn() > 0) {
            echo "<p class='error'>そのユーザー名またはメールアドレスは既に登録されています。</p>";
            exit();
        }

        // データベースへの挿入
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashedPassword);

        if ($stmt->execute()) {
            echo "<p>ユーザー登録が完了しました！ <a href='login.php'>ログインはこちら</a></p>";
        } else {
            echo "<p class='error'>ユーザー登録に失敗しました。</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>データベースエラー: " . $e->getMessage() . "</p>";
    }
} else {
    // POST以外のリクエストの場合は登録ページへリダイレクト
    header('Location: register.php');
    exit();
}
?>