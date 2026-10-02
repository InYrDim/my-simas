<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/*
 * File uploads in browser tests. The plugin's in-process server hands the
 * application a multipart request as a raw body only: no fields, no files
 * (`@TODO files...` in its LaravelHttpServer). A real server parses it, so
 * this is a gap of the test server, not of the application.
 */

/**
 * Make the application see the fields and files of multipart requests for
 * the rest of the test. Call it before the page that uploads.
 */
function acceptBrowserUploads(): void
{
    app(Kernel::class)->prependMiddleware(function (Request $request, Closure $next) {
        $contentType = (string) $request->headers->get('content-type');

        if (preg_match('#^multipart/form-data;.*boundary=("?)([^";]+)\1#i', $contentType, $boundary) !== 1) {
            return $next($request);
        }

        foreach (explode('--'.$boundary[2], (string) $request->getContent()) as $part) {
            [$head, $body] = explode("\r\n\r\n", $part, 2) + [1 => null];

            if ($body === null || preg_match('/\bname="([^"]*)"/', $head, $name) !== 1) {
                continue;
            }

            $body = (string) preg_replace('/\r\n$/', '', $body);

            if (preg_match('/\bfilename="([^"]*)"/', $head, $fileName) !== 1) {
                $request->request->set($name[1], $body);

                continue;
            }

            $path = (string) tempnam(sys_get_temp_dir(), 'upload');
            file_put_contents($path, $body);

            $request->files->set($name[1], new UploadedFile($path, $fileName[1], test: true));
        }

        return $next($request);
    });
}
