<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    <style>
        body { font-family: sans-serif; }
        .container {
            max-width: 400px; 
            margin: 50px auto; 
            padding: 20px; 
            border: 1px solid #ccc; 
            border-radius: 8px; 
        }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], input[type="password"] {
            width: 100%; 
            padding: 8px; 
            margin-bottom: 10px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            box-sizing: border-box;
        }
        button { 
            padding: 10px 15px; 
            background-color: #28a745; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
        }
        button:hover { background-color: #218838; }
        .error { color: red; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>ログイン</h2>
        <?php
        // ログイン失敗メッセージを表示するための簡易的なコード
        session_start(); // セッションを開始
        if (isset($_SESSION['login_error'])) {
            echo '<p class="error">' . $_SESSION['login_error'] . '</p>';
            unset($_SESSION['login_error']); // 一度表示したら削除
        }
        ?>
        <form action="login_process.php" method="post">
            <div>
                <label for="identifier">ユーザー名またはメールアドレス:</label>
                <input type="text" id="identifier" name="identifier" required>
            </div>
            <div>
                <label for="password">パスワード:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">ログイン</button>
        </form>
        <p>アカウントをお持ちでないですか？ <a href="register.php">新規登録はこちら</a></p>
    </div>
</body>
</html>