<?php
// エラー表示設定 (開発時のみONにすることを強く推奨)
// 本番環境では 'display_errors' を 'Off' に設定し、エラーはログに記録するようにしてください。
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// セッションを開始 (login_process.phpの冒頭で必ず呼び出す)
session_start();

// --- データベース接続設定 ---
// 環境ごとのデータベース接続設定を定義
// 本番環境 (さくらインターネットなど) の設定
define('SAKURA_DB_HOST', '*****'); // ★★★ あなたのさくらサーバーのホスト名に置き換えてください ★★★
define('SAKURA_DB_NAME', '*****');      // ★★★ あなたのさくらサーバーのデータベース名に置き換えてください ★★★
define('SAKURA_DB_USER', '*****');      // ★★★ あなたのさくらサーバーのユーザー名に置き換えてください ★★★
define('SAKURA_DB_PASS', '*****');  // ★★★ あなたのさくらサーバーのパスワードに置き換えてください ★★★

// ローカル環境 (XAMPPなど) の設定
define('LOCAL_DB_HOST', 'localhost');
define('LOCAL_DB_NAME', 'bookmark_app'); // XAMPPで作成したデータベース名
define('LOCAL_DB_USER', 'root');         // XAMPPのMySQLユーザー名 (通常はroot)
define('LOCAL_DB_PASS', '');             // XAMPPのMySQLパスワード (通常は空)
// Unixソケットファイルのパス (XAMPPの場合 - あなたの環境に合わせて変更してください)
define('LOCAL_DB_SOCKET', '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock'); // 例: Mac XAMPPの場合

// 文字コードは両環境で共通とします
define('DB_CHARSET', 'utf8mb4');

// 環境判定ロジック
// サーバーのホスト名や特定の環境変数などで判断するのが一般的です。
// ここでは、現在のスクリプトがローカルのXAMPPで実行されているかどうかを簡易的に判断します。
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
    // ローカル環境でソケット接続を使用する場合は、DSNにsocketパラメータを追加
    if ($is_local && $db_socket && file_exists($db_socket)) {
        $dsn = "mysql:unix_socket=$db_socket;dbname=$db_name;charset=" . DB_CHARSET;
    } else {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=" . DB_CHARSET;
    }

    $pdo = new PDO($dsn, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // データベース接続エラーはユーザーに詳細を表示せず、ログに記録し、ログイン画面へリダイレクト
    error_log("Database connection error: " . $e->getMessage()); // エラーをサーバーのログに記録
    $_SESSION['login_error'] = "現在、ログインサービスに問題が発生しています。しばらくしてから再度お試しください。";
    header('Location: login.php');
    exit();
}

// --- ログイン処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = $_POST['identifier'] ?? ''; // ユーザー名またはメールアドレス
    $password = $_POST['password'] ?? '';

    // 入力値の基本的なバリデーション
    if (empty($identifier) || empty($password)) {
        $_SESSION['login_error'] = "ユーザー名/メールアドレスとパスワードを入力してください。";
        header('Location: login.php');
        exit();
    }

    try {
        // ユーザー名またはメールアドレスでユーザーを検索
        // SQLインジェクションを防ぐため、プリペアドステートメントを使用
        // ★修正点: is_admin カラムも取得するように変更
        $stmt = $pdo->prepare("SELECT id, username, password, is_admin FROM users WHERE username = :identifier OR email = :identifier");
        $stmt->bindParam(':identifier', $identifier);
        $stmt->execute();
        $user = $stmt->fetch();

        // ユーザーが存在し、かつパスワードが一致するか検証
        if ($user && password_verify($password, $user['password'])) {
            // ログイン成功

            // セッションIDを再生成してセッション固定攻撃を防ぐ
            session_regenerate_id(true);

            // ユーザー情報をセッションに保存
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (bool)$user['is_admin']; // ★この行を追加: is_adminの値をbool型でセッションに保存

            // ログイン成功後のダッシュボードやブックマーク一覧ページにリダイレクト
            header('Location: index.php'); //  index.php
            exit(); // リダイレクト後はスクリプトの実行を終了
        } else {
            // ログイン失敗 (ユーザーが存在しない、またはパスワードが不正)
            $_SESSION['login_error'] = "ユーザー名またはパスワードが正しくありません。";
            header('Location: login.php');
            exit();
        }
    } catch (PDOException $e) {
        // データベース操作中のエラー (例: SQL構文エラーなど)
        error_log("Login process database error: " . $e->getMessage()); // エラーをログに記録
        $_SESSION['login_error'] = "ログイン処理中にエラーが発生しました。しばらくしてから再度お試しください。";
        header('Location: login.php');

        exit();
    }
} else {
    // POST以外のリクエストの場合はログインページへリダイレクト
    // 直接このファイルにアクセスされた場合など
    header('Location: login.php');
    exit();
}
?>