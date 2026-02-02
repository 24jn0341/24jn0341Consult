<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>レシートOCRシステム</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <h1 class="mb-4 text-center">レシート解析システム (FamilyMart対応)</h1>
        
        <div class="card p-4 shadow-sm">
            <form action="process.php" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">レシート画像 (複数選択可)</label>
                    <input type="file" class="form-control" name="receipts[]" multiple accept="image/*" required>
                    <div class="form-text">ファミリーマートのレシート画像を選択してください。</div>
                </div>
                <button type="submit" class="btn btn-primary w-100">解析してDB保存＆CSV出力</button>
            </form>
        </div>
    </div>
</body>
</html>