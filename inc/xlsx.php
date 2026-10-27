<?php
/**
 * อ่าน/เขียนไฟล์ .xlsx แบบไม่ต้องใช้ ext-zip (ใช้ zlib + SimpleXML) — ดัดแปลงจาก vec.plan ให้รันได้ PHP 5.4 และรองรับหลายแผ่นงาน
 * เขียน: BpXlsx::build(array('ชื่อแผ่น' => array(แถว...))) แถวแรกของแต่ละแผ่น = หัวตาราง (ตัวหนา พื้นแดงอ่อน)
 * อ่าน:  BpXlsx::read($path) → array('ชื่อแผ่น' => array(แถว => array(คอลัมน์ => ข้อความ)))
 */
class BpXlsx
{
    // ------------------------------------------------------------------ read
    public static function read($path)
    {
        $zip = self::unzip(file_get_contents($path));
        $shared = array();
        if (isset($zip['xl/sharedStrings.xml'])) {
            $x = self::xml($zip['xl/sharedStrings.xml']);
            foreach ($x->si as $si) {
                if (isset($si->t)) $shared[] = (string)$si->t;
                else {
                    $s = '';
                    foreach ($si->r as $r) $s .= (string)$r->t;
                    $shared[] = $s;
                }
            }
        }
        if (!isset($zip['xl/workbook.xml'])) throw new RuntimeException('ไฟล์ไม่ใช่ .xlsx ที่ถูกต้อง');
        $targets = array();
        if (isset($zip['xl/_rels/workbook.xml.rels'])) {
            $rels = self::xml($zip['xl/_rels/workbook.xml.rels']);
            foreach ($rels->Relationship as $rel) {
                $t = ltrim((string)$rel['Target'], '/');
                $targets[(string)$rel['Id']] = strpos($t, 'xl/') === 0 ? $t : 'xl/' . $t;
            }
        }
        $wb = self::xml($zip['xl/workbook.xml']);
        $out = array();
        $i = 0;
        foreach ($wb->sheets->sheet as $sheet) {
            $i++;
            $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $rid = (string)$attrs['id'];
            $p = isset($targets[$rid]) ? $targets[$rid] : 'xl/worksheets/sheet' . $i . '.xml';
            if (!isset($zip[$p])) continue;
            $out[(string)$sheet['name']] = self::sheetRows(self::xml($zip[$p]), $shared);
        }
        return $out;
    }

    private static function sheetRows($sheet, $shared)
    {
        $rows = array();
        if (!isset($sheet->sheetData->row)) return $rows;
        foreach ($sheet->sheetData->row as $row) {
            $cells = array();
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];
                $col = $ref !== '' ? self::colIndex($ref) : count($cells);
                $t = (string)$c['t'];
                if ($t === 's') { $k = (int)$c->v; $v = isset($shared[$k]) ? $shared[$k] : ''; }
                elseif ($t === 'inlineStr') {
                    if (isset($c->is->t)) $v = (string)$c->is->t;
                    else { $v = ''; if (isset($c->is->r)) foreach ($c->is->r as $r) $v .= (string)$r->t; }
                }
                elseif ($t === 'b') $v = ((string)$c->v) === '1' ? 'TRUE' : 'FALSE';
                else $v = (string)$c->v;
                $cells[$col] = trim($v);
            }
            if (!$cells) continue;
            $line = array();
            for ($k = 0, $mx = max(array_keys($cells)); $k <= $mx; $k++) $line[$k] = '';
            foreach ($cells as $k => $v) $line[$k] = $v;
            if (implode('', $line) === '') continue;
            $rows[] = $line;
        }
        return $rows;
    }

    private static function colIndex($ref)
    {
        preg_match('/^([A-Z]+)/', strtoupper($ref), $m);
        $n = 0;
        foreach (str_split(isset($m[1]) ? $m[1] : 'A') as $ch) $n = $n * 26 + (ord($ch) - 64);
        return $n - 1;
    }

    private static function xml($s)
    {
        // PHP < 8 ต้องปิดการโหลด entity ภายนอกเอง (PHP 8 ปิดไว้แล้ว และฟังก์ชันนี้เลิกใช้)
        $old = PHP_VERSION_ID < 80000 && function_exists('libxml_disable_entity_loader');
        if ($old) $prev = libxml_disable_entity_loader(true);
        $x = simplexml_load_string($s, 'SimpleXMLElement', LIBXML_NONET);
        if ($old) libxml_disable_entity_loader($prev);
        if ($x === false) throw new RuntimeException('อ่านไฟล์ Excel ไม่ได้ (ไฟล์เสีย)');
        return $x;
    }

    /** แตกไฟล์ zip (ใช้กับ .xlsx และ zip อัปเดตจาก GitHub) → array(ชื่อไฟล์ => เนื้อไฟล์) */
    public static function unzip($data)
    {
        $eocd = strrpos($data, "PK\x05\x06");
        if ($eocd === false) throw new RuntimeException('ไฟล์ไม่ใช่ zip/.xlsx ที่ถูกต้อง');
        $e = unpack('vdisk/vcdDisk/ventriesDisk/ventries/Vsize/Voffset', substr($data, $eocd + 4, 16));
        $pos = $e['offset'];
        $files = array();
        for ($i = 0; $i < $e['entries']; $i++) {
            if (substr($data, $pos, 4) !== "PK\x01\x02") throw new RuntimeException('โครงสร้างไฟล์ zip เสีย');
            $h = unpack('vmade/vneed/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen/vclen/vdisk/vint/Vext/Voffset', substr($data, $pos + 4, 42));
            $name = substr($data, $pos + 46, $h['nlen']);
            $pos += 46 + $h['nlen'] + $h['elen'] + $h['clen'];
            $lh = unpack('vnlen/velen', substr($data, $h['offset'] + 26, 4));
            $start = $h['offset'] + 30 + $lh['nlen'] + $lh['elen'];
            $raw = substr($data, $start, $h['csize']);
            if ($h['method'] == 0) $files[$name] = $raw;
            elseif ($h['method'] == 8) {
                $out = @gzinflate($raw);
                if ($out === false) throw new RuntimeException('แตกไฟล์ zip ไม่ได้');
                $files[$name] = $out;
            }
        }
        return $files;
    }

    // ------------------------------------------------------------------ write
    private static function esc($s)
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$s);
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    /** $sheets = array('ชื่อแผ่น' => array(array(หัว...), array(ค่า...))) — ตัวเลข (int/float) = เซลล์ตัวเลข */
    public static function build($sheets)
    {
        $ct = '';
        $wbSheets = '';
        $wbRels = '';
        $files = array();
        $n = 0;
        foreach ($sheets as $name => $rows) {
            $n++;
            $widths = array();
            $xmlRows = '';
            foreach (array_values($rows) as $r => $row) {
                $xmlRows .= '<row r="' . ($r + 1) . '">';
                foreach (array_values($row) as $c => $v) {
                    $ref = self::colName($c) . ($r + 1);
                    $len = is_float($v) || is_int($v) ? 14 : min(60, mb_strlen((string)$v, 'UTF-8') * 1.1 + 2);
                    if (!isset($widths[$c]) || $widths[$c] < $len) $widths[$c] = $len;
                    if ($r === 0) $xmlRows .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t xml:space="preserve">' . self::esc($v) . '</t></is></c>';
                    elseif (is_float($v)) $xmlRows .= '<c r="' . $ref . '" s="2"><v>' . $v . '</v></c>';
                    elseif (is_int($v)) $xmlRows .= '<c r="' . $ref . '"><v>' . $v . '</v></c>';
                    elseif ((string)$v === '') continue;
                    else $xmlRows .= '<c r="' . $ref . '" t="inlineStr" s="3"><is><t xml:space="preserve">' . self::esc($v) . '</t></is></c>';
                }
                $xmlRows .= '</row>';
            }
            $cols = '';
            foreach ($widths as $c => $w) $cols .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . max(8, round($w)) . '" customWidth="1"/>';
            $files['xl/worksheets/sheet' . $n . '.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                . ($cols !== '' ? '<cols>' . $cols . '</cols>' : '') . '<sheetData>' . $xmlRows . '</sheetData></worksheet>';
            $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $wbSheets .= '<sheet name="' . self::esc(mb_substr(str_replace(array('/', '\\', '?', '*', '[', ']', ':'), ' ', $name), 0, 31, 'UTF-8')) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $wbRels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
        }
        $files['[Content_Types].xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $ct . '</Types>';
        $files['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        $files['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wbSheets . '</sheets></workbook>';
        $files['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wbRels
            . '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $files['xl/styles.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Tahoma"/></font><font><b/><sz val="11"/><color rgb="FF7C0D12"/><name val="Tahoma"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFCECEC"/></patternFill></fill></fills>'
            . '<borders count="2"><border/><border><bottom style="medium"><color rgb="FFC9A227"/></bottom></border></borders><cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="4"><xf fontId="0"/><xf fontId="1" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1"/><xf fontId="0" numFmtId="164" applyNumberFormat="1"/><xf fontId="0" applyAlignment="1"><alignment wrapText="0" vertical="top"/></xf></cellXfs></styleSheet>';
        return self::zip($files);
    }

    private static function colName($i)
    {
        $s = '';
        for ($i++; $i > 0; $i = (int)floor(($i - 1) / 26)) $s = chr(65 + ($i - 1) % 26) . $s;
        return $s;
    }

    /** zip แบบบีบอัด (deflate) */
    private static function zip($files)
    {
        $data = '';
        $central = '';
        $offset = 0;
        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $len = strlen($content);
            $z = gzdeflate($content, 6);
            $zl = strlen($z);
            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 8, 0, 0x21, $crc, $zl, $len, strlen($name), 0) . $name . $z;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 8, 0, 0x21, $crc, $zl, $len, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
            $data .= $local;
            $offset += strlen($local);
        }
        return $data . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), $offset, 0);
    }
}
