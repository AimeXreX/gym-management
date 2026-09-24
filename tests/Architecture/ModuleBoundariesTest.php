<?php

namespace Tests\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class ModuleBoundariesTest extends TestCase
{
    public function test_core_must_not_depend_on_commercial_modules(): void
    {
        $violations = $this->filesIn(app_path('Core'))
            ->filter(fn (string $file): bool => preg_match('/App\\\\Modules\\\\/', (string) file_get_contents($file)) === 1)
            ->values()
            ->all();

        $this->assertSame([], $violations, 'App\Core must not reference App\Modules: '.implode(', ', $violations));
    }

    public function test_core_must_not_depend_on_http_controllers(): void
    {
        $violations = $this->filesIn(app_path('Core'))
            ->filter(fn (string $file): bool => preg_match('/App\\\\Http\\\\Controllers\\\\/', (string) file_get_contents($file)) === 1)
            ->values()
            ->all();

        $this->assertSame([], $violations, 'App\Core must not reference App\Http\Controllers: '.implode(', ', $violations));
    }

    public function test_modules_must_not_depend_on_other_modules_presentation_layer(): void
    {
        $violations = [];

        foreach (glob(app_path('Modules/*'), GLOB_ONLYDIR) as $moduleDir) {
            $moduleName = basename($moduleDir);
            foreach ($this->filesIn($moduleDir) as $file) {
                $content = (string) file_get_contents($file);
                foreach (glob(app_path('Modules/*'), GLOB_ONLYDIR) as $other) {
                    $otherName = basename($other);
                    if ($otherName === $moduleName) {
                        continue;
                    }
                    $presentationNamespace = 'App\\Modules\\'.$otherName.'\\Presentation\\';
                    if (str_contains($content, $presentationNamespace)) {
                        $violations[] = $file;
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($violations)), 'Modules must not depend on another module\'s Presentation layer; use its Application API instead: '.implode(', ', array_unique($violations)));
    }

    /** @return \Illuminate\Support\Collection<int, string> */
    private function filesIn(string $directory)
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return collect($files);
    }
}
