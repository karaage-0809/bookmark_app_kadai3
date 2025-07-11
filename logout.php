<?php
// エラー表示設定 (開発時のみONにすることを強く推奨)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 必ずセッションを開始する
// これにより、既存のセッションにアクセスできるようになります。
session_start();

// セッション変数を全てクリアする
// $_SESSION配列を空にすることで、セッションに保存されている全てのユーザー情報が削除されます。
$_SESSION = array();

// セッションクッキーを削除する
// ini_get("session.use_cookies") でPHPがクッキーを使用しているか確認します。
// setcookie() を使ってセッションクッキーを過去の有効期限に設定することで、ブラウザから削除を促します。
// これはセッションハイジャック対策の一環としても重要です。
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// サーバー側のセッションデータも完全に破棄する
// これにより、セッションIDとそれに関連するサーバー上のデータが削除されます。
session_destroy();

// ログアウト後のページへリダイレクト
// 通常はログインページやトップページへリダイレクトします。
header('Location: login.php'); // 例: ログインページにリダイレクト
exit(); // リダイレクト後はスクリプトの実行を停止
?>