<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_aiquestiongen\local;

/**
 * Extracts plain text from uploaded article files.
 *
 * Supports .txt and .html/.htm (read directly), .docx (zip plus word/document.xml)
 * and .pdf (external pdftotext binary configured in the admin settings).
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_extractor {
    /** @var string[] File extensions this class can handle. */
    public const SUPPORTED_EXTENSIONS = ['txt', 'html', 'htm', 'docx', 'pdf'];

    /** @var int Maximum uncompressed size of word/document.xml that will be read (bytes). */
    private const MAX_DOCX_XML_SIZE = 20971520;

    /** @var string WordprocessingML main namespace. */
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Extract text from a stored file, using its filename extension to choose the method.
     *
     * @param \stored_file $file The file.
     * @return string Normalised plain text.
     * @throws \moodle_exception If the file cannot be read or is unsupported.
     */
    public static function extract_from_file(\stored_file $file): string {
        $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        // Check before copying the file to disk.
        if (!in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            throw new \moodle_exception('err_unsupportedtype', 'local_aiquestiongen', '', $extension);
        }
        $path = $file->copy_content_to_temp();
        if ($path === false) {
            throw new \moodle_exception('err_unreadable', 'local_aiquestiongen');
        }
        try {
            return self::extract_from_path($path, $extension);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Extract text from a file on disk.
     *
     * @param string $path Absolute path of the file.
     * @param string $extension Extension (without dot) deciding the format.
     * @param string|null $pdftotext Path to pdftotext; null uses the plugin setting.
     * @return string Normalised plain text.
     * @throws \moodle_exception If the file cannot be read or is unsupported.
     */
    public static function extract_from_path(string $path, string $extension, ?string $pdftotext = null): string {
        $extension = strtolower(ltrim($extension, '.'));
        if (!is_readable($path)) {
            throw new \moodle_exception('err_unreadable', 'local_aiquestiongen');
        }

        switch ($extension) {
            case 'txt':
                $text = self::extract_txt($path);
                break;
            case 'html':
            case 'htm':
                $text = self::extract_html($path);
                break;
            case 'docx':
                $text = self::extract_docx($path);
                break;
            case 'pdf':
                $text = self::extract_pdf($path, $pdftotext);
                break;
            default:
                throw new \moodle_exception('err_unsupportedtype', 'local_aiquestiongen', '', $extension);
        }

        return self::normalise_text($text);
    }

    /**
     * Read a plain text file.
     *
     * @param string $path File path.
     * @return string Text.
     */
    protected static function extract_txt(string $path): string {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \moodle_exception('err_unreadable', 'local_aiquestiongen');
        }
        return self::to_utf8($raw);
    }

    /**
     * Read an HTML file and reduce it to plain text.
     *
     * @param string $path File path.
     * @return string Text.
     */
    protected static function extract_html(string $path): string {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \moodle_exception('err_unreadable', 'local_aiquestiongen');
        }
        $html = self::to_utf8($raw);

        // Drop non-content elements and comments.
        $html = preg_replace('~<(script|style|head|noscript)\b[^>]*>.*?</\1\s*>~is', ' ', $html) ?? $html;
        $html = preg_replace('~<!--.*?-->~s', ' ', $html) ?? $html;
        // Keep paragraph and line structure.
        $html = preg_replace('~<br\s*/?>~i', "\n", $html) ?? $html;
        $html = preg_replace(
            '~</(p|div|h[1-6]|li|tr|ul|ol|table|blockquote|section|article|pre)\s*>~i',
            "\n\n",
            $html
        ) ?? $html;

        $text = strip_tags($html);
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Read a .docx file: a zip archive whose body lives in word/document.xml.
     *
     * @param string $path File path.
     * @return string Text, one paragraph per line.
     */
    protected static function extract_docx(string $path): string {
        if (!class_exists(\ZipArchive::class)) {
            throw new \moodle_exception('err_docxinvalid', 'local_aiquestiongen');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \moodle_exception('err_docxinvalid', 'local_aiquestiongen');
        }

        try {
            $stat = $zip->statName('word/document.xml');
            if ($stat === false || $stat['size'] > self::MAX_DOCX_XML_SIZE) {
                throw new \moodle_exception('err_docxinvalid', 'local_aiquestiongen');
            }
            $xml = $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }
        if ($xml === false || $xml === '') {
            throw new \moodle_exception('err_docxinvalid', 'local_aiquestiongen');
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // No LIBXML_NOENT and no network access: do not expand external entities.
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new \moodle_exception('err_docxinvalid', 'local_aiquestiongen');
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORD_NS);

        $lines = [];
        foreach ($xpath->query('//w:p') as $paragraph) {
            $line = '';
            foreach ($xpath->query('.//w:t | .//w:tab | .//w:br', $paragraph) as $node) {
                if ($node->localName === 't') {
                    $line .= $node->textContent;
                } else if ($node->localName === 'tab') {
                    $line .= "\t";
                } else {
                    $line .= "\n";
                }
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Extract text from a PDF using the configured pdftotext binary.
     *
     * @param string $path File path.
     * @param string|null $binary Path to pdftotext; null uses the plugin setting.
     * @return string Text.
     */
    protected static function extract_pdf(string $path, ?string $binary): string {
        if ($binary === null) {
            $binary = (string) get_config('local_aiquestiongen', 'pdftotextpath');
        }
        $binary = trim($binary);
        if ($binary === '' || !is_executable($binary)) {
            throw new \moodle_exception('err_pdfnotconfigured', 'local_aiquestiongen');
        }

        // Array form of proc_open: no shell is involved, so no escaping issues.
        $command = [$binary, '-enc', 'UTF-8', '-nopgbrk', $path, '-'];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new \moodle_exception('err_pdffailed', 'local_aiquestiongen');
        }
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        if ($status !== 0 || $output === false) {
            throw new \moodle_exception('err_pdffailed', 'local_aiquestiongen');
        }
        return $output;
    }

    /**
     * Convert raw bytes to UTF-8, detecting common Japanese and Western encodings.
     *
     * @param string $raw Raw file content.
     * @return string UTF-8 text.
     */
    protected static function to_utf8(string $raw): string {
        // Strip UTF-8 BOM.
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            $raw = substr($raw, 3);
        }
        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }
        $detected = mb_detect_encoding($raw, ['SJIS-win', 'EUC-JP', 'ISO-8859-1'], true);
        return mb_convert_encoding($raw, 'UTF-8', $detected ?: 'ISO-8859-1');
    }

    /**
     * Normalise line endings and whitespace and remove control characters.
     *
     * @param string $text Raw extracted text.
     * @return string Clean text.
     */
    public static function normalise_text(string $text): string {
        $text = str_replace(["\r\n", "\r", "\xC2\xA0"], ["\n", "\n", ' '], $text);
        // Remove control characters except tab and newline.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }
}
