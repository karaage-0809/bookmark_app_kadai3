<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php'; // このファイルがdb_connect() 関数ではなく、$pdo オブジェクトを提供しているはず

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

$bookmark = null;
$error_message = '';
$older_bookmark_id = null; // 古い（右ボタン用）
$newer_bookmark_id = null; // 新しい（左ボタン用）

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $bookmark_id = (int)$_GET['id'];

    try {
        // $pdo を直接使用する（db_connect.php で定義されているはず）
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
            $current_created_at = $bookmark['created_at'];
            $current_id = $bookmark['id'];

            // 古いブックマーク（右ボタン用）のIDを取得
            $sql_older = "SELECT id FROM bookmarks
                        WHERE is_public = TRUE AND
                                (created_at < :current_created_at1 OR
                                (created_at = :current_created_at2 AND id < :current_id1))
                        ORDER BY created_at DESC, id DESC LIMIT 1";

            $stmt_older = $pdo->prepare($sql_older);
            $stmt_older->bindParam(':current_created_at1', $current_created_at);
            $stmt_older->bindParam(':current_created_at2', $current_created_at); // 2回バインドする
            $stmt_older->bindParam(':current_id1', $current_id, PDO::PARAM_INT);
            $stmt_older->execute();
            $older_bookmark_id = $stmt_older->fetchColumn();

            // 新しいブックマーク（左ボタン用）のIDを取得
            $sql_newer = "SELECT id FROM bookmarks
                        WHERE is_public = TRUE AND
                                (created_at > :current_created_at3 OR
                                (created_at = :current_created_at4 AND id > :current_id3))
                        ORDER BY created_at ASC, id ASC LIMIT 1";

            $stmt_newer = $pdo->prepare($sql_newer);
            $stmt_newer->bindParam(':current_created_at3', $current_created_at);
            $stmt_newer->bindParam(':current_created_at4', $current_created_at); // 2回バインドする
            $stmt_newer->bindParam(':current_id3', $current_id, PDO::PARAM_INT);
            $stmt_newer->execute();
            $newer_bookmark_id = $stmt_newer->fetchColumn();

        } else {
            $error_message = "指定されたブックマークは見つからないか、公開されていません。";
        }

    } catch (PDOException $e) {
        error_log("Error fetching bookmark detail: " . $e->getMessage());
        $error_message = "ブックマークの取得中にエラーが発生しました。";
    }
} else {
    $error_message = "ブックマークIDが指定されていないか、不正な値です。";
}

$loggedInUserId = $_SESSION['user_id'] ?? null;

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $bookmark ? htmlspecialchars($bookmark['title']) : 'ブックマーク詳細'; ?> - みんなのブックマーク コレ見て！</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <div class="container public-container bookmark-detail-container">
        <h1>みんなのブックマーク <span class="kore-mite-text">コレ見て！！</span></h1>
        <p class="app-description">世の中の面白いウェブサイトをみんなでシェアするブックマークサイトです！</p>

        <?php if (!empty($error_message)): ?>
            <div class="message error show"><?php echo htmlspecialchars($error_message); ?></div>
            <div class="header-actions public-header-actions">
                <a href="public_bookmarks.php" class="common-button back-button"><i class="fas fa-arrow-left"></i> 公開ブックマーク一覧へ戻る</a>
            </div>
        <?php else: ?>
            <div class="detail-card">
                <div class="detail-header">
                    <h2><?php echo htmlspecialchars($bookmark['title']); ?></h2>
                    <?php if ($bookmark['is_starred']): ?>
                        <span class="star-icon fas fa-star" title="投稿者のお気に入り"></span>
                    <?php endif; ?>
                </div>

                <p class="url detail-item"><i class="fas fa-link"></i> <a href="<?php echo htmlspecialchars($bookmark['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($bookmark['url']); ?></a></p>
                <p class="description detail-item"><?php echo nl2br(htmlspecialchars($bookmark['description'])); ?></p>

                <?php if (!empty($bookmark['tags'])): ?>
                    <div class="tags detail-item">
                        <?php foreach (explode(', ', $bookmark['tags']) as $tag): ?>
                            <span class="tag public-tag"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="detail-meta detail-item">
                    <span><i class="fas fa-calendar-alt"></i> 投稿日: <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($bookmark['created_at']))); ?></span>
                    <span><i class="fas fa-user"></i> 投稿者: <?php echo htmlspecialchars($bookmark['username']); ?></span>
                    <span class="public-status"><i class="fas fa-globe"></i> 公開</span>
                </div>

                <?php if (is_admin()): ?>
                    <div class="detail-admin-actions">
                        <a href="edit.php?id=<?php echo htmlspecialchars($bookmark['id']); ?>&from=public_detail" class="common-button edit-button"><i class="fas fa-edit"></i> 編集</a>
                        <button onclick="window.confirmDelete(<?php echo htmlspecialchars($bookmark['id']); ?>, true)" class="common-button delete-button"><i class="fas fa-trash-alt"></i> 削除</button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="detail-navigation">
                <button id="prevBookmark" class="nav-button"
                    data-bookmark-id="<?php echo htmlspecialchars($newer_bookmark_id); ?>"
                    <?php echo $newer_bookmark_id ? '' : 'disabled'; ?>><i class="fas fa-chevron-left"></i></button>
                
                <a href="public_bookmarks.php" class="common-button back-button"><i class="fas fa-arrow-left"></i> 一覧に戻る</a>
                
                <button id="nextBookmark" class="nav-button"
                    data-bookmark-id="<?php echo htmlspecialchars($older_bookmark_id); ?>"
                    <?php echo $older_bookmark_id ? '' : 'disabled'; ?>><i class="fas fa-chevron-right"></i></button>
            </div>

        <?php endif; ?>
    </div>
    <script>
            // PHPから渡される初期データ
            const INITIAL_BOOKMARK_DATA = {
                id: <?php echo json_encode($bookmark_id); ?>, // PHP変数から取得
                // 変数名をget_bookmark_data.phpのJSONキー名と合わせる
                older_bookmark_id: <?php echo json_encode($older_bookmark_id); ?>, // older_bookmark_id に修正
                newer_bookmark_id: <?php echo json_encode($newer_bookmark_id); ?>, // newer_bookmark_id に修正
                is_admin: <?php echo json_encode($_SESSION['is_admin'] ?? false); ?> // is_admin もPHPから取得
            };
        </script>

        
        <script src="script.js"></script>
    </body>
</html>