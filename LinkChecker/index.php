<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

/**
 * Nimmt einen rohen Textblock und gibt ein Array von bereinigten, eindeutigen URLs zurück.
 */
function parseLinks(string $input): array
{
    $lines = preg_split('/\r?\n/', $input);
    if ($lines === false) {
        return [];
    }

    $cleaned = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }
        $cleaned[] = $trimmed;
    }

    return array_values(array_unique($cleaned));
}

/**
 * Prüft eine URL via cURL und liefert Details zurück.
 */
function checkUrl(string $url): array
{
    $result = [
        'url' => $url,
        'http_code' => 0,
        'ok' => false,
        'has_keywords' => false,
        'error' => null,
    ];

    $ch = curl_init();
    if ($ch === false) {
        $result['error'] = 'Konnte cURL nicht initialisieren.';
        return $result;
    }

    $commonOptions = [
        CURLOPT_URL => $url,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HEADER => false,
        CURLOPT_USERAGENT => 'LinkChecker/1.0',
    ];

    curl_setopt_array($ch, $commonOptions + [
        CURLOPT_NOBODY => true,
        CURLOPT_CUSTOMREQUEST => 'HEAD',
    ]);

    $body = '';
    $headResponse = curl_exec($ch);
    if ($headResponse === false) {
        $result['error'] = curl_error($ch) ?: 'Unbekannter cURL-Fehler bei HEAD.';
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode === 0) {
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => false,
            CURLOPT_HTTPGET => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);
        $bodyResponse = curl_exec($ch);
        if ($bodyResponse === false) {
            $result['error'] = curl_error($ch) ?: 'Unbekannter cURL-Fehler bei GET.';
        } else {
            $body = (string) $bodyResponse;
        }
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    } elseif ($httpCode === 200) {
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => false,
            CURLOPT_HTTPGET => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);
        $bodyResponse = curl_exec($ch);
        if ($bodyResponse === false) {
            if ($result['error'] === null) {
                $result['error'] = curl_error($ch) ?: 'Unbekannter cURL-Fehler bei GET.';
            }
        } else {
            $body = (string) $bodyResponse;
        }
    }

    curl_close($ch);

    $result['http_code'] = $httpCode;
    $result['ok'] = $httpCode >= 200 && $httpCode < 400;

    if ($body !== '') {
        $body = substr($body, 0, 2000);
        $keywords = ['gewinn', 'gewinnspiel', 'teilnahme', 'mitmachen'];
        foreach ($keywords as $keyword) {
            if (stripos($body, $keyword) !== false) {
                $result['has_keywords'] = true;
                break;
            }
        }
    }

    return $result;
}

$linksInput = $_POST['links'] ?? '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $links = parseLinks($linksInput);
    foreach ($links as $link) {
        $results[] = checkUrl($link);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>LinkChecker</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background-color: #f5f5f5; }
        h1 { color: #333; }
        form { margin-bottom: 20px; }
        textarea { width: 100%; height: 200px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #333; color: #fff; }
        tr.success { background-color: #e8f5e9; }
        tr.error { background-color: #ffebee; }
    </style>
</head>
<body>
    <h1>LinkChecker</h1>
    <p>Fügen Sie eine URL pro Zeile ein und klicken Sie auf „Links prüfen“.</p>
    <form method="post">
        <textarea name="links" placeholder="https://example.com
https://example.org"><?php echo htmlspecialchars($linksInput, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <br>
        <button type="submit">Links prüfen</button>
    </form>

    <?php if (!empty($results)): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>URL</th>
                    <th>HTTP-Code</th>
                    <th>Status</th>
                    <th>Gewinnspiel-Hinweis</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $index => $result): ?>
                    <?php
                        $statusText = $result['ok'] ? 'Erreichbar' : 'Fehler / nicht erreichbar';
                        $keywordText = $result['has_keywords'] ? 'Keywords gefunden' : 'Keine Keywords gefunden';
                        $rowClass = $result['ok'] ? 'success' : 'error';
                    ?>
                    <tr class="<?php echo $rowClass; ?>">
                        <td><?php echo $index + 1; ?></td>
                        <td><a href="<?php echo htmlspecialchars($result['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($result['url'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                        <td><?php echo (int) $result['http_code']; ?></td>
                        <td><?php echo htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($keywordText, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
