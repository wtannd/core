<?php
// css directory and file extensions
$cssDir = __DIR__ . '/../public/css/';
$cssExt = '.css';
$mincssExt = '.min.css';

function minify_css($css) {
    // 1. Remove comments
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    // 2. Remove spaces around selectors, braces, and colons
    $css = preg_replace('/\s*([{}|:;,])\s+/', '$1', $css);
    // 3. Remove remaining newlines, tabs, and multiple spaces
    $css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '   ', '    '), '', $css);
    return $css;
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
