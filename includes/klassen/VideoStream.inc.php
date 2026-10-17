<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

/**
 * High-Performance HTTP Range Video Streamer (EroCloud)
 *
 * Optimiert für sofortigen Start, flüssiges Seeking ohne Ruckler/Frame-Drops
 * und volle TV-Streaming-Kompatibilität (Apple AirPlay, Google Cast / Chromecast, Smart-TVs).
 * Strikt PHP 7.3+ kompatibel.
 */
class VideoStream
{
    /** @var string */
    private $path = "";

    /** @var string */
    private $mime = "";

    /** @var resource|false|null */
    private $stream = null;

    /**
     * 512 KB Chunk-Größe (524288 Bytes):
     * Optimaler Kompromiss aus geringem Syscall-Overhead, schneller Time-To-First-Frame
     * beim Seeking und stabilem Durchsatz für 1080p/4K-Videos.
     *
     * @var int
     */
    private $buffer = 524288;

    /** @var int */
    private $start = 0;

    /** @var int */
    private $end = -1;

    /** @var int */
    private $size = 0;

    /** @var int */
    private $mtime = 0;

    /** @var string */
    private $etag = "";

    /**
     * @param string $filePath
     * @param string $mime
     */
    public function __construct($filePath, $mime = 'video/mp4')
    {
        $this->path = (string)$filePath;
        $this->mime = $mime !== '' ? (string)$mime : 'video/mp4';
    }

    /**
     * Öffnet den Datei-Stream im Binärmodus und deaktiviert PHPs internen 8KB-Read-Buffer.
     *
     * @return void
     */
    private function open()
    {
        if (!is_file($this->path) || !is_readable($this->path)) {
            $this->sendError(404, 'Video file not found');
        }

        $this->stream = @fopen($this->path, 'rb');
        if ($this->stream === false) {
            $this->sendError(500, 'Could not open stream for reading');
        }

        if (function_exists('stream_set_read_buffer')) {
            @stream_set_read_buffer($this->stream, 0);
        }
        if (function_exists('stream_set_chunk_size')) {
            @stream_set_chunk_size($this->stream, $this->buffer);
        }
    }

    /**
     * Sendet einen HTTP-Fehlercode und beendet das Skript sauber.
     *
     * @param int    $code
     * @param string $message
     * @return void
     */
    private function sendError($code, $message = '')
    {
        if (is_resource($this->stream)) {
            @fclose($this->stream);
            $this->stream = null;
        }
        if (!headers_sent()) {
            http_response_code((int)$code);
            header('Content-Type: text/plain; charset=UTF-8');
        }
        exit($message);
    }

    /**
     * Deaktiviert sämtliche Output-Buffer und Komprimierungen (gzip/deflate/zlib),
     * damit Video-Bytes unverzögert und unkomprimiert an den Browser/TV gestreamt werden.
     *
     * @return void
     */
    private function disableOutputBuffering()
    {
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        ob_implicit_flush(true);
    }

    /**
     * Sendet CORS-Header für Smart-TVs, Google Cast (Chromecast) & Apple AirPlay.
     *
     * @return void
     */
    public static function sendCorsHeaders()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Access-Control-Allow-Headers: Range, If-Range, Content-Type, Origin, Accept');
        header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges, ETag');
    }

    /**
     * Setzt alle RFC 7233-konformen HTTP- und Range-Header.
     *
     * @return bool False wenn kein Body gesendet werden soll (z. B. HEAD oder 304 Not Modified)
     */
    private function setHeader()
    {
        $this->disableOutputBuffering();

        clearstatcache(true, $this->path);
        $fileSize = @filesize($this->path);
        if ($fileSize === false || $fileSize < 0) {
            $this->sendError(404, 'Unable to determine video size');
        }

        $this->size  = (int)$fileSize;
        $this->mtime = (int)@filemtime($this->path);
        if ($this->mtime <= 0) {
            $this->mtime = time();
        }
        $this->start = 0;
        $this->end   = $this->size > 0 ? ($this->size - 1) : 0;
        $this->etag  = '"' . sprintf('%x-%x', $this->mtime, $this->size) . '"';

        $lastModified = gmdate('D, d M Y H:i:s', $this->mtime) . ' GMT';

        // Restriktive Session-No-Cache-Header entfernen, damit Browser & TV Chunks im Puffer halten dürfen
        if (function_exists('header_remove')) {
            @header_remove('Pragma');
            @header_remove('Expires');
            @header_remove('Cache-Control');
        }

        self::sendCorsHeaders();

        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : 'GET';
        if ($method === 'OPTIONS') {
            http_response_code(204);
            header('Content-Length: 0');
            return false;
        }

        header('Content-Type: ' . $this->mime);
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=86400');
        header('Last-Modified: ' . $lastModified);
        header('ETag: ' . $this->etag);
        header('X-Accel-Buffering: no');
        header('Content-Disposition: inline');

        $rangeHeader = isset($_SERVER['HTTP_RANGE']) ? trim((string)$_SERVER['HTTP_RANGE']) : '';

        // If-Range Validierung: Wenn sich die Datei geändert hat, kompletten Stream senden (200 OK)
        if ($rangeHeader !== '' && isset($_SERVER['HTTP_IF_RANGE'])) {
            $ifRange = trim((string)$_SERVER['HTTP_IF_RANGE']);
            if ($ifRange !== $this->etag && $ifRange !== $lastModified) {
                $rangeHeader = '';
            }
        }

        // 304 Not Modified Unterstützung für nicht-Range-Anfragen
        if ($rangeHeader === '') {
            $ifNoneMatch     = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string)$_SERVER['HTTP_IF_NONE_MATCH']) : '';
            $ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? trim((string)$_SERVER['HTTP_IF_MODIFIED_SINCE']) : '';
            if ($ifNoneMatch === $this->etag || ($ifNoneMatch === '' && $ifModifiedSince === $lastModified)) {
                http_response_code(304);
                return false;
            }
        }

        if ($this->size === 0) {
            http_response_code(200);
            header('Content-Length: 0');
            return false;
        }

        if ($rangeHeader !== '') {
            if (stripos($rangeHeader, 'bytes=') !== 0) {
                $this->sendRangeNotSatisfiable();
            }

            $rangeSpec = trim(substr($rangeHeader, 6));
            // Multi-Range-Requests auf den ersten Range reduzieren
            if (strpos($rangeSpec, ',') !== false) {
                $parts     = explode(',', $rangeSpec);
                $rangeSpec = trim($parts[0]);
            }

            $dashPos = strpos($rangeSpec, '-');
            if ($dashPos === false) {
                $this->sendRangeNotSatisfiable();
            }

            $startRaw = trim(substr($rangeSpec, 0, $dashPos));
            $endRaw   = trim(substr($rangeSpec, $dashPos + 1));

            if ($startRaw === '') {
                // Suffix-Byte-Range: "bytes=-500" -> letzte 500 Bytes
                if ($endRaw === '' || !ctype_digit($endRaw)) {
                    $this->sendRangeNotSatisfiable();
                }
                $suffixLength = (int)$endRaw;
                if ($suffixLength <= 0) {
                    $this->sendRangeNotSatisfiable();
                }
                $cStart = max(0, $this->size - $suffixLength);
                $cEnd   = $this->size - 1;
            } else {
                if (!ctype_digit($startRaw)) {
                    $this->sendRangeNotSatisfiable();
                }
                $cStart = (int)$startRaw;
                if ($endRaw === '') {
                    $cEnd = $this->size - 1;
                } else {
                    if (!ctype_digit($endRaw)) {
                        $this->sendRangeNotSatisfiable();
                    }
                    $cEnd = min((int)$endRaw, $this->size - 1);
                }
            }

            if ($cStart < 0 || $cStart > $cEnd || $cStart >= $this->size) {
                $this->sendRangeNotSatisfiable();
            }

            $this->start = $cStart;
            $this->end   = $cEnd;
            $length      = $this->end - $this->start + 1;

            if ($this->start > 0 && @fseek($this->stream, $this->start, SEEK_SET) !== 0) {
                $this->sendError(500, 'Could not seek to requested byte range');
            }

            http_response_code(206);
            header('Content-Range: bytes ' . $this->start . '-' . $this->end . '/' . $this->size);
            header('Content-Length: ' . $length);
        } else {
            http_response_code(200);
            header('Content-Length: ' . $this->size);
        }

        // Bei HEAD-Requests nur die Header senden, keinen Body streamen
        if ($method === 'HEAD') {
            return false;
        }

        return true;
    }

    /**
     * Sendet 416 Range Not Satisfiable gemäß RFC 7233.
     *
     * @return void
     */
    private function sendRangeNotSatisfiable()
    {
        if (is_resource($this->stream)) {
            @fclose($this->stream);
            $this->stream = null;
        }
        http_response_code(416);
        header('Content-Range: bytes */' . $this->size);
        header('Content-Length: 0');
        exit;
    }

    /**
     * Schließt den Datei-Stream und beendet die Ausführung.
     *
     * @return void
     */
    private function end()
    {
        if (is_resource($this->stream)) {
            @fclose($this->stream);
            $this->stream = null;
        }
        exit;
    }

    /**
     * Streamt den angeforderten Byte-Bereich exakt anhand der tatsächlich gelesenen Bytes.
     *
     * WICHTIG: Zählt strlen($data) statt $bytesToRead, damit Short-Reads auf Netzwerk-/Storage-Mounts
     * niemals zu abgeschnittenen Chunks oder übersprungenen Video-Frames führen.
     *
     * @return void
     */
    private function stream()
    {
        @set_time_limit(0);
        @ignore_user_abort(false);

        $bytesRemaining = $this->end - $this->start + 1;

        while ($bytesRemaining > 0 && !feof($this->stream)) {
            if (connection_aborted() || connection_status() !== CONNECTION_NORMAL) {
                break;
            }

            $readLength = $bytesRemaining > $this->buffer ? $this->buffer : $bytesRemaining;
            $data       = @fread($this->stream, $readLength);

            if ($data === false || $data === '') {
                break;
            }

            $bytesRead = strlen($data);
            echo $data;
            flush();

            $bytesRemaining -= $bytesRead;
        }
    }

    /**
     * Startet das Video-Streaming.
     *
     * @return void
     */
    public function start()
    {
        // Falls eine PHP-Session noch offen ist, sofort freigeben, damit parallele Range-Requests nicht blockieren
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        $this->open();
        $shouldStreamBody = $this->setHeader();
        if ($shouldStreamBody) {
            $this->stream();
        }
        $this->end();
    }
}