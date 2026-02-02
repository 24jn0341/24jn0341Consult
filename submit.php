<?php
// POSTリクエストが来た場合のみ処理を実行
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // フォームからデータを受け取る
    $name = isset($_POST['name']) ? $_POST['name'] : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $subject = isset($_POST['subject']) ? $_POST['subject'] : '';
    $message = isset($_POST['message']) ? $_POST['message'] : '';
    
    // 現在日時を取得
    $date = date("Y-m-d H:i:s");

    // CSVファイル名
    $filename = 'contacts.csv';
    
    // ファイルが存在しない場合は、ヘッダー行を追加するフラグを立てる
    $is_new_file = !file_exists($filename);

    // 追記モードでファイルを開く
    $fp = fopen($filename, 'a');

    if ($fp) {
        // 新規ファイルの場合、BOM（Excel文字化け対策）とヘッダーを書き込む
        if ($is_new_file) {
            fwrite($fp, "\xEF\xBB\xBF"); 
            fputcsv($fp, ['日時', '名前', 'Email', '件名', 'お問い合わせ内容']);
        }

        // データを書き込む
        fputcsv($fp, [$date, $name, $email, $subject, $message]);
        
        // ファイルを閉じる
        fclose($fp);
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>送信完了 | 株式会社Jecコンサルティング</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans JP', sans-serif; background-color: #f8f9fa; display: flex; align-items: center; height: 100vh; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card p-5 text-center">
                    <div class="mb-4 text-primary">
                        <i class="bi bi-check-circle-fill" style="font-size: 4rem;"></i>
                    </div>
                    <h2 class="mb-4">送信完了</h2>
                    <p class="lead mb-4"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?> 様<br>お問い合わせありがとうございます。</p>
                    <p class="text-muted mb-4">内容を受け付けました。<br>担当者より折り返しご連絡させていただきます。</p>
                    <a href="index.html" class="btn btn-primary btn-lg rounded-pill px-5">トップに戻る</a>
                </div>
            </div>
        </div>
    </div>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</body>
</html>