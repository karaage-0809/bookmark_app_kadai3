<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php'; 

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '不明なエラーが発生しました。', // 初期メッセージをより汎用的に
    'bookmark' => null,
    'older_bookmark_id' => null,
    'newer_bookmark_id' => null
];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $bookmark_id = (int)$_GET['id'];

    try {
        // メインのブックマーク詳細取得クエリ
        $sql = "SELECT b.id, b.url, b.title, b.description, b.is_starred, b.created_at, b.user_id, u.username,
               GROUP_CONCAT(t.name ORDER BY t.name ASC SEPARATOR ', ') AS tags, b.is_public
        FROM bookmarks b
        JOIN users u ON b.user_id = u.id
        LEFT JOIN bookmark_tags bt ON b.id = bt.bookmark_id
        LEFT JOIN tags t ON bt.tag_id = t.id
        WHERE b.id = :id AND b.is_public = TRUE
        GROUP BY b.id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $bookmark_id, PDO::PARAM_INT);
        $stmt->execute();
        $bookmark = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bookmark) {
            $response['success'] = true; // ブックマーク取得成功
            $response['message'] = 'ブックマーク詳細の取得に成功しました。'; // 成功メッセージ
            $response['bookmark'] = $bookmark;

            $current_created_at = $bookmark['created_at'];
            $current_id = $bookmark['id'];

            error_log("DEBUG get_bookmark_data: current_id = " . $current_id . ", current_created_at = " . $current_created_at);

            // --- 古いブックマーク（右ボタン用）のIDを取得 ---
            $sql_older = "SELECT id FROM bookmarks
                          WHERE is_public = TRUE AND
                                (created_at < :current_created_at_older1 OR
                                 (created_at = :current_created_at_older2 AND id < :current_id_older))
                          ORDER BY created_at DESC, id DESC LIMIT 1";
            error_log("DEBUG get_bookmark_data: Executing SQL_OLDER");

            $stmt_older = $pdo->prepare($sql_older);
            $stmt_older->bindParam(':current_created_at_older1', $current_created_at);
            $stmt_older->bindParam(':current_created_at_older2', $current_created_at);
            $stmt_older->bindParam(':current_id_older', $current_id, PDO::PARAM_INT);
            $stmt_older->execute();
            $older_bookmark_id = $stmt_older->fetchColumn();
            $response['older_bookmark_id'] = ($older_bookmark_id !== false) ? $older_bookmark_id : null; // false の場合は null にする
            error_log("DEBUG get_bookmark_data: older_bookmark_id = " . json_encode($response['older_bookmark_id']));


            // --- 新しいブックマーク（左ボタン用）のIDを取得 ---
            $sql_newer = "SELECT id FROM bookmarks
                          WHERE is_public = TRUE AND
                                (created_at > :current_created_at_newer1 OR
                                 (created_at = :current_created_at_newer2 AND id > :current_id_newer))
                          ORDER BY created_at ASC, id ASC LIMIT 1";
            error_log("DEBUG get_bookmark_data: Executing SQL_NEWER");

            $stmt_newer = $pdo->prepare($sql_newer);
            $stmt_newer->bindParam(':current_created_at_newer1', $current_created_at);
            $stmt_newer->bindParam(':current_created_at_newer2', $current_created_at);
            $stmt_newer->bindParam(':current_id_newer', $current_id, PDO::PARAM_INT);
            $stmt_newer->execute();
            $newer_bookmark_id = $stmt_newer->fetchColumn();
            $response['newer_bookmark_id'] = ($newer_bookmark_id !== false) ? $newer_bookmark_id : null; // false の場合は null にする
            error_log("DEBUG get_bookmark_data: newer_bookmark_id = " . json_encode($response['newer_bookmark_id']));

        } else {
            $response['success'] = false; // ブックマークが見つからない場合は失敗
            $response['message'] = '指定されたブックマークは見つからないか、公開されていません。';
        }

    } catch (PDOException $e) {
        error_log('Error in get_bookmark_data.php: ' . $e->getMessage());
        $response['success'] = false; // DBエラー発生時は失敗
        $response['message'] = 'データ取得中にデータベースエラーが発生しました。';
    }
} else {
    $response['success'] = false; // IDがない場合は失敗
    $response['message'] = 'ブックマークIDが指定されていないか、不正な値です。';
}

echo json_encode($response);
exit();
?>