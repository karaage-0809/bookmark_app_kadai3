<?php
session_start();

// エラー表示設定（開発中は残す）
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

$search_query = $_GET['search'] ?? '';
$search_query = trim($search_query);

$bookmarks = [];
$error_message = '';

try {
    $sql = "SELECT b.id, b.url, b.title, b.description, b.is_starred, b.created_at, b.user_id, u.username,
                   GROUP_CONCAT(t.name ORDER BY t.name ASC SEPARATOR ', ') AS tags
            FROM bookmarks b
            JOIN users u ON b.user_id = u.id
            LEFT JOIN bookmark_tags bt ON b.id = bt.bookmark_id
            LEFT JOIN tags t ON bt.tag_id = t.id
            WHERE b.is_public = TRUE ";

    $params = [];

    if (!empty($search_query)) {
        $sql .= " AND (b.title LIKE :search_title OR b.description LIKE :search_description OR b.url LIKE :search_url OR u.username LIKE :search_username OR t.name LIKE :search_tag) ";
        $params[':search_title'] = '%' . $search_query . '%';
        $params[':search_description'] = '%' . $search_query . '%';
        $params[':search_url'] = '%' . $search_query . '%';
        $params[':search_username'] = '%' . $search_query . '%';
        $params[':search_tag'] = '%' . $search_query . '%';
    }

    $sql .= " GROUP BY b.id ORDER BY b.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookmarks = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching public bookmarks: " . $e->getMessage());
    $error_message = "ブックマークの取得中にエラーが発生しました。しばらくしてから再度お試しください。";
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>みんなのブックマーク コレ見て！</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <div class="container public-container"> <h1>みんなのブックマーク <span class="app-tagline">コレ見て！</span></h1> <p class="app-description">世の中の面白いウェブサイトをみんなでシェアするブックマークサイトです！</p> <?php if (!empty($error_message)): ?>
            <div class="message error show"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="header-actions public-header-actions"> <div class="button-group"> <a href="index.php" class="common-button back-button"><i class="fas fa-arrow-left"></i> 自分のブックマーク</a>
                <?php if (!is_logged_in()): ?>
                    <a href="login.php" class="common-button submit-button"><i class="fas fa-sign-in-alt"></i> ログイン</a>
                    <a href="register.php" class="common-button submit-button"><i class="fas fa-user-plus"></i> 新規登録</a>
                <?php else: ?>
                    <a href="logout.php" class="common-button back-button"><i class="fas fa-sign-out-alt"></i> ログアウト</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="search-container public-search-container"> <form action="public_bookmarks.php" method="GET">
                <input type="text" name="search" placeholder="タイトル、URL、説明、タグ、ユーザー名で検索" value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit"><i class="fas fa-search"></i> 検索</button>
                <?php if (!empty($search_query)): ?>
                    <a href="public_bookmarks.php" class="clear-search"><i class="fas fa-times-circle"></i> クリア</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($bookmarks)): ?>
            <p class="no-bookmarks-message">現在、公開されているブックマークはありません。<br>ぜひ、あなたの気になるブックマークをシェアしてください！</p> <?php else: ?>
            <ul class="bookmark-list public-bookmark-list"> <?php foreach ($bookmarks as $bookmark): ?>
                    <li class="bookmark-card public-bookmark-card">
                        <div class="star-container public-star-container">
                            <?php if ($bookmark['is_starred']): ?>
                                <span class="star-icon fas fa-star" title="お気に入り"></span>
                            <?php endif; ?>
                        </div>

                        <h3>
                            <a href="bookmark_detail.php?id=<?php echo htmlspecialchars($bookmark['id']); ?>" class="bookmark-title-link">
                                <?php echo htmlspecialchars($bookmark['title']); ?>
                            </a>
                        </h3>

                        <p class="url"><i class="fas fa-link"></i> <?php echo htmlspecialchars($bookmark['url']); ?></p>
                        <p class="description"><?php echo nl2br(htmlspecialchars($bookmark['description'])); ?></p>

                        <?php if (!empty($bookmark['tags'])): ?>
                            <div class="tags">
                                <?php foreach (explode(', ', $bookmark['tags']) as $tag): ?>
                                    <span class="tag public-tag"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="bookmark-actions public-bookmark-actions">
                            <div class="date-info">
                                <span><i class="fas fa-calendar-alt"></i> 追加日: <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($bookmark['created_at']))); ?></span>
                                <span> by <?php echo htmlspecialchars($bookmark['username']); ?></span>
                                <span class="public-status">公開</span>
                            </div>
                            <?php if (is_admin()): ?>
                                <div class="buttons">
                                    <a href="edit.php?id=<?php echo $bookmark['id']; ?>&public=true" class="edit-button">編集</a>
                                    <button onclick="confirmDelete(<?php echo $bookmark['id']; ?>, true)" class="delete-button">削除</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const messages = document.querySelectorAll('.message.show');
            messages.forEach(message => {
                setTimeout(() => {
                    message.classList.remove('show');
                }, 5000);
            });
        });

        function confirmDelete(bookmarkId, isPublicBookmark = false) {
            if (confirm('本当にこのブックマークを削除しますか？')) {
                let url = 'delete.php?id=' + bookmarkId;
                if (isPublicBookmark) {
                    url += '&public=true';
                }
                window.location.href = url;
            }
        }
    </script>
</body>
</html>