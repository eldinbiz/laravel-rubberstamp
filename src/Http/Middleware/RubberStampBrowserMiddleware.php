<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

final class RubberStampBrowserMiddleware
{
    /**
     * Handle an incoming request during browser test execution.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Always ensure HTTP method parameter override is active (_method: PUT, DELETE, PATCH, etc.)
        Request::enableHttpMethodParameterOverride();

        if ($this->shouldParseMultipartBody($request)) {
            $this->parseMultipartContent($request);
        }

        // Synchronize spoofed HTTP methods for both multipart and urlencoded requests
        if ($request->isMethod('POST') && $request->has('_method')) {
            $method = strtoupper((string) $request->input('_method'));
            if (in_array($method, ['PUT', 'PATCH', 'DELETE'], true)) {
                $request->setMethod($method);
            }
        }

        return $next($request);
    }

    /**
     * Determine if the incoming request has an unparsed multipart body.
     */
    private function shouldParseMultipartBody(Request $request): bool
    {
        $contentType = $request->header('content-type', '');

        return str_contains($contentType, 'multipart/form-data')
            && $request->isMethod('POST')
            && $request->request->count() === 0
            && ! empty($request->getContent());
    }

    /**
     * Parse raw multipart/form-data content when the test development server fails to populate the request bag.
     */
    private function parseMultipartContent(Request $request): void
    {
        $contentType = $request->header('content-type', '');
        if (! preg_match('/boundary=(?:")?([^";\s]+)(?:")?/', $contentType, $matches)) {
            return;
        }

        $boundary = $matches[1];
        $rawBody = $request->getContent();
        $parts = explode('--'.$boundary, $rawBody);

        $parameters = [];
        $files = [];

        foreach ($parts as $part) {
            $part = ltrim($part, "\r\n");
            if ($part === '' || $part === '--' || $part === "--\r\n") {
                continue;
            }

            if (! str_contains($part, 'Content-Disposition:')) {
                continue;
            }

            if (! preg_match('/name="([^"]+)"/', $part, $nameMatches)) {
                continue;
            }

            $fieldName = $nameMatches[1];

            if (str_contains($part, 'filename=')) {
                if (preg_match('/filename="([^"]*)"/', $part, $filenameMatches)) {
                    $filename = $filenameMatches[1];
                    if ($filename !== '') {
                        preg_match('/Content-Type: ([^\r\n]+)/', $part, $fileTypeMatches);
                        $fileType = $fileTypeMatches[1] ?? 'application/octet-stream';

                        $bodyParts = explode("\r\n\r\n", $part, 2);
                        $fileContent = isset($bodyParts[1]) ? rtrim($bodyParts[1], "\r\n") : '';

                        $tempPath = tempnam(sys_get_temp_dir(), 'browser_upload_');
                        if ($tempPath !== false) {
                            file_put_contents($tempPath, $fileContent);
                            $files[$fieldName] = new UploadedFile($tempPath, $filename, $fileType, null, true);
                        }
                    }
                }
            } else {
                $bodyParts = explode("\r\n\r\n", $part, 2);
                $parameters[$fieldName] = isset($bodyParts[1]) ? rtrim($bodyParts[1], "\r\n") : '';
            }
        }

        if (! empty($parameters)) {
            $request->request->add($parameters);

            if (isset($parameters['_method'])) {
                $request->setMethod((string) $parameters['_method']);
            }
        }

        if (! empty($files)) {
            $request->files->add($files);
        }
    }
}
