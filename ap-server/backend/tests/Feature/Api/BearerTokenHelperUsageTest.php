<?php

namespace Tests\Feature\Api;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class BearerTokenHelperUsageTest extends TestCase
{
    public function test_feature_api_tests_do_not_inline_bearer_authorization_headers(): void
    {
        $testDirectory = base_path('tests');
        $currentFile = realpath(__FILE__);
        $violations = [];

        $patterns = [
            '/withHeader\s*\(\s*[\'"]Authorization[\'"]\s*,\s*(?:[\'"]Bearer\s*\.[^,\)]*|\s*"Bearer\s+\$[A-Za-z_][A-Za-z0-9_]*"|\s*"Bearer\s+\{\$[A-Za-z_][A-Za-z0-9_]*\}")/',
            '/withHeaders\s*\(\s*\[[\s\S]*?[\'"]Authorization[\'"]\s*=>\s*(?:[\'"]Bearer\s*\.[^\]\n]*|\s*"Bearer\s+\$[A-Za-z_][A-Za-z0-9_]*"|\s*"Bearer\s+\{\$[A-Za-z_][A-Za-z0-9_]*\}")/',
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($testDirectory)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getRealPath();

            if ($path === false || $path === $currentFile) {
                continue;
            }

            if (str_contains($path, DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Concerns'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $contents = file_get_contents($path);

            if ($contents === false) {
                $this->fail(sprintf('Failed to read [%s].', $path));
            }

            foreach ($patterns as $pattern) {
                if (! preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($matches[0] as [, $offset]) {
                    $violations[] = sprintf(
                        '%s:%d',
                        str_replace(base_path().DIRECTORY_SEPARATOR, '', $path),
                        substr_count(substr($contents, 0, $offset), PHP_EOL) + 1,
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Backend tests should use withAccessToken() or withBearerToken() instead of inlining Bearer Authorization headers, including string interpolation forms.\nViolations:\n- ".implode("\n- ", $violations),
        );
    }
}
