<?php
// エラー表示設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=UTF-8'); // JSONレスポンスのヘッダー

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = $_POST['url'] ?? '';

    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'error' => '有効なURLを入力してください。']);
        exit();
    }

    $title = '';
    $description = '';

    // cURLを使ってURLの内容を取得
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 結果を文字列で取得
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // リダイレクトを追跡
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // 最大リダイレクト数
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // タイムアウト
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL証明書の検証をスキップ (開発時のみ、本番ではtrueが望ましい)
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // SSLホストの検証をスキップ (開発時のみ、本番ではtrueが望ましい)
    curl_setopt($ch, CURLOPT_HEADER, true); // ヘッダーも取得

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);

    if (curl_errno($ch) || $http_code >= 400) {
        $error_message = curl_error($ch);
        if ($http_code >= 400) {
            $error_message = "HTTPエラー: " . $http_code;
        }
        echo json_encode(['success' => false, 'error' => 'URLの内容取得に失敗しました: ' . $error_message]);
        curl_close($ch);
        exit();
    }
    curl_close($ch);

    // 文字コードの判定と変換
    $charset = null;

    // 1. Content-Type ヘッダーから文字コードを抽出
    if (preg_match('/Content-Type: [^;]+; charset=([^;\s]+)/i', $header, $matches)) {
        $charset = trim($matches[1]);
    }

    // 2. HTMLのmetaタグから文字コードを抽出 (ヘッダーになかった場合)
    if (!$charset && preg_match('/<meta[^>]+charset=["\']?([^"\'\s>]+)["\']?/i', $body, $matches)) {
        $charset = trim($matches[1]);
    }
    if (!$charset && preg_match('/<meta[^>]+http-equiv=["\']Content-Type["\'][^>]+content=["\'][^;]+;\s*charset=([^"\']+)/i', $body, $matches)) {
        $charset = trim($matches[1]);
    }
    
    // 取得した文字コードを小文字に変換して統一
    if ($charset) {
        $charset = strtolower($charset);
        // "utf8" のような表記は "utf-8" に修正
        if ($charset === 'utf8') {
            $charset = 'utf-8';
        }
    }


    // 3. 最終的な文字コード変換
    if ($charset && strcasecmp($charset, 'utf-8') !== 0) {
        // 取得した文字コードがUTF-8でなければ変換
        // 変換元の文字コードを正確に指定できない場合は、autoを試す
        $converted_body = mb_convert_encoding($body, 'UTF-8', $charset);
        if ($converted_body === false) {
             // 変換失敗の場合、念のためmb_detect_encodingに頼る
             $converted_body = mb_convert_encoding($body, 'UTF-8', mb_detect_encoding($body, 'UTF-8,SJIS,EUC-JP,JIS,ASCII', true));
        }
    } else {
        // 既にUTF-8であるか、文字コードが特定できない場合はそのまま
        // 特定できない場合は、後述のdomによる自動判別に期待
        $converted_body = $body;
    }

    // DOMDocumentを使ってHTMLをパース
    $dom = new DOMDocument();
    // エラーを抑制 (HTML5の新しいタグなどで警告が出ることがあるため)
    @$dom->loadHTML($converted_body); 

    $xpath = new DOMXPath($dom);

    // タイトルを取得
    $titleNodes = $xpath->query('//title');
    if ($titleNodes->length > 0) {
        $title = $titleNodes->item(0)->textContent;
    }

    // 説明 (meta description) を取得
    $descriptionNodes = $xpath->query('//meta[@name="description"]/@content');
    if ($descriptionNodes->length > 0) {
        $description = $descriptionNodes->item(0)->textContent;
    }

    // 結果をJSON形式で返す
    echo json_encode([
        'success' => true,
        'title' => $title,
        'description' => $description
    ]);

} else {
    echo json_encode(['success' => false, 'error' => '不正なリクエストです。']);
}
?>