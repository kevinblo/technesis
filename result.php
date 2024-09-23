<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL);

    if (!$url) {
        die("Неверный формат URL.");
    }

    function getPageContent($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $html = curl_exec($ch);
        curl_close($ch);
        return $html;
    }

    function getImages($html, $base_url) {
        preg_match_all('/<img[^>]+src=["\']?([^"\'>]+)["\']?/i', $html, $matches);
        $images = $matches[1];

        preg_match_all('/background-image:\s*url\(["\']?([^"\')]+)["\']?\)/i', $html, $bg_matches);
        $background_images = $bg_matches[1];

        preg_match_all('/<meta[^>]+content=["\']?([^"\'>]+)["\']?/i', $html, $meta_matches);
        $meta_images = array_filter($meta_matches[1], function($url) {
            return preg_match('/\.(jpg|jpeg|png)$/i', $url);
        });

        $all_images = array_merge($images, $background_images, $meta_images);

        $all_images = array_map(function($img) use ($base_url) {
            $img = str_replace('"', '', $img);
            return filter_var($img, FILTER_VALIDATE_URL) ? $img : $base_url . '/' . ltrim($img, '/');
        }, $all_images);

        return $all_images;
    }

    function getTotalSize($images) {
        $total_size = 0;
        foreach ($images as $img) {
            $headers = @get_headers($img, 1);
            if ($headers && isset($headers['Content-Length'])) {
                $total_size += $headers['Content-Length'];
            }
        }
        return $total_size;
    }

    $html = getPageContent($url);
    if (!$html) {
        die("Не удалось загрузить страницу.");
    }

    $images = getImages($html, $url);

    $image_count = count($images);

    $total_size = getTotalSize($images) / (1024 * 1024); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Result</title>
    <style>
        .image-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }
        .image-grid img {
            width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <h1>Найденные изображения</h1>

    <?php if ($image_count > 0): ?>
        <div class="image-grid">
            <?php foreach ($images as $image): ?>
                <div><img src="<?php echo $image; ?>" alt="Image"></div>
            <?php endforeach; ?>
        </div>
        <p>На странице обнаружено <?php echo $image_count; ?> изображений, общий размер: <?php echo round($total_size, 2); ?> МБ.</p>
    <?php else: ?>
        <p>Изображения не найдены.</p>
    <?php endif; ?>
</body>
</html>
