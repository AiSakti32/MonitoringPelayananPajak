<?php
$f = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Form input WEB.xlsx';
if (!is_file($f)) {
    fwrite(STDERR, "missing xlsx\n");
    exit(1);
}
$z = new ZipArchive();
if ($z->open($f) !== true) {
    fwrite(STDERR, "cannot open\n");
    exit(1);
}
$shared = [];
$ss = $z->getFromName('xl/sharedStrings.xml');
if ($ss) {
    preg_match_all('/<si>(.*?)<\/si>/s', $ss, $sis);
    foreach ($sis[1] as $si) {
        if (preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $si, $tm)) {
            $shared[] = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
        } else {
            $shared[] = '';
        }
    }
}
$sheet = $z->getFromName('xl/worksheets/sheet1.xml');
preg_match_all('/<c r="([A-Z]+)(\d+)"([^>]*)>(?:<v>(.*?)<\/v>)?/s', $sheet, $m, PREG_SET_ORDER);
$rows = [];
foreach ($m as $c) {
    $col = $c[1];
    $row = (int) $c[2];
    $attrs = $c[3];
    $v = $c[4] ?? '';
    if (str_contains($attrs, 't="s"')) {
        $v = $shared[(int) $v] ?? $v;
    }
    $rows[$row][$col] = $v;
}
ksort($rows);
foreach ($rows as $r => $cols) {
    ksort($cols);
    echo "ROW $r: " . json_encode($cols, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
$z->close();
