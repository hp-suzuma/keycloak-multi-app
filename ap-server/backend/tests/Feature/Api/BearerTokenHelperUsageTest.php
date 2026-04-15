<?php

namespace Tests\Feature\Api;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class BearerTokenHelperUsageTest extends TestCase
{
    public function test_feature_api_tests_do_not_inline_bearer_authorization_headers(): void
    {
        $apiTestDirectory = __DIR__;
        $currentFile = realpath(__FILE__);
        $violations = [];

        $patterns = [
            '/withHeader\s*\(\s*[\'"]Authorization[\'"]\s*,\s*[\'"]Bearer\s*\./',
            '/withHeaders\s*\(\s*\[[\s\S]*?[\'"]Authorization[\'"]\s*=>\s*[\'"]Bearer\s*\./',
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($apiTestDirectory)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getRealPath();

            if ($path === false || $path === $currentFile) {
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
            "Feature API tests should use withAccessToken() or withBearerToken() instead of inlining Bearer Authorization headers.\nViolations:\n- ".implode("\n- ", $violations),
        );
    }
}
