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
$is_public_edit = isset($_GET['public']) && $_GET['public'] === 'true'; // ★追加: 公開ブックマークの編集フラグ

$bookmark = null;
$message = '';
$message_type = '';

$redirect_after_save = 'index.php'; // デフォルトのリダイレクト先

if ($is_public_edit && is_admin()) {
    $redirect_after_save = 'public_bookmarks.php'; // 管理者による公開編集の場合
}

// ブックマークデータの取得
if ($bookmark_id) {
    try {
        $sql = "SELECT id, url, title, description, is_public, is_starred, user_id FROM bookmarks WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $bookmark_id, PDO::PARAM_INT);
        $stmt->execute();
        $bookmark = $stmt->fetch(PDO::FETCH_ASSOC);

        // ★追加: 権限チェック
        // ブックマークが存在しない、または
        // ログインユーザーがブックマークの所有者ではない、かつ管理者でもない場合
        // かつ、公開ブックマークの管理者編集フラグが立っていない場合
        if (!$bookmark ||
            (!$is_public_edit && $bookmark['user_id'] !== $_SESSION['user_id']) ||
            ($is_public_edit && !is_admin() && $bookmark['user_id'] !== $_SESSION['user_id']) || // 公開編集なのに管理者でもなく所有者でもない
            ($is_public_edit && !$bookmark['is_public']) // 公開編集なのに公開されていない
        ) {
            $_SESSION['message'] = "アクセス権がありません。";
            $_SESSION['message_type'] = "error";
            header('Location: ' . ($is_public_edit ? 'public_bookmarks.php' : 'index.php'));
            exit();
        }

        // タグの取得
        $stmt_tags = $pdo->prepare("SELECT t.id, t.name FROM tags t JOIN bookmark_tags bt ON t.id = bt.tag_id WHERE bt.bookmark_id = :bookmark_id");
        $stmt_tags->bindParam(':bookmark_id', $bookmark_id, PDO::PARAM_INT);
        $stmt_tags->execute();
        $current_tags = $stmt_tags->fetchAll(PDO::FETCH_ASSOC);
        $bookmark['tags'] = array_map(function($tag){ return $tag['name']; }, $current_tags);

    } catch (PDOException $e) {
        error_log("Error fetching bookmark: " . $e->getMessage());
        $message = "ブックマークの取得中にエラーが発生しました。";
        $message_type = "error";
    }
} else {
    // IDが指定されていない場合は新規追加とみなすこともできるが、今回は編集なのでエラー
    $message = "編集するブックマークが指定されていません。";
    $message_type = "error";
    header('Location: ' . $redirect_after_save); // 不正なアクセス
    exit();
}


// POSTリクエストでデータが送信された場合（更新処理）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $bookmark) {
    $url = trim($_POST['url'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $tags_input = trim($_POST['tags'] ?? '');
    $is_starred = isset($_POST['is_starred']) ? 1 : 0;

    if (empty($url) || empty($title)) {
        $message = "URLとタイトルは必須です。";
        $message_type = "error";
    } else {
        try {
            $pdo->beginTransaction();

            // ブックマークの更新
            $stmt_update = $pdo->prepare("UPDATE bookmarks SET url = :url, title = :title, description = :description, is_public = :is_public, is_starred = :is_starred WHERE id = :id");
            $stmt_update->bindParam(':url', $url);
            $stmt_update->bindParam(':title', $title);
            $stmt_update->bindParam(':description', $description);
            $stmt_update->bindParam(':is_public', $is_public, PDO::PARAM_INT);
            $stmt_update->bindParam(':is_starred', $is_starred, PDO::PARAM_INT);
            $stmt_update->bindParam(':id', $bookmark_id, PDO::PARAM_INT);
            $stmt_update->execute();

            // タグの処理
            // 既存のタグを全て削除
            $stmt_delete_tags = $pdo->prepare("DELETE FROM bookmark_tags WHERE bookmark_id = :bookmark_id");
            $stmt_delete_tags->bindParam(':bookmark_id', $bookmark_id, PDO::PARAM_INT);
            $stmt_delete_tags->execute();

            // 新しいタグを追加
            if (!empty($tags_input)) {
                $tag_names = array_map('trim', explode(',', $tags_input));
                $tag_names = array_unique(array_filter($tag_names)); // 空のタグや重複を除去
                foreach ($tag_names as $tag_name) {
                    // タグが存在しなければ追加
                    $stmt_find_tag = $pdo->prepare("SELECT id FROM tags WHERE name = :name");
                    $stmt_find_tag->bindParam(':name', $tag_name);
                    $stmt_find_tag->execute();
                    $tag_id = $stmt_find_tag->fetchColumn();

                    if (!$tag_id) {
                        $stmt_insert_tag = $pdo->prepare("INSERT INTO tags (name) VALUES (:name)");
                        $stmt_insert_tag->bindParam(':name', $tag_name);
                        $stmt_insert_tag->execute();
                        $tag_id = $pdo->lastInsertId();
                    }

                    // ブックマークとタグを関連付け
                    $stmt_link_tag = $pdo->prepare("INSERT INTO bookmark_tags (bookmark_id, tag_id) VALUES (:bookmark_id, :tag_id)");
                    $stmt_link_tag->bindParam(':bookmark_id', $bookmark_id, PDO::PARAM_INT);
                    $stmt_link_tag->bindParam(':tag_id', $tag_id, PDO::PARAM_INT);
                    $stmt_link_tag->execute();
                }
            }

            $pdo->commit();
            $_SESSION['message'] = "ブックマークが更新されました。";
            $_SESSION['message_type'] = "success";
            header('Location: ' . $redirect_after_save); // 保存後のリダイレクト先
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error updating bookmark: " . $e->getMessage());
            $message = "ブックマークの更新中にエラーが発生しました: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// フォーム表示用に現在のデータを設定
$form_url = htmlspecialchars($bookmark['url'] ?? '');
$form_title = htmlspecialchars($bookmark['title'] ?? '');
$form_description = htmlspecialchars($bookmark['description'] ?? '');
$form_is_public = $bookmark['is_public'] ?? 0;
$form_is_starred = $bookmark['is_starred'] ?? 0;
$form_tags = htmlspecialchars(implode(', ', $bookmark['tags'] ?? []));

// メッセージがセッションにあれば取得して表示
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ブックマーク編集</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>ブックマーク編集</h1>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?> show"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form action="edit.php?id=<?php echo htmlspecialchars($bookmark_id); ?><?php echo $is_public_edit ? '&public=true' : ''; ?>" method="POST" class="bookmark-form">
            <div class="form-section">
                <h2>基本情報</h2>
                <div class="form-group">
                    <label for="url">URL:</label>
                    <div class="url-input-group">
                        <input type="url" id="url" name="url" value="<?php echo $form_url; ?>" required>
                        <button type="button" class="fetch-button" id="fetch-ogp">OGP自動取得</button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="title">タイトル:</label>
                    <input type="text" id="title" name="title" value="<?php echo $form_title; ?>" required>
                </div>
                <div class="form-group">
                    <label for="description">説明:</label>
                    <textarea id="description" name="description"><?php echo $form_description; ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h2>オプション</h2>
                <div class="form-group">
                    <input type="checkbox" id="is_public" name="is_public" value="1" <?php echo $form_is_public ? 'checked' : ''; ?>>
                    <label for="is_public">公開する</label>
                    <span class="help-text">チェックを入れると、このブックマークが公開ブックマーク一覧に表示されます。</span>
                </div>
                <div class="form-group">
                    <input type="checkbox" id="is_starred" name="is_starred" value="1" <?php echo $form_is_starred ? 'checked' : ''; ?>>
                    <label for="is_starred">お気に入り</label>
                    <span class="help-text">チェックを入れると、お気に入りとしてマークされます。</span>
                </div>
                <div class="form-group">
                    <label for="tags">タグ (カンマ区切り):</label>
                    <input type="text" id="tags" name="tags" value="<?php echo $form_tags; ?>" placeholder="例: Web, 技術, デザイン">
                    <span class="help-text">複数のタグはカンマ (,) で区切ってください。</span>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="common-button submit-button">更新</button>
                <a href="<?php echo $redirect_after_save; ?>" class="common-button back-button">キャンセル</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // OGP自動取得機能
            const fetchOgpButton = document.getElementById('fetch-ogp');
            if (fetchOgpButton) {
                fetchOgpButton.addEventListener('click', async function() {
                    const urlInput = document.getElementById('url');
                    const titleInput = document.getElementById('title');
                    const descriptionInput = document.getElementById('description');
                    const url = urlInput.value;

                    if (!url) {
                        alert('URLを入力してください。');
                        return;
                    }

                    this.textContent = '取得中...';
                    this.disabled = true;

                    try {
                        const response = await fetch('fetch_ogp.php?url=' + encodeURIComponent(url));
                        const data = await response.json();

                        if (data.success) {
                            if (data.title && !titleInput.value) { // 既存のタイトルがない場合のみ設定
                                titleInput.value = data.title;
                            }
                            if (data.description && !descriptionInput.value) { // 既存の説明がない場合のみ設定
                                descriptionInput.value = data.description;
                            }
                        } else {
                            alert('OGP情報の取得に失敗しました: ' + data.message);
                        }
                    } catch (error) {
                        console.error('OGP取得エラー:', error);
                        alert('OGP情報の取得中に予期せぬエラーが発生しました。');
                    } finally {
                        this.textContent = 'OGP自動取得';
                        this.disabled = false;
                    }
                });
            }

            // メッセージの自動非表示
            const messages = document.querySelectorAll('.message.show');
            messages.forEach(message => {
                setTimeout(() => {
                    message.classList.remove('show');
                }, 5000);
            });
        });
    </script>
</body>
</html>