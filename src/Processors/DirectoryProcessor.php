<?php

declare(strict_types=1);

namespace WpNx\Handler\Processors;

use WpNx\Handler\Configuration;
use WpNx\Handler\Processors\ProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Directory processor
 *
 * Handles directory requests by looking for index files.
 */
class DirectoryProcessor implements ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null
    {
        // Check PHP_SELF first (set by MultisiteProcessor), fallback to PATH_INFO
        $path = $request->server->get('PHP_SELF') ?: $request->getPathInfo();
        $webRoot = $config->get('web_root');
        $fullPath = $webRoot . $path;

        if (!is_dir($fullPath)) {
            return null;
        }

        // Look for index files
        $indexFiles = $config->get('index_files', ['index.php', 'index.html', 'index.htm']);

        foreach ($indexFiles as $index) {
            $indexFilePath = rtrim($fullPath, '/') . '/' . $index;

            if (is_file($indexFilePath)) {
                // Found an index file - update PHP_SELF to point to it
                $indexUrl = rtrim($path, '/') . '/' . $index;

                $request->server->set('PHP_SELF', $indexUrl);

                // Store that this was a directory request
                $request->attributes->set('directory_index', true);

                // Return null to let the appropriate processor handle it
                // PhpFileProcessor will handle .php files
                // StaticFileProcessor will handle .html/.htm files
                return null;
            }
        }

        // No index file found
        if ($config->get('security.allow_directory_listing', false)) {
            return $this->generateDirectoryListing($fullPath, $path);
        }

        return null; // Continue to next processor
    }

    /**
     * Generate a simple directory listing
     */
    private function generateDirectoryListing(string $dir, string $path): Response
    {
        $files = scandir($dir);
        if ($files === false) {
            return new Response('Directory listing failed', 500);
        }
        $html = sprintf('<h1>Index of %s</h1><ul>', htmlspecialchars($path));

        foreach ($files as $file) {
            if ($file === '.') {
                continue;
            }

            $displayName = htmlspecialchars($file);
            $href = htmlspecialchars($file);

            if ($file === '..') {
                // Parent directory link
                $parentPath = dirname($path);
                $href = $parentPath === '/' ? '/' : $parentPath . '/';
            }

            $html .= sprintf('<li><a href="%s">%s</a></li>', $href, $displayName);
        }

        $html .= '</ul>';

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
