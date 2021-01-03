<?php
require(__DIR__ . '/vendor/autoload.php');

define('ROOT', __DIR__);

// listen address, change to 0.0.0.0 to handle public requests
define('ADDR', '127.0.0.1');
define("PORT", 8080);

// use jsdelivr/cnpmjs
define('JSDELIVR', true);
define('CNPMJS', true);

// download size limit, defaults to 2GB
define('SIZE_LIMIT', 2 * 1024 * 1024 * 1024);


$loop = React\EventLoop\Factory::create();

$server = new React\Http\Server($loop, function (Psr\Http\Message\ServerRequestInterface $request) {
    return handler($request);
});

$socket = new React\Socket\Server(ADDR . ':' . PORT, $loop);
$server->listen($socket);

echo 'listen on ', ADDR , ':', PORT, PHP_EOL;
$loop->run();


function handler(Psr\Http\Message\ServerRequestInterface $req) {
    $url = $req->getUri()->getPath();
    // strip start /
    $url = substr($url, 1);
    echo "request url: ", $url, PHP_EOL;

    if (substr($url, 0, 4) !== 'http') {
        return serveStaticFiles($url);
    }

    $exp1 = '/^https?:\/\/?github\.com\/.+?\/.+?\/(?:releases|archive)\/.*$/i';
    $exp2 = '/^https?:\/\/?github\.com\/.+?\/.+?\/(?:blob)\/.*$/i';
    $exp3 = '/^https?:\/\/?github\.com\/.+?\/.+?\/(?:info|git-).*$/i';
    $exp4 = '/^https?:\/\/?raw\.githubusercontent\.com\/.+?\/.+?\/.+?\/.+$/i';
    $exp5 = '/^https?:\/\/?api\.github\.com\/.+?\/.+?\/.+?\/.+$/i';
    if (preg_match($exp1, $url) || preg_match($exp5, $url) || !CNPMJS && (preg_match($exp3, $url) || preg_match($exp4, $url))) {
        return proxy($req);
    } else if (preg_match($exp2, $url)) {
        if (JSDELIVR){
            $url = str_replace('/blob/', '@', $url);
            $url = preg_replace('/^https?:\/\/github\.com/', 'https://cdn.jsdelivr.net/gh', $url);
            return new React\Http\Message\Response(
                302,
                array(
                    'Location' => $url
                )
            );
        }else{
            $url = str_replace('/blob/', '/raw/', $url);
            return proxy($req);
        }
    } else if (preg_match($exp3, $url)) {
        $url = preg_replace('/^https?:\/\/github\.com/', 'https://github.com.cnpmjs.org', $url);
        return new React\Http\Message\Response(
            302,
            array(
                'Location' => $url
            )
        );
    } else if (preg_match($exp4, $url)) {
        $url = preg_replace('/(com\/.+?\/.+?)\/(.+?\/)/', '$1@$2', $url);
        $url = preg_replace('/^https?:\/\/raw\.githubusercontent\.com/', 'https://cdn.jsdelivr.net/gh', $url);
        return new React\Http\Message\Response(
            302,
            array(
                'Location' => $url
            )
        );
    }

    return new React\Http\Message\Response(
        500,
        array(
        ),
        "<h1>unable to deal with request: $url</h1>"
    );
}

function serveStaticFiles(string $url) {
    $path = ROOT . "/$url";
    $path = realpath($path);
    if (strpos(ROOT, $path) === 0 && is_file($path)) {
        header('Content-Type:' . mime_content_type($path));
        readfile($path);
        return;
    }

    return new React\Http\Message\Response(
        404,
        array(
        ),
        "<h1>File Not Found!</h1>"
    );
}


function proxy(Psr\Http\Message\ServerRequestInterface $req) {
    $requestMethod = $req->getMethod();
    if ($requestMethod === 'OPTIONS' &&
        isset($req->header['access-control-request-headers'])) {
            return new React\Http\Message\Response(
                204,
                array(
                    'access-control-allow-origin' => '*',
                    'access-control-allow-methods' => 'GET,POST,PUT,PATCH,TRACE,DELETE,HEAD,OPTIONS',
                    'access-control-max-age' => '1728000',
                )
            );
    }
    
    return fetch($req);
}

function fetch(Psr\Http\Message\ServerRequestInterface $req) {
    global $loop;

    $url = $req->getUri()->getPath();
    // strip start /
    $url = substr($url, 1);

    $client = new React\Http\Browser($loop);

    // see https://github.com/reactphp/http#streaming-response
    return $client->requestStreaming($req->getMethod(), $url)->then(function (Psr\Http\Message\ResponseInterface $response) {
        $headers = $response->getHeaders();
        $body = $response->getBody();
        assert($body instanceof Psr\Http\Message\StreamInterface);
        assert($body instanceof React\Stream\ReadableStreamInterface);

        if (isset($headers['Content-Length']) && intval($headers['Content-Length'][0]) > SIZE_LIMIT) {
            echo 'length: ', normalizeSize(intval($headers['Content-Length'][0])), "exceed limit", PHP_EOL;
            return new React\Http\Message\Response(
                503
            );
        }

        $headers['access-control-expose-headers'] = '*';
        $headers['access-control-allow-origin'] = '*';
        unset($headers['content-security-policy']);
        unset($headers['content-security-policy-report-only']);
        unset($headers['clear-site-data']);
        $res = new React\Http\Message\Response(
            200,
            $headers
        );

        return $res->withBody($body);
    });
}

function normalizeSize(int $size) {
    $BASE = 1024;

    $units = [
        'KB',
        'MB',
        'GB',
    ];

    foreach ($units as $unit) {
        $size /= $BASE;
        if ($size < 1024) {
            return round($size, 2) . $unit;
        }
    }

    return round($size, 2) . 'GB';
}