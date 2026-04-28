<?php
// This one is much better than mincss.php and https://www.minifier.org/
// css directory and file extensions
$cssDir = __DIR__ . '/../public/css/';
$cssExt = '.css';
$mincssExt = '.min.css';

function minify_css($css) {
    $url = 'https://www.toptal.com/developers/cssminifier/api/raw';

    // init the request, set various options, and send it
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/x-www-form-urlencoded"],
        CURLOPT_POSTFIELDS => http_build_query([ "input" => $css ])
    ]);

    $minified = curl_exec($ch);

    // output the $minified CSS
    return $minified;
}

// list of all css files
$cssFiles = [
    'style',
    'admin'
];

// Minify each of them
foreach ($cssFiles as $file) {
    $origfile = $cssDir . $file . $cssExt;
    $css = file_get_contents($origfile);
    $css = minify_css($css);
    $minfile = $cssDir . $file . $mincssExt;
    file_put_contents($minfile, $css);
    echo $minfile . " is minified successfully!\n";
}

?>
