// ===========================================
// グローバルスコープでヘルパー関数を定義
// (DOMContentLoaded の外側、かつ使用される前に定義する)
// ===========================================

/**
 * HTMLエスケープ処理を行う関数
 * @param {string} str - エスケープする文字列
 * @returns {string} エスケープされた文字列
 */
function escapeHTML(str) {
    // null や undefined などの非文字列を空文字列として扱う
    if (typeof str !== 'string') return '';
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

/**
 * ISO形式の文字列を読みやすい日付形式に変換する関数
 * @param {string} isoString - ISO形式の日付文字列 (例: '2025-07-10 13:57:36')
 * @returns {string} フォーマットされた日付文字列
 */
function formatDate(isoString) {
    // null や undefined などの不正な入力を空文字列として扱う
    if (!isoString) return '';
    // MySQLのDATETIME形式をJavaScriptのDateオブジェクトが解釈できるように調整
    // 'YYYY-MM-DD HH:MM:SS' -> 'YYYY-MM-DDTHH:MM:SS'
    const formattedString = isoString.replace(' ', 'T');
    const date = new Date(formattedString);

    // Dateオブジェクトが有効な日付を表しているかチェック
    if (isNaN(date.getTime())) {
        console.error("Invalid date string provided to formatDate:", isoString);
        return isoString; // 無効な場合は元の文字列を返すか、エラーメッセージを返す
    }

    return date.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}


// ===========================================
// DOMContentLoaded イベントリスナー
// = ドキュメントが完全に読み込まれた後に実行されるコード
// ===========================================
document.addEventListener('DOMContentLoaded', function() {

    // PHPから渡される初期データ INITIAL_BOOKMARK_DATA は
    // bookmark_detail.php の <script> タグで既に定義されているものとする。
    // ここで再定義すると "has already been declared" エラーになるため、再定義しない。

    const detailCard = document.querySelector('.detail-card');
    const prevButton = document.getElementById('prevBookmark'); // 左ボタン
    const nextButton = document.getElementById('nextBookmark'); // 右ボタン

    // 初期データが存在することを確認
    if (typeof INITIAL_BOOKMARK_DATA === 'undefined' || !INITIAL_BOOKMARK_DATA.id) {
        console.error("INITIAL_BOOKMARK_DATA is not defined or missing ID.");
        // 必要に応じてユーザーにエラーメッセージを表示
        alert('初期ブックマークデータの読み込みに失敗しました。');
        return; // 以降の処理を停止
    }

    let currentBookmarkId = INITIAL_BOOKMARK_DATA.id;

    // 初期状態でのボタンの data-bookmark-id と disabled 属性を設定
    // PHPから渡された INITIAL_BOOKMARK_DATA を使用
    if (prevButton) {
        if (INITIAL_BOOKMARK_DATA.newer_bookmark_id) { // PHP側のキー名に合わせる
            prevButton.disabled = false;
            prevButton.dataset.bookmarkId = INITIAL_BOOKMARK_DATA.newer_bookmark_id;
        } else {
            prevButton.disabled = true;
            prevButton.dataset.bookmarkId = '';
        }
    }

    if (nextButton) {
        if (INITIAL_BOOKMARK_DATA.older_bookmark_id) { // PHP側のキー名に合わせる
            nextButton.disabled = false;
            nextButton.dataset.bookmarkId = INITIAL_BOOKMARK_DATA.older_bookmark_id;
        } else {
            nextButton.disabled = true;
            nextButton.dataset.bookmarkId = '';
        }
    }


    // ブックマークデータを更新する関数
    async function updateBookmarkDetail(id) {
        if (!id) {
            // IDがnullやundefinedの場合は処理を中断
            console.warn("Attempted to load bookmark with null or undefined ID.");
            return;
        }

        detailCard.style.opacity = '0.5';

        try {
            const response = await fetch(`get_bookmark_data.php?id=${id}`);
            if (!response.ok) {
                // HTTPステータスコードが200以外の場合
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();

            // サーバーからのレスポンスの 'success' フラグと 'bookmark' オブジェクトを確認
            if (data.success && data.bookmark) {
                const bookmark = data.bookmark;

                document.title = `${bookmark.title} - みんなのブックマーク コレ見て！`;
                // URLを更新するが、ページのリロードはしない
                history.pushState(null, '', `bookmark_detail.php?id=${bookmark.id}`);
                currentBookmarkId = bookmark.id; // 現在のブックマークIDを更新

                // detailCard の中身を更新
                detailCard.innerHTML = `
                    <div class="detail-header">
                        <h2>${escapeHTML(bookmark.title)}</h2>
                        ${bookmark.is_starred ? '<span class="star-icon fas fa-star" title="投稿者のお気に入り"></span>' : ''}
                    </div>
                    <p class="url detail-item"><i class="fas fa-link"></i> <a href="${escapeHTML(bookmark.url)}" target="_blank" rel="noopener noreferrer">${escapeHTML(bookmark.url)}</a></p>
                    <p class="description detail-item">${escapeHTML(bookmark.description).replace(/\n/g, '<br>')}</p>
                    ${bookmark.tags ? `
                        <div class="tags detail-item">
                            ${bookmark.tags.split(', ').map(tag => `<span class="tag public-tag"><i class="fas fa-tag"></i> ${escapeHTML(tag)}</span>`).join('')}
                        </div>
                    ` : ''}
                    <div class="detail-meta detail-item">
                        <span><i class="fas fa-calendar-alt"></i> 投稿日: ${formatDate(bookmark.created_at)}</span>
                        <span><i class="fas fa-user"></i> 投稿者: ${escapeHTML(bookmark.username)}</span>
                        <span class="public-status"><i class="fas fa-globe"></i> 公開</span>
                    </div>
                    ${(INITIAL_BOOKMARK_DATA.is_admin) ? ` <div class="detail-admin-actions">
                            <a href="edit.php?id=${escapeHTML(bookmark.id)}&from=public_detail" class="common-button edit-button"><i class="fas fa-edit"></i> 編集</a>
                            <button onclick="window.confirmDelete(${escapeHTML(bookmark.id)}, true)" class="common-button delete-button"><i class="fas fa-trash-alt"></i> 削除</button> </div>
                    ` : ''}
                `;

                // 左右のボタンの data-bookmarkId と disabled 属性を更新
                if (data.newer_bookmark_id) {
                    prevButton.disabled = false;
                    prevButton.dataset.bookmarkId = data.newer_bookmark_id;
                } else {
                    prevButton.disabled = true;
                    prevButton.dataset.bookmarkId = '';
                }

                if (data.older_bookmark_id) {
                    nextButton.disabled = false;
                    nextButton.dataset.bookmarkId = data.older_bookmark_id;
                } else {
                    nextButton.disabled = true;
                    nextButton.dataset.bookmarkId = '';
                }

            } else {
                // 'success' が false の場合、または 'bookmark' オブジェクトがない場合
                alert('ブックマークの読み込みに失敗しました: ' + (data.message || '不明なエラーです。'));
                console.error('Bookmark load error:', data.message, data);
            }
        } catch (error) {
            console.error('Fetch error:', error);
            // ネットワークエラーやJSONパースエラーなどの場合にアラート
            alert('データの取得中にエラーが発生しました。');
        } finally {
            detailCard.style.opacity = '1'; // 処理終了後に不透明度を元に戻す
        }
    }


    // ===========================================
    // イベントリスナーのセットアップ
    // ===========================================

    // prevButton と nextButton にイベントリスナーを設定
    if (prevButton) {
        prevButton.addEventListener('click', function() {
            const idToLoad = this.dataset.bookmarkId;
            // idToLoad が有効な場合にのみ updateBookmarkDetail を呼び出す
            updateBookmarkDetail(idToLoad);
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', function() {
            const idToLoad = this.dataset.bookmarkId;
            // idToLoad が有効な場合にのみ updateBookmarkDetail を呼び出す
            updateBookmarkDetail(idToLoad);
        });
    }

    // ブラウザの戻る/進むボタンに対応
    window.addEventListener('popstate', function(event) {
        const urlParams = new URLSearchParams(window.location.search);
        const idFromUrl = urlParams.get('id');
        if (idFromUrl && idFromUrl != currentBookmarkId) {
            updateBookmarkDetail(idFromUrl);
        } else if (!idFromUrl && window.location.pathname.includes('bookmark_detail.php')) {
            // URLからIDがなくなった場合（例: public_bookmarks.phpに戻るなど）
            // このケースは通常起こらないが、念のため
            history.back(); // 前のページに戻るなど適切な処理
        }
    });

    // ページ読み込み時にURLのIDに基づいてブックマークを初期表示する
    // ただし、初回ロード時はPHP側で既にHTMLが生成されているため、
    // AJAXでの取得は不要。しかし、ボタンのデータ属性は設定する必要がある。
    // PHPからINITIAL_BOOKMARK_DATAが既に提供されているので、
    // ここで updateBookmarkDetail を呼び出すのは不要。
    // 初回ロード時のボタンの状態はINITIAL_BOOKMARK_DATAに基づいて初期化済み。
    // もしURLにIDが指定されていて、かつINITIAL_BOOKMARK_DATA.idと異なる場合のみ更新。
    const initialUrlParams = new URLSearchParams(window.location.search);
    const initialIdFromUrl = initialUrlParams.get('id');

    // ページロード時にhistory.replaceState を実行し、URLをクリーンアップ
    // currentBookmarkId が存在することを前提とする
    if (currentBookmarkId) {
        history.replaceState(null, '', `bookmark_detail.php?id=${currentBookmarkId}`);
    }
});

// window.confirmDelete の定義は script.js の別の場所に既に存在するはずです。
// もし存在しない場合は、グローバルスコープ（DOMContentLoaded の外）に定義してください。
/*
window.confirmDelete = function(bookmarkId, isPublic = false) {
    if (confirm('本当にこのブックマークを削除しますか？')) {
        // 削除処理をここに書く
        // 例: window.location.href = `delete_bookmark.php?id=${bookmarkId}&is_public=${isPublic ? '1' : '0'}`;
        alert('削除処理は未実装です。ID: ' + bookmarkId + ', 公開: ' + isPublic);
    }
};
*/