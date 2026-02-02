<?php
// ==========================================================
// 設定エリア (自分のAzureキーとエンドポイントに書き換えてください)
// ==========================================================
$subscriptionKey = '★ここにキーを貼り付け★';
$endpoint = '★ここにエンドポイントを貼り付け★'; // 例: https://your-resource.cognitiveservices.azure.com/
// ==========================================================

// SQLiteデータベースの準備 (自動作成されます)
$db = new PDO('sqlite:receipts.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// テーブルが存在しなければ作成
$db->exec("CREATE TABLE IF NOT EXISTS receipts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_name TEXT,
    item_name TEXT,
    price INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// エラー表示設定
ini_set('display_errors', 0);
$ocrLogFile = 'ocr.log';
$csvFile = 'result.csv';

// CSVの初期化
$fp = fopen($csvFile, 'w');
// BOMを付けてExcelでの文字化けを防ぐ
fwrite($fp, "\xEF\xBB\xBF");
fputcsv($fp, ['ファイル名', '抽出結果']);
fclose($fp);

$resultsForDisplay = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['receipts'])) {
    
    // アップロードされたファイルを1つずつ処理
    $count = count($_FILES['receipts']['name']);
    
    for ($i = 0; $i < $count; $i++) {
        $tmpName = $_FILES['receipts']['tmp_name'][$i];
        $fileName = $_FILES['receipts']['name'][$i];

        if (is_uploaded_file($tmpName)) {
            // 画像データを読み込み
            $imageData = file_get_contents($tmpName);

            // 1. Azure AI Vision (Read API) に画像を送信
            $url = rtrim($endpoint, '/') . '/vision/v3.2/read/analyze';
            
            $headers = [
                'Content-Type: application/octet-stream',
                'Ocp-Apim-Subscription-Key: ' . $subscriptionKey
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $imageData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true); // ヘッダーを取得する
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $responseHeader = substr($response, 0, $headerSize);
            curl_close($ch);

            if ($httpCode !== 202) {
                $resultsForDisplay[] = ["ファイル" => $fileName, "結果" => "エラー: API送信失敗 ($httpCode)"];
                continue;
            }

            // 2. Operation-Locationを取得して結果をポーリング
            preg_match('/Operation-Location: (.*)\r\n/i', $responseHeader, $matches);
            $operationLocation = trim($matches[1]);

            $analysis = [];
            // 最大10回(10秒)待機して結果を取りに行く
            for ($retry = 0; $retry < 10; $retry++) {
                sleep(1);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $operationLocation);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Ocp-Apim-Subscription-Key: ' . $subscriptionKey]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $resultJson = curl_exec($ch);
                curl_close($ch);

                $analysis = json_decode($resultJson, true);
                if (isset($analysis['status']) && $analysis['status'] === 'succeeded') {
                    break;
                }
            }

            // 3. ocr.log に生データを保存
            $logEntry = "=== File: $fileName ===\n" . print_r($analysis, true) . "\n\n";
            file_put_contents($ocrLogFile, $logEntry, FILE_APPEND);

            // 4. 解析ロジック (ファミリーマート特化)
            $lines = $analysis['analyzeResult']['readResults'][0]['lines'] ?? [];
            $items = [];
            $totalAmount = 0;
            $itemsString = "";

            foreach ($lines as $line) {
                $text = $line['text'];

                // 「合計」行の処理
                if (strpos($text, '合') !== false && strpos($text, '計') !== false) {
                    preg_match('/¥([0-9,]+)/', $text, $m);
                    if (isset($m[1])) {
                        $totalAmount = str_replace(',', '', $m[1]);
                    }
                    continue;
                }

                // 商品行の処理 (¥が含まれ、かつ除外ワードが含まれない)
                if (strpos($text, '¥') !== false) {
                    // 除外ワード (小計、釣銭、対象など)
                    if (preg_match('/(小計|釣銭|預り|対象|税|ポイント)/u', $text)) continue;

                    // 不要な文字の削除 (◎, 軽, 半角スペース)
                    $cleanText = str_replace(['◎', '軽', ' '], '', $text);
                    
                    // 商品名と価格の分離
                    // 例: "ザバスプロテイン¥247" -> Name:ザバスプロテイン, Price:247
                    if (preg_match('/^(.*?)¥([0-9,]+)$/u', $cleanText, $matches)) {
                        $pName = $matches[1];
                        $pPrice = str_replace(',', '', $matches[2]);
                        
                        $items[] = "{$pName} ¥{$pPrice}";
                        
                        // データベースに保存
                        $stmt = $db->prepare("INSERT INTO receipts (file_name, item_name, price) VALUES (?, ?, ?)");
                        $stmt->execute([$fileName, $pName, $pPrice]);
                    }
                }
            }

            // 結果文字列の作成
            if ($totalAmount > 0) {
                $finalString = implode(', ', $items) . ", 合計 ¥{$totalAmount}";
            } else {
                $finalString = "データ抽出失敗 (ログを確認してください)";
            }

            // CSVに追記
            $fp = fopen($csvFile, 'a');
            fputcsv($fp, [$fileName, $finalString]);
            fclose($fp);

            $resultsForDisplay[] = ["ファイル" => $fileName, "結果" => $finalString];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>解析結果</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <h2 class="mb-4">解析結果</h2>
        
        <?php if (!empty($resultsForDisplay)): ?>
            <div class="card p-4 mb-4">
                <table class="table table-striped">
                    <thead><tr><th>ファイル名</th><th>抽出データ</th></tr></thead>
                    <tbody>
                        <?php foreach ($resultsForDisplay as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['ファイル']) ?></td>
                                <td><?= htmlspecialchars($row['結果']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="d-grid gap-2">
            <a href="result.csv" class="btn btn-success" download>CSVをダウンロード</a>
            <a href="ocr.log" class="btn btn-secondary" download>ocr.logをダウンロード</a>
            <a href="index.php" class="btn btn-outline-primary">戻る</a>
        </div>
    </div>
</body>
</html>