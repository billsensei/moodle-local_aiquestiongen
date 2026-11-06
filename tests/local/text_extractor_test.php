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
 * Tests for the text extractor.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_aiquestiongen\local\text_extractor::class)]
final class text_extractor_test extends \advanced_testcase {
    /**
     * Write a file into a request directory and return its path.
     *
     * @param string $name File name.
     * @param string $content File content.
     * @return string Path.
     */
    private function make_file(string $name, string $content): string {
        $path = make_request_directory() . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * Plain text: BOM and CRLF are removed, whitespace is normalised.
     */
    public function test_txt(): void {
        $path = $this->make_file('a.txt', "\xEF\xBB\xBFHello\r\nWorld  \r\n\r\n\r\n\r\nEnd\n");
        $this->assertSame("Hello\nWorld\n\nEnd", text_extractor::extract_from_path($path, 'txt'));
    }

    /**
     * Shift-JIS text is converted to UTF-8.
     */
    public function test_txt_sjis(): void {
        $path = $this->make_file('a.txt', mb_convert_encoding('日本語の文章', 'SJIS-win', 'UTF-8'));
        $this->assertSame('日本語の文章', text_extractor::extract_from_path($path, 'txt'));
    }

    /**
     * HTML: tags, scripts and styles are removed and entities decoded.
     */
    public function test_html(): void {
        $html = '<html><head><title>T</title><style>p{}</style></head><body><script>alert(1)</script>'
            . '<p>First &amp; second</p><p>Line<br>break</p></body></html>';
        $path = $this->make_file('a.html', $html);
        $this->assertSame("First & second\n\nLine\nbreak", text_extractor::extract_from_path($path, 'html'));
    }

    /**
     * DOCX: paragraphs are extracted from word/document.xml.
     */
    public function test_docx(): void {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            . '<w:p><w:r><w:t>Hello </w:t></w:r><w:r><w:t>world &amp; &lt;b&gt;</w:t></w:r></w:p>'
            . '<w:p><w:r><w:t>Second</w:t><w:br/><w:t>line</w:t></w:r></w:p>'
            . '</w:body></w:document>';
        $path = make_request_directory() . '/a.docx';
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::CREATE));
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        $this->assertSame("Hello world & <b>\nSecond\nline", text_extractor::extract_from_path($path, 'docx'));
    }

    /**
     * A zip without word/document.xml is rejected.
     */
    public function test_docx_invalid(): void {
        $path = make_request_directory() . '/bad.docx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('other.txt', 'x');
        $zip->close();

        $this->expectException(\moodle_exception::class);
        text_extractor::extract_from_path($path, 'docx');
    }

    /**
     * PDF extraction fails cleanly when pdftotext is not configured.
     */
    public function test_pdf_not_configured(): void {
        $this->resetAfterTest();
        set_config('pdftotextpath', '', 'local_aiquestiongen');
        $path = $this->make_file('a.pdf', '%PDF-1.4');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('err_pdfnotconfigured', 'local_aiquestiongen'));
        text_extractor::extract_from_path($path, 'pdf');
    }

    /**
     * Unsupported extensions are rejected.
     */
    public function test_unsupported_type(): void {
        $path = $this->make_file('a.exe', 'x');
        $this->expectException(\moodle_exception::class);
        text_extractor::extract_from_path($path, 'exe');
    }

    /**
     * Extraction from a stored_file uses the filename extension.
     */
    public function test_extract_from_stored_file(): void {
        $this->resetAfterTest();
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_aiquestiongen',
            'filearea' => 'source',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'article.txt',
        ], "Stored\r\ncontent");

        $this->assertSame("Stored\ncontent", text_extractor::extract_from_file($file));
    }
}
