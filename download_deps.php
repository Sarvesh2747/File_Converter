<?php
// download_fpdf.php
$url = 'http://www.fpdf.org/en/dl.php?v=186&f=single';
$dest = 'includes/fpdf.php';

echo "Downloading FPDF...\n";
$content = file_get_contents($url);
if ($content === false) {
    die("Failed to download FPDF.");
}

// The download might be a zip or the file directly depending on the link?
// Actually fpdf.org usually gives a zip for the full package, but let's check.
// Using a direct github mirror often safer for raw files. 
// Let's use a reliable github raw url for fpdf 1.86 compatible.

$githubUrl = 'https://raw.githubusercontent.com/Setasign/FPDF/master/fpdf.php';
$content = file_get_contents($githubUrl);

if ($content) {
    if (!is_dir('includes')) mkdir('includes');
    file_put_contents($dest, $content);
    echo "FPDF downloaded successfully to $dest";
} else {
    echo "Failed to download from Github mirror.";
}
?>
