<?php
session_start(); // セッションを開始

require_once 'db_connect.php'; // データベース接続ファイルを読み込む

header('Content-Type: application/json'); // JSON形式でレスポンスを返すことを宣言

// --- 認証チェックロジック ---
// ユーザーがログインしているかチェック
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'ログインが必要です。']);
    exit();
}
// ログインしているユーザーのIDを取得
$loggedInUserId = $_SESSION['user_id'];
// --- ここまで認証チェックロジック ---

// POSTリクエストであることを確認
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

// 必須パラメータのチェック
$bookmark_id = $_POST['id'] ?? null;
$is_starred = $_POST['is_starred'] ?? null; // JavaScriptから送られる次の状態

if ($bookmark_id === null || $is_starred === null) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters.']);
    exit;
}

// 受け取った値を整数に変換し、安全を確保
$bookmark_id = (int)$bookmark_id;
$is_starred = (int)$is_starred; // 0または1を期待

try {
    // データベースを更新するSQLクエリ
    // ★★★ user_id をWHERE句に追加し、ログインユーザーのブックマークのみ更新可能にする ★★★
    $stmt = $pdo->prepare("UPDATE bookmarks SET is_starred = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$is_starred, $bookmark_id, $loggedInUserId]);

    // 更新が成功したかどうかを確認
    // rowCount() > 0 は、レコードが更新されたことを示します。
    // レコードが見つからなかった（他人のブックマークだった）場合も0を返します。
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'id' => $bookmark_id, 'new_status' => $is_starred]);
    } else {
        // 更新対象のレコードが見つからなかった場合（存在しないID、またはログインユーザーのものでないID）
        // 成功とせず、エラーメッセージを返す
        echo json_encode(['success' => false, 'error' => '指定されたブックマークが見つからないか、更新する権限がありません。']);
    }

} catch (PDOException $e) {
    // データベースエラーが発生した場合
    error_log("Error toggling star status: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'データベースエラーが発生しました。']);
}
?>