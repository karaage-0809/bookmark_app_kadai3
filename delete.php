<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

// ★追加: 認証と権限チェックのヘルパー関数（public_bookmarks.php と同じ定義、または共通ファイルからインクルード）
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

if (!is_logged_in()) {
    $_SESSION['message'] = "ログインしてください。";
    $_SESSION['message_type'] = "error";
    header('Location: login.php');
    exit();
}

$bookmark_id = $_GET['id'] ?? null;
$is_public_delete = isset($_GET['public']) && $_GET['public'] === 'true'; // ★追加: 公開ブックマークの削除フラグ

if ($bookmark_id === null) {
    $_SESSION['message'] = "削除するブックマークが指定されていません。";
    $_SESSION['message_type'] = "error";
    // どこへリダイレクトするかは、公開/非公開どちらの削除を意図したかによる
    header('Location: ' . ($is_public_delete ? 'public_bookmarks.php' : 'index.php'));
    exit();
}

try {
    $pdo->beginTransaction();

    // まずはタグの関連付けを削除
    $stmt_tags = $pdo->prepare("DELETE FROM bookmark_tags WHERE bookmark_id = :bookmark_id");
    $stmt_tags->bindParam(':bookmark_id', $bookmark_id, PDO::PARAM_INT);
    $stmt_tags->execute();

    $stmt_delete = null;
    $redirect_url = 'index.php'; // デフォルトのリダイレクト先

    if ($is_public_delete && is_admin()) {
        // ★管理者が公開ブックマークを削除する場合
        $stmt_delete = $pdo->prepare("DELETE FROM bookmarks WHERE id = :id AND is_public = TRUE");
        $stmt_delete->bindParam(':id', $bookmark_id, PDO::PARAM_INT);
        $redirect_url = 'public_bookmarks.php';
    } else {
        // 通常のユーザーが自分のブックマークを削除する場合
        $user_id = $_SESSION['user_id'];
        $stmt_delete = $pdo->prepare("DELETE FROM bookmarks WHERE id = :id AND user_id = :user_id");
        $stmt_delete->bindParam(':id', $bookmark_id, PDO::PARAM_INT);
        $stmt_delete->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    }

    if ($stmt_delete) {
        $stmt_delete->execute();
        if ($stmt_delete->rowCount() > 0) {
            $_SESSION['message'] = "ブックマークが削除されました。";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "ブックマークの削除に失敗しました（権限がないか、ブックマークが存在しません）。";
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "削除処理が適切に実行されませんでした。";
        $_SESSION['message_type'] = "error";
    }

    $pdo->commit();
    header('Location: ' . $redirect_url);
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error deleting bookmark: " . $e->getMessage());
    $_SESSION['message'] = "エラーが発生しました: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    header('Location: ' . ($is_public_delete ? 'public_bookmarks.php' : 'index.php'));
    exit();
}
?>