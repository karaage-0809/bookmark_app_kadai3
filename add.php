<?php
// エラー表示設定（開発中は残す）
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // ★★★ セッションを開始 ★★★

// --- ここから認証チェックロジック ---
// ユーザーがログインしているかチェック
if (!isset($_SESSION['user_id'])) {
    // ログインしていなければログインページへリダイレクト
    $_SESSION['login_error'] = "ブックマークを追加するにはログインが必要です。";
    header('Location: login.php');
    exit();
}
// --- ここまで認証チェックロジック ---

require_once 'db_connect.php';

// ★★★ ログインしているユーザーのIDを取得 ★★★
$user_id = $_SESSION['user_id']; 

// メッセージ変数の初期化
$success_message = '';
$error_message = '';
$url = $_POST['url'] ?? ''; // POSTされた値で初期化しておく
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$tags_input = $_POST['tags'] ?? '';

// POSTリクエストが送信された場合の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // フォームからデータを受け取る (上記で初期化済みだが念のため再代入)
    $url = $_POST['url'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $tags_input = $_POST['tags'] ?? ''; // 新しくタグの入力値を受け取る
    $is_public = isset($_POST['is_public']) ? 1 : 0; // ★追加：is_publicの値を取得

    // バリデーション（簡単な例）
    if (empty($url) || empty($title)) {
        $error_message = "URLとタイトルは必須項目です。";
    } else if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error_message = "有効なURLを入力してください。";
    } else {
        // --- 複合ユニークキー違反の事前チェック ---
        try {
            // 同じユーザーが同じURLを既に登録していないかチェック
            // ここでは、url(255)のようなプレフィックスインデックスを使っている場合でも、
            // 完全一致でエラーメッセージを出すように。
            $stmt_check_duplicate = $pdo->prepare("SELECT id FROM bookmarks WHERE url = ? AND user_id = ?");
            $stmt_check_duplicate->execute([$url, $user_id]);
            if ($stmt_check_duplicate->fetch()) {
                $error_message = "このURLはすでにあなたのブックマークとして登録されています。";
                // エラー時は、フォームの入力値を保持したまま処理を中断し、HTML表示に進む
                goto end_of_post_processing; // ★ goto文を使用して処理をスキップ
            }

            $pdo->beginTransaction(); // トランザクションを開始

            // 1. ブックマークを `bookmarks` テーブルに挿入
            // ★★★ user_id と is_public カラムを追加し、その値をバインドする ★★★
            $stmt = $pdo->prepare("INSERT INTO bookmarks (user_id, url, title, description, is_public, created_at) VALUES (?, ?, ?, ?, ?, NOW())"); // ★変更
            $stmt->execute([$user_id, $url, $title, $description, $is_public]); // ★変更：$is_public をここに追加
            $bookmark_id = $pdo->lastInsertId(); // 挿入されたブックマークのIDを取得

            // 2. タグの処理
            if (!empty($tags_input)) {
                // カンマで分割し、余分な空白を削除
                $tags_array = array_map('trim', explode(',', $tags_input));
                // 空のタグを除去し、重複をなくす
                $tags_array = array_unique(array_filter($tags_array));

                foreach ($tags_array as $tag_name) {
                    // 既にタグが存在するかチェック
                    $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
                    $stmt->execute([$tag_name]);
                    $tag = $stmt->fetch();

                    if ($tag) {
                        // 既存のタグの場合、そのIDを使用
                        $tag_id = $tag['id'];
                    } else {
                        // 新しいタグの場合、`tags` テーブルに挿入
                        $stmt = $pdo->prepare("INSERT INTO tags (name) VALUES (?)");
                        $stmt->execute([$tag_name]);
                        $tag_id = $pdo->lastInsertId();
                    }

                    // `bookmark_tags` 中間テーブルに挿入
                    // 重複挿入エラーを避けるため、ON DUPLICATE KEY UPDATE を使用
                    $stmt = $pdo->prepare("INSERT INTO bookmark_tags (bookmark_id, tag_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE bookmark_id=bookmark_id");
                    $stmt->execute([$bookmark_id, $tag_id]);
                }
            }

            $pdo->commit(); // トランザクションをコミット

            $success_message = "ブックマークとタグが正常に追加されました！";
            // フォームをクリアするために変数をリセット
            $url = $title = $description = $tags_input = '';
            
            // 成功したらブックマーク一覧ページにリダイレクト
            // メッセージをGETパラメータで渡すか、セッションで渡すか選択
            header('Location: index.php?success_message=' . urlencode($success_message));
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack(); // エラー時はトランザクションをロールバック
            // ★★★ ここでの重複エラーチェックは、上記の事前チェックでほとんど捕捉されるはずですが、
            // 念のため残しておきます。
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error_message = "このURLはすでにあなたのブックマークとして登録されています。（DBエラー）"; // 発生しにくいが念のため
            } else {
                $error_message = "ブックマークの追加中にエラーが発生しました: " . $e->getMessage();
            }
            error_log('Error adding bookmark in add.php (POST): ' . $e->getMessage()); // ログをより詳細に
            // エラー時は、フォームの入力値は既に保持されているので何もしない
        }
    }
}
end_of_post_processing: // goto文のターゲット

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ブックマークの追加</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>新しいブックマークを追加</h1>
        <p><a href="index.php">ブックマーク一覧に戻る</a></p> 
        <?php // 成功/エラーメッセージの表示
        if (!empty($success_message)): ?>
            <div class="message success show"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error show"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form action="add.php" method="POST" class="bookmark-form">
            <div class="form-section">
                <h2>基本情報</h2>
                <div class="form-group">
                    <label for="url">URL:</label>
                    <div class="url-input-group">
                        <input type="url" id="url" name="url" required placeholder="例: https://example.com" value="<?php echo htmlspecialchars($url ?? ''); ?>">
                        <button type="button" id="fetchUrlInfo" class="fetch-button" title="URLからタイトルと説明を自動取得">取得</button>
                    </div>
                    <div id="loadingMessage" class="info-message" style="display: none;">情報を取得中...</div>
                    <div id="fetchErrorMessage" class="error-message" style="display: none;"></div>
                </div>

                <div class="form-group">
                    <label for="title">タイトル:</label>
                    <input type="text" id="title" name="title" required placeholder="Webサイトのタイトルを入力" value="<?php echo htmlspecialchars($title ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="description">説明:</label>
                    <textarea id="description" name="description" rows="5" placeholder="ブックマークの説明を入力"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h2>タグ</h2>
                <div class="form-group">
                    <label for="tags">タグ (カンマ区切り):</label>
                    <input type="text" id="tags" name="tags" placeholder="例: ニュース, 技術, プログラミング" value="<?php echo htmlspecialchars($tags_input ?? ''); ?>">
                    <small class="help-text">複数のタグはカンマ `,` で区切ってください。</small>
                </div>
            </div>

            <div class="form-section">
                <h2>公開設定</h2>
                <div class="form-group">
                    <input type="checkbox" id="is_public" name="is_public" value="1">
                    <label for="is_public">このブックマークを公開する</label>
                    <small class="help-text">チェックすると、ログインしていないユーザーもこのブックマークを閲覧できるようになります。</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="submit-button">ブックマークを追加</button>
                <a href="index.php" class="common-button back-button">キャンセルして一覧に戻る</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // メッセージを自動的に非表示にする
            const messages = document.querySelectorAll('.message.show');
            messages.forEach(message => {
                setTimeout(() => {
                    message.classList.remove('show');
                    // 完全にDOMから削除したい場合は以下も追加
                    // message.parentNode.removeChild(message); 
                }, 5000); // 5秒後に非表示
            });

            const urlInput = document.getElementById('url');
            const fetchButton = document.getElementById('fetchUrlInfo');
            const titleInput = document.getElementById('title');
            const descriptionInput = document.getElementById('description');
            const loadingMessage = document.getElementById('loadingMessage');
            const fetchErrorMessage = document.getElementById('fetchErrorMessage');

            if (fetchButton) {
                fetchButton.addEventListener('click', function() {
                    const url = urlInput.value.trim();
                    if (!url) {
                        fetchErrorMessage.textContent = 'URLを入力してください。';
                        fetchErrorMessage.style.display = 'block';
                        return;
                    }

                    fetchErrorMessage.style.display = 'none';
                    loadingMessage.style.display = 'block';
                    fetchButton.disabled = true;

                    const formData = new FormData();
                    formData.append('url', url);

                    fetch('fetch_url_info.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('ネットワークエラー: ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(data => {
                        loadingMessage.style.display = 'none';

                        if (data.success) {
                            // タイトルが未入力の場合のみ自動入力
                            if (!titleInput.value.trim() && data.title) {
                                titleInput.value = data.title;
                            }
                            // 説明が未入力の場合のみ自動入力
                            if (!descriptionInput.value.trim() && data.description) {
                                descriptionInput.value = data.description;
                            }
                        } else {
                            fetchErrorMessage.textContent = '情報の取得に失敗しました: ' + (data.error || '不明なエラー');
                            fetchErrorMessage.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        loadingMessage.style.display = 'none';
                        fetchErrorMessage.textContent = '情報の取得中にエラーが発生しました: ' + error.message;
                        fetchErrorMessage.style.display = 'block';
                        console.error('Fetch error:', error);
                    })
                    .finally(() => {
                        fetchButton.disabled = false;
                    });
                });
            }
        });
    </script>
</body>
</html>