<?php
session_start();

// エラー表示設定（開発中は残す）
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

// ★修正: is_admin() 関数は index.php では不要なので削除（または残しておいても機能には影響ないが、ロジックとして不要）
// 必要であれば is_logged_in() のみ残す
function is_logged_in() {
    return isset($_SESSION['user_id']);
}
// function is_admin() { // この関数は index.php のロジックでは使わないので削除しても良い
//     return is_logged_in() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
// }


// ログインしているユーザーのIDを取得
$loggedInUserId = $_SESSION['user_id'] ?? null;
$loggedInUsername = $_SESSION['username'] ?? 'ゲスト'; // ユーザー名も取得

// 検索キーワードの取得
$search_query = $_GET['search'] ?? '';
$search_query = trim($search_query); // 前後の空白を削除

// 成功メッセージの表示 (add.phpからのリダイレクト用)
$success_message = '';
if (isset($_GET['success_message'])) {
    $success_message = htmlspecialchars($_GET['success_message']);
}
// エラーメッセージの表示 (edit.phpなどからのリダイレクト用)
$error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = htmlspecialchars($_SESSION['error_message']);
    unset($_SESSION['error_message']); // 一度表示したらセッションから削除
}

$bookmarks = []; // ブックマークデータを格納する配列

try {
    // SQLクエリの基本部分
    // ★修正: 自分のブックマークのみを表示する
    $sql = "SELECT b.id, b.url, b.title, b.description, b.is_starred, b.created_at, b.user_id, u.username,
                   GROUP_CONCAT(t.name ORDER BY t.name ASC SEPARATOR ', ') AS tags, b.is_public
            FROM bookmarks b
            JOIN users u ON b.user_id = u.id
            LEFT JOIN bookmark_tags bt ON b.id = bt.bookmark_id
            LEFT JOIN tags t ON bt.tag_id = t.id
            WHERE b.user_id = :loggedInUserId "; // ★この行を修正

    $params = [':loggedInUserId' => $loggedInUserId];

    // 検索キーワードがある場合
    if (!empty($search_query)) {
        // 検索条件も自分のブックマークに限定
        $sql .= " AND (b.title LIKE :search_title OR b.description LIKE :search_description OR b.url LIKE :search_url OR t.name LIKE :search_tag) ";
        // u.username LIKE :search_username は、自分のブックマークだけなら通常不要だが、残す場合は検索ロジックを見直す必要あり。
        // ここでは一旦削除します。もしユーザー名検索も必要なら再度検討。
        $params[':search_title'] = '%' . $search_query . '%';
        $params[':search_description'] = '%' . $search_query . '%';
        $params[':search_url'] = '%' . $search_query . '%';
        // $params[':search_username'] = '%' . $search_query . '%'; // 削除
        $params[':search_tag'] = '%' . $search_query . '%';
    }

    $sql .= " GROUP BY b.id ORDER BY b.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookmarks = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching bookmarks: " . $e->getMessage());
    $error_message = "ブックマークの取得中にエラーが発生しました。";
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ブックマーク一覧</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <div class="container">
        <h1>Myブックマーク一覧</h1>

        <?php if (!empty($success_message)): ?>
            <div class="message success show"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error show"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="header-actions">
            <?php if (is_logged_in()): // ログインしている場合 ?>
                <p>ようこそ、<?php echo htmlspecialchars($loggedInUsername); ?>さん！</p>
                <a href="add.php" class="common-button submit-button">新しいブックマークを追加</a>
                <a href="logout.php" class="common-button back-button">ログアウト</a>
            <?php else: // ログインしていない場合 ?>
                <p>ログインしていません。</p>
                <a href="login.php" class="common-button submit-button">ログイン</a>
                <a href="register.php" class="common-button submit-button">新規登録</a>
            <?php endif; ?>

            <a href="public_bookmarks.php" class="common-button info-button">みんなのコレ見て！</a>
            </div>

        <div class="search-container">
            <form action="index.php" method="GET">
                <input type="text" name="search" placeholder="タイトル、URL、説明、タグ、ユーザー名で検索" value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit">検索</button>
                <?php if (!empty($search_query)): ?>
                    <a href="index.php" class="clear-search">検索をクリア</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($bookmarks)): ?>
            <p>ブックマークはまだありません。</p>
        <?php else: ?>
            <div class="bookmark-list">
                <?php foreach ($bookmarks as $bookmark): ?>
                    <div class="bookmark-card">
                        <div class="bookmark-header">
                            <div class="star-container">
                                <?php if (is_logged_in()): // ログインしているユーザーのみスターを操作可能 ?>
                                    <button class="star-button" data-bookmark-id="<?php echo htmlspecialchars($bookmark['id']); ?>" data-is-starred="<?php echo $bookmark['is_starred'] ? 'true' : 'false'; ?>">
                                        <span class="star-icon <?php echo $bookmark['is_starred'] ? 'fas fa-star' : 'far fa-star'; ?>"></span>
                                    </button>
                                <?php else: // ログインしていない場合はスターを静的に表示 ?>
                                    <?php if ($bookmark['is_starred']): ?>
                                        <span class="star-icon fas fa-star" style="color: #FFD700;"></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <h3>
                                <a href="<?php echo htmlspecialchars($bookmark['url']); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo htmlspecialchars($bookmark['title']); ?>
                                </a>
                            </h3>
                        </div>

                        <p class="url"><?php echo htmlspecialchars($bookmark['url']); ?></p>

                        <p class="description"><?php echo nl2br(htmlspecialchars($bookmark['description'])); ?></p>

                        <?php if (!empty($bookmark['tags'])): ?>
                            <div class="tags">
                                <?php foreach (explode(', ', $bookmark['tags']) as $tag): ?>
                                    <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="bookmark-actions">
                            <div class="date-info">
                                追加日: <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($bookmark['created_at']))); ?>
                                by <span class="username"><?php echo htmlspecialchars($bookmark['username']); ?></span>
                            <?php if ($bookmark['is_public']): ?>
                                <span class="public-status">(公開中)</span>
                            <?php endif; ?>
                        </div>
    <?php
    // ★ここを修正: そのブックマークの所有者がログインユーザー自身である場合のみボタンを表示
    if ($bookmark['user_id'] == $loggedInUserId):
    ?>
        <div class="buttons">
            <a href="edit.php?id=<?php echo htmlspecialchars($bookmark['id']); ?>" class="edit-button">編集</a>
            <button class="delete-button" onclick="confirmDelete(<?php echo htmlspecialchars($bookmark['id']); ?>, false);">削除</button>
        </div>
    <?php endif; ?>
</div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // メッセージを自動的に非表示にする
            const messages = document.querySelectorAll('.message.show');
            messages.forEach(message => {
                setTimeout(() => {
                    message.classList.remove('show');
                }, 5000); // 5秒後に非表示
            });

            // スターボタンの処理
            document.querySelectorAll('.star-button').forEach(button => {
                button.addEventListener('click', function() {
                    const bookmarkId = this.dataset.bookmarkId;
                    const isStarred = this.dataset.isStarred === 'true'; // 現在の状態
                    const newStarredStatus = !isStarred; // 次の状態

                    const iconSpan = this.querySelector('.star-icon');

                    // Ajaxリクエストを送信
                    const formData = new FormData();
                    formData.append('id', bookmarkId);
                    formData.append('is_starred', newStarredStatus ? 1 : 0); // 1または0で送信

                    fetch('toggle_star.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // UIを更新
                            this.dataset.isStarred = newStarredStatus ? 'true' : 'false';
                            // Font Awesome のクラスを切り替える
                            if (newStarredStatus) {
                                iconSpan.classList.remove('far');
                                iconSpan.classList.add('fas');
                            } else {
                                iconSpan.classList.remove('fas');
                                iconSpan.classList.add('far');
                            }
                        } else {
                            alert('スター状態の更新に失敗しました: ' + (data.error || '不明なエラー'));
                            console.error('Error toggling star:', data.error);
                        }
                    })
                    .catch(error => {
                        alert('通信エラーが発生しました。');
                        console.error('Fetch error:', error);
                    });
                });
            });

            // ★修正: 削除確認のためのJavaScript関数
            // index.php では自分のブックマークの削除が基本なので、isPublicBookmark は常に false
            function confirmDelete(bookmarkId, isPublicBookmark = false) { // isPublicBookmark 引数は残しておくが、false を渡す
                if (confirm('本当にこのブックマークを削除しますか？')) {
                    let url = 'delete.php?id=' + bookmarkId;
                    // index.phpからは常に自分のブックマークの削除なので public=true は付けない
                    // if (isPublicBookmark) { // この条件は常に false になる
                    //     url += '&public=true';
                    // }
                    window.location.href = url;
                }
            }
        });
    </script>
</body>
</html>