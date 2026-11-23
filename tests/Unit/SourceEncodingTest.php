<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guards against "mojibake": text like "â€”" that appears when a UTF-8 file is saved with the
 * wrong encoding (found in the transfer form during manual testing, 2026-11-24).
 */
class SourceEncodingTest extends TestCase
{
    public function test_source_files_have_no_garbled_characters(): void
    {
        $root = dirname(__DIR__, 2);
        $bad = [];

        foreach (['app', 'resources', 'routes', 'config', 'database'] as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (preg_match('/\.(php|js|css)$/', $file->getFilename())
                    && preg_match('/\xC3\xA2\xE2\x82\xAC|\xC3\x83/', file_get_contents($file->getPathname()))) {
                    $bad[] = substr($file->getPathname(), strlen($root) + 1);
                }
            }
        }

        $this->assertSame([], $bad, 'Garbled characters found in: '.implode(', ', $bad));
    }
}
